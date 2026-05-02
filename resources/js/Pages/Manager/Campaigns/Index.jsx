import { Head, Link } from '@inertiajs/react';

const STATUS_COLORS = {
    active:    { bg: '#dcfce7', color: '#16a34a' },
    paused:    { bg: '#fef9c3', color: '#ca8a04' },
    completed: { bg: '#f1f5f9', color: '#64748b' },
    draft:     { bg: '#f1f5f9', color: '#64748b' },
};

function StatusPill({ status }) {
    const s = STATUS_COLORS[status] ?? { bg: '#f1f5f9', color: '#64748b' };
    return (
        <span style={{ background: s.bg, color: s.color, fontSize: 11, fontWeight: 600, padding: '3px 8px', borderRadius: 99 }}>
            {status.charAt(0).toUpperCase() + status.slice(1)}
        </span>
    );
}

export default function Index({ campaigns, totalStats }) {
    const stats = totalStats ?? {};

    return (
        <>
            <Head title="Campaigns" />

            <div className="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h2 className="mb-0" style={{ fontSize: 20, fontWeight: 700 }}>Campaigns</h2>
                <Link href="/manager/campaigns/create" className="btn btn-primary d-flex align-items-center gap-1">
                    <span className="material-icons" style={{ fontSize: 16 }}>add</span>
                    New Campaign
                </Link>
            </div>

            {/* ── Stat cards ─────────────────────────────────────────────── */}
            <div className="row g-3 mb-4">
                <div className="col-6 col-md-3">
                    <div className="stat-card">
                        <div className="stat-icon blue"><span className="material-icons">campaign</span></div>
                        <div className="stat-label">Total Campaigns</div>
                        <div className="stat-value">{stats.total ?? 0}</div>
                    </div>
                </div>
                <div className="col-6 col-md-3">
                    <div className="stat-card">
                        <div className="stat-icon green"><span className="material-icons">play_circle</span></div>
                        <div className="stat-label">Active</div>
                        <div className="stat-value">{stats.active ?? 0}</div>
                    </div>
                </div>
                <div className="col-6 col-md-3">
                    <div className="stat-card">
                        <div className="stat-icon amber"><span className="material-icons">pause_circle</span></div>
                        <div className="stat-label">Paused</div>
                        <div className="stat-value">{stats.paused ?? 0}</div>
                    </div>
                </div>
                <div className="col-6 col-md-3">
                    <div className="stat-card">
                        <div className="stat-icon red"><span className="material-icons">check_circle</span></div>
                        <div className="stat-label">Completed</div>
                        <div className="stat-value">{stats.completed ?? 0}</div>
                    </div>
                </div>
            </div>

            {/* ── Campaign table ─────────────────────────────────────────── */}
            <div className="chart-card">
                <div className="chart-header mb-3">
                    <h3>All Campaigns</h3>
                </div>

                {campaigns.data.length === 0 ? (
                    <div className="text-center py-5">
                        <span className="material-icons" style={{ fontSize: 48, color: '#cbd5e1' }}>campaign</span>
                        <p className="text-muted mt-2">No campaigns yet. Create your first campaign to get started.</p>
                        <Link href="/manager/campaigns/create" className="btn btn-primary mt-2">
                            <span className="material-icons me-1" style={{ fontSize: 16 }}>add</span>New Campaign
                        </Link>
                    </div>
                ) : (
                    <>
                        <div className="table-responsive">
                            <table className="table table-hover align-middle mb-0">
                                <thead className="table-light">
                                    <tr>
                                        <th>Campaign Name</th>
                                        <th>Status</th>
                                        <th>Total Contacts</th>
                                        <th>Created</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {campaigns.data.map(campaign => (
                                        <tr key={campaign.id}>
                                            <td>
                                                <div className="fw-semibold">{campaign.name}</div>
                                                {campaign.description && (
                                                    <div className="text-muted small">
                                                        {campaign.description.length > 60
                                                            ? campaign.description.slice(0, 60) + '…'
                                                            : campaign.description}
                                                    </div>
                                                )}
                                            </td>
                                            <td><StatusPill status={campaign.status} /></td>
                                            <td className="fw-semibold">{(campaign.contacts_count ?? 0).toLocaleString()}</td>
                                            <td className="text-muted small">{campaign.created_at}</td>
                                            <td className="text-end">
                                                <Link href={`/manager/campaigns/${campaign.encrypted_id}`}
                                                    className="btn btn-sm btn-outline-primary">
                                                    <span className="material-icons" style={{ fontSize: 15 }}>visibility</span>
                                                    View
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {campaigns.last_page > 1 && (
                            <div className="mt-3">
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
