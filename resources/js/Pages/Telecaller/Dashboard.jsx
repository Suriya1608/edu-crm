import { Head, Link } from '@inertiajs/react';
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

// ─── Follow-up calendar ───────────────────────────────────────────────────────
function FollowupCalendar({ initialData }) {
    const todayDate = new Date();
    const todayY = todayDate.getFullYear();
    const todayM = todayDate.getMonth() + 1;
    const todayD = todayDate.getDate();

    const [state, setState] = useState({
        year:  todayY,
        month: todayM,
        days:  initialData ?? {},
    });
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
            const res  = await fetch(`/telecaller/followups/calendar-data?year=${year}&month=${month}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            setState({ year: data.year, month: data.month, days: data.days || {} });
        } catch (_) {}
        setLoading(false);
    }

    function prevMonth() {
        let { year, month } = state;
        month--;
        if (month < 1) { month = 12; year--; }
        navigate(year, month);
    }

    function nextMonth() {
        let { year, month } = state;
        month++;
        if (month > 12) { month = 1; year++; }
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
                    <h3 className="mb-0">My Follow-Up Calendar</h3>
                    <p className="text-muted mb-0" style={{ fontSize: 12 }}>
                        Follow-up density per day — click any highlighted day
                    </p>
                </div>
                <div className="d-flex align-items-center gap-1">
                    <button
                        className="btn btn-sm btn-outline-secondary px-2"
                        style={{ lineHeight: 1 }}
                        onClick={prevMonth}
                    >
                        <span className="material-icons" style={{ fontSize: 18, verticalAlign: 'middle' }}>chevron_left</span>
                    </button>
                    <span className="fw-semibold px-2" style={{ minWidth: 130, textAlign: 'center', fontSize: 14 }}>
                        {MONTH_NAMES[month - 1]} {year}
                    </span>
                    <button
                        className="btn btn-sm btn-outline-secondary px-2"
                        style={{ lineHeight: 1 }}
                        onClick={nextMonth}
                    >
                        <span className="material-icons" style={{ fontSize: 18, verticalAlign: 'middle' }}>chevron_right</span>
                    </button>
                </div>
            </div>

            <div style={{ opacity: loading ? 0.5 : 1, minHeight: 220 }}>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7,1fr)', gap: 4 }}>
                    {DOW_LABELS.map(d => (
                        <div key={d} style={{ textAlign: 'center', fontSize: 11, fontWeight: 600, color: '#64748b', padding: '3px 0' }}>
                            {d}
                        </div>
                    ))}
                    {cells.map((d, i) => {
                        if (d === null) return <div key={`e${i}`} />;
                        const key   = `${year}-${pad(month)}-${pad(d)}`;
                        const count = days[key] || 0;
                        const ds    = densityStyle(count);

                        const isToday   = isThisMonth && d === todayD;
                        const cellDate  = new Date(year, month - 1, d);
                        const todayFull = new Date(todayY, todayM - 1, todayD);
                        const isPast    = cellDate < todayFull;

                        const bg       = ds ? ds.bg     : (isPast ? '#f8fafc' : '#ffffff');
                        const border   = ds ? ds.border : (isToday ? '#6366f1' : '#e2e8f0');
                        const numColor = ds ? ds.color  : (isPast ? '#94a3b8' : '#334155');
                        const outline  = isToday ? '2px solid #6366f1' : 'none';

                        let href = '';
                        if (count > 0) {
                            href = isToday ? '/telecaller/followups/today'
                                 : isPast  ? '/telecaller/followups/overdue'
                                           : '/telecaller/followups/upcoming';
                        }

                        return (
                            <div
                                key={key}
                                title={count > 0 ? `${count} follow-up${count > 1 ? 's' : ''}` : undefined}
                                onClick={() => href && (window.location.href = href)}
                                style={{
                                    borderRadius: 6,
                                    border: `1px solid ${border}`,
                                    background: bg,
                                    padding: '5px 2px',
                                    textAlign: 'center',
                                    cursor: count > 0 ? 'pointer' : 'default',
                                    outline,
                                    outlineOffset: 1,
                                }}
                            >
                                <div style={{ fontSize: 12, fontWeight: isToday ? 700 : 500, color: numColor }}>
                                    {d}
                                </div>
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
                        <span style={{
                            width: 12, height: 12, borderRadius: 3,
                            background: bg, border: `${bw}px solid ${border}`,
                            display: 'inline-block', flexShrink: 0,
                        }} />
                        {label}
                    </span>
                ))}
            </div>
        </div>
    );
}

// ─── Missed callbacks panel ───────────────────────────────────────────────────
function MissedCallbacksPanel({ callbacks }) {
    if (!callbacks || callbacks.length === 0) {
        return <div className="text-muted small">No missed callbacks.</div>;
    }
    return (
        <div className="d-flex flex-column gap-2">
            {callbacks.map(item => (
                <div key={item.id} className="border rounded p-2">
                    <div className="fw-semibold">{item.lead_name || 'Unknown Lead'}</div>
                    <small className="text-muted">
                        {item.lead_code || '-'} | {item.lead_phone || item.phone || '-'}
                    </small>
                    {item.encrypted_lead_id && (
                        <div className="mt-2">
                            <Link
                                href={`/telecaller/leads/${item.encrypted_lead_id}`}
                                className="btn btn-sm btn-primary"
                            >
                                Call Back
                            </Link>
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────
export default function Dashboard({ stats: initialStats, missed_callbacks: initialCallbacks, followup_calendar }) {
    const [stats,     setStats]     = useState(initialStats     ?? {});
    const [callbacks, setCallbacks] = useState(initialCallbacks ?? []);
    const missedPanelRef = useRef(null);

    const fetchSnapshot = useCallback(async () => {
        try {
            const res  = await fetch('/telecaller/panel/snapshot', { headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (!data?.ok) return;
            setStats({
                assigned:       Number(data.total_assigned_leads       || 0),
                new_leads:      Number(data.new_leads                  || 0),
                followups:      Number(data.today_followup_count       || 0),
                overdue:        Number(data.overdue_followup_count     || 0),
                calls:          Number(data.total_calls_today          || 0),
                talk_time_secs: Number(data.talk_time_today_seconds    || 0),
                active_calls:   Number(data.active_call_count          || 0),
            });
            if (Array.isArray(data.missed_callbacks)) {
                setCallbacks(data.missed_callbacks);
            }
        } catch (_) {}
    }, []);

    useEffect(() => {
        fetchSnapshot();
        const t = setInterval(fetchSnapshot, 45_000);
        return () => clearInterval(t);
    }, [fetchSnapshot]);

    const activeCalls = stats.active_calls ?? 0;
    const showAlert   = (stats.followups ?? 0) > 0 || (stats.overdue ?? 0) > 0;

    return (
        <>
            <Head title="Dashboard" />

            {/* ── Call-status badges (replaces @section header_actions) ────── */}
            <div className="d-flex align-items-center gap-2 flex-wrap mb-4">
                <span className="badge rounded-pill text-bg-light border px-3 py-2 d-flex align-items-center gap-1">
                    <span className="material-icons" style={{ fontSize: 15 }}>call</span>
                    {activeCalls > 0 ? 'On Call' : 'Idle'}
                </span>
                <span className={`badge rounded-pill px-3 py-2 ${activeCalls > 0 ? 'text-bg-danger' : 'text-bg-success'}`}>
                    Active Calls: {activeCalls}
                </span>
            </div>

            {/* ── Follow-up reminder alert ─────────────────────────────────── */}
            {showAlert && (
                <div className="alert alert-warning">
                    <strong>Follow-up reminder:</strong>{' '}
                    Today: {stats.followups ?? 0}, Overdue: {stats.overdue ?? 0}.{' '}
                    <Link href="/telecaller/leads?status=follow_up" className="fw-semibold ms-2">
                        Open Leads
                    </Link>
                </div>
            )}

            {/* ── Stat row 1: Lead & Followup counts ──────────────────────── */}
            <div className="row g-3 mb-4">
                {[
                    { label: 'Total Assigned Leads', value: stats.assigned,  icon: 'assignment_ind', cls: 'blue'  },
                    { label: 'New Leads',            value: stats.new_leads, icon: 'fiber_new',      cls: 'green' },
                    { label: 'Followups Today',      value: stats.followups, icon: 'event',          cls: 'amber' },
                    { label: 'Overdue Followups',    value: stats.overdue,   icon: 'warning',        cls: 'red', highlight: true },
                ].map(card => (
                    <div key={card.label} className="col-6 col-md-3">
                        <div className={`stat-card${card.highlight ? ' highlight-danger' : ''}`}>
                            <div className={`stat-icon ${card.cls}`}>
                                <span className="material-icons">{card.icon}</span>
                            </div>
                            <div className="stat-label">{card.label}</div>
                            <div className="stat-value">{card.value ?? '—'}</div>
                        </div>
                    </div>
                ))}
            </div>

            {/* ── Stat row 2: Call stats ───────────────────────────────────── */}
            <div className="row g-3 mb-4">
                <div className="col-6 col-md-4">
                    <div className="stat-card">
                        <div className="stat-icon blue"><span className="material-icons">call</span></div>
                        <div className="stat-label">Total Calls Today</div>
                        <div className="stat-value">{stats.calls ?? '—'}</div>
                    </div>
                </div>
                <div className="col-6 col-md-4">
                    <div className="stat-card">
                        <div className="stat-icon green"><span className="material-icons">timer</span></div>
                        <div className="stat-label">Talk Time Today</div>
                        <div className="stat-value">{toTimeLabel(stats.talk_time_secs)}</div>
                    </div>
                </div>
                <div className="col-12 col-md-4">
                    <div className="stat-card">
                        <div className="stat-icon red"><span className="material-icons">phone_missed</span></div>
                        <div className="stat-label">Missed Call Alerts</div>
                        <div className="stat-value">{callbacks.length}</div>
                    </div>
                </div>
            </div>

            {/* ── Quick actions + Missed callbacks ─────────────────────────── */}
            <div className="row g-3">
                <div className="col-lg-8">
                    <div className="chart-card h-100">
                        <div className="chart-header mb-3">
                            <h3>Quick Actions</h3>
                            <p>Speed up daily workflow</p>
                        </div>
                        <div className="d-flex gap-2 flex-wrap">
                            <Link href="/telecaller/leads?status=new" className="btn btn-outline-primary btn-sm">
                                <span className="material-icons align-middle" style={{ fontSize: 16 }}>new_releases</span>{' '}
                                New Leads
                            </Link>
                            <Link href="/telecaller/leads?status=follow_up" className="btn btn-outline-warning btn-sm text-dark">
                                <span className="material-icons align-middle" style={{ fontSize: 16 }}>event</span>{' '}
                                Follow-ups Due
                            </Link>
                            <button
                                type="button"
                                className="btn btn-outline-danger btn-sm"
                                onClick={() => missedPanelRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })}
                            >
                                <span className="material-icons align-middle" style={{ fontSize: 16 }}>phone_missed</span>{' '}
                                Missed Callbacks
                            </button>
                            <button
                                type="button"
                                className="btn btn-outline-secondary btn-sm"
                                onClick={fetchSnapshot}
                            >
                                <span className="material-icons align-middle" style={{ fontSize: 16 }}>refresh</span>{' '}
                                Refresh Status
                            </button>
                        </div>
                    </div>
                </div>

                <div className="col-lg-4" ref={missedPanelRef}>
                    <div className="chart-card h-100">
                        <div className="chart-header mb-2">
                            <h3>Missed Callback Alerts</h3>
                            <p>{callbacks.length} pending callback(s)</p>
                        </div>
                        <MissedCallbacksPanel callbacks={callbacks} />
                    </div>
                </div>
            </div>

            {/* ── Follow-up calendar ──────────────────────────────────────── */}
            <div className="row g-4 mt-1">
                <div className="col-12">
                    <FollowupCalendar initialData={followup_calendar} />
                </div>
            </div>
        </>
    );
}
