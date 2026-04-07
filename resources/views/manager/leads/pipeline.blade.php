@extends('layouts.manager.app')

@section('page_title', 'Lead Pipeline')

@section('header_actions')
    <a href="{{ route('manager.leads.create') }}" class="btn btn-primary d-flex align-items-center gap-1">
        <span class="material-icons" style="font-size:16px;">add</span>
        Add Lead
    </a>
@endsection

@section('header_actions1')
    <div class="d-flex align-items-center gap-2 flex-wrap mt-2">
        <a href="{{ route('manager.leads') }}" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
            <span class="material-icons" style="font-size:16px;">view_list</span>
            List View
        </a>
        <a href="{{ route('manager.leads.export') }}" class="btn btn-sm btn-outline-success d-flex align-items-center gap-1">
            <span class="material-icons" style="font-size:16px;">download</span>
            Export
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
            <select name="date_range" class="form-select form-select-sm" style="width:130px;">
                <option value="">All Time</option>
                <option value="today" {{ request('date_range') == 'today' ? 'selected' : '' }}>Today</option>
                <option value="7"     {{ request('date_range') == '7'     ? 'selected' : '' }}>Last 7 Days</option>
                <option value="30"    {{ request('date_range') == '30'    ? 'selected' : '' }}>Last 30 Days</option>
            </select>
        </div>
        <div>
            <label class="form-label mb-1" style="font-size:12px;font-weight:600;">Telecaller</label>
            <select name="telecaller" class="form-select form-select-sm" style="width:170px;">
                <option value="">All Telecallers</option>
                @foreach ($telecallers as $tc)
                    <option value="{{ $tc->id }}" {{ request('telecaller') == $tc->id ? 'selected' : '' }}>
                        {{ $tc->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label mb-1" style="font-size:12px;font-weight:600;">Search</label>
            <input type="text" name="search" value="{{ request('search') }}"
                class="form-control form-control-sm" placeholder="Name / Phone / Code" style="width:200px;">
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm px-3">Apply</button>
            <a href="{{ route('manager.leads.pipeline') }}" class="btn btn-outline-secondary btn-sm px-3">Reset</a>
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
            <span class="ms-1" id="badge-count-{{ $key }}">{{ $columnTotals[$key] }}</span>
        </span>
    @endforeach
</div>

{{-- ── Kanban Board ─────────────────────────────────────────────────────────── --}}
<div id="kanbanBoard" style="display:flex;gap:14px;overflow-x:auto;padding-bottom:20px;align-items:flex-start;">

    @foreach ($statusConfig as $statusKey => $cfg)
    <div class="kanban-column" data-status="{{ $statusKey }}"
         style="min-width:272px;max-width:272px;background:#fff;border-radius:12px;border:1px solid #e2e8f0;display:flex;flex-direction:column;flex-shrink:0;">

        {{-- Column Header --}}
        <div style="padding:14px 16px 12px;border-bottom:2px solid {{ $cfg['color'] }};border-radius:12px 12px 0 0;background:{{ $cfg['bg'] }};">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="material-icons" style="font-size:18px;color:{{ $cfg['color'] }};">{{ $cfg['icon'] }}</span>
                    <span style="font-size:13px;font-weight:700;color:{{ $cfg['color'] }};">{{ $cfg['label'] }}</span>
                </div>
                <span class="kanban-count" id="col-count-{{ $statusKey }}"
                      style="background:{{ $cfg['color'] }};color:#fff;font-size:11px;font-weight:700;padding:2px 9px;border-radius:20px;min-width:24px;text-align:center;">
                    {{ $columnTotals[$statusKey] }}
                </span>
            </div>
        </div>

        {{-- Scrollable Cards Area --}}
        <div class="kanban-column-body"
             style="padding:10px;overflow-y:auto;max-height:calc(100vh - 340px);min-height:80px;display:flex;flex-direction:column;gap:8px;">

            @forelse ($columns[$statusKey] as $lead)
                @include('manager.leads._pipeline-card', ['lead' => $lead])
            @empty
            <div class="kanban-empty" style="text-align:center;padding:24px 12px;color:#94a3b8;font-size:12px;">
                <span class="material-icons" style="font-size:28px;display:block;margin-bottom:4px;opacity:.4;">inbox</span>
                No leads
            </div>
            @endforelse
        </div>

        {{-- Load More --}}
        @if($columnTotals[$statusKey] > $columns[$statusKey]->count())
        <div class="kanban-load-more-wrap" style="padding:8px 10px 10px;">
            <div id="col-info-{{ $statusKey }}" style="font-size:11px;color:#94a3b8;text-align:center;margin-bottom:6px;">
                Showing {{ $columns[$statusKey]->count() }} of {{ $columnTotals[$statusKey] }}
            </div>
            <button class="btn-load-more"
                    data-status="{{ $statusKey }}"
                    data-offset="{{ $columns[$statusKey]->count() }}"
                    style="width:100%;border:1px dashed #cbd5e1;background:transparent;color:#64748b;font-size:12px;font-weight:600;padding:8px;border-radius:8px;cursor:pointer;">
                <span class="material-icons" style="font-size:14px;vertical-align:middle;">expand_more</span>
                Load More
            </button>
        </div>
        @endif

    </div>
    @endforeach

</div>

{{-- ── Assign Telecaller Modal ──────────────────────────────────────────────── --}}
<div class="modal fade" id="assignTelecallerModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content" style="border-radius:14px;border:none;box-shadow:0 20px 60px rgba(0,0,0,.15);">
            <div class="modal-header" style="border-bottom:1px solid #f1f5f9;padding:20px 24px 16px;">
                <div class="d-flex align-items-center gap-2">
                    <span class="material-icons" style="color:#8b5cf6;font-size:22px;">assignment_ind</span>
                    <h5 class="modal-title mb-0" style="font-size:15px;font-weight:700;color:#0f172a;">Assign Telecaller</h5>
                </div>
            </div>
            <div class="modal-body" style="padding:20px 24px;">
                <p style="font-size:13px;color:#64748b;margin-bottom:16px;">
                    Select a telecaller to assign this lead to. The lead status will be set to <strong>Assigned</strong>.
                </p>
                <label class="form-label" style="font-size:12px;font-weight:600;color:#374151;">Telecaller</label>
                <select id="modalTelecallerSelect" class="form-select">
                    <option value="">-- Select Telecaller --</option>
                    @foreach($telecallers as $tc)
                        <option value="{{ encrypt($tc->id) }}">{{ $tc->name }}</option>
                    @endforeach
                </select>
                <div id="assignModalError" style="display:none;color:#ef4444;font-size:12px;margin-top:8px;">
                    Please select a telecaller to continue.
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f1f5f9;padding:16px 24px;">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="btnAssignConfirm" class="btn btn-primary btn-sm px-4">
                    <span class="material-icons" style="font-size:14px;vertical-align:middle;">check</span>
                    Assign & Move
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── Drag Overlay Spinner ────────────────────────────────────────────────── --}}
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
    const STATUS_URL = @json(route('manager.leads.pipeline.status'));
    const MORE_URL   = @json(route('manager.leads.pipeline.more'));
    const CSRF       = document.querySelector('meta[name="csrf-token"]').content;
    const FILTERS    = @json(request()->only(['search', 'telecaller', 'date_range']));

    // Pending drag state — set when dragging into "assigned" column
    let pendingDrag = null;

    // ── Toast ────────────────────────────────────────────────────────────────
    function showToast(msg, type) {
        const wrap  = document.getElementById('pipelineToast');
        const inner = document.getElementById('pipelineToastInner');
        inner.textContent = msg;
        inner.style.background = type === 'success' ? '#10b981' : '#ef4444';
        wrap.style.display = 'block';
        clearTimeout(wrap._t);
        wrap._t = setTimeout(() => { wrap.style.display = 'none'; }, 3200);
    }

    // ── Count helpers ────────────────────────────────────────────────────────
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

    // ── Card hover ───────────────────────────────────────────────────────────
    function attachCardHover(card) {
        card.addEventListener('mouseenter', function () {
            this.style.boxShadow = '0 6px 20px rgba(0,0,0,.10)';
            this.style.transform = 'translateY(-2px)';
        });
        card.addEventListener('mouseleave', function () {
            this.style.boxShadow = '';
            this.style.transform = '';
        });
    }
    document.querySelectorAll('.kanban-card').forEach(attachCardHover);

    // ── Send status update ───────────────────────────────────────────────────
    function sendStatusUpdate(leadId, newStatus, oldStatus, card, oldColBody, oldIndex, telecallerId) {
        document.getElementById('pipelineOverlay').style.display = 'flex';

        const body = { lead_id: leadId, status: newStatus };
        if (telecallerId) body.telecaller_id = telecallerId;

        fetch(STATUS_URL, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    JSON.stringify(body),
        })
        .then(r => r.json())
        .then(data => {
            document.getElementById('pipelineOverlay').style.display = 'none';
            if (data.success) {
                if (data.telecaller_name) {
                    const el = card.querySelector('[data-assigned]');
                    if (el) el.textContent = data.telecaller_name;
                }
                showToast('Moved to "' + newStatus.replace('_', ' ') + '"', 'success');
            } else {
                oldColBody.insertBefore(card, oldColBody.children[oldIndex] || null);
                setCount(newStatus, getCount(newStatus) - 1);
                setCount(oldStatus,  getCount(oldStatus)  + 1);
                showToast(data.message || 'Failed to update status.', 'error');
            }
        })
        .catch(() => {
            document.getElementById('pipelineOverlay').style.display = 'none';
            oldColBody.insertBefore(card, oldColBody.children[oldIndex] || null);
            setCount(newStatus, getCount(newStatus) - 1);
            setCount(oldStatus,  getCount(oldStatus)  + 1);
            showToast('Network error — status not saved.', 'error');
        });
    }

    // ── SortableJS ───────────────────────────────────────────────────────────
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
                setCount(oldStatus,  getCount(oldStatus)  - 1);

                // Dragging into "assigned" → require telecaller selection
                if (newStatus === 'assigned') {
                    pendingDrag = { leadId, newStatus, oldStatus, card, oldColBody: evt.from, oldIndex: evt.oldIndex };
                    document.getElementById('modalTelecallerSelect').value = '';
                    document.getElementById('assignModalError').style.display = 'none';
                    (new bootstrap.Modal(document.getElementById('assignTelecallerModal'))).show();
                    return;
                }

                sendStatusUpdate(leadId, newStatus, oldStatus, card, evt.from, evt.oldIndex, null);
            },
        });
    });

    // ── Assign Modal: Confirm ────────────────────────────────────────────────
    document.getElementById('btnAssignConfirm').addEventListener('click', function () {
        const telecallerId = document.getElementById('modalTelecallerSelect').value;
        if (!telecallerId) {
            document.getElementById('assignModalError').style.display = 'block';
            return;
        }
        document.getElementById('assignModalError').style.display = 'none';

        const { leadId, newStatus, oldStatus, card, oldColBody, oldIndex } = pendingDrag;
        pendingDrag = null; // clear BEFORE hiding so hidden.bs.modal doesn't revert

        bootstrap.Modal.getInstance(document.getElementById('assignTelecallerModal')).hide();
        sendStatusUpdate(leadId, newStatus, oldStatus, card, oldColBody, oldIndex, telecallerId);
    });

    // ── Assign Modal: Cancelled (X / Cancel / backdrop) ─────────────────────
    document.getElementById('assignTelecallerModal').addEventListener('hidden.bs.modal', function () {
        if (!pendingDrag) return; // already handled by Confirm
        const { newStatus, oldStatus, card, oldColBody, oldIndex } = pendingDrag;
        oldColBody.insertBefore(card, oldColBody.children[oldIndex] || null);
        setCount(newStatus, getCount(newStatus) - 1);
        setCount(oldStatus,  getCount(oldStatus)  + 1);
        pendingDrag = null;
    });

    // ── Load More ────────────────────────────────────────────────────────────
    document.querySelectorAll('.btn-load-more').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const status = this.dataset.status;
            const offset = parseInt(this.dataset.offset);
            const colBody = document.querySelector('.kanban-column[data-status="' + status + '"] .kanban-column-body');

            this.disabled = true;
            this.innerHTML = '<span class="material-icons" style="font-size:14px;vertical-align:middle;animation:spin .8s linear infinite;">refresh</span> Loading...';

            const params = new URLSearchParams(Object.assign({ status, offset }, FILTERS));

            fetch(MORE_URL + '?' + params, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                // Remove empty placeholder if present
                const empty = colBody.querySelector('.kanban-empty');
                if (empty) empty.remove();

                // Append new cards
                data.cards.forEach(html => {
                    const tmp = document.createElement('div');
                    tmp.innerHTML = html.trim();
                    const card = tmp.firstElementChild;
                    colBody.appendChild(card);
                    attachCardHover(card);
                });

                // Update info text
                const info = document.getElementById('col-info-' + status);
                if (info) info.textContent = 'Showing ' + data.loaded + ' of ' + data.total;

                if (data.has_more) {
                    this.dataset.offset = data.loaded;
                    this.disabled = false;
                    this.innerHTML = '<span class="material-icons" style="font-size:14px;vertical-align:middle;">expand_more</span> Load More';
                } else {
                    this.closest('.kanban-load-more-wrap').remove();
                }
            })
            .catch(() => {
                this.disabled = false;
                this.innerHTML = '<span class="material-icons" style="font-size:14px;vertical-align:middle;">expand_more</span> Load More';
                showToast('Failed to load more leads.', 'error');
            });
        });
    });
})();
</script>
<style>
.kanban-ghost    { opacity:.4;background:#eff6ff !important;border:2px dashed #137fec !important; }
.kanban-dragging { box-shadow:0 12px 32px rgba(0,0,0,.18) !important;transform:rotate(1.5deg) !important;z-index:9999;cursor:grabbing !important; }
.btn-load-more:hover { background:#f8fafc !important;border-color:#94a3b8 !important; }
@keyframes spin { to { transform:rotate(360deg); } }
#kanbanBoard::-webkit-scrollbar { height:6px; }
#kanbanBoard::-webkit-scrollbar-track { background:#f1f5f9;border-radius:4px; }
#kanbanBoard::-webkit-scrollbar-thumb { background:#cbd5e1;border-radius:4px; }
.kanban-column-body::-webkit-scrollbar { width:4px; }
.kanban-column-body::-webkit-scrollbar-track { background:transparent; }
.kanban-column-body::-webkit-scrollbar-thumb { background:#e2e8f0;border-radius:4px; }
.kanban-column-body::-webkit-scrollbar-thumb:hover { background:#cbd5e1; }
</style>
@endpush
