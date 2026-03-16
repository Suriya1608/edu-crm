@php
    $role   = auth()->user()->role;
    $rp     = $role === 'manager' ? 'manager.instagram' : 'telecaller.instagram';

    // Use route() for ALL URLs — url() uses APP_URL which breaks when behind ngrok/proxy
    $convUrl      = route($rp . '.conversations');
    $replyUrlTpl  = route($rp . '.reply',    ['id' => '__CID__']);
    $readUrlTpl   = route($rp . '.read',     ['id' => '__CID__']);
    $msgsUrlTpl   = route($rp . '.messages', ['id' => '__CID__']);
@endphp

@if (!$account)
    <div class="chart-card text-center py-5">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="56" height="56" style="opacity:.25;">
            <defs>
                <linearGradient id="igErrGrad" x1="0%" y1="100%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#f09433"/>
                    <stop offset="100%" stop-color="#bc1888"/>
                </linearGradient>
            </defs>
            <rect width="48" height="48" rx="12" fill="url(#igErrGrad)"/>
            <circle cx="24" cy="24" r="9" fill="none" stroke="#fff" stroke-width="3.5"/>
            <circle cx="34" cy="14" r="2.5" fill="#fff"/>
        </svg>
        <h5 class="mt-3 mb-1" style="color:#0f172a;">Instagram not connected</h5>
        <p class="text-muted small mb-0">Ask your admin to configure Instagram credentials in<br>
            <strong>Settings → Instagram</strong>.</p>
    </div>
@else

{{-- ─────────────────────────── MAIN CHAT SHELL ──────────────────────────── --}}
<div id="igWrap" style="
    display: flex;
    flex-direction: column;
    height: calc(100vh - 130px);
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
">

    {{-- ── Top bar ── --}}
    <div style="
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        border-bottom: 1px solid #e2e8f0;
        flex-shrink: 0;
        background: #fff;
    ">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" style="flex-shrink:0;">
            <defs>
                <linearGradient id="igTopGrad" x1="0%" y1="100%" x2="100%" y2="0%">
                    <stop offset="0%"   stop-color="#f09433"/>
                    <stop offset="25%"  stop-color="#e6683c"/>
                    <stop offset="50%"  stop-color="#dc2743"/>
                    <stop offset="75%"  stop-color="#cc2366"/>
                    <stop offset="100%" stop-color="#bc1888"/>
                </linearGradient>
            </defs>
            <rect width="24" height="24" rx="6" fill="url(#igTopGrad)"/>
            <circle cx="12" cy="12" r="4.5" fill="none" stroke="#fff" stroke-width="1.8"/>
            <circle cx="17.5" cy="6.5" r="1.2" fill="#fff"/>
        </svg>
        <div style="flex:1;">
            <div style="font-weight:700; font-size:15px; color:#0f172a; line-height:1.2;">Instagram Messages</div>
            <div style="font-size:11px; color:#64748b;">{{ $account->name }}</div>
        </div>
        <span id="igTotalBadge" class="badge bg-danger" style="display:none; font-size:11px;"></span>
    </div>

    {{-- ── Body ── --}}
    <div style="flex:1; display:flex; overflow:hidden;">

        {{-- ── Left: conversation list ── --}}
        <div id="igSidebar" style="
            width: 300px;
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            background: #fff;
        ">
            {{-- Search --}}
            <div style="padding: 10px 12px; border-bottom: 1px solid #f1f5f9;">
                <div style="position:relative;">
                    <span class="material-icons" style="position:absolute; left:8px; top:50%; transform:translateY(-50%); font-size:16px; color:#94a3b8; pointer-events:none;">search</span>
                    <input id="igSearch" type="text" class="form-control form-control-sm"
                           placeholder="Search conversations…"
                           autocomplete="off"
                           style="padding-left: 30px; border-radius: 8px; font-size:13px; border-color:#e2e8f0; background:#f8fafc;">
                </div>
            </div>
            {{-- List --}}
            <div id="igConvList" style="overflow-y:auto; flex:1;">
                <div id="igConvLoading" class="text-center py-5 text-muted" style="font-size:13px;">
                    <span class="material-icons d-block mb-1" style="font-size:22px; animation: ig-spin 1s linear infinite; color:#94a3b8;">autorenew</span>
                    Loading…
                </div>
            </div>
        </div>

        {{-- ── Right: chat panel ── --}}
        <div style="flex:1; display:flex; flex-direction:column; overflow:hidden; background:#f6f7f8;">

            {{-- Empty state --}}
            <div id="igEmpty" style="
                flex:1; display:flex;
                flex-direction:column;
                align-items:center;
                justify-content:center;
                color:#94a3b8;
            ">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="60" height="60" style="opacity:.2; margin-bottom:12px;">
                    <defs>
                        <linearGradient id="igEmptyGrad" x1="0%" y1="100%" x2="100%" y2="0%">
                            <stop offset="0%" stop-color="#f09433"/>
                            <stop offset="100%" stop-color="#bc1888"/>
                        </linearGradient>
                    </defs>
                    <rect width="24" height="24" rx="6" fill="url(#igEmptyGrad)"/>
                    <circle cx="12" cy="12" r="4.5" fill="none" stroke="#fff" stroke-width="1.8"/>
                    <circle cx="17.5" cy="6.5" r="1.2" fill="#fff"/>
                </svg>
                <p style="font-size:14px; margin:0;">Select a conversation to start</p>
            </div>

            {{-- Chat header --}}
            <div id="igChatHeader" style="
                display: none;
                padding: 12px 16px;
                border-bottom: 1px solid #e2e8f0;
                background: #fff;
                flex-shrink: 0;
            ">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div id="igChatAvatar" style="
                        width: 36px; height: 36px; border-radius: 50%;
                        background: linear-gradient(135deg,#f09433,#bc1888);
                        display: flex; align-items: center; justify-content: center;
                        color:#fff; font-weight:700; font-size:14px; flex-shrink:0;
                    ">?</div>
                    <div>
                        <div id="igChatName" style="font-weight:600; font-size:14px; color:#0f172a; line-height:1.3;"></div>
                        <div id="igChatUser" style="font-size:12px; color:#64748b;"></div>
                    </div>
                </div>
            </div>

            {{-- Messages area --}}
            <div id="igMessages" style="
                display: none;
                flex: 1;
                overflow-y: auto;
                padding: 16px;
                background: #f6f7f8;
            "></div>

            {{-- Reply box --}}
            <div id="igReplyBox" style="
                display: none;
                flex-shrink: 0;
                border-top: 1px solid #e2e8f0;
                padding: 10px 12px;
                background: #fff;
            ">
                <div style="display:flex; gap:8px; align-items:flex-end;">
                    <textarea id="igText" rows="2"
                              class="form-control"
                              placeholder="Type a message…"
                              style="resize:none; font-size:13px; border-radius:10px; border-color:#e2e8f0; line-height:1.5;"></textarea>
                    <button id="igSend" class="btn btn-primary"
                            style="height:58px; width:48px; border-radius:10px; flex-shrink:0; padding:0; display:flex; align-items:center; justify-content:center;">
                        <span class="material-icons" style="font-size:20px;">send</span>
                    </button>
                </div>
                <div id="igSendErr" class="text-danger mt-1" style="font-size:12px; display:none;"></div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes ig-spin { to { transform: rotate(360deg); } }

.ig-conv-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-bottom: 1px solid #f8fafc;
    cursor: pointer;
    transition: background .12s;
}
.ig-conv-item:hover  { background: #f8fafc; }
.ig-conv-item.active { background: #eff6ff; border-left: 3px solid #137fec; padding-left: 11px; }

.ig-conv-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, #f09433, #bc1888);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 700; font-size: 15px; flex-shrink: 0;
}

.ig-conv-info { flex: 1; min-width: 0; }
.ig-conv-name {
    font-size: 13px; font-weight: 600; color: #0f172a;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    line-height: 1.3;
}
.ig-conv-handle {
    font-size: 11px; color: #137fec;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    line-height: 1.3;
}
.ig-conv-preview {
    font-size: 12px; color: #64748b;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    margin-top: 1px;
}
.ig-conv-meta {
    display: flex; flex-direction: column; align-items: flex-end;
    gap: 4px; flex-shrink: 0;
}
.ig-conv-time { font-size: 10px; color: #94a3b8; }

/* Messages */
.ig-msg-row { display: flex; margin-bottom: 6px; align-items: flex-end; gap: 6px; }
.ig-msg-row.out { justify-content: flex-end; }
.ig-msg-row.in  { justify-content: flex-start; }

.ig-bubble {
    max-width: 65%; padding: 9px 13px; border-radius: 18px;
    font-size: 13.5px; line-height: 1.5; word-break: break-word;
}
.ig-msg-row.in  .ig-bubble {
    background: #ffffff;
    color: #0f172a;
    border-bottom-left-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,.07);
}
.ig-msg-row.out .ig-bubble {
    background: #137fec;
    color: #fff;
    border-bottom-right-radius: 5px;
    box-shadow: 0 1px 3px rgba(19,127,236,.25);
}

.ig-bubble-meta {
    font-size: 10.5px;
    margin-top: 4px;
    opacity: .65;
    line-height: 1.2;
}
.ig-msg-row.out .ig-bubble-meta { text-align: right; }

.ig-date-sep {
    text-align: center; font-size: 11px; color: #94a3b8;
    margin: 12px 0; display: flex; align-items: center; gap: 8px;
}
.ig-date-sep::before,
.ig-date-sep::after {
    content: ''; flex: 1; height: 1px; background: #e2e8f0;
}
</style>

<script>
(function () {
    'use strict';

    var CONV_URL      = @json($convUrl);
    var REPLY_TPL     = @json($replyUrlTpl);
    var READ_TPL      = @json($readUrlTpl);
    var MSGS_TPL      = @json($msgsUrlTpl);
    var CSRF          = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function u(tpl, id) { return tpl.replace('__CID__', id); }

    var activeId  = null;
    var lastMsgId = null;
    var convPoll  = null;
    var msgPoll   = null;
    var allConvs  = [];

    // ── Display name helper — server now sends display_name but keep fallback ──
    function displayName(c) {
        return c.display_name || c.sender_username && ('@' + c.sender_username)
            || (c.sender_name && /^\d{10,}$/.test(c.sender_name.trim())
                ? 'IG User \u2026' + c.sender_name.slice(-4)
                : c.sender_name)
            || 'Unknown';
    }

    // ── Fetch & render conversations ───────────────────────────────────────────

    function loadConversations() {
        fetch(CONV_URL, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) return;
                allConvs = data.conversations;
                renderConvList(allConvs);
                var badge = document.getElementById('igTotalBadge');
                if (data.total_unread > 0) {
                    badge.textContent  = data.total_unread;
                    badge.style.display = '';
                } else {
                    badge.style.display = 'none';
                }
            })
            .catch(function () {});
    }

    function renderConvList(convs) {
        var el = document.getElementById('igConvList');
        document.getElementById('igConvLoading').style.display = 'none';

        if (!convs.length) {
            el.innerHTML = '<div class="text-center py-5 text-muted" style="font-size:13px;">No conversations yet.</div>';
            return;
        }

        el.innerHTML = convs.map(function (c) {
            var name    = displayName(c);
            var initial = name.replace(/^@/, '').charAt(0).toUpperCase();
            // Show @handle as subtitle only when display_name is NOT already the @handle
            var handle  = (c.sender_username && c.display_name !== ('@' + c.sender_username))
                ? '<div class="ig-conv-handle">@' + esc(c.sender_username) + '</div>'
                : '';
            var preview = c.last_preview || '<em style="color:#94a3b8;">No messages</em>';
            var unread  = c.unread_count > 0
                ? '<span class="badge bg-danger" style="font-size:10px;">' + c.unread_count + '</span>'
                : '';

            return '<div class="ig-conv-item' + (activeId == c.id ? ' active' : '') + '" data-id="' + c.id + '">'
                + '<div class="ig-conv-avatar">' + initial + '</div>'
                + '<div class="ig-conv-info">'
                +   '<div class="ig-conv-name">' + esc(name) + '</div>'
                +   handle
                +   '<div class="ig-conv-preview">' + esc(preview) + '</div>'
                + '</div>'
                + '<div class="ig-conv-meta">'
                +   '<span class="ig-conv-time">' + esc(c.last_at || '') + '</span>'
                +   unread
                + '</div>'
                + '</div>';
        }).join('');

        el.querySelectorAll('.ig-conv-item').forEach(function (item) {
            item.addEventListener('click', function () {
                openConversation(parseInt(this.dataset.id));
            });
        });
    }

    // ── Open a conversation ────────────────────────────────────────────────────

    function openConversation(id) {
        activeId  = id;
        lastMsgId = null;
        clearInterval(msgPoll);

        document.querySelectorAll('.ig-conv-item').forEach(function (el) {
            el.classList.toggle('active', parseInt(el.dataset.id) === id);
        });

        document.getElementById('igEmpty').style.display       = 'none';
        document.getElementById('igChatHeader').style.display  = '';
        document.getElementById('igMessages').style.display    = '';
        document.getElementById('igReplyBox').style.display    = '';

        document.getElementById('igMessages').innerHTML =
            '<div class="text-center py-4 text-muted" style="font-size:13px;">'
            + '<span class="material-icons d-block mb-1" style="font-size:20px; animation:ig-spin 1s linear infinite; color:#94a3b8;">autorenew</span>'
            + 'Loading…</div>';

        // Mark read
        fetch(u(READ_TPL, id), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).catch(function () {});

        fetchMessages(false);
        msgPoll = setInterval(function () { fetchMessages(true); }, 5000);
    }

    // ── Fetch messages ─────────────────────────────────────────────────────────

    function fetchMessages(incremental) {
        var url = u(MSGS_TPL, activeId);
        if (incremental && lastMsgId) url += '?after=' + lastMsgId;

        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                if (!data.ok) return;

                // Update chat header — use server's display_name directly
                var dispName = data.display_name || data.sender_name || 'Unknown';
                document.getElementById('igChatName').textContent = dispName;
                // Show @username as subtitle only when display_name is not already the handle
                var subtitle = (data.sender_username && data.display_name !== ('@' + data.sender_username))
                    ? '@' + data.sender_username : '';
                document.getElementById('igChatUser').textContent = subtitle;
                document.getElementById('igChatAvatar').textContent =
                    dispName.replace(/^@/, '').charAt(0).toUpperCase();

                if (!data.messages.length) {
                    if (!incremental) {
                        document.getElementById('igMessages').innerHTML =
                            '<div class="text-center py-5 text-muted" style="font-size:13px;">No messages yet.</div>';
                    }
                    return;
                }

                if (incremental) {
                    appendMessages(data.messages);
                } else {
                    renderMessages(data.messages);
                }
            })
            .catch(function () {});
    }

    function renderMessages(msgs) {
        var el   = document.getElementById('igMessages');
        el.innerHTML = buildMessagesHtml(msgs);
        el.scrollTop = el.scrollHeight;
        if (msgs.length) lastMsgId = msgs[msgs.length - 1].id;
    }

    function appendMessages(msgs) {
        if (!msgs.length) return;
        var el       = document.getElementById('igMessages');
        var atBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 80;
        el.insertAdjacentHTML('beforeend', buildMessagesHtml(msgs));
        if (atBottom) el.scrollTop = el.scrollHeight;
        lastMsgId = msgs[msgs.length - 1].id;
    }

    function buildMessagesHtml(msgs) {
        var html     = '';
        var lastDate = null;

        msgs.forEach(function (m) {
            if (m.sent_date && m.sent_date !== lastDate) {
                html += '<div class="ig-date-sep">' + esc(m.sent_date) + '</div>';
                lastDate = m.sent_date;
            }
            var dir  = m.direction === 'outbound' ? 'out' : 'in';
            var meta = m.sent_at || '';
            if (m.direction === 'outbound' && m.sent_by) meta += ' · ' + esc(m.sent_by);

            html += '<div class="ig-msg-row ' + dir + '">'
                + '<div class="ig-bubble">'
                +   '<div>' + esc(m.body) + '</div>'
                +   '<div class="ig-bubble-meta">' + esc(meta) + '</div>'
                + '</div>'
                + '</div>';
        });

        return html;
    }

    // ── Send reply ─────────────────────────────────────────────────────────────

    document.getElementById('igSend').addEventListener('click', sendReply);
    document.getElementById('igText').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendReply(); }
    });

    function sendReply() {
        if (!activeId) return;
        var text = document.getElementById('igText').value.trim();
        if (!text) return;

        var btn = document.getElementById('igSend');
        var err = document.getElementById('igSendErr');
        btn.disabled      = true;
        err.style.display = 'none';

        fetch(u(REPLY_TPL, activeId), {
            method:  'POST',
            headers: {
                'Content-Type':     'application/json',
                'Accept':           'application/json',
                'X-CSRF-TOKEN':     CSRF,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ message: text }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled = false;
            if (data.ok) {
                document.getElementById('igText').value = '';
                appendMessages([data.message]);
            } else {
                err.textContent   = data.error || 'Failed to send.';
                err.style.display = '';
            }
        })
        .catch(function () {
            btn.disabled      = false;
            err.textContent   = 'Failed to send. Check your network connection.';
            err.style.display = '';
        });
    }

    // ── Search filter ──────────────────────────────────────────────────────────

    document.getElementById('igSearch').addEventListener('input', function () {
        var q = this.value.toLowerCase().trim();
        var filtered = q
            ? allConvs.filter(function (c) {
                return displayName(c).toLowerCase().includes(q) ||
                       (c.sender_username || '').toLowerCase().includes(q);
              })
            : allConvs;
        renderConvList(filtered);
    });

    // ── Utility ────────────────────────────────────────────────────────────────

    function esc(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Boot ───────────────────────────────────────────────────────────────────

    loadConversations();
    convPoll = setInterval(loadConversations, 10000);
})();
</script>
@endif
