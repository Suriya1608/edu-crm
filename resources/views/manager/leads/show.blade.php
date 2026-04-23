@extends('layouts.manager.app')
@section('content')
    @include('layouts.whatsappchat')
    <!-- Lead sub-nav (non-sticky — avoids duplicate sticky header over notification dropdown) -->
    <div class="lead-profile-nav">
        <div class="d-flex justify-content-between align-items-center w-100">

            <div class="d-flex align-items-center gap-3">

                <a href="{{ route('manager.leads') }}" class="btn btn-sm btn-light">
                    <span class="material-icons me-1" style="font-size: 18px;">arrow_back</span>
                    Back to Leads
                </a>

                <div>
                    <h2 class="page-header-title mb-0">Lead Profile</h2>
                    <p class="page-header-subtitle mb-0">
                        Complete details and activity timeline
                    </p>
                </div>
            </div>

        </div>
    </div>

    <div class="dashboard-content">
        <div class="row g-4">

            <!-- LEFT COLUMN -->
            <div class="col-lg-4">

                <div class="profile-card mb-4">

                    <div class="profile-header">

                        {{-- <div class="profile-avatar">
                            <img src="https://i.pravatar.cc/150?img=33">
                        </div> --}}

                        <div class="profile-info">
                            <h1 class="profile-name">
                                {{ $lead->name }}
                            </h1>

                            <span class="status-badge hot-lead">
                                {{ strtoupper($lead->status) }}
                            </span>

                            <p class="profile-id">
                                ID: {{ $lead->lead_code }}
                            </p>
                        </div>
                    </div>

                    <div class="profile-details">

                        <!-- PHONE -->
                        <div class="detail-item">
                            <span class="material-icons">phone</span>
                            <div class="flex-grow-1">
                                <p class="detail-label">Phone</p>
                                <div class="d-flex justify-content-between">
                                    <p class="detail-value">
                                        {{ $lead->phone }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- EMAIL -->
                        <div class="detail-item">
                            <span class="material-icons">mail</span>
                            <div class="flex-grow-1">
                                <p class="detail-label">Email</p>
                                <p class="detail-value">
                                    {{ $lead->email ?? '-' }}
                                </p>
                            </div>
                        </div>

                        <!-- COURSE -->
                        <div class="detail-item">
                            <span class="material-icons">school</span>
                            <div class="flex-grow-1">
                                <p class="detail-label">Course Applied</p>
                                <p class="detail-value">
                                    {{ $lead->course ?? '-' }}
                                </p>
                            </div>
                        </div>

                        <!-- ASSIGNED TELECALLER -->
                        <div class="detail-item">
                            <span class="material-icons">person</span>
                            <div class="flex-grow-1">
                                <p class="detail-label">Assigned Telecaller</p>

                                <form method="POST" action="{{ route('manager.assign', encrypt($lead->id)) }}">
                                    @csrf

                                    <select name="assigned_to" class="form-select form-select-sm mb-2" required>
                                        <option value="">Select Telecaller</option>

                                        @foreach ($telecallers as $tele)
                                            <option value="{{ $tele->id }}"
                                                {{ $lead->assigned_to == $tele->id ? 'selected disabled' : '' }}>
                                                {{ $tele->name }}
                                                {{ $lead->assigned_to == $tele->id ? '(Current)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>


                                    <button class="btn btn-sm btn-primary w-100">
                                        {{ $lead->assignedUser ? 'Reassign' : 'Assign' }}
                                    </button>
                                </form>

                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN -->
            <div class="col-lg-8">

                <!-- ACTION BAR -->
                <div class="action-bar mb-4">
                    <button type="button" class="btn btn-primary call-btn" data-phone="{{ $lead->phone }}"
                        data-provider="{{ $provider }}" data-lead="{{ $lead->id }}">
                        <span class="material-icons">call</span>
                        <span class="call-text">Call Now</span>
                    </button>

                    {{-- Timer now shown in the global call bar --}}



                    <button class="btn btn-success" type="button" id="openWhatsappChat">
                        <span class="material-icons">chat</span>
                        WhatsApp
                    </button>

                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#statusModal">
                        <span class="material-icons">sync_alt</span>
                        Change Status
                    </button>

                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-0">
                        <div class="wa-chat-window">
                            <div class="wa-chat-header">
                                <div class="wa-user-block">
                                    <div class="wa-avatar">{{ strtoupper(substr($lead->name, 0, 1)) }}</div>
                                    <div>
                                        <h6 class="mb-0">{{ $lead->name }}</h6>
                                        <small>WhatsApp CRM Chat</small>
                                    </div>
                                </div>
                                <span class="wa-live-dot"></span>
                            </div>

                            <div id="waChatBody" class="wa-chat-body">
                                @forelse ($whatsappMessages as $msg)
                                    @php
                                        $isOut   = $msg->direction !== 'inbound';
                                        $status  = data_get($msg->meta_data, 'meta_status', 'sent');
                                        $isDbl   = in_array($status, ['delivered', 'read']);
                                        $tickCls = $status === 'read' ? 'wa-tick-read' : ($status === 'delivered' ? 'wa-tick-delivered' : 'wa-tick-sent');
                                    @endphp
                                    <div class="wa-message {{ $isOut ? 'wa-outgoing' : 'wa-incoming' }}"
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
                                            @if ($isOut)
                                                <span class="wa-tick {{ $tickCls }}">{{ $isDbl ? '✓✓' : '✓' }}</span>
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
                                        <span class="material-icons">send</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NOTE SECTION -->
                <div class="note-section mb-4">
                    <form method="POST" action="{{ route('manager.leads.addNote', encrypt($lead->id)) }}">
                        @csrf

                        <textarea name="note" class="form-control" rows="2" placeholder="Write a note about this lead..." required></textarea>

                        <div class="d-flex justify-content-end mt-3">
                            <button class="btn btn-dark">
                                Add Note
                            </button>
                        </div>
                    </form>
                </div>

                <!-- TIMELINE -->
                <div class="timeline-card">

                    <div class="timeline-header">
                        <h2>Activity Timeline</h2>
                        <div class="timeline-filters">
                            <button class="filter-btn active" onclick="filterTimeline('all')">All</button>
                            <button class="filter-btn" onclick="filterTimeline('call')">Calls</button>
                            <button class="filter-btn" onclick="filterTimeline('whatsapp')">WhatsApp</button>
                        </div>
                    </div>

                    <div class="timeline-content">

                        @foreach ($lead->activities()->latest()->get() as $activity)
                            <div class="timeline-item" data-type="{{ $activity->type }}">

                                <div class="timeline-icon">
                                    <span class="material-icons">
                                        @switch($activity->type)
                                            @case('call')
                                                call
                                            @break

                                            @case('note')
                                                description
                                            @break

                                            @case('whatsapp')
                                                chat
                                            @break

                                            @case('sms')
                                                sms
                                            @break

                                            @case('status_change')
                                                sync_alt
                                            @break

                                            @case('followup')
                                                event
                                            @break

                                            @case('assignment')
                                                person
                                            @break

                                            @default
                                                info
                                        @endswitch
                                    </span>
                                </div>

                                <div class="timeline-body">
                                    {{-- <h5>{{ ucfirst(str_replace('_', ' ', $activity->type)) }}</h5> --}}
                                    <p>{{ $activity->description }}</p>

                                    <small>
                                        {{ $activity->user->name ?? '-' }}
                                        |
                                        {{ $activity->created_at->diffForHumans() }}
                                    </small>
                                </div>
                            </div>
                        @endforeach

                    </div>
                </div>

            </div>
        </div>
    </div>
    </div>


    <!-- Change Status Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('manager.leads.changeStatus', encrypt($lead->id)) }}">
                @csrf

                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Change Lead Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Select New Status</label>
                            <select class="form-select" name="status" id="statusSelect">
                                <option value="new">New</option>
                                <option value="assigned">Assigned</option>
                                <option value="contacted">Contacted</option>
                                <option value="interested">Interested</option>
                                <option value="follow_up">Follow-up Required</option>
                                <option value="not_interested">Not Interested</option>
                                <option value="converted">Converted</option>
                            </select>
                        </div>

                        <!-- FOLLOWUP FIELDS (HIDDEN INITIALLY) -->
                        <div id="followupFields" style="display:none;">

                            <div class="row g-2 mb-3">
                                <div class="col-7">
                                    <label class="form-label fw-semibold">Follow-up Date</label>
                                    <input type="date" name="next_followup" class="form-control"
                                           min="{{ now()->toDateString() }}">
                                </div>
                                <div class="col-5">
                                    <label class="form-label fw-semibold">Time</label>
                                    <input type="time" name="followup_time" class="form-control">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Remarks</label>
                                <textarea name="remarks" class="form-control" rows="2"></textarea>
                            </div>

                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancel
                        </button>

                        <button type="submit" class="btn btn-primary">
                            Update Status
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script>
        function filterTimeline(type) {

            // Toggle active button
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            event.target.classList.add('active');

            // Filter items
            document.querySelectorAll('.timeline-item').forEach(item => {

                if (type === 'all') {
                    item.style.display = 'flex';
                } else {
                    if (item.dataset.type === type) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                }

            });
        }
    </script>

    <script>
        // Initialize call device on page load
        window.addEventListener('load', function () {
            GC.initDevice();
        });

        // Handle call button click — delegate to GC
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
                await GC.startCall(btn.dataset.phone, btn.dataset.lead);
            } catch (err) {
                btn.disabled = false;
                btn.querySelector('.call-text').textContent = 'Call Now';
            }
        });

        // Update call button when call is accepted
        document.addEventListener('gc:callAccepted', function () {
            var btn = document.querySelector('.call-btn');
            if (!btn) return;
            btn.disabled = false;
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-danger', 'active-call');
            btn.querySelector('.call-text').textContent = 'End Call';
        });

        // Reset call button when call ends and show outcome modal
        document.addEventListener('gc:callEnded', function (e) {
            var btn = document.querySelector('.call-btn');
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('btn-danger', 'active-call');
                btn.classList.add('btn-primary');
                btn.querySelector('.call-text').textContent = 'Call Now';
            }
            var callLogId = e.detail && e.detail.callLogId;
            if (callLogId) {
                document.getElementById('outcomeCallLogId').value = callLogId;
                var modal = new bootstrap.Modal(document.getElementById('callOutcomeModal'));
                modal.show();
            }
        });
    </script>






    <script>
        (function () {
            const chatBody = document.getElementById('waChatBody');
            const msgInput = document.getElementById('waMessageInput');
            const form     = document.getElementById('waComposerForm');
            const openBtn  = document.getElementById('openWhatsappChat');
            const sendBtn  = document.getElementById('waSendBtn');

            if (!chatBody || !msgInput || !form) return;

            const saveUrl   = @json(route('manager.leads.whatsapp.store', encrypt($lead->id)));
            const mediaUrl  = @json(route('manager.leads.whatsapp.media', encrypt($lead->id)));
            const fetchUrl  = @json(route('manager.leads.whatsapp.fetch', encrypt($lead->id)));
            const csrfToken = '{{ csrf_token() }}';

            // ── Notification helpers ───────────────────────────
            let _waAudioCtx = null;
            function playWaChime() {
                try {
                    if (!_waAudioCtx) _waAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
                    [[1100, 0], [880, 0.18]].forEach(function(pair) {
                        const osc  = _waAudioCtx.createOscillator();
                        const gain = _waAudioCtx.createGain();
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
                div.innerHTML = '<div style="display:flex;align-items:flex-start;gap:8px;">' +
                    '<span class="material-icons" style="color:' + (color || '#25D366') + ';font-size:20px;flex-shrink:0;margin-top:1px;">chat</span>' +
                    '<div style="flex:1;min-width:0;">' +
                        '<div style="font-weight:700;font-size:13px;color:#0f172a;">' + title + '</div>' +
                        (message ? '<div style="font-size:12px;color:#64748b;margin-top:2px;">' + message + '</div>' : '') +
                    '</div>' +
                    '<button onclick="this.parentElement.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:#94a3b8;font-size:18px;line-height:1;padding:0;flex-shrink:0;">&times;</button>' +
                    '</div>';
                stack.appendChild(div);
                setTimeout(function() { try { div.remove(); } catch(e){} }, 5000);
            }

            // ── File attach ──────────────────────────────────
            const fileInput      = document.getElementById('waLeadFileInput');
            const attachBtn      = document.getElementById('waLeadAttachBtn');
            const filePreviewEl  = document.getElementById('waLeadFilePreview');
            const fileNameEl     = document.getElementById('waLeadFileName');
            const fileSizeEl     = document.getElementById('waLeadFileSize');
            const fileIconEl     = document.getElementById('waLeadFileIcon');
            const fileRemoveBtn  = document.getElementById('waLeadFileRemove');
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

            // Track the highest message ID currently rendered
            let lastMsgId = 0;
            chatBody.querySelectorAll('[data-msg-id]').forEach(el => {
                const id = parseInt(el.dataset.msgId, 10);
                if (id > lastMsgId) lastMsgId = id;
            });

            // Scroll to bottom on load
            chatBody.scrollTop = chatBody.scrollHeight;

            // Open chat shortcut button
            openBtn?.addEventListener('click', function () {
                chatBody.scrollIntoView({ behavior: 'smooth', block: 'start' });
                msgInput.focus();
            });

            // Template quick-reply: text-fill buttons (populate input)
            document.querySelectorAll('.wa-template-btn:not(.wa-tpl-direct-btn)').forEach(btn => {
                btn.addEventListener('click', function () {
                    msgInput.value = btn.dataset.msg || '';
                    msgInput.focus();
                });
            });

            // Template quick-reply: direct-send buttons (fire approved Meta template immediately)
            const templateUrl = @json(route('manager.leads.whatsapp.template', encrypt($lead->id)));
            document.querySelectorAll('.wa-tpl-direct-btn').forEach(btn => {
                btn.addEventListener('click', async function () {
                    const templateName = btn.dataset.template;
                    const params       = JSON.parse(btn.dataset.params || '[]');
                    const displayBody  = btn.dataset.display || '';

                    const origHtml = btn.innerHTML;
                    btn.disabled   = true;
                    btn.innerHTML  = '⏳ Sending…';

                    try {
                        const res = await fetch(templateUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                template_name: templateName,
                                params:        params,
                                display_body:  displayBody,
                            }),
                        });

                        const data = await res.json().catch(() => ({}));

                        if (!res.ok) {
                            showWaError(data.message || 'Template send failed (' + res.status + ')');
                            return;
                        }

                        appendBubble({
                            id:        data.message_id,
                            body:      data.message,
                            direction: 'outbound',
                            time:      data.time,
                            status:    'sent',
                        });

                        if (data.message_id > lastMsgId) lastMsgId = data.message_id;

                    } catch (err) {
                        showWaError(err.message || 'Network error.');
                    } finally {
                        btn.disabled  = false;
                        btn.innerHTML = origHtml;
                    }
                });
            });

            // ── Tick mark helpers ──────────────────────────────
            function tickCls(status) {
                return status === 'read' ? 'wa-tick-read'
                     : status === 'delivered' ? 'wa-tick-delivered'
                     : 'wa-tick-sent';
            }

            function tickChar(status) {
                return (status === 'delivered' || status === 'read') ? '✓✓' : '✓';
            }

            function updateTick(msgEl, status) {
                const tick = msgEl.querySelector('.wa-tick');
                if (!tick) return;
                tick.className = 'wa-tick ' + tickCls(status);
                tick.textContent = tickChar(status);
            }

            // ── Build a bubble element ─────────────────────────
            function buildBubble(msg) {
                const isOut = msg.direction !== 'inbound';
                const div   = document.createElement('div');
                div.className  = 'wa-message ' + (isOut ? 'wa-outgoing' : 'wa-incoming');
                div.dataset.msgId = msg.id;

                // Media content
                if (msg.media_type && msg.media_url) {
                    if (msg.media_type === 'image') {
                        const img = document.createElement('img');
                        img.src = msg.media_url;
                        img.style.cssText = 'max-width:200px;max-height:160px;border-radius:6px;display:block;margin-bottom:4px;cursor:pointer;';
                        img.onclick = () => window.open(img.src, '_blank');
                        div.appendChild(img);
                    } else if (msg.media_type === 'audio') {
                        const audio = document.createElement('audio');
                        audio.controls = true;
                        audio.style.cssText = 'width:100%;min-width:180px;margin-bottom:4px;';
                        audio.innerHTML = `<source src="${msg.media_url}">`;
                        div.appendChild(audio);
                    } else if (msg.media_type === 'video') {
                        const video = document.createElement('video');
                        video.controls = true;
                        video.style.cssText = 'max-width:200px;max-height:160px;border-radius:6px;display:block;margin-bottom:4px;';
                        video.innerHTML = `<source src="${msg.media_url}">`;
                        div.appendChild(video);
                    } else {
                        const a = document.createElement('a');
                        a.href = msg.media_url; a.target = '_blank'; a.download = true;
                        a.style.cssText = 'display:flex;align-items:center;gap:6px;background:rgba(0,0,0,.07);border-radius:6px;padding:6px 10px;margin-bottom:4px;text-decoration:none;color:inherit;font-size:12px;font-weight:600;';
                        a.innerHTML = `<span class="material-icons" style="font-size:18px;color:#137fec;">description</span>${msg.media_filename || 'File'}`;
                        div.appendChild(a);
                    }
                }

                const bodyText = msg.body || '';
                const showText = bodyText && !['image','audio','video'].includes(msg.media_type || '');
                if (showText) {
                    const p = document.createElement('p');
                    p.className = 'mb-1';
                    p.textContent = bodyText;
                    div.appendChild(p);
                }

                const meta = document.createElement('div');
                meta.className = 'wa-message-meta';
                const time = document.createElement('small');
                time.textContent = msg.time;
                meta.appendChild(time);

                if (isOut) {
                    const tick = document.createElement('span');
                    const st   = msg.status || 'sent';
                    tick.className   = 'wa-tick ' + tickCls(st);
                    tick.textContent = tickChar(st);
                    meta.appendChild(tick);
                }

                div.appendChild(meta);
                return div;
            }

            function appendBubble(msg) {
                document.getElementById('waEmptyPlaceholder')?.remove();
                chatBody.appendChild(buildBubble(msg));
                chatBody.scrollTop = chatBody.scrollHeight;
            }

            // ── Auto-poll every 5 s ───────────────────────────
            async function poll() {
                try {
                    const res = await fetch(`${fetchUrl}?after=${lastMsgId}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    if (!res.ok) return;
                    const data = await res.json();

                    // Append any new messages (inbound or outbound from another session)
                    let newInbound = 0;
                    (data.messages || []).forEach(msg => {
                        if (!chatBody.querySelector(`[data-msg-id="${msg.id}"]`)) {
                            appendBubble(msg);
                            if (msg.direction === 'inbound') newInbound++;
                        }
                        if (msg.id > lastMsgId) lastMsgId = msg.id;
                    });
                    if (newInbound > 0) {
                        playWaChime();
                        showWaToast('New WhatsApp message', newInbound > 1 ? newInbound + ' new messages received' : 'New message received', '#25D366');
                    }

                    // Update tick marks on already-shown outbound bubbles
                    Object.entries(data.statuses || {}).forEach(([id, status]) => {
                        const el = chatBody.querySelector(`[data-msg-id="${id}"]`);
                        if (el) updateTick(el, status);
                    });
                } catch (_) { /* silent on network errors */ }
            }

            setInterval(poll, 15000);

            function showWaError(msg) {
                const err = document.createElement('div');
                err.className = 'alert alert-danger alert-dismissible mx-2 my-1 py-2 small';
                err.innerHTML = '<strong>Send failed:</strong> ' + msg +
                    ' <button type="button" class="btn-close" style="font-size:11px;" onclick="this.parentElement.remove()"></button>';
                document.getElementById('waEmptyPlaceholder')?.remove();
                chatBody.appendChild(err);
                chatBody.scrollTop = chatBody.scrollHeight;
            }

            // ── Send ──────────────────────────────────────────
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                if (pendingFile) { await sendMedia(); return; }

                const message = msgInput.value.trim();
                if (!message) return;

                sendBtn.disabled = true;

                try {
                    const res = await fetch(saveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ message }),
                    });

                    const data = await res.json().catch(() => ({}));

                    if (!res.ok) {
                        showWaError(data.message || 'Server error (' + res.status + ')');
                        return;
                    }

                    // Show bubble immediately with 'sent' tick
                    appendBubble({
                        id:        data.message_id,
                        body:      data.message,
                        direction: 'outbound',
                        time:      data.time,
                        status:    'sent',
                    });

                    if (data.message_id > lastMsgId) lastMsgId = data.message_id;
                    msgInput.value = '';
                    showWaToast('Message sent', 'WhatsApp message delivered to queue', '#137fec');

                } catch (err) {
                    showWaError(err.message || 'Network error — check your connection.');
                } finally {
                    sendBtn.disabled = false;
                }
            });

            // ── Send media ────────────────────────────────────
            async function sendMedia() {
                if (!pendingFile) return;
                sendBtn.disabled = true;
                try {
                    const fd = new FormData();
                    fd.append('_token', csrfToken);
                    fd.append('file', pendingFile);
                    const caption = msgInput.value.trim();
                    if (caption) fd.append('caption', caption);

                    const res  = await fetch(mediaUrl, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd });
                    const data = await res.json().catch(() => ({}));

                    if (!res.ok) { showWaError(data.message || 'Upload failed'); return; }

                    clearFile();
                    msgInput.value = '';
                    appendBubble({
                        id:             data.message_id,
                        body:           data.message,
                        direction:      'outbound',
                        time:           data.time,
                        status:         'sent',
                        media_type:     data.media_type,
                        media_url:      data.media_url,
                        media_filename: data.media_filename,
                    });
                    if (data.message_id > lastMsgId) lastMsgId = data.message_id;
                    showWaToast('Media sent', 'File delivered to queue', '#137fec');
                } catch (err) {
                    showWaError(err.message || 'Upload failed.');
                } finally {
                    sendBtn.disabled = false;
                }
            }
        })();
    </script>

    
    <script>
        document.getElementById('statusSelect').addEventListener('change', function() {
            let followupSection = document.getElementById('followupFields');

            if (this.value === 'follow_up') {
                followupSection.style.display = 'block';
            } else {
                followupSection.style.display = 'none';
            }
        });
    </script>

    {{-- Call Outcome Modal --}}
    <div class="modal fade" id="callOutcomeModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">How did the call go?</h5>
                </div>
                <div class="modal-body pt-2">
                    <p class="text-muted small mb-3">Select the outcome to log it against this lead.</p>
                    <input type="hidden" id="outcomeCallLogId">
                    <div class="d-grid gap-2">
                        <button class="btn btn-success outcome-btn" data-outcome="interested">Interested</button>
                        <button class="btn btn-danger outcome-btn" data-outcome="not_interested">Not Interested</button>
                        <button class="btn btn-warning text-dark outcome-btn" data-outcome="call_back_later">Call Back Later</button>
                        <button class="btn btn-secondary outcome-btn" data-outcome="switched_off">Switched Off / No Answer</button>
                        <button class="btn btn-outline-secondary outcome-btn" data-outcome="wrong_number">Wrong Number</button>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-link text-muted btn-sm" data-bs-dismiss="modal">Skip</button>
                </div>
            </div>
        </div>
    </div>

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
                    } catch(e) { /* silent */ }

                    bootstrap.Modal.getInstance(document.getElementById('callOutcomeModal'))?.hide();
                });
            });
        })();
    </script>
@endsection
