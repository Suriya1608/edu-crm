<?php

namespace App\Exports;

use App\Models\Lead;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LeadsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Lead::with('enrolledCourse')
            ->select('lead_code', 'name', 'phone', 'email', 'course_id', 'source', 'status')
            ->get();
    }

    public function headings(): array
    {
        return ['Lead Code', 'Name', 'Phone', 'Email', 'Course', 'Source', 'Status'];
    }

    public function map($lead): array
    {
        return [
            $lead->lead_code,
            $lead->name,
            $lead->phone,
            $lead->email ?? '',
            $lead->course ?? '',
            $lead->source ?? '',
            ucfirst(str_replace('_', ' ', $lead->status)),
        ];
    }
}
