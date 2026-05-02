import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, useEffect, useRef, useCallback } from 'react';

// ─── helpers ──────────────────────────────────────────────────────────────────
const STATUS_LABELS = {
    new: 'New', contacted: 'Contacted', interested: 'Interested',
    follow_up: 'Follow-up', not_interested: 'Not Interested',
    converted: 'Converted', lost: 'Lost',
};

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function now12h() {
    return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Plays a two-tone chime using Web Audio API
function playChime() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        [[1100, 0], [880, 0.18]].forEach(([freq, delay]) => {
            const osc  = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain); gain.connect(ctx.destination);
            osc.type = 'sine'; osc.frequency.value = freq;
            const t = ctx.currentTime + delay;
            gain.gain.setValueAtTime(0, t);
            gain.gain.linearRampToValueAtTime(0.3, t + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.001, t + 0.22);
            osc.start(t); osc.stop(t + 0.22);
        });
    } catch (_) {}
}

// ─── ProfileCard ──────────────────────────────────────────────────────────────
function ProfileCard({ lead }) {
    const details = [
        { icon: 'phone',  label: 'Phone',          value: lead.phone },
        { icon: 'mail',   label: 'Email',           value: lead.email   || '—' },
        { icon: 'school', label: 'Course Applied',  value: lead.course  || '—' },
        { icon: 'person', label: 'Assigned By',     value: lead.assigned_by || '—', green: true },
    ];
    return (
        <div className="profile-card mb-4">
            <div className="profile-header">
                <div className="profile-info">
                    <h1 className="profile-name">{lead.name}</h1>
                    <span className="status-badge hot-lead">{lead.status?.toUpperCase()}</span>
                    <p className="profile-id">ID: {lead.lead_code}</p>
                </div>
            </div>
            <div className="profile-details">
                {details.map(d => (
                    <div className="detail-item" key={d.label}>
                        <span className="material-icons">{d.icon}</span>
                        <div className="flex-grow-1">
                            <p className="detail-label">{d.label}</p>
                            <p className={`detail-value${d.green ? ' text-success' : ''}`}>{d.value}</p>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

// ─── WaChat ───────────────────────────────────────────────────────────────────
function WaChat({ lead, initialMessages, templateName, urls, initialSessionActive }) {
    const chatBodyRef   = useRef(null);
    const lastIdRef     = useRef(
        initialMessages.length ? Math.max(...initialMessages.map(m => m.id)) : 0
    );
    const fileInputRef  = useRef(null);

    const [messages,       setMessages]       = useState(initialMessages);
    const [text,           setText]           = useState('');
    const [pendingFile,    setPendingFile]     = useState(null);
    const [sending,        setSending]         = useState(false);
    const [waToasts,       setWaToasts]        = useState([]);
    const [sessionActive,  setSessionActive]   = useState(initialSessionActive ?? false);
    const [templateSent,   setTemplateSent]    = useState(false);

    // scroll to bottom whenever messages change
    useEffect(() => {
        if (chatBodyRef.current) {
            chatBodyRef.current.scrollTop = chatBodyRef.current.scrollHeight;
        }
    }, [messages]);

    // ── polling ────────────────────────────────────────────────────────────────
    const poll = useCallback(async () => {
        try {
            const res  = await fetch(`${urls.wa_fetch}?after=${lastIdRef.current}`,
                { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();

            if (data.session_active !== undefined) {
                setSessionActive(data.session_active);
                if (data.session_active) setTemplateSent(false);
            }
            if (data.messages?.length) {
                const fresh = data.messages.filter(m => m.id > lastIdRef.current);
                if (fresh.length) {
                    setMessages(prev => [...prev, ...fresh]);
                    lastIdRef.current = Math.max(...fresh.map(m => m.id));
                    const inbound = fresh.filter(m => m.direction === 'inbound').length;
                    if (inbound) {
                        playChime();
                        addToast(inbound > 1 ? `${inbound} new messages` : 'New WhatsApp message', '#25D366');
                        setTemplateSent(false);
                    }
                }
            }
            if (data.statuses) {
                setMessages(prev => prev.map(m => {
                    const s = data.statuses[m.id];
                    return s ? { ...m, status: s } : m;
                }));
            }
        } catch (_) {}
    }, [urls.wa_fetch]);

    useEffect(() => {
        const t = setInterval(poll, 15_000);
        return () => clearInterval(t);
    }, [poll]);

    // ── toast helper ──────────────────────────────────────────────────────────
    function addToast(msg, color) {
        const id = Date.now();
        setWaToasts(prev => [...prev, { id, msg, color }]);
        setTimeout(() => setWaToasts(prev => prev.filter(t => t.id !== id)), 5000);
    }

    // ── file attachment ────────────────────────────────────────────────────────
    function handleFileChange(e) {
        const f = e.target.files[0];
        if (!f) return;
        setPendingFile(f);
    }
    function clearFile() {
        setPendingFile(null);
        if (fileInputRef.current) fileInputRef.current.value = '';
    }

    // ── send text ─────────────────────────────────────────────────────────────
    async function handleSubmit(e) {
        e.preventDefault();
        if (pendingFile) { await sendMedia(); return; }
        const body = text.trim();
        if (!body) return;
        setSending(true);
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const res  = await fetch(urls.wa_store, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body: JSON.stringify({ message: body }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) { addToast(data.message || 'Send failed', '#ef4444'); return; }
            if (data.session_active !== undefined) setSessionActive(data.session_active);
            setText('');
            const newMsg = { id: data.message_id, body: data.message || body,
                direction: 'outbound', time: data.time || now12h(), status: 'sent' };
            setMessages(prev => [...prev, newMsg]);
            if (data.message_id > lastIdRef.current) lastIdRef.current = data.message_id;
            addToast('Message sent', '#6366f1');
        } catch (err) {
            addToast(err.message || 'Network error', '#ef4444');
        } finally {
            setSending(false);
        }
    }

    // ── send media ────────────────────────────────────────────────────────────
    async function sendMedia() {
        if (!pendingFile) return;
        setSending(true);
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const fd   = new FormData();
            fd.append('_token', csrf);
            fd.append('file', pendingFile);
            if (text.trim()) fd.append('caption', text.trim());
            const res  = await fetch(urls.wa_media, { method: 'POST', headers: { Accept: 'application/json' }, body: fd });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) { addToast(data.message || 'Upload failed', '#ef4444'); return; }
            clearFile(); setText('');
            const newMsg = { id: data.message_id, body: data.message, direction: 'outbound',
                time: data.time || now12h(), status: 'sent',
                media_type: data.media_type, media_url: data.media_url, media_filename: data.media_filename };
            setMessages(prev => [...prev, newMsg]);
            if (data.message_id > lastIdRef.current) lastIdRef.current = data.message_id;
            addToast('Media sent', '#6366f1');
        } catch (err) {
            addToast(err.message || 'Upload failed', '#ef4444');
        } finally {
            setSending(false);
        }
    }

    // ── direct template send ───────────────────────────────────────────────────
    async function sendTemplate() {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const displayBody = `Hello ${lead.name}, thank you for your interest in our programs!`;
        try {
            const res  = await fetch(urls.wa_template, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body: JSON.stringify({ template_name: templateName, params: [lead.name], display_body: displayBody }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) { addToast(data.message || 'Template failed', '#ef4444'); return; }
            const newMsg = { id: data.message_id, body: data.message || displayBody,
                direction: 'outbound', time: data.time || now12h(), status: 'sent' };
            setMessages(prev => [...prev, newMsg]);
            if (data.message_id > lastIdRef.current) lastIdRef.current = data.message_id;
            setTemplateSent(true);
            addToast('Welcome template sent — waiting for lead to reply', '#6366f1');
        } catch (err) {
            addToast(err.message || 'Network error', '#ef4444');
        }
    }

    const fileLabel = pendingFile
        ? (pendingFile.size < 1_048_576
            ? `${pendingFile.name} (${(pendingFile.size / 1024).toFixed(1)} KB)`
            : `${pendingFile.name} (${(pendingFile.size / 1_048_576).toFixed(1)} MB)`)
        : null;

    return (
        <div className="card border-0 shadow-sm mb-4">
            <div className="card-body p-0">
                <div className="wa-chat-window">

                    {/* header */}
                    <div className="wa-chat-header">
                        <div className="wa-user-block">
                            <div className="wa-avatar">{lead.name.charAt(0).toUpperCase()}</div>
                            <div>
                                <h6 className="mb-0">{lead.name}</h6>
                                <small>Meta WhatsApp</small>
                            </div>
                        </div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            {sessionActive ? (
                                <span style={{ fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 20,
                                    background: '#dcfce7', color: '#15803d',
                                    display: 'flex', alignItems: 'center', gap: 4 }}>
                                    <span style={{ width: 7, height: 7, borderRadius: '50%', background: '#16a34a', display: 'inline-block' }}></span>
                                    24h session active
                                </span>
                            ) : templateSent ? (
                                <span style={{ fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 20,
                                    background: '#eef2ff', color: '#6366f1',
                                    display: 'flex', alignItems: 'center', gap: 4 }}>
                                    <span style={{ width: 7, height: 7, borderRadius: '50%', background: '#6366f1', display: 'inline-block' }}></span>
                                    Awaiting reply
                                </span>
                            ) : (
                                <span style={{ fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 20,
                                    background: '#fef9c3', color: '#854d0e',
                                    display: 'flex', alignItems: 'center', gap: 4 }}>
                                    <span style={{ width: 7, height: 7, borderRadius: '50%', background: '#f59e0b', display: 'inline-block' }}></span>
                                    No active session
                                </span>
                            )}
                            <span className="wa-live-dot"></span>
                        </div>
                    </div>

                    {/* Session banner */}
                    {!sessionActive && !templateSent && (
                        <div style={{
                            background: '#fffbeb', borderBottom: '1px solid #fde68a',
                            padding: '7px 14px', fontSize: 12, color: '#92400e',
                            display: 'flex', alignItems: 'center', gap: 6,
                        }}>
                            <span className="material-icons" style={{ fontSize: 14, color: '#f59e0b' }}>info</span>
                            <span>
                                <strong>No active session</strong> — the lead hasn't messaged you yet.
                                Click <strong>Welcome</strong> below to send an opening template.
                            </span>
                        </div>
                    )}
                    {!sessionActive && templateSent && (
                        <div style={{
                            background: '#eef2ff', borderBottom: '1px solid #c7d2fe',
                            padding: '7px 14px', fontSize: 12, color: '#4338ca',
                            display: 'flex', alignItems: 'center', gap: 6,
                        }}>
                            <span className="material-icons" style={{ fontSize: 14, color: '#6366f1' }}>schedule_send</span>
                            <span>
                                <strong>Welcome template sent.</strong> Once the lead replies, a 24h session opens and you can send any message freely. No need to send it again.
                            </span>
                        </div>
                    )}

                    {/* messages */}
                    <div id="waChatBody" className="wa-chat-body" ref={chatBodyRef}>
                        {messages.length === 0 && (
                            <div className="wa-message wa-incoming">
                                <p className="mb-1">No WhatsApp messages yet for this lead.</p>
                                <small>Start the conversation below</small>
                            </div>
                        )}
                        {messages.map(m => <WaBubble key={m.id} msg={m} />)}
                    </div>

                    {/* footer */}
                    <div className="wa-chat-footer">
                        {/* quick reply buttons */}
                        <div className="wa-template-row">
                            <button type="button" className="wa-template-btn" onClick={sendTemplate}>
                                ✅ Welcome
                            </button>
                            <button type="button" className="wa-template-btn"
                                onClick={() => setText('Reminder: your follow-up is scheduled. Please confirm your preferred time.')}>
                                Follow-up
                            </button>
                            <button type="button" className="wa-template-btn"
                                onClick={() => setText('Please share your preferred course and we will guide you with next steps.')}>
                                Course Info
                            </button>
                        </div>

                        {/* file preview */}
                        {pendingFile && (
                            <div style={{ display: 'flex', alignItems: 'center', gap: 8, background: '#f0f9ff',
                                border: '1.5px solid #bae6fd', borderRadius: 8, padding: '6px 10px',
                                marginBottom: 6, fontSize: 12 }}>
                                <span className="material-icons" style={{ color: '#6366f1', fontSize: 18 }}>attach_file</span>
                                <span style={{ flex: 1, fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                    {fileLabel}
                                </span>
                                <button type="button" onClick={clearFile}
                                    style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#ef4444', padding: 0, display: 'flex' }}>
                                    <span className="material-icons" style={{ fontSize: 16 }}>close</span>
                                </button>
                            </div>
                        )}

                        {/* composer */}
                        <form className="wa-composer-form" onSubmit={handleSubmit}>
                            <input type="file" ref={fileInputRef} style={{ display: 'none' }}
                                accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip"
                                onChange={handleFileChange} />
                            <button type="button" onClick={() => fileInputRef.current?.click()}
                                style={{ background: '#f1f5f9', border: '1.5px solid #e2e8f0', borderRadius: '50%',
                                    width: 38, height: 38, display: 'flex', alignItems: 'center',
                                    justifyContent: 'center', cursor: 'pointer', flexShrink: 0 }}
                                title="Attach file">
                                <span className="material-icons" style={{ fontSize: 18, color: '#64748b' }}>attach_file</span>
                            </button>
                            <input className="form-control" type="text" autoComplete="off"
                                placeholder={pendingFile ? 'Add a caption (optional)…' : 'Type a WhatsApp message...'}
                                value={text} onChange={e => setText(e.target.value)} />
                            <button type="submit" className="btn btn-success" disabled={sending}>
                                {sending
                                    ? <span className="spinner-border spinner-border-sm"></span>
                                    : <span className="material-icons">send</span>
                                }
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {/* in-chat toast stack */}
            {waToasts.length > 0 && (
                <div style={{ position: 'absolute', bottom: 80, right: 12, zIndex: 10, pointerEvents: 'none', display: 'flex', flexDirection: 'column', gap: 6 }}>
                    {waToasts.map(t => (
                        <div key={t.id} style={{ background: '#fff', border: `1px solid #e2e8f0`,
                            borderLeft: `4px solid ${t.color}`, borderRadius: 10,
                            padding: '8px 14px', boxShadow: '0 4px 16px rgba(0,0,0,.12)',
                            fontSize: 13, fontWeight: 600, color: '#0f172a' }}>
                            {t.msg}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function WaBubble({ msg }) {
    const out = msg.direction !== 'inbound';
    const tickClass = msg.status === 'read' ? 'wa-tick-read'
        : msg.status === 'delivered' ? 'wa-tick-delivered' : 'wa-tick-sent';
    const tickChar  = ['delivered', 'read'].includes(msg.status) ? '✓✓' : '✓';

    return (
        <div className={`wa-message ${out ? 'wa-outgoing' : 'wa-incoming'}`} data-msg-id={msg.id}>
            {/* media */}
            {msg.media_type && msg.media_url && (() => {
                if (msg.media_type === 'image') {
                    return <img src={msg.media_url} alt="" onClick={() => window.open(msg.media_url, '_blank')}
                        style={{ maxWidth: 200, maxHeight: 160, borderRadius: 6, display: 'block', marginBottom: 4, cursor: 'pointer' }} />;
                }
                if (msg.media_type === 'audio') {
                    return <audio controls style={{ width: '100%', minWidth: 180, marginBottom: 4 }}>
                        <source src={msg.media_url} />
                    </audio>;
                }
                if (msg.media_type === 'video') {
                    return <video controls style={{ maxWidth: 200, maxHeight: 160, borderRadius: 6, display: 'block', marginBottom: 4 }}>
                        <source src={msg.media_url} />
                    </video>;
                }
                return (
                    <a href={msg.media_url} target="_blank" rel="noreferrer" download
                        style={{ display: 'flex', alignItems: 'center', gap: 6, background: 'rgba(0,0,0,.07)',
                            borderRadius: 6, padding: '6px 10px', marginBottom: 4, textDecoration: 'none',
                            color: 'inherit', fontSize: 12, fontWeight: 600 }}>
                        <span className="material-icons" style={{ fontSize: 18, color: '#6366f1' }}>description</span>
                        {msg.media_filename || 'File'}
                    </a>
                );
            })()}

            {/* text body — skip for image/audio/video when no caption */}
            {msg.body && !['image', 'audio', 'video'].includes(msg.media_type || '') && (
                <p className="mb-1">{msg.body}</p>
            )}

            {/* meta row */}
            <div className="wa-message-meta">
                <small>{msg.time}</small>
                {out && <span className={`wa-tick ${tickClass}`}>{tickChar}</span>}
            </div>
        </div>
    );
}

// ─── NoteForm ─────────────────────────────────────────────────────────────────
function NoteForm({ url }) {
    const form = useForm({ note: '' });
    function submit(e) {
        e.preventDefault();
        form.post(url, { onSuccess: () => form.reset('note') });
    }
    return (
        <div className="note-section mb-4">
            <form onSubmit={submit}>
                <textarea className="form-control" rows={2}
                    placeholder="Write a note about this lead..."
                    value={form.data.note}
                    onChange={e => form.setData('note', e.target.value)}
                    required />
                <div className="d-flex justify-content-end mt-3">
                    <button className="btn btn-dark" disabled={form.processing}>
                        {form.processing ? 'Saving…' : 'Add Note'}
                    </button>
                </div>
            </form>
        </div>
    );
}

// ─── Timeline ─────────────────────────────────────────────────────────────────
const TYPE_ICON = {
    call: 'call', note: 'description', whatsapp: 'chat',
    status_change: 'sync_alt', followup: 'event', meeting: 'videocam',
};

const MISSED_STATUSES = new Set(['missed', 'no-answer', 'busy', 'failed', 'canceled']);

function callIcon(item) {
    if (item.type !== 'call' || !item.direction) return 'call';
    if (MISSED_STATUSES.has(item.call_status)) return 'call_missed';
    return item.direction === 'inbound' ? 'call_received' : 'call_made';
}

function getIcon(item) {
    if (item.type === 'call') return callIcon(item);
    return TYPE_ICON[item.type] ?? 'info';
}

const DIRECTION_STYLE = {
    inbound:  { background: '#dcfce7', color: '#16a34a' },
    outbound: { background: '#eff6ff', color: '#2563eb' },
};

const FILTERS = [
    { key: 'all',      label: 'All' },
    { key: 'call',     label: 'Calls' },
    { key: 'whatsapp', label: 'WhatsApp' },
];

function Timeline({ activities }) {
    const [filter, setFilter] = useState('all');

    const visible = filter === 'all'
        ? activities
        : activities.filter(a => a.type === filter);

    return (
        <div className="timeline-card">
            <div className="timeline-header">
                <h2>Activity Timeline</h2>
                <div className="timeline-filters">
                    {FILTERS.map(f => (
                        <button key={f.key} className={`filter-btn${filter === f.key ? ' active' : ''}`}
                            onClick={() => setFilter(f.key)}>
                            {f.label}
                        </button>
                    ))}
                </div>
            </div>
            <div className="timeline-content">
                {visible.length === 0 && (
                    <p className="text-muted text-center py-3" style={{ fontSize: 13 }}>No activity yet.</p>
                )}
                {visible.map(a => (
                    <div className="timeline-item" key={a.id} data-type={a.type}>
                        <div className="timeline-icon">
                            <span className="material-icons"
                                style={a.type === 'call' && MISSED_STATUSES.has(a.call_status)
                                    ? { color: '#ef4444' }
                                    : undefined}>
                                {getIcon(a)}
                            </span>
                        </div>
                        <div className="timeline-body">
                            <p style={{ marginBottom: a.type === 'call' && a.direction ? 4 : 0 }}>
                                {a.description}
                            </p>
                            {a.type === 'call' && a.direction && (
                                <span style={{
                                    display: 'inline-block',
                                    fontSize: 11,
                                    fontWeight: 600,
                                    padding: '1px 8px',
                                    borderRadius: 20,
                                    marginBottom: 4,
                                    textTransform: 'uppercase',
                                    letterSpacing: '0.03em',
                                    ...(DIRECTION_STYLE[a.direction] ?? {}),
                                }}>
                                    {a.direction}
                                </span>
                            )}
                            <small>{a.user || '—'} | {a.time}</small>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

// ─── StatusPanel ──────────────────────────────────────────────────────────────
const STATUS_OPTIONS_TC = [
    { value: 'contacted',      label: 'Contacted',      icon: 'phone_in_talk', bg: '#0ea5e9' },
    { value: 'interested',     label: 'Interested',     icon: 'thumb_up',      bg: '#10b981' },
    { value: 'follow_up',      label: 'Follow-up',      icon: 'event_repeat',  bg: '#f59e0b' },
    { value: 'not_interested', label: 'Not Interested', icon: 'thumb_down',    bg: '#ef4444' },
    { value: 'converted',      label: 'Converted',      icon: 'check_circle',  bg: '#8b5cf6' },
];

function StatusPanel({ lead, url, onClose }) {
    const form          = useForm({ status: lead.status, next_followup: '', followup_time: '', remarks: '' });
    const needsFollowup = form.data.status === 'follow_up';

    function submit(e) {
        e.preventDefault();
        form.post(url, { onSuccess: onClose });
    }

    return (
        <form onSubmit={submit} style={{
            background: '#fff', border: '1px solid #e2e8f0', borderRadius: 10,
            boxShadow: '0 4px 16px rgba(0,0,0,.07)', padding: '14px 16px', marginBottom: 16,
        }}>
            {/* Header */}
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 7 }}>
                    <span className="material-icons" style={{ fontSize: 16, color: '#6366f1' }}>sync_alt</span>
                    <span style={{ fontWeight: 600, fontSize: 13, color: '#0f172a' }}>Update Status</span>
                    <span style={{ padding: '2px 8px', borderRadius: 20, fontSize: 11, fontWeight: 600, background: '#f1f5f9', color: '#64748b' }}>
                        Current: {STATUS_LABELS[lead.status] ?? lead.status}
                    </span>
                </div>
                <button type="button" onClick={onClose}
                    style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#94a3b8', lineHeight: 1, padding: 0 }}>
                    <span className="material-icons" style={{ fontSize: 18 }}>close</span>
                </button>
            </div>

            {/* Status pills */}
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 7, marginBottom: needsFollowup ? 12 : 14 }}>
                {STATUS_OPTIONS_TC.map(s => {
                    const active = form.data.status === s.value;
                    return (
                        <button key={s.value} type="button"
                            onClick={() => form.setData('status', s.value)}
                            style={{
                                display: 'inline-flex', alignItems: 'center', gap: 5,
                                padding: '6px 14px', borderRadius: 20,
                                border: `1.5px solid ${s.bg}`,
                                background: active ? s.bg : `${s.bg}18`,
                                color: active ? '#fff' : s.bg,
                                fontSize: 12, fontWeight: 600, cursor: 'pointer', transition: 'all .15s',
                            }}>
                            {active && <span className="material-icons" style={{ fontSize: 12 }}>check</span>}
                            <span className="material-icons" style={{ fontSize: 14 }}>{s.icon}</span>
                            {s.label}
                        </button>
                    );
                })}
            </div>

            {/* Follow-up fields */}
            {needsFollowup && (
                <div style={{ borderTop: '1px solid #f1f5f9', paddingTop: 12, marginBottom: 12 }}>
                    <div style={{ display: 'flex', gap: 10, marginBottom: 8 }}>
                        <div style={{ flex: 1 }}>
                            <label style={{ fontSize: 11, fontWeight: 600, color: '#64748b', display: 'block', marginBottom: 3 }}>Follow-up Date</label>
                            <input type="date" className="form-control form-control-sm"
                                min={new Date().toISOString().slice(0, 10)}
                                value={form.data.next_followup}
                                onChange={e => form.setData('next_followup', e.target.value)} />
                        </div>
                        <div style={{ width: 110 }}>
                            <label style={{ fontSize: 11, fontWeight: 600, color: '#64748b', display: 'block', marginBottom: 3 }}>Time</label>
                            <input type="time" className="form-control form-control-sm"
                                value={form.data.followup_time}
                                onChange={e => form.setData('followup_time', e.target.value)} />
                        </div>
                    </div>
                    <textarea className="form-control form-control-sm" rows={2} placeholder="Remarks (optional)…"
                        value={form.data.remarks}
                        onChange={e => form.setData('remarks', e.target.value)} />
                </div>
            )}

            {/* Footer */}
            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                <button type="button" className="btn btn-sm btn-outline-secondary" onClick={onClose}>Cancel</button>
                <button type="submit" className="btn btn-sm btn-primary"
                    disabled={form.processing || form.data.status === lead.status}>
                    {form.processing ? 'Saving…' : 'Apply Status'}
                </button>
            </div>
        </form>
    );
}

// ─── CallOutcomeModal ─────────────────────────────────────────────────────────
const OUTCOMES = [
    { value: 'interested',      label: 'Interested',              cls: 'btn-success' },
    { value: 'not_interested',  label: 'Not Interested',          cls: 'btn-danger' },
    { value: 'call_back_later', label: 'Call Back Later',         cls: 'btn-warning text-dark' },
    { value: 'switched_off',    label: 'Switched Off / No Answer',cls: 'btn-secondary' },
    { value: 'wrong_number',    label: 'Wrong Number',            cls: 'btn-outline-secondary' },
];

function CallOutcomeModal({ url }) {
    const modalRef   = useRef(null);
    const callLogRef = useRef(null);

    // Listen for gc:callEnded to show the modal
    useEffect(() => {
        function onCallEnded(e) {
            const id = e.detail?.callLogId;
            if (!id) return;
            callLogRef.current = id;
            const el = modalRef.current;
            if (el && window.bootstrap?.Modal) {
                new window.bootstrap.Modal(el).show();
            }
        }
        document.addEventListener('gc:callEnded', onCallEnded);
        return () => document.removeEventListener('gc:callEnded', onCallEnded);
    }, []);

    async function recordOutcome(outcome) {
        const id   = callLogRef.current;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        if (!id) return;
        try {
            await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body: JSON.stringify({ call_log_id: id, outcome }),
            });
        } catch (_) {}
        window.bootstrap?.Modal.getInstance(modalRef.current)?.hide();
    }

    return (
        <div className="modal fade" id="callOutcomeModal" ref={modalRef}
            tabIndex={-1} data-bs-backdrop="static" data-bs-keyboard="false">
            <div className="modal-dialog modal-dialog-centered">
                <div className="modal-content">
                    <div className="modal-header border-0 pb-0">
                        <h5 className="modal-title fw-bold">How did the call go?</h5>
                    </div>
                    <div className="modal-body pt-2">
                        <p className="text-muted small mb-3">Select the outcome to log it against this lead.</p>
                        <div className="d-grid gap-2">
                            {OUTCOMES.map(o => (
                                <button key={o.value} className={`btn ${o.cls}`}
                                    onClick={() => recordOutcome(o.value)}>
                                    {o.label}
                                </button>
                            ))}
                        </div>
                    </div>
                    <div className="modal-footer border-0 pt-0">
                        <button type="button" className="btn btn-link text-muted btn-sm"
                            data-bs-dismiss="modal">Skip</button>
                    </div>
                </div>
            </div>
        </div>
    );
}

// ─── CallButton ───────────────────────────────────────────────────────────────
function CallButton({ phone, leadId }) {
    const [state, setState] = useState('idle'); // idle | connecting | active

    useEffect(() => {
        function onAccepted() { setState('active'); }
        function onEnded()    { setState('idle'); }
        document.addEventListener('gc:callAccepted', onAccepted);
        document.addEventListener('gc:callEnded',    onEnded);
        return () => {
            document.removeEventListener('gc:callAccepted', onAccepted);
            document.removeEventListener('gc:callEnded',    onEnded);
        };
    }, []);

    async function handleClick() {
        if (state === 'active') { window.GC?.endCall(); return; }
        setState('connecting');
        try {
            await window.GC?.startCall(phone, leadId);
        } catch (_) {
            setState('idle');
        }
    }

    const label = state === 'active' ? 'End Call' : state === 'connecting' ? 'Connecting…' : 'Call Now';
    const cls   = state === 'active'
        ? 'btn btn-danger call-btn active-call'
        : 'btn btn-primary call-btn';

    return (
        <button type="button" className={cls}
            data-phone={phone} data-lead={leadId}
            disabled={state === 'connecting'}
            onClick={handleClick}>
            <span className="material-icons">call</span>
            <span className="call-text">{label}</span>
        </button>
    );
}

// ─── MeetButton (Start Meet — instant) ───────────────────────────────────────
function MeetStartButton({ url, lead, onCreated }) {
    const [loading, setLoading] = useState(false);
    const [toast,   setToast]   = useState(null);
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function handleClick() {
        setLoading(true); setToast(null);
        try {
            const res  = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) { setToast({ ok: false, msg: data.error || 'Failed to create meeting.' }); return; }
            onCreated(data.meeting);
            const channels = [
                data.wa_sent    ? 'WhatsApp' : null,
                data.email_sent ? `Email (${lead.email})` : null,
            ].filter(Boolean).join(' & ') || 'none';
            setToast({ ok: true, msg: `Meet created! Notified via ${channels}.` });
            setTimeout(() => setToast(null), 5000);
            if (data.meeting?.meeting_link) {
                window.open(data.meeting.meeting_link, '_blank');
            }
        } catch (err) {
            setToast({ ok: false, msg: err.message || 'Network error.' });
        } finally {
            setLoading(false);
        }
    }

    return (
        <div style={{ position: 'relative', display: 'inline-block' }}>
            <button className="btn btn-outline-primary" type="button" onClick={handleClick} disabled={loading}>
                {loading
                    ? <span className="spinner-border spinner-border-sm me-1"></span>
                    : <span className="material-icons">videocam</span>
                }
                Start Meet
            </button>
            {toast && (
                <div style={{ position: 'absolute', top: 44, left: 0, zIndex: 99, minWidth: 260,
                    background: '#fff', border: `1.5px solid ${toast.ok ? '#bbf7d0' : '#fecaca'}`,
                    borderRadius: 10, padding: '8px 12px', boxShadow: '0 4px 16px rgba(0,0,0,.12)',
                    fontSize: 12, color: toast.ok ? '#15803d' : '#dc2626', fontWeight: 600 }}>
                    <span className="material-icons" style={{ fontSize: 13, verticalAlign: 'middle', marginRight: 4 }}>
                        {toast.ok ? 'check_circle' : 'error'}
                    </span>
                    {toast.msg}
                </div>
            )}
        </div>
    );
}

// ─── ZoomStartButton (Start Zoom — instant) ───────────────────────────────────
function ZoomStartButton({ url, lead, onCreated }) {
    const [loading, setLoading] = useState(false);
    const [toast,   setToast]   = useState(null);
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function handleClick() {
        setLoading(true); setToast(null);
        try {
            const res  = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) { setToast({ ok: false, msg: data.error || 'Failed to create Zoom meeting.' }); return; }
            onCreated(data.meeting);
            const channels = data.wa_sent ? 'WhatsApp' : 'none';
            setToast({ ok: true, msg: `Zoom created! Notified via ${channels}.` });
            setTimeout(() => setToast(null), 5000);
            if (data.meeting?.meeting_link) {
                window.open(data.meeting.meeting_link, '_blank');
            }
        } catch (err) {
            setToast({ ok: false, msg: err.message || 'Network error.' });
        } finally {
            setLoading(false);
        }
    }

    return (
        <div style={{ position: 'relative', display: 'inline-block' }}>
            <button className="btn btn-outline-primary" type="button" onClick={handleClick} disabled={loading}>
                {loading
                    ? <span className="spinner-border spinner-border-sm me-1"></span>
                    : <span className="material-icons">video_call</span>
                }
                Start Zoom
            </button>
            {toast && (
                <div style={{ position: 'absolute', top: 44, left: 0, zIndex: 99, minWidth: 260,
                    background: '#fff', border: `1.5px solid ${toast.ok ? '#bbf7d0' : '#fecaca'}`,
                    borderRadius: 10, padding: '8px 12px', boxShadow: '0 4px 16px rgba(0,0,0,.12)',
                    fontSize: 12, color: toast.ok ? '#15803d' : '#dc2626', fontWeight: 600 }}>
                    <span className="material-icons" style={{ fontSize: 13, verticalAlign: 'middle', marginRight: 4 }}>
                        {toast.ok ? 'check_circle' : 'error'}
                    </span>
                    {toast.msg}
                </div>
            )}
        </div>
    );
}

// ─── EmailModal ───────────────────────────────────────────────────────────────
function EmailModal({ url, lead, templates }) {
    const [mode,    setMode]    = useState('compose'); // 'compose' | 'template'
    const [tplId,   setTplId]   = useState('');
    const [subject, setSubject] = useState('');
    const [body,    setBody]    = useState('');
    const [files,   setFiles]   = useState([]);
    const [loading, setLoading] = useState(false);
    const [error,   setError]   = useState('');
    const [success, setSuccess] = useState(false);
    const fileRef = useRef(null);
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    function reset() {
        setMode('compose'); setTplId(''); setSubject(''); setBody('');
        setFiles([]); setLoading(false); setError(''); setSuccess(false);
        if (fileRef.current) fileRef.current.value = '';
    }

    function selectTemplate(id) {
        setTplId(id);
        const tpl = templates.find(t => String(t.id) === String(id));
        if (tpl) { setSubject(tpl.subject || ''); setBody(tpl.body || ''); }
    }

    async function handleSend(e) {
        e.preventDefault();
        if (!subject.trim()) { setError('Subject is required.'); return; }
        if (!body.trim())    { setError('Message body is required.'); return; }
        setLoading(true); setError('');
        const fd = new FormData();
        fd.append('subject', subject);
        fd.append('body', body);
        files.forEach(f => fd.append('attachments[]', f));
        try {
            const res  = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                body: fd,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) { setError(data.error || data.message || 'Failed to send email.'); return; }
            setSuccess(true);
            setTimeout(() => {
                reset();
                window.bootstrap?.Modal.getInstance(document.getElementById('emailModal'))?.hide();
            }, 2000);
        } catch (err) {
            setError(err.message || 'Network error.');
        } finally {
            setLoading(false);
        }
    }

    const hasEmail = !!lead.email;

    return (
        <div className="modal fade" id="emailModal" tabIndex={-1}>
            <div className="modal-dialog modal-dialog-centered modal-lg">
                <form onSubmit={handleSend}>
                    <div className="modal-content" style={{ borderRadius: 16, border: 'none' }}>
                        <div className="modal-header" style={{ borderBottom: '1px solid #e2e8f0', padding: '20px 24px 16px' }}>
                            <div className="d-flex align-items-center gap-2">
                                <div style={{ width: 34, height: 34, borderRadius: 10, background: '#ede9fe',
                                    display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                    <span className="material-icons" style={{ fontSize: 18, color: '#7c3aed' }}>email</span>
                                </div>
                                <div>
                                    <h5 className="modal-title fw-bold mb-0">Send Email</h5>
                                    <div style={{ fontSize: 11.5, color: '#64748b' }}>
                                        To: {hasEmail
                                            ? lead.email
                                            : <span style={{ color: '#ef4444' }}>No email on file</span>}
                                    </div>
                                </div>
                            </div>
                            <button type="button" className="btn-close" data-bs-dismiss="modal" onClick={reset}></button>
                        </div>

                        <div className="modal-body" style={{ padding: '20px 24px' }}>
                            {!hasEmail && (
                                <div className="alert alert-warning py-2 small mb-3">
                                    <span className="material-icons" style={{ fontSize: 14, verticalAlign: 'middle', marginRight: 4 }}>warning</span>
                                    This lead has no email address — email cannot be sent.
                                </div>
                            )}

                            {success && (
                                <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: 10,
                                    padding: '12px 14px', marginBottom: 16 }}>
                                    <div style={{ fontWeight: 700, fontSize: 13, color: '#15803d' }}>
                                        <span className="material-icons" style={{ fontSize: 15, verticalAlign: 'middle', marginRight: 4 }}>check_circle</span>
                                        Email sent successfully!
                                    </div>
                                </div>
                            )}

                            {error && <div className="alert alert-danger py-2 small mb-3">{error}</div>}

                            {/* Mode toggle — only shown when templates exist */}
                            {templates.length > 0 && (
                                <div className="mb-3 d-flex gap-2">
                                    {[
                                        { key: 'compose',  icon: 'edit',        label: 'Compose' },
                                        { key: 'template', icon: 'description', label: 'Use Template' },
                                    ].map(({ key, icon, label }) => (
                                        <button key={key} type="button"
                                            onClick={() => { setMode(key); if (key === 'compose') { setTplId(''); setSubject(''); setBody(''); } }}
                                            style={{ fontSize: 12.5, padding: '5px 16px', borderRadius: 20, cursor: 'pointer',
                                                border: `1.5px solid ${mode === key ? '#6366f1' : '#e2e8f0'}`,
                                                background: mode === key ? '#eef2ff' : '#f8fafc',
                                                color: mode === key ? '#6366f1' : '#64748b',
                                                fontWeight: mode === key ? 700 : 500 }}>
                                            <span className="material-icons" style={{ fontSize: 13, verticalAlign: 'middle', marginRight: 3 }}>{icon}</span>
                                            {label}
                                        </button>
                                    ))}
                                </div>
                            )}

                            {/* Template selector */}
                            {mode === 'template' && (
                                <div className="mb-3">
                                    <label className="form-label fw-semibold" style={{ fontSize: 13 }}>Select Template</label>
                                    <select className="form-select" style={{ borderRadius: 10, fontSize: 13 }}
                                        value={tplId} onChange={e => selectTemplate(e.target.value)}>
                                        <option value="">— Choose a template —</option>
                                        {templates.map(t => (
                                            <option key={t.id} value={t.id}>{t.name}</option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            {/* Subject */}
                            <div className="mb-3">
                                <label className="form-label fw-semibold" style={{ fontSize: 13 }}>Subject</label>
                                <input type="text" className="form-control" style={{ borderRadius: 10, fontSize: 13 }}
                                    placeholder="Email subject…"
                                    value={subject} onChange={e => setSubject(e.target.value)} maxLength={255} />
                            </div>

                            {/* Body */}
                            <div className="mb-3">
                                <label className="form-label fw-semibold" style={{ fontSize: 13 }}>Message</label>
                                <textarea className="form-control" rows={8}
                                    style={{ borderRadius: 10, fontSize: 13, resize: 'vertical', fontFamily: 'inherit' }}
                                    placeholder="Write your message here…"
                                    value={body} onChange={e => setBody(e.target.value)} />
                                {mode === 'template' && tplId && (
                                    <div style={{ fontSize: 11, color: '#64748b', marginTop: 4 }}>
                                        Template loaded — you can edit the content before sending.
                                    </div>
                                )}
                            </div>

                            {/* Attachments */}
                            <div className="mb-1">
                                <label className="form-label fw-semibold" style={{ fontSize: 13 }}>Attachments <span style={{ fontWeight: 400, color: '#94a3b8' }}>(optional)</span></label>
                                <div style={{ border: '1.5px dashed #cbd5e1', borderRadius: 10, padding: '10px 14px',
                                    background: '#f8fafc', cursor: 'pointer' }}
                                    onClick={() => fileRef.current?.click()}>
                                    <input type="file" multiple ref={fileRef} className="d-none"
                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.zip,.txt"
                                        onChange={e => setFiles(Array.from(e.target.files))} />
                                    {files.length === 0 ? (
                                        <div style={{ textAlign: 'center', color: '#94a3b8', fontSize: 12.5 }}>
                                            <span className="material-icons" style={{ fontSize: 20, display: 'block', marginBottom: 4 }}>attach_file</span>
                                            Click to attach files — PDF, DOC, images, ZIP (max 10 MB each)
                                        </div>
                                    ) : (
                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, alignItems: 'center' }}>
                                            {files.map((f, i) => (
                                                <span key={i} style={{ fontSize: 11.5, padding: '3px 10px', borderRadius: 20,
                                                    background: '#eef2ff', color: '#4f46e5', fontWeight: 600,
                                                    display: 'flex', alignItems: 'center', gap: 4 }}>
                                                    <span className="material-icons" style={{ fontSize: 12 }}>attach_file</span>
                                                    {f.name}
                                                </span>
                                            ))}
                                            <button type="button"
                                                onClick={ev => { ev.stopPropagation(); setFiles([]); if (fileRef.current) fileRef.current.value = ''; }}
                                                style={{ fontSize: 11, color: '#ef4444', background: 'none', border: 'none', cursor: 'pointer', padding: 0 }}>
                                                Clear all
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="modal-footer" style={{ borderTop: '1px solid #e2e8f0', padding: '16px 24px' }}>
                            <button type="button" className="btn btn-light" style={{ borderRadius: 8 }}
                                data-bs-dismiss="modal" onClick={reset}>Cancel</button>
                            <button type="submit" disabled={loading || !hasEmail || success}
                                style={{ borderRadius: 8, background: 'linear-gradient(135deg,#7c3aed,#8b5cf6)',
                                    color: '#fff', border: 'none', padding: '8px 18px', fontWeight: 600, fontSize: 13.5 }}>
                                {loading
                                    ? <><span className="spinner-border spinner-border-sm me-1"></span>Sending…</>
                                    : <><span className="material-icons me-1" style={{ fontSize: 16, verticalAlign: 'middle' }}>send</span>Send Email</>
                                }
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ─── ScheduleMeetModal ────────────────────────────────────────────────────────
function ScheduleMeetModal({ url, lead, onCreated }) {
    const [loading,     setLoading]     = useState(false);
    const [meetingTime, setMeetingTime] = useState('');
    const [duration,    setDuration]    = useState(60);
    const [title,       setTitle]       = useState('');
    const [notes,       setNotes]       = useState('');
    const [error,       setError]       = useState('');
    const [result,      setResult]      = useState(null); // { email_sent, wa_sent }
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    function reset() {
        setMeetingTime(''); setDuration(60); setTitle(''); setNotes('');
        setError(''); setResult(null);
    }

    async function handleSubmit(e) {
        e.preventDefault();
        if (!meetingTime) { setError('Please select a date and time.'); return; }
        setLoading(true); setError(''); setResult(null);
        try {
            const res  = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                body: JSON.stringify({ meeting_time: meetingTime, duration, title, notes }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) { setError(data.error || data.message || 'Failed to schedule meeting.'); return; }
            onCreated(data.meeting);
            setResult({ email_sent: data.email_sent, wa_sent: data.wa_sent });
            // Auto-close after 2s so user sees the confirmation
            setTimeout(() => {
                reset();
                window.bootstrap?.Modal.getInstance(document.getElementById('scheduleMeetModal'))?.hide();
            }, 2200);
        } catch (err) {
            setError(err.message || 'Network error.');
        } finally {
            setLoading(false);
        }
    }

    const minDateTime = new Date(Date.now() + 5 * 60000).toISOString().slice(0, 16);
    const hasEmail    = !!lead.email;

    return (
        <div className="modal fade" id="scheduleMeetModal" tabIndex={-1}>
            <div className="modal-dialog modal-dialog-centered">
                <form onSubmit={handleSubmit}>
                    <div className="modal-content" style={{ borderRadius: 16, border: 'none' }}>
                        <div className="modal-header" style={{ borderBottom: '1px solid #e2e8f0', padding: '20px 24px 16px' }}>
                            <div className="d-flex align-items-center gap-2">
                                <div style={{ width: 34, height: 34, borderRadius: 10, background: '#eef2ff',
                                    display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                    <span className="material-icons" style={{ fontSize: 18, color: '#6366f1' }}>event</span>
                                </div>
                                <h5 className="modal-title fw-bold mb-0">Schedule Google Meet</h5>
                            </div>
                            <button type="button" className="btn-close" data-bs-dismiss="modal" onClick={reset}></button>
                        </div>

                        <div className="modal-body" style={{ padding: '20px 24px' }}>

                            {/* Success confirmation */}
                            {result && (
                                <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: 10,
                                    padding: '12px 14px', marginBottom: 16 }}>
                                    <div style={{ fontWeight: 700, fontSize: 13, color: '#15803d', marginBottom: 6 }}>
                                        <span className="material-icons" style={{ fontSize: 15, verticalAlign: 'middle', marginRight: 4 }}>check_circle</span>
                                        Meeting scheduled successfully!
                                    </div>
                                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                        <span style={{ fontSize: 11.5, padding: '2px 10px', borderRadius: 20,
                                            background: result.wa_sent ? '#dcfce7' : '#fef9c3',
                                            color: result.wa_sent ? '#15803d' : '#854d0e', fontWeight: 600 }}>
                                            <span className="material-icons" style={{ fontSize: 11, verticalAlign: 'middle' }}>chat</span>
                                            {' '}WhatsApp {result.wa_sent ? 'sent ✓' : 'not sent'}
                                        </span>
                                        <span style={{ fontSize: 11.5, padding: '2px 10px', borderRadius: 20,
                                            background: result.email_sent ? '#dcfce7' : '#fef9c3',
                                            color: result.email_sent ? '#15803d' : '#854d0e', fontWeight: 600 }}>
                                            <span className="material-icons" style={{ fontSize: 11, verticalAlign: 'middle' }}>email</span>
                                            {' '}Calendar invite {result.email_sent ? 'sent ✓' : 'skipped (no email)'}
                                        </span>
                                    </div>
                                </div>
                            )}

                            {error && <div className="alert alert-danger py-2 small mb-3">{error}</div>}

                            <div className="mb-3">
                                <label className="form-label fw-semibold" style={{ fontSize: 13 }}>Meeting Title</label>
                                <input type="text" className="form-control" style={{ borderRadius: 10, fontSize: 13 }}
                                    placeholder={`Meeting with ${lead.name}`}
                                    value={title} onChange={e => setTitle(e.target.value)} maxLength={200} />
                            </div>

                            <div className="row g-3 mb-3">
                                <div className="col-7">
                                    <label className="form-label fw-semibold" style={{ fontSize: 13 }}>Date &amp; Time</label>
                                    <input type="datetime-local" className="form-control" style={{ borderRadius: 10, fontSize: 13 }}
                                        min={minDateTime} value={meetingTime}
                                        onChange={e => setMeetingTime(e.target.value)} required />
                                </div>
                                <div className="col-5">
                                    <label className="form-label fw-semibold" style={{ fontSize: 13 }}>Duration</label>
                                    <select className="form-select" style={{ borderRadius: 10, fontSize: 13 }}
                                        value={duration} onChange={e => setDuration(Number(e.target.value))}>
                                        <option value={15}>15 min</option>
                                        <option value={30}>30 min</option>
                                        <option value={45}>45 min</option>
                                        <option value={60}>1 hour</option>
                                        <option value={90}>1.5 hours</option>
                                        <option value={120}>2 hours</option>
                                    </select>
                                </div>
                            </div>

                            <div className="mb-3">
                                <label className="form-label fw-semibold" style={{ fontSize: 13 }}>Notes / Agenda (optional)</label>
                                <textarea className="form-control" rows={2} style={{ borderRadius: 10, fontSize: 13, resize: 'none' }}
                                    placeholder="Agenda, topics to discuss, etc."
                                    value={notes} onChange={e => setNotes(e.target.value)} maxLength={1000} />
                            </div>

                            {/* Auto-notification info */}
                            <div style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 10,
                                padding: '10px 14px', fontSize: 12, color: '#475569' }}>
                                <div style={{ fontWeight: 700, marginBottom: 4, color: '#334155' }}>
                                    <span className="material-icons" style={{ fontSize: 13, verticalAlign: 'middle', marginRight: 4 }}>notifications_active</span>
                                    Notifications sent automatically:
                                </div>
                                <div style={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                                    <span>
                                        <span className="material-icons" style={{ fontSize: 12, verticalAlign: 'middle', color: '#25D366' }}>chat</span>
                                        {' '}WhatsApp message with Meet link → {lead.phone}
                                    </span>
                                    <span>
                                        <span className="material-icons" style={{ fontSize: 12, verticalAlign: 'middle', color: '#6366f1' }}>email</span>
                                        {' '}Google Calendar invite → {hasEmail
                                            ? lead.email
                                            : <span style={{ color: '#ef4444' }}>no email on file</span>
                                        }
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="modal-footer" style={{ borderTop: '1px solid #e2e8f0', padding: '16px 24px' }}>
                            <button type="button" className="btn btn-light" data-bs-dismiss="modal"
                                style={{ borderRadius: 8 }} onClick={reset}>Cancel</button>
                            <button type="submit" className="btn btn-primary" style={{ borderRadius: 8 }} disabled={loading || !!result}>
                                {loading
                                    ? <><span className="spinner-border spinner-border-sm me-1"></span>Scheduling…</>
                                    : <><span className="material-icons me-1" style={{ fontSize: 16, verticalAlign: 'middle' }}>event</span>Schedule Meet</>
                                }
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ─── ScheduleZoomModal ────────────────────────────────────────────────────────
function ScheduleZoomModal({ url, lead, onCreated }) {
    const [loading,     setLoading]     = useState(false);
    const [meetingTime, setMeetingTime] = useState('');
    const [duration,    setDuration]    = useState(60);
    const [title,       setTitle]       = useState('');
    const [notes,       setNotes]       = useState('');
    const [error,       setError]       = useState('');
    const [result,      setResult]      = useState(null);
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    function reset() {
        setMeetingTime(''); setDuration(60); setTitle(''); setNotes('');
        setError(''); setResult(null);
    }

    async function handleSubmit(e) {
        e.preventDefault();
        if (!meetingTime) { setError('Please select a date and time.'); return; }
        setLoading(true); setError(''); setResult(null);
        try {
            const res  = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                body: JSON.stringify({ meeting_time: meetingTime, duration, title, notes }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) { setError(data.error || data.message || 'Failed to schedule Zoom meeting.'); return; }
            onCreated(data.meeting);
            setResult({ wa_sent: data.wa_sent });
            setTimeout(() => {
                reset();
                window.bootstrap?.Modal.getInstance(document.getElementById('scheduleZoomModal'))?.hide();
            }, 2200);
        } catch (err) {
            setError(err.message || 'Network error.');
        } finally {
            setLoading(false);
        }
    }

    const minDateTime = new Date(Date.now() + 5 * 60000).toISOString().slice(0, 16);

    return (
        <div className="modal fade" id="scheduleZoomModal" tabIndex={-1}>
            <div className="modal-dialog modal-dialog-centered">
                <form onSubmit={handleSubmit}>
                    <div className="modal-content" style={{ borderRadius: 16, border: 'none' }}>
                        <div className="modal-header" style={{ borderBottom: '1px solid #e2e8f0', padding: '20px 24px 16px' }}>
                            <div className="d-flex align-items-center gap-2">
                                <div style={{ width: 34, height: 34, borderRadius: 10, background: '#dbeafe',
                                    display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                    <span className="material-icons" style={{ fontSize: 18, color: '#2D8CFF' }}>event</span>
                                </div>
                                <h5 className="modal-title fw-bold mb-0">Schedule Zoom Meeting</h5>
                            </div>
                            <button type="button" className="btn-close" data-bs-dismiss="modal" onClick={reset}></button>
                        </div>

                        <div className="modal-body" style={{ padding: '20px 24px' }}>
                            {result && (
                                <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: 10,
                                    padding: '12px 14px', marginBottom: 16 }}>
                                    <div style={{ fontWeight: 700, fontSize: 13, color: '#15803d', marginBottom: 6 }}>
                                        <span className="material-icons" style={{ fontSize: 15, verticalAlign: 'middle', marginRight: 4 }}>check_circle</span>
                                        Zoom meeting scheduled successfully!
                                    </div>
                                    <span style={{ fontSize: 11.5, padding: '2px 10px', borderRadius: 20,
                                        background: result.wa_sent ? '#dcfce7' : '#fef9c3',
                                        color: result.wa_sent ? '#15803d' : '#854d0e', fontWeight: 600 }}>
                                        <span className="material-icons" style={{ fontSize: 11, verticalAlign: 'middle' }}>chat</span>
                                        {' '}WhatsApp {result.wa_sent ? 'sent ✓' : 'not sent'}
                                    </span>
                                </div>
                            )}

                            {error && <div className="alert alert-danger py-2 small mb-3">{error}</div>}

                            <div className="mb-3">
                                <label className="form-label fw-semibold" style={{ fontSize: 13 }}>Meeting Title</label>
                                <input type="text" className="form-control" style={{ borderRadius: 10, fontSize: 13 }}
                                    placeholder={`Zoom with ${lead.name}`}
                                    value={title} onChange={e => setTitle(e.target.value)} maxLength={200} />
                            </div>

                            <div className="row g-3 mb-3">
                                <div className="col-7">
                                    <label className="form-label fw-semibold" style={{ fontSize: 13 }}>Date &amp; Time</label>
                                    <input type="datetime-local" className="form-control" style={{ borderRadius: 10, fontSize: 13 }}
                                        min={minDateTime} value={meetingTime}
                                        onChange={e => setMeetingTime(e.target.value)} required />
                                </div>
                                <div className="col-5">
                                    <label className="form-label fw-semibold" style={{ fontSize: 13 }}>Duration</label>
                                    <select className="form-select" style={{ borderRadius: 10, fontSize: 13 }}
                                        value={duration} onChange={e => setDuration(Number(e.target.value))}>
                                        <option value={15}>15 min</option>
                                        <option value={30}>30 min</option>
                                        <option value={45}>45 min</option>
                                        <option value={60}>1 hour</option>
                                        <option value={90}>1.5 hours</option>
                                        <option value={120}>2 hours</option>
                                    </select>
                                </div>
                            </div>

                            <div className="mb-3">
                                <label className="form-label fw-semibold" style={{ fontSize: 13 }}>Notes / Agenda (optional)</label>
                                <textarea className="form-control" rows={2} style={{ borderRadius: 10, fontSize: 13, resize: 'none' }}
                                    placeholder="Agenda, topics to discuss, etc."
                                    value={notes} onChange={e => setNotes(e.target.value)} maxLength={1000} />
                            </div>

                            <div style={{ background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: 10,
                                padding: '10px 14px', fontSize: 12, color: '#1e40af' }}>
                                <div style={{ fontWeight: 700, marginBottom: 4 }}>
                                    <span className="material-icons" style={{ fontSize: 13, verticalAlign: 'middle', marginRight: 4 }}>notifications_active</span>
                                    Notifications sent automatically:
                                </div>
                                <span>
                                    <span className="material-icons" style={{ fontSize: 12, verticalAlign: 'middle', color: '#25D366' }}>chat</span>
                                    {' '}WhatsApp message with Zoom link → {lead.phone}
                                </span>
                            </div>
                        </div>

                        <div className="modal-footer" style={{ borderTop: '1px solid #e2e8f0', padding: '16px 24px' }}>
                            <button type="button" className="btn btn-light" data-bs-dismiss="modal"
                                style={{ borderRadius: 8 }} onClick={reset}>Cancel</button>
                            <button type="submit" className="btn btn-primary" style={{ borderRadius: 8, background: '#2D8CFF', borderColor: '#2D8CFF' }}
                                disabled={loading || !!result}>
                                {loading
                                    ? <><span className="spinner-border spinner-border-sm me-1"></span>Scheduling…</>
                                    : <><span className="material-icons me-1" style={{ fontSize: 16, verticalAlign: 'middle' }}>video_call</span>Schedule Zoom</>
                                }
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ─── MeetHistory ──────────────────────────────────────────────────────────────
const STATUS_COLOR = {
    scheduled: { bg: '#eef2ff', color: '#6366f1' },
    completed:  { bg: '#f0fdf4', color: '#16a34a' },
    missed:     { bg: '#fef2f2', color: '#ef4444' },
};

function MeetHistory({ meetings, statusUrl, onStatusChanged }) {
    if (!meetings || meetings.length === 0) {
        return (
            <div style={{ textAlign: 'center', padding: '28px 0', color: '#64748b', fontSize: 13 }}>
                <span className="material-icons" style={{ fontSize: 36, opacity: 0.25, display: 'block', marginBottom: 6 }}>videocam_off</span>
                No meetings scheduled yet.
            </div>
        );
    }

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function changeStatus(id, status) {
        const url = statusUrl.replace('__ID__', id);
        try {
            const res = await fetch(url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                body: JSON.stringify({ status }),
            });
            if (res.ok) onStatusChanged(id, status);
        } catch (_) {}
    }

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10, padding: '14px 18px' }}>
            {meetings.map(m => {
                const sc = STATUS_COLOR[m.status] ?? STATUS_COLOR.scheduled;
                return (
                    <div key={m.id} style={{ display: 'flex', alignItems: 'flex-start', gap: 12,
                        padding: '12px 14px', borderRadius: 12, background: '#f8fafc',
                        border: '1px solid #e2e8f0' }}>
                        <div style={{ width: 36, height: 36, borderRadius: 10, background: sc.bg, color: sc.color,
                            display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                            <span className="material-icons" style={{ fontSize: 18 }}>videocam</span>
                        </div>
                        <div style={{ flex: 1, minWidth: 0 }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 2 }}>
                                <span style={{ fontSize: 13, fontWeight: 700, color: '#0f172a' }}>{m.title}</span>
                                {m.meeting_type === 'zoom'
                                    ? <span style={{ fontSize: 10, fontWeight: 700, padding: '1px 7px', borderRadius: 20, background: '#dbeafe', color: '#2D8CFF' }}>Zoom</span>
                                    : <span style={{ fontSize: 10, fontWeight: 700, padding: '1px 7px', borderRadius: 20, background: '#eef2ff', color: '#6366f1' }}>Google</span>
                                }
                            </div>
                            <div style={{ fontSize: 12, color: '#64748b' }}>
                                <span className="material-icons" style={{ fontSize: 11, verticalAlign: 'middle' }}>schedule</span>
                                {' '}{m.meeting_time} &bull; {m.duration} min
                            </div>
                            {m.notes && (
                                <div style={{ fontSize: 12, color: '#64748b', marginTop: 2 }}>{m.notes}</div>
                            )}
                            {m.meeting_link && (
                                <a href={m.meeting_link} target="_blank" rel="noreferrer"
                                    style={{ fontSize: 12, color: '#6366f1', fontWeight: 600, display: 'inline-flex',
                                        alignItems: 'center', gap: 3, marginTop: 4, textDecoration: 'none' }}>
                                    <span className="material-icons" style={{ fontSize: 13 }}>open_in_new</span>
                                    Join Meeting
                                </a>
                            )}
                        </div>
                        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 6, flexShrink: 0 }}>
                            <span style={{ fontSize: 10.5, fontWeight: 700, padding: '3px 10px', borderRadius: 20,
                                background: sc.bg, color: sc.color, textTransform: 'capitalize' }}>
                                {m.status}
                            </span>
                            {m.status === 'scheduled' && (
                                <div style={{ display: 'flex', gap: 4 }}>
                                    <button onClick={() => changeStatus(m.id, 'completed')}
                                        style={{ fontSize: 11, padding: '2px 8px', borderRadius: 6, border: '1px solid #10b981',
                                            background: '#f0fdf4', color: '#16a34a', cursor: 'pointer' }}>Done</button>
                                    <button onClick={() => changeStatus(m.id, 'missed')}
                                        style={{ fontSize: 11, padding: '2px 8px', borderRadius: 6, border: '1px solid #ef4444',
                                            background: '#fef2f2', color: '#ef4444', cursor: 'pointer' }}>Missed</button>
                                </div>
                            )}
                            {m.whatsapp_sent && (
                                <span style={{ fontSize: 10, color: '#16a34a', display: 'flex', alignItems: 'center', gap: 2 }}>
                                    <span className="material-icons" style={{ fontSize: 11 }}>check_circle</span> WA sent
                                </span>
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

// ─── Main Show component ──────────────────────────────────────────────────────
export default function Show({ lead, whatsapp_messages, wa_template_name, wa_session_active, urls, meetings: initialMeetings, email_templates }) {
    const [meetings, setMeetings]       = useState(initialMeetings ?? []);
    const [statusOpen, setStatusOpen]   = useState(false);

    function scrollToChat() {
        document.getElementById('waChatBody')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function handleMeetingCreated(meeting) {
        setMeetings(prev => [meeting, ...prev]);
    }

    function handleStatusChanged(id, status) {
        setMeetings(prev => prev.map(m => m.id === id ? { ...m, status } : m));
    }

    return (
        <>
            <Head title="Lead Profile" />

            {/* breadcrumb nav */}
            <div className="lead-profile-nav">
                <div className="d-flex justify-content-between align-items-center w-100">
                    <div className="d-flex align-items-center gap-3">
                        <Link href="/telecaller/leads" className="btn btn-sm btn-light">
                            <span className="material-icons me-1" style={{ fontSize: 18 }}>arrow_back</span>
                            Back to Leads
                        </Link>
                        <div>
                            <h2 className="page-header-title mb-0">Lead Profile</h2>
                            <p className="page-header-subtitle mb-0">Complete details and activity timeline</p>
                        </div>
                    </div>
                </div>
            </div>

            <div className="dashboard-content">
                <div className="row g-4">

                    {/* ── Left: profile card ─────────────────────────────── */}
                    <div className="col-lg-4">
                        <ProfileCard lead={lead} />
                    </div>

                    {/* ── Right: action bar + chat + notes + timeline ─────── */}
                    <div className="col-lg-8">

                        {/* action bar */}
                        <div className="action-bar mb-4">
                            <CallButton phone={lead.phone} leadId={lead.id} />

                            <button className="btn btn-success" type="button" onClick={scrollToChat}>
                                <span className="material-icons">chat</span>
                                WhatsApp
                            </button>

                            <MeetStartButton
                                url={urls.meet_start}
                                lead={lead}
                                onCreated={handleMeetingCreated}
                            />

                            <button className="btn btn-outline-primary" type="button"
                                data-bs-toggle="modal" data-bs-target="#scheduleMeetModal">
                                <span className="material-icons">event</span>
                                Schedule Meet
                            </button>

                            <ZoomStartButton
                                url={urls.zoom_start}
                                lead={lead}
                                onCreated={handleMeetingCreated}
                            />

                            <button className="btn btn-outline-primary" type="button"
                                data-bs-toggle="modal" data-bs-target="#scheduleZoomModal">
                                <span className="material-icons">event</span>
                                Schedule Zoom
                            </button>

                            <button className={`btn btn-sm ${statusOpen ? 'btn-secondary' : 'btn-outline-secondary'}`} type="button"
                                onClick={() => setStatusOpen(o => !o)}>
                                <span className="material-icons">sync_alt</span>
                                Change Status
                            </button>

                            <button className="btn btn-outline-secondary" type="button"
                                data-bs-toggle="modal" data-bs-target="#emailModal"
                                title={!lead.email ? 'No email address on file' : 'Send email to lead'}>
                                <span className="material-icons">email</span>
                                Email
                            </button>
                        </div>

                        {statusOpen && (
                            <StatusPanel
                                lead={lead}
                                url={urls.change_status}
                                onClose={() => setStatusOpen(false)}
                            />
                        )}

                        {/* WhatsApp chat */}
                        <div style={{ position: 'relative' }}>
                            <WaChat
                                lead={lead}
                                initialMessages={whatsapp_messages}
                                templateName={wa_template_name}
                                initialSessionActive={wa_session_active}
                                urls={urls}
                            />
                        </div>

                        {/* Google Meet History */}
                        <div className="card border-0 shadow-sm mb-4">
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10,
                                padding: '14px 18px', borderBottom: '1px solid #e2e8f0' }}>
                                <div style={{ width: 34, height: 34, borderRadius: 10, background: '#eef2ff',
                                    display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                    <span className="material-icons" style={{ fontSize: 18, color: '#6366f1' }}>videocam</span>
                                </div>
                                <div>
                                    <div style={{ fontSize: 14, fontWeight: 700, color: '#0f172a' }}>Meeting History</div>
                                    <div style={{ fontSize: 11.5, color: '#64748b' }}>Google Meet &amp; Zoom sessions</div>
                                </div>
                                <span style={{ marginLeft: 'auto', fontSize: 11.5, fontWeight: 700,
                                    background: '#eef2ff', color: '#6366f1', padding: '3px 10px', borderRadius: 20 }}>
                                    {meetings.length}
                                </span>
                            </div>
                            <MeetHistory
                                meetings={meetings}
                                statusUrl={urls.meet_status}
                                onStatusChanged={handleStatusChanged}
                            />
                        </div>

                        {/* Add note */}
                        <NoteForm url={urls.add_note} />

                        {/* Activity timeline */}
                        <Timeline activities={lead.activities} />
                    </div>
                </div>
            </div>

            {/* Modals */}
            <CallOutcomeModal url={urls.call_outcome} />
            <ScheduleMeetModal url={urls.meet_schedule} lead={lead} onCreated={handleMeetingCreated} />
            <ScheduleZoomModal url={urls.zoom_schedule} lead={lead} onCreated={handleMeetingCreated} />
            <EmailModal url={urls.email} lead={lead} templates={email_templates ?? []} />
        </>
    );
}
