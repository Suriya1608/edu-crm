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

class ManagerLeadsExport implements FromQuery, WithHeadings, WithMapping, WithColumnWidths, WithStyles
{
    protected int $managerId;
    protected array $filters;

    public function __construct(int $managerId, array $filters = [])
    {
        $this->managerId = $managerId;
        $this->filters   = $filters;
    }

    public static function buildQuery(int $managerId, array $filters = [])
    {
        $query = Lead::with(['assignedUser', 'enrolledCourse', 'academicYear'])
            ->where('assigned_by', $managerId);

        if (!empty($filters['search'])) {
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

        if (!empty($filters['telecaller'])) {
            $query->where('assigned_to', $filters['telecaller']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_range'])) {
            if ($filters['date_range'] === 'custom') {
                if (!empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
                if (!empty($filters['date_to']))   $query->whereDate('created_at', '<=', $filters['date_to']);
            } elseif ($filters['date_range'] === 'today') {
                $query->whereDate('created_at', now());
            } elseif (is_numeric($filters['date_range'])) {
                $query->whereDate('created_at', '>=', now()->subDays((int) $filters['date_range']));
            }
        }

        if (!empty($filters['course_id'])) {
            $query->where('course_id', $filters['course_id']);
        }

        if (!empty($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (!empty($filters['quota'])) {
            $query->where('quota', $filters['quota']);
        }

        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (!empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (!empty($filters['state'])) {
            $query->where('state', 'like', '%' . $filters['state'] . '%');
        }

        if (!empty($filters['city'])) {
            $query->where('city', 'like', '%' . $filters['city'] . '%');
        }

        if (!empty($filters['district'])) {
            $query->where('district', 'like', '%' . $filters['district'] . '%');
        }

        if (!empty($filters['followup'])) {
            if ($filters['followup'] === 'today') {
                $query->whereHas('followups', fn($q) => $q->whereDate('next_followup', today()));
            } elseif ($filters['followup'] === 'overdue') {
                $query->whereHas('followups', fn($q) => $q->whereDate('next_followup', '<', today()));
            } elseif ($filters['followup'] === 'this_week') {
                $query->whereHas('followups', fn($q) => $q
                    ->whereDate('next_followup', '>=', today())
                    ->whereDate('next_followup', '<=', today()->endOfWeek()));
            } elseif ($filters['followup'] === 'none') {
                $query->whereDoesntHave('followups');
            }
        }

        if (!empty($filters['no_activity_days']) && is_numeric($filters['no_activity_days'])) {
            $cutoff = now()->subDays((int) $filters['no_activity_days']);
            $recentLeadIds = LeadActivity::where('activity_time', '>=', $cutoff)->distinct()->pluck('lead_id');
            $query->whereNotIn('id', $recentLeadIds);
        }

        if (!empty($filters['sla'])) {
            if ($filters['sla'] === 'escalated') {
                $query->whereNotNull('sla_escalated_at');
            } elseif (is_numeric($filters['sla'])) {
                $query->where('sla_level', '>=', (int) $filters['sla']);
            }
        }

        if (isset($filters['is_duplicate']) && $filters['is_duplicate'] !== '') {
            $query->where('is_duplicate', (bool) $filters['is_duplicate']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (!empty($filters['aged_min']) && is_numeric($filters['aged_min'])) {
            $query->whereDate('created_at', '<=', now()->subDays((int) $filters['aged_min']));
        }

        if (!empty($filters['aged_max']) && is_numeric($filters['aged_max'])) {
            $query->whereDate('created_at', '>=', now()->subDays((int) $filters['aged_max']));
        }

        return $query;
    }

    public function query()
    {
        return self::buildQuery($this->managerId, $this->filters)->orderBy('id', 'desc');
    }

    public function headings(): array
    {
        return [
            'Lead Code', 'Name', 'Phone', 'Email',
            'Course', 'Academic Year', 'Quota',
            'Source', 'Gender', 'State', 'City', 'District',
            'Status', 'Assigned To',
            'Duplicate', 'Active', 'Days Aged',
            'Next Follow-up', 'Created At',
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
            $lead->assignedUser?->name ?? 'Unassigned',
            $lead->is_duplicate ? 'Yes' : 'No',
            $lead->is_active ? 'Yes' : 'No',
            $lead->days_aged,
            optional($lead->followups->sortByDesc('next_followup')->first()?->next_followup)
                ? \Carbon\Carbon::parse($lead->followups->sortByDesc('next_followup')->first()->next_followup)->format('d M Y')
                : '',
            $lead->created_at->format('d M Y H:i'),
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, 'B' => 25, 'C' => 18, 'D' => 30,
            'E' => 22, 'F' => 18, 'G' => 16,
            'H' => 16, 'I' => 12, 'J' => 16, 'K' => 16, 'L' => 16,
            'M' => 18, 'N' => 22,
            'O' => 12, 'P' => 10, 'Q' => 12,
            'R' => 18, 'S' => 20,
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
