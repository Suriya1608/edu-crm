<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityType;
use App\Exports\LeadsExport;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Notifications\LeadAssignmentNotification;
use App\Services\AuditLogService;
use App\Services\LeadCodeGenerator;
use App\Services\LeadDefaults;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

class LeadManagementController extends Controller
{
    public function all(Request $request)
    {
        return $this->renderIndex($request, 'all', 'All Leads');
    }

    public function unassigned(Request $request)
    {
        return $this->renderIndex($request, 'unassigned', 'Unassigned Leads');
    }

    public function assigned(Request $request)
    {
        return $this->renderIndex($request, 'assigned', 'Assigned Leads');
    }

    public function converted(Request $request)
    {
        return $this->renderIndex($request, 'converted', 'Converted Leads');
    }

    public function lost(Request $request)
    {
        return $this->renderIndex($request, 'lost', 'Lost Leads');
    }

    public function duplicates(Request $request)
    {
        return $this->renderIndex($request, 'duplicates', 'Duplicate Leads');
    }

    public function show($encryptedId)
    {
        $id = decrypt($encryptedId);

        $lead = Lead::with(['assignedUser', 'assignedBy', 'activities.user'])
            ->findOrFail($id);

        return view('admin.leads.show', compact('lead'));
    }

    public function assignManager(Request $request, $encryptedId)
    {
        $id = decrypt($encryptedId);

        $request->validate([
            'manager_id' => 'required|exists:users,id',
        ]);

        $lead = Lead::findOrFail($id);
        $manager = User::where('role', 'manager')->findOrFail($request->manager_id);

        $lead->assigned_by = $manager->id;
        $lead->save();

        $manager->notify(new LeadAssignmentNotification(
            title: 'Lead Assigned',
            message: 'Lead ' . ($lead->lead_code ?? ('#' . $lead->id)) . ' assigned to you.',
            link: route('manager.leads.show', encrypt($lead->id)),
            meta: ['type' => 'lead_assignment', 'lead_id' => $lead->id]
        ));

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'type' => 'assignment',
            'description' => "Assigned to manager {$manager->name}",
            'activity_time' => now(),
        ]);

        return back()->with('success', 'Manager assigned successfully.');
    }

    public function reassignTelecaller(Request $request, $encryptedId)
    {
        $id = decrypt($encryptedId);

        $request->validate([
            'telecaller_id' => 'required|exists:users,id',
        ]);

        $lead = Lead::findOrFail($id);
        $telecaller = User::where('role', 'telecaller')->findOrFail($request->telecaller_id);
        $oldTelecaller = $lead->assignedUser?->name;

        $lead->assigned_to = $telecaller->id;
        $lead->status = 'assigned';
        $lead->save();

        $telecaller->notify(new LeadAssignmentNotification(
            title: 'Lead Assigned',
            message: 'Lead ' . ($lead->lead_code ?? ('#' . $lead->id)) . ' assigned to you.',
            link: route('telecaller.leads.show', encrypt($lead->id)),
            meta: ['type' => 'lead_assignment', 'lead_id' => $lead->id]
        ));

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'type' => 'assignment',
            'description' => $oldTelecaller
                ? "Reassigned telecaller from {$oldTelecaller} to {$telecaller->name}"
                : "Assigned telecaller {$telecaller->name}",
            'activity_time' => now(),
        ]);

        return back()->with('success', 'Telecaller assigned successfully.');
    }

    public function bulkAssign(Request $request)
    {
        $request->validate([
            'lead_ids'     => 'required|array|min:1|max:500',
            'lead_ids.*'   => 'required|integer|exists:leads,id',
            'manager_id'   => 'nullable|exists:users,id',
            'telecaller_id' => 'nullable|exists:users,id',
        ]);

        if (!$request->filled('manager_id') && !$request->filled('telecaller_id')) {
            return back()->with('error', 'Please select manager or telecaller for bulk assignment.');
        }

        // Resolve users ONCE before the loop — prevents N+1 on every iteration
        $manager = $request->filled('manager_id')
            ? User::where('role', 'manager')->where('status', 1)->findOrFail($request->manager_id)
            : null;

        $telecaller = $request->filled('telecaller_id')
            ? User::where('role', 'telecaller')->where('status', 1)->findOrFail($request->telecaller_id)
            : null;

        $leads = Lead::whereIn('id', $request->lead_ids)->get();

        foreach ($leads as $lead) {
            $updates      = [];
            $descriptions = [];

            if ($manager) {
                $updates['assigned_by'] = $manager->id;
                $descriptions[]         = 'manager ' . $manager->name;
                $manager->notify(new LeadAssignmentNotification(
                    title:   'Lead Assigned (Bulk)',
                    message: 'Lead ' . ($lead->lead_code ?? ('#' . $lead->id)) . ' assigned to you.',
                    link:    route('manager.leads.show', encrypt($lead->id)),
                    meta:    ['type' => 'lead_assignment', 'lead_id' => $lead->id]
                ));
            }

            if ($telecaller) {
                $updates['assigned_to'] = $telecaller->id;
                $updates['status']      = 'assigned';
                $descriptions[]         = 'telecaller ' . $telecaller->name;
                $telecaller->notify(new LeadAssignmentNotification(
                    title:   'Lead Assigned (Bulk)',
                    message: 'Lead ' . ($lead->lead_code ?? ('#' . $lead->id)) . ' assigned to you.',
                    link:    route('telecaller.leads.show', encrypt($lead->id)),
                    meta:    ['type' => 'lead_assignment', 'lead_id' => $lead->id]
                ));
            }

            $lead->update($updates);

            LeadActivity::create([
                'lead_id'       => $lead->id,
                'user_id'       => Auth::id(),
                'type'          => ActivityType::Assignment->value,
                'description'   => 'Bulk assigned to ' . implode(' and ', $descriptions),
                'activity_time' => now(),
            ]);
        }

        return back()->with('success', 'Bulk assignment completed.');
    }

    public function merge($id, $targetId)
    {
        $sourceId = is_numeric($id) ? (int) $id : decrypt($id);
        $targetLeadId = is_numeric($targetId) ? (int) $targetId : decrypt($targetId);

        if ($sourceId === $targetLeadId) {
            return back()->with('error', 'Cannot merge a lead into itself.');
        }

        $source = Lead::findOrFail($sourceId);
        $target = Lead::findOrFail($targetLeadId);

        DB::transaction(function () use ($source, $target) {
            // Move activities
            LeadActivity::where('lead_id', $source->id)->update(['lead_id' => $target->id]);

            // Move followups
            if (class_exists(\App\Models\Followup::class)) {
                \App\Models\Followup::where('lead_id', $source->id)->update(['lead_id' => $target->id]);
            }

            // Move call logs
            \App\Models\CallLog::where('lead_id', $source->id)->update(['lead_id' => $target->id]);

            // Move WhatsApp messages
            if (Schema::hasTable('whatsapp_messages')) {
                \App\Models\WhatsAppMessage::where('lead_id', $source->id)->update(['lead_id' => $target->id]);
            }

            // Mark source as merged
            $source->update([
                'merged_into_lead_id' => $target->id,
                'status' => 'merged',
            ]);

            // Log activity on target
            LeadActivity::create([
                'lead_id'     => $target->id,
                'user_id'     => Auth::id(),
                'type'        => 'note',
                'description' => "Lead {$source->lead_code} (#{$source->id}) merged into this lead.",
                'activity_time' => now(),
            ]);
        });

        AuditLogService::log('lead.merged', 'Lead', $source->id, ['id' => $source->id], ['merged_into' => $target->id]);

        return back()->with('success', "Lead {$source->lead_code} merged into {$target->lead_code} successfully.");
    }

    public function importForm()
    {
        return view('admin.leads.import');
    }

    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv',
        ]);

        $file = $request->file('file');
        $data = Excel::toArray([], $file);
        $rows = $data[0] ?? [];
        if (!empty($rows)) {
            array_shift($rows);
        }

        return view('admin.leads.import', compact('rows'));
    }

    public function importStore(Request $request)
    {
        $rows = json_decode((string) $request->input('leads_data'), true) ?: [];

        foreach ($rows as $row) {
            if (empty($row[0]) || empty($row[1])) {
                continue;
            }

            $courseId = isset($row[3]) && $row[3] !== ''
                ? Course::where('name', trim($row[3]))->value('id')
                : null;

            $lead = Lead::create([
                'lead_code'   => LeadCodeGenerator::placeholder(),
                'name'        => $row[0],
                'phone'       => $row[1],
                'email'       => $row[2] ?? null,
                'course_id'   => $courseId,
                'source'      => $row[4] ?? 'manual',
                'status'      => LeadDefaults::defaultStatus(),
                'assigned_by' => Auth::id(),
            ]);
            LeadCodeGenerator::assignCode($lead);

            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => Auth::id(),
                'type' => 'note',
                'description' => 'Lead imported by admin',
                'activity_time' => now(),
            ]);
        }

        return redirect()->route('admin.leads.all')
            ->with('success', 'Leads imported successfully.');
    }

    public function export(Request $request)
    {
        if ($request->query('format') === 'pdf') {
            $leads = Lead::with('enrolledCourse')->orderBy('id', 'desc')->get();

            $headers = ['Lead Code', 'Name', 'Phone', 'Email', 'Course', 'Source', 'Status'];
            $rows = $leads->map(fn($l) => [
                $l->lead_code,
                $l->name,
                $l->phone,
                $l->email ?? '',
                $l->course ?? '',
                $l->source ?? '',
                ucfirst(str_replace('_', ' ', $l->status)),
            ])->all();

            return view('admin.reports.print', [
                'title'   => 'Leads Export — ' . now()->format('d M Y'),
                'headers' => $headers,
                'rows'    => $rows,
            ]);
        }

        return Excel::download(new LeadsExport(), 'admin-leads.xlsx');
    }

    private function renderIndex(Request $request, string $scope, string $title)
    {
        $query = Lead::with(['assignedBy:id,name', 'assignedUser:id,name', 'lastActivity', 'enrolledCourse']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('lead_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $duplicatePhones = collect();
        $duplicateEmails = collect();

        switch ($scope) {
            case 'unassigned':
                $query->whereNull('assigned_to');
                break;
            case 'assigned':
                $query->whereNotNull('assigned_to');
                break;
            case 'converted':
                $query->where('status', 'converted');
                break;
            case 'lost':
                $query->where('status', 'not_interested');
                break;
            case 'duplicates':
                $duplicatePhones = Lead::select('phone')
                    ->whereNotNull('phone')
                    ->groupBy('phone')
                    ->havingRaw('COUNT(*) > 1')
                    ->pluck('phone');

                $duplicateEmails = Lead::select('email')
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->groupBy('email')
                    ->havingRaw('COUNT(*) > 1')
                    ->pluck('email');

                $query->where(function ($q) use ($duplicatePhones, $duplicateEmails) {
                    $q->whereIn('phone', $duplicatePhones)
                        ->orWhereIn('email', $duplicateEmails);
                });
                break;
            case 'all':
            default:
                break;
        }

        $leads = $query->latest('id')->paginate(15)->withQueryString();

        $managers = User::where('role', 'manager')->where('status', 1)->orderBy('name')->get(['id', 'name']);
        $telecallers = User::where('role', 'telecaller')->where('status', 1)->orderBy('name')->get(['id', 'name']);

        return view('admin.leads.index', compact('leads', 'scope', 'title', 'managers', 'telecallers', 'duplicatePhones', 'duplicateEmails'));
    }

}
