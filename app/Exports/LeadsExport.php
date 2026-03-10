<?php

namespace App\Exports;

use App\Models\Lead;
use Maatwebsite\Excel\Concerns\FromCollection;

class LeadsExport implements FromCollection
{
    public function collection()
    {
        return Lead::select(
            'lead_code',
            'name',
            'phone',
            'email',
            'course',
            'source',
            'status'
        )->get();
    }
}
