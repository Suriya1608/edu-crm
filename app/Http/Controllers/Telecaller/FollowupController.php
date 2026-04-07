<?php

namespace App\Http\Controllers\Telecaller;

use App\Http\Controllers\Controller;
use App\Models\Followup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

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

        $followups = $query->orderBy('next_followup')->paginate(10)->withQueryString();

        return view('telecaller.followups.index', compact('title', 'scope', 'followups'));
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

    private function editableFollowup($id): Followup
    {
        return Followup::where('id', $id)
            ->whereHas('lead', function ($q) {
                $q->where('assigned_to', Auth::id());
            })
            ->firstOrFail();
    }
}
