<div class="kanban-card"
     data-id="{{ encrypt($lead->id) }}"
     style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px;cursor:grab;transition:box-shadow .15s,transform .15s;position:relative;">

    {{-- Top row: code + aging --}}
    <div class="d-flex justify-content-between align-items-center mb-1">
        <span style="font-size:10px;font-weight:700;color:#64748b;letter-spacing:.4px;">
            {{ $lead->lead_code }}
        </span>
        <x-aging-badge :days="$lead->days_aged" />
    </div>

    {{-- Name --}}
    <div style="font-size:13px;font-weight:700;color:#0f172a;line-height:1.3;margin-bottom:6px;">
        {{ $lead->name }}
        @if($lead->is_duplicate)
            <span style="font-size:9px;background:#fff7ed;color:#ea580c;border:1px solid #fed7aa;padding:1px 5px;border-radius:4px;font-weight:600;vertical-align:middle;">DUP</span>
        @endif
    </div>

    {{-- Phone --}}
    <div class="d-flex align-items-center gap-1 mb-1" style="font-size:12px;color:#475569;">
        <span class="material-icons" style="font-size:13px;color:#94a3b8;">phone</span>
        {{ $lead->phone }}
    </div>

    {{-- Course --}}
    @if($lead->course)
    <div class="d-flex align-items-center gap-1 mb-1" style="font-size:11px;color:#64748b;">
        <span class="material-icons" style="font-size:13px;color:#94a3b8;">school</span>
        {{ $lead->course }}
    </div>
    @endif

    {{-- Assigned to --}}
    <div class="d-flex align-items-center gap-1 mb-2" style="font-size:11px;color:#64748b;">
        <span class="material-icons" style="font-size:13px;color:#94a3b8;">person</span>
        <span data-assigned>{{ $lead->assignedUser?->name ?? 'Unassigned' }}</span>
    </div>

    {{-- Next followup --}}
    @php $latestFu = $lead->followups->sortByDesc('next_followup')->first(); @endphp
    @if($latestFu?->next_followup)
    <div class="d-flex align-items-center gap-1 mb-2" style="font-size:11px;color:#f97316;">
        <span class="material-icons" style="font-size:13px;">event</span>
        {{ \Carbon\Carbon::parse($latestFu->next_followup)->format('d M Y') }}
    </div>
    @endif

    {{-- Footer --}}
    <div class="d-flex justify-content-between align-items-center" style="margin-top:4px;padding-top:8px;border-top:1px solid #f1f5f9;">
        <span style="font-size:10px;color:#94a3b8;">
            {{ $lead->created_at->format('d M') }}
        </span>
        <a href="{{ route('manager.leads.show', encrypt($lead->id)) }}"
           class="btn btn-sm"
           style="font-size:11px;padding:2px 10px;background:#eff6ff;color:#137fec;border:1px solid #bfdbfe;border-radius:6px;font-weight:600;text-decoration:none;">
            View
        </a>
    </div>
</div>
