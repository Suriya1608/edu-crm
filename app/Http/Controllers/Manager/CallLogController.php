<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CallLogController extends Controller
{
    public function index(Request $request)
    {
        $scope = $request->get('scope', 'all');
        if (!in_array($scope, ['all', 'inbound', 'outbound', 'missed'], true)) {
            $scope = 'all';
        }

        $managerId       = Auth::id();
        $myLeadsSubquery = Lead::where('assigned_by', $managerId)->select('id');
        $myTelecallerIds = Lead::where('assigned_by', $managerId)
            ->whereNotNull('assigned_to')->distinct()->pluck('assigned_to');

        $query = CallLog::with([
            'lead:id,lead_code,name,phone',
            'user:id,name,role',
        ])->whereIn('lead_id', $myLeadsSubquery);

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        if ($request->filled('telecaller')) {
            $telecallerId = (int) $request->input('telecaller');
            $query->where('user_id', $telecallerId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        switch ($scope) {
            case 'inbound':
                $query->where(function ($subQuery) {
                    $subQuery->where('direction', 'inbound')
                        ->orWhere(function ($legacyQuery) {
                            $legacyQuery->whereNull('direction')->whereNull('user_id');
                        });
                });
                break;
            case 'outbound':
                $query->where(function ($subQuery) {
                    $subQuery->where('direction', 'outbound')
                        ->orWhere(function ($legacyQuery) {
                            $legacyQuery->whereNull('direction')
                                ->where(function ($q) {
                                    $q->whereNotNull('user_id');
                                });
                        });
                });
                break;
            case 'missed':
                $query->whereIn('status', ['no-answer', 'busy', 'failed', 'canceled']);
                break;
        }

        $query->latest('id');

        $callLogs = $query->paginate(15)->withQueryString();

        $telecallers = User::where('role', 'telecaller')->where('status', 1)->whereIn('id', $myTelecallerIds)->orderBy('name')->get(['id', 'name']);

        $statusOptions = [
            'initiated',
            'ringing',
            'in-progress',
            'answered',
            'completed',
            'busy',
            'failed',
            'no-answer',
            'canceled',
        ];

        return view('manager.call_logs.index', [
            'callLogs' => $callLogs,
            'telecallers' => $telecallers,
            'statusOptions' => $statusOptions,
            'scope' => $scope,
        ]);
    }
}
