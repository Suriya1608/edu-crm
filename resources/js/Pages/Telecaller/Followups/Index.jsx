import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';

// ─── Status badge ─────────────────────────────────────────────────────────────
const BADGE_MAP = {
    completed: { cls: 'bg-success',           label: 'Completed' },
    overdue:   { cls: 'bg-danger',             label: 'Overdue'   },
    today:     { cls: 'bg-warning text-dark',  label: 'Today'     },
    upcoming:  { cls: 'bg-info text-dark',     label: 'Upcoming'  },
};

function StatusBadge({ label }) {
    const cfg = BADGE_MAP[label] ?? { cls: 'bg-secondary', label };
    return <span className={`badge ${cfg.cls}`}>{cfg.label}</span>;
}

// ─── Reschedule modal ─────────────────────────────────────────────────────────
function RescheduleModal({ modalRef, onSubmit, form }) {
    return (
        <div className="modal fade" id="rescheduleModal" tabIndex={-1} aria-hidden="true" ref={modalRef}>
            <div className="modal-dialog">
                <div className="modal-content">
                    <div className="modal-header">
                        <h5 className="modal-title">Reschedule Followup</h5>
                        <button type="button" className="btn-close" data-bs-dismiss="modal" />
                    </div>
                    <div className="modal-body">
                        {form.errors.next_followup && (
                            <div className="alert alert-danger py-2 mb-3">{form.errors.next_followup}</div>
                        )}
                        <div className="row g-3 mb-3">
                            <div className="col-7">
                                <label className="form-label">
                                    Next Followup Date <span className="text-danger">*</span>
                                </label>
                                <input
                                    type="date"
                                    className="form-control"
                                    value={form.data.next_followup}
                                    onChange={e => form.setData('next_followup', e.target.value)}
                                    required
                                />
                            </div>
                            <div className="col-5">
                                <label className="form-label">
                                    Time <span className="text-danger">*</span>
                                </label>
                                <input
                                    type="time"
                                    className="form-control"
                                    value={form.data.followup_time}
                                    onChange={e => form.setData('followup_time', e.target.value)}
                                    required
                                />
                            </div>
                        </div>
                        <div className="mb-0">
                            <label className="form-label">Remarks</label>
                            <textarea
                                className="form-control"
                                rows={3}
                                value={form.data.remarks}
                                onChange={e => form.setData('remarks', e.target.value)}
                            />
                        </div>
                    </div>
                    <div className="modal-footer">
                        <button className="btn btn-secondary" type="button" data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button
                            className="btn btn-primary"
                            type="button"
                            disabled={form.processing}
                            onClick={onSubmit}
                        >
                            Save
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

    const btnCls =
        state === 'active'  ? 'btn btn-sm btn-danger'          :
        state === 'calling' ? 'btn btn-sm btn-warning'         :
                              'btn btn-sm btn-outline-success';
    const icon =
        state === 'active'  ? 'call_end'    :
        state === 'calling' ? 'ring_volume' :
                              'call';

    return (
        <button
            type="button"
            className={btnCls}
            title="Call"
            disabled={state === 'calling'}
            onClick={handleClick}
        >
            <span className="material-icons" style={{ fontSize: 16 }}>{icon}</span>
        </button>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────
export default function Index({ scope, title, followups }) {
    const modalRef   = useRef(null);
    const rescheduleId = useRef(null);

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
        { key: 'today',     href: '/telecaller/followups/today',     label: 'Today',     activeCls: 'btn-primary',                   inactiveCls: 'btn-outline-primary'  },
        { key: 'overdue',   href: '/telecaller/followups/overdue',   label: 'Overdue',   activeCls: 'btn-danger',                    inactiveCls: 'btn-outline-danger'   },
        { key: 'upcoming',  href: '/telecaller/followups/upcoming',  label: 'Upcoming',  activeCls: 'btn-warning text-dark',         inactiveCls: 'btn-outline-warning text-dark' },
        { key: 'completed', href: '/telecaller/followups/completed', label: 'Completed', activeCls: 'btn-success',                   inactiveCls: 'btn-outline-success'  },
    ];

    return (
        <>
            <Head title={title} />

            {/* ── Scope tabs ─────────────────────────────────────────────────── */}
            <div className="chart-card mb-3">
                <div className="chart-header mb-2">
                    <h3>{title}</h3>
                    <p>Manage followups with quick actions</p>
                </div>
                <div className="d-flex gap-2 flex-wrap">
                    {TAB_CFG.map(tab => (
                        <Link
                            key={tab.key}
                            href={tab.href}
                            className={`btn btn-sm ${scope === tab.key ? tab.activeCls : tab.inactiveCls}`}
                        >
                            {tab.label}
                        </Link>
                    ))}
                </div>
            </div>

            {/* ── Followups table ────────────────────────────────────────────── */}
            <div className="custom-table">
                <div className="table-header">
                    <h3>Followup List</h3>
                    <span className="text-muted" style={{ fontSize: 12 }}>
                        {followups.total} records
                    </span>
                </div>

                <div className="table-responsive">
                    <table className="table mb-0">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Date &amp; Time</th>
                                <th>Lead</th>
                                <th>Phone</th>
                                <th>Remarks</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {followups.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="text-center py-4 text-muted">
                                        No followups found.
                                    </td>
                                </tr>
                            ) : followups.data.map((item, idx) => {
                                const sno = (followups.current_page - 1) * followups.per_page + idx + 1;
                                return (
                                    <tr key={item.id}>
                                        <td>{sno}</td>
                                        <td>
                                            {item.next_followup_fmt || '—'}
                                            {item.followup_time_fmt && (
                                                <><br /><small className="text-muted">{item.followup_time_fmt}</small></>
                                            )}
                                        </td>
                                        <td>
                                            <div className="fw-semibold">{item.lead_name || '—'}</div>
                                            <small className="text-muted">{item.lead_code || '—'}</small>
                                        </td>
                                        <td>{item.lead_phone || '—'}</td>
                                        <td>{item.remarks || '—'}</td>
                                        <td><StatusBadge label={item.status_label} /></td>
                                        <td>
                                            <div className="d-flex gap-1 flex-wrap">
                                                {item.lead_phone && (
                                                    <CallButton
                                                        phone={item.lead_phone}
                                                        leadId={item.lead_id}
                                                    />
                                                )}

                                                {item.encrypted_lead_id && (
                                                    <Link
                                                        href={`/telecaller/leads/${item.encrypted_lead_id}`}
                                                        className="btn btn-sm btn-outline-primary"
                                                        title="Open Lead"
                                                    >
                                                        <span className="material-icons" style={{ fontSize: 16 }}>open_in_new</span>
                                                    </Link>
                                                )}

                                                {!item.is_completed && (
                                                    <>
                                                        <button
                                                            type="button"
                                                            className="btn btn-sm btn-outline-warning text-dark"
                                                            title="Reschedule"
                                                            onClick={() => openReschedule(item)}
                                                        >
                                                            <span className="material-icons" style={{ fontSize: 16 }}>event_repeat</span>
                                                        </button>

                                                        <button
                                                            type="button"
                                                            className="btn btn-sm btn-outline-success"
                                                            title="Mark as completed"
                                                            onClick={() => markComplete(item.id)}
                                                        >
                                                            <span className="material-icons" style={{ fontSize: 16 }}>task_alt</span>
                                                        </button>
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

                {/* ── Pagination ──────────────────────────────────────────── */}
                <div className="p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small className="text-muted">
                        Showing {followups.from ?? 0}–{followups.to ?? 0} of {followups.total} results
                    </small>
                    {followups.last_page > 1 && (
                        <nav>
                            <ul className="pagination pagination-sm mb-0">
                                {followups.links.map((link, i) => (
                                    <li key={i} className={[
                                        'page-item',
                                        link.active ? 'active'   : '',
                                        !link.url   ? 'disabled' : '',
                                    ].join(' ')}>
                                        {link.url ? (
                                            <Link href={link.url} className="page-link"
                                                dangerouslySetInnerHTML={{ __html: link.label }} />
                                        ) : (
                                            <span className="page-link"
                                                dangerouslySetInnerHTML={{ __html: link.label }} />
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </nav>
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
