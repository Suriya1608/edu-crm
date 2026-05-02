<?php

namespace App\Http\Controllers\Telecaller;

use App\Exports\ArrayExport;
use App\Http\Controllers\Controller;
use App\Models\Followup;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class FollowupController extends Controller
{
    public function today()
    {
        return $this->indexByScope('today', 'Today Followups');
    }

    public function overdue()
    {
        return $this->indexByScope('overdue', 'Overdue Followups');
    }

    public function upcoming()
    {
        return $this->indexByScope('upcoming', 'Upcoming Followups');
    }

    public function completed()
    {
        return $this->indexByScope('completed', 'Completed Followups');
    }

    public function reschedule(Request $request, $id)
    {
        $request->validate([
            'next_followup' => 'required|date',
            'followup_time' => 'required',
            'remarks'       => 'nullable|string|max:1000',
        ]);

        $scheduledAt = \Carbon\Carbon::parse(
            $request->input('next_followup') . ' ' . $request->input('followup_time')
        );

        if ($scheduledAt->isPast()) {
            return back()
                ->withErrors(['next_followup' => 'The scheduled date & time cannot be in the past.'])
                ->withInput();
        }

        $followup = $this->editableFollowup($id);

        $payload = [
            'next_followup' => $request->input('next_followup'),
            'followup_time' => $request->input('followup_time'),
        ];

        if ($request->filled('remarks')) {
            $payload['remarks'] = $request->input('remarks');
        }

        if (Schema::hasColumn('followups', 'completed_at')) {
            $payload['completed_at'] = null;
        }
        if (Schema::hasColumn('followups', 'reminder_notified_at')) {
            $payload['reminder_notified_at'] = null;
        }

        $followup->update($payload);

        $followup->lead?->activities()->create([
            'user_id'       => Auth::id(),
            'type'          => 'followup',
            'description'   => 'Follow-up rescheduled to ' . $scheduledAt->format('d M Y H:i'),
            'activity_time' => now(),
        ]);

        return back()->with('success', 'Follow-up rescheduled successfully.');
    }

    public function markCompleted(Request $request, $id)
    {
        $request->validate([
            'remarks' => 'nullable|string|max:1000',
        ]);

        $followup = $this->editableFollowup($id);

        $payload = [];
        if (Schema::hasColumn('followups', 'completed_at')) {
            $payload['completed_at'] = now();
        }

        if ($request->filled('remarks')) {
            $payload['remarks'] = $request->input('remarks');
        }

        if (!empty($payload)) {
            $followup->update($payload);
        }

        $followup->lead?->activities()->create([
            'user_id' => Auth::id(),
            'type' => 'followup',
            'description' => 'Follow-up marked as completed.',
            'activity_time' => now(),
        ]);

        return back()->with('success', 'Follow-up marked as completed.');
    }

    private function indexByScope(string $scope, string $title)
    {
        $query = Followup::with(['lead:id,name,lead_code,phone,status', 'user:id,name'])
            ->whereHas('lead', function ($q) {
                $q->where('assigned_to', Auth::id());
            });

        $hasCompleted = Schema::hasColumn('followups', 'completed_at');

        if ($scope !== 'completed' && $hasCompleted) {
            $query->whereNull('completed_at');
        }

        $nowTime = now()->format('H:i:s');

        if ($scope === 'today') {
            $query->whereDate('next_followup', today());
        } elseif ($scope === 'overdue') {
            $query->where(function ($q) use ($nowTime) {
                $q->whereDate('next_followup', '<', today())
                  ->orWhere(function ($q2) use ($nowTime) {
                      $q2->whereDate('next_followup', today())
                         ->whereNotNull('followup_time')
                         ->whereRaw('followup_time < ?', [$nowTime]);
                  });
            });
        } elseif ($scope === 'upcoming') {
            $query->where(function ($q) use ($nowTime) {
                $q->whereDate('next_followup', '>', today())
                  ->orWhere(function ($q2) use ($nowTime) {
                      $q2->whereDate('next_followup', today())
                         ->where(function ($q3) use ($nowTime) {
                             $q3->whereNull('followup_time')
                                ->orWhereRaw('followup_time >= ?', [$nowTime]);
                         });
                  });
            });
        } elseif ($scope === 'completed') {
            if ($hasCompleted) {
                $query->whereNotNull('completed_at');
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $followups = $query->orderBy('next_followup')->paginate(10)->withQueryString()->through(function ($followup) use ($scope) {
            // Flat lead fields the JSX renders directly
            $followup->lead_name  = $followup->lead?->name;
            $followup->lead_code  = $followup->lead?->lead_code;
            $followup->lead_phone = $followup->lead?->phone;

            // Human-readable date/time strings for display columns
            $followup->next_followup_fmt = $followup->next_followup?->format('d M Y');
            $followup->followup_time_fmt = $followup->followup_time
                ? Carbon::parse($followup->followup_time)->format('h:i A')
                : null;

            // Status label used by the StatusBadge component
            $followup->status_label = $scope;

            // Completion flag for showing/hiding reschedule & mark-complete buttons
            $followup->is_completed = !is_null($followup->completed_at);

            // Encrypted lead ID for the "Open Lead" link
            $followup->encrypted_lead_id = $followup->lead_id ? encrypt($followup->lead_id) : null;

            // Hide nested relationship objects — JSX uses flat fields only
            $followup->makeHidden(['lead', 'user']);

            return $followup;
        });

        return Inertia::render('Telecaller/Followups/Index', [
            'scope'     => $scope,
            'title'     => $title,
            'followups' => $followups,
        ]);
    }

    public function calendarData(Request $request): \Illuminate\Http\JsonResponse
    {
        $year  = (int) $request->get('year',  now()->year);
        $month = (int) $request->get('month', now()->month);

        if ($month < 1)  { $month = 12; $year--; }
        if ($month > 12) { $month = 1;  $year++; }

        $userId = Auth::id();

        $query = Followup::whereHas('lead', fn($q) => $q->where('assigned_to', $userId))
            ->whereYear('next_followup', $year)
            ->whereMonth('next_followup', $month);

        if (Schema::hasColumn('followups', 'completed_at')) {
            $query->whereNull('completed_at');
        }

        $days = $query
            ->selectRaw('DATE(next_followup) as day, COUNT(*) as total')
            ->groupByRaw('DATE(next_followup)')
            ->pluck('total', 'day');

        return response()->json([
            'year'  => $year,
            'month' => $month,
            'days'  => $days,
        ]);
    }

    public function export(Request $request, string $scope)
    {
        $format = $request->input('format', 'excel');
        $titles = [
            'today'     => 'Today Followups',
            'overdue'   => 'Overdue Followups',
            'upcoming'  => 'Upcoming Followups',
            'completed' => 'Completed Followups',
        ];
        $title = $titles[$scope] ?? 'Followups';

        $query = Followup::with(['lead:id,name,lead_code,phone'])
            ->whereHas('lead', fn($q) => $q->where('assigned_to', Auth::id()));

        $hasCompleted = Schema::hasColumn('followups', 'completed_at');
        if ($scope !== 'completed' && $hasCompleted) {
            $query->whereNull('completed_at');
        }

        $nowTime = now()->format('H:i:s');
        if ($scope === 'today') {
            $query->whereDate('next_followup', today());
        } elseif ($scope === 'overdue') {
            $query->where(function ($q) use ($nowTime) {
                $q->whereDate('next_followup', '<', today())
                  ->orWhere(function ($q2) use ($nowTime) {
                      $q2->whereDate('next_followup', today())
                         ->whereNotNull('followup_time')
                         ->whereRaw('followup_time < ?', [$nowTime]);
                  });
            });
        } elseif ($scope === 'upcoming') {
            $query->where(function ($q) use ($nowTime) {
                $q->whereDate('next_followup', '>', today())
                  ->orWhere(function ($q2) use ($nowTime) {
                      $q2->whereDate('next_followup', today())
                         ->where(fn($q3) => $q3->whereNull('followup_time')->orWhereRaw('followup_time >= ?', [$nowTime]));
                  });
            });
        } elseif ($scope === 'completed') {
            $hasCompleted ? $query->whereNotNull('completed_at') : $query->whereRaw('1 = 0');
        }

        $followups = $query->orderBy('next_followup')->get()->values()->map(function ($fu, $idx) use ($scope) {
            return [
                'sno'       => $idx + 1,
                'date'      => $fu->next_followup?->format('d M Y') ?? '—',
                'time'      => $fu->followup_time ? Carbon::parse($fu->followup_time)->format('h:i A') : '',
                'lead_name' => $fu->lead?->name ?? '—',
                'lead_code' => $fu->lead?->lead_code ?? '—',
                'phone'     => $fu->lead?->phone ?? '—',
                'remarks'   => $fu->remarks ?? '',
                'scope'     => $scope,
            ];
        })->toArray();

        $filename = strtolower(str_replace(' ', '-', $title)) . '-' . now()->format('Ymd-His');
        $meta = ['userName' => Auth::user()->name, 'generatedAt' => now()->format('d M Y, h:i A'), 'title' => $title];

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.telecaller.followups', array_merge($meta, ['followups' => $followups]))
                ->setPaper('a4', 'landscape');
            return $pdf->download($filename . '.pdf');
        }

        $headings = ['S.No', 'Date', 'Time', 'Lead Name', 'Lead Code', 'Phone', 'Remarks', 'Status'];
        return Excel::download(new ArrayExport($followups, $headings, $title), $filename . '.xlsx');
    }

    private function editableFollowup($id): Followup
    {
        return Followup::where('id', $id)
            ->whereHas('lead', function ($q) {
                $q->where('assigned_to', Auth::id());
            })
            ->firstOrFail();
    }
}
