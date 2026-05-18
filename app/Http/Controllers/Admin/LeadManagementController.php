<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityType;
use App\Exports\LeadImportSampleExport;
use App\Exports\LeadsExport;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
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
        $academicYears  = AcademicYear::orderBy('id', 'desc')->get();
        $academicYearId = AcademicYear::current()?->id;
        return view('admin.leads.import', compact('academicYears', 'academicYearId'));
    }

    public function downloadSample()
    {
        return Excel::download(new LeadImportSampleExport, 'lead_import_sample.xlsx');
    }

    public function importPreview(Request $request)
    {
        $request->validate([
            'file'             => 'required|mimes:xlsx,csv',
            'academic_year_id' => 'required|exists:academic_years,id',
        ]);

        $academicYears    = AcademicYear::orderBy('id', 'desc')->get();
        $academicYearId   = $request->academic_year_id;

        $data = Excel::toArray([], $request->file('file'));
        $rows = $data[0] ?? [];
        if (!empty($rows)) array_shift($rows);

        $rows = array_values(array_filter($rows, fn($r) =>
            !empty(trim((string) ($r[0] ?? ''))) || !empty(trim((string) ($r[1] ?? '')))));

        // Batch DB lookups for phone and email
        $inPhones = collect($rows)->pluck(1)->filter()
            ->map(fn($p) => preg_replace('/\D+/', '', (string) $p))->filter()->unique()->values()->toArray();
        $inEmails = collect($rows)->pluck(2)->filter()
            ->map(fn($e) => strtolower(trim((string) $e)))->filter()->unique()->values()->toArray();

        $dbPhones = Lead::whereIn('phone', $inPhones)->pluck('phone')
            ->map(fn($p) => preg_replace('/\D+/', '', (string) $p))->flip()->toArray();
        $dbEmails = !empty($inEmails)
            ? Lead::whereIn('email', $inEmails)->pluck('email')
                ->map(fn($e) => strtolower(trim((string) $e)))->flip()->toArray()
            : [];

        $seenPhones = [];
        $seenEmails = [];

        $enriched = array_map(function ($row) use ($dbPhones, $dbEmails, &$seenPhones, &$seenEmails) {
            $phone  = preg_replace('/\D+/', '', (string) ($row[1] ?? ''));
            $email  = strtolower(trim((string) ($row[2] ?? '')));
            $dup    = false;
            $reason = '';

            if ($phone !== '' && isset($dbPhones[$phone])) {
                $dup = true; $reason = 'Phone exists in database';
            } elseif ($email !== '' && isset($dbEmails[$email])) {
                $dup = true; $reason = 'Email exists in database';
            } elseif ($phone !== '' && isset($seenPhones[$phone])) {
                $dup = true; $reason = 'Phone repeated in file';
            } elseif ($email !== '' && isset($seenEmails[$email])) {
                $dup = true; $reason = 'Email repeated in file';
            }

            if (!$dup) {
                if ($phone !== '') $seenPhones[$phone] = true;
                if ($email !== '') $seenEmails[$email] = true;
            }

            return ['row' => $row, 'duplicate' => $dup, 'duplicate_reason' => $reason];
        }, $rows);

        $duplicateCount = collect($enriched)->filter(fn($e) => $e['duplicate'])->count();
        $cleanRows      = collect($enriched)->reject(fn($e) => $e['duplicate'])->pluck('row')->values()->toArray();

        return view('admin.leads.import', compact('enriched', 'duplicateCount', 'cleanRows', 'academicYears', 'academicYearId'));
    }

    public function importStore(Request $request)
    {
        $request->validate(['academic_year_id' => 'required|exists:academic_years,id']);

        $rows           = json_decode((string) $request->input('leads_data'), true) ?: [];
        $academicYearId = $request->academic_year_id;

        // Safety-net duplicate check (catches race conditions after preview)
        $inPhones = collect($rows)->pluck(1)->filter()
            ->map(fn($p) => preg_replace('/\D+/', '', (string) $p))->filter()->unique()->values()->toArray();
        $inEmails = collect($rows)->pluck(2)->filter()
            ->map(fn($e) => strtolower(trim((string) $e)))->filter()->unique()->values()->toArray();

        $dbPhones = Lead::whereIn('phone', $inPhones)->pluck('phone')
            ->map(fn($p) => preg_replace('/\D+/', '', (string) $p))->flip()->toArray();
        $dbEmails = !empty($inEmails)
            ? Lead::whereIn('email', $inEmails)->pluck('email')
                ->map(fn($e) => strtolower(trim((string) $e)))->flip()->toArray()
            : [];

        $seenPhones    = [];
        $seenEmails    = [];
        $importedCount = 0;
        $skippedCount  = 0;

        foreach ($rows as $row) {
            if (empty($row[0]) || empty($row[1])) continue;

            $phone = preg_replace('/\D+/', '', (string) ($row[1] ?? ''));
            $email = strtolower(trim((string) ($row[2] ?? '')));

            $isDuplicate = ($phone !== '' && isset($dbPhones[$phone]))
                || ($email !== '' && isset($dbEmails[$email]))
                || ($phone !== '' && isset($seenPhones[$phone]))
                || ($email !== '' && isset($seenEmails[$email]));

            if ($isDuplicate) {
                $skippedCount++;
                continue;
            }

            if ($phone !== '') $seenPhones[$phone] = true;
            if ($email !== '') $seenEmails[$email] = true;

            $courseId = isset($row[3]) && $row[3] !== ''
                ? Course::where('name', trim($row[3]))->value('id')
                : null;

            $lead = Lead::create([
                'lead_code'        => LeadCodeGenerator::placeholder(),
                'name'             => $row[0],
                'phone'            => $row[1],
                'email'            => $row[2] ?? null,
                'course_id'        => $courseId,
                'academic_year_id' => $academicYearId,
                'source'           => $row[4] ?? 'manual',
                'status'           => LeadDefaults::defaultStatus(),
                'assigned_by'      => Auth::id(),
            ]);
            LeadCodeGenerator::assignCode($lead);

            LeadActivity::create([
                'lead_id'       => $lead->id,
                'user_id'       => Auth::id(),
                'type'          => 'note',
                'description'   => 'Lead imported by admin',
                'activity_time' => now(),
            ]);

            $importedCount++;
        }

        $msg = "{$importedCount} lead(s) imported successfully.";
        if ($skippedCount > 0) $msg .= " {$skippedCount} duplicate(s) skipped.";

        return redirect()->route('admin.leads.all')->with('success', $msg);
    }

    public function export(Request $request)
    {
        $filters = $request->only([
            'search', 'manager_id', 'telecaller_id', 'status', 'date_range', 'date_from', 'date_to',
            'course_id', 'academic_year_id', 'quota', 'source', 'gender',
            'state', 'city', 'district', 'followup', 'no_activity_days',
            'sla', 'is_duplicate', 'is_active', 'aged_min', 'aged_max',
        ]);

        if ($request->query('format') === 'pdf') {
            $leads = LeadsExport::buildQuery($filters)->get();

            $headers = ['Lead Code', 'Name', 'Phone', 'Email', 'Course', 'Acad. Year', 'Quota', 'Source', 'Gender', 'State', 'City', 'Status', 'Manager', 'Telecaller', 'Duplicate', 'Active', 'Days Aged', 'Created'];
            $rows = $leads->map(fn($l) => [
                $l->lead_code, $l->name, $l->phone, $l->email ?? '',
                $l->course ?? '', $l->academicYear?->name ?? '', $l->quota ? ucfirst($l->quota) : '',
                $l->source ?? '', $l->gender ? ucfirst($l->gender) : '',
                $l->state ?? '', $l->city ?? '',
                ucfirst(str_replace('_', ' ', $l->status)),
                $l->assignedBy?->name ?? '—', $l->assignedUser?->name ?? '—',
                $l->is_duplicate ? 'Yes' : 'No', $l->is_active ? 'Yes' : 'No',
                $l->days_aged, $l->created_at->format('d M Y'),
            ])->all();

            return view('admin.reports.print', [
                'title'   => 'Leads Export — ' . now()->format('d M Y'),
                'headers' => $headers,
                'rows'    => $rows,
            ]);
        }

        AuditLogService::log('lead.exported', 'Lead', null, [], ['filters' => array_filter($filters)]);

        return Excel::download(new LeadsExport($filters), 'admin-leads-' . now()->format('Y-m-d') . '.xlsx');
    }

    private static array $FILTER_KEYS = [
        'search', 'manager_id', 'telecaller_id', 'status', 'date_range', 'date_from', 'date_to',
        'course_id', 'academic_year_id', 'quota', 'source', 'gender',
        'state', 'city', 'district', 'followup', 'no_activity_days',
        'sla', 'is_duplicate', 'is_active', 'aged_min', 'aged_max',
    ];

    private function renderIndex(Request $request, string $scope, string $title)
    {
        $query = Lead::with(['assignedBy:id,name', 'assignedUser:id,name', 'lastActivity', 'enrolledCourse', 'academicYear:id,name']);

        // ── Basic search ──────────────────────────────────────────────────────
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(fn($q) => $q
                ->where('lead_code', 'like', "%{$search}%")
                ->orWhere('name',    'like', "%{$search}%")
                ->orWhere('phone',   'like', "%{$search}%")
                ->orWhere('email',   'like', "%{$search}%")
            );
        }

        // ── Advanced filters ──────────────────────────────────────────────────
        if ($request->filled('manager_id'))       $query->where('assigned_by',       $request->manager_id);
        if ($request->filled('telecaller_id'))    $query->where('assigned_to',        $request->telecaller_id);
        if ($request->filled('status'))           $query->where('status',             $request->status);
        if ($request->filled('course_id'))        $query->where('course_id',          $request->course_id);
        if ($request->filled('academic_year_id')) $query->where('academic_year_id',   $request->academic_year_id);
        if ($request->filled('quota'))            $query->where('quota',              $request->quota);
        if ($request->filled('source'))           $query->where('source',             $request->source);
        if ($request->filled('gender'))           $query->where('gender',             $request->gender);
        if ($request->filled('state'))            $query->where('state',    'like',   '%' . $request->state    . '%');
        if ($request->filled('city'))             $query->where('city',     'like',   '%' . $request->city     . '%');
        if ($request->filled('district'))         $query->where('district', 'like',   '%' . $request->district . '%');

        // Date range
        if ($request->filled('date_range')) {
            if ($request->date_range === 'custom') {
                if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
                if ($request->filled('date_to'))   $query->whereDate('created_at', '<=', $request->date_to);
            } elseif ($request->date_range === 'today') {
                $query->whereDate('created_at', today());
            } elseif (is_numeric($request->date_range)) {
                $query->whereDate('created_at', '>=', now()->subDays((int) $request->date_range));
            }
        }

        // Follow-up
        if ($request->filled('followup')) {
            match ($request->followup) {
                'today'     => $query->whereHas('followups', fn($q) => $q->whereDate('next_followup', today())),
                'overdue'   => $query->whereHas('followups', fn($q) => $q->whereDate('next_followup', '<', today())),
                'this_week' => $query->whereHas('followups', fn($q) => $q
                    ->whereDate('next_followup', '>=', today())
                    ->whereDate('next_followup', '<=', today()->endOfWeek())),
                'none'      => $query->whereDoesntHave('followups'),
                default     => null,
            };
        }

        // No activity in last N days
        if ($request->filled('no_activity_days') && is_numeric($request->no_activity_days)) {
            $cutoff = now()->subDays((int) $request->no_activity_days);
            $recentIds = \App\Models\LeadActivity::where('activity_time', '>=', $cutoff)->distinct()->pluck('lead_id');
            $query->whereNotIn('id', $recentIds);
        }

        // SLA
        if ($request->filled('sla')) {
            if ($request->sla === 'escalated') {
                $query->whereNotNull('sla_escalated_at');
            } elseif (is_numeric($request->sla)) {
                $query->where('sla_level', '>=', (int) $request->sla);
            }
        }

        // Boolean flags
        if ($request->filled('is_duplicate')) $query->where('is_duplicate', (bool) $request->is_duplicate);
        if ($request->filled('is_active'))    $query->where('is_active',    (bool) $request->is_active);

        // Days aged
        if ($request->filled('aged_min') && is_numeric($request->aged_min))
            $query->whereDate('created_at', '<=', now()->subDays((int) $request->aged_min));
        if ($request->filled('aged_max') && is_numeric($request->aged_max))
            $query->whereDate('created_at', '>=', now()->subDays((int) $request->aged_max));

        // ── Scope ─────────────────────────────────────────────────────────────
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
                $duplicatePhones = Lead::select('phone')->whereNotNull('phone')
                    ->groupBy('phone')->havingRaw('COUNT(*) > 1')->pluck('phone');
                $duplicateEmails = Lead::select('email')->whereNotNull('email')->where('email', '!=', '')
                    ->groupBy('email')->havingRaw('COUNT(*) > 1')->pluck('email');
                $query->where(fn($q) => $q
                    ->whereIn('phone', $duplicatePhones)
                    ->orWhereIn('email', $duplicateEmails)
                );
                break;
        }

        $leads       = $query->latest('id')->paginate(15)->withQueryString();
        $managers    = User::where('role', 'manager')->where('status', 1)->orderBy('name')->get(['id', 'name']);
        $telecallers = User::where('role', 'telecaller')->where('status', 1)->orderBy('name')->get(['id', 'name']);
        $courses     = Course::active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $academicYears = AcademicYear::orderByDesc('id')->get(['id', 'name']);
        $sources     = Lead::whereNotNull('source')->where('source', '!=', '')
                        ->distinct()->orderBy('source')->pluck('source');
        $activeFilters = array_filter($request->only(self::$FILTER_KEYS));

        return view('admin.leads.index', compact(
            'leads', 'scope', 'title',
            'managers', 'telecallers',
            'courses', 'academicYears', 'sources',
            'duplicatePhones', 'duplicateEmails',
            'activeFilters'
        ));
    }

}
