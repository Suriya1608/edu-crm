import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';

// ─── Design tokens ─────────────────────────────────────────────────────────────
const ORG   = '#FF5C1A';
const ORGL  = '#FF8042';
const DARKC = '#1A1A2E';
const BORDER = '#EAEAED';
const TEXT   = '#1A1A2E';
const MUTED  = '#9EA3B0';
const WHITE  = '#FFFFFF';
const BGGRAY = '#F5F5F7';

const card = (e = {}) => ({
    background: WHITE,
    borderRadius: 14,
    boxShadow: '0 2px 16px rgba(0,0,0,0.07)',
    border: `1px solid ${BORDER}`,
    ...e,
});

const selStyle = (e = {}) => ({
    fontSize: 13,
    color: TEXT,
    border: `1px solid ${BORDER}`,
    borderRadius: 10,
    padding: '9px 14px',
    background: WHITE,
    cursor: 'pointer',
    appearance: 'none',
    WebkitAppearance: 'none',
    ...e,
});

const TH_STYLE = {
    padding: '11px 16px',
    fontSize: 10.5,
    fontWeight: 700,
    color: MUTED,
    textTransform: 'uppercase',
    letterSpacing: '0.5px',
    background: '#F8F9FB',
    borderBottom: `1px solid ${BORDER}`,
};

const TD_STYLE = {
    padding: '12px 16px',
    fontSize: 13,
    color: TEXT,
    borderBottom: `1px solid ${BORDER}`,
};

// ─── Status badge ─────────────────────────────────────────────────────────────
const BADGE_STYLES = {
    completed: { background: '#D1FAE5', color: '#065F46' },
    overdue:   { background: '#FEE2E2', color: '#991B1B' },
    today:     { background: '#FEF3C7', color: '#92400E' },
    upcoming:  { background: '#DBEAFE', color: '#1E40AF' },
};

const BADGE_LABELS = {
    completed: 'Completed',
    overdue:   'Overdue',
    today:     'Today',
    upcoming:  'Upcoming',
};

function StatusBadge({ label }) {
    const style = BADGE_STYLES[label] ?? { background: '#E5E7EB', color: '#374151' };
    const text  = BADGE_LABELS[label] ?? label;
    return (
        <span style={{
            display: 'inline-block',
            borderRadius: 20,
            fontSize: 11,
            fontWeight: 700,
            padding: '3px 10px',
            ...style,
        }}>
            {text}
        </span>
    );
}

// ─── Reschedule modal ─────────────────────────────────────────────────────────
function RescheduleModal({ modalRef, onSubmit, form }) {
    const inputStyle = {
        fontSize: 13,
        color: TEXT,
        border: `1px solid ${BORDER}`,
        borderRadius: 10,
        padding: '9px 14px',
        background: WHITE,
        width: '100%',
        outline: 'none',
    };

    return (
        <div className="modal fade" id="rescheduleModal" tabIndex={-1} aria-hidden="true" ref={modalRef}>
            <div className="modal-dialog modal-dialog-centered">
                <div className="modal-content" style={{ borderRadius: 16, border: `1px solid ${BORDER}`, boxShadow: '0 8px 40px rgba(0,0,0,0.13)' }}>
                    <div className="modal-header" style={{ borderBottom: `1px solid ${BORDER}`, padding: '18px 24px' }}>
                        <h5 className="modal-title" style={{ fontSize: 16, fontWeight: 700, color: TEXT, margin: 0 }}>
                            Reschedule Followup
                        </h5>
                        <button type="button" className="btn-close" data-bs-dismiss="modal" />
                    </div>
                    <div className="modal-body" style={{ padding: '20px 24px' }}>
                        {form.errors.next_followup && (
                            <div style={{
                                background: '#FEE2E2',
                                color: '#991B1B',
                                borderRadius: 8,
                                padding: '10px 14px',
                                fontSize: 13,
                                marginBottom: 16,
                            }}>
                                {form.errors.next_followup}
                            </div>
                        )}
                        <div style={{ display: 'flex', gap: 14, marginBottom: 16 }}>
                            <div style={{ flex: 7 }}>
                                <label style={{ fontSize: 12, fontWeight: 600, color: MUTED, display: 'block', marginBottom: 6, textTransform: 'uppercase', letterSpacing: '0.4px' }}>
                                    Next Followup Date <span style={{ color: '#EF4444' }}>*</span>
                                </label>
                                <input
                                    type="date"
                                    style={inputStyle}
                                    value={form.data.next_followup}
                                    onChange={e => form.setData('next_followup', e.target.value)}
                                    required
                                />
                            </div>
                            <div style={{ flex: 5 }}>
                                <label style={{ fontSize: 12, fontWeight: 600, color: MUTED, display: 'block', marginBottom: 6, textTransform: 'uppercase', letterSpacing: '0.4px' }}>
                                    Time <span style={{ color: '#EF4444' }}>*</span>
                                </label>
                                <input
                                    type="time"
                                    style={inputStyle}
                                    value={form.data.followup_time}
                                    onChange={e => form.setData('followup_time', e.target.value)}
                                    required
                                />
                            </div>
                        </div>
                        <div>
                            <label style={{ fontSize: 12, fontWeight: 600, color: MUTED, display: 'block', marginBottom: 6, textTransform: 'uppercase', letterSpacing: '0.4px' }}>
                                Remarks
                            </label>
                            <textarea
                                rows={3}
                                style={{ ...inputStyle, resize: 'vertical' }}
                                value={form.data.remarks}
                                onChange={e => form.setData('remarks', e.target.value)}
                            />
                        </div>
                    </div>
                    <div className="modal-footer" style={{ borderTop: `1px solid ${BORDER}`, padding: '16px 24px', gap: 10 }}>
                        <button
                            className="btn btn-secondary"
                            type="button"
                            data-bs-dismiss="modal"
                            style={{ borderRadius: 8, fontSize: 13, fontWeight: 600 }}
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            disabled={form.processing}
                            onClick={onSubmit}
                            style={{
                                background: ORG,
                                color: WHITE,
                                border: 'none',
                                borderRadius: 8,
                                padding: '8px 22px',
                                fontSize: 13,
                                fontWeight: 700,
                                cursor: form.processing ? 'not-allowed' : 'pointer',
                                opacity: form.processing ? 0.7 : 1,
                            }}
                        >
                            {form.processing ? 'Saving…' : 'Save'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

// ─── Call button — one per row, manages idle/calling/active states ─────────────
function CallButton({ phone, leadId }) {
    const [state, setState] = useState('idle'); // idle | calling | active

    useEffect(() => {
        function onAccepted() { setState(prev => prev === 'calling' ? 'active' : prev); }
        function onEnded()    { setState('idle'); }
        document.addEventListener('gc:callAccepted', onAccepted);
        document.addEventListener('gc:callEnded',    onEnded);
        return () => {
            document.removeEventListener('gc:callAccepted', onAccepted);
            document.removeEventListener('gc:callEnded',    onEnded);
        };
    }, []);

    async function handleClick() {
        if (!window.GC) return;
        if (state === 'active' || state === 'calling') {
            window.GC.endCall();
            return;
        }
        setState('calling');
        try {
            await window.GC.startCall(phone, leadId ?? null);
        } catch (_) {
            setState('idle');
        }
    }

    const bgColor =
        state === 'active'  ? '#EF4444' :
        state === 'calling' ? '#F59E0B' :
                              '#10B981';

    const icon =
        state === 'active'  ? 'call_end'    :
        state === 'calling' ? 'ring_volume' :
                              'call';

    return (
        <button
            type="button"
            title="Call"
            disabled={state === 'calling'}
            onClick={handleClick}
            style={{
                background: bgColor,
                color: WHITE,
                border: 'none',
                borderRadius: 8,
                width: 32,
                height: 32,
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                cursor: state === 'calling' ? 'not-allowed' : 'pointer',
                opacity: state === 'calling' ? 0.7 : 1,
                flexShrink: 0,
            }}
        >
            {state === 'calling' ? (
                <span style={{
                    width: 14,
                    height: 14,
                    border: `2px solid ${WHITE}`,
                    borderTopColor: 'transparent',
                    borderRadius: '50%',
                    display: 'inline-block',
                    animation: 'spin 0.7s linear infinite',
                }} />
            ) : (
                <span className="material-icons" style={{ fontSize: 16 }}>{icon}</span>
            )}
        </button>
    );
}

// ─── Action icon button ────────────────────────────────────────────────────────
function IconBtn({ title, onClick, color = ORG, children, disabled }) {
    return (
        <button
            type="button"
            title={title}
            disabled={disabled}
            onClick={onClick}
            style={{
                background: color,
                color: WHITE,
                border: 'none',
                borderRadius: 8,
                width: 32,
                height: 32,
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                cursor: disabled ? 'not-allowed' : 'pointer',
                opacity: disabled ? 0.5 : 1,
                flexShrink: 0,
            }}
        >
            {children}
        </button>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────
export default function Index({ scope, title, followups }) {
    const modalRef   = useRef(null);
    const rescheduleId = useRef(null);
    const [hoveredRow, setHoveredRow] = useState(null);

    const form = useForm({
        next_followup: '',
        followup_time: '',
        remarks:       '',
    });

    // Open the reschedule modal and pre-fill from the row
    function openReschedule(item) {
        rescheduleId.current = item.id;
        form.setData({
            next_followup: item.next_followup  ?? '',
            followup_time: item.followup_time  ?? '',
            remarks:       item.remarks        ?? '',
        });
        const el = modalRef.current;
        if (el && window.bootstrap) {
            window.bootstrap.Modal.getOrCreateInstance(el).show();
        }
    }

    function submitReschedule() {
        const id = rescheduleId.current;
        if (!id) return;

        const date = form.data.next_followup;
        const time = form.data.followup_time;
        if (!date || !time) return;

        const chosen = new Date(date + 'T' + time);
        if (chosen <= new Date()) {
            form.setError('next_followup', 'The scheduled date & time cannot be in the past.');
            return;
        }

        form.post(`/telecaller/followups/${id}/reschedule`, {
            onSuccess: () => {
                const el = modalRef.current;
                if (el && window.bootstrap) {
                    window.bootstrap.Modal.getOrCreateInstance(el).hide();
                }
            },
        });
    }

    function markComplete(id) {
        router.post(`/telecaller/followups/${id}/complete`, {}, {
            preserveScroll: true,
        });
    }

    const TAB_CFG = [
        { key: 'today',     href: '/telecaller/followups/today',     label: 'Today'     },
        { key: 'overdue',   href: '/telecaller/followups/overdue',   label: 'Overdue'   },
        { key: 'upcoming',  href: '/telecaller/followups/upcoming',  label: 'Upcoming'  },
        { key: 'completed', href: '/telecaller/followups/completed', label: 'Completed' },
    ];

    return (
        <>
            <Head title={title} />

            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Work+Sans:wght@300;400;500;600;700;800;900&display=swap');
                *, *::before, *::after { font-family: 'Work Sans', sans-serif !important; }
                @keyframes spin { to { transform: rotate(360deg); } }
            `}</style>

            {/* ── Header + Tab navigation ─────────────────────────────────────── */}
            <div style={{ ...card(), padding: '20px 24px', marginBottom: 20 }}>
                <div style={{ marginBottom: 16 }}>
                    <h3 style={{ fontSize: 20, fontWeight: 700, color: TEXT, margin: 0 }}>{title}</h3>
                    <p style={{ fontSize: 13, color: MUTED, margin: '4px 0 0' }}>Manage followups with quick actions</p>
                </div>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {TAB_CFG.map(tab => {
                        const isActive = scope === tab.key;
                        return (
                            <Link
                                key={tab.key}
                                href={tab.href}
                                style={{
                                    display: 'inline-block',
                                    padding: '7px 18px',
                                    borderRadius: 20,
                                    fontSize: 13,
                                    fontWeight: 600,
                                    textDecoration: 'none',
                                    border: `1.5px solid ${isActive ? ORG : BORDER}`,
                                    background: isActive ? ORG : WHITE,
                                    color: isActive ? WHITE : TEXT,
                                    transition: 'all 0.15s',
                                }}
                            >
                                {tab.label}
                            </Link>
                        );
                    })}
                </div>
            </div>

            {/* ── Followups table ────────────────────────────────────────────── */}
            <div style={card()}>
                {/* Table header toolbar */}
                <div style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '16px 20px',
                    borderBottom: `1px solid ${BORDER}`,
                    flexWrap: 'wrap',
                    gap: 10,
                }}>
                    <div>
                        <span style={{ fontSize: 15, fontWeight: 700, color: TEXT }}>Followup List</span>
                        <span style={{ marginLeft: 10, fontSize: 12, color: MUTED, fontWeight: 500 }}>
                            {followups.total} records
                        </span>
                    </div>
                    <div className="dropdown">
                        <button
                            type="button"
                            data-bs-toggle="dropdown"
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 6,
                                background: WHITE,
                                border: `1px solid ${BORDER}`,
                                borderRadius: 8,
                                padding: '7px 14px',
                                fontSize: 13,
                                fontWeight: 600,
                                color: TEXT,
                                cursor: 'pointer',
                            }}
                        >
                            <span className="material-icons" style={{ fontSize: 15, color: '#10B981' }}>download</span>
                            Export
                        </button>
                        <ul className="dropdown-menu dropdown-menu-end">
                            <li>
                                <a className="dropdown-item d-flex align-items-center gap-2"
                                    href={`/telecaller/followups/${scope}/export?format=excel`}
                                    target="_blank" rel="noreferrer">
                                    <span className="material-icons" style={{ fontSize: 16, color: '#10b981' }}>table_view</span>
                                    Excel (.xlsx)
                                </a>
                            </li>
                            <li>
                                <a className="dropdown-item d-flex align-items-center gap-2"
                                    href={`/telecaller/followups/${scope}/export?format=pdf`}
                                    target="_blank" rel="noreferrer">
                                    <span className="material-icons" style={{ fontSize: 16, color: '#ef4444' }}>picture_as_pdf</span>
                                    PDF
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                {/* Table */}
                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr>
                                {['S.No', 'Date & Time', 'Lead', 'Phone', 'Remarks', 'Status', 'Actions'].map(h => (
                                    <th key={h} style={TH_STYLE}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {followups.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} style={{ ...TD_STYLE, border: 'none' }}>
                                        <div style={{ textAlign: 'center', padding: '52px 0 44px' }}>
                                            <div style={{
                                                width: 72, height: 72, borderRadius: 20,
                                                background: '#F0FDF4',
                                                display: 'flex', alignItems: 'center',
                                                justifyContent: 'center', margin: '0 auto 16px',
                                            }}>
                                                <span className="material-icons" style={{ fontSize: 36, color: '#86EFAC' }}>event_available</span>
                                            </div>
                                            <div style={{ fontSize: 15, fontWeight: 700, color: TEXT, marginBottom: 6 }}>
                                                All clear!
                                            </div>
                                            <div style={{ fontSize: 13, color: MUTED, maxWidth: 260, margin: '0 auto' }}>
                                                No follow-ups found in this view. Check another tab or schedule one from a lead.
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            ) : followups.data.map((item, idx) => {
                                const sno = (followups.current_page - 1) * followups.per_page + idx + 1;
                                const isHovered = hoveredRow === item.id;
                                return (
                                    <tr
                                        key={item.id}
                                        onMouseEnter={() => setHoveredRow(item.id)}
                                        onMouseLeave={() => setHoveredRow(null)}
                                        style={{ background: isHovered ? '#FAFAFA' : WHITE, transition: 'background 0.12s' }}
                                    >
                                        <td style={{ ...TD_STYLE, color: MUTED, fontWeight: 600 }}>{sno}</td>
                                        <td style={TD_STYLE}>
                                            <span style={{ fontWeight: 600, color: TEXT }}>
                                                {item.next_followup_fmt || '—'}
                                            </span>
                                            {item.followup_time_fmt && (
                                                <><br /><span style={{ fontSize: 11, color: MUTED }}>{item.followup_time_fmt}</span></>
                                            )}
                                        </td>
                                        <td style={TD_STYLE}>
                                            <div style={{ fontWeight: 600, color: TEXT }}>{item.lead_name || '—'}</div>
                                            <div style={{ fontSize: 11, color: MUTED }}>{item.lead_code || '—'}</div>
                                        </td>
                                        <td style={{ ...TD_STYLE, fontFamily: 'monospace', fontSize: 13 }}>
                                            {item.lead_phone || '—'}
                                        </td>
                                        <td style={{ ...TD_STYLE, maxWidth: 200, color: item.remarks ? TEXT : MUTED }}>
                                            {item.remarks || '—'}
                                        </td>
                                        <td style={TD_STYLE}>
                                            <StatusBadge label={item.status_label} />
                                        </td>
                                        <td style={TD_STYLE}>
                                            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', alignItems: 'center' }}>
                                                {item.lead_phone && (
                                                    <CallButton
                                                        phone={item.lead_phone}
                                                        leadId={item.lead_id}
                                                    />
                                                )}

                                                {item.encrypted_lead_id && (
                                                    <Link
                                                        href={`/telecaller/leads/${item.encrypted_lead_id}`}
                                                        title="Open Lead"
                                                        style={{
                                                            background: ORG,
                                                            color: WHITE,
                                                            borderRadius: 8,
                                                            width: 32,
                                                            height: 32,
                                                            display: 'inline-flex',
                                                            alignItems: 'center',
                                                            justifyContent: 'center',
                                                            textDecoration: 'none',
                                                            flexShrink: 0,
                                                        }}
                                                    >
                                                        <span className="material-icons" style={{ fontSize: 16 }}>open_in_new</span>
                                                    </Link>
                                                )}

                                                {!item.is_completed && (
                                                    <>
                                                        <IconBtn
                                                            title="Reschedule"
                                                            color="#F59E0B"
                                                            onClick={() => openReschedule(item)}
                                                        >
                                                            <span className="material-icons" style={{ fontSize: 16 }}>event_repeat</span>
                                                        </IconBtn>

                                                        <IconBtn
                                                            title="Mark as completed"
                                                            color="#10B981"
                                                            onClick={() => markComplete(item.id)}
                                                        >
                                                            <span className="material-icons" style={{ fontSize: 16 }}>task_alt</span>
                                                        </IconBtn>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {/* ── Pagination ──────────────────────────────────────────────── */}
                <div style={{
                    padding: '14px 20px',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    flexWrap: 'wrap',
                    gap: 10,
                    borderTop: `1px solid ${BORDER}`,
                }}>
                    <span style={{ fontSize: 12, color: MUTED }}>
                        Showing {followups.from ?? 0}–{followups.to ?? 0} of {followups.total} results
                    </span>
                    {followups.last_page > 1 && (
                        <div style={{ display: 'flex', gap: 4, alignItems: 'center' }}>
                            {followups.links.map((link, i) => {
                                const isActive   = link.active;
                                const isDisabled = !link.url;
                                const base = {
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    minWidth: 34,
                                    height: 34,
                                    padding: '0 10px',
                                    borderRadius: 8,
                                    fontSize: 13,
                                    fontWeight: isActive ? 700 : 500,
                                    border: `1px solid ${isActive ? ORG : BORDER}`,
                                    background: isActive ? ORG : WHITE,
                                    color: isActive ? WHITE : isDisabled ? MUTED : TEXT,
                                    textDecoration: 'none',
                                    cursor: isDisabled ? 'default' : 'pointer',
                                    opacity: isDisabled ? 0.5 : 1,
                                    boxShadow: isActive ? '0 2px 8px rgba(255,92,26,0.18)' : 'none',
                                };
                                return link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        style={base}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span
                                        key={i}
                                        style={base}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>

            {/* ── Reschedule modal ────────────────────────────────────────── */}
            <RescheduleModal
                modalRef={modalRef}
                form={form}
                onSubmit={submitReschedule}
            />
        </>
    );
}
