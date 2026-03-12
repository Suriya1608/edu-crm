<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Exports\ManagerLeadsExport;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class LeadExportController extends Controller
{
    public function export(Request $request)
    {
        $managerId = Auth::id();

        $filters = $request->only(['search', 'telecaller', 'status', 'date_range']);

        AuditLogService::log('lead.exported', 'Lead', null, [], [
            'manager_id' => $managerId,
            'filters'    => array_filter($filters),
        ]);

        $filename = 'leads_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new ManagerLeadsExport($managerId, $filters), $filename);
    }
}
