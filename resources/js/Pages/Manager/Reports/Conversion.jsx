import { Head, Link } from '@inertiajs/react';
import ReportFilters from './_Filters';

const NAV = [
    { href: '/manager/reports',                        label: 'Overview'               },
    { href: '/manager/reports/telecaller-performance', label: 'Telecaller Performance'  },
    { href: '/manager/reports/conversion',             label: 'Conversion'             },
    { href: '/manager/reports/source-performance',     label: 'Source Performance'     },
    { href: '/manager/reports/period',                 label: 'Period Analysis'        },
    { href: '/manager/reports/response-time',          label: 'Response Time'          },
    { href: '/manager/reports/call-efficiency',        label: 'Call Efficiency'        },
];

const STATUS_COLORS = {
    new: '#6366f1', assigned: '#0891b2', contacted: '#475569',
    interested: '#16a34a', not_interested: '#dc2626',
    converted: '#15803d', follow_up: '#92400e',
};

const FUNNEL_STEPS = [
    { key: 'new',        label: 'New / Assigned', color: '#6366f1', icon: 'person_add'   },
    { key: 'contacted',  label: 'Contacted',       color: '#0891b2', icon: 'call'         },
    { key: 'interested', label: 'Interested',      color: '#f59e0b', icon: 'thumb_up'     },
    { key: 'converted',  label: 'Converted',       color: '#10b981', icon: 'check_circle' },
];

function KpiCard({ icon, label, value, sub, iconColor, iconBg }) {
    return (
        <div className="col-6 col-xl-3">
            <div className="card border-0 shadow-sm h-100">
                <div className="card-body d-flex align-items-center gap-3 py-3">
                    <div className="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                        style={{ width: 48, height: 48, background: iconBg }}>
                        <span className="material-icons" style={{ color: iconColor, fontSize: 22 }}>{icon}</span>
                    </div>
                    <div className="min-w-0">
                        <div style={{ fontSize: 11, fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.06em', color: '#64748b' }}>{label}</div>
                        <div style={{ fontSize: 22, fontWeight: 800, color: '#0f172a', lineHeight: 1.2 }}>{value}</div>
                        {sub && <div style={{ fontSize: 11, color: '#94a3b8' }}>{sub}</div>}
                    </div>
                </div>
            </div>
        </div>
    );
}

function RankBadge({ rank }) {
    if (rank === 0) return <span className="badge" style={{ background: '#fef3c7', color: '#b45309', fontWeight: 700 }}>🥇 1</span>;
    if (rank === 1) return <span className="badge" style={{ background: '#f1f5f9', color: '#475569', fontWeight: 700 }}>🥈 2</span>;
    if (rank === 2) return <span className="badge" style={{ background: '#fff7ed', color: '#c2410c', fontWeight: 700 }}>🥉 3</span>;
    return <span className="text-muted" style={{ fontSize: 13 }}>{rank + 1}</span>;
}

function RateBar({ rate, height = 8 }) {
    const color = rate >= 50 ? '#10b981' : rate >= 25 ? '#f59e0b' : '#ef4444';
    return (
        <div className="d-flex align-items-center gap-2">
            <div className="progress flex-grow-1" style={{ height, background: '#f1f5f9' }}>
                <div className="progress-bar" style={{ width: `${rate}%`, background: color }} />
            </div>
            <span style={{ fontSize: 12, fontWeight: 700, color, whiteSpace: 'nowrap', minWidth: 38, textAlign: 'right' }}>{rate}%</span>
        </div>
    );
}

export default function Conversion({
    filters, filterOptions, statusRows, teleRows,
    totalLeads, convertedLeads, overallRate, funnel, sourceRows,
    enquiredCourseRows, finalCourseRows,
}) {
    const exportUrl = (fmt) => {
        const p = new URLSearchParams({
            date_range: filters?.date_range ?? '30',
            source:     filters?.source     ?? 'all',
            telecaller: filters?.telecaller ?? 'all',
        });
        return `/manager/reports/export/conversion/${fmt}?${p}`;
    };

    const tele         = teleRows   ?? [];
    const sources      = sourceRows ?? [];
    const statuses     = statusRows ?? [];
    const bestPerformer = tele[0] ?? null;
    const funnelBase   = funnel?.new ?? 0;
    const funnelPct    = (val) => funnelBase > 0 ? Math.round((val / funnelBase) * 100) : 0;

    // Compute drop-off rates between funnel steps
    const funnelValues = FUNNEL_STEPS.map(s => funnel?.[s.key] ?? 0);

    return (
        <>
            <Head title="Conversion Report" />

            {/* Nav */}
            <div className="d-flex gap-2 flex-wrap mb-4">
                {NAV.map(n => (
                    <Link key={n.href} href={n.href}
                        className={`btn btn-sm ${n.href === '/manager/reports/conversion' ? 'btn-primary' : 'btn-outline-secondary'}`}>
                        {n.label}
                    </Link>
                ))}
            </div>

            <ReportFilters filters={filters} filterOptions={filterOptions} url="/manager/reports/conversion" />

            {/* KPI Cards */}
            <div className="row g-3 mb-4">
                <KpiCard icon="group"        label="Total Leads"      value={totalLeads ?? 0}
                    sub="in selected period" iconColor="#6366f1" iconBg="#ede9fe" />
                <KpiCard icon="check_circle" label="Converted"        value={convertedLeads ?? 0}
                    sub={`of ${totalLeads ?? 0} total leads`} iconColor="#10b981" iconBg="#d1fae5" />
                <KpiCard icon="trending_up"  label="Conversion Rate"  value={`${overallRate ?? 0}%`}
                    sub="overall" iconColor="#0891b2" iconBg="#cffafe" />
                <KpiCard icon="emoji_events" label="Best Performer"
                    value={bestPerformer?.name ?? '—'}
                    sub={bestPerformer ? `${bestPerformer.rate}% conv. rate` : 'No data'}
                    iconColor="#f59e0b" iconBg="#fef3c7" />
            </div>

            {/* Funnel + Status Breakdown */}
            <div className="row g-4 mb-4">

                {/* Conversion Funnel */}
                <div className="col-lg-5">
                    <div className="card border-0 shadow-sm h-100">
                        <div className="card-body p-4">
                            <h6 className="fw-bold mb-4" style={{ fontSize: 14, color: '#0f172a' }}>
                                <span className="material-icons align-middle me-1" style={{ fontSize: 17, color: '#6366f1' }}>filter_list</span>
                                Conversion Funnel
                            </h6>
                            <div className="d-flex flex-column gap-4">
                                {FUNNEL_STEPS.map((step, idx) => {
                                    const val  = funnelValues[idx];
                                    const pct  = funnelPct(val);
                                    const prev = idx > 0 ? funnelValues[idx - 1] : null;
                                    const drop = prev && prev > 0 ? Math.round(((prev - val) / prev) * 100) : null;
                                    return (
                                        <div key={step.key}>
                                            <div className="d-flex justify-content-between align-items-center mb-2">
                                                <div className="d-flex align-items-center gap-2">
                                                    <div className="rounded-2 d-flex align-items-center justify-content-center"
                                                        style={{ width: 28, height: 28, background: step.color + '18' }}>
                                                        <span className="material-icons" style={{ fontSize: 15, color: step.color }}>{step.icon}</span>
                                                    </div>
                                                    <span style={{ fontSize: 13, fontWeight: 600, color: '#334155' }}>{step.label}</span>
                                                </div>
                                                <div className="d-flex align-items-center gap-2">
                                                    <span style={{ fontSize: 15, fontWeight: 800, color: step.color }}>{val}</span>
                                                    <span className="badge rounded-pill" style={{ background: step.color + '1a', color: step.color, fontSize: 11, fontWeight: 600 }}>
                                                        {pct}%
                                                    </span>
                                                    {drop !== null && (
                                                        <span className="badge rounded-pill" style={{ background: '#fee2e2', color: '#dc2626', fontSize: 10 }}>
                                                            ↓ {drop}%
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="progress rounded-pill" style={{ height: 10, background: '#f1f5f9' }}>
                                                <div className="progress-bar rounded-pill" style={{ width: `${pct}%`, background: step.color, transition: 'width 0.6s ease' }} />
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>

                            {/* Funnel summary */}
                            {funnelBase > 0 && (
                                <div className="mt-4 p-3 rounded-3" style={{ background: '#f8fafc', border: '1px solid #e2e8f0' }}>
                                    <div className="d-flex justify-content-between align-items-center">
                                        <span style={{ fontSize: 12, color: '#64748b', fontWeight: 600 }}>Overall Funnel Efficiency</span>
                                        <span style={{ fontSize: 15, fontWeight: 800, color: '#10b981' }}>
                                            {funnelPct(funnel?.converted ?? 0)}%
                                        </span>
                                    </div>
                                    <div className="progress mt-2 rounded-pill" style={{ height: 6 }}>
                                        <div className="progress-bar rounded-pill"
                                            style={{ width: `${funnelPct(funnel?.converted ?? 0)}%`, background: 'linear-gradient(90deg,#6366f1,#10b981)' }} />
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Status Breakdown */}
                <div className="col-lg-7">
                    <div className="custom-table h-100">
                        <div className="table-header">
                            <h3>Lead Status Breakdown</h3>
                            <a href={exportUrl('excel')} className="btn btn-sm btn-outline-success">
                                <span className="material-icons me-1" style={{ fontSize: 14 }}>download</span>CSV
                            </a>
                            <a href={exportUrl('pdf')} className="btn btn-sm btn-primary" target="_blank" rel="noreferrer">
                                <span className="material-icons me-1" style={{ fontSize: 14 }}>picture_as_pdf</span>PDF
                            </a>
                        </div>
                        <div className="table-responsive">
                            <table className="table mb-0">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Count</th>
                                        <th>Share of Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {statuses.length === 0 ? (
                                        <tr><td colSpan={3} className="text-center py-5 text-muted">No data for selected period</td></tr>
                                    ) : statuses.map((r, i) => {
                                        const pct   = (totalLeads ?? 0) > 0 ? Math.round((r.total / totalLeads) * 100) : 0;
                                        const color = STATUS_COLORS[r.status] ?? '#94a3b8';
                                        return (
                                            <tr key={i}>
                                                <td>
                                                    <span style={{ display: 'inline-block', width: 10, height: 10, borderRadius: '50%', background: color, marginRight: 8 }} />
                                                    {r.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}
                                                </td>
                                                <td className="fw-bold">{r.total}</td>
                                                <td style={{ minWidth: 160 }}>
                                                    <div className="d-flex align-items-center gap-2">
                                                        <div className="progress flex-grow-1" style={{ height: 7, background: '#f1f5f9' }}>
                                                            <div className="progress-bar" style={{ width: `${pct}%`, background: color }} />
                                                        </div>
                                                        <span style={{ fontSize: 12, color: '#64748b', whiteSpace: 'nowrap', minWidth: 34, textAlign: 'right' }}>{pct}%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {/* Telecaller Conversion Table */}
            <div className="custom-table mb-4">
                <div className="table-header">
                    <h3>Conversion by Telecaller</h3>
                    <span className="badge" style={{ background: '#ede9fe', color: '#6366f1', fontSize: 12 }}>
                        Ranked by conversion rate
                    </span>
                </div>
                <div className="table-responsive">
                    <table className="table mb-0">
                        <thead>
                            <tr>
                                <th style={{ width: 50 }}>#</th>
                                <th>Telecaller</th>
                                <th>Assigned</th>
                                <th>Attended</th>
                                <th>Interested</th>
                                <th>Converted</th>
                                <th style={{ minWidth: 200 }}>Conversion Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tele.length === 0 ? (
                                <tr><td colSpan={7} className="text-center py-5 text-muted">No data for selected period</td></tr>
                            ) : tele.map((r, i) => (
                                <tr key={i} style={i === 0 ? { background: '#fffbeb' } : {}}>
                                    <td><RankBadge rank={i} /></td>
                                    <td>
                                        <span className="fw-semibold" style={{ color: '#0f172a' }}>{r.name}</span>
                                        {i === 0 && tele.length > 1 && (
                                            <span className="ms-2 badge" style={{ background: '#fef3c7', color: '#b45309', fontSize: 10 }}>Top</span>
                                        )}
                                    </td>
                                    <td>{r.total}</td>
                                    <td>
                                        <span className="badge" style={{ background: '#ede9fe', color: '#6366f1', fontWeight: 600 }}>
                                            {r.attended ?? 0}
                                        </span>
                                    </td>
                                    <td>
                                        <span className="badge" style={{ background: '#fef3c7', color: '#b45309', fontWeight: 600 }}>
                                            {r.interested ?? 0}
                                        </span>
                                    </td>
                                    <td>
                                        <span className="badge" style={{ background: '#d1fae5', color: '#065f46', fontWeight: 700 }}>
                                            {r.converted}
                                        </span>
                                    </td>
                                    <td><RateBar rate={r.rate} /></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Source Conversion */}
            {sources.length > 0 && (
                <div className="custom-table mb-4">
                    <div className="table-header">
                        <h3>Conversion by Source</h3>
                        <span style={{ fontSize: 12, color: '#64748b' }}>{sources.length} source{sources.length !== 1 ? 's' : ''}</span>
                    </div>
                    <div className="table-responsive">
                        <table className="table mb-0">
                            <thead>
                                <tr>
                                    <th>Source</th>
                                    <th>Total Leads</th>
                                    <th>Converted</th>
                                    <th style={{ minWidth: 200 }}>Conversion Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                {sources.map((r, i) => (
                                    <tr key={i}>
                                        <td className="fw-semibold">
                                            <span className="material-icons align-middle me-1" style={{ fontSize: 14, color: '#6366f1' }}>source</span>
                                            {r.source || <span className="text-muted fst-italic">Unknown</span>}
                                        </td>
                                        <td>{r.total}</td>
                                        <td>
                                            <span className="badge" style={{ background: '#d1fae5', color: '#065f46', fontWeight: 700 }}>{r.converted}</span>
                                        </td>
                                        <td><RateBar rate={r.rate} height={6} /></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* Course Reports — side by side */}
            <div className="row g-4 mb-4">

                {/* Enquired Course breakdown */}
                {(enquiredCourseRows ?? []).length > 0 && (
                    <div className="col-lg-6">
                        <div className="custom-table h-100">
                            <div className="table-header">
                                <h3>
                                    <span className="material-icons align-middle me-1" style={{ fontSize: 16, color: '#6366f1' }}>search</span>
                                    Leads by Enquired Course
                                </h3>
                                <span style={{ fontSize: 12, color: '#64748b' }}>{enquiredCourseRows.length} course{enquiredCourseRows.length !== 1 ? 's' : ''}</span>
                            </div>
                            <div className="table-responsive">
                                <table className="table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Course</th>
                                            <th>Total Leads</th>
                                            <th>Converted</th>
                                            <th style={{ minWidth: 160 }}>Conversion Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {enquiredCourseRows.map((r, i) => (
                                            <tr key={i}>
                                                <td className="fw-semibold" style={{ maxWidth: 180 }}>
                                                    <span className="material-icons align-middle me-1" style={{ fontSize: 14, color: '#8b5cf6' }}>menu_book</span>
                                                    <span title={r.course_name}>{r.course_name}</span>
                                                </td>
                                                <td>{r.total}</td>
                                                <td>
                                                    <span className="badge" style={{ background: '#d1fae5', color: '#065f46', fontWeight: 700 }}>{r.converted}</span>
                                                </td>
                                                <td><RateBar rate={r.rate} height={6} /></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}

                {/* Final Selected Course breakdown */}
                {(finalCourseRows ?? []).length > 0 && (
                    <div className="col-lg-6">
                        <div className="custom-table h-100">
                            <div className="table-header">
                                <h3>
                                    <span className="material-icons align-middle me-1" style={{ fontSize: 16, color: '#10b981' }}>school</span>
                                    Enrollments by Final Course
                                </h3>
                                <span style={{ fontSize: 12, color: '#64748b' }}>{finalCourseRows.length} course{finalCourseRows.length !== 1 ? 's' : ''}</span>
                            </div>
                            <div className="table-responsive">
                                <table className="table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Course</th>
                                            <th>Enrolled</th>
                                            <th>Management</th>
                                            <th>Counselling</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {finalCourseRows.map((r, i) => (
                                            <tr key={i}>
                                                <td className="fw-semibold" style={{ maxWidth: 180 }}>
                                                    <span className="material-icons align-middle me-1" style={{ fontSize: 14, color: '#10b981' }}>check_circle</span>
                                                    <span title={r.course_name}>{r.course_name}</span>
                                                </td>
                                                <td>
                                                    <span className="badge" style={{ background: '#d1fae5', color: '#065f46', fontWeight: 700 }}>{r.total}</span>
                                                </td>
                                                <td>{r.management_count ?? 0}</td>
                                                <td>{r.counselling_count ?? 0}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}
