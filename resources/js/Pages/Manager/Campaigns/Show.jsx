import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const STATUS_MAP = {
    pending:        { label: 'Pending',        bg: '#f1f5f9', color: '#64748b' },
    called:         { label: 'Called',          bg: '#e0f2fe', color: '#0284c7' },
    interested:     { label: 'Interested',      bg: '#dcfce7', color: '#16a34a' },
    not_interested: { label: 'Not Interested',  bg: '#fee2e2', color: '#dc2626' },
    no_answer:      { label: 'No Answer',       bg: '#fef9c3', color: '#ca8a04' },
    callback:       { label: 'Callback',        bg: '#ede9fe', color: '#7c3aed' },
    converted:      { label: 'Converted',       bg: '#dcfce7', color: '#15803d' },
};

const CAMPAIGN_STATUS_COLORS = {
    active:    { bg: '#dcfce7', color: '#16a34a' },
    paused:    { bg: '#fef9c3', color: '#ca8a04' },
    completed: { bg: '#f1f5f9', color: '#64748b' },
    draft:     { bg: '#f1f5f9', color: '#64748b' },
};

function StatusPill({ status }) {
    const s = STATUS_MAP[status] ?? { label: status, bg: '#f1f5f9', color: '#64748b' };
    return (
        <span style={{ background: s.bg, color: s.color, fontSize: 11, fontWeight: 600, padding: '3px 8px', borderRadius: 99, whiteSpace: 'nowrap' }}>
            {s.label}
        </span>
    );
}

const STATUSES = ['pending','called','interested','not_interested','no_answer','callback','converted'];

export default function Show({ campaign, contacts, telecallers, stats, unassigned_count, assignment_summary, filters }) {
    const s = stats ?? {};
    const cStatusCfg = CAMPAIGN_STATUS_COLORS[campaign.status] ?? { bg: '#f1f5f9', color: '#64748b' };

    const [search, setSearch]     = useState(filters?.search     ?? '');
    const [status, setStatus]     = useState(filters?.status     ?? '');
    const [telecaller, setTelecaller] = useState(filters?.telecaller ?? '');
    const [selectedTcs, setSelectedTcs] = useState([]);

    const distributeForm = useForm({ telecaller_ids: [] });

    function handleFilter(e) {
        e.preventDefault();
        const params = {};
        if (search)     params.search     = search;
        if (status)     params.status     = status;
        if (telecaller) params.telecaller = telecaller;
        router.get(`/manager/campaigns/${campaign.encrypted_id}`, params, { preserveState: false });
    }

    function resetFilter() {
        setSearch(''); setStatus(''); setTelecaller('');
        router.get(`/manager/campaigns/${campaign.encrypted_id}`, {}, { preserveState: false });
    }

    function handleStatusChange(e) {
        const newStatus = e.target.value;
        if (!window.confirm(`Change campaign status to "${newStatus}"?`)) return;
        router.patch(campaign.status_url, { status: newStatus }, { preserveScroll: true });
    }

    function handleDistribute(e) {
        e.preventDefault();
        if (selectedTcs.length === 0) return;
        if (!window.confirm(`Distribute ${unassigned_count} contacts among ${selectedTcs.length} telecaller(s)?`)) return;
        router.post(campaign.distribute_url, { telecaller_ids: selectedTcs }, { preserveScroll: true });
    }

    function toggleTc(id) {
        setSelectedTcs(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
    }

    return (
        <>
            <Head title={campaign.name} />

            {/* ── Sub-nav ─────────────────────────────────────────────────── */}
            <div className="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div className="d-flex align-items-center gap-3">
                    <Link href="/manager/campaigns" className="btn btn-sm btn-light d-flex align-items-center gap-1">
                        <span className="material-icons" style={{ fontSize: 16 }}>arrow_back</span>
                        Back to Campaigns
                    </Link>
                    <div>
                        <h2 className="mb-0" style={{ fontSize: 18, fontWeight: 700 }}>{campaign.name}</h2>
                        <div className="d-flex align-items-center gap-2 mt-1">
                            <span style={{ background: cStatusCfg.bg, color: cStatusCfg.color, fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 99 }}>
                                {campaign.status.charAt(0).toUpperCase() + campaign.status.slice(1)}
                            </span>
                            <small className="text-muted">Created {campaign.created_at}</small>
                        </div>
                    </div>
                </div>
                <div className="d-flex gap-2">
                    <a href={campaign.import_url} className="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
                        <span className="material-icons" style={{ fontSize: 15 }}>upload_file</span>
                        Upload More
                    </a>
                    <select className="form-select form-select-sm" value={campaign.status} onChange={handleStatusChange} style={{ width: 'auto' }}>
                        {['active','paused','completed','draft'].map(s => (
                            <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
                        ))}
                    </select>
                </div>
            </div>

            {/* ── Stat cards ─────────────────────────────────────────────── */}
            <div className="row g-3 mb-4">
                <div className="col-6 col-md-2">
                    <div className="stat-card">
                        <div className="stat-icon blue"><span className="material-icons">people</span></div>
                        <div className="stat-label">Total</div>
                        <div className="stat-value">{(s.total ?? 0).toLocaleString()}</div>
                    </div>
                </div>
                <div className="col-6 col-md-2">
                    <div className="stat-card">
                        <div className="stat-icon amber"><span className="material-icons">hourglass_empty</span></div>
                        <div className="stat-label">Pending</div>
                        <div className="stat-value">{(s.pending ?? 0).toLocaleString()}</div>
                    </div>
                </div>
                <div className="col-6 col-md-3">
                    <div className="stat-card">
                        <div className="stat-icon blue"><span className="material-icons">phone_in_talk</span></div>
                        <div className="stat-label">Contacted</div>
                        <div className="stat-value">{(s.called ?? 0).toLocaleString()}</div>
                    </div>
                </div>
                <div className="col-6 col-md-2">
                    <div className="stat-card">
                        <div className="stat-icon green"><span className="material-icons">thumb_up</span></div>
                        <div className="stat-label">Interested</div>
                        <div className="stat-value">{(s.interested ?? 0).toLocaleString()}</div>
                    </div>
                </div>
                <div className="col-6 col-md-3">
                    <div className="stat-card">
                        <div className="stat-icon green"><span className="material-icons">check_circle</span></div>
                        <div className="stat-label">Converted</div>
                        <div className="stat-value">{(s.converted ?? 0).toLocaleString()}</div>
                    </div>
                </div>
            </div>

            <div className="row g-4">
                {/* ── Contacts table ─────────────────────────────────────── */}
                <div className="col-lg-8">
                    <div className="chart-card">
                        <div className="chart-header mb-3">
                            <h3>Contacts</h3>
                        </div>

                        <form onSubmit={handleFilter} className="row g-2 mb-3">
                            <div className="col-12 col-md-4">
                                <input type="text" className="form-control form-control-sm"
                                    placeholder="Search name, phone, email..."
                                    value={search} onChange={e => setSearch(e.target.value)} />
                            </div>
                            <div className="col-6 col-md-3">
                                <select className="form-select form-select-sm" value={status} onChange={e => setStatus(e.target.value)}>
                                    <option value="">All Statuses</option>
                                    {STATUSES.map(s => (
                                        <option key={s} value={s}>{s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="col-6 col-md-3">
                                <select className="form-select form-select-sm" value={telecaller} onChange={e => setTelecaller(e.target.value)}>
                                    <option value="">All Telecallers</option>
                                    {telecallers.map(t => (
                                        <option key={t.id} value={t.id}>{t.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="col-12 col-md-2 d-flex gap-1">
                                <button type="submit" className="btn btn-sm btn-primary flex-grow-1">Filter</button>
                                <button type="button" className="btn btn-sm btn-light" onClick={resetFilter}>Clear</button>
                            </div>
                        </form>

                        {contacts.data.length === 0 ? (
                            <div className="text-center py-4">
                                <span className="material-icons" style={{ fontSize: 40, color: '#cbd5e1' }}>people</span>
                                <p className="text-muted mt-2">No contacts found.</p>
                            </div>
                        ) : (
                            <>
                                <div className="table-responsive">
                                    <table className="table table-hover align-middle table-sm mb-0">
                                        <thead className="table-light">
                                            <tr>
                                                <th>Name</th>
                                                <th>Mobile</th>
                                                <th>Email</th>
                                                <th>Course</th>
                                                <th>Status</th>
                                                <th>Assigned To</th>
                                                <th>Follow-up</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {contacts.data.map(contact => (
                                                <tr key={contact.id}>
                                                    <td className="fw-semibold">{contact.name}</td>
                                                    <td>{contact.phone}</td>
                                                    <td className="text-muted small">{contact.email || '—'}</td>
                                                    <td className="text-muted small">{contact.course || '—'}</td>
                                                    <td><StatusPill status={contact.status} /></td>
                                                    <td className="text-muted small">{contact.assigned_user ?? '—'}</td>
                                                    <td className="text-muted small">
                                                        {contact.next_followup ?? '—'}
                                                        {contact.followup_time && (
                                                            <span className="text-primary d-block" style={{ fontSize: 11 }}>{contact.followup_time}</span>
                                                        )}
                                                    </td>
                                                    <td>
                                                        <a href={`/manager/campaigns/${campaign.encrypted_id}/contacts/${contact.encrypted_id}`}
                                                            className="btn btn-sm btn-outline-primary">
                                                            <span className="material-icons" style={{ fontSize: 15 }}>open_in_new</span>
                                                        </a>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                {contacts.last_page > 1 && (
                                    <div className="mt-3">
                                        <nav>
                                            <ul className="pagination pagination-sm mb-0">
                                                {contacts.links.map((link, i) => (
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
                </div>

                {/* ── Side panel ─────────────────────────────────────────── */}
                <div className="col-lg-4">
                    {/* Distribute */}
                    <div className="chart-card mb-4">
                        <div className="chart-header mb-3">
                            <h3>Distribute Contacts</h3>
                        </div>
                        <p className="text-muted small mb-3">
                            <strong>{(unassigned_count ?? 0).toLocaleString()}</strong> unassigned contact(s) ready to distribute.
                        </p>
                        {(unassigned_count ?? 0) > 0 ? (
                            <form onSubmit={handleDistribute}>
                                <label className="form-label fw-semibold small">Select Telecallers</label>
                                {telecallers.map(tc => (
                                    <div className="form-check mb-1" key={tc.id}>
                                        <input className="form-check-input" type="checkbox"
                                            id={`tc_${tc.id}`} checked={selectedTcs.includes(tc.id)}
                                            onChange={() => toggleTc(tc.id)} />
                                        <label className="form-check-label small" htmlFor={`tc_${tc.id}`}>
                                            {tc.name}
                                        </label>
                                    </div>
                                ))}
                                {telecallers.length === 0
                                    ? <p className="text-muted small">No telecallers found.</p>
                                    : (
                                        <button type="submit" className="btn btn-primary btn-sm mt-3 w-100"
                                            disabled={selectedTcs.length === 0}>
                                            <span className="material-icons me-1" style={{ fontSize: 15 }}>shuffle</span>
                                            Auto-Distribute
                                        </button>
                                    )
                                }
                            </form>
                        ) : (
                            <p className="text-success small">
                                <span className="material-icons align-middle" style={{ fontSize: 16 }}>check_circle</span>
                                All contacts have been assigned.
                            </p>
                        )}
                    </div>

                    {/* Assignment summary */}
                    <div className="chart-card">
                        <div className="chart-header mb-3">
                            <h3>Assignment Summary</h3>
                        </div>
                        {(assignment_summary ?? []).map((row, i) => (
                            <div key={i} className="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <span className="small">{row.name}</span>
                                <span className="badge bg-light text-dark border">{row.cnt}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}
