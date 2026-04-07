@extends('layouts.app')

@section('page_title', 'My Lead Pipeline')

@section('header_actions')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('telecaller.leads') }}" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
            <span class="material-icons" style="font-size:16px;">view_list</span>
            List View
        </a>
    </div>
@endsection

@php
$statusConfig = [
    'new'            => ['label' => 'New',           'color' => '#137fec', 'bg' => '#eff6ff', 'icon' => 'fiber_new'],
    'assigned'       => ['label' => 'Assigned',       'color' => '#8b5cf6', 'bg' => '#f5f3ff', 'icon' => 'assignment_ind'],
    'contacted'      => ['label' => 'Contacted',      'color' => '#f59e0b', 'bg' => '#fffbeb', 'icon' => 'phone_in_talk'],
    'interested'     => ['label' => 'Interested',     'color' => '#10b981', 'bg' => '#ecfdf5', 'icon' => 'thumb_up'],
    'follow_up'      => ['label' => 'Follow Up',      'color' => '#f97316', 'bg' => '#fff7ed', 'icon' => 'event_repeat'],
    'not_interested' => ['label' => 'Not Interested', 'color' => '#ef4444', 'bg' => '#fef2f2', 'icon' => 'thumb_down'],
    'converted'      => ['label' => 'Converted',      'color' => '#059669', 'bg' => '#d1fae5', 'icon' => 'check_circle'],
];
@endphp

@section('content')

{{-- ── Filter Bar ──────────────────────────────────────────────────────────── --}}
<div class="chart-card mb-3">
    <form method="GET" class="d-flex flex-wrap gap-3 align-items-end">
        <div>
            <label class="form-label mb-1" style="font-size:12px;font-weight:600;">Date</label>
            <select name="date_range" class="form-select form-select-sm" style="width:140px;">
                <option value="">All Time</option>
                <option value="today" {{ request('date_range') == 'today' ? 'selected' : '' }}>Today</option>
                <option value="7"     {{ request('date_range') == '7'     ? 'selected' : '' }}>Last 7 Days</option>
                <option value="30"    {{ request('date_range') == '30'    ? 'selected' : '' }}>Last 30 Days</option>
            </select>
        </div>
        <div>
            <label class="form-label mb-1" style="font-size:12px;font-weight:600;">Search</label>
            <input type="text" name="search" value="{{ request('search') }}"
                class="form-control form-control-sm" placeholder="Name / Phone / Code" style="width:210px;">
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm px-3">Apply</button>
            <a href="{{ route('telecaller.leads.pipeline') }}" class="btn btn-outline-secondary btn-sm px-3">Reset</a>
        </div>
    </form>
</div>

{{-- ── Summary Badges ──────────────────────────────────────────────────────── --}}
<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach ($statusConfig as $key => $cfg)
        <span class="badge d-flex align-items-center gap-1 px-3 py-2"
              style="background:{{ $cfg['bg'] }};color:{{ $cfg['color'] }};border:1px solid {{ $cfg['color'] }}20;font-size:12px;font-weight:600;border-radius:20px;">
            <span class="material-icons" style="font-size:13px;">{{ $cfg['icon'] }}</span>
            {{ $cfg['label'] }}
            <span class="ms-1" id="badge-count-{{ $key }}">{{ $columns[$key]->count() }}</span>
        </span>
    @endforeach
</div>

{{-- ── Kanban Board ─────────────────────────────────────────────────────────── --}}
<div id="kanbanBoard" style="display:flex;gap:14px;overflow-x:auto;padding-bottom:20px;align-items:flex-start;">

    @foreach ($statusConfig as $statusKey => $cfg)
    <div class="kanban-column" data-status="{{ $statusKey }}"
         style="min-width:260px;max-width:260px;background:#fff;border-radius:12px;border:1px solid #e2e8f0;display:flex;flex-direction:column;flex-shrink:0;">

        {{-- Column Header --}}
        <div style="padding:14px 16px 12px;border-bottom:2px solid {{ $cfg['color'] }};border-radius:12px 12px 0 0;background:{{ $cfg['bg'] }};">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="material-icons" style="font-size:18px;color:{{ $cfg['color'] }};">{{ $cfg['icon'] }}</span>
                    <span style="font-size:13px;font-weight:700;color:{{ $cfg['color'] }};">{{ $cfg['label'] }}</span>
                </div>
                <span class="kanban-count" id="col-count-{{ $statusKey }}"
                      style="background:{{ $cfg['color'] }};color:#fff;font-size:11px;font-weight:700;padding:2px 9px;border-radius:20px;min-width:24px;text-align:center;">
                    {{ $columns[$statusKey]->count() }}
                </span>
            </div>
        </div>

        {{-- Scrollable Cards Area --}}
        <div class="kanban-column-body"
             style="padding:10px;overflow-y:auto;max-height:calc(100vh - 320px);min-height:80px;display:flex;flex-direction:column;gap:8px;">

            @forelse ($columns[$statusKey] as $lead)
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
                    <a href="{{ route('telecaller.leads.show', encrypt($lead->id)) }}"
                       class="btn btn-sm"
                       style="font-size:11px;padding:2px 10px;background:#eff6ff;color:#137fec;border:1px solid #bfdbfe;border-radius:6px;font-weight:600;text-decoration:none;">
                        View
                    </a>
                </div>
            </div>
            @empty
            <div class="kanban-empty" style="text-align:center;padding:24px 12px;color:#94a3b8;font-size:12px;">
                <span class="material-icons" style="font-size:28px;display:block;margin-bottom:4px;opacity:.4;">inbox</span>
                No leads
            </div>
            @endforelse
        </div>
    </div>
    @endforeach

</div>

{{-- ── Drag Overlay ─────────────────────────────────────────────────────────── --}}
<div id="pipelineOverlay" style="display:none;position:fixed;inset:0;background:rgba(255,255,255,.5);z-index:9999;align-items:center;justify-content:center;">
    <div class="spinner-border text-primary"></div>
</div>

{{-- ── Toast ────────────────────────────────────────────────────────────────── --}}
<div id="pipelineToast" style="position:fixed;bottom:24px;right:24px;z-index:10000;min-width:220px;display:none;">
    <div id="pipelineToastInner" style="padding:12px 18px;border-radius:10px;font-size:13px;font-weight:600;color:#fff;box-shadow:0 4px 16px rgba(0,0,0,.15);">
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    const STATUS_URL = @json(route('telecaller.leads.pipeline.status'));
    const CSRF       = document.querySelector('meta[name="csrf-token"]').content;

    function showToast(msg, type) {
        const wrap  = document.getElementById('pipelineToast');
        const inner = document.getElementById('pipelineToastInner');
        inner.textContent = msg;
        inner.style.background = type === 'success' ? '#10b981' : '#ef4444';
        wrap.style.display = 'block';
        clearTimeout(wrap._t);
        wrap._t = setTimeout(() => { wrap.style.display = 'none'; }, 3200);
    }

    function getCount(key) {
        const el = document.getElementById('col-count-' + key);
        return el ? parseInt(el.textContent) || 0 : 0;
    }
    function setCount(key, n) {
        const col   = document.getElementById('col-count-' + key);
        const badge = document.getElementById('badge-count-' + key);
        if (col)   col.textContent   = Math.max(0, n);
        if (badge) badge.textContent = Math.max(0, n);
    }

    document.querySelectorAll('.kanban-column-body').forEach(function (colBody) {
        Sortable.create(colBody, {
            group:      'leads-pipeline',
            animation:  160,
            ghostClass: 'kanban-ghost',
            dragClass:  'kanban-dragging',
            handle:     '.kanban-card',
            onStart: function () {
                document.querySelectorAll('.kanban-column-body').forEach(function (cb) {
                    const empty = cb.querySelector('.kanban-empty');
                    if (empty) empty.style.display = 'none';
                });
            },
            onEnd: function (evt) {
                const card      = evt.item;
                const newColEl  = evt.to.closest('.kanban-column');
                const oldColEl  = evt.from.closest('.kanban-column');
                const newStatus = newColEl.dataset.status;
                const oldStatus = oldColEl.dataset.status;

                document.querySelectorAll('.kanban-column-body').forEach(function (cb) {
                    const empty = cb.querySelector('.kanban-empty');
                    if (empty) empty.style.display = cb.querySelectorAll('.kanban-card').length ? 'none' : 'block';
                });

                if (newStatus === oldStatus) return;

                const leadId = card.dataset.id;

                setCount(newStatus, getCount(newStatus) + 1);
                setCount(oldStatus, getCount(oldStatus) - 1);

                document.getElementById('pipelineOverlay').style.display = 'flex';

                fetch(STATUS_URL, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body:    JSON.stringify({ lead_id: leadId, status: newStatus }),
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    document.getElementById('pipelineOverlay').style.display = 'none';
                    if (data.success) {
                        showToast('Moved to "' + newStatus.replace('_', ' ') + '"', 'success');
                    } else {
                        evt.from.insertBefore(card, evt.from.children[evt.oldIndex] || null);
                        setCount(newStatus, getCount(newStatus) - 1);
                        setCount(oldStatus, getCount(oldStatus) + 1);
                        showToast(data.message || 'Failed to update.', 'error');
                    }
                })
                .catch(function () {
                    document.getElementById('pipelineOverlay').style.display = 'none';
                    evt.from.insertBefore(card, evt.from.children[evt.oldIndex] || null);
                    setCount(newStatus, getCount(newStatus) - 1);
                    setCount(oldStatus, getCount(oldStatus) + 1);
                    showToast('Network error — not saved.', 'error');
                });
            },
        });
    });

    document.querySelectorAll('.kanban-card').forEach(function (card) {
        card.addEventListener('mouseenter', function () {
            this.style.boxShadow = '0 6px 20px rgba(0,0,0,.10)';
            this.style.transform = 'translateY(-2px)';
        });
        card.addEventListener('mouseleave', function () {
            this.style.boxShadow = '';
            this.style.transform = '';
        });
    });
})();
</script>
<style>
.kanban-ghost   { opacity:.4;background:#eff6ff !important;border:2px dashed #137fec !important; }
.kanban-dragging{ box-shadow:0 12px 32px rgba(0,0,0,.18) !important;transform:rotate(1.5deg) !important;z-index:9999;cursor:grabbing !important; }
#kanbanBoard::-webkit-scrollbar { height:6px; }
#kanbanBoard::-webkit-scrollbar-track { background:#f1f5f9;border-radius:4px; }
#kanbanBoard::-webkit-scrollbar-thumb { background:#cbd5e1;border-radius:4px; }
.kanban-column-body::-webkit-scrollbar { width:4px; }
.kanban-column-body::-webkit-scrollbar-track { background:transparent; }
.kanban-column-body::-webkit-scrollbar-thumb { background:#e2e8f0;border-radius:4px; }
</style>
@endpush
