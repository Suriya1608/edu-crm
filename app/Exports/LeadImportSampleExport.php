<?php

namespace App\Exports;

use App\Models\Course;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LeadImportSampleExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        // Pull real course names so sample rows match on import
        $courseNames = Course::orderBy('name')->pluck('name');

        $sampleRows = [
            ['John Doe',   '9876543210', 'john@example.com', $courseNames->get(0) ?? 'B.E. Computer Science', 'Facebook Ads', 'Male',   '15-06-2000', 'Chennai',   'Chennai',    'Tamil Nadu', '600001'],
            ['Jane Smith', '9123456789', 'jane@example.com', $courseNames->get(1) ?? 'B.Tech IT',             'Walk-in',      'Female', '22-09-1998', 'Coimbatore','Coimbatore', 'Tamil Nadu', '641001'],
            ['Ravi Kumar', '9000011111', '',                  $courseNames->get(2) ?? 'MBA',                   'Referral',     '',       '',           'Madurai',   'Madurai',    'Tamil Nadu', '625001'],
        ];

        $courseRows = $courseNames->map(fn($n) => [$n])->values()->toArray();

        $sourceRows = [
            ['Facebook Ads'],
            ['Instagram Ads'],
            ['Google Ads'],
            ['Social Media'],
            ['Walk-in'],
            ['Referral'],
            ['Newspaper'],
            ['TV Advertisement'],
            ['Other'],
        ];

        return [
            new ArrayExport(
                rows:       $sampleRows,
                headings:   ['Name', 'Phone', 'Email', 'Course', 'Source', 'Gender', 'Date of Birth (DD-MM-YYYY)', 'City', 'District', 'State', 'Pincode'],
                sheetTitle: 'Lead Import',
            ),
            new ArrayExport(
                rows:       $courseRows,
                headings:   ['Course Name  (copy exact spelling into Column D)'],
                sheetTitle: 'Valid Courses',
            ),
            new ArrayExport(
                rows:       $sourceRows,
                headings:   ['Source Value  (copy exact spelling into Column E)'],
                sheetTitle: 'Valid Sources',
            ),
        ];
    }
}
