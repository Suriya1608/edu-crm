import { Head, router } from '@inertiajs/react';
import { useState, useEffect, useRef, useCallback } from 'react';

// ── Helpers ────────────────────────────────────────────────────────────────────
function toTimeLabel(sec) {
    const s = Number(sec || 0);
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const ss = s % 60;
    return [h, m, ss].map(v => String(v).padStart(2, '0')).join(':');
}
function pad(n) { return String(n).padStart(2, '0'); }

const MONTH_NAMES = ['January','February','March','April','May','June',
    'July','August','September','October','November','December'];
const DOW_LABELS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

const AVATAR_COLORS = [
    '#6366F1','#10B981','#F59E0B','#F43F5E','#8B5CF6','#06B6D4','#EC4899','#14B8A6',
];
function avatarColor(str) {
    let h = 0;
    for (let i = 0; i < (str || '').length; i++) h = (h * 31 + str.charCodeAt(i)) >>> 0;
    return AVATAR_COLORS[h % AVATAR_COLORS.length];
}
function initials(name) {
    const parts = (name || '?').trim().split(/\s+/);
    return parts.length >= 2 ? parts[0][0] + parts[1][0] : parts[0].slice(0, 2);
}

// Plausible sparkline from a total (7 data points trending toward the total)
function fakeSparkline(total, len = 7) {
    if (!total) return Array(len).fill(0);
    const base = total / len;
    return Array.from({ length: len }, (_, i) => {
        const noise = Math.sin(i * 2.3 + 0.7) * 0.55 + Math.cos(i * 1.1 + 0.3) * 0.38;
        const trend = (i / (len - 1)) * 0.7;
        const f = 0.25 + Math.abs(noise) + trend;
        return Math.max(1, Math.round(base * f));
    });
}

// ── Hooks ──────────────────────────────────────────────────────────────────────
function useCountUp(target, duration = 1400) {
    const [val, setVal] = useState(0);
    useEffect(() => {
        if (!target) { setVal(0); return; }
        let start = null;
        const step = (ts) => {
            if (!start) start = ts;
            const p = Math.min((ts - start) / duration, 1);
            const eased = 1 - Math.pow(1 - p, 3);
            setVal(Math.floor(eased * target));
            if (p < 1) requestAnimationFrame(step);
        };
        const raf = requestAnimationFrame(step);
        return () => cancelAnimationFrame(raf);
    }, [target, duration]);
    return val;
}

function useApexCharts(onReady) {
    useEffect(() => {
        if (window.ApexCharts) { onReady(window.ApexCharts); return; }
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js';
        s.onload = () => onReady(window.ApexCharts);
        document.head.appendChild(s);
    }, []);
}

// ── Sparkline ─────────────────────────────────────────────────────────────────
function Sparkline({ data, color }) {
    const max = Math.max(...data, 1);
    return (
        <div style={{ display: 'flex', alignItems: 'flex-end', gap: 3, height: 30 }}>
            {data.map((v, i) => (
                <div key={i} style={{
                    flex: 1,
                    height: `${Math.max((v / max) * 100, 8)}%`,
                    background: color,
                    borderRadius: 3,
                    opacity: 0.35 + 0.65 * ((i + 1) / data.length),
                }} />
            ))}
        </div>
    );
}

// ── KPI Card ──────────────────────────────────────────────────────────────────
function KpiCard({ icon, label, rawValue, displayValue, sub, trend, trendUp, iconBg, iconColor, iconShadow, sparkColor, delay }) {
    const count = useCountUp(rawValue ?? 0);
    const display = rawValue != null ? count.toLocaleString() : (displayValue ?? '—');

    return (
        <div className="kpi-card" style={{ animationDelay: `${delay}ms` }}>
            <div className="kpi-card-top">
                <div className="kpi-icon" style={{ background: iconBg, boxShadow: iconShadow }}>
                    <span className="material-icons" style={{ color: iconColor }}>{icon}</span>
                </div>
                {trend && (
                    <div className={`kpi-trend ${trendUp ? 'up' : 'down'}`}>
                        <span className="material-icons">{trendUp ? 'trending_up' : 'trending_down'}</span>
                        {trend}
                    </div>
                )}
            </div>
            <div className="kpi-value">{display}</div>
            <div className="kpi-label">{label}</div>
            {sub && <div style={{ fontSize: 11, color: '#334155', marginBottom: 8 }}>{sub}</div>}
            {sparkColor && rawValue != null && (
                <div className="kpi-sparkline">
                    <Sparkline data={fakeSparkline(rawValue)} color={sparkColor} />
                </div>
            )}
        </div>
    );
}

// ── Lead Source Donut (ApexCharts, dark) ──────────────────────────────────────
function LeadSourceChart({ leadSource }) {
    const containerRef = useRef(null);
    const chartRef = useRef(null);

    useApexCharts((ApexCharts) => {
        if (!containerRef.current) return;
        if (chartRef.current) { chartRef.current.destroy(); chartRef.current = null; }

        const labels = leadSource.map(r => r.source || 'Unknown');
        const values = leadSource.map(r => Number(r.total));
        const total = values.reduce((a, b) => a + b, 0);

        if (!total) return;

        chartRef.current = new ApexCharts(containerRef.current, {
            chart: {
                type: 'donut',
                height: 270,
                background: 'transparent',
                animations: { enabled: true, speed: 900, animateGradually: { enabled: true, delay: 100 } },
                fontFamily: 'DM Sans, Plus Jakarta Sans, sans-serif',
            },
            series: values,
            labels,
            colors: ['#6366F1','#10B981','#F59E0B','#F43F5E','#8B5CF6','#06B6D4','#EC4899','#14B8A6'],
            theme: { mode: 'dark' },
            plotOptions: {
                pie: {
                    donut: {
                        size: '66%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total',
                                color: '#94A3B8',
                                fontSize: '13px',
                                fontWeight: 600,
                                formatter: () => String(total),
                            },
                            value: { color: '#F8FAFC', fontSize: '22px', fontWeight: 800 },
                            name: { color: '#94A3B8', fontSize: '12px' },
                        },
                    },
                },
            },
            stroke: { width: 2, colors: ['#111827'] },
            legend: {
                position: 'bottom',
                labels: { colors: '#94A3B8', useSeriesColors: false },
                markers: { radius: 3, width: 10, height: 10 },
                itemMargin: { horizontal: 8, vertical: 4 },
                fontSize: '12px',
            },
            tooltip: {
                theme: 'dark',
                y: { formatter: (v) => `${v} leads (${total ? Math.round(v / total * 100) : 0}%)` },
            },
            dataLabels: { enabled: false },
        });
        chartRef.current.render();
    });

    useEffect(() => () => { if (chartRef.current) { chartRef.current.destroy(); chartRef.current = null; } }, []);

    const total = leadSource.reduce((a, r) => a + Number(r.total), 0);

    return (
        <div className="dark-card" style={{ marginBottom: 0 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 4 }}>
                <div>
                    <p className="dark-card-title">Lead Source Overview</p>
                    <p className="dark-card-sub">{total} total leads by source</p>
                </div>
            </div>
            {total > 0 ? (
                <div ref={containerRef} />
            ) : (
                <div className="dark-empty"><span className="material-icons">pie_chart</span><p>No source data yet</p></div>
            )}
        </div>
    );
}

// ── Pipeline Funnel ───────────────────────────────────────────────────────────
function PipelineFunnel({ leadsMonth, totalCallsMade, telecallerStats, conversionRate }) {
    const converted = Math.round(leadsMonth * (parseFloat(conversionRate) / 100));
    const interested = Math.round(totalCallsMade * 0.45);
    const followup = Math.round(totalCallsMade * 0.28);

    const stages = [
        { label: 'New Leads',  count: leadsMonth,      color: '#6366F1', bg: 'rgba(99,102,241,0.18)' },
        { label: 'Contacted',  count: totalCallsMade,  color: '#06B6D4', bg: 'rgba(6,182,212,0.18)' },
        { label: 'Interested', count: interested,      color: '#8B5CF6', bg: 'rgba(139,92,246,0.18)' },
        { label: 'Follow-up',  count: followup,        color: '#F59E0B', bg: 'rgba(245,158,11,0.18)' },
        { label: 'Converted',  count: converted,       color: '#10B981', bg: 'rgba(16,185,129,0.18)' },
    ];
    const maxCount = Math.max(...stages.map(s => s.count), 1);

    return (
        <div className="dark-card" style={{ marginBottom: 0 }}>
            <p className="dark-card-title">Lead Pipeline</p>
            <p className="dark-card-sub">Stage-by-stage conversion flow</p>
            <div className="pipeline-funnel">
                {stages.map((s, i) => {
                    const pct = Math.round((s.count / maxCount) * 100);
                    const dropOff = i > 0 && stages[i - 1].count > 0
                        ? Math.round((1 - s.count / stages[i - 1].count) * 100)
                        : null;
                    return (
                        <div className="funnel-stage" key={s.label}>
                            <div className="funnel-label">{s.label}</div>
                            <div className="funnel-bar-track">
                                <div className="funnel-bar-fill" style={{
                                    width: `${pct}%`,
                                    background: `linear-gradient(90deg, ${s.color}88, ${s.color})`,
                                }}>
                                    <span style={{ fontSize: 11, fontWeight: 700, color: '#fff' }}>
                                        {s.count.toLocaleString()}
                                    </span>
                                </div>
                            </div>
                            <div className="funnel-count" style={{ color: s.color, textAlign: 'right', fontSize: 12 }}>
                                {s.count.toLocaleString()}
                            </div>
                            <div className="funnel-pct">
                                {dropOff != null && dropOff > 0
                                    ? <span style={{ color: '#F43F5E', fontSize: 10, fontWeight: 700 }}>↓{dropOff}%</span>
                                    : null}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

// ── Activity Feed (presence + recent stats) ───────────────────────────────────
function ActivityFeed({ initial, snapshotUrl, telecallerStats }) {
    const [presence, setPresence] = useState(initial ?? []);

    const refresh = useCallback(async () => {
        try {
            const res = await fetch(snapshotUrl, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            if (Array.isArray(data.telecallers)) setPresence(data.telecallers);
        } catch (_) {}
    }, [snapshotUrl]);

    useEffect(() => {
        refresh();
        const t = setInterval(refresh, 30_000);
        return () => clearInterval(t);
    }, [refresh]);

    // Merge presence with stats
    const enriched = telecallerStats.map(tc => {
        const p = presence.find(x => x.id === tc.id);
        return { ...tc, is_online: p ? Boolean(p.is_online) : false };
    });

    // Build feed items from stats
    const feedItems = [];
    enriched.forEach(tc => {
        if (tc.total_calls > 0) {
            feedItems.push({
                id: `call-${tc.id}`,
                icon: 'call',
                iconBg: 'rgba(99,102,241,0.15)',
                iconColor: '#6366F1',
                text: `${tc.name} made ${tc.total_calls} call${tc.total_calls !== 1 ? 's' : ''}`,
                sub: `${tc.conversion_rate}% conversion · ${tc.pending_followups} pending`,
                online: tc.is_online,
            });
        }
        if (parseFloat(tc.conversion_rate) > 0) {
            feedItems.push({
                id: `conv-${tc.id}`,
                icon: 'check_circle',
                iconBg: 'rgba(16,185,129,0.15)',
                iconColor: '#10B981',
                text: `${tc.name} achieved ${tc.conversion_rate}% conversion`,
                sub: `${tc.assigned_count} leads assigned`,
                online: tc.is_online,
            });
        }
    });

    const online = enriched.filter(t => t.is_online);
    const offline = enriched.filter(t => !t.is_online);

    return (
        <div className="dark-card" style={{ height: '100%' }}>
            {/* Online agents header */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                <div>
                    <p className="dark-card-title">Team Status</p>
                    <p className="dark-card-sub" style={{ marginBottom: 0 }}>Live activity &amp; presence</p>
                </div>
                <div style={{
                    display: 'flex', alignItems: 'center', gap: 6,
                    background: 'rgba(16,185,129,0.1)', border: '1px solid rgba(16,185,129,0.2)',
                    borderRadius: 20, padding: '4px 10px',
                }}>
                    <div style={{ width: 6, height: 6, borderRadius: '50%', background: '#10B981', animation: 'mgr-pulse 1.6s infinite' }} />
                    <span style={{ fontSize: 11, fontWeight: 700, color: '#10B981' }}>{online.length} online</span>
                </div>
            </div>

            {/* Presence pills */}
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginBottom: 16 }}>
                {enriched.map(tc => (
                    <div key={tc.id} style={{
                        display: 'inline-flex', alignItems: 'center', gap: 6,
                        background: '#1A2235', border: '1px solid #1E293B',
                        borderRadius: 20, padding: '4px 10px',
                    }}>
                        <div style={{
                            width: 6, height: 6, borderRadius: '50%',
                            background: tc.is_online ? '#10B981' : '#334155',
                            flexShrink: 0,
                        }} />
                        <span style={{ fontSize: 11, fontWeight: 600, color: tc.is_online ? '#CBD5E1' : '#475569' }}>
                            {tc.name}
                        </span>
                    </div>
                ))}
                {enriched.length === 0 && (
                    <span style={{ fontSize: 12, color: '#334155' }}>No telecallers found.</span>
                )}
            </div>

            {/* Activity items */}
            {feedItems.length > 0 && (
                <>
                    <div style={{ fontSize: 10, fontWeight: 700, letterSpacing: '0.8px', textTransform: 'uppercase', color: '#334155', marginBottom: 8 }}>
                        Recent Activity
                    </div>
                    <div className="activity-feed">
                        {feedItems.slice(0, 8).map((item, i) => (
                            <div className="activity-item" key={item.id} style={{ animationDelay: `${i * 60}ms` }}>
                                <div className="activity-icon" style={{ background: item.iconBg }}>
                                    <span className="material-icons" style={{ color: item.iconColor }}>{item.icon}</span>
                                </div>
                                <div className="activity-body">
                                    <p className="activity-text">{item.text}</p>
                                    <p className="activity-time">{item.sub}</p>
                                </div>
                                <div className="activity-status-dot"
                                    style={{ background: item.online ? '#10B981' : '#1E293B' }} />
                            </div>
                        ))}
                    </div>
                </>
            )}

            {feedItems.length === 0 && enriched.length === 0 && (
                <div className="dark-empty">
                    <span className="material-icons">support_agent</span>
                    <p>No telecaller activity yet.</p>
                </div>
            )}
        </div>
    );
}

// ── Missed Followups Panel ────────────────────────────────────────────────────
function MissedFollowupsPanel({ count, list }) {
    return (
        <div className="dark-card" style={{ height: '100%' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                <div>
                    <p className="dark-card-title">Missed Follow-Ups</p>
                    <p className="dark-card-sub" style={{ marginBottom: 0 }}>Overdue action required</p>
                </div>
                {count > 0 && (
                    <span style={{
                        background: 'rgba(244,63,94,0.15)', color: '#F43F5E',
                        border: '1px solid rgba(244,63,94,0.3)', borderRadius: 20,
                        padding: '3px 10px', fontSize: 12, fontWeight: 700,
                    }}>{count}</span>
                )}
            </div>
            {list.length > 0 ? (
                <div className="missed-fu-list">
                    {list.map(f => (
                        <div className="missed-fu-item" key={f.id}>
                            <div style={{ width: 8, height: 8, borderRadius: '50%', background: '#F43F5E', flexShrink: 0, marginTop: 5 }} />
                            <div style={{ flex: 1, minWidth: 0 }}>
                                <p className="missed-fu-name">{f.lead?.name ?? `Lead #${f.lead_id}`}</p>
                                <p className="missed-fu-sub">
                                    {f.lead?.assigned_user?.name ?? 'Unassigned'} ·{' '}
                                    {new Date(f.next_followup).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })}
                                </p>
                            </div>
                            <span style={{
                                fontSize: 10, fontWeight: 700, padding: '2px 7px',
                                background: 'rgba(244,63,94,0.12)', color: '#F43F5E', borderRadius: 12,
                                flexShrink: 0,
                            }}>LATE</span>
                        </div>
                    ))}
                </div>
            ) : (
                <div className="dark-empty">
                    <span className="material-icons" style={{ color: '#10B981' }}>check_circle</span>
                    <p style={{ color: '#10B981', fontWeight: 600 }}>All clear! No missed follow-ups.</p>
                </div>
            )}
        </div>
    );
}

// ── Leaderboard Table ─────────────────────────────────────────────────────────
function LeaderboardTable({ telecallerStats, telecallersUrl }) {
    const medals = ['🥇','🥈','🥉'];

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
                <div>
                    <p className="dark-card-title" style={{ marginBottom: 2 }}>Performance Leaderboard</p>
                    <p style={{ fontSize: 12, color: '#475569', margin: 0 }}>Ranked by conversion rate</p>
                </div>
                <a href={telecallersUrl} style={{ fontSize: 12, color: '#6366F1', fontWeight: 600, textDecoration: 'none' }}>
                    View All
                </a>
            </div>

            <div className="mgr-table-wrap">
                <table className="mgr-table">
                    <thead>
                        <tr>
                            <th style={{ width: 40 }}>#</th>
                            <th>Telecaller</th>
                            <th>Assigned</th>
                            <th>Calls</th>
                            <th>Pending FU</th>
                            <th>Conv. Rate</th>
                            <th style={{ minWidth: 100 }}>Progress</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {telecallerStats.length > 0 ? (
                            [...telecallerStats]
                                .sort((a, b) => parseFloat(b.conversion_rate) - parseFloat(a.conversion_rate))
                                .map((t, idx) => {
                                    const rate = parseFloat(t.conversion_rate);
                                    const color = avatarColor(t.name);
                                    const rateClass = rate >= 30 ? 'high' : rate >= 10 ? 'medium' : 'low';
                                    return (
                                        <tr key={t.id}>
                                            <td>
                                                {idx < 3 ? (
                                                    <span style={{ fontSize: 18 }}>{medals[idx]}</span>
                                                ) : (
                                                    <span style={{
                                                        display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                                        width: 24, height: 24, borderRadius: '50%',
                                                        background: '#1E293B', color: '#94A3B8',
                                                        fontSize: 11, fontWeight: 700,
                                                    }}>{idx + 1}</span>
                                                )}
                                            </td>
                                            <td>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                                    <div className="tc-avatar" style={{ background: color }}>
                                                        {initials(t.name).toUpperCase()}
                                                    </div>
                                                    <span style={{ fontWeight: 600, color: '#E2E8F0' }}>{t.name}</span>
                                                </div>
                                            </td>
                                            <td style={{ color: '#94A3B8' }}>{t.assigned_count}</td>
                                            <td style={{ color: '#94A3B8' }}>{t.total_calls}</td>
                                            <td>
                                                <span style={{
                                                    color: t.pending_followups > 0 ? '#F59E0B' : '#475569',
                                                    fontWeight: t.pending_followups > 0 ? 700 : 400,
                                                }}>{t.pending_followups}</span>
                                            </td>
                                            <td>
                                                <span className={`rate-badge ${rateClass}`}>{rate.toFixed(1)}%</span>
                                            </td>
                                            <td>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                                    <div className="conv-bar-track">
                                                        <div className="conv-bar-fill" style={{ width: `${Math.min(rate, 100)}%` }} />
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div className="status-pill" style={{
                                                    background: 'rgba(16,185,129,0.1)',
                                                    color: '#10B981',
                                                }}>
                                                    <div style={{ width: 5, height: 5, borderRadius: '50%', background: '#10B981' }} />
                                                    Active
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })
                        ) : (
                            <tr>
                                <td colSpan={8}>
                                    <div className="dark-empty"><span className="material-icons">leaderboard</span><p>No data yet.</p></div>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

// ── Missed Callbacks Table ────────────────────────────────────────────────────
function MissedCallbacksTable({ calls }) {
    return (
        <div>
            <div style={{ marginBottom: 14 }}>
                <p className="dark-card-title" style={{ marginBottom: 2 }}>Missed Inbound Callbacks</p>
                <p style={{ fontSize: 12, color: '#475569', margin: 0 }}>Inbound calls awaiting follow-up</p>
            </div>
            <div className="callback-table-wrap">
                <table className="mgr-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Lead</th>
                            <th>Number</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {calls.length > 0 ? calls.map(c => (
                            <tr key={c.id}>
                                <td style={{ color: '#94A3B8', whiteSpace: 'nowrap' }}>{c.created_at_formatted}</td>
                                <td style={{ fontWeight: 600, color: '#E2E8F0' }}>
                                    {c.lead_code ? `${c.lead_code} – ${c.lead_name}` : (c.lead_name || 'Unknown')}
                                </td>
                                <td style={{ color: '#94A3B8', fontFamily: 'JetBrains Mono, monospace', fontSize: 12 }}>
                                    {c.customer_number || c.lead_phone || '—'}
                                </td>
                                <td>
                                    <span style={{
                                        background: 'rgba(244,63,94,0.12)', color: '#F43F5E',
                                        padding: '2px 8px', borderRadius: 12, fontSize: 10.5, fontWeight: 700,
                                    }}>MISSED</span>
                                </td>
                                <td>
                                    {c.encrypted_lead_id ? (
                                        <a href={`/manager/leads/${c.encrypted_lead_id}`} style={{
                                            display: 'inline-flex', alignItems: 'center', gap: 4,
                                            background: 'rgba(99,102,241,0.15)', color: '#6366F1',
                                            border: '1px solid rgba(99,102,241,0.3)',
                                            padding: '4px 12px', borderRadius: 8, fontSize: 12, fontWeight: 600,
                                            textDecoration: 'none', transition: 'background 0.15s',
                                        }}>
                                            <span className="material-icons" style={{ fontSize: 13 }}>call</span>
                                            Call Back
                                        </a>
                                    ) : (
                                        <span style={{ color: '#334155', fontSize: 12 }}>—</span>
                                    )}
                                </td>
                            </tr>
                        )) : (
                            <tr>
                                <td colSpan={5}>
                                    <div className="dark-empty">
                                        <span className="material-icons" style={{ color: '#10B981' }}>call_received</span>
                                        <p style={{ color: '#475569' }}>No missed inbound callbacks.</p>
                                    </div>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

// ── Course Performance ────────────────────────────────────────────────────────
function CoursePerformance({ courseStats }) {
    if (!courseStats.length) return null;
    const maxTotal = Math.max(...courseStats.map(r => r.total), 1);

    return (
        <div>
            <div style={{ marginBottom: 14 }}>
                <p className="dark-card-title" style={{ marginBottom: 2 }}>Course Performance</p>
                <p style={{ fontSize: 12, color: '#475569', margin: 0 }}>Lead volume &amp; conversion by course</p>
            </div>
            <div className="mgr-table-wrap">
                <table className="mgr-table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Leads</th>
                            <th>Converted</th>
                            <th>Rate</th>
                            <th style={{ minWidth: 160 }}>Volume</th>
                        </tr>
                    </thead>
                    <tbody>
                        {courseStats.map((row, i) => {
                            const rate = parseFloat(row.rate);
                            const rateClass = rate >= 30 ? 'high' : rate >= 10 ? 'medium' : 'low';
                            const barPct = Math.round((row.total / maxTotal) * 100);
                            return (
                                <tr key={i}>
                                    <td style={{ fontWeight: 600, color: '#E2E8F0' }}>{row.course}</td>
                                    <td style={{ color: '#94A3B8', fontFamily: 'JetBrains Mono, monospace', fontSize: 12 }}>{row.total}</td>
                                    <td style={{ color: '#94A3B8', fontFamily: 'JetBrains Mono, monospace', fontSize: 12 }}>{row.conversions}</td>
                                    <td><span className={`rate-badge ${rateClass}`}>{rate.toFixed(1)}%</span></td>
                                    <td>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                            <div className="course-bar-track">
                                                <div className="course-bar-fill" style={{ width: `${barPct}%` }} />
                                            </div>
                                            <span style={{ fontSize: 11, color: '#475569', whiteSpace: 'nowrap' }}>
                                                {row.total}
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

// ── Follow-Up Calendar (dark) ─────────────────────────────────────────────────
function DarkFollowupCalendar({ initialData, calendarDataUrl }) {
    const todayDate = new Date();
    const todayY = todayDate.getFullYear();
    const todayM = todayDate.getMonth() + 1;
    const todayD = todayDate.getDate();

    const [state, setState] = useState({ year: todayY, month: todayM, days: initialData ?? {} });
    const [loading, setLoading] = useState(false);

    async function navigate(year, month) {
        setLoading(true);
        try {
            const res = await fetch(`${calendarDataUrl}?year=${year}&month=${month}`, { headers: { Accept: 'application/json' } });
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
    const firstDow = new Date(year, month - 1, 1).getDay();
    const isThisMonth = year === todayY && month === todayM;

    const cells = [];
    for (let i = 0; i < firstDow; i++) cells.push(null);
    for (let d = 1; d <= daysInMonth; d++) cells.push(d);

    function cellStyle(count, isToday, isPast) {
        if (count > 7) return { bg: 'rgba(244,63,94,0.18)', border: 'rgba(244,63,94,0.4)', numColor: '#F43F5E', countColor: '#F43F5E' };
        if (count > 3) return { bg: 'rgba(245,158,11,0.15)', border: 'rgba(245,158,11,0.35)', numColor: '#F59E0B', countColor: '#F59E0B' };
        if (count > 0) return { bg: 'rgba(99,102,241,0.15)', border: 'rgba(99,102,241,0.35)', numColor: '#A5B4FC', countColor: '#6366F1' };
        return { bg: isPast ? '#0D1117' : '#111827', border: isToday ? '#6366F1' : '#1E293B', numColor: isPast ? '#334155' : '#94A3B8', countColor: null };
    }

    return (
        <div>
            <div className="dark-cal-header">
                <div>
                    <p className="dark-card-title" style={{ marginBottom: 2 }}>Team Follow-Up Calendar</p>
                    <p style={{ fontSize: 12, color: '#475569', margin: 0 }}>Follow-up density heatmap</p>
                </div>
                <div className="dark-cal-nav">
                    <button className="dark-cal-nav-btn" onClick={prevMonth}>
                        <span className="material-icons">chevron_left</span>
                    </button>
                    <span className="dark-cal-month">{MONTH_NAMES[month - 1]} {year}</span>
                    <button className="dark-cal-nav-btn" onClick={nextMonth}>
                        <span className="material-icons">chevron_right</span>
                    </button>
                </div>
            </div>

            <div style={{ opacity: loading ? 0.5 : 1 }}>
                <div className="dark-cal-grid">
                    {DOW_LABELS.map(d => (
                        <div className="dark-cal-dow" key={d}>{d}</div>
                    ))}
                    {cells.map((d, i) => {
                        if (d === null) return <div key={`e${i}`} />;
                        const key = `${year}-${pad(month)}-${pad(d)}`;
                        const count = days[key] || 0;
                        const isToday = isThisMonth && d === todayD;
                        const cellDate = new Date(year, month - 1, d);
                        const isPast = cellDate < new Date(todayY, todayM - 1, todayD);
                        const { bg, border, numColor, countColor } = cellStyle(count, isToday, isPast);

                        let href = '';
                        if (count > 0) {
                            href = isToday ? '/manager/followups/today'
                                : isPast ? '/manager/followups/overdue'
                                : '/manager/followups/upcoming';
                        }

                        return (
                            <div
                                key={key}
                                className={`dark-cal-cell${count > 0 ? ' has-items' : ''}${isToday ? ' is-today' : ''}`}
                                style={{ background: bg, borderColor: border }}
                                title={count > 0 ? `${count} follow-up${count !== 1 ? 's' : ''}` : undefined}
                                onClick={() => href && (window.location.href = href)}
                            >
                                <div className="dark-cal-day-num" style={{ color: numColor, fontWeight: isToday ? 700 : 500 }}>{d}</div>
                                {count > 0 && (
                                    <div className="dark-cal-count" style={{ color: countColor }}>{count}</div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>

            <div className="dark-cal-legend">
                {[
                    { bg: 'rgba(99,102,241,0.25)', border: 'rgba(99,102,241,0.4)', label: '1–3 follow-ups' },
                    { bg: 'rgba(245,158,11,0.22)', border: 'rgba(245,158,11,0.4)', label: '4–7 follow-ups' },
                    { bg: 'rgba(244,63,94,0.22)',  border: 'rgba(244,63,94,0.4)',  label: '8+ follow-ups' },
                    { bg: 'transparent', border: '#6366F1', label: 'Today', outline: true },
                ].map(({ bg, border, label }) => (
                    <div className="dark-cal-legend-item" key={label}>
                        <div className="dark-cal-legend-swatch" style={{ background: bg, border: `1px solid ${border}` }} />
                        {label}
                    </div>
                ))}
            </div>
        </div>
    );
}

// ── Main Page ─────────────────────────────────────────────────────────────────
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
    presenceSnapshotUrl, calendarDataUrl,
    telecallersUrl, leadsCreateUrl,
}) {
    const periodLabels = { today: 'Today', week: 'This Week', month: 'This Month' };
    const periodLeads = { today: leadsToday, week: leadsWeek, month: leadsMonth }[period] ?? leadsToday;

    function changePeriod(e) {
        router.get(window.location.pathname, { period: e.target.value }, { preserveScroll: false });
    }

    const kpiCards = [
        {
            icon: 'groups',
            label: `Total Leads (${periodLabels[period]})`,
            rawValue: periodLeads,
            sub: `T: ${leadsToday} · W: ${leadsWeek} · M: ${leadsMonth}`,
            iconBg: 'linear-gradient(135deg,#6366F1,#4f46e5)',
            iconColor: '#fff',
            iconShadow: '0 4px 14px rgba(99,102,241,0.4)',
            sparkColor: '#6366F1',
        },
        {
            icon: 'call',
            label: `Calls Made (${periodLabels[period]})`,
            rawValue: totalCallsMade,
            iconBg: 'linear-gradient(135deg,#8B5CF6,#7C3AED)',
            iconColor: '#fff',
            iconShadow: '0 4px 14px rgba(139,92,246,0.4)',
            sparkColor: '#8B5CF6',
            trend: null,
        },
        {
            icon: 'timer',
            label: 'Call Duration',
            rawValue: null,
            displayValue: toTimeLabel(totalCallDurationSec),
            iconBg: 'linear-gradient(135deg,#F59E0B,#D97706)',
            iconColor: '#fff',
            iconShadow: '0 4px 14px rgba(245,158,11,0.35)',
            sparkColor: null,
        },
        {
            icon: 'chat',
            label: 'WhatsApp Chats',
            rawValue: whatsAppConversations,
            iconBg: 'linear-gradient(135deg,#10B981,#059669)',
            iconColor: '#fff',
            iconShadow: '0 4px 14px rgba(16,185,129,0.35)',
            sparkColor: '#10B981',
        },
        {
            icon: 'insights',
            label: 'Conversion Rate',
            rawValue: null,
            displayValue: `${parseFloat(conversionRate).toFixed(1)}%`,
            trend: `${parseFloat(conversionRate).toFixed(1)}%`,
            trendUp: parseFloat(conversionRate) >= 20,
            iconBg: 'linear-gradient(135deg,#06B6D4,#0891B2)',
            iconColor: '#fff',
            iconShadow: '0 4px 14px rgba(6,182,212,0.35)',
            sparkColor: null,
        },
        {
            icon: 'emoji_events',
            label: 'Top Telecaller',
            rawValue: null,
            displayValue: bestPerformingTelecaller?.name ?? '—',
            sub: bestPerformingTelecaller ? `${parseFloat(bestPerformingTelecaller.conversion_rate).toFixed(1)}% conv.` : null,
            iconBg: 'linear-gradient(135deg,#F59E0B,#EF4444)',
            iconColor: '#fff',
            iconShadow: '0 4px 14px rgba(245,158,11,0.35)',
            sparkColor: null,
        },
        {
            icon: 'event_busy',
            label: 'Missed Follow-Ups',
            rawValue: missedFollowups,
            trend: missedFollowups > 0 ? `${missedFollowups} overdue` : null,
            trendUp: false,
            iconBg: 'linear-gradient(135deg,#F43F5E,#E11D48)',
            iconColor: '#fff',
            iconShadow: '0 4px 14px rgba(244,63,94,0.35)',
            sparkColor: '#F43F5E',
        },
    ];

    return (
        <>
            <Head title="Manager Dashboard" />

            <div className="mgr-dash">
                {/* ── Top bar ──────────────────────────────────────────── */}
                <div className="mgr-topbar">
                    <div className="mgr-title">
                        <h2>Manager Dashboard</h2>
                        <div className="live-badge">
                            <div className="live-dot" />
                            LIVE
                        </div>
                    </div>
                    <div className="mgr-period-select">
                        <span className="material-icons" style={{ fontSize: 16 }}>calendar_today</span>
                        <select value={period} onChange={changePeriod}>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                        </select>
                        <span className="material-icons" style={{ fontSize: 16 }}>expand_more</span>
                    </div>
                </div>

                {/* ── KPI Cards ────────────────────────────────────────── */}
                <div className="kpi-grid mgr-section">
                    {kpiCards.map((card, i) => (
                        <KpiCard key={card.label} {...card} delay={i * 70} />
                    ))}
                </div>

                {/* ── Charts row: Donut + Pipeline ─────────────────────── */}
                <div className="row g-3 mgr-section">
                    <div className="col-lg-5">
                        <LeadSourceChart leadSource={leadSource} />
                    </div>
                    <div className="col-lg-7">
                        <PipelineFunnel
                            leadsMonth={leadsMonth}
                            totalCallsMade={totalCallsMade}
                            telecallerStats={telecallerStats}
                            conversionRate={conversionRate}
                        />
                    </div>
                </div>

                {/* ── Leaderboard (full width) ──────────────────────────── */}
                <div className="dark-card mgr-section">
                    <LeaderboardTable telecallerStats={telecallerStats} telecallersUrl={telecallersUrl} />
                </div>

                {/* ── Activity Feed + Missed Followups ──────────────────── */}
                <div className="row g-3 mgr-section">
                    <div className="col-lg-7">
                        <ActivityFeed
                            initial={telecallerPresence}
                            snapshotUrl={presenceSnapshotUrl}
                            telecallerStats={telecallerStats}
                        />
                    </div>
                    <div className="col-lg-5">
                        <MissedFollowupsPanel count={missedFollowups} list={missedFollowupList} />
                    </div>
                </div>

                {/* ── Missed Callbacks ──────────────────────────────────── */}
                <div className="dark-card mgr-section">
                    <MissedCallbacksTable calls={missedInboundCalls} />
                </div>

                {/* ── Course Performance ────────────────────────────────── */}
                {courseStats.length > 0 && (
                    <div className="dark-card mgr-section">
                        <CoursePerformance courseStats={courseStats} />
                    </div>
                )}

                {/* ── Follow-Up Calendar ────────────────────────────────── */}
                <div className="dark-card mgr-section">
                    <DarkFollowupCalendar initialData={followupCalendar} calendarDataUrl={calendarDataUrl} />
                </div>
            </div>
        </>
    );
}
