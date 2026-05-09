<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AgentController extends Controller
{
    private const MODEL     = 'claude-sonnet-4-6';
    private const MAX_TURNS = 10;

    public function index()
    {
        return view('manager.agent.chat');
    }

    // ─── Entry point ──────────────────────────────────────────────────────────
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:800',
            'history' => 'nullable|array|max:20',
        ]);

        $apiKey = config('services.anthropic.api_key');
        if (!$apiKey) {
            return response()->json([
                'type'    => 'error',
                'icon'    => 'key_off',
                'message' => "API key not configured.\n\nAdd `ANTHROPIC_API_KEY=sk-ant-...` to your **.env** file.",
            ]);
        }

        $user    = Auth::user();
        $today   = now()->setTimezone('Asia/Kolkata')->format('l, d F Y');
        $nowTime = now()->setTimezone('Asia/Kolkata')->format('g:i A');

        $system = <<<SYSTEM
You are an intelligent CRM assistant for {$user->name}, a manager in an education admissions CRM.
Today: {$today}. Current time: {$nowTime} IST.

## What you can do
- **Daily briefing**: get a full morning snapshot — new leads, unassigned, conversions, follow-ups, calls, cold leads, team online status
- Filter and view leads: today's new leads, unassigned leads, assigned leads, leads by status, leads by telecaller, leads with overdue follow-ups
- Search for any specific lead by name, phone, or lead code
- Assign or reassign leads to active telecallers
- Get telecaller performance insights: calls made, conversion rate, assigned leads, follow-up status
- Get lead pipeline overview and analytics for any time period
- List all active telecallers with their current online status
- List team follow-ups for today, overdue, or upcoming

## Rules
1. **Always call find_lead first** when the user mentions a person's name — never assume lead_id.
2. **Always call list_telecallers first** when the user assigns a lead by telecaller name — never assume telecaller_id.
3. If find_lead returns multiple leads, ask the user which one they mean before proceeding.
4. When assigning a lead that already has an assignee, clearly state the current assignee and confirm the change.
5. Present insights with clear context: percentages, counts, and comparisons.
6. Be concise, professional, and data-driven. Format numbers clearly.
7. Never invent lead or telecaller data — only use what the tools return.
8. If a tool fails, explain what went wrong and suggest the next step.
SYSTEM;

        $history = collect($request->input('history', []))
            ->filter(fn($m) => isset($m['role'], $m['content']) && in_array($m['role'], ['user', 'assistant']))
            ->map(fn($m) => ['role' => $m['role'], 'content' => (string) $m['content']])
            ->values()
            ->toArray();

        $messages = array_merge(
            $history,
            [['role' => 'user', 'content' => $request->input('message')]]
        );

        return response()->json($this->runAgentLoop($apiKey, $system, $messages));
    }

    // ─── Agentic loop ─────────────────────────────────────────────────────────
    private function runAgentLoop(string $apiKey, string $system, array $messages): array
    {
        $tools = $this->toolDefinitions();
        $turns = 0;

        while ($turns < self::MAX_TURNS) {
            $turns++;

            $response = Http::timeout(30)
                ->withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'      => self::MODEL,
                    'max_tokens' => 1024,
                    'system'     => $system,
                    'tools'      => $tools,
                    'messages'   => $messages,
                ]);

            if (!$response->successful()) {
                $err    = $response->json('error.message', 'Unknown error');
                $status = $response->status();
                return [
                    'type'    => 'error',
                    'icon'    => 'cloud_off',
                    'message' => "Claude API error ({$status}): {$err}",
                ];
            }

            $body       = $response->json();
            $stopReason = $body['stop_reason'] ?? 'end_turn';
            $content    = $body['content']     ?? [];

            if ($stopReason === 'end_turn') {
                $text = collect($content)
                    ->where('type', 'text')
                    ->pluck('text')
                    ->join("\n\n");

                return [
                    'type'              => 'ai',
                    'icon'              => 'smart_toy',
                    'message'           => $text ?: 'Done.',
                    'assistant_message' => ['role' => 'assistant', 'content' => $text ?: 'Done.'],
                ];
            }

            if ($stopReason === 'tool_use') {
                $normalizedContent = array_map(function ($block) {
                    if (($block['type'] ?? '') === 'tool_use' && empty($block['input'])) {
                        $block['input'] = new \stdClass();
                    }
                    return $block;
                }, $content);
                $messages[] = ['role' => 'assistant', 'content' => $normalizedContent];

                $toolResults = [];
                foreach ($content as $block) {
                    if (($block['type'] ?? '') !== 'tool_use') continue;

                    $result        = $this->executeTool($block['name'], $block['input'] ?? []);
                    $toolResults[] = [
                        'type'        => 'tool_result',
                        'tool_use_id' => $block['id'],
                        'content'     => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ];
                }

                $messages[] = ['role' => 'user', 'content' => $toolResults];
                continue;
            }

            break;
        }

        return [
            'type'    => 'error',
            'icon'    => 'loop',
            'message' => 'The agent took too many steps. Please try a simpler request.',
        ];
    }

    // ─── Tool dispatcher ──────────────────────────────────────────────────────
    private function executeTool(string $name, array $input): array
    {
        return match ($name) {
            'filter_leads'            => $this->toolFilterLeads($input),
            'find_lead'               => $this->toolFindLead($input),
            'assign_lead'             => $this->toolAssignLead($input),
            'get_telecaller_insights' => $this->toolGetTelecallerInsights($input),
            'get_lead_insights'       => $this->toolGetLeadInsights($input),
            'list_telecallers'        => $this->toolListTelecallers($input),
            'list_followups'          => $this->toolListFollowups($input),
            'get_daily_briefing'      => $this->toolGetDailyBriefing(),
            default                   => ['ok' => false, 'error' => "Unknown tool: {$name}"],
        };
    }

    // ─── Tool: filter_leads ───────────────────────────────────────────────────
    private function toolFilterLeads(array $input): array
    {
        $scope        = $input['scope'] ?? 'all';
        $status       = $input['status'] ?? null;
        $telecallerId = $input['telecaller_id'] ?? null;
        $limit        = min((int) ($input['limit'] ?? 10), 20);

        $query = Lead::query();

        match ($scope) {
            'today_new'        => $query->whereDate('created_at', today()),
            'unassigned'       => $query->whereNull('assigned_to'),
            'assigned'         => $query->whereNotNull('assigned_to'),
            'overdue_followup' => $query->whereHas('followups', fn($q) =>
                $q->whereNull('completed_at')->whereDate('next_followup', '<', today())
            ),
            'by_status'      => $status ? $query->where('status', $status) : $query,
            'by_telecaller'  => $telecallerId ? $query->where('assigned_to', $telecallerId) : $query,
            default          => $query,
        };

        $totalCount = (clone $query)->count();

        $leads = $query
            ->with(['assignedUser:id,name'])
            ->latest()
            ->limit($limit)
            ->get(['id', 'lead_code', 'name', 'phone', 'status', 'assigned_to', 'created_at']);

        return [
            'ok'      => true,
            'scope'   => $scope,
            'total'   => $totalCount,
            'showing' => $leads->count(),
            'leads'   => $leads->map(fn($l) => [
                'id'          => $l->id,
                'lead_code'   => $l->lead_code,
                'name'        => $l->name,
                'phone'       => $l->phone,
                'status'      => $l->status,
                'assigned_to' => $l->assignedUser?->name ?? 'Unassigned',
                'created_at'  => Carbon::parse($l->created_at)->format('d M Y'),
            ])->toArray(),
        ];
    }

    // ─── Tool: find_lead ──────────────────────────────────────────────────────
    private function toolFindLead(array $input): array
    {
        $term = trim($input['query'] ?? '');

        if (strlen($term) < 2) {
            return ['ok' => false, 'error' => 'Search term must be at least 2 characters.'];
        }

        $leads = Lead::with(['assignedUser:id,name'])
            ->where(fn($q) =>
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('lead_code', 'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%")
            )
            ->limit(5)
            ->get(['id', 'lead_code', 'name', 'phone', 'email', 'status', 'assigned_to', 'created_at']);

        if ($leads->isEmpty()) {
            return ['ok' => false, 'error' => "No lead found matching '{$term}'."];
        }

        return [
            'ok'    => true,
            'leads' => $leads->map(fn($l) => [
                'id'          => $l->id,
                'lead_code'   => $l->lead_code,
                'name'        => $l->name,
                'phone'       => $l->phone,
                'email'       => $l->email,
                'status'      => $l->status,
                'assigned_to' => $l->assignedUser?->name ?? 'Unassigned',
                'created_at'  => Carbon::parse($l->created_at)->format('d M Y'),
            ])->toArray(),
        ];
    }

    // ─── Tool: assign_lead ────────────────────────────────────────────────────
    private function toolAssignLead(array $input): array
    {
        $lead = Lead::find($input['lead_id'] ?? 0);
        if (!$lead) {
            return ['ok' => false, 'error' => 'Lead not found.'];
        }

        $telecaller = User::where('id', $input['telecaller_id'] ?? 0)
            ->where('role', 'telecaller')
            ->where('status', 'active')
            ->first();

        if (!$telecaller) {
            return ['ok' => false, 'error' => 'Telecaller not found or not active. Use list_telecallers to get valid IDs.'];
        }

        $oldAssignee = $lead->assigned_to
            ? (User::find($lead->assigned_to)?->name ?? 'Unknown')
            : 'Unassigned';

        $lead->update([
            'assigned_to'         => $telecaller->id,
            'assigned_by'         => Auth::id(),
            'manager_assigned_at' => now(),
            'status'              => $lead->status === 'new' ? 'assigned' : $lead->status,
        ]);

        LeadActivity::create([
            'lead_id'       => $lead->id,
            'user_id'       => Auth::id(),
            'type'          => 'assigned',
            'description'   => "Lead assigned to {$telecaller->name} via AI assistant.",
            'activity_time' => now(),
        ]);

        AuditLogService::log('lead.assigned', 'Lead', $lead->id,
            ['assigned_to' => $oldAssignee],
            ['assigned_to' => $telecaller->name, 'source' => 'ai_agent']
        );

        return [
            'ok'          => true,
            'lead_name'   => $lead->name,
            'lead_code'   => $lead->lead_code,
            'assigned_to' => $telecaller->name,
            'from'        => $oldAssignee,
        ];
    }

    // ─── Tool: get_telecaller_insights ────────────────────────────────────────
    private function toolGetTelecallerInsights(array $input): array
    {
        $telecallerId = $input['telecaller_id'] ?? null;

        if ($telecallerId) {
            $tc = User::where('id', $telecallerId)->where('role', 'telecaller')->first();
            if (!$tc) {
                return ['ok' => false, 'error' => 'Telecaller not found.'];
            }

            $total         = Lead::where('assigned_to', $tc->id)->count();
            $converted     = Lead::where('assigned_to', $tc->id)->where('status', 'converted')->count();
            $interested    = Lead::where('assigned_to', $tc->id)->where('status', 'interested')->count();
            $notInterested = Lead::where('assigned_to', $tc->id)->where('status', 'not_interested')->count();
            $contacted     = Lead::where('assigned_to', $tc->id)->where('status', 'contacted')->count();
            $followUp      = Lead::where('assigned_to', $tc->id)->where('status', 'follow_up')->count();
            $callsToday    = CallLog::where('user_id', $tc->id)->whereDate('created_at', today())->count();
            $callsWeek     = CallLog::where('user_id', $tc->id)
                ->whereBetween('created_at', [now()->startOfWeek(), now()])->count();
            $callsMonth    = CallLog::where('user_id', $tc->id)
                ->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();
            $followupsToday   = Followup::where('user_id', $tc->id)->whereNull('completed_at')
                ->whereDate('next_followup', today())->count();
            $followupsOverdue = Followup::where('user_id', $tc->id)->whereNull('completed_at')
                ->whereDate('next_followup', '<', today())->count();
            $convRate = $total > 0 ? round(($converted / $total) * 100, 1) : 0;

            return [
                'ok'                => true,
                'telecaller'        => $tc->name,
                'is_online'         => $tc->is_online,
                'last_seen'         => $tc->last_seen_at ? Carbon::parse($tc->last_seen_at)->diffForHumans() : 'Never',
                'total_leads'       => $total,
                'converted'         => $converted,
                'interested'        => $interested,
                'contacted'         => $contacted,
                'follow_up'         => $followUp,
                'not_interested'    => $notInterested,
                'conversion_rate'   => $convRate . '%',
                'calls_today'       => $callsToday,
                'calls_this_week'   => $callsWeek,
                'calls_this_month'  => $callsMonth,
                'followups_today'   => $followupsToday,
                'followups_overdue' => $followupsOverdue,
            ];
        }

        // All telecallers summary
        $telecallers = User::where('role', 'telecaller')->where('status', 'active')->get();

        $summary = $telecallers->map(function ($tc) {
            $total     = Lead::where('assigned_to', $tc->id)->count();
            $converted = Lead::where('assigned_to', $tc->id)->where('status', 'converted')->count();
            $calls     = CallLog::where('user_id', $tc->id)->whereDate('created_at', today())->count();
            $overdue   = Followup::where('user_id', $tc->id)->whereNull('completed_at')
                ->whereDate('next_followup', '<', today())->count();

            return [
                'id'              => $tc->id,
                'name'            => $tc->name,
                'is_online'       => $tc->is_online,
                'total_leads'     => $total,
                'converted'       => $converted,
                'conversion_rate' => $total > 0 ? round(($converted / $total) * 100, 1) . '%' : '0%',
                'calls_today'     => $calls,
                'overdue_followups' => $overdue,
            ];
        });

        return [
            'ok'          => true,
            'type'        => 'all_telecallers_summary',
            'count'       => $telecallers->count(),
            'telecallers' => $summary->toArray(),
        ];
    }

    // ─── Tool: get_lead_insights ──────────────────────────────────────────────
    private function toolGetLeadInsights(array $input): array
    {
        $period = in_array($input['period'] ?? '', ['today', 'this_week', 'this_month', 'all'])
            ? ($input['period'] ?? 'this_month')
            : 'this_month';

        $applyPeriod = function ($q) use ($period) {
            match ($period) {
                'today'      => $q->whereDate('created_at', today()),
                'this_week'  => $q->whereBetween('created_at', [now()->startOfWeek(), now()]),
                'this_month' => $q->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
                default      => null,
            };
        };

        $base = Lead::query();
        $applyPeriod($base);

        $total      = (clone $base)->count();
        $unassigned = (clone $base)->whereNull('assigned_to')->count();
        $assigned   = (clone $base)->whereNotNull('assigned_to')->count();

        $statusBreakdown = (clone $base)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $topSources = (clone $base)
            ->select('source', DB::raw('count(*) as count'))
            ->groupBy('source')
            ->orderByDesc('count')
            ->limit(5)
            ->pluck('count', 'source')
            ->toArray();

        $converted      = $statusBreakdown['converted'] ?? 0;
        $conversionRate = $total > 0 ? round(($converted / $total) * 100, 1) : 0;

        $overdueFollowups = Followup::whereNull('completed_at')
            ->whereDate('next_followup', '<', today())->count();

        $followupsToday = Followup::whereNull('completed_at')
            ->whereDate('next_followup', today())->count();

        return [
            'ok'               => true,
            'period'           => $period,
            'total_leads'      => $total,
            'unassigned'       => $unassigned,
            'assigned'         => $assigned,
            'conversion_rate'  => $conversionRate . '%',
            'status_breakdown' => $statusBreakdown,
            'top_sources'      => $topSources,
            'overdue_followups' => $overdueFollowups,
            'followups_today'  => $followupsToday,
        ];
    }

    // ─── Tool: list_telecallers ───────────────────────────────────────────────
    private function toolListTelecallers(array $input): array
    {
        $telecallers = User::where('role', 'telecaller')
            ->where('status', 'active')
            ->get(['id', 'name', 'phone', 'is_online', 'last_seen_at']);

        return [
            'ok'          => true,
            'count'       => $telecallers->count(),
            'telecallers' => $telecallers->map(fn($tc) => [
                'id'        => $tc->id,
                'name'      => $tc->name,
                'phone'     => $tc->phone,
                'is_online' => $tc->is_online,
                'last_seen' => $tc->last_seen_at
                    ? Carbon::parse($tc->last_seen_at)->diffForHumans()
                    : 'Never',
            ])->toArray(),
        ];
    }

    // ─── Tool: get_daily_briefing ─────────────────────────────────────────────
    private function toolGetDailyBriefing(): array
    {
        $today = today();
        $now   = now();

        // ── Leads ──────────────────────────────────────────────────────────────
        $newLeadsToday  = Lead::whereDate('created_at', $today)->count();
        $unassigned     = Lead::whereNull('assigned_to')->count();
        $convertedToday = Lead::where('status', 'converted')->whereDate('updated_at', $today)->count();
        $monthTotal     = Lead::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count();
        $monthConverted = Lead::where('status', 'converted')
            ->whereMonth('updated_at', $now->month)->whereYear('updated_at', $now->year)->count();
        $monthConvRate  = $monthTotal > 0 ? round(($monthConverted / $monthTotal) * 100, 1) : 0;

        // ── Follow-ups ─────────────────────────────────────────────────────────
        $followupsToday   = Followup::whereNull('completed_at')->whereDate('next_followup', $today)->count();
        $followupsOverdue = Followup::whereNull('completed_at')->whereDate('next_followup', '<', $today)->count();

        // ── Calls today ────────────────────────────────────────────────────────
        $callsToday = CallLog::whereDate('created_at', $today)->count();

        // ── Telecaller status ──────────────────────────────────────────────────
        $telecallers = User::where('role', 'telecaller')->where('status', 'active')
            ->get(['id', 'name', 'is_online', 'last_seen_at']);

        $onlineCount  = $telecallers->where('is_online', true)->count();
        $offlineCount = $telecallers->where('is_online', false)->count();

        // Per-telecaller calls today + overdue followups
        $tcSummary = $telecallers->map(function ($tc) use ($today) {
            $calls   = CallLog::where('user_id', $tc->id)->whereDate('created_at', $today)->count();
            $overdue = Followup::where('user_id', $tc->id)->whereNull('completed_at')
                ->whereDate('next_followup', '<', $today)->count();
            return [
                'id'              => $tc->id,
                'name'            => $tc->name,
                'is_online'       => $tc->is_online,
                'calls_today'     => $calls,
                'overdue_followups' => $overdue,
            ];
        })->sortByDesc('calls_today')->values()->toArray();

        $topPerformer = collect($tcSummary)->first();

        // ── Cold leads (no activity in 7+ days) ────────────────────────────────
        $coldLeads = Lead::whereNotIn('status', ['converted', 'not_interested'])
            ->whereDoesntHave('activities', fn($q) =>
                $q->where('activity_time', '>=', now()->subDays(7))
            )->count();

        return [
            'ok'                   => true,
            'date'                 => $today->format('l, d F Y'),
            'new_leads_today'      => $newLeadsToday,
            'unassigned_leads'     => $unassigned,
            'converted_today'      => $convertedToday,
            'month_conversion_rate' => $monthConvRate . '%',
            'followups_today'      => $followupsToday,
            'followups_overdue'    => $followupsOverdue,
            'calls_today'          => $callsToday,
            'cold_leads'           => $coldLeads,
            'team_online'          => $onlineCount,
            'team_offline'         => $offlineCount,
            'total_telecallers'    => $telecallers->count(),
            'top_performer_today'  => $topPerformer ? [
                'name'        => $topPerformer['name'],
                'calls_today' => $topPerformer['calls_today'],
                'is_online'   => $topPerformer['is_online'],
            ] : null,
            'telecaller_summary'   => $tcSummary,
        ];
    }

    // ─── Tool: list_followups ─────────────────────────────────────────────────
    private function toolListFollowups(array $input): array
    {
        $scope        = in_array($input['scope'] ?? '', ['today', 'overdue', 'upcoming']) ? $input['scope'] : 'today';
        $telecallerId = $input['telecaller_id'] ?? null;

        $query = Followup::with(['lead:id,name,phone,lead_code', 'user:id,name'])
            ->whereNull('completed_at');

        if ($telecallerId) {
            $query->where('user_id', $telecallerId);
        }

        match ($scope) {
            'overdue'  => $query->whereDate('next_followup', '<', today()),
            'upcoming' => $query->whereDate('next_followup', '>', today()),
            default    => $query->whereDate('next_followup', today()),
        };

        $items = $query->orderBy('next_followup')->orderBy('followup_time')->limit(15)->get();

        return [
            'ok'        => true,
            'scope'     => $scope,
            'count'     => $items->count(),
            'followups' => $items->map(fn($f) => [
                'telecaller' => $f->user?->name  ?? 'Unknown',
                'lead_name'  => $f->lead?->name  ?? 'Unknown',
                'lead_code'  => $f->lead?->lead_code ?? '-',
                'phone'      => $f->lead?->phone  ?? '-',
                'date'       => Carbon::parse($f->next_followup)->format('d M Y'),
                'time'       => $f->followup_time ? Carbon::parse($f->followup_time)->format('g:i A') : '-',
            ])->toArray(),
        ];
    }

    // ─── Tool definitions for Claude API ─────────────────────────────────────
    private function toolDefinitions(): array
    {
        return [
            [
                'name'        => 'filter_leads',
                'description' => 'Filter leads by scope. Use this when the user asks for lists of leads by category.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'scope' => [
                            'type'        => 'string',
                            'description' => 'today_new | unassigned | assigned | overdue_followup | by_status | by_telecaller',
                        ],
                        'status' => [
                            'type'        => 'string',
                            'description' => 'Required when scope=by_status. Values: new | assigned | contacted | interested | follow_up | not_interested | converted',
                        ],
                        'telecaller_id' => [
                            'type'        => 'integer',
                            'description' => 'Required when scope=by_telecaller. Get IDs from list_telecallers.',
                        ],
                        'limit' => [
                            'type'        => 'integer',
                            'description' => 'Max leads to return (default 10, max 20)',
                        ],
                    ],
                    'required' => ['scope'],
                ],
            ],
            [
                'name'        => 'find_lead',
                'description' => 'Search any lead in the system by name, phone, or lead code. Always call this first when the user mentions a specific person.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'query' => [
                            'type'        => 'string',
                            'description' => 'Name, phone number, or lead code to search for',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name'        => 'assign_lead',
                'description' => 'Assign or reassign a lead to an active telecaller. Always call find_lead and list_telecallers first to get valid IDs.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'lead_id' => [
                            'type'        => 'integer',
                            'description' => 'Lead ID from find_lead',
                        ],
                        'telecaller_id' => [
                            'type'        => 'integer',
                            'description' => 'Telecaller ID from list_telecallers',
                        ],
                    ],
                    'required' => ['lead_id', 'telecaller_id'],
                ],
            ],
            [
                'name'        => 'get_telecaller_insights',
                'description' => 'Get performance data for a specific telecaller or all telecallers. Shows leads assigned, conversion rate, calls, and follow-ups.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'telecaller_id' => [
                            'type'        => 'integer',
                            'description' => 'Telecaller user ID (optional — omit to get a summary of all telecallers)',
                        ],
                    ],
                ],
            ],
            [
                'name'        => 'get_lead_insights',
                'description' => 'Get lead pipeline overview: totals, unassigned count, status breakdown, conversion rate, source analysis.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'period' => [
                            'type'        => 'string',
                            'description' => 'today | this_week | this_month | all (default: this_month)',
                        ],
                    ],
                ],
            ],
            [
                'name'        => 'list_telecallers',
                'description' => 'List all active telecallers with their IDs, online status, and last-seen time. Always call this before assigning leads by telecaller name.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => (object)[],
                ],
            ],
            [
                'name'        => 'get_daily_briefing',
                'description' => 'Get a full morning briefing snapshot: new leads today, unassigned leads, conversions, follow-up counts (today + overdue), calls made today, cold leads, team online status, and per-telecaller call counts. Call this when the user asks for a briefing, morning update, daily summary, or "what\'s happening today".',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => (object)[],
                ],
            ],
            [
                'name'        => 'list_followups',
                'description' => 'List team follow-ups: today, overdue, or upcoming. Can be filtered to a specific telecaller.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'scope' => [
                            'type'        => 'string',
                            'description' => 'today | overdue | upcoming (default: today)',
                        ],
                        'telecaller_id' => [
                            'type'        => 'integer',
                            'description' => 'Filter by telecaller ID (optional — omit for all telecallers)',
                        ],
                    ],
                ],
            ],
        ];
    }
}
