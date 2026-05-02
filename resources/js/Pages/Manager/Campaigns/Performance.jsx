import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Performance({ campaigns, telecallers, stats, perCampaign, filters }) {
    const s = stats ?? {};

    const [campaign, setCampaign]   = useState(filters?.campaign   ?? '');
    const [telecaller, setTelecaller] = useState(filters?.telecaller ?? '');
    const [dateFrom, setDateFrom]   = useState(filters?.date_from  ?? '');
    const [dateTo, setDateTo]       = useState(filters?.date_to    ?? '');

    function applyFilters(e) {
        e.preventDefault();
        const params = {};
        if (campaign)   params.campaign   = campaign;
        if (telecaller) params.telecaller = telecaller;
        if (dateFrom)   params.date_from  = dateFrom;
        if (dateTo)     params.date_to    = dateTo;
        router.get('/manager/campaigns/performance', params, { preserveState: false });
    }

    function resetFilters() {
        setCampaign(''); setTelecaller(''); setDateFrom(''); setDateTo('');
        router.get('/manager/campaigns/performance', {}, { preserveState: false });
    }

    return (
        <>
            <Head title="Campaign Performance" />

            <div className="mb-3">
                <h2 className="mb-1" style={{ fontSize: 20, fontWeight: 700 }}>Campaign Performance</h2>
                <p className="text-muted mb-0" style={{ fontSize: 13 }}>Aggregate campaign analytics across your team</p>
            </div>

            {/* ── Filters ────────────────────────────────────────────────── */}
            <div className="chart-card mb-4">
                <form onSubmit={applyFilters} className="row g-3 align-items-end">
                    <div className="col-md-3">
                        <label className="form-label">Campaign</label>
                        <select className="form-select" value={campaign} onChange={e => setCampaign(e.target.value)}>
                            <option value="">All Campaigns</option>
                            {campaigns.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </div>
                    <div className="col-md-3">
                        <label className="form-label">Telecaller</label>
                        <select className="form-select" value={telecaller} onChange={e => setTelecaller(e.target.value)}>
                            <option value="">All</option>
                            {telecallers.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                        </select>
                    </div>
                    <div className="col-md-2">
                        <label className="form-label">From</label>
                        <input type="date" className="form-control" value={dateFrom} onChange={e => setDateFrom(e.target.value)} />
                    </div>
                    <div className="col-md-2">
                        <label className="form-label">To</label>
                        <input type="date" className="form-control" value={dateTo} onChange={e => setDateTo(e.target.value)} />
                    </div>
                    <div className="col-md-2 d-flex gap-2">
                        <button type="submit" className="btn btn-primary w-100">Apply</button>
                        <button type="button" className="btn btn-outline-secondary w-100" onClick={resetFilters}>Reset</button>
                    </div>
                </form>
            </div>

            {/* ── Summary stat cards ─────────────────────────────────────── */}
            <div className="row g-3 mb-4">
                {[
                    { label: 'Total Contacts',    value: s.total_contacts,    icon: 'people',       cls: 'blue'  },
                    { label: 'Assigned',          value: s.assigned,          icon: 'assignment_ind', cls: 'amber' },
                    { label: 'Calls Completed',   value: s.calls_completed,   icon: 'phone_in_talk', cls: 'blue'  },
                    { label: 'WhatsApp Sent',     value: s.whatsapp_sent,     icon: 'chat',          cls: 'green' },
                    { label: 'Interested',        value: s.interested,        icon: 'thumb_up',      cls: 'green' },
                    { label: 'Not Interested',    value: s.not_interested,    icon: 'thumb_down',    cls: 'red'   },
                    { label: 'Pending Follow-up', value: s.followups_pending, icon: 'event_note',    cls: 'amber' },
                    { label: 'Converted',         value: s.converted,         icon: 'check_circle',  cls: 'green' },
                ].map(stat => (
                    <div className="col-6 col-md-3" key={stat.label}>
                        <div className="stat-card">
                            <div className={`stat-icon ${stat.cls}`}><span className="material-icons">{stat.icon}</span></div>
                            <div className="stat-label">{stat.label}</div>
                            <div className="stat-value">{(stat.value ?? 0).toLocaleString()}</div>
                        </div>
                    </div>
                ))}
            </div>

            {/* ── Per-campaign breakdown ─────────────────────────────────── */}
            <div className="custom-table">
                <div className="table-header">
                    <h3>Campaign Breakdown</h3>
                </div>
                <div className="table-responsive">
                    <table className="table mb-0">
                        <thead>
                            <tr>
                                <th>Campaign</th>
                                <th>Status</th>
                                <th>Contacts</th>
                                <th>Assigned</th>
                                <th>Calls Done</th>
                                <th>WA Sent</th>
                                <th>Interested</th>
                                <th>Not Interested</th>
                                <th>Follow-ups</th>
                                <th>Converted</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(perCampaign ?? []).length === 0 ? (
                                <tr>
                                    <td colSpan={10} className="text-center py-5 text-muted">No campaign data.</td>
                                </tr>
                            ) : (perCampaign ?? []).map((row, i) => (
                                <tr key={i}>
                                    <td className="fw-semibold">{row.name}</td>
                                    <td><span className="badge bg-light text-dark">{row.status}</span></td>
                                    <td>{(row.total_contacts ?? 0).toLocaleString()}</td>
                                    <td>{(row.assigned ?? 0).toLocaleString()}</td>
                                    <td>{(row.calls_completed ?? 0).toLocaleString()}</td>
                                    <td>{(row.whatsapp_sent ?? 0).toLocaleString()}</td>
                                    <td><span className="badge bg-success">{row.interested ?? 0}</span></td>
                                    <td><span className="badge bg-danger">{row.not_interested ?? 0}</span></td>
                                    <td>{row.followups_pending ?? 0}</td>
                                    <td><span className="badge bg-dark">{row.converted ?? 0}</span></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
