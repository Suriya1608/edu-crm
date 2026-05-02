<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Exports\LeadImportSampleExport;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Services\AuditLogService;
use App\Services\LeadDefaults;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class LeadImportController extends Controller
{
    // ── Source label → DB enum slug ──────────────────────────────────────────
    private static array $SOURCE_MAP = [
        'facebook ads'     => 'facebook_ads',
        'facebook'         => 'facebook_ads',
        'instagram ads'    => 'instagram_ads',
        'instagram'        => 'instagram_ads',
        'google ads'       => 'google_ads',
        'google'           => 'google_ads',
        'social media'     => 'social_media',
        'social'           => 'social_media',
        'walk-in'          => 'walk_in',
        'walk in'          => 'walk_in',
        'walkin'           => 'walk_in',
        'self'             => 'walk_in',
        'referral'         => 'referral',
        'newspaper'        => 'newspaper',
        'tv advertisement' => 'tv',
        'tv advert'        => 'tv',
        'television'       => 'tv',
        'tv'               => 'tv',
        'other'            => 'other',
    ];

    private function mapSourceCategory(string $raw): string
    {
        return self::$SOURCE_MAP[strtolower(trim($raw))] ?? 'other';
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function index()
    {
        return view('manager.leads.import');
    }

    // STEP 1 – Preview with course-match & source-map validation
    public function preview(Request $request)
    {
        $request->validate(['file' => 'required|mimes:xlsx,csv']);

        $file = $request->file('file');
        $data = Excel::toArray([], $file);
        $rows = $data[0];
        array_shift($rows); // drop header
        $file->store('imports');

        // Load all courses once, keyed by lowercase name for O(1) lookup
        $courseMap = Course::all()->keyBy(fn($c) => strtolower(trim($c->name)));

        $enriched = array_map(function ($row) use ($courseMap) {
            $courseName = trim($row[3] ?? '');
            $matched    = $courseMap->get(strtolower($courseName));
            $sourceRaw  = trim($row[4] ?? '');

            return [
                'row'            => $row,
                'course_matched' => $matched !== null,
                'course_name'    => $matched?->name ?? $courseName,
                'source_raw'     => $sourceRaw,
                'source_mapped'  => $this->mapSourceCategory($sourceRaw),
            ];
        }, $rows);

        $unmatchedCount = collect($enriched)
            ->filter(fn($e) => !$e['course_matched'] && $e['course_name'] !== '')
            ->count();

        return view('manager.leads.import', compact('rows', 'enriched', 'unmatchedCount'));
    }

    // STEP 2 – Confirm & store
    public function store(Request $request)
    {
        $leads = json_decode($request->leads_data, true);

        // Pre-load all courses keyed by lowercase name (case-insensitive match)
        $courseMap = Course::all()->keyBy(fn($c) => strtolower(trim($c->name)));

        // Batch duplicate-phone detection
        $incomingPhones = collect($leads)
            ->pluck(1)->filter()
            ->map(fn($p) => preg_replace('/\D+/', '', (string) $p))
            ->filter()->values()->toArray();

        $existingPhones = Lead::whereIn('phone', $incomingPhones)
            ->pluck('phone')
            ->map(fn($p) => preg_replace('/\D+/', '', (string) $p))
            ->flip();

        $importedCount  = 0;
        $duplicateCount = 0;

        foreach ($leads as $row) {
            $rawPhone    = preg_replace('/\D+/', '', (string) ($row[1] ?? ''));
            $isDuplicate = isset($existingPhones[$rawPhone]) && $rawPhone !== '';

            if ($isDuplicate) {
                $duplicateCount++;
            }

            // Case-insensitive course matching
            $courseKey = strtolower(trim($row[3] ?? ''));
            $courseId  = $courseMap->get($courseKey)?->id;

            // Source mapping
            $sourceRaw      = trim($row[4] ?? '');
            $sourceCategory = $this->mapSourceCategory($sourceRaw);

            $lead = Lead::create([
                'lead_code'       => $this->generateLeadCode(),
                'name'            => $row[0],
                'phone'           => $row[1],
                'email'           => $row[2] ?? null,
                'course_id'       => $courseId,
                'academic_year_id'=> AcademicYear::current()?->id,
                'quota'           => 'counselling',
                'source'          => $sourceRaw ?: 'import',
                'source_type'     => 'import',
                'source_category' => $sourceCategory,
                'source_detail'   => $sourceRaw ?: null,
                'status'          => LeadDefaults::defaultStatus(),
                'assigned_by'     => Auth::id(),
                'is_duplicate'    => $isDuplicate,
            ]);

            if ($isDuplicate) {
                AuditLogService::log('lead.duplicate_detected', 'Lead', $lead->id, [], [
                    'phone'  => $row[1],
                    'source' => 'import',
                ]);
            }

            LeadActivity::create([
                'lead_id'     => $lead->id,
                'user_id'     => Auth::id(),
                'type'        => 'note',
                'description' => 'Lead imported via bulk import' . ($isDuplicate ? ' (flagged as duplicate)' : ''),
            ]);

            $importedCount++;
        }

        $msg = "{$importedCount} lead(s) imported successfully.";
        if ($duplicateCount > 0) {
            $msg .= " {$duplicateCount} flagged as duplicate (phone already exists).";
        }

        return redirect()->route('manager.leads')->with('success', $msg);
    }

    // ── Sample Excel (3 sheets: Import / Valid Courses / Valid Sources) ───────
    public function downloadSample()
    {
        return Excel::download(new LeadImportSampleExport, 'lead_import_sample.xlsx');
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function generateLeadCode(): string
    {
        $prefix     = 'SMIT';
        $lastLead   = Lead::latest('id')->first();
        $nextNumber = $lastLead ? $lastLead->id + 1 : 1;

        return $prefix . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
