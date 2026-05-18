import { Head, Link } from '@inertiajs/react';

const ORG    = '#FF5C1A';
const DARKC  = '#1A1A2E';
const WHITE  = '#FFFFFF';
const BORDER = '#EAEAED';
const TEXT   = '#1A1A2E';
const MUTED  = '#9EA3B0';

const card = (extra = {}) => ({
    background: WHITE, borderRadius: 14,
    boxShadow: '0 2px 16px rgba(0,0,0,0.07)',
    border: `1px solid ${BORDER}`,
    ...extra,
});

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
            fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 99,
            whiteSpace: 'nowrap',
        }}>
            {s.label}
        </span>
    );
}

// ─── Campaign Card ────────────────────────────────────────────────────────────
function CampaignCard({ campaign }) {
    return (
        <div
            style={{
                background: WHITE, border: `1px solid ${BORDER}`, borderRadius: 14,
                padding: '20px 22px', display: 'flex', flexDirection: 'column', gap: 14,
                transition: 'box-shadow .15s, transform .15s',
            }}
            onMouseEnter={e => {
                e.currentTarget.style.boxShadow = '0 8px 28px rgba(0,0,0,.10)';
                e.currentTarget.style.transform = 'translateY(-2px)';
            }}
            onMouseLeave={e => {
                e.currentTarget.style.boxShadow = '';
                e.currentTarget.style.transform = '';
            }}
        >
            {/* Header row */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 8 }}>
                <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12 }}>
                    <div style={{
                        width: 40, height: 40, borderRadius: 10, flexShrink: 0,
                        background: `${ORG}15`, display: 'flex', alignItems: 'center', justifyContent: 'center',
                    }}>
                        <span className="material-icons" style={{ color: ORG, fontSize: 20 }}>campaign</span>
                    </div>
                    <h5 style={{ fontSize: 15, fontWeight: 700, color: TEXT, margin: 0, lineHeight: 1.35, paddingTop: 2 }}>
                        {campaign.name}
                    </h5>
                </div>
                <StatusPill status={campaign.status} />
            </div>

            {/* Description */}
            {campaign.description && (
                <p style={{ fontSize: 12.5, color: MUTED, margin: 0, lineHeight: 1.6 }}>
                    {campaign.description.length > 90
                        ? campaign.description.slice(0, 90) + '…'
                        : campaign.description}
                </p>
            )}

            {/* Contacts count */}
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '10px 14px', borderRadius: 10, background: '#F5F5F7' }}>
                <span className="material-icons" style={{ fontSize: 16, color: '#6366f1' }}>people</span>
                <span style={{ fontSize: 13, fontWeight: 700, color: TEXT }}>
                    {(campaign.my_contacts_count ?? 0).toLocaleString()}
                </span>
                <span style={{ fontSize: 12, color: MUTED }}>contacts assigned to you</span>
            </div>

            {/* CTA */}
            <Link
                href={`/telecaller/campaigns/${campaign.encrypted_id}`}
                style={{
                    display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6,
                    padding: '10px 16px', borderRadius: 10,
                    background: `linear-gradient(135deg, ${ORG}, #FF8042)`,
                    color: WHITE, fontSize: 13, fontWeight: 700,
                    textDecoration: 'none',
                    transition: 'opacity .15s',
                }}
                onMouseEnter={e => e.currentTarget.style.opacity = '.88'}
                onMouseLeave={e => e.currentTarget.style.opacity = '1'}
            >
                <span className="material-icons" style={{ fontSize: 16 }}>phone_in_talk</span>
                Start Calling
            </Link>
        </div>
    );
}

// ─── Stat Card ────────────────────────────────────────────────────────────────
function StatCard({ icon, iconColor, iconBg, label, value }) {
    return (
        <div style={{ ...card(), padding: '20px 24px' }}>
            <div style={{ width: 42, height: 42, borderRadius: 10, background: iconBg, display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: 12 }}>
                <span className="material-icons" style={{ color: iconColor, fontSize: 22 }}>{icon}</span>
            </div>
            <div style={{ fontSize: 12, color: MUTED, fontWeight: 600, marginBottom: 4 }}>{label}</div>
            <div style={{ fontSize: 28, fontWeight: 800, color: TEXT }}>{value}</div>
        </div>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────
export default function Index({ campaigns, totalStats }) {
    const stats = totalStats ?? {};

    return (
        <>
            <Head title="My Campaigns" />

            {/* ── Stat cards ──────────────────────────────────────────────── */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: 16, marginBottom: 24 }}>
                <StatCard icon="campaign" iconColor="#6366f1" iconBg="#6366f115" label="Assigned Campaigns" value={stats.total ?? 0} />
                <StatCard icon="people" iconColor="#f59e0b" iconBg="#f59e0b15" label="Total Contacts" value={(stats.contacts ?? 0).toLocaleString()} />
            </div>

            {/* ── Campaign list ────────────────────────────────────────────── */}
            <div style={card({ padding: '24px 28px' })}>
                {/* Section header */}
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 22, paddingBottom: 18, borderBottom: `1px solid ${BORDER}` }}>
                    <div style={{ width: 36, height: 36, borderRadius: 10, background: `${ORG}15`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <span className="material-icons" style={{ color: ORG, fontSize: 18 }}>campaign</span>
                    </div>
                    <h3 style={{ fontSize: 16, fontWeight: 700, color: TEXT, margin: 0 }}>My Campaigns</h3>
                </div>

                {campaigns.data.length === 0 ? (
                    <div style={{ textAlign: 'center', padding: '60px 0' }}>
                        <span className="material-icons" style={{ fontSize: 52, color: BORDER, display: 'block', marginBottom: 14 }}>campaign</span>
                        <p style={{ color: MUTED, margin: 0, fontSize: 14, fontWeight: 500 }}>No campaigns assigned to you yet.</p>
                    </div>
                ) : (
                    <>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: 16 }}>
                            {campaigns.data.map(campaign => (
                                <CampaignCard key={campaign.id} campaign={campaign} />
                            ))}
                        </div>

                        {/* ── Pagination ────────────────────────────────────── */}
                        {campaigns.last_page > 1 && (
                            <div style={{ marginTop: 24, display: 'flex', justifyContent: 'center' }}>
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
