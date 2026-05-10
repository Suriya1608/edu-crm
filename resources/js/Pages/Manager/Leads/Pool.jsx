import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Pool({ leads, filters }) {
    const [search, setSearch] = useState(filters.search ?? '');

    function doSearch(e) {
        e.preventDefault();
        router.get('/manager/leads/pool', { search }, { preserveState: true, replace: true });
    }

    function claimLead(url) {
        if (!window.confirm('Claim this lead?')) return;
        router.post(url);
    }

    return (
        <>
            <Head title="Open Lead Pool" />

            <div className="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h4 className="fw-bold mb-0">Open Lead Pool</h4>
                    <p className="text-muted mb-0 small">Unclaimed leads available to pick up. First-come, first-served.</p>
                </div>
                <span className="badge bg-primary fs-6">{leads.total} leads</span>
            </div>

            <form onSubmit={doSearch} className="mb-3">
                <div className="input-group" style={{ maxWidth: 360 }}>
                    <input type="text" className="form-control" placeholder="Search name, phone, code…"
                        value={search} onChange={e => setSearch(e.target.value)} />
                    <button className="btn btn-outline-secondary" type="submit">
                        <span className="material-icons" style={{ fontSize: 18, verticalAlign: -4 }}>search</span>
                    </button>
                    {filters.search && (
                        <Link href="/manager/leads/pool" className="btn btn-outline-danger">
                            <span className="material-icons" style={{ fontSize: 18, verticalAlign: -4 }}>close</span>
                        </Link>
                    )}
                </div>
            </form>

            {leads.data.length === 0 ? (
                <div className="chart-card text-center py-5">
                    <span className="material-icons" style={{ fontSize: 48, color: '#cbd5e1' }}>inbox</span>
                    <p className="text-muted mt-2 mb-0">No unclaimed leads in the pool right now.</p>
                </div>
            ) : (
                <>
                    <div className="chart-card p-0">
                        <div className="table-responsive">
                            <table className="table table-hover align-middle mb-0">
                                <thead className="table-light">
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Course</th>
                                        <th>Source</th>
                                        <th>Age</th>
                                        <th className="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {leads.data.map(lead => (
                                        <tr key={lead.id}>
                                            <td><span className="badge bg-light text-dark border">{lead.lead_code}</span></td>
                                            <td className="fw-semibold">{lead.name}</td>
                                            <td>{lead.phone}</td>
                                            <td>{lead.course}</td>
                                            <td><span className="text-muted small">{lead.source}</span></td>
                                            <td><span className="text-muted small">{lead.age}</span></td>
                                            <td className="text-end">
                                                <button className="btn btn-sm btn-primary"
                                                    onClick={() => claimLead(lead.claim_url)}>
                                                    <span className="material-icons" style={{ fontSize: 15, verticalAlign: -3 }}>pan_tool</span>
                                                    {' '}Claim
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {leads.last_page > 1 && (
                        <div className="mt-3">
                            <nav>
                                <ul className="pagination pagination-sm mb-0">
                                    {leads.links.map((link, i) => (
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
        </>
    );
}
