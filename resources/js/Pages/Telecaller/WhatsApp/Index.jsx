import { Head } from '@inertiajs/react';
import { useState, useEffect, useRef, useCallback } from 'react';

// ─── Template quick-replies ───────────────────────────────────────────────────
const TEMPLATES = [
    { label: 'Intro',       msg: (n) => `Hello ${n}, thanks for your interest. Can we connect now?` },
    { label: 'Follow-up',   msg: ()  => `Reminder: your follow-up is scheduled. Please confirm your preferred time.` },
    { label: 'Course Info', msg: ()  => `Please share your preferred course and we'll guide you with next steps.` },
    { label: 'Admission',   msg: (n) => `Hi ${n}, the admission process is now open. Let's get you enrolled!` },
];

// ─── Helpers ─────────────────────────────────────────────────────────────────
function now() {
    return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
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

// ─── MediaBubble ─────────────────────────────────────────────────────────────
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

// ─── Single Message Bubble ────────────────────────────────────────────────────
function Bubble({ msg }) {
    const showText = msg.message_body &&
        !['image', 'audio', 'video'].includes(msg.media_type || '');
    return (
        <div className={`wa-bubble ${msg.direction}`} data-msg-id={msg.id}>
            <MediaBubble
                mediaType={msg.media_type}
                mediaUrl={msg.media_url}
                mediaFilename={msg.media_filename}
            />
            {showText && <div className="wa-bubble-text">{msg.message_body}</div>}
            <div className="wa-bubble-meta">
                <span className="wa-bubble-time">{msg.time}</span>
                {msg.direction === 'outbound' && (
                    <span className={`material-icons wa-tick ${msg.status || 'sent'}`}>done_all</span>
                )}
            </div>
        </div>
    );
}

// ─── Messages list with day-dividers ─────────────────────────────────────────
function MessageList({ messages, msgAreaRef }) {
    // Group messages by date, inserting dividers
    const rows = [];
    let lastDate = null;
    messages.forEach((m) => {
        if (m.date !== lastDate) {
            rows.push({ type: 'divider', key: `d-${m.date}-${m.id}`, date: m.date });
            lastDate = m.date;
        }
        rows.push({ type: 'msg', key: m.id, msg: m });
    });

    return (
        <div className="wa-messages-area" ref={msgAreaRef}>
            {rows.map(r =>
                r.type === 'divider'
                    ? <div key={r.key} className="wa-day-divider">{r.date}</div>
                    : <Bubble key={r.key} msg={r.msg} />
            )}
        </div>
    );
}

// ─── Main Page ────────────────────────────────────────────────────────────────
export default function Index({
    conversations: initialConversations,
    activeLead: initialActiveLead,
    activeMessages: initialActiveMessages,
    sendUrlPattern,
    mediaUrlPattern,
    messagesUrlPattern,
}) {
    // ── Conversation list (local copy for reordering + unread updates) ────────
    const [conversations, setConversations] = useState(
        (initialConversations ?? []).map(c => ({ ...c }))
    );

    // ── Active chat state ─────────────────────────────────────────────────────
    const [activeLead, setActiveLead]   = useState(initialActiveLead ?? null);
    const [messages,   setMessages]     = useState(initialActiveMessages ?? []);
    const [lastMsgId,  setLastMsgId]    = useState(() => {
        const msgs = initialActiveMessages ?? [];
        return msgs.length ? msgs[msgs.length - 1].id : 0;
    });

    // ── UI state ──────────────────────────────────────────────────────────────
    const [search,    setSearch]    = useState('');
    const [sending,   setSending]   = useState(false);
    const [msgText,   setMsgText]   = useState('');
    const [toast,     setToast]     = useState(null);
    const [mobileShowChat, setMobileShowChat] = useState(!!initialActiveLead);

    // ── File state ────────────────────────────────────────────────────────────
    const [pendingFile,     setPendingFile]     = useState(null);
    const [filePreviewName, setFilePreviewName] = useState('');
    const [filePreviewSize, setFilePreviewSize] = useState('');
    const [filePreviewIcon, setFilePreviewIcon] = useState('attach_file');

    // ── Refs ──────────────────────────────────────────────────────────────────
    const msgAreaRef  = useRef(null);
    const textareaRef = useRef(null);
    const fileInputRef = useRef(null);
    const pollRef     = useRef(null);
    const toastTimer  = useRef(null);

    // ── CSRF token ────────────────────────────────────────────────────────────
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ── Scroll to bottom ─────────────────────────────────────────────────────
    const scrollBottom = useCallback((smooth = true) => {
        if (!msgAreaRef.current) return;
        msgAreaRef.current.scrollTo({
            top: msgAreaRef.current.scrollHeight,
            behavior: smooth ? 'smooth' : 'instant',
        });
    }, []);

    // Scroll to bottom when messages change
    useEffect(() => { scrollBottom(); }, [messages, scrollBottom]);

    // ── Toast helper ─────────────────────────────────────────────────────────
    function showToast(msg) {
        setToast(msg);
        if (toastTimer.current) clearTimeout(toastTimer.current);
        toastTimer.current = setTimeout(() => setToast(null), 3500);
    }

    // ── Polling ───────────────────────────────────────────────────────────────
    const fetchMessages = useCallback(async (encryptedId, currentLastId) => {
        if (!encryptedId) return;
        try {
            const url = messagesUrlPattern.replace('__ID__', encryptedId)
                + '?after=' + currentLastId;
            const res  = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();

            if (data.messages?.length > 0) {
                setMessages(prev => {
                    const existing = new Set(prev.map(m => m.id));
                    const fresh = data.messages.filter(m => !existing.has(m.id));
                    if (!fresh.length) return prev;
                    setLastMsgId(fresh[fresh.length - 1].id);
                    // Update conversation preview with the latest incoming message
                    const lastNew = fresh[fresh.length - 1];
                    setConversations(prev2 => {
                        const updated = prev2.map(c =>
                            String(c.id) === String(data.lead?.id)
                                ? { ...c, last_message: { body: lastNew.message_body, direction: lastNew.direction, time: lastNew.time } }
                                : c
                        );
                        // Bubble the active conv to the top
                        const idx = updated.findIndex(c => String(c.id) === String(data.lead?.id));
                        if (idx > 0) {
                            const [item] = updated.splice(idx, 1);
                            updated.unshift(item);
                        }
                        return updated;
                    });
                    return [...prev, ...fresh];
                });
            }

            // Update tick statuses
            if (data.statuses && msgAreaRef.current) {
                Object.entries(data.statuses).forEach(([id, status]) => {
                    const bubble = msgAreaRef.current?.querySelector(`[data-msg-id="${id}"]`);
                    const tick   = bubble?.querySelector('.wa-tick');
                    if (tick) tick.className = `material-icons wa-tick ${status}`;
                });
            }

            // Update unread counts in conversation list
            if (data.unread) {
                setConversations(prev =>
                    prev.map(c => ({
                        ...c,
                        unread_count: data.unread[c.id] ?? 0,
                    }))
                );
            }
        } catch (_) {}
    }, [messagesUrlPattern]);

    function startPolling(encryptedId, lastId) {
        stopPolling();
        // Capture in closure — the interval needs to always use the latest lastMsgId.
        // We do this via a ref trick below.
        pollRef.current = { encryptedId, lastIdRef: { current: lastId } };
        const interval = setInterval(() => {
            fetchMessages(pollRef.current.encryptedId, pollRef.current.lastIdRef.current);
        }, 7000);
        pollRef.current.interval = interval;
    }

    function stopPolling() {
        if (pollRef.current?.interval) {
            clearInterval(pollRef.current.interval);
            pollRef.current = null;
        }
    }

    // Keep the pollRef's lastIdRef in sync with state
    useEffect(() => {
        if (pollRef.current) pollRef.current.lastIdRef.current = lastMsgId;
    }, [lastMsgId]);

    // Start polling if there's an active lead on initial render
    useEffect(() => {
        if (initialActiveLead) {
            startPolling(initialActiveLead.encrypted_id, lastMsgId);
        }
        return () => stopPolling();
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // ── Open a conversation ───────────────────────────────────────────────────
    async function openConversation(conv) {
        stopPolling();
        setActiveLead(conv);
        setMessages([]);
        setLastMsgId(0);
        setMobileShowChat(true);
        setMsgText('');
        clearFile();

        // Fetch full message history for this lead
        try {
            const url = messagesUrlPattern.replace('__ID__', conv.encrypted_id) + '?after=0';
            const res  = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            const msgs = data.messages ?? [];
            setMessages(msgs);
            const newLastId = msgs.length ? msgs[msgs.length - 1].id : 0;
            setLastMsgId(newLastId);

            // Clear unread for this lead
            setConversations(prev =>
                prev.map(c => String(c.id) === String(conv.id) ? { ...c, unread_count: 0 } : c)
            );

            startPolling(conv.encrypted_id, newLastId);
        } catch (_) {}

        if (textareaRef.current) textareaRef.current.focus();
    }

    // ── Send text message ─────────────────────────────────────────────────────
    async function sendTextMessage(e) {
        e.preventDefault();
        if (!activeLead || !msgText.trim()) return;
        if (pendingFile) { await sendMediaFile(); return; }

        setSending(true);
        try {
            const url = sendUrlPattern.replace('__ID__', activeLead.encrypted_id);
            const res  = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                body: JSON.stringify({ message: msgText.trim() }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Send failed');

            const newMsg = {
                id:           data.message_id || Date.now(),
                message_body: data.message || msgText.trim(),
                direction:    'outbound',
                time:         data.time || now(),
                date:         new Date().toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }),
                status:       'sent',
                media_type:   null,
                media_url:    null,
            };
            setMessages(prev => [...prev, newMsg]);
            setLastMsgId(newMsg.id);
            updateConvPreview(activeLead.id, msgText.trim(), 'outbound');
            setMsgText('');
        } catch (err) {
            showToast(err.message || 'Failed to send message.');
        } finally {
            setSending(false);
        }
    }

    // ── Send media file ───────────────────────────────────────────────────────
    async function sendMediaFile() {
        if (!pendingFile || !activeLead) return;
        setSending(true);
        try {
            const url = mediaUrlPattern.replace('__ID__', activeLead.encrypted_id);
            const fd  = new FormData();
            fd.append('_token', csrf());
            fd.append('file', pendingFile);
            if (msgText.trim()) fd.append('caption', msgText.trim());

            const res  = await fetch(url, { method: 'POST', headers: { Accept: 'application/json' }, body: fd });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Upload failed');

            const newMsg = {
                id:             data.message_id || Date.now(),
                message_body:   data.message || '',
                direction:      'outbound',
                time:           data.time || now(),
                date:           new Date().toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }),
                status:         'sent',
                media_type:     data.media_type,
                media_url:      data.media_url,
                media_filename: data.media_filename,
            };
            setMessages(prev => [...prev, newMsg]);
            setLastMsgId(newMsg.id);
            updateConvPreview(activeLead.id, data.message || '[File]', 'outbound');
            clearFile();
            setMsgText('');
        } catch (err) {
            showToast(err.message || 'Failed to send file.');
        } finally {
            setSending(false);
        }
    }

    // ── Update conversation preview & bubble to top ───────────────────────────
    function updateConvPreview(leadId, text, direction) {
        setConversations(prev => {
            const updated = prev.map(c =>
                String(c.id) === String(leadId)
                    ? { ...c, last_message: { body: text, direction, time: now() } }
                    : c
            );
            const idx = updated.findIndex(c => String(c.id) === String(leadId));
            if (idx > 0) {
                const [item] = updated.splice(idx, 1);
                updated.unshift(item);
            }
            return updated;
        });
    }

    // ── File attachment ───────────────────────────────────────────────────────
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

    // ── Filtered conversation list ────────────────────────────────────────────
    const filteredConvs = conversations.filter(c => {
        if (!search) return true;
        const q = search.toLowerCase();
        return c.name.toLowerCase().includes(q) || c.phone.toLowerCase().includes(q);
    });

    // ─────────────────────────────────────────────────────────────────────────
    return (
        <>
            <Head title="WhatsApp Chat" />

            <div className="dashboard-content" style={{ paddingTop: 0 }}>
                <div className="wa-hub" id="waHub">

                    {/* ══ LEFT: Conversations ══════════════════════════════ */}
                    <div
                        className={`wa-sidebar${mobileShowChat ? ' mobile-hidden' : ''}`}
                        id="waSidebar"
                    >
                        <div className="wa-sidebar-header">
                            <div className="wa-sidebar-title">
                                <span className="material-icons">chat</span>
                                WhatsApp Chat
                            </div>
                            <div className="wa-search-box">
                                <span className="material-icons">search</span>
                                <input
                                    type="text"
                                    placeholder="Search leads…"
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
                                        {conv.name.charAt(0).toUpperCase()}
                                    </div>
                                    <div className="wa-conv-body">
                                        <div className="wa-conv-name">{conv.name}</div>
                                        <div className="wa-conv-preview">
                                            {conv.last_message ? (
                                                <>
                                                    {conv.last_message.direction === 'outbound' && (
                                                        <span className="material-icons" style={{ fontSize: 12, verticalAlign: 'middle' }}>done_all</span>
                                                    )}
                                                    {' '}{(conv.last_message.body || '').slice(0, 38)}
                                                    {(conv.last_message.body || '').length > 38 ? '…' : ''}
                                                </>
                                            ) : <em>No messages</em>}
                                        </div>
                                    </div>
                                    <div className="wa-conv-meta">
                                        <span className="wa-conv-time">
                                            {conv.last_message?.time ?? ''}
                                        </span>
                                        {conv.unread_count > 0 && (
                                            <span className="wa-unread-badge">
                                                {conv.unread_count}
                                            </span>
                                        )}
                                    </div>
                                </a>
                            ))}
                        </div>
                    </div>

                    {/* ══ RIGHT: Chat Window ═══════════════════════════════ */}
                    <div
                        className={`wa-main${!mobileShowChat && !activeLead ? ' ' : ''}${mobileShowChat ? '' : activeLead ? '' : ''}`}
                        id="waMain"
                        style={{ display: 'flex', flexDirection: 'column' }}
                    >
                        {!activeLead ? (
                            <div className="wa-main-empty">
                                <span className="material-icons">forum</span>
                                <p>Select a conversation to start chatting</p>
                            </div>
                        ) : (
                            <>
                                {/* Chat Header */}
                                <div className="wa-chat-head">
                                    <button
                                        className="btn btn-sm btn-light"
                                        style={{ display: 'none' }}
                                        id="waBackBtn"
                                        onClick={() => {
                                            setMobileShowChat(false);
                                            stopPolling();
                                        }}
                                    >
                                        <span className="material-icons" style={{ fontSize: 18 }}>arrow_back</span>
                                    </button>
                                    <div className="wa-chat-head-avatar">
                                        {activeLead.name.charAt(0).toUpperCase()}
                                    </div>
                                    <div className="wa-chat-head-info">
                                        <div className="wa-chat-head-name">{activeLead.name}</div>
                                        <div className="wa-chat-head-phone">{activeLead.phone}</div>
                                    </div>
                                    <div className="wa-chat-head-actions">
                                        <a
                                            href={activeLead.lead_url}
                                            className="btn btn-sm btn-outline-primary"
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <span className="material-icons" style={{ fontSize: 16 }}>open_in_new</span>
                                            Lead Profile
                                        </a>
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
                                            {sending ? (
                                                <div className="wa-spinner" />
                                            ) : (
                                                <span className="material-icons">send</span>
                                            )}
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

            {/* Mobile back button wiring — inject CSS to show the back button on small screens */}
            <style>{`
                @media (max-width: 768px) {
                    #waBackBtn { display: flex !important; }
                    .wa-sidebar.mobile-hidden { display: none !important; }
                }
            `}</style>
        </>
    );
}
