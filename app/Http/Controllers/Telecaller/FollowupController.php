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
            'remarks' => 'nullable|string|max:1000',
        ]);

        $followup = $this->editableFollowup($id);

        $payload = [
            'next_followup' => $request->input('next_followup'),
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
            'user_id' => Auth::id(),
            'type' => 'followup',
            'description' => 'Follow-up rescheduled to ' . $request->input('next_followup'),
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

        if ($scope === 'today') {
            $query->whereDate('next_followup', today());
        } elseif ($scope === 'overdue') {
            $query->whereDate('next_followup', '<', today());
        } elseif ($scope === 'upcoming') {
            $query->whereDate('next_followup', '>', today());
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

    private function editableFollowup($id): Followup
    {
        return Followup::where('id', $id)
            ->whereHas('lead', function ($q) {
                $q->where('assigned_to', Auth::id());
            })
            ->firstOrFail();
    }
}
