<?php

namespace App\Exports;

use App\Models\Lead;
use App\Models\LeadActivity;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LeadsExport implements FromQuery, WithHeadings, WithMapping, WithColumnWidths, WithStyles
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public static function buildQuery(array $filters = [])
    {
        $query = Lead::with(['assignedBy:id,name', 'assignedUser:id,name', 'enrolledCourse', 'academicYear:id,name']);

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(fn($q) => $q
                ->where('lead_code', 'like', "%{$s}%")
                ->orWhere('name',  'like', "%{$s}%")
                ->orWhere('phone', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
            );
        }

        if (!empty($filters['manager_id']))       $query->where('assigned_by',      $filters['manager_id']);
        if (!empty($filters['telecaller_id']))    $query->where('assigned_to',       $filters['telecaller_id']);
        if (!empty($filters['status']))           $query->where('status',            $filters['status']);
        if (!empty($filters['course_id']))        $query->where('course_id',         $filters['course_id']);
        if (!empty($filters['academic_year_id'])) $query->where('academic_year_id',  $filters['academic_year_id']);
        if (!empty($filters['quota']))            $query->where('quota',             $filters['quota']);
        if (!empty($filters['source']))           $query->where('source',            $filters['source']);
        if (!empty($filters['gender']))           $query->where('gender',            $filters['gender']);
        if (!empty($filters['state']))            $query->where('state',    'like',  '%' . $filters['state']    . '%');
        if (!empty($filters['city']))             $query->where('city',     'like',  '%' . $filters['city']     . '%');
        if (!empty($filters['district']))         $query->where('district', 'like',  '%' . $filters['district'] . '%');

        if (!empty($filters['date_range'])) {
            if ($filters['date_range'] === 'custom') {
                if (!empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
                if (!empty($filters['date_to']))   $query->whereDate('created_at', '<=', $filters['date_to']);
            } elseif ($filters['date_range'] === 'today') {
                $query->whereDate('created_at', today());
            } elseif (is_numeric($filters['date_range'])) {
                $query->whereDate('created_at', '>=', now()->subDays((int) $filters['date_range']));
            }
        }

        if (!empty($filters['followup'])) {
            match ($filters['followup']) {
                'today'     => $query->whereHas('followups', fn($q) => $q->whereDate('next_followup', today())),
                'overdue'   => $query->whereHas('followups', fn($q) => $q->whereDate('next_followup', '<', today())),
                'this_week' => $query->whereHas('followups', fn($q) => $q
                    ->whereDate('next_followup', '>=', today())
                    ->whereDate('next_followup', '<=', today()->endOfWeek())),
                'none'      => $query->whereDoesntHave('followups'),
                default     => null,
            };
        }

        if (!empty($filters['no_activity_days']) && is_numeric($filters['no_activity_days'])) {
            $cutoff    = now()->subDays((int) $filters['no_activity_days']);
            $recentIds = LeadActivity::where('activity_time', '>=', $cutoff)->distinct()->pluck('lead_id');
            $query->whereNotIn('id', $recentIds);
        }

        if (!empty($filters['sla'])) {
            if ($filters['sla'] === 'escalated') {
                $query->whereNotNull('sla_escalated_at');
            } elseif (is_numeric($filters['sla'])) {
                $query->where('sla_level', '>=', (int) $filters['sla']);
            }
        }

        if (isset($filters['is_duplicate']) && $filters['is_duplicate'] !== '')
            $query->where('is_duplicate', (bool) $filters['is_duplicate']);
        if (isset($filters['is_active']) && $filters['is_active'] !== '')
            $query->where('is_active', (bool) $filters['is_active']);

        if (!empty($filters['aged_min']) && is_numeric($filters['aged_min']))
            $query->whereDate('created_at', '<=', now()->subDays((int) $filters['aged_min']));
        if (!empty($filters['aged_max']) && is_numeric($filters['aged_max']))
            $query->whereDate('created_at', '>=', now()->subDays((int) $filters['aged_max']));

        return $query;
    }

    public function query()
    {
        return self::buildQuery($this->filters)->orderBy('id', 'desc');
    }

    public function headings(): array
    {
        return [
            'Lead Code', 'Name', 'Phone', 'Email',
            'Course', 'Academic Year', 'Quota',
            'Source', 'Gender', 'State', 'City', 'District',
            'Status', 'Manager', 'Telecaller',
            'Duplicate', 'Active', 'SLA Level', 'Days Aged', 'Created At',
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
            $lead->academicYear?->name ?? '',
            $lead->quota ? ucfirst($lead->quota) : '',
            $lead->source ?? '',
            $lead->gender ? ucfirst($lead->gender) : '',
            $lead->state ?? '',
            $lead->city ?? '',
            $lead->district ?? '',
            ucfirst(str_replace('_', ' ', $lead->status)),
            $lead->assignedBy?->name ?? '—',
            $lead->assignedUser?->name ?? '—',
            $lead->is_duplicate ? 'Yes' : 'No',
            $lead->is_active ? 'Yes' : 'No',
            $lead->sla_level ?? 0,
            $lead->days_aged,
            $lead->created_at->format('d M Y H:i'),
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, 'B' => 25, 'C' => 18, 'D' => 30,
            'E' => 22, 'F' => 18, 'G' => 14,
            'H' => 16, 'I' => 12, 'J' => 16, 'K' => 16, 'L' => 16,
            'M' => 18, 'N' => 22, 'O' => 22,
            'P' => 12, 'Q' => 10, 'R' => 10, 'S' => 12, 'T' => 20,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF6366F1'],
                ],
            ],
        ];
    }
}
