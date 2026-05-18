import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

const ORG    = '#FF5C1A';
const WHITE  = '#FFFFFF';
const BORDER = '#EAEAED';
const TEXT   = '#1A1A2E';
const MUTED  = '#9EA3B0';
const BGGRAY = '#F5F5F7';

const card = (extra = {}) => ({
    background: WHITE, borderRadius: 14,
    boxShadow: '0 2px 16px rgba(0,0,0,0.07)',
    border: `1px solid ${BORDER}`,
    ...extra,
});

// ─── Contact status config ────────────────────────────────────────────────────
const STATUS_MAP = {
    pending:        { label: 'Pending',        bg: '#f1f5f9', color: '#64748b' },
    called:         { label: 'Called',          bg: '#e0f2fe', color: '#0284c7' },
    interested:     { label: 'Interested',      bg: '#dcfce7', color: '#16a34a' },
    not_interested: { label: 'Not Interested',  bg: '#fee2e2', color: '#dc2626' },
    no_answer:      { label: 'No Answer',       bg: '#fef9c3', color: '#ca8a04' },
    callback:       { label: 'Callback',        bg: '#ede9fe', color: '#7c3aed' },
    converted:      { label: 'Converted',       bg: '#dcfce7', color: '#15803d' },
};

function StatusPill({ status }) {
    const s = STATUS_MAP[status] ?? { label: status, bg: '#f1f5f9', color: '#64748b' };
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

// ─── Format date ──────────────────────────────────────────────────────────────
function fmtDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric',
    });
}

// ─── Stat Card ────────────────────────────────────────────────────────────────
function StatCard({ icon, iconColor, iconBg, label, value }) {
    return (
        <div style={{ ...card(), padding: '18px 22px' }}>
            <div style={{ width: 40, height: 40, borderRadius: 10, background: iconBg, display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: 12 }}>
                <span className="material-icons" style={{ color: iconColor, fontSize: 20 }}>{icon}</span>
            </div>
            <div style={{ fontSize: 11.5, color: MUTED, fontWeight: 600, marginBottom: 3 }}>{label}</div>
            <div style={{ fontSize: 26, fontWeight: 800, color: TEXT }}>{value}</div>
        </div>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────
export default function Show({ campaign, contacts, stats, filters }) {
    const s = stats ?? {};

    const [form, setForm] = useState({
        search: filters?.search ?? '',
        status: filters?.status ?? '',
    });

    const STATUSES = ['pending','called','interested','not_interested','no_answer','callback','converted'];

    function handleFilter(e) {
        e.preventDefault();
        const params = {};
        if (form.search) params.search = form.search;
        if (form.status) params.status = form.status;
        router.get(`/telecaller/campaigns/${campaign.encrypted_id}`, params, { preserveState: false });
    }

    function resetFilter() {
        setForm({ search: '', status: '' });
        router.get(`/telecaller/campaigns/${campaign.encrypted_id}`, {}, { preserveState: false });
    }

    return (
        <>
            <Head title={campaign.name} />

            {/* ── Back + title ─────────────────────────────────────────────── */}
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 12, marginBottom: 22 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
                    <Link
                        href="/telecaller/campaigns"
                        style={{
                            display: 'inline-flex', alignItems: 'center', gap: 5,
                            padding: '7px 14px', borderRadius: 8, border: `1px solid ${BORDER}`,
                            background: WHITE, color: TEXT, fontSize: 13, fontWeight: 600,
                            textDecoration: 'none', transition: 'background .15s',
                        }}
                        onMouseEnter={e => e.currentTarget.style.background = BGGRAY}
                        onMouseLeave={e => e.currentTarget.style.background = WHITE}
                    >
                        <span className="material-icons" style={{ fontSize: 16 }}>arrow_back</span>
                        My Campaigns
                    </Link>
                    <div>
                        <h2 style={{ fontSize: 18, fontWeight: 800, color: TEXT, margin: 0 }}>{campaign.name}</h2>
                        <p style={{ fontSize: 12, color: MUTED, margin: 0 }}>Campaign contact list</p>
                    </div>
                </div>
            </div>

            {/* ── Stat cards ──────────────────────────────────────────────── */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(160px, 1fr))', gap: 14, marginBottom: 22 }}>
                <StatCard icon="people"         iconColor="#6366f1" iconBg="#6366f115" label="My Contacts"  value={(s.total     ?? 0).toLocaleString()} />
                <StatCard icon="hourglass_empty" iconColor="#f59e0b" iconBg="#f59e0b15" label="Pending"     value={(s.pending   ?? 0).toLocaleString()} />
                <StatCard icon="phone_in_talk"  iconColor="#10b981" iconBg="#10b98115" label="Contacted"   value={(s.called    ?? 0).toLocaleString()} />
                <StatCard icon="check_circle"   iconColor="#15803d" iconBg="#10b98115" label="Converted"   value={(s.converted ?? 0).toLocaleString()} />
            </div>

            {/* ── Contact list ─────────────────────────────────────────────── */}
            <div style={card()}>
                {/* Card header */}
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '18px 24px', borderBottom: `1px solid ${BORDER}` }}>
                    <div style={{ width: 36, height: 36, borderRadius: 10, background: `${ORG}15`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <span className="material-icons" style={{ color: ORG, fontSize: 18 }}>people</span>
                    </div>
                    <h3 style={{ fontSize: 15, fontWeight: 700, color: TEXT, margin: 0 }}>Contact List</h3>
                    <span style={{ marginLeft: 'auto', fontSize: 11.5, fontWeight: 700, background: BGGRAY, color: MUTED, padding: '3px 10px', borderRadius: 20 }}>
                        {(s.total ?? 0).toLocaleString()} total
                    </span>
                </div>

                {/* ── Filters ─────────────────────────────────────────────── */}
                <form onSubmit={handleFilter} style={{ display: 'flex', flexWrap: 'wrap', gap: 10, alignItems: 'flex-end', padding: '16px 24px', borderBottom: `1px solid ${BORDER}`, background: BGGRAY }}>
                    <div style={{ flex: '1 1 220px', minWidth: 160 }}>
                        <label style={{ fontSize: 11, fontWeight: 600, color: MUTED, display: 'block', marginBottom: 5 }}>Search</label>
                        <div style={{ position: 'relative' }}>
                            <span className="material-icons" style={{ position: 'absolute', left: 10, top: '50%', transform: 'translateY(-50%)', fontSize: 16, color: MUTED, pointerEvents: 'none' }}>search</span>
                            <input
                                type="text"
                                placeholder="Name or phone…"
                                value={form.search}
                                onChange={e => setForm({ ...form, search: e.target.value })}
                                style={{
                                    width: '100%', padding: '8px 12px 8px 34px', borderRadius: 8,
                                    border: `1px solid ${BORDER}`, fontSize: 13, background: WHITE,
                                    color: TEXT, outline: 'none',
                                }}
                            />
                        </div>
                    </div>
                    <div style={{ flex: '0 1 180px', minWidth: 140 }}>
                        <label style={{ fontSize: 11, fontWeight: 600, color: MUTED, display: 'block', marginBottom: 5 }}>Status</label>
                        <select
                            value={form.status}
                            onChange={e => setForm({ ...form, status: e.target.value })}
                            style={{
                                width: '100%', padding: '8px 12px', borderRadius: 8,
                                border: `1px solid ${BORDER}`, fontSize: 13, background: WHITE, color: TEXT,
                            }}
                        >
                            <option value="">All Statuses</option>
                            {STATUSES.map(s => (
                                <option key={s} value={s}>
                                    {s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div style={{ display: 'flex', gap: 8, alignItems: 'flex-end' }}>
                        <button
                            type="submit"
                            style={{
                                padding: '8px 18px', borderRadius: 8, border: 'none', cursor: 'pointer',
                                background: `linear-gradient(135deg, ${ORG}, #FF8042)`,
                                color: WHITE, fontSize: 13, fontWeight: 700,
                            }}
                        >
                            Apply
                        </button>
                        <button
                            type="button"
                            onClick={resetFilter}
                            style={{
                                padding: '8px 14px', borderRadius: 8, cursor: 'pointer',
                                border: `1px solid ${BORDER}`, background: WHITE, color: TEXT, fontSize: 13, fontWeight: 600,
                            }}
                        >
                            Reset
                        </button>
                    </div>
                </form>

                {/* ── Table ─────────────────────────────────────────────────── */}
                {contacts.data.length === 0 ? (
                    <div style={{ textAlign: 'center', padding: '60px 0' }}>
                        <span className="material-icons" style={{ fontSize: 48, color: BORDER, display: 'block', marginBottom: 12 }}>people</span>
                        <p style={{ color: MUTED, margin: 0, fontSize: 14, fontWeight: 500 }}>No contacts match your filters.</p>
                    </div>
                ) : (
                    <>
                        <div style={{ overflowX: 'auto' }}>
                            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                                <thead>
                                    <tr style={{ background: BGGRAY }}>
                                        {['Student','Mobile','Course','Status','Follow-up','Calls',''].map(h => (
                                            <th key={h} style={{
                                                padding: '11px 16px', fontSize: 11, fontWeight: 700,
                                                color: MUTED, textTransform: 'uppercase', letterSpacing: '.4px',
                                                borderBottom: `1px solid ${BORDER}`, whiteSpace: 'nowrap',
                                                position: 'sticky', top: 0, background: BGGRAY, zIndex: 1,
                                                textAlign: h === '' ? 'right' : 'left',
                                            }}>
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {contacts.data.map((contact, idx) => (
                                        <tr
                                            key={contact.id}
                                            style={{ borderBottom: `1px solid ${BORDER}`, background: WHITE, transition: 'background .12s' }}
                                            onMouseEnter={e => e.currentTarget.style.background = BGGRAY}
                                            onMouseLeave={e => e.currentTarget.style.background = WHITE}
                                        >
                                            <td style={{ padding: '12px 16px', verticalAlign: 'middle' }}>
                                                <div style={{ fontWeight: 700, fontSize: 13, color: TEXT }}>{contact.name}</div>
                                                {contact.city && (
                                                    <div style={{ fontSize: 11.5, color: MUTED }}>{contact.city}</div>
                                                )}
                                            </td>
                                            <td style={{ padding: '12px 16px', verticalAlign: 'middle' }}>
                                                <a href={`tel:${contact.phone}`}
                                                    style={{ fontSize: 13, color: '#6366f1', fontWeight: 600, textDecoration: 'none' }}>
                                                    {contact.phone}
                                                </a>
                                            </td>
                                            <td style={{ padding: '12px 16px', verticalAlign: 'middle', fontSize: 13, color: MUTED }}>
                                                {contact.course || '—'}
                                            </td>
                                            <td style={{ padding: '12px 16px', verticalAlign: 'middle' }}>
                                                <StatusPill status={contact.status} />
                                            </td>
                                            <td style={{ padding: '12px 16px', verticalAlign: 'middle', fontSize: 13, color: MUTED }}>
                                                {fmtDate(contact.next_followup)}
                                            </td>
                                            <td style={{ padding: '12px 16px', verticalAlign: 'middle' }}>
                                                <span style={{
                                                    display: 'inline-flex', alignItems: 'center', gap: 4,
                                                    fontSize: 12, fontWeight: 700, color: TEXT,
                                                    background: BGGRAY, padding: '3px 10px', borderRadius: 20,
                                                }}>
                                                    <span className="material-icons" style={{ fontSize: 13, color: MUTED }}>call</span>
                                                    {contact.call_count}
                                                </span>
                                            </td>
                                            <td style={{ padding: '12px 16px', verticalAlign: 'middle', textAlign: 'right' }}>
                                                <Link
                                                    href={`/telecaller/campaigns/${campaign.encrypted_id}/contacts/${contact.encrypted_id}`}
                                                    style={{
                                                        display: 'inline-flex', alignItems: 'center', gap: 5,
                                                        padding: '6px 14px', borderRadius: 8, fontSize: 12, fontWeight: 700,
                                                        border: `1.5px solid ${ORG}`, color: ORG, background: `${ORG}08`,
                                                        textDecoration: 'none', transition: 'background .12s',
                                                    }}
                                                    onMouseEnter={e => e.currentTarget.style.background = `${ORG}15`}
                                                    onMouseLeave={e => e.currentTarget.style.background = `${ORG}08`}
                                                >
                                                    <span className="material-icons" style={{ fontSize: 14 }}>open_in_new</span>
                                                    View
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* ── Pagination ─────────────────────────────────────── */}
                        {contacts.last_page > 1 && (
                            <div style={{ padding: '16px 24px', display: 'flex', justifyContent: 'center', borderTop: `1px solid ${BORDER}` }}>
                                <nav>
                                    <ul className="pagination pagination-sm mb-0">
                                        {contacts.links.map((link, i) => (
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
