<?php

namespace App\Http\Controllers;

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
    public function index()
    {
        return view('manager.leads.import');
    }

    // STEP 1: Preview Only
    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv'
        ]);

        // Get uploaded file
        $file = $request->file('file');

        // Read Excel directly from uploaded file
        $data = Excel::toArray([], $file);

        $rows = $data[0];

        // Remove header row
        $header = array_shift($rows);

        // OPTIONAL: Store file after reading
        $file->store('imports');

        return view('manager.leads.import', compact('rows'));
    }



    // STEP 2: Store After Confirm
    public function store(Request $request)
    {
        $leads = json_decode($request->leads_data, true);

        // Pre-load existing phones for batch duplicate detection (avoid N+1)
        $incomingPhones = collect($leads)->pluck(1)->filter()->map(fn($p) => preg_replace('/\D+/', '', (string) $p))->filter()->values()->toArray();
        $existingPhones = Lead::whereIn('phone', $incomingPhones)
            ->pluck('phone')
            ->map(fn($p) => preg_replace('/\D+/', '', (string) $p))
            ->flip(); // flip for O(1) lookup

        $importedCount  = 0;
        $duplicateCount = 0;

        foreach ($leads as $row) {
            $rawPhone    = preg_replace('/\D+/', '', (string) ($row[1] ?? ''));
            $isDuplicate = isset($existingPhones[$rawPhone]) && $rawPhone !== '';

            if ($isDuplicate) {
                $duplicateCount++;
            }

            $courseId = isset($row[3]) && $row[3] !== ''
                ? Course::where('name', trim($row[3]))->value('id')
                : null;

            $lead = Lead::create([
                'lead_code'    => $this->generateLeadCode(),
                'name'         => $row[0],
                'phone'        => $row[1],
                'email'        => $row[2] ?? null,
                'course_id'    => $courseId,
                'source'       => $row[4] ?? 'meta_ads',
                'status'       => LeadDefaults::defaultStatus(),
                'assigned_by'  => Auth::id(),
                'is_duplicate' => $isDuplicate,
            ]);

            if ($isDuplicate) {
                AuditLogService::log('lead.duplicate_detected', 'Lead', $lead->id, [], ['phone' => $row[1], 'source' => 'import']);
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
    private function generateLeadCode()
    {
        $prefix = 'SMIT'; // later dynamic

        $lastLead = Lead::latest('id')->first();

        $nextNumber = $lastLead ? $lastLead->id + 1 : 1;

        $formattedNumber = str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

        return $prefix . '-' . $formattedNumber;
    }
    public function downloadSample()
    {
        $path = storage_path('app/sample/lead_import_sample.xlsx');

        if (!file_exists($path)) {
            abort(404, 'Sample file not found.');
        }

        return response()->download($path);
    }
}
