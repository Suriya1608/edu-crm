@extends('layouts.manager.app')

@section('title', 'AI Assistant')

@section('content')

<style>
#agentWrap {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 116px);
    max-width: 860px;
    margin: 0 auto;
}
#chatMessages {
    flex: 1;
    overflow-y: auto;
    padding: 16px 4px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    scroll-behavior: smooth;
}
.msg-row { display: flex; align-items: flex-start; gap: 10px; }
.msg-row.user-row { flex-direction: row-reverse; }

.msg-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 18px;
}
.msg-avatar.ai-av  { background: linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; }
.msg-avatar.usr-av { background: #0f172a; color:#fff; font-size:13px; font-weight:700; }

.msg-bubble {
    max-width: 78%;
    padding: 11px 15px;
    border-radius: 14px;
    font-size: 14px;
    line-height: 1.6;
    word-break: break-word;
}
.msg-bubble.ai-bubble {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-top-left-radius: 4px;
    color: #0f172a;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.msg-bubble.user-bubble {
    background: #6366f1;
    color: #fff;
    border-top-right-radius: 4px;
}
.msg-bubble.error-bubble {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-top-left-radius: 4px;
    color: #dc2626;
}

/* Markdown inside AI bubbles */
.ai-bubble h1,.ai-bubble h2,.ai-bubble h3 { font-size:15px; font-weight:700; margin:10px 0 4px; }
.ai-bubble p  { margin: 0 0 8px; }
.ai-bubble p:last-child { margin-bottom: 0; }
.ai-bubble ul,.ai-bubble ol { margin: 4px 0 8px 18px; padding: 0; }
.ai-bubble li { margin-bottom: 3px; }
.ai-bubble strong { color: #0f172a; }
.ai-bubble code {
    background: #f1f5f9; border-radius: 4px;
    padding: 1px 5px; font-size: 12.5px; color: #6366f1;
}
.ai-bubble table { border-collapse: collapse; width: 100%; margin: 8px 0; font-size: 13px; }
.ai-bubble th { background: #f8fafc; color: #0f172a; font-weight:600; }
.ai-bubble td, .ai-bubble th { border: 1px solid #e2e8f0; padding: 6px 10px; text-align: left; }
.ai-bubble hr { border: none; border-top: 1px solid #e2e8f0; margin: 10px 0; }

/* Thinking indicator */
.thinking-dots span {
    display: inline-block;
    width: 7px; height: 7px;
    background: #94a3b8;
    border-radius: 50%;
    margin: 0 2px;
    animation: dotBounce 1.2s infinite ease-in-out;
}
.thinking-dots span:nth-child(2) { animation-delay: .2s; }
.thinking-dots span:nth-child(3) { animation-delay: .4s; }
@keyframes dotBounce {
    0%,80%,100% { transform: translateY(0); opacity:.4; }
    40%          { transform: translateY(-6px); opacity:1; }
}

/* Quick chip buttons */
.chip-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 13px; border-radius: 20px;
    border: 1px solid #e2e8f0; background: #fff;
    font-size: 12.5px; font-weight: 500; color: #334155;
    cursor: pointer; transition: all .15s; white-space: nowrap;
}
.chip-btn:hover {
    background: #6366f1; color: #fff;
    border-color: #6366f1; transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(99,102,241,.25);
}
.chip-btn .material-icons { font-size: 15px; }

/* Input area */
#chatInput {
    resize: none; border: 1.5px solid #e2e8f0; border-radius: 12px;
    padding: 10px 14px; font-size: 14px; font-family: inherit;
    line-height: 1.5; min-height: 46px; max-height: 120px;
    outline: none; transition: border-color .15s; flex: 1;
    background: #fff;
}
#chatInput:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.1); }
#chatInput:disabled { background: #f8fafc; color: #94a3b8; }

#sendBtn {
    width: 44px; height: 44px; border-radius: 12px; border: none;
    background: #6366f1; color: #fff; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background .15s, transform .1s; flex-shrink: 0;
}
#sendBtn:hover:not(:disabled)  { background: #4f46e5; transform: translateY(-1px); }
#sendBtn:disabled { background: #c7d2fe; cursor: not-allowed; transform: none; }

.input-wrap { display: flex; align-items: flex-end; gap: 8px; }
</style>

<div id="agentWrap">

    {{-- ── Header ── --}}
    <div class="d-flex align-items-center justify-content-between mb-3" style="flex-shrink:0;">
        <div class="d-flex align-items-center gap-3">
            <div style="width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;">
                <span class="material-icons" style="color:#fff;font-size:22px;">smart_toy</span>
            </div>
            <div>
                <h5 class="mb-0 fw-bold" style="color:#0f172a;">AI Assistant</h5>
                <p class="mb-0" style="font-size:12px;color:#64748b;">Powered by Claude · Manager mode</p>
            </div>
        </div>
        <button onclick="clearChat()" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1" style="font-size:12px;">
            <span class="material-icons" style="font-size:15px;">refresh</span> New chat
        </button>
    </div>

    {{-- ── Quick chips ── --}}
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;flex-shrink:0;">
        <button class="chip-btn" onclick="sendChip('Show me today\'s new leads')">
            <span class="material-icons">today</span>Today's Leads
        </button>
        <button class="chip-btn" onclick="sendChip('Show all unassigned leads')">
            <span class="material-icons">person_off</span>Unassigned
        </button>
        <button class="chip-btn" onclick="sendChip('Show leads with overdue follow-ups')">
            <span class="material-icons">schedule</span>Overdue Follow-ups
        </button>
        <button class="chip-btn" onclick="sendChip('Give me the lead pipeline overview for this month')">
            <span class="material-icons">bar_chart</span>Lead Insights
        </button>
        <button class="chip-btn" onclick="sendChip('Show telecaller performance summary')">
            <span class="material-icons">leaderboard</span>Telecaller Insights
        </button>
        <button class="chip-btn" onclick="sendChip('List all active telecallers and their online status')">
            <span class="material-icons">headset_mic</span>Telecallers
        </button>
        <button class="chip-btn" onclick="sendChip('Show today\'s follow-ups across the team')">
            <span class="material-icons">event_note</span>Today's Follow-ups
        </button>
        <button class="chip-btn" onclick="sendChip('Show overdue follow-ups across all telecallers')">
            <span class="material-icons">warning_amber</span>Team Overdue
        </button>
    </div>

    {{-- ── Messages ── --}}
    <div id="chatMessages">
        {{-- Welcome message --}}
        <div class="msg-row" id="welcomeMsg">
            <div class="msg-avatar ai-av">
                <span class="material-icons" style="font-size:18px;">smart_toy</span>
            </div>
            <div class="msg-bubble ai-bubble">
                <p style="margin:0 0 8px;font-weight:600;">👋 Hello, {{ auth()->user()->name }}!</p>
                <p style="margin:0 0 8px;">I'm your AI assistant for the manager panel. I can help you:</p>
                <ul style="margin:0 0 8px 18px;padding:0;">
                    <li>Filter and find leads (unassigned, today's, by status)</li>
                    <li>Assign or reassign leads to telecallers</li>
                    <li>Get telecaller performance insights</li>
                    <li>View lead pipeline analytics</li>
                    <li>Track follow-ups across the team</li>
                </ul>
                <p style="margin:0;">Try the quick chips above or type a question below.</p>
            </div>
        </div>
    </div>

    {{-- ── Input area ── --}}
    <div style="padding-top:12px;flex-shrink:0;border-top:1px solid #e2e8f0;">
        <div class="input-wrap">
            <textarea id="chatInput" placeholder="Ask anything… e.g. Assign Priya's lead to Ramesh" rows="1"></textarea>
            <button id="sendBtn" onclick="sendMessage()" title="Send (Enter)">
                <span class="material-icons" style="font-size:20px;">send</span>
            </button>
        </div>
        <p style="font-size:11px;color:#94a3b8;margin-top:6px;text-align:center;">
            AI can make mistakes. Verify critical actions before proceeding.
        </p>
    </div>

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
(function () {
    const CHAT_URL  = @json(route('manager.agent.chat'));
    const CSRF      = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const USER_INIT = @json(strtoupper(substr(auth()->user()->name, 0, 1)));
    const HISTORY_KEY = 'mgr_agent_history_{{ auth()->id() }}';

    marked.setOptions({ breaks: true, gfm: true });

    // ── History ──────────────────────────────────────────────────────────────
    let history = [];
    try {
        const saved = sessionStorage.getItem(HISTORY_KEY);
        if (saved) history = JSON.parse(saved);
    } catch (e) {}

    // Restore previous messages if any
    if (history.length > 0) {
        document.getElementById('welcomeMsg').style.display = 'none';
        history.forEach(function(h) {
            if (h.role === 'user')      appendUserBubble(h.content);
            else if (h.role === 'ai')   appendAiBubble(h.content);
        });
    }

    function saveHistory() {
        try { sessionStorage.setItem(HISTORY_KEY, JSON.stringify(history)); } catch (e) {}
    }

    // ── DOM helpers ──────────────────────────────────────────────────────────
    function scrollToBottom() {
        const el = document.getElementById('chatMessages');
        el.scrollTop = el.scrollHeight;
    }

    function appendUserBubble(text) {
        const box = document.getElementById('chatMessages');
        const row = document.createElement('div');
        row.className = 'msg-row user-row';
        row.innerHTML =
            '<div class="msg-avatar usr-av">' + escHtml(USER_INIT) + '</div>' +
            '<div class="msg-bubble user-bubble">' + escHtml(text) + '</div>';
        box.appendChild(row);
        scrollToBottom();
        return row;
    }

    function appendAiBubble(markdown, isError) {
        const box = document.getElementById('chatMessages');
        const row = document.createElement('div');
        row.className = 'msg-row';
        const cls = isError ? 'error-bubble' : 'ai-bubble';
        const icon = isError ? 'error_outline' : 'smart_toy';
        row.innerHTML =
            '<div class="msg-avatar ai-av">' +
            '<span class="material-icons" style="font-size:18px;">' + icon + '</span>' +
            '</div>' +
            '<div class="msg-bubble ' + cls + '">' +
            (isError ? escHtml(markdown) : marked.parse(markdown)) +
            '</div>';
        box.appendChild(row);
        scrollToBottom();
        return row;
    }

    function showThinking() {
        const box = document.getElementById('chatMessages');
        const row = document.createElement('div');
        row.className = 'msg-row';
        row.id = 'thinkingRow';
        row.innerHTML =
            '<div class="msg-avatar ai-av">' +
            '<span class="material-icons" style="font-size:18px;">smart_toy</span>' +
            '</div>' +
            '<div class="msg-bubble ai-bubble" style="padding:13px 16px;">' +
            '<div class="thinking-dots">' +
            '<span></span><span></span><span></span>' +
            '</div></div>';
        box.appendChild(row);
        scrollToBottom();
    }

    function removeThinking() {
        const el = document.getElementById('thinkingRow');
        if (el) el.remove();
    }

    function setLoading(on) {
        document.getElementById('chatInput').disabled = on;
        document.getElementById('sendBtn').disabled   = on;
    }

    function escHtml(str) {
        return String(str ?? '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Auto-resize textarea ──────────────────────────────────────────────────
    const textarea = document.getElementById('chatInput');
    textarea.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
    textarea.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // ── Send message ─────────────────────────────────────────────────────────
    window.sendMessage = async function () {
        const input = document.getElementById('chatInput');
        const msg   = input.value.trim();
        if (!msg) return;

        input.value = '';
        input.style.height = 'auto';

        appendUserBubble(msg);
        showThinking();
        setLoading(true);

        // Build API history (only user/assistant pairs)
        const apiHistory = history
            .filter(h => h.role === 'user' || h.role === 'assistant')
            .map(h => ({ role: h.role === 'ai' ? 'assistant' : h.role, content: h.content }));

        try {
            const res = await fetch(CHAT_URL, {
                method:  'POST',
                headers: {
                    'Content-Type':  'application/json',
                    'X-CSRF-TOKEN':  CSRF,
                    'Accept':        'application/json',
                },
                body: JSON.stringify({ message: msg, history: apiHistory }),
            });

            const data = await res.json();
            removeThinking();

            const isError = data.type === 'error';
            appendAiBubble(data.message ?? 'No response.', isError);

            // Update history
            history.push({ role: 'user',      content: msg });
            history.push({ role: isError ? 'error' : 'ai', content: data.message ?? '' });

            if (!isError && data.assistant_message) {
                // Replace last history entry with proper assistant role for API continuity
                history[history.length - 1].role = 'ai';
                history[history.length - 1]._api = data.assistant_message;
            }

            // Trim history to last 20 entries
            if (history.length > 20) history = history.slice(history.length - 20);
            saveHistory();

        } catch (err) {
            removeThinking();
            appendAiBubble('Network error. Please check your connection and try again.', true);
        } finally {
            setLoading(false);
            document.getElementById('chatInput').focus();
        }
    };

    window.sendChip = function (text) {
        const input = document.getElementById('chatInput');
        input.value = text;
        sendMessage();
    };

    window.clearChat = function () {
        history = [];
        saveHistory();
        const box = document.getElementById('chatMessages');
        box.innerHTML = '';
        // Re-add welcome message
        const welcome = document.createElement('div');
        welcome.className = 'msg-row';
        welcome.id = 'welcomeMsg';
        welcome.innerHTML =
            '<div class="msg-avatar ai-av">' +
            '<span class="material-icons" style="font-size:18px;">smart_toy</span>' +
            '</div>' +
            '<div class="msg-bubble ai-bubble">' +
            '<p style="margin:0 0 8px;font-weight:600;">Chat cleared. How can I help you?</p>' +
            '<p style="margin:0;">Use the quick chips above or type a question below.</p>' +
            '</div>';
        box.appendChild(welcome);
        document.getElementById('chatInput').focus();
    };

    // Focus input on load
    document.getElementById('chatInput').focus();
    scrollToBottom();
})();
</script>
@endpush

@endsection
