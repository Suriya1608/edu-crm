import { Head, Link } from '@inertiajs/react';

const TAB_CFG = [
    { key: 'today',    href: '/manager/followups/today',    label: 'Today',    activeCls: 'btn-primary',           inactiveCls: 'btn-outline-primary'           },
    { key: 'overdue',  href: '/manager/followups/overdue',  label: 'Overdue',  activeCls: 'btn-danger',            inactiveCls: 'btn-outline-danger'            },
    { key: 'upcoming', href: '/manager/followups/upcoming', label: 'Upcoming', activeCls: 'btn-warning text-dark', inactiveCls: 'btn-outline-warning text-dark' },
    { key: 'missed',   href: '/manager/followups/missed',   label: 'Missed by Telecaller', activeCls: 'btn-dark', inactiveCls: 'btn-outline-dark' },
];

export default function Missed({ rows }) {
    return (
        <>
            <Head title="Missed Follow-ups by Telecaller" />

            <div className="chart-card mb-3">
                <div className="chart-header mb-2">
                    <h3>Missed Follow-ups by Telecaller</h3>
                    <p className="text-muted mb-0" style={{ fontSize: 13 }}>
                        Escalated view of overdue follow-ups grouped by telecaller
                    </p>
                </div>
                <div className="d-flex gap-2 flex-wrap">
                    {TAB_CFG.map(tab => (
                        <Link
                            key={tab.key}
                            href={tab.href}
                            className={`btn btn-sm ${tab.key === 'missed' ? tab.activeCls : tab.inactiveCls}`}
                        >
                            {tab.label}
                        </Link>
                    ))}
                </div>
            </div>

            <div className="custom-table">
                <div className="table-header">
                    <h3>Escalation Summary</h3>
                    <span className="text-muted" style={{ fontSize: 12 }}>{rows.total} records</span>
                </div>

                <div className="table-responsive">
                    <table className="table mb-0">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Telecaller</th>
                                <th>Missed Count</th>
                                <th>Oldest Pending</th>
                                <th>Latest Pending</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="text-center py-5 text-muted">
                                        <span className="material-icons d-block mb-2" style={{ fontSize: 40, opacity: 0.3 }}>event_busy</span>
                                        No missed follow-ups found.
                                    </td>
                                </tr>
                            ) : rows.data.map((row, idx) => {
                                const sno = (rows.current_page - 1) * rows.per_page + idx + 1;
                                return (
                                    <tr key={row.telecaller_id ?? idx}>
                                        <td>{sno}</td>
                                        <td className="fw-semibold">{row.telecaller_name}</td>
                                        <td>
                                            <span className="badge bg-danger">{row.missed_count}</span>
                                        </td>
                                        <td>{row.oldest_pending}</td>
                                        <td>{row.latest_pending}</td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                <div className="p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small className="text-muted">
                        Showing {rows.from ?? 0}–{rows.to ?? 0} of {rows.total} results
                    </small>
                    {rows.last_page > 1 && (
                        <nav>
                            <ul className="pagination pagination-sm mb-0">
                                {rows.links.map((link, i) => (
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
