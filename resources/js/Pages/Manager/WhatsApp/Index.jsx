import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect, useRef, useCallback } from 'react';

// ─── Template quick-replies ───────────────────────────────────────────────────
const TEMPLATES = [
    { label: 'Intro',       msg: (n) => `Hello ${n}, thanks for your interest. Can we connect now?` },
    { label: 'Follow-up',   msg: ()  => `Reminder: your follow-up is scheduled. Please confirm your preferred time.` },
    { label: 'Course Info', msg: ()  => `Please share your preferred course and we'll guide you with next steps.` },
    { label: 'Admission',   msg: (n) => `Hi ${n}, the admission process is now open. Let's get you enrolled!` },
];

// ─── Helpers ──────────────────────────────────────────────────────────────────
function nowTime() {
    return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
function todayDateStr() {
    return new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}
function friendlyDate(dateStr) {
    if (!dateStr) return '';
    const today = todayDateStr();
    if (dateStr === today) return 'Today';
    const d = new Date();
    d.setDate(d.getDate() - 1);
    const yesterday = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    if (dateStr === yesterday) return 'Yesterday';
    return dateStr;
}
function formatBytes(b) {
    if (b < 1024)    return b + ' B';
    if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
    return (b / 1048576).toFixed(1) + ' MB';
}
function fileIcon(type) {
    if (!type) return 'description';
    if (type.startsWith('image/')) return 'image';
    if (type.startsWith('video/')) return 'videocam';
    if (type.startsWith('audio/')) return 'headphones';
    return 'description';
}

// ─── Tick icon (WhatsApp-style) ───────────────────────────────────────────────
function TickIcon({ status }) {
    if (status === 'pending') {
        return <span className="material-icons wa-tick pending" style={{ fontSize: 13 }}>schedule</span>;
    }
    if (status === 'sent') {
        return <span className="material-icons wa-tick sent" style={{ fontSize: 13 }}>done</span>;
    }
    return (
        <span className={`material-icons wa-tick ${status}`} style={{ fontSize: 13 }}>done_all</span>
    );
}

// ─── MediaBubble ──────────────────────────────────────────────────────────────
function MediaBubble({ mediaType, mediaUrl, mediaFilename }) {
    if (!mediaType || !mediaUrl) return null;
    if (mediaType === 'image') {
        return (
            <img src={mediaUrl} className="wa-media-img"
                onClick={() => window.open(mediaUrl, '_blank')} alt="Image" />
        );
    }
    if (mediaType === 'audio') {
        return <audio controls className="wa-media-audio"><source src={mediaUrl} /></audio>;
    }
    if (mediaType === 'video') {
        return (
            <video controls className="wa-media-img" style={{ maxHeight: 200 }}>
                <source src={mediaUrl} />
            </video>
        );
    }
    return (
        <a href={mediaUrl} target="_blank" rel="noreferrer" className="wa-media-doc" download>
            <span className="material-icons">description</span>
            {mediaFilename || 'File'}
        </a>
    );
}

// ─── Message bubble ───────────────────────────────────────────────────────────
function Bubble({ msg }) {
    const isPending = msg.status === 'pending';
    const showText  = msg.message_body &&
        !['image', 'audio', 'video'].includes(msg.media_type || '');

    return (
        <div
            className={`wa-bubble ${msg.direction}`}
            data-msg-id={msg.id}
            style={isPending ? { opacity: 0.7 } : undefined}
        >
            <MediaBubble
                mediaType={msg.media_type}
                mediaUrl={msg.media_url}
                mediaFilename={msg.media_filename}
            />
            {showText && <div className="wa-bubble-text">{msg.message_body}</div>}
            <div className="wa-bubble-meta">
                <span className="wa-bubble-time">{msg.time}</span>
                {msg.direction === 'outbound' && <TickIcon status={msg.status || 'sent'} />}
            </div>
        </div>
    );
}

// ─── Messages list with date separators ──────────────────────────────────────
function MessageList({ messages, msgAreaRef }) {
    const rows = [];
    let lastDate = null;
    messages.forEach((m) => {
        if (m.date !== lastDate) {
            rows.push({ type: 'divider', key: `d-${m.date}-${m.id}`, label: friendlyDate(m.date) });
            lastDate = m.date;
        }
        rows.push({ type: 'msg', key: m.id ?? m._tempId, msg: m });
    });

    return (
        <div className="wa-messages-area" ref={msgAreaRef}>
            {rows.length === 0 && (
                <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#94a3b8', fontSize: 13 }}>
                    No messages yet
                </div>
            )}
            {rows.map(r =>
                r.type === 'divider'
                    ? <div key={r.key} className="wa-day-divider">{r.label}</div>
                    : <Bubble key={r.key} msg={r.msg} />
            )}
        </div>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────
export default function Index({
    conversations: initialConversations,
    activeLead:    initialActiveLead,
    activeMessages: initialActiveMessages,
    unreadCounts:  initialUnread,
}) {
    const [conversations,   setConversations]   = useState((initialConversations ?? []).map(c => ({ ...c })));
    const [activeLead,      setActiveLead]       = useState(initialActiveLead ?? null);
    const [messages,        setMessages]         = useState(initialActiveMessages ?? []);
    const [lastMsgId,       setLastMsgId]        = useState(() => {
        const msgs = initialActiveMessages ?? [];
        return msgs.length ? msgs[msgs.length - 1].id : 0;
    });

    const [search,          setSearch]           = useState('');
    const [sending,         setSending]          = useState(false);
    const [msgText,         setMsgText]          = useState('');
    const [toast,           setToast]            = useState(null);
    const [mobileShowChat,  setMobileShowChat]   = useState(!!initialActiveLead);

    const [pendingFile,     setPendingFile]      = useState(null);
    const [filePreviewName, setFilePreviewName]  = useState('');
    const [filePreviewSize, setFilePreviewSize]  = useState('');
    const [filePreviewIcon, setFilePreviewIcon]  = useState('attach_file');

    const msgAreaRef  = useRef(null);
    const textareaRef = useRef(null);
    const fileInputRef = useRef(null);
    const pollRef      = useRef(null);
    const toastTimer   = useRef(null);

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ── Scroll to bottom ──────────────────────────────────────────────────────
    const scrollBottom = useCallback((smooth = true) => {
        if (!msgAreaRef.current) return;
        msgAreaRef.current.scrollTo({ top: msgAreaRef.current.scrollHeight, behavior: smooth ? 'smooth' : 'instant' });
    }, []);
    useEffect(() => { scrollBottom(); }, [messages, scrollBottom]);

    // ── Toast ─────────────────────────────────────────────────────────────────
    function showToast(msg) {
        setToast(msg);
        if (toastTimer.current) clearTimeout(toastTimer.current);
        toastTimer.current = setTimeout(() => setToast(null), 3500);
    }

    // ── Polling ───────────────────────────────────────────────────────────────
    const fetchMessages = useCallback(async (encryptedId, currentLastId) => {
        if (!encryptedId) return;
        try {
            const url = `/manager/whatsapp/${encryptedId}/messages?after=${currentLastId}`;
            const res  = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();

            if (data.messages?.length > 0) {
                setMessages(prev => {
                    // Remove any pending placeholders that match real messages (by body)
                    const noPending = prev.filter(m => m.status !== 'pending');
                    const existing  = new Set(noPending.map(m => m.id));
                    const fresh     = data.messages.filter(m => !existing.has(m.id));
                    if (!fresh.length) return prev;
                    setLastMsgId(fresh[fresh.length - 1].id);

                    const lastNew = fresh[fresh.length - 1];
                    setConversations(prev2 => {
                        const updated = prev2.map(c =>
                            String(c.id) === String(data.lead?.id)
                                ? { ...c, last_message: lastNew.message_body }
                                : c
                        );
                        const idx = updated.findIndex(c => String(c.id) === String(data.lead?.id));
                        if (idx > 0) { const [item] = updated.splice(idx, 1); updated.unshift(item); }
                        return updated;
                    });
                    return [...noPending, ...fresh];
                });
            }

            // Update tick statuses on DOM elements directly (avoids full re-render)
            if (data.statuses && msgAreaRef.current) {
                Object.entries(data.statuses).forEach(([id, status]) => {
                    const bubble = msgAreaRef.current?.querySelector(`[data-msg-id="${id}"]`);
                    const tick   = bubble?.querySelector('.wa-tick');
                    if (tick) tick.className = `material-icons wa-tick ${status}`;
                });
            }

            if (data.unread) {
                setConversations(prev =>
                    prev.map(c => ({ ...c, unread_count: data.unread[c.id] ?? 0 }))
                );
            }
        } catch (_) {}
    }, []);

    function startPolling(encryptedId, lastId) {
        stopPolling();
        pollRef.current = { encryptedId, lastIdRef: { current: lastId } };
        const interval = setInterval(() => {
            fetchMessages(pollRef.current.encryptedId, pollRef.current.lastIdRef.current);
        }, 7000);
        pollRef.current.interval = interval;
    }
    function stopPolling() {
        if (pollRef.current?.interval) { clearInterval(pollRef.current.interval); pollRef.current = null; }
    }

    // Keep pollRef's lastIdRef in sync
    useEffect(() => {
        if (pollRef.current) pollRef.current.lastIdRef.current = lastMsgId;
    }, [lastMsgId]);

    // Start polling on mount if a lead is already active
    useEffect(() => {
        if (initialActiveLead) startPolling(initialActiveLead.encrypted_id, lastMsgId);
        return () => stopPolling();
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // ── Open conversation ─────────────────────────────────────────────────────
    async function openConversation(conv) {
        stopPolling();
        setActiveLead(conv);
        setMessages([]);
        setLastMsgId(0);
        setMobileShowChat(true);
        setMsgText('');
        clearFile();

        try {
            const url = `/manager/whatsapp/${conv.encrypted_id}/messages?after=0`;
            const res  = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            const msgs = data.messages ?? [];
            setMessages(msgs);
            const newLastId = msgs.length ? msgs[msgs.length - 1].id : 0;
            setLastMsgId(newLastId);
            setConversations(prev =>
                prev.map(c => String(c.id) === String(conv.id) ? { ...c, unread_count: 0 } : c)
            );
            startPolling(conv.encrypted_id, newLastId);
        } catch (_) {}

        if (textareaRef.current) textareaRef.current.focus();
    }

    // ── Optimistic message helper ─────────────────────────────────────────────
    function addPendingMsg(body, mediaType = null, mediaUrl = null, mediaFilename = null) {
        const tempId = `pending_${Date.now()}`;
        const msg = {
            _tempId:      tempId,
            id:           tempId,
            message_body: body,
            direction:    'outbound',
            time:         nowTime(),
            date:         todayDateStr(),
            status:       'pending',
            media_type:   mediaType,
            media_url:    mediaUrl,
            media_filename: mediaFilename,
        };
        setMessages(prev => [...prev, msg]);
        return tempId;
    }
    function replacePendingMsg(tempId, realMsg) {
        setMessages(prev => prev.map(m => m._tempId === tempId ? { ...realMsg } : m));
    }
    function removePendingMsg(tempId) {
        setMessages(prev => prev.filter(m => m._tempId !== tempId));
    }

    // ── Send text ─────────────────────────────────────────────────────────────
    async function sendTextMessage(e) {
        e.preventDefault();
        if (!activeLead) return;
        if (pendingFile) { await sendMediaFile(); return; }
        if (!msgText.trim()) return;

        const text   = msgText.trim();
        const tempId = addPendingMsg(text);
        setMsgText('');
        setSending(true);
        try {
            const res  = await fetch(`/manager/leads/${activeLead.encrypted_id}/whatsapp`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                body: JSON.stringify({ message: text }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Send failed');

            const realMsg = {
                id:           data.message_id || Date.now(),
                message_body: data.message || text,
                direction:    'outbound',
                time:         data.time || nowTime(),
                date:         todayDateStr(),
                status:       'sent',
                media_type:   null,
                media_url:    null,
                media_filename: null,
            };
            replacePendingMsg(tempId, realMsg);
            setLastMsgId(realMsg.id);
            updateConvPreview(activeLead.id, text);
        } catch (err) {
            removePendingMsg(tempId);
            showToast(err.message || 'Failed to send message.');
        } finally {
            setSending(false);
        }
    }

    // ── Send media ────────────────────────────────────────────────────────────
    async function sendMediaFile() {
        if (!pendingFile || !activeLead) return;

        const file       = pendingFile;            // capture before clearFile()
        const caption    = msgText.trim();
        const previewUrl = file.type.startsWith('image/')
            ? URL.createObjectURL(file)
            : null;
        const tempId = addPendingMsg(
            caption || `📎 ${file.name}`,
            file.type.startsWith('image/') ? 'image' : 'document',
            previewUrl,
            file.name
        );
        clearFile();
        setMsgText('');
        setSending(true);

        try {
            const fd = new FormData();
            fd.append('_token', csrf());
            fd.append('file', file);
            if (caption) fd.append('caption', caption);

            const res  = await fetch(`/manager/leads/${activeLead.encrypted_id}/whatsapp/media`, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: fd,
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Upload failed');

            const realMsg = {
                id:             data.message_id || Date.now(),
                message_body:   data.message || '',
                direction:      'outbound',
                time:           data.time || nowTime(),
                date:           todayDateStr(),
                status:         'sent',
                media_type:     data.media_type,
                media_url:      data.media_url,
                media_filename: data.media_filename,
            };
            replacePendingMsg(tempId, realMsg);
            setLastMsgId(realMsg.id);
            updateConvPreview(activeLead.id, data.message || '[File]');
            if (previewUrl) URL.revokeObjectURL(previewUrl);  // free blob memory
        } catch (err) {
            removePendingMsg(tempId);
            showToast(err.message || 'Failed to send file.');
        } finally {
            setSending(false);
        }
    }

    // ── Conversation preview bubble-to-top ────────────────────────────────────
    function updateConvPreview(leadId, text) {
        setConversations(prev => {
            const updated = prev.map(c =>
                String(c.id) === String(leadId) ? { ...c, last_message: text } : c
            );
            const idx = updated.findIndex(c => String(c.id) === String(leadId));
            if (idx > 0) { const [item] = updated.splice(idx, 1); updated.unshift(item); }
            return updated;
        });
    }

    // ── File handling ─────────────────────────────────────────────────────────
    function handleFileChange(e) {
        const file = e.target.files[0];
        if (!file) return;
        setPendingFile(file);
        setFilePreviewName(file.name);
        setFilePreviewSize(formatBytes(file.size));
        setFilePreviewIcon(fileIcon(file.type));
    }
    function clearFile() {
        setPendingFile(null);
        setFilePreviewName('');
        setFilePreviewSize('');
        setFilePreviewIcon('attach_file');
        if (fileInputRef.current) fileInputRef.current.value = '';
    }

    // ── Textarea auto-resize ──────────────────────────────────────────────────
    function autoResize(e) {
        e.target.style.height = 'auto';
        e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px';
    }

    const filteredConvs = conversations.filter(c => {
        if (!search) return true;
        const q = search.toLowerCase();
        return c.name?.toLowerCase().includes(q) || c.phone?.includes(q);
    });

    // ─────────────────────────────────────────────────────────────────────────
    return (
        <>
            <Head title="WhatsApp Chat" />

            <div className="dashboard-content" style={{ paddingTop: 0 }}>
                <div className="wa-hub" id="waHub">

                    {/* ══ LEFT: Conversations ══════════════════════════════ */}
                    <div className={`wa-sidebar${mobileShowChat ? ' mobile-hidden' : ''}`} id="waSidebar">
                        <div className="wa-sidebar-header">
                            <div className="wa-sidebar-title">
                                <span className="material-icons">chat</span>
                                WhatsApp Chats
                            </div>
                            <div className="wa-search-box">
                                <span className="material-icons">search</span>
                                <input
                                    type="text"
                                    placeholder="Search lead name or phone…"
                                    autoComplete="off"
                                    value={search}
                                    onChange={e => setSearch(e.target.value)}
                                />
                            </div>
                        </div>

                        <div className="wa-conv-list">
                            {filteredConvs.length === 0 ? (
                                <div className="wa-empty-conv">
                                    <span className="material-icons">chat_bubble_outline</span>
                                    No WhatsApp conversations yet.
                                </div>
                            ) : filteredConvs.map(conv => (
                                <a
                                    key={conv.id}
                                    href="#"
                                    className={`wa-conv-item${activeLead?.id === conv.id ? ' active' : ''}`}
                                    onClick={e => { e.preventDefault(); openConversation(conv); }}
                                >
                                    <div className="wa-conv-avatar">
                                        {conv.name?.charAt(0).toUpperCase()}
                                    </div>
                                    <div className="wa-conv-body">
                                        <div className="wa-conv-name">{conv.name}</div>
                                        <div className="wa-conv-preview">
                                            {conv.last_message
                                                ? <>{String(conv.last_message).slice(0, 40)}{String(conv.last_message).length > 40 ? '…' : ''}</>
                                                : <em>No messages</em>
                                            }
                                        </div>
                                    </div>
                                    <div className="wa-conv-meta">
                                        <span className="wa-conv-time">{conv.last_message_at ?? ''}</span>
                                        {(conv.unread_count ?? 0) > 0 && (
                                            <span className="wa-unread-badge">{conv.unread_count}</span>
                                        )}
                                    </div>
                                </a>
                            ))}
                        </div>
                    </div>

                    {/* ══ RIGHT: Chat Window ═══════════════════════════════ */}
                    <div className="wa-main" id="waMain">
                        {!activeLead ? (
                            <div className="wa-main-empty">
                                <span className="material-icons">forum</span>
                                <p>Select a conversation to start chatting</p>
                            </div>
                        ) : (
                            <>
                                {/* Chat header */}
                                <div className="wa-chat-head">
                                    <button
                                        className="btn btn-sm btn-light wa-back-btn"
                                        id="waBackBtn"
                                        onClick={() => { setMobileShowChat(false); stopPolling(); }}
                                    >
                                        <span className="material-icons" style={{ fontSize: 18 }}>arrow_back</span>
                                    </button>
                                    <div className="wa-chat-head-avatar">
                                        {activeLead.name?.charAt(0).toUpperCase()}
                                    </div>
                                    <div className="wa-chat-head-info">
                                        <div className="wa-chat-head-name">{activeLead.name}</div>
                                        <div className="wa-chat-head-phone">{activeLead.phone}</div>
                                    </div>
                                    <div className="wa-chat-head-actions">
                                        <Link
                                            href={`/manager/leads/${activeLead.encrypted_id}`}
                                            className="btn btn-sm btn-outline-primary"
                                        >
                                            <span className="material-icons" style={{ fontSize: 16 }}>person</span>
                                            Lead Profile
                                        </Link>
                                    </div>
                                </div>

                                {/* Messages */}
                                <MessageList messages={messages} msgAreaRef={msgAreaRef} />

                                {/* Composer */}
                                <div className="wa-composer">
                                    {/* Template quick-replies */}
                                    <div className="wa-template-row">
                                        {TEMPLATES.map(t => (
                                            <button
                                                key={t.label}
                                                type="button"
                                                className="wa-tpl-btn"
                                                onClick={() => {
                                                    setMsgText(t.msg(activeLead.name));
                                                    if (textareaRef.current) {
                                                        textareaRef.current.focus();
                                                        textareaRef.current.style.height = 'auto';
                                                        textareaRef.current.style.height =
                                                            Math.min(textareaRef.current.scrollHeight, 120) + 'px';
                                                    }
                                                }}
                                            >
                                                {t.label}
                                            </button>
                                        ))}
                                    </div>

                                    {/* File preview */}
                                    {pendingFile && (
                                        <div className="wa-file-preview">
                                            <span className="material-icons">{filePreviewIcon}</span>
                                            <span className="wa-file-preview-name">{filePreviewName}</span>
                                            <span className="wa-file-preview-size">{filePreviewSize}</span>
                                            <button
                                                type="button"
                                                className="wa-file-remove"
                                                onClick={clearFile}
                                                title="Remove"
                                            >
                                                <span className="material-icons" style={{ fontSize: 18 }}>close</span>
                                            </button>
                                        </div>
                                    )}

                                    {/* Input row */}
                                    <form className="wa-input-row" onSubmit={sendTextMessage}>
                                        <input
                                            type="file"
                                            ref={fileInputRef}
                                            style={{ display: 'none' }}
                                            accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip"
                                            onChange={handleFileChange}
                                        />
                                        <button
                                            type="button"
                                            className="wa-attach-btn"
                                            title="Attach file"
                                            onClick={() => fileInputRef.current?.click()}
                                        >
                                            <span className="material-icons">attach_file</span>
                                        </button>
                                        <textarea
                                            ref={textareaRef}
                                            rows={1}
                                            placeholder={pendingFile ? 'Add a caption (optional)…' : 'Type a message…'}
                                            autoComplete="off"
                                            value={msgText}
                                            onChange={e => setMsgText(e.target.value)}
                                            onInput={autoResize}
                                            onKeyDown={e => {
                                                if (e.key === 'Enter' && !e.shiftKey) {
                                                    e.preventDefault();
                                                    e.target.form.dispatchEvent(new Event('submit', { bubbles: true }));
                                                }
                                            }}
                                        />
                                        <button
                                            type="submit"
                                            className="wa-send-btn"
                                            disabled={sending || (!msgText.trim() && !pendingFile)}
                                        >
                                            {sending
                                                ? <div className="wa-spinner" />
                                                : <span className="material-icons">send</span>
                                            }
                                        </button>
                                    </form>
                                </div>
                            </>
                        )}
                    </div>

                </div>
            </div>

            {/* Error toast */}
            {toast && <div className="wa-toast">{toast}</div>}

            <style>{`
                .wa-back-btn { display: none; }
                @media (max-width: 768px) {
                    .wa-back-btn { display: flex !important; }
                    .wa-sidebar.mobile-hidden { display: none !important; }
                }
            `}</style>
        </>
    );
}
