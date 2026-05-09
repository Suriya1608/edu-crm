import { Head, Link, router } from '@inertiajs/react';

const TC_PALETTE = [
    { bg: '#ede9fe', color: '#6d28d9' }, // violet
    { bg: '#dbeafe', color: '#1d4ed8' }, // blue
    { bg: '#d1fae5', color: '#065f46' }, // green
    { bg: '#fef3c7', color: '#92400e' }, // amber
    { bg: '#fce7f3', color: '#9d174d' }, // pink
    { bg: '#cffafe', color: '#155e75' }, // cyan
    { bg: '#fee2e2', color: '#991b1b' }, // red
    { bg: '#f3e8ff', color: '#7e22ce' }, // purple
];

function tcColor(name) {
    if (!name) return TC_PALETTE[0];
    let h = 0;
    for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) >>> 0;
    return TC_PALETTE[h % TC_PALETTE.length];
}

function TelecallerBadge({ name }) {
    if (!name) return <span className="text-muted">—</span>;
    const { bg, color } = tcColor(name);
    const initials = name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
    return (
        <div className="d-flex align-items-center gap-2">
            <span style={{
                display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                width: 28, height: 28, borderRadius: '50%',
                background: bg, color, fontSize: 11, fontWeight: 700, flexShrink: 0,
            }}>{initials}</span>
            <span style={{ fontSize: 13, fontWeight: 500, color }}>{name}</span>
        </div>
    );
}

const BADGE_MAP = {
    completed: { cls: 'bg-success',          label: 'Completed' },
    overdue:   { cls: 'bg-danger',            label: 'Overdue'   },
    today:     { cls: 'bg-warning text-dark', label: 'Today'     },
    upcoming:  { cls: 'bg-info text-dark',    label: 'Upcoming'  },
};

function StatusBadge({ label }) {
    const cfg = BADGE_MAP[label] ?? { cls: 'bg-secondary', label };
    return <span className={`badge ${cfg.cls}`}>{cfg.label}</span>;
}

const TAB_CFG = [
    { key: 'today',    href: '/manager/followups/today',    label: 'Today',    activeCls: 'btn-primary',           inactiveCls: 'btn-outline-primary'           },
    { key: 'overdue',  href: '/manager/followups/overdue',  label: 'Overdue',  activeCls: 'btn-danger',            inactiveCls: 'btn-outline-danger'            },
    { key: 'upcoming', href: '/manager/followups/upcoming', label: 'Upcoming', activeCls: 'btn-warning text-dark', inactiveCls: 'btn-outline-warning text-dark' },
    { key: 'missed',   href: '/manager/followups/missed',   label: 'Missed by Telecaller', activeCls: 'btn-dark', inactiveCls: 'btn-outline-dark' },
];

export default function Index({ scope, title, followups }) {
    return (
        <>
            <Head title={title} />

            <div className="chart-card mb-3">
                <div className="chart-header mb-2">
                    <h3>{title}</h3>
                    <p className="text-muted mb-0" style={{ fontSize: 13 }}>
                        Follow-ups for leads assigned under your team
                    </p>
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

            <div className="custom-table">
                <div className="table-header">
                    <h3>Follow-up List</h3>
                    <span className="text-muted" style={{ fontSize: 12 }}>{followups.total} records</span>
                </div>

                <div className="table-responsive">
                    <table className="table mb-0">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Date &amp; Time</th>
                                <th>Lead</th>
                                <th>Phone</th>
                                <th>Telecaller</th>
                                <th>Remarks</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {followups.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="text-center py-5 text-muted">
                                        <span className="material-icons d-block mb-2" style={{ fontSize: 40, opacity: 0.3 }}>event_note</span>
                                        No follow-ups found.
                                    </td>
                                </tr>
                            ) : followups.data.map((item, idx) => {
                                const sno = (followups.current_page - 1) * followups.per_page + idx + 1;
                                return (
                                    <tr key={item.id}>
                                        <td>{sno}</td>
                                        <td>
                                            <div className="fw-semibold">{item.next_followup_fmt || '—'}</div>
                                            {item.followup_time_fmt && (
                                                <small className="text-muted">{item.followup_time_fmt}</small>
                                            )}
                                        </td>
                                        <td>
                                            <div className="fw-semibold">{item.lead_name || '—'}</div>
                                            <small className="text-muted">{item.lead_code || '—'}</small>
                                        </td>
                                        <td>{item.lead_phone || '—'}</td>
                                        <td><TelecallerBadge name={item.telecaller_name} /></td>
                                        <td style={{ maxWidth: 180 }}>
                                            <span className="text-truncate d-inline-block" style={{ maxWidth: 160 }} title={item.remarks}>
                                                {item.remarks || '—'}
                                            </span>
                                        </td>
                                        <td><StatusBadge label={item.status_label} /></td>
                                        <td>
                                            {item.encrypted_lead_id && (
                                                <Link
                                                    href={`/manager/leads/${item.encrypted_lead_id}`}
                                                    className="btn btn-sm btn-outline-primary"
                                                    title="View Lead"
                                                >
                                                    <span className="material-icons" style={{ fontSize: 16 }}>open_in_new</span>
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                <div className="p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small className="text-muted">
                        Showing {followups.from ?? 0}–{followups.to ?? 0} of {followups.total} results
                    </small>
                    {followups.last_page > 1 && (
                        <nav>
                            <ul className="pagination pagination-sm mb-0">
                                {followups.links.map((link, i) => (
                                    <li key={i} className={['page-item', link.active ? 'active' : '', !link.url ? 'disabled' : ''].join(' ')}>
                                        {link.url
                                            ? <Link href={link.url} className="page-link" dangerouslySetInnerHTML={{ __html: link.label }} />
                                            : <span className="page-link" dangerouslySetInnerHTML={{ __html: link.label }} />
                                        }
                                    </li>
                                ))}
                            </ul>
                        </nav>
                    )}
                </div>
            </div>
        </>
    );
}
