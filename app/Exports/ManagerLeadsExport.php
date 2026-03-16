<?php

namespace App\Exports;

use App\Models\Lead;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ManagerLeadsExport implements FromQuery, WithHeadings, WithMapping, WithColumnWidths, WithStyles
{
    protected int $managerId;
    protected array $filters;

    public function __construct(int $managerId, array $filters = [])
    {
        $this->managerId = $managerId;
        $this->filters   = $filters;
    }

    public function query()
    {
        $query = Lead::with(['assignedUser', 'enrolledCourse'])
            ->where('assigned_by', $this->managerId);

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('lead_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%")
                    ->orWhereHas('enrolledCourse', fn($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        if (!empty($this->filters['telecaller'])) {
            $query->where('assigned_to', $this->filters['telecaller']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['date_range'])) {
            match ($this->filters['date_range']) {
                'today' => $query->whereDate('created_at', now()),
                '7'     => $query->whereDate('created_at', '>=', now()->subDays(7)),
                '30'    => $query->whereDate('created_at', '>=', now()->subDays(30)),
                default => null,
            };
        }

        return $query->orderBy('id', 'desc');
    }

    public function headings(): array
    {
        return [
            'Lead Code',
            'Name',
            'Phone',
            'Email',
            'Course',
            'Source',
            'Status',
            'Assigned To',
            'Duplicate',
            'Created At',
        ];
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
            $lead->assignedUser->name ?? 'Unassigned',
            $lead->is_duplicate ? 'Yes' : 'No',
            $lead->created_at->format('d M Y H:i'),
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,
            'B' => 25,
            'C' => 18,
            'D' => 30,
            'E' => 20,
            'F' => 15,
            'G' => 16,
            'H' => 22,
            'I' => 12,
            'J' => 20,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF137FEC'],
                ],
            ],
        ];
    }
}
