<?php

namespace App\Imports;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Services\LeadDefaults;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class LeadsImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        $lead = Lead::create([
            'lead_code'   => 'LD-' . rand(10000,99999),
            'name'        => $row['name'],
            'phone'       => $row['phone'],
            'email'       => $row['email'],
            'course'      => $row['course'],
            'source'      => $row['source'],
            'status'      => LeadDefaults::defaultStatus(),
            'assigned_by' => Auth::id(),
        ]);

        // Insert activity log
        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'type' => 'note',
            'description' => 'Lead imported via bulk upload'
        ]);

        return $lead;
    }
}
