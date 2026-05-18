<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Exports\ManagerLeadsExport;
use App\Models\Lead;
use App\Services\AuditLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class LeadExportController extends Controller
{
    private static array $FILTER_KEYS = [
        'search', 'telecaller', 'status', 'date_range', 'date_from', 'date_to',
        'course_id', 'academic_year_id', 'quota', 'source', 'gender',
        'state', 'city', 'district', 'followup', 'no_activity_days',
        'sla', 'is_duplicate', 'is_active', 'aged_min', 'aged_max',
    ];

    public function export(Request $request)
    {
        $managerId = Auth::id();
        $filters   = $request->only(self::$FILTER_KEYS);

        AuditLogService::log('lead.exported', 'Lead', null, [], [
            'manager_id' => $managerId,
            'filters'    => array_filter($filters),
        ]);

        if ($request->query('format') === 'pdf') {
            $query = ManagerLeadsExport::buildQuery($managerId, $filters);
            $leads = $query->orderBy('id', 'desc')->get();

            $headers = ['Lead Code', 'Name', 'Phone', 'Email', 'Course', 'Academic Year', 'Quota', 'Source', 'Gender', 'State', 'City', 'Status', 'Assigned To', 'Duplicate', 'Active', 'Days Aged', 'Created At'];
            $rows = $leads->map(fn($l) => [
                $l->lead_code,
                $l->name,
                $l->phone,
                $l->email ?? '',
                $l->course ?? '',
                $l->academicYear?->name ?? '',
                $l->quota ? ucfirst($l->quota) : '',
                $l->source ?? '',
                $l->gender ? ucfirst($l->gender) : '',
                $l->state ?? '',
                $l->city ?? '',
                ucfirst(str_replace('_', ' ', $l->status)),
                $l->assignedUser?->name ?? 'Unassigned',
                $l->is_duplicate ? 'Yes' : 'No',
                $l->is_active ? 'Yes' : 'No',
                $l->days_aged . 'd',
                $l->created_at->format('d M Y H:i'),
            ])->all();

            $pdf = Pdf::loadView('manager.leads.export-pdf', [
                'title'   => 'Leads Export — ' . now()->format('d M Y'),
                'headers' => $headers,
                'rows'    => $rows,
            ])->setPaper('a4', 'landscape');

            return $pdf->download('leads_' . now()->format('Y-m-d') . '.pdf');
        }

        $filename = 'leads_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new ManagerLeadsExport($managerId, $filters), $filename);
    }
}
