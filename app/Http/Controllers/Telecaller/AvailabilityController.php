<?php

namespace App\Http\Controllers\Telecaller;

use App\Http\Controllers\Controller;
use App\Models\TelecallerUnavailability;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AvailabilityController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();

        $year  = (int) ($request->year  ?? now()->year);
        $month = (int) ($request->month ?? now()->month);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $blocked = TelecallerUnavailability::where('user_id', $userId)
            ->whereBetween('blocked_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->map(fn($r) => [
                'date'   => $r->blocked_date->toDateString(),
                'reason' => $r->reason,
            ])
            ->values();

        return Inertia::render('Telecaller/Availability/Index', [
            'blocked_dates' => $blocked,
            'year'          => $year,
            'month'         => $month,
            'today'         => now()->toDateString(),
            'urls' => [
                'store'   => route('telecaller.availability.store'),
                'destroy' => route('telecaller.availability.destroy', ['date' => '__DATE__']),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'dates'   => 'required|array|min:1|max:60',
            'dates.*' => 'date|after_or_equal:today',
            'reason'  => 'nullable|string|max:191',
        ]);

        $userId = Auth::id();
        $reason = $request->reason;

        foreach ($request->dates as $date) {
            TelecallerUnavailability::updateOrCreate(
                ['user_id' => $userId, 'blocked_date' => $date],
                ['reason'  => $reason]
            );
        }

        $count = count($request->dates);
        return back()->with('success', $count === 1 ? 'Date blocked successfully.' : "{$count} dates blocked successfully.");
    }

    public function destroy(string $date)
    {
        TelecallerUnavailability::where('user_id', Auth::id())
            ->where('blocked_date', $date)
            ->delete();

        return back()->with('success', 'Date unblocked.');
    }
}
