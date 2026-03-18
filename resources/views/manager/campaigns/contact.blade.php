@extends('layouts.manager.app')

@section('page_title', $contact->name . ' — ' . $campaign->name)

@section('content')
    @include('layouts.whatsappchat')

    {{-- Sub-nav --}}
    <div class="lead-profile-nav">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('manager.campaigns.show', encrypt($campaign->id)) }}" class="btn btn-sm btn-light">
                    <span class="material-icons me-1" style="font-size:18px;">arrow_back</span>
                    Back to {{ $campaign->name }}
                </a>
                <div>
                    <h2 class="page-header-title mb-0">{{ $contact->name }}</h2>
                    <p class="page-header-subtitle mb-0">Campaign Contact</p>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-content">
        <div class="row g-4">

            {{-- LEFT: Profile Card --}}
            <div class="col-lg-4">
                <div class="profile-card mb-4">
                    <div class="profile-header">
                        <div class="profile-info">
                            <h1 class="profile-name">{{ $contact->name }}</h1>
                            <span class="badge bg-{{ App\Models\CampaignContact::statusColor($contact->status) }} mb-2">
                                {{ App\Models\CampaignContact::statusLabel($contact->status) }}
                            </span>
                            <p class="profile-id">{{ $campaign->name }}</p>
                        </div>
                    </div>

                    <div class="profile-details">
                        <div class="detail-item">
                            <span class="material-icons">phone</span>
                            <div class="flex-grow-1">
                                <p class="detail-label">Mobile</p>
                                <p class="detail-value">{{ $contact->phone }}</p>
                            </div>
                        </div>

                        @if ($contact->email)
                            <div class="detail-item">
                                <span class="material-icons">mail</span>
                                <div class="flex-grow-1">
                                    <p class="detail-label">Email</p>
                                    <p class="detail-value">{{ $contact->email }}</p>
                                </div>
                            </div>
                        @endif

                        @if ($contact->course)
                            <div class="detail-item">
                                <span class="material-icons">school</span>
                                <div class="flex-grow-1">
                                    <p class="detail-label">Course Interest</p>
                                    <p class="detail-value">{{ $contact->course }}</p>
                                </div>
                            </div>
                        @endif

                        @if ($contact->city)
                            <div class="detail-item">
                                <span class="material-icons">location_on</span>
                                <div class="flex-grow-1">
                                    <p class="detail-label">City</p>
                                    <p class="detail-value">{{ $contact->city }}</p>
                                </div>
                            </div>
                        @endif

                        <div class="detail-item">
                            <span class="material-icons">person</span>
                            <div class="flex-grow-1">
                                <p class="detail-label">Assigned To</p>
                                <p class="detail-value">{{ $contact->assignedUser?->name ?? '—' }}</p>
                            </div>
                        </div>

                        <div class="detail-item">
                            <span class="material-icons">call</span>
                            <div class="flex-grow-1">
                                <p class="detail-label">Total Calls Made</p>
                                <p class="detail-value">{{ $contact->call_count }}</p>
                            </div>
                        </div>

                        @if ($contact->next_followup)
                            <div class="detail-item">
                                <span class="material-icons">event</span>
                                <div class="flex-grow-1">
                                    <p class="detail-label">Next Follow-up</p>
                                    <p class="detail-value">
                                        {{ $contact->next_followup->format('d M Y') }}
                                        @if ($contact->followup_time)
                                            <span class="text-primary fw-semibold">
                                                {{ date('h:i A', strtotime($contact->followup_time)) }}
                                            </span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Reassign Contact --}}
                <div class="chart-card mb-4">
                    <div class="chart-header mb-3">
                        <h3>Assign / Reassign</h3>
                    </div>
                    <form action="{{ route('manager.campaigns.contact.reassign', [encrypt($campaign->id), encrypt($contact->id)]) }}"
                        method="POST">
                        @csrf @method('PATCH')
                        <select name="assigned_to" class="form-select form-select-sm mb-2" required>
                            <option value="">Select Telecaller</option>
                            @foreach ($telecallers as $tc)
                                <option value="{{ $tc->id }}" {{ $contact->assigned_to == $tc->id ? 'selected' : '' }}>
                                    {{ $tc->name }}
                                    @if ($tc->is_online ?? false)
                                        (Online)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <button class="btn btn-sm btn-primary w-100">
                            <span class="material-icons me-1" style="font-size:15px;">person_add</span>
                            Assign
                        </button>
                    </form>
                </div>

                {{-- Set Follow-Up --}}
                <div class="chart-card mb-4">
                    <div class="chart-header mb-3">
                        <h3>Schedule Follow-Up</h3>
                    </div>
                    <form action="{{ route('manager.campaigns.contact.followup', [encrypt($campaign->id), encrypt($contact->id)]) }}"
                        method="POST">
                        @csrf
                        <div class="row g-2 mb-2">
                            <div class="col-7">
                                <label class="form-label small fw-semibold mb-1">Date</label>
                                <input type="date" name="followup_date" class="form-control form-control-sm"
                                    value="{{ $contact->next_followup?->format('Y-m-d') }}"
                                    min="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="col-5">
                                <label class="form-label small fw-semibold mb-1">Time</label>
                                <input type="time" name="followup_time" class="form-control form-control-sm"
                                    value="{{ $contact->followup_time }}">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1">Update Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">— Keep current —</option>
                                @foreach (['callback','interested','not_interested','no_answer','called'] as $s)
                                    <option value="{{ $s }}" {{ $contact->status === $s ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $s)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1">Notes (optional)</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2"
                                placeholder="Add follow-up notes..."></textarea>
                        </div>
                        <button class="btn btn-sm btn-primary w-100">
                            <span class="material-icons me-1" style="font-size:15px;">event</span>
                            Save Follow-up
                        </button>
                    </form>
                </div>
            </div>

            {{-- RIGHT: Actions + WA Chat + Notes + Timeline --}}
            <div class="col-lg-8">

                {{-- Action Bar --}}
                <div class="action-bar mb-4">
                    <button type="button" class="btn btn-primary call-btn"
                        data-phone="{{ $contact->phone }}"
                        data-provider="{{ $provider }}"
                        data-contact-id="{{ encrypt($contact->id) }}"
                        data-campaign-id="{{ encrypt($campaign->id) }}">
                        <span class="material-icons">call</span>
                        <span class="call-text">Call Now</span>
                    </button>

                    <button class="btn btn-success" type="button" id="openWhatsappChat">
                        <span class="material-icons">chat</span>
                        WhatsApp
                    </button>

                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#statusModal">
                        <span class="material-icons">sync_alt</span>
                        Change Status
                    </button>
                </div>

                {{-- WhatsApp Chat Window --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-0">
                        <div class="wa-chat-window">
                            <div class="wa-chat-header">
                                <div class="wa-user-block">
                                    <div class="wa-avatar">{{ strtoupper(substr($contact->name, 0, 1)) }}</div>
                                    <div>
                                        <h6 class="mb-0">{{ $contact->name }}</h6>
                                        <small>Meta WhatsApp</small>
                                    </div>
                                </div>
                                <span class="wa-live-dot"></span>
                            </div>

                            <div id="waChatBody" class="wa-chat-body">
                                @forelse ($contactMessages as $msg)
                                    @php
                                        $outgoing = $msg->direction !== 'inbound';
                                        $st       = data_get($msg->meta_data, 'meta_status', 'sent');
                                    @endphp
                                    <div class="wa-message {{ $outgoing ? 'wa-outgoing' : 'wa-incoming' }}"
                                         data-msg-id="{{ $msg->id }}">
                                        @if ($msg->media_type && $msg->media_url)
                                            @php $mUrl = asset('storage/' . $msg->media_url); @endphp
                                            @if ($msg->media_type === 'image')
                                                <img src="{{ $mUrl }}" style="max-width:200px;max-height:160px;border-radius:6px;display:block;margin-bottom:4px;cursor:pointer;"
                                                     onclick="window.open(this.src,'_blank')" alt="Image">
                                            @elseif ($msg->media_type === 'audio')
                                                <audio controls style="width:100%;min-width:180px;margin-bottom:4px;"><source src="{{ $mUrl }}"></audio>
                                            @elseif ($msg->media_type === 'video')
                                                <video controls style="max-width:200px;max-height:160px;border-radius:6px;display:block;margin-bottom:4px;"><source src="{{ $mUrl }}"></video>
                                            @else
                                                <a href="{{ $mUrl }}" target="_blank" download
                                                   style="display:flex;align-items:center;gap:6px;background:rgba(0,0,0,.07);border-radius:6px;padding:6px 10px;margin-bottom:4px;text-decoration:none;color:inherit;font-size:12px;font-weight:600;">
                                                    <span class="material-icons" style="font-size:18px;color:#137fec;">description</span>
                                                    {{ $msg->media_filename ?? basename($msg->media_url) }}
                                                </a>
                                            @endif
                                        @endif
                                        @if ($msg->message_body && !($msg->media_type && in_array($msg->media_type, ['image','audio','video'])))
                                            <p class="mb-1">{{ $msg->message_body }}</p>
                                        @endif
                                        <div class="wa-message-meta">
                                            <small>{{ $msg->created_at?->format('h:i A') }}</small>
                                            @if ($outgoing)
                                                <span class="wa-tick {{ $st === 'read' ? 'wa-tick-read' : ($st === 'delivered' ? 'wa-tick-delivered' : 'wa-tick-sent') }}">{{ in_array($st, ['delivered','read']) ? '✓✓' : '✓' }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div id="waEmptyPlaceholder" class="wa-message wa-incoming">
                                        <p class="mb-1">No WhatsApp messages yet for this contact.</p>
                                        <small>Start the conversation below.</small>
                                    </div>
                                @endforelse
                            </div>

                            <div class="wa-chat-footer">
                                <div class="wa-template-row">
                                    <button type="button" class="wa-template-btn"
                                        data-msg="Hello {{ $contact->name }}, thanks for your interest. Can we connect now?">
                                        Intro
                                    </button>
                                    <button type="button" class="wa-template-btn"
                                        data-msg="Reminder: your follow-up is scheduled. Please confirm your preferred time.">
                                        Follow-up
                                    </button>
                                    <button type="button" class="wa-template-btn"
                                        data-msg="Please share your preferred course and we will guide you with next steps.">
                                        Course Info
                                    </button>
                                </div>

                                {{-- File preview --}}
                                <div id="waLeadFilePreview" style="display:none;align-items:center;gap:8px;background:#f0f9ff;border:1.5px solid #bae6fd;border-radius:8px;padding:6px 10px;margin-bottom:6px;font-size:12px;">
                                    <span class="material-icons" id="waLeadFileIcon" style="color:#137fec;font-size:18px;">attach_file</span>
                                    <span id="waLeadFileName" style="flex:1;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                                    <span id="waLeadFileSize" style="color:#64748b;white-space:nowrap;"></span>
                                    <button type="button" id="waLeadFileRemove" style="background:none;border:none;cursor:pointer;color:#ef4444;padding:0;display:flex;">
                                        <span class="material-icons" style="font-size:16px;">close</span>
                                    </button>
                                </div>
                                <form id="waComposerForm" class="wa-composer-form">
                                    @csrf
                                    <input type="file" id="waLeadFileInput" style="display:none;"
                                           accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip">
                                    <button type="button" id="waLeadAttachBtn"
                                            style="background:#f1f5f9;border:1.5px solid #e2e8f0;border-radius:50%;width:38px;height:38px;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;"
                                            title="Attach file">
                                        <span class="material-icons" style="font-size:18px;color:#64748b;">attach_file</span>
                                    </button>
                                    <input type="text" id="waMessageInput" class="form-control"
                                        placeholder="Type a WhatsApp message..." autocomplete="off">
                                    <button type="submit" class="btn btn-success" id="waSendBtn">
                                        <span class="material-icons" id="waSendIcon">send</span>
                                        <span class="spinner-border spinner-border-sm d-none" id="waSpinner"></span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Add Note --}}
                <div class="note-section mb-4">
                    <form action="{{ route('manager.campaigns.contact.note', [encrypt($campaign->id), encrypt($contact->id)]) }}"
                        method="POST">
                        @csrf
                        <textarea name="note" class="form-control" rows="2"
                            placeholder="Write a note about this contact..." required></textarea>
                        <div class="d-flex justify-content-end mt-3">
                            <button class="btn btn-dark">Add Note</button>
                        </div>
                    </form>
                </div>

                {{-- Activity Timeline --}}
                <div class="timeline-card">
                    <div class="timeline-header">
                        <h2>Activity Timeline</h2>
                        <div class="timeline-filters">
                            <button class="filter-btn active" onclick="filterTimeline('all', event)">All</button>
                            <button class="filter-btn" onclick="filterTimeline('call', event)">Calls</button>
                            <button class="filter-btn" onclick="filterTimeline('whatsapp', event)">WhatsApp</button>
                            <button class="filter-btn" onclick="filterTimeline('note', event)">Notes</button>
                        </div>
                    </div>

                    <div class="timeline-content">
                        @forelse ($activities as $activity)
                            <div class="timeline-item" data-type="{{ $activity->type }}">
                                <div class="timeline-icon">
                                    <span class="material-icons">
                                        @switch($activity->type)
                                            @case('call') call @break
                                            @case('whatsapp') chat @break
                                            @case('note') description @break
                                            @case('status_change') sync_alt @break
                                            @case('followup_set') event @break
                                            @default info @break
                                        @endswitch
                                    </span>
                                </div>
                                <div class="timeline-body">
                                    <p>{{ $activity->description }}</p>
                                    @if ($activity->type === 'call' && $activity->meta)
                                        @php $meta = $activity->meta; @endphp
                                        <div class="d-flex flex-wrap gap-1 mb-1">
                                            @if (!empty($meta['outcome']))
                                                <span class="badge bg-light text-dark border">Outcome: {{ ucfirst($meta['outcome']) }}</span>
                                            @endif
                                            @if (!empty($meta['duration']))
                                                <span class="badge bg-light text-dark border">Duration: {{ $meta['duration'] }}s</span>
                                            @endif
                                        </div>
                                    @endif
                                    <small>
                                        {{ $activity->createdBy->name ?? '-' }}
                                        |
                                        {{ $activity->created_at->diffForHumans() }}
                                    </small>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-5">
                                <span class="material-icons" style="font-size:40px; color:#cbd5e1;">timeline</span>
                                <p class="text-muted mt-2">No activity recorded yet.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Status Change Modal --}}
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('manager.campaigns.contact.status', [encrypt($campaign->id), encrypt($contact->id)]) }}">
                @csrf @method('PATCH')
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Contact Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Select Status</label>
                            <select class="form-select" name="status" id="statusSelect">
                                @foreach (['pending','called','interested','not_interested','no_answer','callback','converted'] as $s)
                                    <option value="{{ $s }}" {{ $contact->status === $s ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $s)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="statusConfirmBtn">Update Status</button>
                        <button type="submit" class="btn btn-warning d-none" id="statusFinalSubmit">Yes, Confirm</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Call Outcome Modal --}}
    <div class="modal fade" id="callOutcomeModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">How did the call go?</h5>
                </div>
                <div class="modal-body pt-2">
                    <p class="text-muted small mb-3">Select the outcome to log it in the activity timeline.</p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-success outcome-btn" data-outcome="interested">Interested</button>
                        <button class="btn btn-danger outcome-btn" data-outcome="not_interested">Not Interested</button>
                        <button class="btn btn-warning text-dark outcome-btn" data-outcome="callback">Call Back Later</button>
                        <button class="btn btn-secondary outcome-btn" data-outcome="no_answer">Switched Off / No Answer</button>
                        <button class="btn btn-outline-secondary outcome-btn" data-outcome="called">Other / Just Called</button>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-link text-muted btn-sm" data-bs-dismiss="modal">Skip</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ── Timeline filter ─────────────────────────────────────────────────────
        function filterTimeline(type, event) {
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            document.querySelectorAll('.timeline-item').forEach(item => {
                item.style.display = type === 'all' || item.dataset.type === type ? 'flex' : 'none';
            });
        }

        // ── Call button — use global GC call bar ────────────────────────────────
        window.addEventListener('load', function () {
            GC.initDevice();
        });

        document.addEventListener('click', async function (e) {
            var btn = e.target.closest('.call-btn');
            if (!btn) return;

            if (GC.isActive()) {
                GC.endCall();
                return;
            }

            btn.disabled = true;
            btn.querySelector('.call-text').textContent = 'Connecting...';

            try {
                await GC.startCall(btn.dataset.phone, null);
            } catch (err) {
                btn.disabled = false;
                btn.querySelector('.call-text').textContent = 'Call Now';
            }
        });

        document.addEventListener('gc:callAccepted', function () {
            var btn = document.querySelector('.call-btn');
            if (!btn) return;
            btn.disabled = false;
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-danger', 'active-call');
            btn.querySelector('.call-text').textContent = 'End Call';
        });

        document.addEventListener('gc:callEnded', function () {
            var btn = document.querySelector('.call-btn');
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('btn-danger', 'active-call');
                btn.classList.add('btn-primary');
                btn.querySelector('.call-text').textContent = 'Call Now';
            }
            var modal = new bootstrap.Modal(document.getElementById('callOutcomeModal'));
            modal.show();
        });

        // ── Call outcome — log to campaign activity ─────────────────────────────
        (function () {
            const logUrl = '{{ route('manager.campaigns.contact.call', [encrypt($campaign->id), encrypt($contact->id)]) }}';
            const csrf   = document.querySelector('meta[name="csrf-token"]')?.content || '';

            document.querySelectorAll('.outcome-btn').forEach(function (btn) {
                btn.addEventListener('click', async function () {
                    const outcome = this.dataset.outcome;
                    try {
                        await fetch(logUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                            body: JSON.stringify({ outcome: outcome }),
                        });
                    } catch (e) { /* silent */ }

                    bootstrap.Modal.getInstance(document.getElementById('callOutcomeModal'))?.hide();
                    location.reload();
                });
            });
        })();

        // ── Status modal confirmation flow ──────────────────────────────────────
        (function () {
            var statusSelect  = document.getElementById('statusSelect');
            var confirmBtn    = document.getElementById('statusConfirmBtn');
            var finalBtn      = document.getElementById('statusFinalSubmit');
            var currentStatus = @json(ucfirst(str_replace('_', ' ', $contact->status)));

            confirmBtn?.addEventListener('click', function () {
                var selected = statusSelect?.options[statusSelect.selectedIndex]?.text || '';
                confirmBtn.textContent = 'Changing: ' + currentStatus + ' → ' + selected;
                confirmBtn.classList.add('d-none');
                finalBtn?.classList.remove('d-none');
            });

            document.getElementById('statusModal')?.addEventListener('hidden.bs.modal', function () {
                confirmBtn?.classList.remove('d-none');
                confirmBtn && (confirmBtn.textContent = 'Update Status');
                finalBtn?.classList.add('d-none');
            });
        })();
    </script>

    <script>
        (function () {
            const chatBody    = document.getElementById('waChatBody');
            const msgInput    = document.getElementById('waMessageInput');
            const form        = document.getElementById('waComposerForm');
            const sendBtn     = document.getElementById('waSendBtn');
            const sendIcon    = document.getElementById('waSendIcon');
            const spinner     = document.getElementById('waSpinner');
            const openChatBtn = document.getElementById('openWhatsappChat');

            if (!chatBody || !msgInput || !form) return;

            const SAVE_URL  = @json(route('manager.campaigns.contact.whatsapp.store', [encrypt($campaign->id), encrypt($contact->id)]));
            const MEDIA_URL = @json(route('manager.campaigns.contact.whatsapp.media', [encrypt($campaign->id), encrypt($contact->id)]));
            const FETCH_URL = @json(route('manager.campaigns.contact.whatsapp.fetch', [encrypt($campaign->id), encrypt($contact->id)]));
            const CSRF      = document.querySelector('meta[name="csrf-token"]')?.content || '';

            const fileInput     = document.getElementById('waLeadFileInput');
            const attachBtn     = document.getElementById('waLeadAttachBtn');
            const filePreviewEl = document.getElementById('waLeadFilePreview');
            const fileNameEl    = document.getElementById('waLeadFileName');
            const fileSizeEl    = document.getElementById('waLeadFileSize');
            const fileIconEl    = document.getElementById('waLeadFileIcon');
            const fileRemoveBtn = document.getElementById('waLeadFileRemove');
            let pendingFile = null;

            attachBtn?.addEventListener('click', () => fileInput.click());
            fileInput?.addEventListener('change', function () {
                const f = this.files[0];
                if (!f) return;
                pendingFile = f;
                fileNameEl.textContent = f.name;
                fileSizeEl.textContent = f.size < 1048576 ? (f.size/1024).toFixed(1)+' KB' : (f.size/1048576).toFixed(1)+' MB';
                fileIconEl.textContent = f.type.startsWith('image/') ? 'image' : f.type.startsWith('video/') ? 'videocam' : f.type.startsWith('audio/') ? 'headphones' : 'description';
                filePreviewEl.style.display = 'flex';
                msgInput.placeholder = 'Add a caption (optional)…';
                msgInput.removeAttribute('required');
            });
            fileRemoveBtn?.addEventListener('click', clearFile);

            function clearFile() {
                pendingFile = null;
                if (fileInput) fileInput.value = '';
                if (filePreviewEl) filePreviewEl.style.display = 'none';
                msgInput.placeholder = 'Type a WhatsApp message...';
            }

            let lastMsgId = 0;
            chatBody.querySelectorAll('[data-msg-id]').forEach(el => {
                const id = parseInt(el.dataset.msgId) || 0;
                if (id > lastMsgId) lastMsgId = id;
            });

            chatBody.scrollTop = chatBody.scrollHeight;

            openChatBtn?.addEventListener('click', function () {
                chatBody.scrollIntoView({ behavior: 'smooth', block: 'start' });
                msgInput.focus();
            });

            document.querySelectorAll('.wa-template-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    msgInput.value = btn.dataset.msg || '';
                    msgInput.focus();
                });
            });

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                if (pendingFile) { await sendMedia(); return; }

                const text = msgInput.value.trim();
                if (!text) return;

                setSending(true);
                try {
                    const res  = await fetch(SAVE_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                        body: JSON.stringify({ message: text }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) { showError(data.message || 'Send failed'); return; }

                    msgInput.value = '';
                    document.getElementById('waEmptyPlaceholder')?.remove();
                    appendBubble({ id: data.message_id, body: data.message || text, direction: 'outbound', time: data.time || now(), status: 'sent' });
                    lastMsgId = data.message_id || lastMsgId;
                } catch (err) {
                    showError(err.message || 'Network error.');
                } finally {
                    setSending(false);
                }
            });

            async function sendMedia() {
                if (!pendingFile) return;
                setSending(true);
                try {
                    const fd = new FormData();
                    fd.append('_token', CSRF);
                    fd.append('file', pendingFile);
                    const caption = msgInput.value.trim();
                    if (caption) fd.append('caption', caption);

                    const res  = await fetch(MEDIA_URL, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) { showError(data.message || 'Upload failed'); return; }

                    clearFile();
                    msgInput.value = '';
                    document.getElementById('waEmptyPlaceholder')?.remove();
                    appendBubble({
                        id: data.message_id, body: data.message, direction: 'outbound',
                        time: data.time || now(), status: 'sent',
                        media_type: data.media_type, media_url: data.media_url, media_filename: data.media_filename,
                    });
                    lastMsgId = data.message_id || lastMsgId;
                } catch (err) {
                    showError(err.message || 'Upload failed.');
                } finally {
                    setSending(false);
                }
            }

            const pollTimer = setInterval(poll, 7000);
            document.addEventListener('visibilitychange', () => { if (document.hidden) clearInterval(pollTimer); });

            async function poll() {
                try {
                    const res  = await fetch(FETCH_URL + '?after=' + lastMsgId, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (data.messages?.length > 0) {
                        document.getElementById('waEmptyPlaceholder')?.remove();
                        data.messages.forEach(m => {
                            if (m.id > lastMsgId) {
                                appendBubble({ id: m.id, body: m.body, direction: m.direction, time: m.time, status: m.status || 'sent',
                                               media_type: m.media_type, media_url: m.media_url, media_filename: m.media_filename });
                                lastMsgId = m.id;
                            }
                        });
                    }
                    if (data.statuses) {
                        Object.entries(data.statuses).forEach(([id, status]) => {
                            const tick = chatBody.querySelector('[data-msg-id="' + id + '"] .wa-tick');
                            if (tick) tick.className = 'wa-tick ' + (status === 'read' ? 'wa-tick-read' : status === 'delivered' ? 'wa-tick-delivered' : 'wa-tick-sent');
                        });
                    }
                } catch (_) {}
            }

            function appendBubble({ id, body, direction, time, status, media_type, media_url, media_filename }) {
                const outgoing = direction !== 'inbound';
                const el = document.createElement('div');
                el.className = 'wa-message ' + (outgoing ? 'wa-outgoing' : 'wa-incoming');
                el.dataset.msgId = id || '';

                if (media_type && media_url) {
                    if (media_type === 'image') {
                        const img = document.createElement('img');
                        img.src = media_url;
                        img.style.cssText = 'max-width:200px;max-height:160px;border-radius:6px;display:block;margin-bottom:4px;cursor:pointer;';
                        img.onclick = () => window.open(img.src, '_blank');
                        el.appendChild(img);
                    } else if (media_type === 'audio') {
                        const audio = document.createElement('audio');
                        audio.controls = true;
                        audio.style.cssText = 'width:100%;min-width:180px;margin-bottom:4px;';
                        audio.innerHTML = `<source src="${media_url}">`;
                        el.appendChild(audio);
                    } else if (media_type === 'video') {
                        const video = document.createElement('video');
                        video.controls = true;
                        video.style.cssText = 'max-width:200px;max-height:160px;border-radius:6px;display:block;margin-bottom:4px;';
                        video.innerHTML = `<source src="${media_url}">`;
                        el.appendChild(video);
                    } else {
                        const a = document.createElement('a');
                        a.href = media_url; a.target = '_blank'; a.download = true;
                        a.style.cssText = 'display:flex;align-items:center;gap:6px;background:rgba(0,0,0,.07);border-radius:6px;padding:6px 10px;margin-bottom:4px;text-decoration:none;color:inherit;font-size:12px;font-weight:600;';
                        a.innerHTML = `<span class="material-icons" style="font-size:18px;color:#137fec;">description</span>${escHtml(media_filename || 'File')}`;
                        el.appendChild(a);
                    }
                }

                const showText = body && !['image','audio','video'].includes(media_type || '');
                if (showText) {
                    const p = document.createElement('p');
                    p.className = 'mb-1';
                    p.textContent = body;
                    el.appendChild(p);
                }

                const tickHtml = outgoing
                    ? `<span class="wa-tick ${status === 'read' ? 'wa-tick-read' : status === 'delivered' ? 'wa-tick-delivered' : 'wa-tick-sent'}">${(status === 'delivered' || status === 'read') ? '✓✓' : '✓'}</span>`
                    : '';
                const meta = document.createElement('div');
                meta.className = 'wa-message-meta';
                meta.innerHTML = `<small>${time || now()}</small>${tickHtml}`;
                el.appendChild(meta);

                chatBody.appendChild(el);
                chatBody.scrollTop = chatBody.scrollHeight;
            }

            function setSending(v) {
                sendBtn.disabled = v;
                sendIcon.classList.toggle('d-none', v);
                spinner.classList.toggle('d-none', !v);
            }

            function showError(msg) {
                const div = document.createElement('div');
                div.className = 'alert alert-danger alert-dismissible mx-2 my-1 py-2 small';
                div.innerHTML = '<strong>Send failed:</strong> ' + escHtml(msg) +
                    ' <button type="button" class="btn-close" style="font-size:11px;" onclick="this.parentElement.remove()"></button>';
                chatBody.appendChild(div);
                chatBody.scrollTop = chatBody.scrollHeight;
            }

            function now() { return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
            function escHtml(str) { return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
        })();
    </script>
@endsection
