<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Exports\ManagerLeadsExport;
use App\Models\Lead;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class LeadExportController extends Controller
{
    public function export(Request $request)
    {
        $managerId = Auth::id();
        $filters   = $request->only(['search', 'telecaller', 'status', 'date_range']);

        AuditLogService::log('lead.exported', 'Lead', null, [], [
            'manager_id' => $managerId,
            'filters'    => array_filter($filters),
        ]);

        if ($request->query('format') === 'pdf') {
            $query = Lead::with(['assignedUser', 'enrolledCourse'])
                ->where('assigned_by', $managerId);

            if (! empty($filters['search'])) {
                $s = $filters['search'];
                $query->where(fn($q) => $q
                    ->where('lead_code', 'like', "%{$s}%")
                    ->orWhere('name', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('source', 'like', "%{$s}%")
                    ->orWhereHas('enrolledCourse', fn($cq) => $cq->where('name', 'like', "%{$s}%"))
                );
            }

            if (! empty($filters['telecaller'])) {
                $query->where('assigned_to', $filters['telecaller']);
            }

            if (! empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (! empty($filters['date_range'])) {
                match ($filters['date_range']) {
                    'today' => $query->whereDate('created_at', now()),
                    '7'     => $query->whereDate('created_at', '>=', now()->subDays(7)),
                    '30'    => $query->whereDate('created_at', '>=', now()->subDays(30)),
                    default => null,
                };
            }

            $leads = $query->orderBy('id', 'desc')->get();

            $headers = ['Lead Code', 'Name', 'Phone', 'Email', 'Course', 'Source', 'Status', 'Assigned To', 'Duplicate', 'Created At'];
            $rows = $leads->map(fn($l) => [
                $l->lead_code,
                $l->name,
                $l->phone,
                $l->email ?? '',
                $l->course ?? '',
                $l->source ?? '',
                ucfirst(str_replace('_', ' ', $l->status)),
                $l->assignedUser->name ?? 'Unassigned',
                $l->is_duplicate ? 'Yes' : 'No',
                $l->created_at->format('d M Y H:i'),
            ])->all();

            return view('admin.reports.print', [
                'title'   => 'Leads Export — ' . now()->format('d M Y'),
                'headers' => $headers,
                'rows'    => $rows,
            ]);
        }

        $filename = 'leads_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new ManagerLeadsExport($managerId, $filters), $filename);
    }
}
