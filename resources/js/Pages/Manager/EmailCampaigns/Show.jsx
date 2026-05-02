import { Head, Link, router } from '@inertiajs/react';

const STATUS_CLS = {
    draft:     'bg-secondary',
    scheduled: 'bg-info',
    sending:   'bg-warning text-dark',
    completed: 'bg-success',
    failed:    'bg-danger',
};

const R_STATUS_CLS = {
    pending: 'bg-secondary',
    sent:    'bg-success',
    failed:  'bg-danger',
    bounced: 'bg-warning text-dark',
    opened:  'bg-primary',
};

function ProgressBar({ label, value, max, rate, color }) {
    return (
        <div className="chart-card">
            <div className="d-flex justify-content-between mb-1" style={{ fontSize: 13 }}>
                <span className="fw-semibold">{label}</span>
                <span>{value}/{max}</span>
            </div>
            <div className="progress" style={{ height: 6 }}>
                <div className="progress-bar" style={{ width: `${rate}%`, background: color }} />
            </div>
            <div className="text-muted mt-1" style={{ fontSize: 11 }}>{rate}% {label.toLowerCase()}</div>
        </div>
    );
}

export default function Show({ campaign, recipients }) {
    const c = campaign;

    function handleDelete() {
        if (!window.confirm('Delete this campaign?')) return;
        router.delete(c.delete_url);
    }

    return (
        <>
            <Head title={`${c.name} — Analytics`} />

            {/* ── Header ─────────────────────────────────────────────────── */}
            <div className="d-flex align-items-center gap-3 mb-4 flex-wrap">
                <Link href="/manager/email-campaigns" className="btn btn-sm btn-light d-flex align-items-center gap-1">
                    <span className="material-icons" style={{ fontSize: 16 }}>arrow_back</span>Back
                </Link>
                <div className="flex-grow-1">
                    <h2 className="mb-0" style={{ fontSize: 18, fontWeight: 700 }}>{c.name}</h2>
                    <div className="d-flex align-items-center gap-2 mt-1">
                        <span className={`badge ${STATUS_CLS[c.status] ?? 'bg-secondary'}`}>
                            {c.status.charAt(0).toUpperCase() + c.status.slice(1)}
                        </span>
                        <small className="text-muted">
                            Template: <strong>{c.template_name}</strong>
                            {c.course_filter && <> &mdash; Course: <strong>{c.course_filter}</strong></>}
                        </small>
                    </div>
                </div>
                <button className="btn btn-sm btn-outline-danger d-flex align-items-center gap-1" onClick={handleDelete}>
                    <span className="material-icons" style={{ fontSize: 15 }}>delete</span>
                    Delete
                </button>
            </div>

            {/* ── Count stat cards ───────────────────────────────────────── */}
            <div className="row g-3 mb-3">
                {[
                    { label: 'Recipients', value: c.recipients_count, icon: 'people',         cls: 'blue'   },
                    { label: 'Sent',       value: c.sent_count,       icon: 'check_circle',   cls: 'green'  },
                    { label: 'Opened',     value: c.opened_count,     icon: 'visibility',     cls: 'blue'   },
                    { label: 'Clicked',    value: c.click_count,      icon: 'ads_click',      cls: 'purple' },
                    { label: 'Bounced',    value: c.bounced_count,    icon: 'block',          cls: 'red'    },
                    { label: 'Failed',     value: c.failed_count,     icon: 'cancel',         cls: 'red'    },
                ].map(stat => (
                    <div className="col-6 col-md-2" key={stat.label}>
                        <div className="stat-card">
                            <div className={`stat-icon ${stat.cls}`}><span className="material-icons">{stat.icon}</span></div>
                            <div className="stat-label">{stat.label}</div>
                            <div className="stat-value">{(stat.value ?? 0).toLocaleString()}</div>
                        </div>
                    </div>
                ))}
            </div>

            {/* ── Rate stat cards ────────────────────────────────────────── */}
            <div className="row g-3 mb-4">
                {[
                    { label: 'Delivery Rate', value: c.delivery_rate, icon: 'local_post_office', cls: 'green'  },
                    { label: 'Open Rate',     value: c.open_rate,     icon: 'drafts',            cls: 'blue'   },
                    { label: 'Click Rate',    value: c.click_rate,    icon: 'touch_app',         cls: 'purple' },
                    { label: 'Bounce Rate',   value: c.bounce_rate,   icon: 'warning_amber',     cls: 'red'    },
                ].map(stat => (
                    <div className="col-6 col-md-3" key={stat.label}>
                        <div className="stat-card">
                            <div className={`stat-icon ${stat.cls}`}><span className="material-icons">{stat.icon}</span></div>
                            <div className="stat-label">{stat.label}</div>
                            <div className="stat-value">{stat.value ?? 0}%</div>
                        </div>
                    </div>
                ))}
            </div>

            {/* ── Progress bars ──────────────────────────────────────────── */}
            <div className="row g-3 mb-4">
                <div className="col-md-3">
                    <ProgressBar label="Delivery" value={c.sent_count} max={c.recipients_count} rate={c.delivery_rate} color="#10b981" />
                </div>
                <div className="col-md-3">
                    <ProgressBar label="Opens" value={c.opened_count} max={c.sent_count} rate={c.open_rate} color="#6366f1" />
                </div>
                <div className="col-md-3">
                    <ProgressBar label="Clicks" value={c.click_count} max={c.sent_count} rate={c.click_rate} color="#8b5cf6" />
                </div>
                <div className="col-md-3">
                    <ProgressBar label="Bounces" value={c.bounced_count} max={c.sent_count} rate={c.bounce_rate} color="#ef4444" />
                </div>
            </div>

            {/* ── Recipients table ───────────────────────────────────────── */}
            <div className="chart-card">
                <h6 className="fw-semibold mb-3">
                    Recipients{' '}
                    <span className="text-muted fw-normal" style={{ fontSize: 13 }}>
                        ({(c.recipients_count ?? 0).toLocaleString()} total)
                    </span>
                </h6>

                {recipients.data.length === 0 ? (
                    <div className="text-center py-4 text-muted" style={{ fontSize: 13 }}>No recipients found.</div>
                ) : (
                    <>
                        <div className="table-responsive">
                            <table className="table table-hover align-middle table-sm mb-0">
                                <thead className="table-light">
                                    <tr>
                                        <th>Email</th>
                                        <th>Name</th>
                                        <th>Status</th>
                                        <th>Sent At</th>
                                        <th>Opened At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recipients.data.map(r => (
                                        <tr key={r.id}>
                                            <td style={{ fontSize: 13 }}>{r.email}</td>
                                            <td className="text-muted" style={{ fontSize: 13 }}>{r.name || '—'}</td>
                                            <td>
                                                <span className={`badge ${R_STATUS_CLS[r.status] ?? 'bg-secondary'}`}>
                                                    {r.status.charAt(0).toUpperCase() + r.status.slice(1)}
                                                </span>
                                                {r.opened_at && <span className="badge bg-primary ms-1">Opened</span>}
                                            </td>
                                            <td className="text-muted" style={{ fontSize: 12 }}>{r.sent_at ?? '—'}</td>
                                            <td className="text-muted" style={{ fontSize: 12 }}>{r.opened_at ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {recipients.last_page > 1 && (
                            <div className="mt-3">
                                <nav>
                                    <ul className="pagination pagination-sm mb-0">
                                        {recipients.links.map((link, i) => (
                                            <li key={i} className={['page-item', link.active ? 'active' : '', !link.url ? 'disabled' : ''].join(' ')}>
                                                {link.url
                                                    ? <Link href={link.url} className="page-link" dangerouslySetInnerHTML={{ __html: link.label }} />
                                                    : <span className="page-link" dangerouslySetInnerHTML={{ __html: link.label }} />
                                                }
                                            </li>
                                        ))}
                                    </ul>
                                </nav>
                            </div>
                        )}
                    </>
                )}
            </div>
        </>
    );
}
