<?php

namespace App\Http\Controllers\Telecaller;

use App\Exports\ArrayExport;
use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Lead;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

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
            ->where('user_id', Auth::id())
            ->where(function ($q) {
                $q->whereNotNull('lead_id')
                  ->orWhereExists(function ($sub) {
                      $sub->selectRaw('1')
                          ->from('leads')
                          ->whereColumn('leads.phone', 'call_logs.customer_number');
                  });
            });

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        } elseif ($request->filled('date_from') || $request->filled('date_to')) {
            if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->input('date_from'));
            if ($request->filled('date_to'))   $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('outcome')) {
            $query->where('outcome', $request->input('outcome'));
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
                $query->where('status', 'missed');
                break;
            case 'history':
            default:
                break;
        }

        $statusOptions = [
            'ringing', 'in-progress', 'completed', 'answered',
            'missed', 'no-answer', 'busy', 'failed', 'canceled',
        ];

        $outcomeOptions = [
            'interested', 'not_interested', 'wrong_number',
            'call_back_later', 'switched_off',
        ];

        $paginated = $query->latest('id')->paginate(15)->withQueryString();

        // For call logs where lead_id is NULL but customer_number is set,
        // do a single batch lookup to resolve leads by phone number.
        $unresolvedPhones = $paginated->getCollection()
            ->filter(fn($call) => !$call->lead_id && filled($call->customer_number))
            ->map(fn($call) => $this->normalizeTo10Digit($call->customer_number))
            ->filter(fn($n) => $n !== null)
            ->unique()
            ->values();

        $phoneLeadMap = [];
        if ($unresolvedPhones->isNotEmpty()) {
            Lead::select('id', 'lead_code', 'name', 'phone')
                ->where(function ($q) use ($unresolvedPhones) {
                    foreach ($unresolvedPhones as $num) {
                        $q->orWhere('phone', $num)
                          ->orWhere('phone', '91' . $num)
                          ->orWhere('phone', '+91' . $num);
                    }
                })
                ->get()
                ->each(function ($lead) use (&$phoneLeadMap) {
                    $norm = $this->normalizeTo10Digit($lead->phone);
                    if ($norm) {
                        $phoneLeadMap[$norm] = $lead;
                    }
                });
        }

        $callLogs = $paginated->through(function ($call) use ($phoneLeadMap) {
            $duration = (int) ($call->duration ?? 0);
            $type     = ($call->direction === 'inbound') ? 'inbound' : 'outbound';

            $lead = $call->lead;
            if (!$lead && filled($call->customer_number)) {
                $norm = $this->normalizeTo10Digit($call->customer_number);
                $lead = $norm ? ($phoneLeadMap[$norm] ?? null) : null;
            }

            return [
                'id'              => $call->id,
                'created_at_fmt'  => optional($call->created_at)->format('d M Y, h:i A'),
                'lead_name'       => $lead?->name,
                'lead_code'       => $lead?->lead_code,
                'lead_phone'      => $lead?->phone,
                'customer_number' => $call->customer_number,
                'lead_id'         => $call->lead_id ?? $lead?->id,
                'direction'       => $type,
                'status'          => $call->status ?? '',
                'duration_fmt'    => sprintf(
                    '%02d:%02d:%02d',
                    floor($duration / 3600),
                    floor(($duration % 3600) / 60),
                    $duration % 60
                ),
                'outcome'         => $call->outcome,
                'answered_at'     => optional($call->answered_at)->format('d M Y, h:i A'),
                'ended_at'        => optional($call->ended_at)->format('d M Y, h:i A'),
                'ended_by'        => $call->ended_by,
                'end_reason'      => $call->end_reason,
                'call_sid'        => $call->call_sid,
            ];
        });

        return Inertia::render('Telecaller/Calls/Index', [
            'scope'          => $scope,
            'title'          => $title,
            'callLogs'       => $callLogs,
            'statusOptions'  => $statusOptions,
            'outcomeOptions' => $outcomeOptions,
            'filters'        => $request->only('date', 'date_from', 'date_to', 'status', 'outcome'),
        ]);
    }

    public function export(Request $request, string $scope)
    {
        $format = $request->input('format', 'excel');
        $titles = [
            'outbound' => 'Outbound Calls',
            'inbound'  => 'Inbound Calls',
            'missed'   => 'Missed Calls',
            'history'  => 'Call History',
        ];
        $title = $titles[$scope] ?? 'Call History';

        $query = CallLog::with(['lead:id,lead_code,name,phone'])
            ->where('user_id', Auth::id())
            ->where(function ($q) {
                $q->whereNotNull('lead_id')
                  ->orWhereExists(function ($sub) {
                      $sub->selectRaw('1')->from('leads')
                          ->whereColumn('leads.phone', 'call_logs.customer_number');
                  });
            });

        if ($request->filled('date'))    $query->whereDate('created_at', $request->input('date'));
        if ($request->filled('status'))  $query->where('status', $request->input('status'));
        if ($request->filled('outcome')) $query->where('outcome', $request->input('outcome'));

        switch ($scope) {
            case 'outbound':
                $query->where(fn($q) => $q->where('direction', 'outbound')->orWhereNull('direction'));
                break;
            case 'inbound':
                $query->where('direction', 'inbound');
                break;
            case 'missed':
                $query->where('status', 'missed');
                break;
        }

        $outcomeLabels = [
            'interested'      => 'Interested',
            'not_interested'  => 'Not Interested',
            'wrong_number'    => 'Wrong Number',
            'call_back_later' => 'Call Back Later',
            'switched_off'    => 'Switched Off',
        ];

        $unresolvedPhones = $query->latest('id')->get()
            ->filter(fn($c) => !$c->lead_id && filled($c->customer_number))
            ->map(fn($c) => $this->normalizeTo10Digit($c->customer_number))
            ->filter()->unique()->values();

        $phoneLeadMap = [];
        if ($unresolvedPhones->isNotEmpty()) {
            Lead::select('id', 'lead_code', 'name', 'phone')
                ->where(function ($q) use ($unresolvedPhones) {
                    foreach ($unresolvedPhones as $num) {
                        $q->orWhere('phone', $num)
                          ->orWhere('phone', '91' . $num)
                          ->orWhere('phone', '+91' . $num);
                    }
                })->get()->each(function ($lead) use (&$phoneLeadMap) {
                    $norm = $this->normalizeTo10Digit($lead->phone);
                    if ($norm) $phoneLeadMap[$norm] = $lead;
                });
        }

        $calls = $query->latest('id')->get()->values()->map(function ($call, $idx) use ($phoneLeadMap, $outcomeLabels) {
            $duration = (int) ($call->duration ?? 0);
            $lead = $call->lead;
            if (!$lead && filled($call->customer_number)) {
                $norm = $this->normalizeTo10Digit($call->customer_number);
                $lead = $norm ? ($phoneLeadMap[$norm] ?? null) : null;
            }
            return [
                'sno'       => $idx + 1,
                'date'      => optional($call->created_at)->format('d M Y, h:i A') ?? '—',
                'lead_name' => $lead?->name ?? 'N/A',
                'lead_code' => $lead?->lead_code ?? '—',
                'phone'     => $lead?->phone ?? $call->customer_number ?? '—',
                'direction' => $call->direction === 'inbound' ? 'inbound' : 'outbound',
                'status'    => $call->status ?? '—',
                'duration'  => sprintf('%02d:%02d:%02d', floor($duration / 3600), floor(($duration % 3600) / 60), $duration % 60),
                'outcome'   => $outcomeLabels[$call->outcome] ?? ($call->outcome ?? ''),
            ];
        })->toArray();

        $filename = strtolower(str_replace(' ', '-', $title)) . '-' . now()->format('Ymd-His');
        $meta = ['userName' => Auth::user()->name, 'generatedAt' => now()->format('d M Y, h:i A'), 'title' => $title];

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.telecaller.calls', array_merge($meta, ['calls' => $calls]))
                ->setPaper('a4', 'landscape');
            return $pdf->download($filename . '.pdf');
        }

        $headings = ['S.No', 'Date', 'Lead Name', 'Lead Code', 'Phone', 'Direction', 'Status', 'Duration', 'Outcome'];
        return Excel::download(new ArrayExport($calls, $headings, $title), $filename . '.xlsx');
    }

    private function normalizeTo10Digit(?string $phone): ?string
    {
        if (!$phone) return null;
        $raw = preg_replace('/\D/', '', $phone);
        if (strlen($raw) === 12 && str_starts_with($raw, '91')) {
            $raw = substr($raw, 2);
        }
        return strlen($raw) === 10 ? $raw : null;
    }
}
