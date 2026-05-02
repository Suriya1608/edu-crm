import { Head, Link } from '@inertiajs/react';

// ─── Status badge ─────────────────────────────────────────────────────────────
const STATUS_COLORS = {
    active:    { bg: '#dcfce7', color: '#16a34a', label: 'Active' },
    paused:    { bg: '#fef9c3', color: '#ca8a04', label: 'Paused' },
    completed: { bg: '#f1f5f9', color: '#64748b', label: 'Completed' },
    draft:     { bg: '#f1f5f9', color: '#64748b', label: 'Draft' },
};

function StatusPill({ status }) {
    const s = STATUS_COLORS[status] ?? { bg: '#f1f5f9', color: '#64748b', label: status };
    return (
        <span style={{
            background: s.bg, color: s.color,
            fontSize: 11, fontWeight: 600, padding: '3px 8px', borderRadius: 99,
        }}>
            {s.label}
        </span>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────
export default function Index({ campaigns, totalStats }) {
    const stats = totalStats ?? {};

    return (
        <>
            <Head title="My Campaigns" />

            {/* ── Stat cards ──────────────────────────────────────────────── */}
            <div className="row g-3 mb-4">
                <div className="col-6 col-md-3">
                    <div className="stat-card">
                        <div className="stat-icon" style={{ background: '#6366f122' }}>
                            <span className="material-icons" style={{ color: '#6366f1' }}>campaign</span>
                        </div>
                        <div className="stat-label">Assigned Campaigns</div>
                        <div className="stat-value">{stats.total ?? 0}</div>
                    </div>
                </div>
                <div className="col-6 col-md-3">
                    <div className="stat-card">
                        <div className="stat-icon" style={{ background: '#f59e0b22' }}>
                            <span className="material-icons" style={{ color: '#f59e0b' }}>people</span>
                        </div>
                        <div className="stat-label">Total Contacts</div>
                        <div className="stat-value">
                            {(stats.contacts ?? 0).toLocaleString()}
                        </div>
                    </div>
                </div>
            </div>

            {/* ── Campaign cards ──────────────────────────────────────────── */}
            <div className="chart-card">
                <div className="chart-header mb-3">
                    <h3>My Campaigns</h3>
                </div>

                {campaigns.data.length === 0 ? (
                    <div className="text-center py-5">
                        <span className="material-icons" style={{ fontSize: 48, color: '#cbd5e1' }}>campaign</span>
                        <p className="text-muted mt-2">No campaigns assigned to you yet.</p>
                    </div>
                ) : (
                    <>
                        <div className="row g-3">
                            {campaigns.data.map(campaign => (
                                <div className="col-12 col-md-6 col-lg-4" key={campaign.id}>
                                    <div className="card border h-100">
                                        <div className="card-body">
                                            <div className="d-flex justify-content-between align-items-start mb-2">
                                                <h5 className="card-title mb-0 fw-semibold" style={{ fontSize: 15 }}>
                                                    {campaign.name}
                                                </h5>
                                                <StatusPill status={campaign.status} />
                                            </div>

                                            {campaign.description && (
                                                <p className="text-muted small mb-3">
                                                    {campaign.description.length > 80
                                                        ? campaign.description.slice(0, 80) + '…'
                                                        : campaign.description}
                                                </p>
                                            )}

                                            <div className="d-flex align-items-center gap-1 mb-3">
                                                <span className="material-icons text-primary" style={{ fontSize: 16 }}>people</span>
                                                <span className="fw-semibold">
                                                    {(campaign.my_contacts_count ?? 0).toLocaleString()}
                                                </span>
                                                <span className="text-muted small">contacts assigned to you</span>
                                            </div>

                                            {/* Inertia Link — keeps SIP connection alive */}
                                            <Link
                                                href={`/telecaller/campaigns/${campaign.encrypted_id}`}
                                                className="btn btn-primary btn-sm w-100 d-flex align-items-center justify-content-center gap-1"
                                            >
                                                <span className="material-icons" style={{ fontSize: 15 }}>phone_in_talk</span>
                                                Start Calling
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* ── Pagination ────────────────────────────────────── */}
                        {campaigns.last_page > 1 && (
                            <div className="mt-4">
                                <nav>
                                    <ul className="pagination pagination-sm mb-0">
                                        {campaigns.links.map((link, i) => (
                                            <li key={i}
                                                className={[
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
                            </div>
                        )}
                    </>
                )}
            </div>
        </>
    );
}
