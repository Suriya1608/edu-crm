import { Head, Link, router } from '@inertiajs/react';

const STATUS_CLS = {
    draft:     'bg-secondary',
    scheduled: 'bg-info',
    sending:   'bg-warning text-dark',
    completed: 'bg-success',
    failed:    'bg-danger',
};

export default function Index({ campaigns }) {
    function deleteCampaign(id) {
        if (!window.confirm('Delete this campaign?')) return;
        router.delete(`/manager/email-campaigns/${id}`, { preserveScroll: false });
    }

    return (
        <>
            <Head title="Email Campaigns" />

            <div className="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                <div>
                    <h2 className="mb-0" style={{ fontSize: 20, fontWeight: 700 }}>Email Campaigns</h2>
                    <p className="text-muted mb-0" style={{ fontSize: 13 }}>Create and track email marketing campaigns</p>
                </div>
                <a href="/manager/email-campaigns/create" className="btn btn-primary btn-sm d-flex align-items-center gap-1">
                    <span className="material-icons" style={{ fontSize: 16 }}>add</span>
                    New Campaign
                </a>
            </div>

            <div className="chart-card">
                {campaigns.data.length === 0 ? (
                    <div className="text-center py-5 text-muted">
                        <span className="material-icons" style={{ fontSize: 48, opacity: 0.3 }}>mark_email_read</span>
                        <p className="mt-2">No email campaigns yet.</p>
                        <a href="/manager/email-campaigns/create" className="btn btn-primary btn-sm mt-1">
                            Create First Campaign
                        </a>
                    </div>
                ) : (
                    <>
                        <div className="table-responsive">
                            <table className="table table-hover align-middle mb-0">
                                <thead className="table-light">
                                    <tr>
                                        <th>Campaign</th>
                                        <th>Template</th>
                                        <th>Status</th>
                                        <th>Recipients</th>
                                        <th>Sent</th>
                                        <th>Opened</th>
                                        <th>Failed</th>
                                        <th>Date</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {campaigns.data.map(ec => (
                                        <tr key={ec.id}>
                                            <td>
                                                <Link href={`/manager/email-campaigns/${ec.id}`}
                                                    className="fw-semibold text-decoration-none">
                                                    {ec.name}
                                                </Link>
                                                {ec.description && (
                                                    <div className="text-muted" style={{ fontSize: 12 }}>
                                                        {ec.description.length > 60 ? ec.description.slice(0, 60) + '…' : ec.description}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="text-muted" style={{ fontSize: 13 }}>{ec.template_name}</td>
                                            <td>
                                                <span className={`badge ${STATUS_CLS[ec.status] ?? 'bg-secondary'}`}>
                                                    {ec.status.charAt(0).toUpperCase() + ec.status.slice(1)}
                                                </span>
                                            </td>
                                            <td>{(ec.recipients_count ?? 0).toLocaleString()}</td>
                                            <td>
                                                <span className="text-success fw-semibold">{(ec.sent_count ?? 0).toLocaleString()}</span>
                                                {ec.recipients_count > 0 && (
                                                    <span className="text-muted" style={{ fontSize: 11 }}> ({ec.delivery_rate}%)</span>
                                                )}
                                            </td>
                                            <td>
                                                <span className="text-primary fw-semibold">{(ec.opened_count ?? 0).toLocaleString()}</span>
                                                {ec.sent_count > 0 && (
                                                    <span className="text-muted" style={{ fontSize: 11 }}> ({ec.open_rate}%)</span>
                                                )}
                                            </td>
                                            <td>
                                                {(ec.failed_count ?? 0) > 0
                                                    ? <span className="text-danger fw-semibold">{ec.failed_count.toLocaleString()}</span>
                                                    : <span className="text-muted">0</span>
                                                }
                                            </td>
                                            <td className="text-muted" style={{ fontSize: 13 }}>
                                                {ec.scheduled_at ? `Sched: ${ec.scheduled_at}` : ec.created_at}
                                            </td>
                                            <td>
                                                <div className="d-flex gap-1">
                                                    <Link href={`/manager/email-campaigns/${ec.id}`}
                                                        className="btn btn-sm btn-outline-primary">
                                                        <span className="material-icons" style={{ fontSize: 15 }}>bar_chart</span>
                                                    </Link>
                                                    <button className="btn btn-sm btn-outline-danger"
                                                        onClick={() => deleteCampaign(ec.id)}>
                                                        <span className="material-icons" style={{ fontSize: 15 }}>delete</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {campaigns.last_page > 1 && (
                            <div className="mt-3 px-2">
                                <nav>
                                    <ul className="pagination pagination-sm mb-0">
                                        {campaigns.links.map((link, i) => (
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
