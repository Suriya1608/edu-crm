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
function WaChat({ lead, initialMessages, templateName, urls }) {
    const chatBodyRef   = useRef(null);
    const lastIdRef     = useRef(
        initialMessages.length ? Math.max(...initialMessages.map(m => m.id)) : 0
    );
    const fileInputRef  = useRef(null);

    const [messages,    setMessages]    = useState(initialMessages);
    const [text,        setText]        = useState('');
    const [pendingFile, setPendingFile] = useState(null);
    const [sending,     setSending]     = useState(false);
    const [waToasts,    setWaToasts]    = useState([]);

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

            if (data.messages?.length) {
                const fresh = data.messages.filter(m => m.id > lastIdRef.current);
                if (fresh.length) {
                    setMessages(prev => [...prev, ...fresh]);
                    lastIdRef.current = Math.max(...fresh.map(m => m.id));
                    const inbound = fresh.filter(m => m.direction === 'inbound').length;
                    if (inbound) {
                        playChime();
                        addToast(inbound > 1 ? `${inbound} new messages` : 'New WhatsApp message', '#25D366');
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
                        <span className="wa-live-dot"></span>
                    </div>

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
    status_change: 'sync_alt', followup: 'event',
};

function Timeline({ activities }) {
    const [filter, setFilter] = useState('all');
    const visible = filter === 'all' ? activities : activities.filter(a => a.type === filter);
    return (
        <div className="timeline-card">
            <div className="timeline-header">
                <h2>Activity Timeline</h2>
                <div className="timeline-filters">
                    {['all', 'call', 'whatsapp'].map(f => (
                        <button key={f} className={`filter-btn${filter === f ? ' active' : ''}`}
                            onClick={() => setFilter(f)}>
                            {f.charAt(0).toUpperCase() + f.slice(1)}
                        </button>
                    ))}
                </div>
            </div>
            <div className="timeline-content">
                {visible.map(a => (
                    <div className="timeline-item" key={a.id} data-type={a.type}>
                        <div className="timeline-icon">
                            <span className="material-icons">{TYPE_ICON[a.type] ?? 'info'}</span>
                        </div>
                        <div className="timeline-body">
                            <p>{a.description}</p>
                            <small>{a.user || '—'} | {a.time}</small>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

// ─── StatusModal ──────────────────────────────────────────────────────────────
function StatusModal({ lead, url }) {
    const form = useForm({
        status:        'contacted',
        next_followup: '',
        followup_time: '',
        remarks:       '',
    });
    const [step, setStep] = useState('idle'); // idle | confirm

    const needsFollowup = form.data.status === 'follow_up';

    function onConfirmClick() {
        setStep('confirm');
    }

    function onSubmit(e) {
        e.preventDefault();
        if (step !== 'confirm') return;
        form.post(url, {
            onSuccess: () => {
                setStep('idle');
                window.bootstrap?.Modal.getInstance(document.getElementById('statusModal'))?.hide();
            },
        });
    }

    function onHide() { setStep('idle'); form.reset(); }

    const currentLabel = STATUS_LABELS[lead.status] ?? lead.status;
    const selectedLabel = STATUS_LABELS[form.data.status] ?? form.data.status;

    return (
        <div className="modal fade" id="statusModal" tabIndex={-1}
            onHide={onHide}>
            <div className="modal-dialog">
                <form onSubmit={onSubmit}>
                    <div className="modal-content">
                        <div className="modal-header">
                            <h5 className="modal-title">Update Lead Status</h5>
                            <button type="button" className="btn-close" data-bs-dismiss="modal"
                                onClick={onHide}></button>
                        </div>
                        <div className="modal-body">
                            <div className="mb-3">
                                <label className="form-label fw-semibold">Select Status</label>
                                <select className="form-select" value={form.data.status}
                                    onChange={e => { form.setData('status', e.target.value); setStep('idle'); }}>
                                    <option value="contacted">Contacted</option>
                                    <option value="interested">Interested</option>
                                    <option value="follow_up">Follow-up Required</option>
                                    <option value="not_interested">Not Interested</option>
                                </select>
                            </div>

                            {needsFollowup && (
                                <div>
                                    <div className="row g-2 mb-3">
                                        <div className="col-7">
                                            <label className="form-label fw-semibold">Follow-up Date</label>
                                            <input type="date" className="form-control"
                                                min={new Date().toISOString().slice(0, 10)}
                                                value={form.data.next_followup}
                                                onChange={e => form.setData('next_followup', e.target.value)} />
                                        </div>
                                        <div className="col-5">
                                            <label className="form-label fw-semibold">Time</label>
                                            <input type="time" className="form-control"
                                                value={form.data.followup_time}
                                                onChange={e => form.setData('followup_time', e.target.value)} />
                                        </div>
                                    </div>
                                    <div className="mb-3">
                                        <label className="form-label fw-semibold">Remarks</label>
                                        <textarea className="form-control" rows={2}
                                            value={form.data.remarks}
                                            onChange={e => form.setData('remarks', e.target.value)} />
                                    </div>
                                </div>
                            )}
                        </div>
                        <div className="modal-footer">
                            <button type="button" className="btn btn-secondary"
                                data-bs-dismiss="modal" onClick={onHide}>Cancel</button>

                            {step === 'idle' && (
                                <button type="button" className="btn btn-primary" onClick={onConfirmClick}>
                                    Update Status
                                </button>
                            )}
                            {step === 'confirm' && (
                                <button type="submit" className="btn btn-warning" disabled={form.processing}>
                                    {form.processing
                                        ? 'Saving…'
                                        : `Changing: ${currentLabel} → ${selectedLabel}`}
                                </button>
                            )}
                        </div>
                    </div>
                </form>
            </div>
        </div>
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

// ─── Main Show component ──────────────────────────────────────────────────────
export default function Show({ lead, whatsapp_messages, wa_template_name, urls }) {
    function scrollToChat() {
        document.getElementById('waChatBody')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
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

                            <button className="btn btn-outline-primary" type="button"
                                data-bs-toggle="modal" data-bs-target="#statusModal">
                                <span className="material-icons">sync_alt</span>
                                Change Status
                            </button>
                        </div>

                        {/* WhatsApp chat */}
                        <div style={{ position: 'relative' }}>
                            <WaChat
                                lead={lead}
                                initialMessages={whatsapp_messages}
                                templateName={wa_template_name}
                                urls={urls}
                            />
                        </div>

                        {/* Add note */}
                        <NoteForm url={urls.add_note} />

                        {/* Activity timeline */}
                        <Timeline activities={lead.activities} />
                    </div>
                </div>
            </div>

            {/* Modals (rendered at component root, outside the columns) */}
            <StatusModal lead={lead} url={urls.change_status} />
            <CallOutcomeModal url={urls.call_outcome} />
        </>
    );
}
