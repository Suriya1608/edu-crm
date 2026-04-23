<?php

namespace App\Http\Controllers\Telecaller;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

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

        $callLogs = $query->latest('id')->paginate(15)->withQueryString()->through(function ($call) {
            $duration = (int) ($call->duration ?? 0);
            $type     = ($call->direction === 'inbound') ? 'inbound' : 'outbound';

            return [
                'id'              => $call->id,
                'created_at_fmt'  => optional($call->created_at)->format('d M Y, h:i A'),
                'lead_name'       => $call->lead?->name,
                'lead_code'       => $call->lead?->lead_code,
                'lead_phone'      => $call->lead?->phone,
                'customer_number' => $call->customer_number,
                'lead_id'         => $call->lead_id,
                'direction'       => $type,
                'status'          => $call->status ?? '',
                'duration_fmt'    => sprintf(
                    '%02d:%02d:%02d',
                    floor($duration / 3600),
                    floor(($duration % 3600) / 60),
                    $duration % 60
                ),
                'recording_url'   => $call->recording_url,
            ];
        });

        return Inertia::render('Telecaller/Calls/Index', [
            'scope'         => $scope,
            'title'         => $title,
            'callLogs'      => $callLogs,
            'statusOptions' => $statusOptions,
            'filters'       => $request->only('date', 'status'),
        ]);
    }
}

