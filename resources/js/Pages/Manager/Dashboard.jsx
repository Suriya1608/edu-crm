import { Head, router } from '@inertiajs/react';
import { useState, useEffect, useRef, useCallback } from 'react';

// ─── Helpers ──────────────────────────────────────────────────────────────────
function toTimeLabel(totalSeconds) {
    const sec = Number(totalSeconds || 0);
    const h   = Math.floor(sec / 3600);
    const m   = Math.floor((sec % 3600) / 60);
    const s   = sec % 60;
    return [h, m, s].map(v => String(v).padStart(2, '0')).join(':');
}

function pad(n) { return String(n).padStart(2, '0'); }

const MONTH_NAMES = [
    'January','February','March','April','May','June',
    'July','August','September','October','November','December',
];
const DOW_LABELS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

// ─── Load Chart.js from CDN once ─────────────────────────────────────────────
function useChartJs(onReady) {
    useEffect(() => {
        if (window.Chart) { onReady(window.Chart); return; }
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
        script.onload = () => onReady(window.Chart);
        document.head.appendChild(script);
    }, []);
}

// ─── Lead Source Doughnut Chart ───────────────────────────────────────────────
function LeadSourceChart({ leadSource }) {
    const canvasRef = useRef(null);
    const chartRef  = useRef(null);

    useChartJs((Chart) => {
        if (!canvasRef.current) return;
        if (chartRef.current) chartRef.current.destroy();

        const labels = leadSource.map(r => r.source || 'Unknown');
        const values = leadSource.map(r => r.total);

        chartRef.current = new Chart(canvasRef.current, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: ['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#0ea5e9'],
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
            },
        });
    });

    return (
        <div className="chart-card">
            <div className="chart-header d-flex justify-content-between align-items-start">
                <div>
                    <h3>Lead Source Overview</h3>
                    <p>Distribution by source</p>
                </div>
            </div>
            <div className="chart-container">
                <canvas ref={canvasRef} />
            </div>
        </div>
    );
}

// ─── Telecaller Presence Panel ────────────────────────────────────────────────
function PresencePanel({ initial, snapshotUrl }) {
    const [list, setList] = useState(initial ?? []);

    const refresh = useCallback(async () => {
        try {
            const res  = await fetch(snapshotUrl, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            if (Array.isArray(data.telecallers)) {
                setList(data.telecallers.map(t => ({
                    id:        t.id,
                    name:      t.name,
                    is_online: Boolean(t.is_online),
                })));
            }
        } catch (_) {}
    }, [snapshotUrl]);

    useEffect(() => {
        refresh();
        const timer = setInterval(refresh, 30_000);
        return () => clearInterval(timer);
    }, [refresh]);

    return (
        <div className="alerts-panel mb-4">
            <div className="alerts-header">
                <h3>
                    Telecaller Availability
                    <span className="material-icons" style={{ color: 'var(--primary-color)', fontSize: 20 }}>support_agent</span>
                </h3>
            </div>
            <div className="d-flex flex-column gap-2">
                {list.map(t => (
                    <div key={t.id} className="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span className="fw-semibold">{t.name}</span>
                        <span className={`badge ${t.is_online ? 'bg-success' : 'bg-secondary'}`}>
                            {t.is_online ? 'Online' : 'Offline'}
                        </span>
                    </div>
                ))}
                {list.length === 0 && (
                    <p className="text-muted small mb-0">No telecallers found.</p>
                )}
            </div>
        </div>
    );
}

// ─── Follow-up Calendar ───────────────────────────────────────────────────────
function FollowupCalendar({ initialData, calendarDataUrl }) {
    const todayDate = new Date();
    const todayY = todayDate.getFullYear();
    const todayM = todayDate.getMonth() + 1;
    const todayD = todayDate.getDate();

    const [state, setState]   = useState({ year: todayY, month: todayM, days: initialData ?? {} });
    const [loading, setLoading] = useState(false);

    function densityStyle(count) {
        if (!count) return null;
        if (count <= 3) return { bg: '#dcfce7', border: '#bbf7d0', color: '#16a34a' };
        if (count <= 7) return { bg: '#fef9c3', border: '#fde68a', color: '#92400e' };
        return { bg: '#fee2e2', border: '#fecaca', color: '#dc2626' };
    }

    async function navigate(year, month) {
        setLoading(true);
        try {
            const res  = await fetch(`${calendarDataUrl}?year=${year}&month=${month}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            setState({ year: data.year, month: data.month, days: data.days || {} });
        } catch (_) {}
        setLoading(false);
    }

    function prevMonth() {
        let { year, month } = state;
        if (--month < 1) { month = 12; year--; }
        navigate(year, month);
    }

    function nextMonth() {
        let { year, month } = state;
        if (++month > 12) { month = 1; year++; }
        navigate(year, month);
    }

    const { year, month, days } = state;
    const daysInMonth = new Date(year, month, 0).getDate();
    const firstDow    = new Date(year, month - 1, 1).getDay();
    const isThisMonth = year === todayY && month === todayM;

    const cells = [];
    for (let i = 0; i < firstDow; i++) cells.push(null);
    for (let d = 1; d <= daysInMonth; d++) cells.push(d);

    return (
        <div className="chart-card">
            <div className="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h3 className="mb-0">Team Follow-Up Calendar</h3>
                    <p className="text-muted mb-0" style={{ fontSize: 12 }}>Follow-up density per day</p>
                </div>
                <div className="d-flex align-items-center gap-1">
                    <button className="btn btn-sm btn-outline-secondary px-2" style={{ lineHeight: 1 }} onClick={prevMonth}>
                        <span className="material-icons" style={{ fontSize: 18, verticalAlign: 'middle' }}>chevron_left</span>
                    </button>
                    <span className="fw-semibold px-2" style={{ minWidth: 130, textAlign: 'center', fontSize: 14 }}>
                        {MONTH_NAMES[month - 1]} {year}
                    </span>
                    <button className="btn btn-sm btn-outline-secondary px-2" style={{ lineHeight: 1 }} onClick={nextMonth}>
                        <span className="material-icons" style={{ fontSize: 18, verticalAlign: 'middle' }}>chevron_right</span>
                    </button>
                </div>
            </div>

            <div style={{ opacity: loading ? 0.5 : 1, minHeight: 220 }}>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7,1fr)', gap: 4 }}>
                    {DOW_LABELS.map(d => (
                        <div key={d} style={{ textAlign: 'center', fontSize: 11, fontWeight: 600, color: '#64748b', padding: '3px 0' }}>{d}</div>
                    ))}
                    {cells.map((d, i) => {
                        if (d === null) return <div key={`e${i}`} />;
                        const key   = `${year}-${pad(month)}-${pad(d)}`;
                        const count = days[key] || 0;
                        const ds    = densityStyle(count);

                        const isToday  = isThisMonth && d === todayD;
                        const cellDate = new Date(year, month - 1, d);
                        const today0   = new Date(todayY, todayM - 1, todayD);
                        const isPast   = cellDate < today0;

                        const bg       = ds ? ds.bg     : (isPast ? '#f8fafc' : '#ffffff');
                        const border   = ds ? ds.border : (isToday ? '#6366f1' : '#e2e8f0');
                        const numColor = ds ? ds.color  : (isPast ? '#94a3b8' : '#334155');
                        const outline  = isToday ? '2px solid #6366f1' : 'none';

                        let href = '';
                        if (count > 0) {
                            href = isToday ? '/manager/followups/today'
                                 : isPast  ? '/manager/followups/overdue'
                                           : '/manager/followups/upcoming';
                        }

                        return (
                            <div
                                key={key}
                                title={count > 0 ? `${count} follow-up${count > 1 ? 's' : ''}` : undefined}
                                onClick={() => href && (window.location.href = href)}
                                style={{
                                    borderRadius: 6, border: `1px solid ${border}`,
                                    background: bg, padding: '5px 2px', textAlign: 'center',
                                    cursor: count > 0 ? 'pointer' : 'default', outline, outlineOffset: 1,
                                }}
                            >
                                <div style={{ fontSize: 12, fontWeight: isToday ? 700 : 500, color: numColor }}>{d}</div>
                                {count > 0
                                    ? <div style={{ fontSize: 11, fontWeight: 700, color: ds.color, lineHeight: 1.2 }}>{count}</div>
                                    : <div style={{ height: 15 }} />
                                }
                            </div>
                        );
                    })}
                </div>
            </div>

            <div className="d-flex gap-3 mt-3 flex-wrap" style={{ fontSize: 12 }}>
                {[
                    { bg: '#dcfce7', border: '#bbf7d0', label: 'Low (1–3)',    bw: 1 },
                    { bg: '#fef9c3', border: '#fde68a', label: 'Medium (4–7)', bw: 1 },
                    { bg: '#fee2e2', border: '#fecaca', label: 'High (8+)',     bw: 1 },
                    { bg: '#fff',    border: '#6366f1', label: 'Today',         bw: 2 },
                ].map(({ bg, border, label, bw }) => (
                    <span key={label} className="d-flex align-items-center gap-1">
                        <span style={{ width: 12, height: 12, borderRadius: 3, background: bg, border: `${bw}px solid ${border}`, display: 'inline-block', flexShrink: 0 }} />
                        {label}
                    </span>
                ))}
            </div>
        </div>
    );
}

// ─── Main Page ────────────────────────────────────────────────────────────────
export default function Dashboard({
    period,
    leadsToday, leadsWeek, leadsMonth,
    totalCallsMade, totalCallDurationSec,
    whatsAppConversations, conversionRate,
    bestPerformingTelecaller,
    missedFollowups, missedFollowupList,
    leadSource, telecallerStats,
    telecallerPresence, missedInboundCalls,
    courseStats, followupCalendar,
    // URLs passed from controller
    presenceSnapshotUrl, calendarDataUrl,
    telecallersUrl, leadsCreateUrl,
}) {
    const periodLabels = { today: 'Today', week: 'This Week', month: 'This Month' };

    function changePeriod(e) {
        router.get(window.location.pathname, { period: e.target.value }, { preserveScroll: false });
    }

    return (
        <>
            <Head title="Manager Dashboard" />

            {/* ── Page header ─────────────────────────────────────────────── */}
            <div className="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div className="page-title">
                    <h2>Manager Dashboard</h2>
                    <span className="realtime-badge">Live</span>
                </div>

                <div className="date-filter d-flex align-items-center gap-1">
                    <span className="material-icons">calendar_today</span>
                    <select value={period} onChange={changePeriod}>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                    </select>
                    <span className="material-icons">expand_more</span>
                </div>
            </div>

            {/* ── Stat cards ──────────────────────────────────────────────── */}
            <div className="row g-3 mb-4">
                <div className="col-12 col-md-6 col-lg-4">
                    <div className="stat-card">
                        <div className="stat-icon blue"><span className="material-icons">groups</span></div>
                        <div className="stat-label">Total Leads (Today / Week / Month)</div>
                        <div className="stat-value">{leadsToday} / {leadsWeek} / {leadsMonth}</div>
                    </div>
                </div>

                <div className="col-6 col-md-6 col-lg-4">
                    <div className="stat-card">
                        <div className="stat-icon purple"><span className="material-icons">call</span></div>
                        <div className="stat-label">Total Calls Made ({periodLabels[period]})</div>
                        <div className="stat-value">{totalCallsMade}</div>
                    </div>
                </div>

                <div className="col-6 col-md-6 col-lg-4">
                    <div className="stat-card">
                        <div className="stat-icon amber"><span className="material-icons">timer</span></div>
                        <div className="stat-label">Total Call Duration ({periodLabels[period]})</div>
                        <div className="stat-value">{toTimeLabel(totalCallDurationSec)}</div>
                    </div>
                </div>

                <div className="col-6 col-md-6 col-lg-3">
                    <div className="stat-card">
                        <div className="stat-icon green"><span className="material-icons">chat</span></div>
                        <div className="stat-label">WhatsApp Conversations</div>
                        <div className="stat-value">{whatsAppConversations}</div>
                    </div>
                </div>

                <div className="col-6 col-md-6 col-lg-3">
                    <div className="stat-card highlight-success">
                        <div className="stat-icon blue"><span className="material-icons">insights</span></div>
                        <div className="stat-label">Conversion Rate %</div>
                        <div className="stat-value">{parseFloat(conversionRate).toFixed(2).replace(/\.?0+$/, '')}%</div>
                    </div>
                </div>

                <div className="col-12 col-md-6 col-lg-3">
                    <div className="stat-card">
                        <div className="stat-icon green"><span className="material-icons">emoji_events</span></div>
                        <div className="stat-label">Best Performing Telecaller</div>
                        <div className="stat-value" style={{ fontSize: 18 }}>
                            {bestPerformingTelecaller?.name ?? '—'}
                        </div>
                        <div className="stat-trend up">
                            {bestPerformingTelecaller
                                ? `${parseFloat(bestPerformingTelecaller.conversion_rate).toFixed(2)}% conversion`
                                : 'No data'}
                        </div>
                    </div>
                </div>

                <div className="col-12 col-md-6 col-lg-3">
                    <div className="stat-card highlight-danger">
                        <div className="stat-icon red"><span className="material-icons">event_busy</span></div>
                        <div className="stat-label">Missed Followups</div>
                        <div className="stat-value">{missedFollowups}</div>
                    </div>
                </div>
            </div>

            {/* ── Charts + Tables row ──────────────────────────────────────── */}
            <div className="row g-4">
                <div className="col-lg-8">
                    <LeadSourceChart leadSource={leadSource} />

                    <div className="custom-table mt-4">
                        <div className="table-header">
                            <h3>Telecaller Performance</h3>
                            <a href={telecallersUrl} className="text-primary text-decoration-none fw-bold" style={{ fontSize: 12 }}>View All</a>
                        </div>
                        <div className="table-responsive">
                            <table className="table mb-0">
                                <thead>
                                    <tr>
                                        <th>Staff Name</th>
                                        <th>Assigned</th>
                                        <th>Total Calls</th>
                                        <th className="text-center">Pending FU</th>
                                        <th className="text-end">Conv. Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {telecallerStats.length > 0 ? telecallerStats.map(t => (
                                        <tr key={t.id}>
                                            <td>{t.name}</td>
                                            <td>{t.assigned_count}</td>
                                            <td>{t.total_calls}</td>
                                            <td className="text-center">{t.pending_followups}</td>
                                            <td className="text-end">{parseFloat(t.conversion_rate).toFixed(2)}%</td>
                                        </tr>
                                    )) : (
                                        <tr><td colSpan={5} className="text-center text-muted py-4">No telecaller stats available.</td></tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div className="col-lg-4">
                    <PresencePanel initial={telecallerPresence} snapshotUrl={presenceSnapshotUrl} />

                    <div className="alerts-panel">
                        <div className="alerts-header">
                            <h3>
                                Missed Followups
                                <span className="material-icons" style={{ color: 'var(--warning-color)', fontSize: 20 }}>campaign</span>
                            </h3>
                            <span className="alert-count">{missedFollowups}</span>
                        </div>
                        <div className="alerts-content">
                            {missedFollowupList.length > 0 ? missedFollowupList.map(f => (
                                <div key={f.id} className="alert-item critical">
                                    <div className="alert-item-header">
                                        <p className="alert-item-name">{f.lead?.name ?? `Lead #${f.lead_id}`}</p>
                                        <span className="alert-time">
                                            {new Date(f.next_followup).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })}
                                        </span>
                                    </div>
                                    <p className="alert-description">
                                        Assigned: {f.lead?.assigned_user?.name ?? 'Unassigned'}
                                    </p>
                                </div>
                            )) : (
                                <p className="text-muted mb-0">No missed followups.</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* ── Missed Inbound Callbacks ─────────────────────────────────── */}
            <div className="row g-4 mt-1">
                <div className="col-12">
                    <div className="custom-table">
                        <div className="table-header">
                            <h3>Missed Inbound Callbacks</h3>
                        </div>
                        <div className="table-responsive">
                            <table className="table mb-0">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Lead</th>
                                        <th>Customer Number</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {missedInboundCalls.length > 0 ? missedInboundCalls.map(c => (
                                        <tr key={c.id}>
                                            <td>{c.created_at_formatted}</td>
                                            <td>{c.lead_code ? `${c.lead_code} - ${c.lead_name}` : (c.lead_name || 'Unknown')}</td>
                                            <td>{c.customer_number || c.lead_phone || '—'}</td>
                                            <td><span className="badge bg-danger">Missed</span></td>
                                            <td>
                                                {c.encrypted_lead_id
                                                    ? <a className="btn btn-sm btn-primary" href={`/manager/leads/${c.encrypted_lead_id}`}>Call Back</a>
                                                    : <button className="btn btn-sm btn-secondary" disabled>Call Back</button>
                                                }
                                            </td>
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td colSpan={5} className="text-center text-muted py-3">
                                                <span className="material-icons d-block mb-1" style={{ fontSize: 28, color: '#cbd5e1' }}>call_missed</span>
                                                No missed inbound callbacks.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {/* ── Course Performance ───────────────────────────────────────── */}
            {courseStats.length > 0 && (
                <div className="row g-4 mt-1">
                    <div className="col-12">
                        <div className="custom-table">
                            <div className="table-header">
                                <div>
                                    <h3>Course Performance</h3>
                                    <p className="text-muted mb-0" style={{ fontSize: 12 }}>Lead volume and conversion rate by course</p>
                                </div>
                            </div>
                            <div className="table-responsive">
                                <table className="table mb-0" style={{ fontSize: 13 }}>
                                    <thead>
                                        <tr>
                                            <th>Course</th>
                                            <th>Total Leads</th>
                                            <th>Conversions</th>
                                            <th>Rate</th>
                                            <th style={{ minWidth: 180 }}>Volume</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {(() => {
                                            const maxTotal = Math.max(...courseStats.map(r => r.total), 1);
                                            return courseStats.map((row, idx) => {
                                                const rateColor = row.rate >= 30
                                                    ? { bg: '#dcfce7', color: '#16a34a' }
                                                    : row.rate >= 10
                                                        ? { bg: '#fef9c3', color: '#92400e' }
                                                        : { bg: '#fee2e2', color: '#dc2626' };
                                                return (
                                                    <tr key={idx}>
                                                        <td className="fw-semibold">{row.course}</td>
                                                        <td>{row.total}</td>
                                                        <td>{row.conversions}</td>
                                                        <td>
                                                            <span className="badge" style={{ background: rateColor.bg, color: rateColor.color, padding: '3px 8px', borderRadius: 6, fontSize: 12 }}>
                                                                {row.rate}%
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div className="d-flex align-items-center gap-2">
                                                                <div style={{ flex: 1, background: '#e2e8f0', borderRadius: 4, height: 10, overflow: 'hidden', minWidth: 100 }}>
                                                                    <div style={{ background: '#6366f1', height: '100%', width: `${Math.round(row.total / maxTotal * 100)}%`, borderRadius: 4, minWidth: row.total > 0 ? 4 : 0 }} />
                                                                </div>
                                                                <span style={{ fontSize: 11, color: '#64748b', whiteSpace: 'nowrap' }}>{row.total} leads</span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                );
                                            });
                                        })()}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* ── Follow-up Calendar ───────────────────────────────────────── */}
            <div className="row g-4 mt-1">
                <div className="col-12">
                    <FollowupCalendar initialData={followupCalendar} calendarDataUrl={calendarDataUrl} />
                </div>
            </div>
        </>
    );
}
