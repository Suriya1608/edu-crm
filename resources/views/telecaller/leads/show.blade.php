@extends('layouts.app')

@section('content')
    @include('layouts.whatsappchat')

    {{-- ── Page header ─────────────────────────────────────────────────── --}}
    <div class="sl-page-header mb-4">
        <a href="{{ route('telecaller.leads') }}" class="sl-back-btn">
            <span class="material-icons">arrow_back</span>
        </a>
        <div>
            <h2 class="sl-page-title">Lead Profile</h2>
            <p class="sl-page-sub">{{ $lead->lead_code }} &bull; Complete details &amp; activity</p>
        </div>
        <div class="ms-auto d-flex align-items-center gap-2">
            @php $stCls = str_replace('_','-',$lead->status); @endphp
            <span class="lead-status status-{{ $stCls }}">{{ ucfirst(str_replace('_',' ',$lead->status)) }}</span>
            <x-aging-badge :days="$lead->days_aged" />
        </div>
    </div>

    <div class="row g-4">

        {{-- ── Left Column: Profile Card ──────────────────────────────── --}}
        <div class="col-lg-4">

            {{-- Profile card --}}
            <div class="sl-profile-card mb-3">
                <div class="sl-profile-banner">
                    <div class="sl-profile-avatar">
                        {{ strtoupper(substr($lead->name, 0, 2)) }}
                    </div>
                </div>
                <div class="sl-profile-body">
                    <h3 class="sl-profile-name">{{ $lead->name }}</h3>
                    <p class="sl-profile-code">{{ $lead->lead_code }}</p>

                    <div class="sl-info-grid">
                        <div class="sl-info-item">
                            <div class="sl-info-icon" style="background:#eef2ff;color:#6366f1;">
                                <span class="material-icons">phone</span>
                            </div>
                            <div>
                                <div class="sl-info-label">Phone</div>
                                <div class="sl-info-val">{{ $lead->phone }}</div>
                            </div>
                        </div>
                        <div class="sl-info-item">
                            <div class="sl-info-icon" style="background:#f0fdf4;color:#10b981;">
                                <span class="material-icons">mail</span>
                            </div>
                            <div>
                                <div class="sl-info-label">Email</div>
                                <div class="sl-info-val" style="font-size:12.5px;">{{ $lead->email ?? '—' }}</div>
                            </div>
                        </div>
                        <div class="sl-info-item">
                            <div class="sl-info-icon" style="background:#fef3c7;color:#d97706;">
                                <span class="material-icons">school</span>
                            </div>
                            <div>
                                <div class="sl-info-label">Course Applied</div>
                                <div class="sl-info-val">{{ $lead->course ?? '—' }}</div>
                            </div>
                        </div>
                        <div class="sl-info-item">
                            <div class="sl-info-icon" style="background:#fdf4ff;color:#9333ea;">
                                <span class="material-icons">person</span>
                            </div>
                            <div>
                                <div class="sl-info-label">Assigned By</div>
                                <div class="sl-info-val text-success fw-semibold">{{ $lead->assignedBy->name ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Action buttons panel --}}
            <div class="sl-actions-panel">
                <button type="button" class="sl-action-btn sl-action-call call-btn"
                    data-phone="{{ $lead->phone }}"
                    data-provider="{{ $provider }}"
                    data-lead="{{ $lead->id }}">
                    <span class="material-icons">call</span>
                    <span class="call-text">Call Now</span>
                </button>
                <button class="sl-action-btn sl-action-wa" type="button" id="openWhatsappChat">
                    <span class="material-icons">chat</span>
                    WhatsApp
                </button>
                <button class="sl-action-btn sl-action-status" data-bs-toggle="modal" data-bs-target="#statusModal">
                    <span class="material-icons">sync_alt</span>
                    Status
                </button>
            </div>
        </div>

        {{-- ── Right Column: Chat + Activity ──────────────────────────── --}}
        <div class="col-lg-8">

            {{-- WhatsApp Chat Window --}}
            <div class="sl-section-card mb-4" id="whatsappSection">
                <div class="sl-section-head">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:34px;height:34px;border-radius:10px;background:#dcfce7;display:flex;align-items:center;justify-content:center;">
                            <span class="material-icons" style="font-size:18px;color:#16a34a;">chat</span>
                        </div>
                        <div>
                            <div style="font-size:14px;font-weight:700;color:var(--text-dark);">WhatsApp Chat</div>
                            <div style="font-size:11.5px;color:var(--text-muted);">Meta Official API</div>
                        </div>
                    </div>
                    <span class="wa-live-dot" style="margin-left:auto;"></span>
                </div>

                <div class="wa-chat-window" style="border:none;border-radius:0;box-shadow:none;">
                    <div id="waChatBody" class="wa-chat-body">
                        @forelse ($whatsappMessages as $msg)
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
                                <p class="mb-1">No WhatsApp messages yet for this lead.</p>
                                <small>Start the conversation below</small>
                            </div>
                        @endforelse
                    </div>

                    <div class="wa-chat-footer">
                        <div class="wa-template-row">
                            <button type="button" class="wa-template-btn wa-tpl-direct-btn"
                                data-template="{{ $waTemplateName }}"
                                data-params="{{ json_encode([$lead->name]) }}"
                                data-display="Hello {{ $lead->name }}, thank you for your interest in our programs!">
                                ✅ Welcome
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
                                placeholder="Type a WhatsApp message…" autocomplete="off">
                            <button type="submit" class="btn btn-success" id="waSendBtn">
                                <span class="material-icons" id="waSendIcon">send</span>
                                <span class="spinner-border spinner-border-sm d-none" id="waSpinner"></span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Add Note --}}
            <div class="sl-section-card mb-4">
                <div class="sl-section-head">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:34px;height:34px;border-radius:10px;background:#fef3c7;display:flex;align-items:center;justify-content:center;">
                            <span class="material-icons" style="font-size:18px;color:#d97706;">edit_note</span>
                        </div>
                        <div style="font-size:14px;font-weight:700;color:var(--text-dark);">Add Note</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('telecaller.leads.addNote', encrypt($lead->id)) }}">
                    @csrf
                    <textarea name="note" class="form-control sl-note-input" rows="2"
                        placeholder="Write a note about this lead…" required></textarea>
                    <div class="d-flex justify-content-end mt-2">
                        <button class="btn btn-dark btn-sm px-4" style="border-radius:8px;font-size:13px;">
                            <span class="material-icons" style="font-size:14px;vertical-align:middle;">add</span>
                            Add Note
                        </button>
                    </div>
                </form>
            </div>

            {{-- Activity Timeline --}}
            <div class="sl-section-card">
                <div class="sl-section-head">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:34px;height:34px;border-radius:10px;background:#eef2ff;display:flex;align-items:center;justify-content:center;">
                            <span class="material-icons" style="font-size:18px;color:#6366f1;">timeline</span>
                        </div>
                        <div style="font-size:14px;font-weight:700;color:var(--text-dark);">Activity Timeline</div>
                    </div>
                    <div class="sl-timeline-filters ms-auto">
                        <button class="sl-filter-btn active" onclick="filterTimeline('all',event)">All</button>
                        <button class="sl-filter-btn" onclick="filterTimeline('call',event)">
                            <span class="material-icons" style="font-size:12px;vertical-align:middle;">call</span> Calls
                        </button>
                        <button class="sl-filter-btn" onclick="filterTimeline('whatsapp',event)">
                            <span class="material-icons" style="font-size:12px;vertical-align:middle;">chat</span> WhatsApp
                        </button>
                        <button class="sl-filter-btn" onclick="filterTimeline('note',event)">
                            <span class="material-icons" style="font-size:12px;vertical-align:middle;">description</span> Notes
                        </button>
                    </div>
                </div>

                <div class="sl-timeline" id="slTimeline">
                    @forelse($lead->activities()->latest()->get() as $activity)
                        @php
                            $typeConfig = [
                                'call'          => ['icon' => 'call',          'bg' => '#eef2ff', 'color' => '#6366f1'],
                                'note'          => ['icon' => 'description',   'bg' => '#fef3c7', 'color' => '#d97706'],
                                'whatsapp'      => ['icon' => 'chat',          'bg' => '#dcfce7', 'color' => '#16a34a'],
                                'status_change' => ['icon' => 'sync_alt',      'bg' => '#f0fdf4', 'color' => '#10b981'],
                                'followup'      => ['icon' => 'event',         'bg' => '#fdf4ff', 'color' => '#9333ea'],
                                'email'         => ['icon' => 'email',         'bg' => '#eff6ff', 'color' => '#3b82f6'],
                            ];
                            $tc = $typeConfig[$activity->type] ?? ['icon' => 'info', 'bg' => '#f1f5f9', 'color' => '#64748b'];
                        @endphp
                        <div class="sl-timeline-item" data-type="{{ $activity->type }}">
                            <div class="sl-timeline-icon" style="background:{{ $tc['bg'] }};color:{{ $tc['color'] }};">
                                <span class="material-icons">{{ $tc['icon'] }}</span>
                            </div>
                            <div class="sl-timeline-body">
                                <div class="sl-timeline-desc">{{ $activity->description }}</div>
                                <div class="sl-timeline-meta">
                                    <span class="material-icons" style="font-size:11px;">person</span>
                                    {{ $activity->user->name ?? '—' }}
                                    <span style="opacity:.35;margin:0 2px;">•</span>
                                    <span class="material-icons" style="font-size:11px;">schedule</span>
                                    {{ $activity->created_at->diffForHumans() }}
                                </div>
                            </div>
                            <div class="sl-timeline-type-badge" style="background:{{ $tc['bg'] }};color:{{ $tc['color'] }};">
                                {{ ucfirst(str_replace('_', ' ', $activity->type)) }}
                            </div>
                        </div>
                    @empty
                        <div class="sl-timeline-empty">
                            <span class="material-icons" style="font-size:40px;opacity:.25;display:block;margin-bottom:8px;">history</span>
                            No activity yet for this lead.
                        </div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>

    {{-- Status Change Modal --}}
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('telecaller.leads.changeStatus', encrypt($lead->id)) }}">
                @csrf
                <div class="modal-content" style="border-radius:16px;border:none;">
                    <div class="modal-header" style="border-bottom:1px solid var(--border-color);padding:20px 24px 16px;">
                        <h5 class="modal-title fw-bold">Update Lead Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="padding:20px 24px;">
                        <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size:13px;">Select New Status</label>
                            <select class="form-select" name="status" id="statusSelect" style="border-radius:10px;font-size:13px;">
                                <option value="contacted">Contacted</option>
                                <option value="interested">Interested</option>
                                <option value="follow_up">Follow-up Required</option>
                                <option value="not_interested">Not Interested</option>
                            </select>
                        </div>
                        <div id="followupFields" style="display:none;">
                            <div class="row g-2 mb-3">
                                <div class="col-7">
                                    <label class="form-label fw-semibold" style="font-size:13px;">Follow-up Date</label>
                                    <input type="date" name="next_followup" class="form-control" style="border-radius:10px;" min="{{ now()->toDateString() }}">
                                </div>
                                <div class="col-5">
                                    <label class="form-label fw-semibold" style="font-size:13px;">Time</label>
                                    <input type="time" name="followup_time" class="form-control" style="border-radius:10px;">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold" style="font-size:13px;">Remarks</label>
                                <textarea name="remarks" class="form-control" rows="2" style="border-radius:10px;"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top:1px solid var(--border-color);padding:16px 24px;">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px;">Cancel</button>
                        <button type="button" class="btn btn-primary" id="statusConfirmBtn" style="border-radius:8px;">Update Status</button>
                        <button type="submit" class="btn btn-warning d-none" id="statusFinalSubmit" style="border-radius:8px;">Yes, Confirm</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Call Outcome Modal --}}
    <div class="modal fade" id="callOutcomeModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:16px;border:none;">
                <div class="modal-header border-0 pb-0" style="padding:20px 24px 12px;">
                    <h5 class="modal-title fw-bold">How did the call go?</h5>
                </div>
                <div class="modal-body" style="padding:8px 24px 20px;">
                    <p class="text-muted small mb-3">Select the outcome to log it against this lead.</p>
                    <input type="hidden" id="outcomeCallLogId">
                    <div class="d-grid gap-2">
                        <button class="btn btn-success outcome-btn" style="border-radius:10px;" data-outcome="interested">
                            <span class="material-icons" style="font-size:16px;vertical-align:middle;">thumb_up</span> Interested
                        </button>
                        <button class="btn btn-danger outcome-btn" style="border-radius:10px;" data-outcome="not_interested">
                            <span class="material-icons" style="font-size:16px;vertical-align:middle;">thumb_down</span> Not Interested
                        </button>
                        <button class="btn btn-warning text-dark outcome-btn" style="border-radius:10px;" data-outcome="call_back_later">
                            <span class="material-icons" style="font-size:16px;vertical-align:middle;">schedule</span> Call Back Later
                        </button>
                        <button class="btn btn-secondary outcome-btn" style="border-radius:10px;" data-outcome="switched_off">
                            <span class="material-icons" style="font-size:16px;vertical-align:middle;">phone_disabled</span> Switched Off / No Answer
                        </button>
                        <button class="btn btn-outline-secondary outcome-btn" style="border-radius:10px;" data-outcome="wrong_number">
                            <span class="material-icons" style="font-size:16px;vertical-align:middle;">cancel</span> Wrong Number
                        </button>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0" style="padding:0 24px 16px;">
                    <button type="button" class="btn btn-link text-muted btn-sm" data-bs-dismiss="modal">Skip</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function filterTimeline(type, event) {
            document.querySelectorAll('.sl-filter-btn').forEach(b => b.classList.remove('active'));
            event.target.closest('.sl-filter-btn').classList.add('active');
            document.querySelectorAll('.sl-timeline-item').forEach(item => {
                item.style.display = type === 'all' || item.dataset.type === type ? 'flex' : 'none';
            });
        }
    </script>

    <script>
        window.addEventListener('load', function () { GC.initDevice(); });

        document.addEventListener('click', async function (e) {
            var btn = e.target.closest('.call-btn');
            if (!btn) return;
            if (GC.isActive()) { GC.endCall(); return; }
            btn.disabled = true;
            btn.querySelector('.call-text').textContent = 'Connecting…';
            try {
                await GC.startCall(btn.dataset.phone, btn.dataset.lead);
            } catch (err) {
                btn.disabled = false;
                btn.querySelector('.call-text').textContent = 'Call Now';
            }
        });

        document.addEventListener('gc:callAccepted', function () {
            var btn = document.querySelector('.call-btn');
            if (!btn) return;
            btn.disabled = false;
            btn.classList.remove('sl-action-call');
            btn.classList.add('sl-action-end');
            btn.querySelector('.call-text').textContent = 'End Call';
        });

        document.addEventListener('gc:callEnded', function (e) {
            var btn = document.querySelector('.call-btn');
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('sl-action-end');
                btn.classList.add('sl-action-call');
                btn.querySelector('.call-text').textContent = 'Call Now';
            }
            var callLogId = e.detail && e.detail.callLogId;
            if (callLogId) {
                document.getElementById('outcomeCallLogId').value = callLogId;
                new bootstrap.Modal(document.getElementById('callOutcomeModal')).show();
            }
        });
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

            const SAVE_URL  = @json(route('telecaller.leads.whatsapp.store', encrypt($lead->id)));
            const MEDIA_URL = @json(route('telecaller.leads.whatsapp.media', encrypt($lead->id)));
            const FETCH_URL = @json(route('telecaller.leads.whatsapp.fetch', encrypt($lead->id)));
            const CSRF      = document.querySelector('meta[name="csrf-token"]')?.content || '';

            let _waAudioCtx = null;
            function playWaChime() {
                try {
                    if (!_waAudioCtx) _waAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
                    [[1100, 0], [880, 0.18]].forEach(function(pair) {
                        const osc = _waAudioCtx.createOscillator(), gain = _waAudioCtx.createGain();
                        osc.connect(gain); gain.connect(_waAudioCtx.destination);
                        osc.type = 'sine'; osc.frequency.value = pair[0];
                        const t = _waAudioCtx.currentTime + pair[1];
                        gain.gain.setValueAtTime(0, t);
                        gain.gain.linearRampToValueAtTime(0.3, t + 0.01);
                        gain.gain.exponentialRampToValueAtTime(0.001, t + 0.22);
                        osc.start(t); osc.stop(t + 0.22);
                    });
                } catch(e) {}
            }

            function showWaToast(title, message, color) {
                const stack = document.getElementById('waToastStack');
                if (!stack) return;
                const div = document.createElement('div');
                div.style.cssText = 'background:#fff;border:1px solid #e2e8f0;border-left:4px solid ' + (color || '#25D366') + ';border-radius:10px;padding:10px 14px;box-shadow:0 4px 16px rgba(0,0,0,0.12);pointer-events:auto;animation:waSlideIn .25s ease;';
                div.innerHTML = '<div style="display:flex;align-items:flex-start;gap:8px;"><span class="material-icons" style="color:' + (color||'#25D366') + ';font-size:20px;flex-shrink:0;margin-top:1px;">chat</span><div style="flex:1;min-width:0;"><div style="font-weight:700;font-size:13px;color:#0f172a;">' + title + '</div>' + (message ? '<div style="font-size:12px;color:#64748b;margin-top:2px;">' + message + '</div>' : '') + '</div><button onclick="this.parentElement.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:#94a3b8;font-size:18px;line-height:1;padding:0;flex-shrink:0;">&times;</button></div>';
                stack.appendChild(div);
                setTimeout(function() { try { div.remove(); } catch(e){} }, 5000);
            }

            const fileInput = document.getElementById('waLeadFileInput');
            const attachBtn = document.getElementById('waLeadAttachBtn');
            const filePreviewEl = document.getElementById('waLeadFilePreview');
            const fileNameEl = document.getElementById('waLeadFileName');
            const fileSizeEl = document.getElementById('waLeadFileSize');
            const fileIconEl = document.getElementById('waLeadFileIcon');
            const fileRemoveBtn = document.getElementById('waLeadFileRemove');
            let pendingFile = null;

            attachBtn?.addEventListener('click', () => fileInput.click());
            fileInput?.addEventListener('change', function () {
                const f = this.files[0]; if (!f) return;
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
                msgInput.placeholder = 'Type a WhatsApp message…';
            }

            let lastMsgId = 0;
            chatBody.querySelectorAll('[data-msg-id]').forEach(el => {
                const id = parseInt(el.dataset.msgId) || 0;
                if (id > lastMsgId) lastMsgId = id;
            });
            chatBody.scrollTop = chatBody.scrollHeight;

            openChatBtn?.addEventListener('click', function () {
                document.getElementById('whatsappSection')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                msgInput.focus();
            });

            document.querySelectorAll('.wa-template-btn:not(.wa-tpl-direct-btn)').forEach(btn => {
                btn.addEventListener('click', () => { msgInput.value = btn.dataset.msg || ''; msgInput.focus(); });
            });

            const TEMPLATE_URL = @json(route('telecaller.leads.whatsapp.template', encrypt($lead->id)));
            document.querySelectorAll('.wa-tpl-direct-btn').forEach(btn => {
                btn.addEventListener('click', async function () {
                    const templateName = btn.dataset.template;
                    const params = JSON.parse(btn.dataset.params || '[]');
                    const displayBody = btn.dataset.display || '';
                    const origHtml = btn.innerHTML;
                    btn.disabled = true; btn.innerHTML = '⏳ Sending…';
                    try {
                        const res = await fetch(TEMPLATE_URL, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                            body: JSON.stringify({ template_name: templateName, params: params, display_body: displayBody }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok) { showError(data.message || 'Template send failed'); return; }
                        document.getElementById('waEmptyPlaceholder')?.remove();
                        appendBubble({ id: data.message_id, body: data.message, direction: 'outbound', time: data.time, status: 'sent' });
                        if (data.message_id > lastMsgId) lastMsgId = data.message_id;
                    } catch (err) { showError(err.message || 'Network error.');
                    } finally { btn.disabled = false; btn.innerHTML = origHtml; }
                });
            });

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                if (pendingFile) { await sendMedia(); return; }
                const text = msgInput.value.trim(); if (!text) return;
                setSending(true);
                try {
                    const res = await fetch(SAVE_URL, {
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
                    showWaToast('Message sent', 'WhatsApp message delivered to queue', '#137fec');
                } catch (err) { showError(err.message || 'Network error.');
                } finally { setSending(false); }
            });

            async function sendMedia() {
                if (!pendingFile) return;
                setSending(true);
                try {
                    const fd = new FormData();
                    fd.append('_token', CSRF); fd.append('file', pendingFile);
                    const caption = msgInput.value.trim(); if (caption) fd.append('caption', caption);
                    const res = await fetch(MEDIA_URL, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) { showError(data.message || 'Upload failed'); return; }
                    clearFile(); msgInput.value = '';
                    document.getElementById('waEmptyPlaceholder')?.remove();
                    appendBubble({ id: data.message_id, body: data.message, direction: 'outbound', time: data.time || now(), status: 'sent', media_type: data.media_type, media_url: data.media_url, media_filename: data.media_filename });
                    lastMsgId = data.message_id || lastMsgId;
                    showWaToast('Media sent', 'File delivered to queue', '#137fec');
                } catch (err) { showError(err.message || 'Upload failed.');
                } finally { setSending(false); }
            }

            const pollTimer = setInterval(poll, 15000);
            document.addEventListener('visibilitychange', () => { if (document.hidden) clearInterval(pollTimer); });

            async function poll() {
                try {
                    const res = await fetch(FETCH_URL + '?after=' + lastMsgId, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (data.messages?.length > 0) {
                        document.getElementById('waEmptyPlaceholder')?.remove();
                        let newInbound = 0;
                        data.messages.forEach(m => {
                            if (m.id > lastMsgId) {
                                appendBubble({ id: m.id, body: m.body, direction: m.direction, time: m.time, status: m.status || 'sent', media_type: m.media_type, media_url: m.media_url, media_filename: m.media_filename });
                                lastMsgId = m.id;
                                if (m.direction === 'inbound') newInbound++;
                            }
                        });
                        if (newInbound > 0) { playWaChime(); showWaToast('New WhatsApp message', newInbound > 1 ? newInbound + ' new messages' : 'New message received', '#25D366'); }
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
                        img.src = media_url; img.style.cssText = 'max-width:200px;max-height:160px;border-radius:6px;display:block;margin-bottom:4px;cursor:pointer;';
                        img.onclick = () => window.open(img.src, '_blank'); el.appendChild(img);
                    } else if (media_type === 'audio') {
                        const audio = document.createElement('audio'); audio.controls = true;
                        audio.style.cssText = 'width:100%;min-width:180px;margin-bottom:4px;';
                        audio.innerHTML = `<source src="${media_url}">`; el.appendChild(audio);
                    } else if (media_type === 'video') {
                        const video = document.createElement('video'); video.controls = true;
                        video.style.cssText = 'max-width:200px;max-height:160px;border-radius:6px;display:block;margin-bottom:4px;';
                        video.innerHTML = `<source src="${media_url}">`; el.appendChild(video);
                    } else {
                        const a = document.createElement('a'); a.href = media_url; a.target = '_blank'; a.download = true;
                        a.style.cssText = 'display:flex;align-items:center;gap:6px;background:rgba(0,0,0,.07);border-radius:6px;padding:6px 10px;margin-bottom:4px;text-decoration:none;color:inherit;font-size:12px;font-weight:600;';
                        a.innerHTML = `<span class="material-icons" style="font-size:18px;color:#137fec;">description</span>${escHtml(media_filename || 'File')}`;
                        el.appendChild(a);
                    }
                }
                const showText = body && !['image','audio','video'].includes(media_type || '');
                if (showText) { const p = document.createElement('p'); p.className = 'mb-1'; p.textContent = body; el.appendChild(p); }
                const tickHtml = outgoing ? `<span class="wa-tick ${status === 'read' ? 'wa-tick-read' : status === 'delivered' ? 'wa-tick-delivered' : 'wa-tick-sent'}">${(status === 'delivered' || status === 'read') ? '✓✓' : '✓'}</span>` : '';
                const meta = document.createElement('div'); meta.className = 'wa-message-meta';
                meta.innerHTML = `<small>${time || now()}</small>${tickHtml}`; el.appendChild(meta);
                chatBody.appendChild(el); chatBody.scrollTop = chatBody.scrollHeight;
            }

            function setSending(v) { sendBtn.disabled = v; sendIcon.classList.toggle('d-none', v); spinner.classList.toggle('d-none', !v); }
            function showError(msg) {
                const div = document.createElement('div');
                div.className = 'alert alert-danger alert-dismissible mx-2 my-1 py-2 small';
                div.innerHTML = '<strong>Send failed:</strong> ' + escHtml(msg) + ' <button type="button" class="btn-close" style="font-size:11px;" onclick="this.parentElement.remove()"></button>';
                chatBody.appendChild(div); chatBody.scrollTop = chatBody.scrollHeight;
            }
            function now() { return new Date().toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' }); }
            function escHtml(str) { return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
        })();
    </script>

    <script>
        (function() {
            const statusSelect = document.getElementById('statusSelect');
            const confirmBtn   = document.getElementById('statusConfirmBtn');
            const finalBtn     = document.getElementById('statusFinalSubmit');

            statusSelect?.addEventListener('change', function() {
                document.getElementById('followupFields').style.display = this.value === 'follow_up' ? 'block' : 'none';
                confirmBtn?.classList.remove('d-none');
                finalBtn?.classList.add('d-none');
            });
            confirmBtn?.addEventListener('click', function() {
                const selected = statusSelect?.options[statusSelect.selectedIndex]?.text || '';
                const current  = @json(ucfirst(str_replace('_', ' ', $lead->status)));
                confirmBtn.textContent = 'Changing: ' + current + ' → ' + selected;
                confirmBtn.classList.add('d-none');
                finalBtn?.classList.remove('d-none');
            });
            document.getElementById('statusModal')?.addEventListener('hidden.bs.modal', function() {
                confirmBtn?.classList.remove('d-none');
                if (confirmBtn) confirmBtn.textContent = 'Update Status';
                finalBtn?.classList.add('d-none');
            });
        })();
    </script>

    <script>
        (function() {
            const outcomeUrl = @json(route('call.outcome'));
            const csrf = @json(csrf_token());
            document.querySelectorAll('.outcome-btn').forEach(function(btn) {
                btn.addEventListener('click', async function() {
                    const callLogId = document.getElementById('outcomeCallLogId').value;
                    const outcome = this.dataset.outcome;
                    if (!callLogId) return;
                    try {
                        await fetch(outcomeUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                            body: JSON.stringify({ call_log_id: callLogId, outcome: outcome })
                        });
                    } catch(e) {}
                    bootstrap.Modal.getInstance(document.getElementById('callOutcomeModal'))?.hide();
                });
            });
        })();
    </script>

<style>
/* ── Page Header ─────────────────────────────────── */
.sl-page-header {
    display: flex; align-items: center; gap: 16px;
    background: #fff; border: 1px solid var(--border-color);
    border-radius: 14px; padding: 14px 20px;
}
.sl-back-btn {
    width: 38px; height: 38px; border-radius: 10px;
    background: var(--background-light); color: var(--text-dark);
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; flex-shrink: 0; transition: background .15s;
}
.sl-back-btn:hover { background: var(--primary-color); color: #fff; }
.sl-page-title { font-size: 16px; font-weight: 800; color: var(--text-dark); margin: 0; }
.sl-page-sub   { font-size: 12px; color: var(--text-muted); margin: 2px 0 0; }

/* ── Profile Card ────────────────────────────────── */
.sl-profile-card {
    background: #fff; border: 1px solid var(--border-color);
    border-radius: 16px; overflow: hidden;
}
.sl-profile-banner {
    height: 80px;
    background: var(--grad-primary);
    display: flex; align-items: flex-end; justify-content: center;
    padding-bottom: 0; position: relative;
}
.sl-profile-avatar {
    width: 68px; height: 68px; border-radius: 50%;
    background: #fff; color: var(--primary-color);
    font-size: 22px; font-weight: 900;
    display: flex; align-items: center; justify-content: center;
    border: 3px solid #fff;
    box-shadow: 0 4px 16px rgba(99,102,241,0.25);
    position: absolute; bottom: -34px;
}
.sl-profile-body { padding: 44px 20px 20px; }
.sl-profile-name { font-size: 17px; font-weight: 800; color: var(--text-dark); text-align: center; margin: 0 0 4px; }
.sl-profile-code { font-size: 12px; color: var(--text-muted); text-align: center; margin: 0 0 20px; font-weight: 600; }

.sl-info-grid { display: flex; flex-direction: column; gap: 10px; }
.sl-info-item {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 12px; border-radius: 10px;
    background: var(--background-light); border: 1px solid var(--border-color);
}
.sl-info-icon {
    width: 34px; height: 34px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.sl-info-icon .material-icons { font-size: 17px; }
.sl-info-label { font-size: 10.5px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .5px; }
.sl-info-val   { font-size: 13px; font-weight: 600; color: var(--text-dark); }

/* ── Action Panel ────────────────────────────────── */
.sl-actions-panel {
    display: flex; gap: 8px; flex-direction: column;
}
.sl-action-btn {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    padding: 12px 16px; border-radius: 12px;
    font-size: 14px; font-weight: 700;
    border: none; cursor: pointer; transition: all .18s;
    width: 100%;
}
.sl-action-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(0,0,0,0.14); }
.sl-action-call   { background: var(--grad-primary); color: #fff; }
.sl-action-end    { background: var(--grad-danger);  color: #fff; }
.sl-action-wa     { background: var(--grad-success); color: #fff; }
.sl-action-status { background: #f1f5f9; color: var(--text-dark); border: 1px solid var(--border-color); }
.sl-action-status:hover { background: var(--text-dark); color: #fff; }

/* ── Section Card ────────────────────────────────── */
.sl-section-card {
    background: #fff; border: 1px solid var(--border-color);
    border-radius: 14px; overflow: hidden;
}
.sl-section-head {
    display: flex; align-items: center;
    padding: 14px 18px; border-bottom: 1px solid var(--border-color);
    gap: 8px; flex-wrap: wrap;
}

/* ── Note Input ──────────────────────────────────── */
.sl-note-input {
    border-radius: 10px !important; font-size: 13px !important;
    resize: none;
    padding: 12px 14px !important;
}
.sl-note-input:focus { border-color: var(--primary-color) !important; box-shadow: 0 0 0 3px rgba(99,102,241,0.1) !important; }
.sl-section-card form { padding: 14px 18px; }

/* ── Timeline ────────────────────────────────────── */
.sl-timeline-filters {
    display: flex; align-items: center; gap: 4px; flex-wrap: wrap;
}
.sl-filter-btn {
    padding: 5px 12px; border-radius: 20px;
    font-size: 11.5px; font-weight: 600;
    border: 1px solid var(--border-color); background: #fff;
    color: var(--text-muted); cursor: pointer;
    transition: all .15s; display: inline-flex; align-items: center; gap: 3px;
}
.sl-filter-btn:hover { border-color: var(--primary-color); color: var(--primary-color); }
.sl-filter-btn.active { background: var(--primary-color); border-color: var(--primary-color); color: #fff; }

.sl-timeline { padding: 16px 18px; display: flex; flex-direction: column; gap: 4px; }
.sl-timeline-item {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 12px 14px; border-radius: 10px;
    transition: background .12s;
    position: relative;
}
.sl-timeline-item:hover { background: var(--background-light); }
.sl-timeline-icon {
    width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    margin-top: 1px;
}
.sl-timeline-icon .material-icons { font-size: 17px; }
.sl-timeline-body { flex: 1; min-width: 0; }
.sl-timeline-desc { font-size: 13px; color: var(--text-dark); font-weight: 500; line-height: 1.45; }
.sl-timeline-meta {
    display: flex; align-items: center; gap: 4px;
    font-size: 11px; color: var(--text-muted); margin-top: 4px; font-weight: 500;
}
.sl-timeline-type-badge {
    font-size: 10.5px; font-weight: 700;
    padding: 2px 8px; border-radius: 20px;
    flex-shrink: 0; white-space: nowrap;
    text-transform: capitalize;
    align-self: flex-start; margin-top: 2px;
}
.sl-timeline-empty {
    text-align: center; padding: 32px 0; color: var(--text-muted); font-size: 13px;
}
</style>
@endsection
