<?php

namespace App\Http\Controllers;

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
        $academicYears  = AcademicYear::orderBy('id', 'desc')->get();
        $academicYearId = AcademicYear::current()?->id;
        return view('manager.leads.import', compact('academicYears', 'academicYearId'));
    }

    // STEP 1 – Preview with duplicate detection, course-match & source-map validation
    public function preview(Request $request)
    {
        $request->validate([
            'file'             => 'required|mimes:xlsx,csv',
            'academic_year_id' => 'required|exists:academic_years,id',
        ]);

        $academicYears  = AcademicYear::orderBy('id', 'desc')->get();
        $academicYearId = $request->academic_year_id;

        $data = Excel::toArray([], $request->file('file'));
        $rows = $data[0];
        array_shift($rows);

        $rows = array_values(array_filter($rows, fn($row) =>
            !empty(trim((string) ($row[0] ?? ''))) || !empty(trim((string) ($row[1] ?? '')))));

        $courseMap = Course::all()->keyBy(fn($c) => strtolower(trim($c->name)));

        // Batch DB lookups for phone and email
        $inPhones = collect($rows)->pluck(1)->filter()
            ->map(fn($p) => preg_replace('/\D+/', '', (string) $p))->filter()->unique()->values()->toArray();
        $inEmails = collect($rows)->pluck(2)->filter()
            ->map(fn($e) => strtolower(trim((string) $e)))->filter()->unique()->values()->toArray();

        $dbPhones = Lead::whereIn('phone', $inPhones)->pluck('phone')
            ->map(fn($p) => preg_replace('/\D+/', '', (string) $p))->flip()->toArray();
        $dbEmails = !empty($inEmails)
            ? Lead::whereIn('email', $inEmails)->pluck('email')
                ->map(fn($e) => strtolower(trim((string) $e)))->flip()->toArray()
            : [];

        $seenPhones = [];
        $seenEmails = [];

        $enriched = array_map(function ($row) use ($courseMap, $dbPhones, $dbEmails, &$seenPhones, &$seenEmails) {
            $courseName = trim($row[3] ?? '');
            $matched    = $courseMap->get(strtolower($courseName));
            $sourceRaw  = trim($row[4] ?? '');

            $phone  = preg_replace('/\D+/', '', (string) ($row[1] ?? ''));
            $email  = strtolower(trim((string) ($row[2] ?? '')));
            $dup    = false;
            $reason = '';

            if ($phone !== '' && isset($dbPhones[$phone])) {
                $dup = true; $reason = 'Phone exists in database';
            } elseif ($email !== '' && isset($dbEmails[$email])) {
                $dup = true; $reason = 'Email exists in database';
            } elseif ($phone !== '' && isset($seenPhones[$phone])) {
                $dup = true; $reason = 'Phone repeated in file';
            } elseif ($email !== '' && isset($seenEmails[$email])) {
                $dup = true; $reason = 'Email repeated in file';
            }

            if (!$dup) {
                if ($phone !== '') $seenPhones[$phone] = true;
                if ($email !== '') $seenEmails[$email] = true;
            }

            return [
                'row'              => $row,
                'course_matched'   => $matched !== null,
                'course_name'      => $matched?->name ?? $courseName,
                'source_raw'       => $sourceRaw,
                'source_mapped'    => $this->mapSourceCategory($sourceRaw),
                'duplicate'        => $dup,
                'duplicate_reason' => $reason,
            ];
        }, $rows);

        $unmatchedCount = collect($enriched)->filter(fn($e) => !$e['course_matched'] && $e['course_name'] !== '')->count();
        $duplicateCount = collect($enriched)->filter(fn($e) => $e['duplicate'])->count();
        $cleanRows      = collect($enriched)->reject(fn($e) => $e['duplicate'])->pluck('row')->values()->toArray();

        return view('manager.leads.import', compact('rows', 'enriched', 'unmatchedCount', 'duplicateCount', 'cleanRows', 'academicYears', 'academicYearId'));
    }

    // STEP 2 – Confirm & store (duplicates are skipped, not saved)
    public function store(Request $request)
    {
        $request->validate(['academic_year_id' => 'required|exists:academic_years,id']);

        $leads          = json_decode($request->leads_data, true);
        $academicYearId = $request->academic_year_id;

        $courseMap = Course::all()->keyBy(fn($c) => strtolower(trim($c->name)));

        // Safety-net: re-check DB duplicates (catches race conditions after preview)
        $inPhones = collect($leads)->pluck(1)->filter()
            ->map(fn($p) => preg_replace('/\D+/', '', (string) $p))->filter()->unique()->values()->toArray();
        $inEmails = collect($leads)->pluck(2)->filter()
            ->map(fn($e) => strtolower(trim((string) $e)))->filter()->unique()->values()->toArray();

        $dbPhones = Lead::whereIn('phone', $inPhones)->pluck('phone')
            ->map(fn($p) => preg_replace('/\D+/', '', (string) $p))->flip()->toArray();
        $dbEmails = !empty($inEmails)
            ? Lead::whereIn('email', $inEmails)->pluck('email')
                ->map(fn($e) => strtolower(trim((string) $e)))->flip()->toArray()
            : [];

        $seenPhones    = [];
        $seenEmails    = [];
        $importedCount = 0;
        $skippedCount  = 0;

        foreach ($leads as $row) {
            if (empty(trim((string) ($row[0] ?? ''))) && empty(trim((string) ($row[1] ?? '')))) {
                continue;
            }

            $rawPhone = preg_replace('/\D+/', '', (string) ($row[1] ?? ''));
            $rawEmail = strtolower(trim((string) ($row[2] ?? '')));

            $isDuplicate = ($rawPhone !== '' && isset($dbPhones[$rawPhone]))
                || ($rawEmail !== '' && isset($dbEmails[$rawEmail]))
                || ($rawPhone !== '' && isset($seenPhones[$rawPhone]))
                || ($rawEmail !== '' && isset($seenEmails[$rawEmail]));

            if ($isDuplicate) {
                $skippedCount++;
                continue;
            }

            if ($rawPhone !== '') $seenPhones[$rawPhone] = true;
            if ($rawEmail !== '') $seenEmails[$rawEmail] = true;

            $courseKey      = strtolower(trim($row[3] ?? ''));
            $courseId       = $courseMap->get($courseKey)?->id;
            $sourceRaw      = trim($row[4] ?? '');
            $sourceCategory = $this->mapSourceCategory($sourceRaw);

            $lead = Lead::create([
                'lead_code'        => $this->generateLeadCode(),
                'name'             => $row[0],
                'phone'            => $row[1],
                'email'            => $row[2] ?? null,
                'course_id'        => $courseId,
                'academic_year_id' => $academicYearId,
                'source'           => $sourceRaw ?: 'import',
                'source_type'      => 'import',
                'source_category'  => $sourceCategory,
                'source_detail'    => $sourceRaw ?: null,
                'status'           => LeadDefaults::defaultStatus(),
                'assigned_by'      => Auth::id(),
            ]);

            AuditLogService::log('lead.imported', 'Lead', $lead->id, [], ['source' => 'bulk_import']);

            LeadActivity::create([
                'lead_id'       => $lead->id,
                'user_id'       => Auth::id(),
                'type'          => 'note',
                'description'   => 'Lead imported via bulk import',
                'activity_time' => now(),
            ]);

            $importedCount++;
        }

        $msg = "{$importedCount} lead(s) imported successfully.";
        if ($skippedCount > 0) $msg .= " {$skippedCount} duplicate(s) skipped.";

        return redirect()->route('manager.leads.import')->with('success', $msg);
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
