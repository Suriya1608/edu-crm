import { Head, Link } from '@inertiajs/react';

// ─── Helpers ──────────────────────────────────────────────────────────────────
const TABS = [
    { key: 'daily',   href: '/telecaller/performance/daily',   label: 'Today' },
    { key: 'weekly',  href: '/telecaller/performance/weekly',  label: 'This Week' },
    { key: 'monthly', href: '/telecaller/performance/monthly', label: 'This Month' },
];

const OUTCOME_META = {
    interested:      { label: 'Interested',       color: '#10b981', icon: 'thumb_up' },
    not_interested:  { label: 'Not Interested',   color: '#ef4444', icon: 'thumb_down' },
    call_back_later: { label: 'Call Back Later',  color: '#f59e0b', icon: 'schedule' },
    switched_off:    { label: 'Switched Off',     color: '#64748b', icon: 'phone_disabled' },
    wrong_number:    { label: 'Wrong Number',     color: '#8b5cf6', icon: 'call_missed' },
};

const STATUS_META = {
    new:         { label: 'New',         color: '#6366f1' },
    contacted:   { label: 'Contacted',   color: '#06b6d4' },
    interested:  { label: 'Interested',  color: '#10b981' },
    converted:   { label: 'Converted',   color: '#f59e0b' },
    not_interested: { label: 'Not Interested', color: '#ef4444' },
    lost:        { label: 'Lost',        color: '#94a3b8' },
};

function scoreGrade(score) {
    if (score >= 80) return { grade: 'A', label: 'Excellent', color: '#10b981' };
    if (score >= 60) return { grade: 'B', label: 'Good',      color: '#6366f1' };
    if (score >= 40) return { grade: 'C', label: 'Average',   color: '#f59e0b' };
    return                  { grade: 'D', label: 'Needs Work', color: '#ef4444' };
}

// ─── Stat Card ────────────────────────────────────────────────────────────────
function KpiCard({ icon, iconColor, label, value, sub, badge, badgeColor }) {
    return (
        <div className="col-6 col-lg-3">
            <div style={{
                background: '#fff',
                borderRadius: 16,
                padding: '20px 18px',
                boxShadow: '0 1px 6px rgba(15,23,42,.07)',
                height: '100%',
                position: 'relative',
                overflow: 'hidden',
            }}>
                <div style={{
                    width: 44, height: 44, borderRadius: 12,
                    background: iconColor + '18',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    marginBottom: 12,
                }}>
                    <span className="material-icons" style={{ color: iconColor, fontSize: 22 }}>{icon}</span>
                </div>
                <div style={{ fontSize: 11, fontWeight: 600, color: '#64748b', textTransform: 'uppercase', letterSpacing: .6, marginBottom: 4 }}>
                    {label}
                </div>
                <div style={{ fontSize: 26, fontWeight: 800, color: '#0f172a', lineHeight: 1.1, marginBottom: 4 }}>
                    {value}
                </div>
                {sub && <div style={{ fontSize: 12, color: '#94a3b8', marginTop: 2 }}>{sub}</div>}
                {badge && (
                    <span style={{
                        position: 'absolute', top: 14, right: 14,
                        background: (badgeColor || '#6366f1') + '18',
                        color: badgeColor || '#6366f1',
                        fontSize: 11, fontWeight: 700,
                        padding: '2px 8px', borderRadius: 20,
                    }}>{badge}</span>
                )}
            </div>
        </div>
    );
}

// ─── Section heading ──────────────────────────────────────────────────────────
function SectionTitle({ icon, title, right }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 14 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <span className="material-icons" style={{ fontSize: 20, color: '#6366f1' }}>{icon}</span>
                <span style={{ fontWeight: 700, fontSize: 15, color: '#0f172a' }}>{title}</span>
            </div>
            {right && <span style={{ fontSize: 12, color: '#94a3b8' }}>{right}</span>}
        </div>
    );
}

// ─── Outcome bar row ──────────────────────────────────────────────────────────
function OutcomeRow({ outcome, count, total }) {
    const meta  = OUTCOME_META[outcome] || { label: outcome, color: '#64748b', icon: 'call' };
    const pct   = total > 0 ? Math.round((count / total) * 100) : 0;
    return (
        <div style={{ marginBottom: 14 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 5 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 7 }}>
                    <span className="material-icons" style={{ fontSize: 16, color: meta.color }}>{meta.icon}</span>
                    <span style={{ fontSize: 13, fontWeight: 600, color: '#334155' }}>{meta.label}</span>
                </div>
                <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                    <span style={{ fontSize: 13, color: '#64748b' }}>{count} calls</span>
                    <span style={{
                        minWidth: 38, textAlign: 'center',
                        background: meta.color + '18', color: meta.color,
                        fontSize: 11, fontWeight: 700,
                        padding: '2px 7px', borderRadius: 20,
                    }}>{pct}%</span>
                </div>
            </div>
            <div style={{ background: '#f1f5f9', borderRadius: 99, height: 6 }}>
                <div style={{
                    width: pct + '%', height: '100%',
                    borderRadius: 99, background: meta.color,
                    transition: 'width .6s ease',
                    minWidth: pct > 0 ? 6 : 0,
                }} />
            </div>
        </div>
    );
}

// ─── Hourly heatmap bar ───────────────────────────────────────────────────────
function HourlyChart({ hourlyBreakdown }) {
    const maxCalls = Math.max(...hourlyBreakdown.map(h => h.calls), 1);
    const workHours = hourlyBreakdown.filter(h => h.hour >= 8 && h.hour <= 20);
    return (
        <div style={{ overflowX: 'auto' }}>
            <div style={{ display: 'flex', alignItems: 'flex-end', gap: 4, minWidth: 360, height: 80, paddingBottom: 22 }}>
                {workHours.map(h => {
                    const heightPct = (h.calls / maxCalls) * 100;
                    const active    = h.calls > 0;
                    return (
                        <div key={h.hour} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 3 }}>
                            <div title={`${h.calls} calls at ${h.hour}:00`} style={{
                                width: '100%', borderRadius: '4px 4px 0 0',
                                height: active ? Math.max(heightPct * 0.55, 4) + 'px' : '4px',
                                background: active ? '#6366f1' : '#e2e8f0',
                                cursor: 'default',
                            }} />
                            <span style={{ fontSize: 9, color: '#94a3b8', whiteSpace: 'nowrap' }}>
                                {h.hour}h
                            </span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

// ─── Main ─────────────────────────────────────────────────────────────────────
export default function Index({
    title, scope, period,
    callsHandled, talkTimeLabel, talkMinutes, avgCallDuration,
    conversionPercent, totalAssigned,
    followupsCompleted, pendingFollowups,
    responseTimeLabel,
    outcomeBreakdown, leadStatusRows,
    dailyBreakdown, hourlyBreakdown, bestDay,
    productivityScore,
}) {
    const { grade, label: gradeLabel, color: gradeColor } = scoreGrade(productivityScore);
    const totalOutcomeCalls = Object.values(outcomeBreakdown).reduce((a, b) => a + b, 0);
    const totalLeads = Object.values(leadStatusRows).reduce((a, b) => a + b, 0);

    return (
        <>
            <Head title={title} />

            {/* ── Hero header ────────────────────────────────────────────── */}
            <div style={{
                background: 'linear-gradient(135deg, #6366f1 0%, #4f46e5 100%)',
                borderRadius: 20, padding: '24px 28px', marginBottom: 24,
                color: '#fff', position: 'relative', overflow: 'hidden',
            }}>
                {/* Decorative blob */}
                <div style={{
                    position: 'absolute', top: -40, right: -40,
                    width: 160, height: 160, borderRadius: '50%',
                    background: 'rgba(255,255,255,.08)',
                }} />
                <div style={{
                    position: 'absolute', bottom: -30, right: 80,
                    width: 100, height: 100, borderRadius: '50%',
                    background: 'rgba(255,255,255,.05)',
                }} />

                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16 }}>
                    <div>
                        <div style={{ fontSize: 12, fontWeight: 600, opacity: .75, textTransform: 'uppercase', letterSpacing: 1, marginBottom: 6 }}>
                            My Performance
                        </div>
                        <h1 style={{ fontSize: 26, fontWeight: 800, margin: 0, marginBottom: 6 }}>{title}</h1>
                        <p style={{ margin: 0, opacity: .75, fontSize: 13 }}>{period}</p>
                    </div>

                    {/* Score badge */}
                    <div style={{
                        background: 'rgba(255,255,255,.15)', backdropFilter: 'blur(10px)',
                        borderRadius: 16, padding: '16px 22px',
                        textAlign: 'center', minWidth: 110,
                    }}>
                        <div style={{ fontSize: 38, fontWeight: 900, lineHeight: 1 }}>{productivityScore}</div>
                        <div style={{ fontSize: 11, opacity: .8, marginTop: 2 }}>/ 100 score</div>
                        <div style={{
                            marginTop: 6, background: gradeColor,
                            borderRadius: 20, padding: '2px 12px',
                            fontSize: 12, fontWeight: 700, display: 'inline-block',
                        }}>{grade} · {gradeLabel}</div>
                    </div>
                </div>

                {/* Scope tabs */}
                <div style={{ display: 'flex', gap: 8, marginTop: 20, flexWrap: 'wrap' }}>
                    {TABS.map(tab => (
                        <Link key={tab.key} href={tab.href} style={{
                            padding: '6px 18px', borderRadius: 99, fontSize: 13, fontWeight: 600,
                            textDecoration: 'none', transition: 'all .2s',
                            background: scope === tab.key ? '#fff' : 'rgba(255,255,255,.18)',
                            color: scope === tab.key ? '#4f46e5' : '#fff',
                            border: 'none',
                        }}>
                            {tab.label}
                        </Link>
                    ))}
                </div>
            </div>

            {/* ── KPI cards ──────────────────────────────────────────────── */}
            <div className="row g-3 mb-4">
                <KpiCard
                    icon="call" iconColor="#6366f1"
                    label="Calls Handled"
                    value={callsHandled}
                    sub={`Avg duration: ${avgCallDuration}`}
                />
                <KpiCard
                    icon="timer" iconColor="#06b6d4"
                    label="Total Talk Time"
                    value={talkTimeLabel}
                    sub={`${talkMinutes} minutes on calls`}
                />
                <KpiCard
                    icon="trending_up" iconColor="#10b981"
                    label="Conversion Rate"
                    value={`${conversionPercent}%`}
                    sub={`${totalAssigned} leads assigned`}
                    badge={conversionPercent > 0 ? `${conversionPercent}%` : null}
                    badgeColor="#10b981"
                />
                <KpiCard
                    icon="task_alt" iconColor="#f59e0b"
                    label="Followups Done"
                    value={followupsCompleted}
                    sub={pendingFollowups > 0 ? `⚠ ${pendingFollowups} overdue` : 'None overdue'}
                    badge={pendingFollowups > 0 ? `${pendingFollowups} pending` : null}
                    badgeColor="#ef4444"
                />
                <KpiCard
                    icon="speed" iconColor="#8b5cf6"
                    label="Avg Response Time"
                    value={responseTimeLabel}
                    sub="From lead assignment to first contact"
                />
                {bestDay && (
                    <KpiCard
                        icon="star" iconColor="#f59e0b"
                        label="Best Day"
                        value={bestDay.calls + ' calls'}
                        sub={bestDay.day}
                        badge="Peak"
                        badgeColor="#f59e0b"
                    />
                )}
            </div>

            {/* ── Middle row: Outcomes + Lead pipeline ───────────────────── */}
            <div className="row g-3 mb-4">
                {/* Outcome breakdown */}
                <div className="col-md-6">
                    <div style={{ background: '#fff', borderRadius: 16, padding: '20px 22px', boxShadow: '0 1px 6px rgba(15,23,42,.07)', height: '100%' }}>
                        <SectionTitle
                            icon="donut_large"
                            title="Call Outcomes"
                            right={`${totalOutcomeCalls} classified`}
                        />
                        {totalOutcomeCalls === 0 ? (
                            <div style={{ textAlign: 'center', padding: '30px 0', color: '#94a3b8', fontSize: 13 }}>
                                <span className="material-icons" style={{ fontSize: 36, display: 'block', marginBottom: 8 }}>call_end</span>
                                No outcome data yet
                            </div>
                        ) : (
                            Object.entries(outcomeBreakdown).map(([key, count]) => (
                                <OutcomeRow key={key} outcome={key} count={count} total={totalOutcomeCalls} />
                            ))
                        )}
                    </div>
                </div>

                {/* Lead pipeline */}
                <div className="col-md-6">
                    <div style={{ background: '#fff', borderRadius: 16, padding: '20px 22px', boxShadow: '0 1px 6px rgba(15,23,42,.07)', height: '100%' }}>
                        <SectionTitle
                            icon="account_tree"
                            title="My Lead Pipeline"
                            right={`${totalLeads} total`}
                        />
                        {totalLeads === 0 ? (
                            <div style={{ textAlign: 'center', padding: '30px 0', color: '#94a3b8', fontSize: 13 }}>
                                <span className="material-icons" style={{ fontSize: 36, display: 'block', marginBottom: 8 }}>person_search</span>
                                No leads assigned yet
                            </div>
                        ) : (
                            Object.entries(leadStatusRows).map(([status, count]) => {
                                const meta = STATUS_META[status] || { label: status, color: '#64748b' };
                                const pct  = Math.round((count / totalLeads) * 100);
                                return (
                                    <div key={status} style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 12 }}>
                                        <div style={{
                                            width: 10, height: 10, borderRadius: '50%',
                                            background: meta.color, flexShrink: 0,
                                        }} />
                                        <span style={{ flex: 1, fontSize: 13, color: '#334155', fontWeight: 500 }}>{meta.label}</span>
                                        <div style={{ width: 90, background: '#f1f5f9', borderRadius: 99, height: 6 }}>
                                            <div style={{
                                                width: pct + '%', height: '100%',
                                                background: meta.color, borderRadius: 99,
                                                minWidth: pct > 0 ? 4 : 0,
                                            }} />
                                        </div>
                                        <span style={{ fontSize: 12, color: '#64748b', minWidth: 28, textAlign: 'right' }}>{count}</span>
                                    </div>
                                );
                            })
                        )}
                    </div>
                </div>
            </div>

            {/* ── Hourly call distribution ────────────────────────────────── */}
            <div style={{ background: '#fff', borderRadius: 16, padding: '20px 22px', boxShadow: '0 1px 6px rgba(15,23,42,.07)', marginBottom: 24 }}>
                <SectionTitle icon="bar_chart" title="Call Activity by Hour" right="8AM – 8PM" />
                {callsHandled === 0 ? (
                    <div style={{ textAlign: 'center', padding: '20px 0', color: '#94a3b8', fontSize: 13 }}>No call activity for this period</div>
                ) : (
                    <HourlyChart hourlyBreakdown={hourlyBreakdown} />
                )}
            </div>

            {/* ── Daily breakdown table ───────────────────────────────────── */}
            <div style={{ background: '#fff', borderRadius: 16, overflow: 'hidden', boxShadow: '0 1px 6px rgba(15,23,42,.07)' }}>
                <div style={{ padding: '18px 22px', borderBottom: '1px solid #f1f5f9', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <span className="material-icons" style={{ fontSize: 20, color: '#6366f1' }}>calendar_today</span>
                        <span style={{ fontWeight: 700, fontSize: 15, color: '#0f172a' }}>Call Activity Breakdown</span>
                    </div>
                    <span style={{ fontSize: 12, color: '#94a3b8' }}>{dailyBreakdown.length} day(s)</span>
                </div>
                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ background: '#f8fafc' }}>
                                {['Date', 'Calls', 'Talk Time', 'Avg/Call'].map(h => (
                                    <th key={h} style={{ padding: '10px 22px', textAlign: 'left', fontSize: 11, fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: .6, whiteSpace: 'nowrap' }}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {dailyBreakdown.length === 0 ? (
                                <tr>
                                    <td colSpan={4} style={{ padding: '36px 22px', textAlign: 'center', color: '#94a3b8', fontSize: 13 }}>
                                        <span className="material-icons" style={{ fontSize: 32, display: 'block', marginBottom: 8 }}>event_busy</span>
                                        No activity for this period
                                    </td>
                                </tr>
                            ) : dailyBreakdown.map((row, i) => {
                                const avgSec = row.calls > 0 ? Math.round(row.talk_secs / row.calls) : 0;
                                const avgFmt = gmdate(avgSec);
                                return (
                                    <tr key={i} style={{ borderTop: '1px solid #f1f5f9' }}>
                                        <td style={{ padding: '13px 22px', fontWeight: 600, color: '#0f172a', fontSize: 13 }}>{row.day}</td>
                                        <td style={{ padding: '13px 22px' }}>
                                            <span style={{
                                                background: '#6366f118', color: '#6366f1',
                                                fontWeight: 700, fontSize: 13,
                                                padding: '3px 10px', borderRadius: 20,
                                            }}>{row.calls}</span>
                                        </td>
                                        <td style={{ padding: '13px 22px', fontSize: 13, color: '#334155', fontFamily: 'monospace' }}>{row.talk_time}</td>
                                        <td style={{ padding: '13px 22px', fontSize: 13, color: '#64748b', fontFamily: 'monospace' }}>{avgFmt}</td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

function gmdate(secs) {
    secs = Math.max(0, secs);
    const m = String(Math.floor(secs / 60)).padStart(2, '0');
    const s = String(secs % 60).padStart(2, '0');
    return `${m}:${s}`;
}
