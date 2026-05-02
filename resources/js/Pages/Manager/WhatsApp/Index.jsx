import { Head, router } from '@inertiajs/react';
import { useState, useEffect, useRef, useCallback } from 'react';

function WaBubble({ msg }) {
    const isOut = msg.direction === 'outbound';
    return (
        <div className={`d-flex mb-2 ${isOut ? 'justify-content-end' : 'justify-content-start'}`}>
            <div style={{
                maxWidth: '72%',
                background: isOut ? '#dcf8c6' : '#fff',
                border: '1px solid #e2e8f0',
                borderRadius: isOut ? '16px 4px 16px 16px' : '4px 16px 16px 16px',
                padding: '8px 12px',
                boxShadow: '0 1px 2px rgba(0,0,0,.08)',
            }}>
                {msg.media_url && (
                    msg.media_type?.startsWith('image') ? (
                        <img src={msg.media_url} alt="media" style={{ maxWidth: 200, borderRadius: 8, marginBottom: 4, display: 'block' }} />
                    ) : (
                        <a href={msg.media_url} target="_blank" rel="noreferrer" className="d-flex align-items-center gap-1 mb-1 text-primary" style={{ fontSize: 13 }}>
                            <span className="material-icons" style={{ fontSize: 16 }}>attach_file</span>Attachment
                        </a>
                    )
                )}
                <div style={{ fontSize: 13, color: '#0f172a', whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>
                    {msg.message_body}
                </div>
                <div className="d-flex justify-content-end align-items-center gap-1 mt-1">
                    <span style={{ fontSize: 10, color: '#94a3b8' }}>{msg.time}</span>
                    {isOut && (
                        <span className="material-icons" style={{ fontSize: 12, color: msg.status === 'read' ? '#34b7f1' : '#94a3b8' }}>
                            {msg.status === 'read' ? 'done_all' : msg.status === 'delivered' ? 'done_all' : 'done'}
                        </span>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function Index({ conversations: initialConversations, activeLead: initialActiveLead, activeMessages: initialMessages, unreadCounts: initialUnread }) {
    const [conversations, setConversations]     = useState(initialConversations ?? []);
    const [activeLead, setActiveLead]           = useState(initialActiveLead ?? null);
    const [messages, setMessages]               = useState(initialMessages ?? []);
    const [unreadCounts, setUnreadCounts]       = useState(initialUnread ?? {});
    const [search, setSearch]                   = useState('');
    const [messageText, setMessageText]         = useState('');
    const [sending, setSending]                 = useState(false);
    const [lastMsgId, setLastMsgId]             = useState(initialMessages.length ? initialMessages[initialMessages.length - 1]?.id : 0);

    const chatEndRef  = useRef(null);
    const pollRef     = useRef(null);
    const fileRef     = useRef(null);

    // Scroll to bottom on new messages
    useEffect(() => {
        chatEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    // Poll for new messages when a lead is active
    const pollMessages = useCallback(async () => {
        if (!activeLead) return;
        try {
            const res = await fetch(`/manager/whatsapp/${activeLead.encrypted_id}/messages?after=${lastMsgId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            if (data.ok && data.messages?.length) {
                setMessages(prev => [...prev, ...data.messages]);
                setLastMsgId(data.messages[data.messages.length - 1].id);
            }
            if (data.unread) setUnreadCounts(data.unread);
        } catch (_) {}
    }, [activeLead, lastMsgId]);

    useEffect(() => {
        if (!activeLead) return;
        pollRef.current = setInterval(pollMessages, 7000);
        return () => clearInterval(pollRef.current);
    }, [pollMessages, activeLead]);

    function openConversation(conv) {
        router.get('/manager/whatsapp', { lead: conv.encrypted_id }, { preserveState: false });
    }

    async function sendMessage(e) {
        e.preventDefault();
        if (!messageText.trim() || !activeLead || sending) return;
        setSending(true);
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const res = await fetch(`/manager/leads/${activeLead.encrypted_id}/whatsapp`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ message: messageText }),
            });
            const data = await res.json();
            if (data.ok) {
                setMessages(prev => [...prev, data.message]);
                setMessageText('');
                setLastMsgId(data.message?.id ?? lastMsgId);
            }
        } catch (_) {} finally {
            setSending(false);
        }
    }

    async function sendFile(e) {
        const file = e.target.files?.[0];
        if (!file || !activeLead) return;
        const fd = new FormData();
        fd.append('file', file);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        try {
            const res = await fetch(`/manager/leads/${activeLead.encrypted_id}/whatsapp/media`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            });
            const data = await res.json();
            if (data.ok) {
                setMessages(prev => [...prev, data.message]);
                setLastMsgId(data.message?.id ?? lastMsgId);
            }
        } catch (_) {}
        fileRef.current.value = '';
    }

    const filtered = conversations.filter(c =>
        !search || c.name?.toLowerCase().includes(search.toLowerCase()) || c.phone?.includes(search)
    );

    return (
        <>
            <Head title="WhatsApp Chat" />

            <div style={{
                display: 'flex',
                height: 'calc(100vh - 130px)',
                background: '#fff',
                borderRadius: 16,
                border: '1px solid #e2e8f0',
                overflow: 'hidden',
                boxShadow: '0 2px 16px rgba(19,127,236,.06)',
            }}>
                {/* ── Left: conversation list ─────────────────────────── */}
                <div style={{ width: 320, flexShrink: 0, borderRight: '1px solid #e2e8f0', display: 'flex', flexDirection: 'column' }}>
                    <div style={{ padding: '16px 14px 10px', borderBottom: '1px solid #e2e8f0' }}>
                        <div style={{ fontSize: 16, fontWeight: 700, color: '#0f172a', marginBottom: 10, display: 'flex', alignItems: 'center', gap: 8 }}>
                            <span className="material-icons" style={{ color: '#25d366', fontSize: 22 }}>chat</span>
                            WhatsApp Chats
                        </div>
                        <div style={{ position: 'relative' }}>
                            <span className="material-icons" style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', fontSize: 17, color: '#94a3b8', pointerEvents: 'none' }}>search</span>
                            <input
                                type="text"
                                placeholder="Search lead name or phone..."
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                style={{ width: '100%', padding: '7px 10px 7px 32px', border: '1.5px solid #e2e8f0', borderRadius: 10, fontSize: 13, background: '#f6f7f8', outline: 'none' }}
                            />
                        </div>
                    </div>

                    <div style={{ flex: 1, overflowY: 'auto' }}>
                        {filtered.length === 0 ? (
                            <div className="text-center py-5 text-muted" style={{ fontSize: 13 }}>
                                <span className="material-icons d-block mb-2" style={{ fontSize: 36, opacity: 0.3 }}>chat_bubble_outline</span>
                                No conversations
                            </div>
                        ) : filtered.map(conv => {
                            const isActive = activeLead?.id === conv.id;
                            const unread   = unreadCounts[conv.id] ?? 0;
                            return (
                                <div key={conv.id}
                                    onClick={() => openConversation(conv)}
                                    style={{
                                        display: 'flex', alignItems: 'center', gap: 10,
                                        padding: '11px 14px',
                                        cursor: 'pointer',
                                        borderBottom: '1px solid #f1f5f9',
                                        background: isActive ? '#eff6ff' : 'transparent',
                                        transition: 'background .15s',
                                    }}>
                                    <div style={{ width: 40, height: 40, borderRadius: '50%', background: '#6366f1', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                                        <span style={{ color: '#fff', fontWeight: 700, fontSize: 15 }}>{conv.name?.[0]?.toUpperCase()}</span>
                                    </div>
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                            <span style={{ fontWeight: 600, fontSize: 13, color: '#0f172a' }}>{conv.name}</span>
                                            <span style={{ fontSize: 10, color: '#94a3b8' }}>{conv.last_message_at}</span>
                                        </div>
                                        <div style={{ fontSize: 12, color: '#64748b', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', display: 'flex', justifyContent: 'space-between' }}>
                                            <span style={{ flex: 1, minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis' }}>
                                                {conv.last_message ?? 'No messages'}
                                            </span>
                                            {unread > 0 && (
                                                <span style={{ background: '#25d366', color: '#fff', borderRadius: '50%', fontSize: 10, fontWeight: 700, minWidth: 18, height: 18, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0, marginLeft: 6 }}>
                                                    {unread}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* ── Right: chat area ────────────────────────────────── */}
                {activeLead ? (
                    <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
                        {/* Header */}
                        <div style={{ padding: '14px 18px', borderBottom: '1px solid #e2e8f0', background: '#fff', display: 'flex', alignItems: 'center', gap: 12 }}>
                            <div style={{ width: 38, height: 38, borderRadius: '50%', background: '#6366f1', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                <span style={{ color: '#fff', fontWeight: 700, fontSize: 15 }}>{activeLead.name?.[0]?.toUpperCase()}</span>
                            </div>
                            <div>
                                <div style={{ fontWeight: 700, fontSize: 14, color: '#0f172a' }}>{activeLead.name}</div>
                                <div style={{ fontSize: 12, color: '#64748b' }}>{activeLead.phone}</div>
                            </div>
                        </div>

                        {/* Messages */}
                        <div style={{ flex: 1, overflowY: 'auto', padding: '16px', background: '#f0f2f5' }}>
                            {messages.length === 0 ? (
                                <div className="text-center text-muted py-5" style={{ fontSize: 13 }}>No messages yet</div>
                            ) : messages.map(msg => <WaBubble key={msg.id} msg={msg} />)}
                            <div ref={chatEndRef} />
                        </div>

                        {/* Send box */}
                        <div style={{ padding: '10px 14px', borderTop: '1px solid #e2e8f0', background: '#fff', display: 'flex', gap: 8, alignItems: 'center' }}>
                            <input ref={fileRef} type="file" className="d-none" onChange={sendFile} />
                            <button type="button" className="btn btn-light btn-sm" onClick={() => fileRef.current?.click()} title="Attach file">
                                <span className="material-icons" style={{ fontSize: 18 }}>attach_file</span>
                            </button>
                            <form onSubmit={sendMessage} className="d-flex gap-2 flex-grow-1">
                                <input
                                    type="text"
                                    className="form-control"
                                    placeholder="Type a message..."
                                    value={messageText}
                                    onChange={e => setMessageText(e.target.value)}
                                    style={{ borderRadius: 20, fontSize: 13 }}
                                />
                                <button type="submit" className="btn btn-success btn-sm px-3" disabled={sending || !messageText.trim()}>
                                    <span className="material-icons" style={{ fontSize: 18 }}>send</span>
                                </button>
                            </form>
                        </div>
                    </div>
                ) : (
                    <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', flexDirection: 'column', gap: 12, color: '#94a3b8' }}>
                        <span className="material-icons" style={{ fontSize: 56, opacity: 0.3 }}>chat</span>
                        <p style={{ fontSize: 14 }}>Select a conversation to start chatting</p>
                    </div>
                )}
            </div>
        </>
    );
}
