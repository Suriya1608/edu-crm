import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

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
            fontSize: 11, fontWeight: 600, padding: '3px 8px', borderRadius: 99,
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
            <div className="mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <Link
                    href="/telecaller/campaigns"
                    className="btn btn-sm btn-light d-flex align-items-center gap-1"
                >
                    <span className="material-icons" style={{ fontSize: 16 }}>arrow_back</span>
                    My Campaigns
                </Link>
                <h5 className="mb-0 fw-semibold">{campaign.name}</h5>
            </div>

            {/* ── Stat cards ──────────────────────────────────────────────── */}
            <div className="row g-3 mb-4">
                <div className="col-6 col-md-3">
                    <div className="stat-card">
                        <div className="stat-icon" style={{ background: '#6366f122' }}>
                            <span className="material-icons" style={{ color: '#6366f1' }}>people</span>
                        </div>
                        <div className="stat-label">My Contacts</div>
                        <div className="stat-value">{(s.total ?? 0).toLocaleString()}</div>
                    </div>
                </div>
                <div className="col-6 col-md-3">
                    <div className="stat-card">
                        <div className="stat-icon" style={{ background: '#f59e0b22' }}>
                            <span className="material-icons" style={{ color: '#f59e0b' }}>hourglass_empty</span>
                        </div>
                        <div className="stat-label">Pending</div>
                        <div className="stat-value">{(s.pending ?? 0).toLocaleString()}</div>
                    </div>
                </div>
                <div className="col-6 col-md-3">
                    <div className="stat-card">
                        <div className="stat-icon" style={{ background: '#10b98122' }}>
                            <span className="material-icons" style={{ color: '#10b981' }}>phone_in_talk</span>
                        </div>
                        <div className="stat-label">Contacted</div>
                        <div className="stat-value">{(s.called ?? 0).toLocaleString()}</div>
                    </div>
                </div>
                <div className="col-6 col-md-3">
                    <div className="stat-card">
                        <div className="stat-icon" style={{ background: '#10b98122' }}>
                            <span className="material-icons" style={{ color: '#15803d' }}>check_circle</span>
                        </div>
                        <div className="stat-label">Converted</div>
                        <div className="stat-value">{(s.converted ?? 0).toLocaleString()}</div>
                    </div>
                </div>
            </div>

            {/* ── Contact list ─────────────────────────────────────────────── */}
            <div className="chart-card">
                <div className="chart-header mb-3">
                    <h3>Contact List</h3>
                </div>

                {/* ── Filters ── */}
                <form onSubmit={handleFilter} className="row g-2 mb-3">
                    <div className="col-12 col-md-5">
                        <input
                            type="text"
                            className="form-control form-control-sm"
                            placeholder="Search name, phone..."
                            value={form.search}
                            onChange={e => setForm({ ...form, search: e.target.value })}
                        />
                    </div>
                    <div className="col-6 col-md-4">
                        <select
                            className="form-select form-select-sm"
                            value={form.status}
                            onChange={e => setForm({ ...form, status: e.target.value })}
                        >
                            <option value="">All Statuses</option>
                            {STATUSES.map(s => (
                                <option key={s} value={s}>
                                    {s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="col-6 col-md-3 d-flex gap-1">
                        <button type="submit" className="btn btn-sm btn-primary flex-grow-1">Filter</button>
                        <button type="button" className="btn btn-sm btn-light" onClick={resetFilter}>Clear</button>
                    </div>
                </form>

                {contacts.data.length === 0 ? (
                    <div className="text-center py-5">
                        <span className="material-icons" style={{ fontSize: 40, color: '#cbd5e1' }}>people</span>
                        <p className="text-muted mt-2">No contacts match your filters.</p>
                    </div>
                ) : (
                    <>
                        <div className="table-responsive">
                            <table className="table table-hover align-middle mb-0">
                                <thead className="table-light">
                                    <tr>
                                        <th>Student</th>
                                        <th>Mobile</th>
                                        <th>Course</th>
                                        <th>Status</th>
                                        <th>Follow-up</th>
                                        <th>Calls</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {contacts.data.map(contact => (
                                        <tr key={contact.id}>
                                            <td>
                                                <div className="fw-semibold">{contact.name}</div>
                                                {contact.city && (
                                                    <div className="text-muted small">{contact.city}</div>
                                                )}
                                            </td>
                                            <td>
                                                <a href={`tel:${contact.phone}`} className="text-decoration-none">
                                                    {contact.phone}
                                                </a>
                                            </td>
                                            <td className="text-muted small">{contact.course || '—'}</td>
                                            <td>
                                                <StatusPill status={contact.status} />
                                            </td>
                                            <td className="text-muted small">{fmtDate(contact.next_followup)}</td>
                                            <td className="text-muted small">{contact.call_count}</td>
                                            <td>
                                                {/*
                                                  * Contact detail page is still Blade — use a plain <a> so
                                                  * the browser does a full load into the Blade layout.
                                                  */}
                                                <a
                                                    href={`/telecaller/campaigns/${campaign.encrypted_id}/contacts/${contact.encrypted_id}`}
                                                    className="btn btn-sm btn-outline-primary"
                                                >
                                                    <span className="material-icons" style={{ fontSize: 15 }}>open_in_new</span>
                                                </a>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* ── Pagination ────────────────────────────────────── */}
                        {contacts.last_page > 1 && (
                            <div className="mt-3">
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
