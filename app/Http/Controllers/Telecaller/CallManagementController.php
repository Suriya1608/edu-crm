<?php

namespace App\Http\Controllers\Telecaller;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CallManagementController extends Controller
{
    public function outbound(Request $request)
    {
        return $this->indexByScope($request, 'outbound', 'Outbound Calls');
    }

    public function inbound(Request $request)
    {
        return $this->indexByScope($request, 'inbound', 'Inbound Calls');
    }

    public function missed(Request $request)
    {
        return $this->indexByScope($request, 'missed', 'Missed Calls');
    }

    public function history(Request $request)
    {
        return $this->indexByScope($request, 'history', 'Call History');
    }

    private function indexByScope(Request $request, string $scope, string $title)
    {
        $query = CallLog::with(['lead:id,lead_code,name,phone'])
            ->where('user_id', Auth::id());

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        switch ($scope) {
            case 'outbound':
                $query->where(function ($q) {
                    $q->where('direction', 'outbound')
                        ->orWhereNull('direction');
                });
                break;
            case 'inbound':
                $query->where('direction', 'inbound');
                break;
            case 'missed':
                $query->whereIn('status', ['missed', 'no-answer', 'busy', 'failed', 'canceled']);
                break;
            case 'history':
            default:
                break;
        }

        $callLogs = $query->latest('id')->paginate(15)->withQueryString();

        $statusOptions = [
            'ringing',
            'in-progress',
            'completed',
            'answered',
            'missed',
            'no-answer',
            'busy',
            'failed',
            'canceled',
        ];

        return view('telecaller.calls.index', compact('callLogs', 'scope', 'title', 'statusOptions'));
    }
}

