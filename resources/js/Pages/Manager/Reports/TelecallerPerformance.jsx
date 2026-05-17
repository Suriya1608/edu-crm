import { Head, Link, router } from '@inertiajs/react';
import ReportFilters from './_Filters';

const NAV = [
    { href: '/manager/reports',                        label: 'Overview'              },
    { href: '/manager/reports/telecaller-performance', label: 'Telecaller Performance' },
    { href: '/manager/reports/conversion',             label: 'Conversion'            },
    { href: '/manager/reports/source-performance',     label: 'Source Performance'    },
    { href: '/manager/reports/period',                 label: 'Period Analysis'       },
    { href: '/manager/reports/response-time',          label: 'Response Time'         },
    { href: '/manager/reports/call-efficiency',        label: 'Call Efficiency'       },
];

const RANK_COLORS = ['#f59e0b', '#94a3b8', '#cd7f32'];
const RANK_ICONS  = ['workspace_premium', 'military_tech', 'emoji_events'];

function AggCard({ icon, color, label, value, sub }) {
    return (
        <div className="col-6 col-xl-2">
            <div style={{
                background: '#fff', borderRadius: 14, padding: '16px 18px',
                boxShadow: '0 1px 6px rgba(15,23,42,.07)', height: '100%',
            }}>
                <div style={{
                    width: 38, height: 38, borderRadius: 10, background: color + '18',
                    display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: 10,
                }}>
                    <span className="material-icons" style={{ color, fontSize: 19 }}>{icon}</span>
                </div>
                <div style={{ fontSize: 10, fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: .7, marginBottom: 3 }}>
                    {label}
                </div>
                <div style={{ fontSize: 22, fontWeight: 800, color: '#0f172a', lineHeight: 1.1 }}>{value}</div>
                {sub && <div style={{ fontSize: 11, color: '#94a3b8', marginTop: 3 }}>{sub}</div>}
            </div>
        </div>
    );
}

function ScoreBadge({ score }) {
    const color = score >= 70 ? '#10b981' : score >= 40 ? '#6366f1' : score >= 20 ? '#f59e0b' : '#ef4444';
    const grade = score >= 70 ? 'A' : score >= 40 ? 'B' : score >= 20 ? 'C' : 'D';
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
            <div style={{
                width: 32, height: 32, borderRadius: 8, background: color + '18',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                fontSize: 13, fontWeight: 800, color,
            }}>{grade}</div>
            <span style={{ fontWeight: 700, color }}>{score}</span>
        </div>
    );
}

function MiniBar({ value, max, color }) {
    const pct = max > 0 ? Math.min(100, Math.round((value / max) * 100)) : 0;
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
            <div style={{ flex: 1, height: 5, borderRadius: 99, background: '#f1f5f9', minWidth: 40 }}>
                <div style={{ width: pct + '%', height: '100%', borderRadius: 99, background: color, transition: 'width .4s' }} />
            </div>
            <span style={{ fontSize: 12, fontWeight: 600, color: '#0f172a', minWidth: 28, textAlign: 'right' }}>{value}</span>
        </div>
    );
}

export default function TelecallerPerformance({ filters, filterOptions, rows, aggStats }) {
    const safeRows = rows ?? [];
    const maxCalls = Math.max(...safeRows.map(r => r.calls), 1);

    const exportParams = new URLSearchParams({
        date_range: filters?.date_range ?? '30',
        source:     filters?.source     ?? 'all',
        telecaller: filters?.telecaller ?? 'all',
        campaign:   filters?.campaign   ?? 'all',
        call_type:  filters?.call_type  ?? 'all',
    }).toString();

    const detailUrl = (id) =>
        `/manager/reports/telecaller-detail?telecaller=${id}&date_range=${filters?.date_range ?? '30'}&source=${filters?.source ?? 'all'}&campaign=${filters?.campaign ?? 'all'}&call_type=${filters?.call_type ?? 'all'}`;

    return (
        <>
            <Head title="Telecaller Performance" />

            {/* Nav */}
            <div className="d-flex gap-2 flex-wrap mb-4">
                {NAV.map(n => (
                    <Link key={n.href} href={n.href}
                        className={`btn btn-sm ${n.href === '/manager/reports/telecaller-performance' ? 'btn-primary' : 'btn-outline-secondary'}`}>
                        {n.label}
                    </Link>
                ))}
            </div>

            {/* Aggregate KPI row */}
            <div className="row g-3 mb-4">
                <AggCard icon="groups"         color="#6366f1" label="Active Telecallers" value={aggStats?.total_telecallers ?? 0} />
                <AggCard icon="call"           color="#10b981" label="Total Calls"        value={aggStats?.total_calls      ?? 0} />
                <AggCard icon="timer"          color="#06b6d4" label="Total Talk Time"    value={aggStats?.total_talk_time  ?? '00:00:00'} />
                <AggCard icon="check_circle"   color="#8b5cf6" label="Total Converted"    value={aggStats?.total_converted  ?? 0} />
                <AggCard icon="chat"           color="#25d366" label="WhatsApp Sent"      value={aggStats?.total_whatsapp   ?? 0} />
                <AggCard icon="call_missed"    color="#ef4444" label="Total Missed"       value={aggStats?.total_missed     ?? 0} />
            </div>

            {/* Filters */}
            <ReportFilters
                filters={filters}
                filterOptions={filterOptions}
                url="/manager/reports/telecaller-performance"
                showCampaign
                showCallType
            />

            {/* Main table */}
            <div className="custom-table">
                <div className="table-header">
                    <h3>
                        <span className="material-icons me-2" style={{ verticalAlign: -5, fontSize: 20 }}>leaderboard</span>
                        Telecaller Performance Ranking
                    </h3>
                    <a href={`/manager/reports/export/telecaller-performance/excel?${exportParams}`}
                        className="btn btn-sm btn-outline-success">
                        <span className="material-icons me-1" style={{ fontSize: 15 }}>download</span>Export Excel
                    </a>
                    <button
                        className="btn btn-sm btn-primary"
                        onClick={() => router.visit(`/manager/reports/export/telecaller-performance/pdf?${exportParams}`)}>
                        <span className="material-icons me-1" style={{ fontSize: 15 }}>picture_as_pdf</span>Export PDF
                    </button>
                </div>

                <div className="table-responsive">
                    <table className="table mb-0">
                        <thead>
                            <tr>
                                <th style={{ width: 44 }}>#</th>
                                <th>Telecaller</th>
                                <th>Leads Assigned</th>
                                <th>Leads Attended</th>
                                <th>Total Calls</th>
                                <th>Inbound</th>
                                <th>Outbound</th>
                                <th>Missed</th>
                                <th>Connected</th>
                                <th>Talk Time</th>
                                <th>Avg Duration</th>
                                <th>WhatsApp</th>
                                <th>Campaign Calls</th>
                                <th>Follow-ups</th>
                                <th>Pending</th>
                                <th>Converted</th>
                                <th>Conv. %</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            {safeRows.length === 0 ? (
                                <tr>
                                    <td colSpan={18} className="text-center py-5 text-muted">
                                        No data for selected period.
                                    </td>
                                </tr>
                            ) : safeRows.map((r, i) => (
                                <tr key={r.id}>
                                    <td style={{ textAlign: 'center' }}>
                                        {i < 3 ? (
                                            <span className="material-icons"
                                                style={{ fontSize: 20, color: RANK_COLORS[i], display: 'block' }}>
                                                {RANK_ICONS[i]}
                                            </span>
                                        ) : (
                                            <span style={{ fontSize: 12, fontWeight: 700, color: '#94a3b8' }}>{i + 1}</span>
                                        )}
                                    </td>
                                    <td>
                                        <Link href={detailUrl(r.id)} className="fw-semibold text-decoration-none" style={{ color: '#6366f1' }}>
                                            {r.name}
                                        </Link>
                                        <div style={{ fontSize: 11, color: '#94a3b8', marginTop: 1 }}>View details →</div>
                                    </td>
                                    <td>{r.assigned}</td>
                                    <td>
                                        <span style={{ fontWeight: 600, color: '#06b6d4' }}>{r.attended}</span>
                                        <div style={{ fontSize: 11, color: '#94a3b8' }}>
                                            {r.assigned > 0 ? Math.round((r.attended / r.assigned) * 100) : 0}% of assigned
                                        </div>
                                    </td>
                                    <td>
                                        <MiniBar value={r.calls} max={maxCalls} color="#6366f1" />
                                    </td>
                                    <td>
                                        <span className="badge" style={{ background: '#10b98115', color: '#10b981', fontSize: 11 }}>
                                            {r.calls_inbound}
                                        </span>
                                    </td>
                                    <td>
                                        <span className="badge" style={{ background: '#6366f115', color: '#6366f1', fontSize: 11 }}>
                                            {r.calls_outbound}
                                        </span>
                                    </td>
                                    <td>
                                        <span className="badge" style={{ background: '#ef444415', color: '#ef4444', fontSize: 11 }}>
                                            {r.calls_missed}
                                        </span>
                                    </td>
                                    <td>
                                        <span className="badge" style={{ background: '#8b5cf615', color: '#8b5cf6', fontSize: 11 }}>
                                            {r.calls_connected}
                                        </span>
                                    </td>
                                    <td style={{ fontWeight: 600, color: '#0f172a', fontSize: 13 }}>{r.total_talk_time}</td>
                                    <td style={{ color: '#64748b', fontSize: 13 }}>{r.avg_talk_time}</td>
                                    <td>
                                        <span className="badge" style={{ background: '#25d36615', color: '#25d366', fontSize: 11 }}>
                                            {r.whatsapp_sent}
                                        </span>
                                    </td>
                                    <td>{r.campaign_calls}</td>
                                    <td>{r.followups}</td>
                                    <td>
                                        {r.followups_pending > 0 ? (
                                            <span className="badge bg-warning text-dark" style={{ fontSize: 11 }}>
                                                {r.followups_pending}
                                            </span>
                                        ) : (
                                            <span style={{ color: '#94a3b8' }}>—</span>
                                        )}
                                    </td>
                                    <td>
                                        <span className="badge bg-success" style={{ fontSize: 11 }}>{r.converted}</span>
                                    </td>
                                    <td>
                                        <span style={{ fontWeight: 700, color: r.conversion_rate >= 20 ? '#10b981' : r.conversion_rate >= 10 ? '#f59e0b' : '#ef4444', fontSize: 13 }}>
                                            {r.conversion_rate}%
                                        </span>
                                    </td>
                                    <td>
                                        <ScoreBadge score={r.efficiency_score} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
