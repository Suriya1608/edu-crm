/**
 * ChatWidget — Global AI assistant (Claude-powered, agentic tool use)
 *
 * Mounted ONCE in inertia-app.jsx alongside SipProvider so it persists
 * across all Inertia page navigations without remounting or losing state.
 * Chat history is also synced to localStorage so it survives hard refreshes.
 */

import { useState, useEffect, useRef, useCallback } from 'react';

// ─── localStorage helpers ─────────────────────────────────────────────────────
const STORAGE_KEYS = {
    telecaller: 'crm_chat_state_v2',
    manager:    'crm_chat_state_manager_v1',
};

function storageKey(role) {
    return STORAGE_KEYS[role] ?? STORAGE_KEYS.telecaller;
}

function loadState(role) {
    try {
        const raw = localStorage.getItem(storageKey(role));
        if (!raw) return null;
        return JSON.parse(raw);
    } catch (_) {
        return null;
    }
}

function saveState(messages, apiHistory, role) {
    try {
        localStorage.setItem(storageKey(role), JSON.stringify({
            messages:   messages.slice(-40),
            apiHistory: apiHistory.slice(-12),
        }));
    } catch (_) {}
}

// ─── Design tokens ────────────────────────────────────────────────────────────
const glass = (opacity = 0.92, blur = 24) => ({
    background:              `rgba(255,255,255,${opacity})`,
    backdropFilter:          `blur(${blur}px)`,
    WebkitBackdropFilter:    `blur(${blur}px)`,
    border:                  '1px solid rgba(255,255,255,0.65)',
});

function bubbleColors(type) {
    if (type === 'error') return { bg: 'rgba(244,63,94,0.08)',   border: 'rgba(244,63,94,0.2)'   };
    return                       { bg: 'rgba(248,250,252,0.95)', border: 'rgba(226,232,240,0.65)' };
}

// ─── Markdown-lite renderer (supports **bold** and newlines) ──────────────────
function Md({ text }) {
    return text.split('\n').map((line, i, arr) => {
        const segs = line.split(/\*\*(.*?)\*\*/g);
        return (
            <span key={i}>
                {segs.map((s, j) => j % 2 === 1 ? <strong key={j}>{s}</strong> : s)}
                {i < arr.length - 1 && <br />}
            </span>
        );
    });
}

// ─── Role-based config ────────────────────────────────────────────────────────
const ROLE_CONFIG = {
    telecaller: {
        endpoint: '/telecaller/agent/chat',
        welcome: "Hi! I'm your **CRM Assistant**.\n\nI can schedule **Google Meet** or **Zoom**, set follow-ups, update lead status, and more.\n\nTry: *\"Schedule a Google Meet with **Ravi** tomorrow at 3:30pm\"*",
        suggestions: [
            { text: 'Schedule a Google Meet with [name] tomorrow at 3pm', icon: 'video_call'     },
            { text: 'Schedule Zoom with [name] on Friday at 10am',         icon: 'videocam'       },
            { text: 'Cancel meeting with [name]',                          icon: 'event_busy'     },
            { text: 'Follow up with [name] on Thursday at 2pm',            icon: 'calendar_month' },
            { text: 'Mark [name] as interested',                           icon: 'check_circle'   },
            { text: 'Show my overdue follow-ups',                          icon: 'event'          },
        ],
    },
    manager: {
        endpoint: '/manager/agent/chat',
        welcome: "Hi! I'm your **CRM Assistant**.\n\nI can give you team insights, assign leads, show pipeline stats, and more.\n\nTry: *\"Give me today's briefing\"* or *\"Assign **Ravi** to Jayasurriya\"*",
        suggestions: [
            { text: "Give me today's briefing",          icon: 'today'           },
            { text: 'Show unassigned leads',             icon: 'person_off'      },
            { text: 'Show today\'s new leads',           icon: 'fiber_new'       },
            { text: 'Telecaller performance summary',    icon: 'leaderboard'     },
            { text: 'Lead pipeline overview this month', icon: 'bar_chart'       },
            { text: 'Show overdue follow-ups',           icon: 'event_busy'      },
        ],
    },
};

const THINK_LABELS = ['Thinking…', 'Searching leads…', 'Calling tools…', 'Almost done…'];

// ─── ChatWidget ───────────────────────────────────────────────────────────────
export default function ChatWidget({ userRole }) {
    const role   = ROLE_CONFIG[userRole] ? userRole : null;
    const config = role ? ROLE_CONFIG[role] : null;

    // Only show for telecallers and managers
    if (!config) return null;

    const WELCOME_MSG = { role: 'agent', icon: 'smart_toy', type: 'help', text: config.welcome };

    const saved = loadState(role);

    const [open,       setOpen]       = useState(false);
    const [input,      setInput]      = useState('');
    const [loading,    setLoading]    = useState(false);
    const [thinkIdx,   setThinkIdx]   = useState(0);
    const [messages,   setMessages]   = useState(saved?.messages   ?? [WELCOME_MSG]);
    const [apiHistory, setApiHistory] = useState(saved?.apiHistory ?? []);

    const bottomRef  = useRef(null);
    const inputRef   = useRef(null);
    const [headerH, setHeaderH] = useState(74);

    // Persist to localStorage whenever messages or history change
    useEffect(() => {
        saveState(messages, apiHistory, role);
    }, [messages, apiHistory]);

    // Auto-scroll to latest message
    useEffect(() => {
        if (open) {
            setTimeout(() => {
                bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
            }, 60);
        }
    }, [open, messages, loading]);

    // Focus input when panel opens
    useEffect(() => {
        if (open) setTimeout(() => inputRef.current?.focus(), 120);
    }, [open]);

    // Track actual header height so panel never overlaps it
    useEffect(() => {
        const measure = () => {
            const h = document.querySelector('.top-header');
            if (h) setHeaderH(h.getBoundingClientRect().height + 4);
        };
        measure();
        const obs = new ResizeObserver(measure);
        const el = document.querySelector('.top-header');
        if (el) obs.observe(el);
        return () => obs.disconnect();
    }, []);

    // Cycle thinking label while agent is working
    useEffect(() => {
        if (!loading) { setThinkIdx(0); return; }
        const t = setInterval(() => setThinkIdx(i => (i + 1) % THINK_LABELS.length), 1800);
        return () => clearInterval(t);
    }, [loading]);

    // ── Clear chat ────────────────────────────────────────────────────────────
    const clearChat = useCallback(() => {
        setMessages([WELCOME_MSG]);
        setApiHistory([]);
        localStorage.removeItem(storageKey(role));
    }, []);

    // ── Send message ──────────────────────────────────────────────────────────
    const send = useCallback(async (override) => {
        const msg = (override ?? input).trim();
        if (!msg || loading) return;

        setInput('');
        setMessages(prev => [...prev, { role: 'user', text: msg }]);
        setLoading(true);

        try {
            const csrf    = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const history = apiHistory.slice(-8);

            const res  = await fetch(config.endpoint, {
                method:  'POST',
                headers: {
                    'Content-Type':  'application/json',
                    'Accept':        'application/json',
                    'X-CSRF-TOKEN':  csrf,
                },
                body: JSON.stringify({ message: msg, history }),
            });

            const data      = await res.json();
            const replyText = data.message ?? 'Sorry, something went wrong.';

            setMessages(prev => [...prev, {
                role: 'agent',
                icon: data.type === 'error' ? 'error_outline' : 'smart_toy',
                type: data.type ?? 'ai',
                text: replyText,
            }]);

            setApiHistory(prev => [
                ...prev,
                { role: 'user',      content: msg       },
                { role: 'assistant', content: replyText },
            ]);
        } catch (_) {
            setMessages(prev => [...prev, {
                role: 'agent', icon: 'cloud_off', type: 'error',
                text: 'Connection error — check your network and try again.',
            }]);
        }
        setLoading(false);
    }, [input, loading, apiHistory]);

    const unread = !open && messages.length > 1 &&
        messages[messages.length - 1].role === 'agent';

    return (
        <>
            {/* ── Chat panel ───────────────────────────────────────────────── */}
            {open && (
                <div style={{
                    position: 'fixed', top: headerH, bottom: 158, right: 24, zIndex: 9999,
                    width: 378, maxWidth: 'calc(100vw - 40px)',
                    ...glass(0.96, 28),
                    borderRadius: 28,
                    boxShadow: '0 32px 80px rgba(99,102,241,0.22), 0 8px 28px rgba(0,0,0,0.1)',
                    display: 'flex', flexDirection: 'column',
                    overflow: 'hidden',
                }}>

                    {/* Header */}
                    <div style={{
                        background: 'linear-gradient(135deg,#4f46e5 0%,#6366f1 55%,#8b5cf6 100%)',
                        padding: '15px 16px',
                        display: 'flex', alignItems: 'center', gap: 10,
                        position: 'relative', overflow: 'hidden', flexShrink: 0,
                    }}>
                        {/* Decorative blob */}
                        <div style={{ position:'absolute', top:-24, right:-12, width:88, height:88, borderRadius:'50%', background:'rgba(255,255,255,0.07)', pointerEvents:'none' }} />

                        {/* Avatar */}
                        <div style={{ width:36, height:36, borderRadius:12, background:'rgba(255,255,255,0.18)', display:'flex', alignItems:'center', justifyContent:'center', flexShrink:0 }}>
                            <span className="material-icons" style={{ fontSize:18, color:'#fff' }}>smart_toy</span>
                        </div>

                        {/* Title */}
                        <div style={{ flex:1, minWidth:0 }}>
                            <div style={{ fontSize:13, fontWeight:700, color:'#fff', lineHeight:1.2 }}>CRM Assistant</div>
                            <div style={{ fontSize:10.5, color:'rgba(255,255,255,0.7)', marginTop:2, display:'flex', alignItems:'center', gap:5 }}>
                                <span style={{ width:6, height:6, borderRadius:'50%', background:'#4ade80', display:'inline-block', flexShrink:0 }} />
                                Online
                            </div>
                        </div>

                        {/* Clear button */}
                        <button
                            onClick={clearChat}
                            title="Clear chat history"
                            style={{ width:28, height:28, borderRadius:8, background:'rgba(255,255,255,0.15)', border:'1px solid rgba(255,255,255,0.2)', cursor:'pointer', display:'flex', alignItems:'center', justifyContent:'center', flexShrink:0, marginRight:4 }}
                        >
                            <span className="material-icons" style={{ fontSize:14, color:'#fff' }}>delete_sweep</span>
                        </button>

                        {/* Close button */}
                        <button
                            onClick={() => setOpen(false)}
                            title="Close"
                            style={{ width:28, height:28, borderRadius:8, background:'rgba(255,255,255,0.15)', border:'1px solid rgba(255,255,255,0.2)', cursor:'pointer', display:'flex', alignItems:'center', justifyContent:'center', flexShrink:0 }}
                        >
                            <span className="material-icons" style={{ fontSize:15, color:'#fff' }}>close</span>
                        </button>
                    </div>

                    {/* Messages */}
                    <div style={{ flex:1, overflowY:'auto', padding:'14px 14px 6px', display:'flex', flexDirection:'column', gap:10, minHeight:0 }}>
                        {messages.map((msg, i) => {
                            const isUser = msg.role === 'user';
                            const c      = isUser ? null : bubbleColors(msg.type);
                            return (
                                <div key={i} style={{ display:'flex', justifyContent:isUser ? 'flex-end' : 'flex-start', gap:7, alignItems:'flex-end' }}>
                                    {!isUser && (
                                        <div style={{ width:28, height:28, borderRadius:9, flexShrink:0, background:'linear-gradient(135deg,#6366f1,#4f46e5)', display:'flex', alignItems:'center', justifyContent:'center' }}>
                                            <span className="material-icons" style={{ fontSize:14, color:'#fff' }}>{msg.icon ?? 'smart_toy'}</span>
                                        </div>
                                    )}
                                    <div style={{
                                        maxWidth: '80%',
                                        background:   isUser ? 'linear-gradient(135deg,#6366f1,#4f46e5)' : c.bg,
                                        border:       isUser ? 'none' : `1px solid ${c.border}`,
                                        borderRadius: isUser ? '18px 18px 4px 18px' : '4px 18px 18px 18px',
                                        padding:      '10px 13px',
                                        fontSize:     12.5, lineHeight: 1.65,
                                        color:        isUser ? '#fff' : '#1e293b',
                                        boxShadow:    isUser ? '0 4px 14px rgba(99,102,241,0.28)' : 'none',
                                        wordBreak:    'break-word',
                                    }}>
                                        <Md text={msg.text} />
                                    </div>
                                </div>
                            );
                        })}

                        {/* Thinking indicator */}
                        {loading && (
                            <div style={{ display:'flex', gap:7, alignItems:'flex-end' }}>
                                <div style={{ width:28, height:28, borderRadius:9, background:'linear-gradient(135deg,#6366f1,#4f46e5)', display:'flex', alignItems:'center', justifyContent:'center', flexShrink:0 }}>
                                    <span className="material-icons" style={{ fontSize:14, color:'#fff', animation:'cwSpin 1.8s linear infinite' }}>autorenew</span>
                                </div>
                                <div style={{ background:'rgba(248,250,252,0.96)', border:'1px solid rgba(226,232,240,0.7)', borderRadius:'4px 18px 18px 18px', padding:'10px 14px', display:'flex', gap:8, alignItems:'center' }}>
                                    <div style={{ display:'flex', gap:4 }}>
                                        {[0,1,2].map(d => (
                                            <span key={d} style={{ width:6, height:6, borderRadius:'50%', background:'#6366f1', display:'inline-block', animation:`cwDot 1.2s ${d*0.2}s ease-in-out infinite` }} />
                                        ))}
                                    </div>
                                    <span style={{ fontSize:11, color:'#6366f1', fontWeight:600 }}>{THINK_LABELS[thinkIdx]}</span>
                                </div>
                            </div>
                        )}
                        <div ref={bottomRef} />
                    </div>

                    {/* Suggestion chips — only on first message */}
                    {messages.length <= 1 && (
                        <div style={{ padding:'4px 12px 8px', display:'flex', gap:5, flexWrap:'wrap' }}>
                            {config.suggestions.map(s => (
                                <button key={s.text} onClick={() => { setInput(s.text); setTimeout(() => inputRef.current?.focus(), 50); }} style={{
                                    display:'flex', alignItems:'center', gap:4,
                                    background:'rgba(99,102,241,0.07)', border:'1px solid rgba(99,102,241,0.16)',
                                    borderRadius:20, padding:'4px 10px',
                                    fontSize:10.5, fontWeight:600, color:'#4f46e5', cursor:'pointer',
                                }}>
                                    <span className="material-icons" style={{ fontSize:12 }}>{s.icon}</span>
                                    {s.text.length > 32 ? s.text.slice(0, 32) + '…' : s.text}
                                </button>
                            ))}
                        </div>
                    )}

                    {/* Input bar */}
                    <div style={{ padding:'10px 12px', borderTop:'1px solid rgba(226,232,240,0.5)', display:'flex', gap:8, alignItems:'center', background:'rgba(255,255,255,0.65)', flexShrink:0 }}>
                        <input
                            ref={inputRef}
                            value={input}
                            onChange={e => setInput(e.target.value)}
                            onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } }}
                            placeholder="Ask me anything…"
                            disabled={loading}
                            style={{ flex:1, border:`1.5px solid ${input.trim() ? 'rgba(99,102,241,0.35)' : 'rgba(99,102,241,0.2)'}`, borderRadius:14, padding:'9px 14px', fontSize:12.5, color:'#0f172a', background:'rgba(255,255,255,0.9)', outline:'none', transition:'border-color 0.2s' }}
                        />
                        <button
                            onClick={() => send()}
                            disabled={!input.trim() || loading}
                            style={{
                                width:38, height:38, borderRadius:12, flexShrink:0,
                                background: input.trim() && !loading ? 'linear-gradient(135deg,#6366f1,#4f46e5)' : 'rgba(226,232,240,0.7)',
                                border:'none',
                                cursor: input.trim() && !loading ? 'pointer' : 'default',
                                display:'flex', alignItems:'center', justifyContent:'center',
                                boxShadow: input.trim() && !loading ? '0 4px 12px rgba(99,102,241,0.35)' : 'none',
                                transition:'all 0.2s',
                            }}
                        >
                            <span className="material-icons" style={{ fontSize:17, color: input.trim() && !loading ? '#fff' : '#94a3b8' }}>send</span>
                        </button>
                    </div>
                </div>
            )}

            {/* ── FAB button ───────────────────────────────────────────────── */}
            <button
                onClick={() => setOpen(o => !o)}
                title={open ? 'Close assistant' : 'Open CRM Assistant'}
                style={{
                    position: 'fixed', bottom: 90, right: 24, zIndex: 10000,
                    width: 56, height: 56, borderRadius: '50%',
                    background: open
                        ? 'linear-gradient(135deg,#4f46e5,#6366f1)'
                        : 'linear-gradient(135deg,#6366f1,#8b5cf6)',
                    border: 'none', cursor: 'pointer',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    boxShadow: '0 8px 28px rgba(99,102,241,0.45)',
                    transition: 'all 0.25s cubic-bezier(.4,0,.2,1)',
                    transform: open ? 'rotate(90deg) scale(1.05)' : 'rotate(0deg) scale(1)',
                }}
            >
                <span className="material-icons" style={{ fontSize:24, color:'#fff' }}>
                    {open ? 'close' : 'smart_toy'}
                </span>
                {/* Unread dot */}
                {unread && !open && (
                    <span style={{
                        position:'absolute', top:10, right:10,
                        width:10, height:10, borderRadius:'50%',
                        background:'#f43f5e', border:'2px solid #fff',
                        animation: 'cwPulse 1.5s ease-in-out infinite',
                    }} />
                )}
            </button>

            {/* ── Keyframes ────────────────────────────────────────────────── */}
            <style>{`
                @keyframes cwSpin   { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }
                @keyframes cwDot    { 0%,60%,100%{transform:translateY(0);opacity:.4} 30%{transform:translateY(-5px);opacity:1} }
                @keyframes cwPulse  { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.3);opacity:.7} }
            `}</style>
        </>
    );
}
