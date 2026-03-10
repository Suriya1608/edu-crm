<?php

namespace App\Http\Controllers;

use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LeadsExport;
use App\Models\LeadActivity;
use Illuminate\Support\Facades\Auth;
class LeadExportController extends Controller
{
    public function export()
    {
        LeadActivity::create([
            'lead_id' => 0,
            'user_id' => Auth::id(),
            'type' => 'note',
            'description' => 'Leads exported via bulk export'
        ]);

        return Excel::download(new LeadsExport, 'leads.xlsx');
    }
}
