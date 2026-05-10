import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect, useRef, useCallback } from 'react';

// ─── Status config ────────────────────────────────────────────────────────────
const STATUS_MAP = {
    pending:        { label: 'Pending',        bg: '#f1f5f9', color: '#64748b' },
    called:         { label: 'Called',          bg: '#e0f2fe', color: '#0284c7' },
    interested:     { label: 'Interested',      bg: '#dcfce7', color: '#16a34a' },
    not_interested: { label: 'Not Interested',  bg: '#fee2e2', color: '#dc2626' },
    no_answer:      { label: 'No Answer',       bg: '#fef9c3', color: '#ca8a04' },
    callback:       { label: 'Callback',        bg: '#ede9fe', color: '#7c3aed' },
    converted:      { label: 'Converted',       bg: '#dcfce7', color: '#15803d' },
};
const STATUSES = ['pending','called','interested','not_interested','no_answer','callback','converted'];

const ACTIVITY_ICON = {
    call: 'call', note: 'description', whatsapp: 'chat', status_change: 'sync_alt', followup_set: 'event',
};

function now12h() {
    return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// ─── StatusPill ───────────────────────────────────────────────────────────────
function StatusPill({ status }) {
    const s = STATUS_MAP[status] ?? { label: status, bg: '#f1f5f9', color: '#64748b' };
    return (
        <span style={{ background: s.bg, color: s.color, fontSize: 11, fontWeight: 700,
            padding: '3px 10px', borderRadius: 99, whiteSpace: 'nowrap' }}>
            {s.label}
        </span>
    );
}

// ─── WaBubble ─────────────────────────────────────────────────────────────────
function WaBubble({ msg }) {
    const out = msg.direction !== 'inbound';
    const tickClass = msg.status === 'read' ? 'wa-tick-read'
        : msg.status === 'delivered' ? 'wa-tick-delivered' : 'wa-tick-sent';
    const tickChar = ['delivered','read'].includes(msg.status) ? '✓✓' : '✓';

    return (
        <div className={`wa-message ${out ? 'wa-outgoing' : 'wa-incoming'}`} data-msg-id={msg.id}>
            {msg.media_type && msg.media_url && (() => {
                if (msg.media_type === 'image') return (
                    <img src={msg.media_url} alt="" onClick={() => window.open(msg.media_url,'_blank')}
                        style={{ maxWidth:200, maxHeight:160, borderRadius:6, display:'block', marginBottom:4, cursor:'pointer' }} />
                );
                if (msg.media_type === 'audio') return (
                    <audio controls style={{ width:'100%', minWidth:180, marginBottom:4 }}>
                        <source src={msg.media_url} />
                    </audio>
                );
                if (msg.media_type === 'video') return (
                    <video controls style={{ maxWidth:200, maxHeight:160, borderRadius:6, display:'block', marginBottom:4 }}>
                        <source src={msg.media_url} />
                    </video>
                );
                return (
                    <a href={msg.media_url} target="_blank" rel="noreferrer" download
                        style={{ display:'flex', alignItems:'center', gap:6, background:'rgba(0,0,0,.07)',
                            borderRadius:6, padding:'6px 10px', marginBottom:4, textDecoration:'none',
                            color:'inherit', fontSize:12, fontWeight:600 }}>
                        <span className="material-icons" style={{ fontSize:18, color:'#6366f1' }}>description</span>
                        {msg.media_filename || 'File'}
                    </a>
                );
            })()}
            {msg.body && !['image','audio','video'].includes(msg.media_type || '') && (
                <p className="mb-1">{msg.body}</p>
            )}
            <div className="wa-message-meta">
                <small>{msg.time}</small>
                {out && <span className={`wa-tick ${tickClass}`}>{tickChar}</span>}
            </div>
        </div>
    );
}

// ─── WaChat ───────────────────────────────────────────────────────────────────
function WaChat({ contactName, initialMessages, urls }) {
    const chatBodyRef = useRef(null);
    const lastIdRef   = useRef(initialMessages.length ? Math.max(...initialMessages.map(m => m.id)) : 0);
    const fileInputRef = useRef(null);

    const [messages,    setMessages]    = useState(initialMessages);
    const [text,        setText]        = useState('');
    const [pendingFile, setPendingFile] = useState(null);
    const [sending,     setSending]     = useState(false);
    const [toasts,      setToasts]      = useState([]);

    useEffect(() => {
        if (chatBodyRef.current) chatBodyRef.current.scrollTop = chatBodyRef.current.scrollHeight;
    }, [messages]);

    const poll = useCallback(async () => {
        try {
            const res  = await fetch(`${urls.wa_fetch}?after=${lastIdRef.current}`, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            if (data.messages?.length) {
                const fresh = data.messages.filter(m => m.id > lastIdRef.current);
                if (fresh.length) {
                    setMessages(prev => [...prev, ...fresh]);
                    lastIdRef.current = Math.max(...fresh.map(m => m.id));
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
        const t = setInterval(poll, 7_000);
        return () => clearInterval(t);
    }, [poll]);

    function addToast(msg, color) {
        const id = Date.now();
        setToasts(prev => [...prev, { id, msg, color }]);
        setTimeout(() => setToasts(prev => prev.filter(t => t.id !== id)), 4000);
    }

    function clearFile() {
        setPendingFile(null);
        if (fileInputRef.current) fileInputRef.current.value = '';
    }

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
        } catch (err) {
            addToast(err.message || 'Network error', '#ef4444');
        } finally {
            setSending(false);
        }
    }

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
        } catch (err) {
            addToast(err.message || 'Upload failed', '#ef4444');
        } finally {
            setSending(false);
        }
    }

    const fileLabel = pendingFile
        ? (pendingFile.size < 1_048_576
            ? `${pendingFile.name} (${(pendingFile.size / 1024).toFixed(1)} KB)`
            : `${pendingFile.name} (${(pendingFile.size / 1_048_576).toFixed(1)} MB)`)
        : null;

    return (
        <div className="card border-0 shadow-sm mb-4" style={{ position: 'relative' }}>
            <div className="card-body p-0">
                <div className="wa-chat-window">
                    <div className="wa-chat-header">
                        <div className="wa-user-block">
                            <div className="wa-avatar">{contactName.charAt(0).toUpperCase()}</div>
                            <div>
                                <h6 className="mb-0">{contactName}</h6>
                                <small>Meta WhatsApp</small>
                            </div>
                        </div>
                        <span className="wa-live-dot"></span>
                    </div>

                    <div className="wa-chat-body" ref={chatBodyRef}>
                        {messages.length === 0 && (
                            <div className="wa-message wa-incoming">
                                <p className="mb-1">No WhatsApp messages yet for this contact.</p>
                                <small>Start the conversation below.</small>
                            </div>
                        )}
                        {messages.map(m => <WaBubble key={m.id} msg={m} />)}
                    </div>

                    <div className="wa-chat-footer">
                        <div className="wa-template-row">
                            <button type="button" className="wa-template-btn"
                                onClick={() => setText(`Hello ${contactName}, thanks for your interest. Can we connect now?`)}>
                                Intro
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

                        {pendingFile && (
                            <div style={{ display:'flex', alignItems:'center', gap:8, background:'#f0f9ff',
                                border:'1.5px solid #bae6fd', borderRadius:8, padding:'6px 10px',
                                marginBottom:6, fontSize:12 }}>
                                <span className="material-icons" style={{ color:'#6366f1', fontSize:18 }}>attach_file</span>
                                <span style={{ flex:1, fontWeight:600, overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap' }}>
                                    {fileLabel}
                                </span>
                                <button type="button" onClick={clearFile}
                                    style={{ background:'none', border:'none', cursor:'pointer', color:'#ef4444', padding:0, display:'flex' }}>
                                    <span className="material-icons" style={{ fontSize:16 }}>close</span>
                                </button>
                            </div>
                        )}

                        <form className="wa-composer-form" onSubmit={handleSubmit}>
                            <input type="file" ref={fileInputRef} style={{ display:'none' }}
                                accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip"
                                onChange={e => setPendingFile(e.target.files[0] || null)} />
                            <button type="button" onClick={() => fileInputRef.current?.click()}
                                style={{ background:'#f1f5f9', border:'1.5px solid #e2e8f0', borderRadius:'50%',
                                    width:38, height:38, display:'flex', alignItems:'center',
                                    justifyContent:'center', cursor:'pointer', flexShrink:0 }}>
                                <span className="material-icons" style={{ fontSize:18, color:'#64748b' }}>attach_file</span>
                            </button>
                            <input className="form-control" type="text" autoComplete="off"
                                placeholder={pendingFile ? 'Add a caption (optional)…' : 'Type a WhatsApp message...'}
                                value={text} onChange={e => setText(e.target.value)} />
                            <button type="submit" className="btn btn-success" disabled={sending}>
                                {sending
                                    ? <span className="spinner-border spinner-border-sm" />
                                    : <span className="material-icons">send</span>
                                }
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {toasts.length > 0 && (
                <div style={{ position:'absolute', bottom:80, right:12, zIndex:10, pointerEvents:'none',
                    display:'flex', flexDirection:'column', gap:6 }}>
                    {toasts.map(t => (
                        <div key={t.id} style={{ background:'#fff', border:`1px solid #e2e8f0`,
                            borderLeft:`4px solid ${t.color}`, borderRadius:10,
                            padding:'8px 14px', boxShadow:'0 4px 16px rgba(0,0,0,.12)',
                            fontSize:13, fontWeight:600, color:'#0f172a' }}>
                            {t.msg}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

// ─── ActivityTimeline ─────────────────────────────────────────────────────────
function ActivityTimeline({ activities }) {
    const [filter, setFilter] = useState('all');
    const FILTERS = ['all','call','whatsapp','note','status_change'];
    const visible = filter === 'all' ? activities : activities.filter(a => a.type === filter);

    return (
        <div className="timeline-card">
            <div className="timeline-header">
                <h2>Activity Timeline</h2>
                <div className="timeline-filters">
                    {FILTERS.map(f => (
                        <button key={f} className={`filter-btn${filter === f ? ' active' : ''}`}
                            onClick={() => setFilter(f)}>
                            {f === 'all' ? 'All' : f === 'status_change' ? 'Status' : f.charAt(0).toUpperCase() + f.slice(1)}
                        </button>
                    ))}
                </div>
            </div>
            <div className="timeline-content">
                {visible.length === 0 ? (
                    <div className="text-center py-5">
                        <span className="material-icons" style={{ fontSize:40, color:'#cbd5e1' }}>timeline</span>
                        <p className="text-muted mt-2">No activity recorded yet.</p>
                    </div>
                ) : visible.map(activity => (
                    <div className="timeline-item" key={activity.id} data-type={activity.type}>
                        <div className="timeline-icon">
                            <span className="material-icons">{ACTIVITY_ICON[activity.type] || 'info'}</span>
                        </div>
                        <div className="timeline-body">
                            <p>{activity.description}</p>
                            {activity.type === 'call' && activity.meta && (
                                <div className="d-flex flex-wrap gap-1 mb-1">
                                    {activity.meta.outcome && (
                                        <span className="badge bg-light text-dark border">
                                            Outcome: {activity.meta.outcome.charAt(0).toUpperCase() + activity.meta.outcome.slice(1)}
                                        </span>
                                    )}
                                    {activity.meta.duration && (
                                        <span className="badge bg-light text-dark border">Duration: {activity.meta.duration}s</span>
                                    )}
                                </div>
                            )}
                            <small>{activity.created_by} | {activity.created_at}</small>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

// ─── ScheduleFollowupForm ─────────────────────────────────────────────────────
function ScheduleFollowupForm({ contact, url, onSaved }) {
    const [form, setForm] = useState({
        followup_date: contact.next_followup ?? '',
        followup_time: contact.followup_time ?? '',
        status: '',
        notes: '',
    });
    const [saving, setSaving] = useState(false);
    const [saved,  setSaved]  = useState(false);

    async function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const res  = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body: JSON.stringify(form),
            });
            if (res.ok) {
                setSaved(true);
                setTimeout(() => setSaved(false), 3000);
                onSaved?.({ next_followup: form.followup_date, followup_time: form.followup_time });
            }
        } catch (_) {}
        setSaving(false);
    }

    return (
        <div className="chart-card mb-4">
            <div className="chart-header mb-3"><h3>Schedule Follow-Up</h3></div>
            <form onSubmit={handleSubmit}>
                <div className="row g-2 mb-2">
                    <div className="col-7">
                        <label className="form-label small fw-semibold mb-1">Date</label>
                        <input type="date" className="form-control form-control-sm"
                            value={form.followup_date}
                            min={new Date().toISOString().split('T')[0]}
                            onChange={e => setForm({ ...form, followup_date: e.target.value })} required />
                    </div>
                    <div className="col-5">
                        <label className="form-label small fw-semibold mb-1">Time</label>
                        <input type="time" className="form-control form-control-sm"
                            value={form.followup_time}
                            onChange={e => setForm({ ...form, followup_time: e.target.value })} />
                    </div>
                </div>
                <div className="mb-2">
                    <label className="form-label small fw-semibold mb-1">Update Status</label>
                    <select className="form-select form-select-sm" value={form.status}
                        onChange={e => setForm({ ...form, status: e.target.value })}>
                        <option value="">— Keep current —</option>
                        {['callback','interested','not_interested','no_answer','called'].map(s => (
                            <option key={s} value={s}>{s.replace(/_/g,' ').replace(/\b\w/g, c => c.toUpperCase())}</option>
                        ))}
                    </select>
                </div>
                <div className="mb-2">
                    <label className="form-label small fw-semibold mb-1">Notes (optional)</label>
                    <textarea className="form-control form-control-sm" rows={2}
                        placeholder="Add follow-up notes..."
                        value={form.notes}
                        onChange={e => setForm({ ...form, notes: e.target.value })} />
                </div>
                <button className={`btn btn-sm w-100 ${saved ? 'btn-success' : 'btn-primary'}`} disabled={saving}>
                    <span className="material-icons me-1" style={{ fontSize:15, verticalAlign:'middle' }}>
                        {saved ? 'check' : 'event'}
                    </span>
                    {saving ? 'Saving…' : saved ? 'Saved!' : 'Save Follow-up'}
                </button>
            </form>
        </div>
    );
}

// ─── ReassignForm ─────────────────────────────────────────────────────────────
function ReassignForm({ contact, telecallers, url }) {
    const [assignedTo, setAssignedTo] = useState(contact.assigned_to ?? '');
    const [saving,     setSaving]     = useState(false);
    const [saved,      setSaved]      = useState(false);

    async function handleSubmit(e) {
        e.preventDefault();
        setSaving(true);
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const res  = await fetch(url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body: JSON.stringify({ assigned_to: assignedTo }),
            });
            if (res.ok) { setSaved(true); setTimeout(() => setSaved(false), 3000); }
        } catch (_) {}
        setSaving(false);
    }

    return (
        <div className="chart-card mb-4">
            <div className="chart-header mb-3"><h3>Assign / Reassign</h3></div>
            <form onSubmit={handleSubmit}>
                <select className="form-select form-select-sm mb-2" value={assignedTo}
                    onChange={e => setAssignedTo(e.target.value)} required>
                    <option value="">Select Telecaller</option>
                    {telecallers.map(tc => (
                        <option key={tc.id} value={tc.id}>{tc.name}</option>
                    ))}
                </select>
                <button className={`btn btn-sm w-100 ${saved ? 'btn-success' : 'btn-primary'}`} disabled={saving}>
                    <span className="material-icons me-1" style={{ fontSize:15, verticalAlign:'middle' }}>person_add</span>
                    {saving ? 'Saving…' : saved ? 'Assigned!' : 'Assign'}
                </button>
            </form>
        </div>
    );
}

// ─── AddNote ──────────────────────────────────────────────────────────────────
function AddNote({ url, onAdded }) {
    const [note,   setNote]   = useState('');
    const [saving, setSaving] = useState(false);

    async function handleSubmit(e) {
        e.preventDefault();
        if (!note.trim()) return;
        setSaving(true);
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const res  = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body: JSON.stringify({ note }),
            });
            if (res.ok) { setNote(''); onAdded?.(); }
        } catch (_) {}
        setSaving(false);
    }

    return (
        <div className="note-section mb-4">
            <form onSubmit={handleSubmit}>
                <textarea className="form-control" rows={2}
                    placeholder="Write a note about this contact..."
                    value={note} onChange={e => setNote(e.target.value)} required />
                <div className="d-flex justify-content-end mt-3">
                    <button className="btn btn-dark" disabled={saving}>
                        {saving ? 'Saving…' : 'Add Note'}
                    </button>
                </div>
            </form>
        </div>
    );
}

// ─── StatusModal ──────────────────────────────────────────────────────────────
function StatusModal({ show, currentStatus, url, onClose, onChanged }) {
    const [selected, setSelected] = useState(currentStatus);
    const [step,     setStep]     = useState('pick');
    const [saving,   setSaving]   = useState(false);

    useEffect(() => {
        if (show) { setSelected(currentStatus); setStep('pick'); }
    }, [show, currentStatus]);

    async function confirm() {
        if (step === 'pick') { setStep('confirm'); return; }
        setSaving(true);
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const res  = await fetch(url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body: JSON.stringify({ status: selected }),
            });
            if (res.ok) { onChanged?.(selected); onClose?.(); }
        } catch (_) {}
        setSaving(false);
    }

    if (!show) return null;
    return (
        <div className="modal fade show d-block" tabIndex="-1" style={{ backgroundColor:'rgba(0,0,0,.5)' }}>
            <div className="modal-dialog">
                <div className="modal-content">
                    <div className="modal-header">
                        <h5 className="modal-title">Update Contact Status</h5>
                        <button type="button" className="btn-close" onClick={onClose}></button>
                    </div>
                    <div className="modal-body">
                        {step === 'pick' ? (
                            <div className="mb-3">
                                <label className="form-label fw-semibold">Select Status</label>
                                <select className="form-select" value={selected}
                                    onChange={e => setSelected(e.target.value)}>
                                    {STATUSES.map(s => (
                                        <option key={s} value={s}>
                                            {s.replace(/_/g,' ').replace(/\b\w/g, c => c.toUpperCase())}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        ) : (
                            <p className="text-muted">
                                Change status from <strong>{currentStatus.replace(/_/g,' ')}</strong> to{' '}
                                <strong className="text-primary">{selected.replace(/_/g,' ')}</strong>?
                            </p>
                        )}
                    </div>
                    <div className="modal-footer">
                        <button type="button" className="btn btn-secondary"
                            onClick={step === 'confirm' ? () => setStep('pick') : onClose}>
                            {step === 'confirm' ? 'Back' : 'Cancel'}
                        </button>
                        <button type="button"
                            className={`btn ${step === 'confirm' ? 'btn-warning' : 'btn-primary'}`}
                            onClick={confirm} disabled={saving}>
                            {saving ? 'Saving…' : step === 'confirm' ? 'Yes, Confirm' : 'Update Status'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

// ─── CallOutcomeModal ─────────────────────────────────────────────────────────
function CallOutcomeModal({ show, url, onClose, onLogged }) {
    const [saving, setSaving] = useState(false);

    async function log(outcome) {
        setSaving(true);
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body: JSON.stringify({ outcome }),
            });
            onLogged?.(outcome);
        } catch (_) {}
        setSaving(false);
        onClose?.();
    }

    if (!show) return null;
    return (
        <div className="modal fade show d-block" tabIndex="-1" style={{ backgroundColor:'rgba(0,0,0,.5)' }}>
            <div className="modal-dialog modal-dialog-centered">
                <div className="modal-content">
                    <div className="modal-header border-0 pb-0">
                        <h5 className="modal-title fw-bold">How did the call go?</h5>
                    </div>
                    <div className="modal-body pt-2">
                        <p className="text-muted small mb-3">Select the outcome to log it in the activity timeline.</p>
                        <div className="d-grid gap-2">
                            {[
                                { outcome:'interested',     label:'Interested',               cls:'btn-success' },
                                { outcome:'not_interested', label:'Not Interested',            cls:'btn-danger' },
                                { outcome:'callback',       label:'Call Back Later',           cls:'btn-warning text-dark' },
                                { outcome:'no_answer',      label:'Switched Off / No Answer',  cls:'btn-secondary' },
                                { outcome:'called',         label:'Other / Just Called',       cls:'btn-outline-secondary' },
                            ].map(({ outcome, label, cls }) => (
                                <button key={outcome} className={`btn ${cls}`} disabled={saving}
                                    onClick={() => log(outcome)}>{label}</button>
                            ))}
                        </div>
                    </div>
                    <div className="modal-footer border-0 pt-0">
                        <button type="button" className="btn btn-link text-muted btn-sm" onClick={onClose}>Skip</button>
                    </div>
                </div>
            </div>
        </div>
    );
}

// ─── Main ─────────────────────────────────────────────────────────────────────
export default function Contact({ campaign, contact: initialContact, activities: initialActivities,
    whatsapp_messages, telecallers, urls }) {

    const [contact,     setContact]     = useState(initialContact);
    const [activities,  setActivities]  = useState(initialActivities ?? []);
    const [showStatus,  setShowStatus]  = useState(false);
    const [showOutcome, setShowOutcome] = useState(false);

    function fmtFollowup() {
        if (!contact.next_followup) return null;
        const date = new Date(contact.next_followup).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
        if (!contact.followup_time) return date;
        const [h, m] = contact.followup_time.split(':');
        const d = new Date(); d.setHours(+h, +m);
        return `${date} ${d.toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' })}`;
    }

    function handleStatusChanged(newStatus) {
        setContact(prev => ({ ...prev, status: newStatus }));
        setActivities(prev => [{
            id: Date.now(), type: 'status_change',
            description: `Status changed to ${newStatus}`,
            meta: null, created_by: 'You', created_at: 'just now',
        }, ...prev]);
    }

    function handleNoteAdded() {
        router.reload({ only: ['activities'] });
    }

    function handleOutcomeLogged(outcome) {
        const statusMap = { interested:'interested', not_interested:'not_interested',
            callback:'callback', no_answer:'no_answer', called:'called' };
        if (statusMap[outcome]) setContact(prev => ({ ...prev, status: statusMap[outcome] }));
        setActivities(prev => [{
            id: Date.now(), type: 'call',
            description: 'Outbound call made',
            meta: { outcome }, created_by: 'You', created_at: 'just now',
        }, ...prev]);
    }

    useEffect(() => {
        const handler = () => setShowOutcome(true);
        document.addEventListener('gc:callEnded', handler);
        return () => document.removeEventListener('gc:callEnded', handler);
    }, []);

    const followupLabel = fmtFollowup();
    const profileItems = [
        { icon: 'phone',       label: 'Mobile',          value: contact.phone },
        contact.email  && { icon: 'mail',      label: 'Email',           value: contact.email },
        contact.course && { icon: 'school',    label: 'Course Interest', value: contact.course },
        contact.city   && { icon: 'location_on', label: 'City',          value: contact.city },
        { icon: 'person',      label: 'Assigned To',     value: contact.assigned_user ?? '—' },
        { icon: 'call',        label: 'Total Calls Made', value: String(contact.call_count) },
        followupLabel && { icon: 'event',      label: 'Next Follow-up',  value: followupLabel },
    ].filter(Boolean);

    return (
        <>
            <Head title={contact.name} />

            <div className="lead-profile-nav">
                <div className="d-flex justify-content-between align-items-center w-100">
                    <div className="d-flex align-items-center gap-3">
                        <Link href={urls.back} className="btn btn-sm btn-light d-flex align-items-center gap-1">
                            <span className="material-icons me-1" style={{ fontSize:18 }}>arrow_back</span>
                            Back to {campaign.name}
                        </Link>
                        <div>
                            <h2 className="page-header-title mb-0">{contact.name}</h2>
                            <p className="page-header-subtitle mb-0">Campaign Contact</p>
                        </div>
                    </div>
                </div>
            </div>

            <div className="dashboard-content">
                <div className="row g-4">

                    {/* ── Left ──────────────────────────────────────────── */}
                    <div className="col-lg-4">
                        <div className="profile-card mb-4">
                            <div className="profile-header">
                                <div className="profile-info">
                                    <h1 className="profile-name">{contact.name}</h1>
                                    <div className="mb-2"><StatusPill status={contact.status} /></div>
                                    <p className="profile-id">{campaign.name}</p>
                                </div>
                            </div>
                            <div className="profile-details">
                                {profileItems.map(item => (
                                    <div className="detail-item" key={item.label}>
                                        <span className="material-icons">{item.icon}</span>
                                        <div className="flex-grow-1">
                                            <p className="detail-label">{item.label}</p>
                                            <p className="detail-value">{item.value}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <ReassignForm
                            contact={contact}
                            telecallers={telecallers}
                            url={urls.reassign}
                        />

                        <ScheduleFollowupForm
                            contact={contact}
                            url={urls.set_followup}
                            onSaved={({ next_followup, followup_time }) =>
                                setContact(prev => ({ ...prev, next_followup, followup_time }))
                            }
                        />
                    </div>

                    {/* ── Right ─────────────────────────────────────────── */}
                    <div className="col-lg-8">
                        <div className="action-bar mb-4">
                            <button type="button" className="btn btn-primary"
                                onClick={async (e) => {
                                    const btn = e.currentTarget;
                                    if (window.GC?.isActive?.()) { window.GC.endCall(); return; }
                                    btn.disabled = true;
                                    btn.textContent = 'Connecting…';
                                    try { await window.GC?.startCall?.(contact.phone, null); }
                                    catch (_) {
                                        btn.disabled = false;
                                        btn.innerHTML = '<span class="material-icons">call</span> Call Now';
                                    }
                                }}>
                                <span className="material-icons">call</span> Call Now
                            </button>

                            <button className="btn btn-success" type="button"
                                onClick={() => document.querySelector('.wa-chat-body')
                                    ?.scrollIntoView({ behavior:'smooth', block:'start' })}>
                                <span className="material-icons">chat</span> WhatsApp
                            </button>

                            <button className="btn btn-outline-primary" type="button"
                                onClick={() => setShowStatus(true)}>
                                <span className="material-icons">sync_alt</span> Change Status
                            </button>
                        </div>

                        <WaChat
                            contactName={contact.name}
                            initialMessages={whatsapp_messages ?? []}
                            urls={urls}
                        />

                        <AddNote url={urls.add_note} onAdded={handleNoteAdded} />

                        <ActivityTimeline activities={activities} />
                    </div>
                </div>
            </div>

            <StatusModal
                show={showStatus}
                currentStatus={contact.status}
                url={urls.change_status}
                onClose={() => setShowStatus(false)}
                onChanged={handleStatusChanged}
            />
            <CallOutcomeModal
                show={showOutcome}
                url={urls.log_call}
                onClose={() => setShowOutcome(false)}
                onLogged={handleOutcomeLogged}
            />
        </>
    );
}
