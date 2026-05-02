import { Head, router } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';

// ── Helpers ────────────────────────────────────────────────────────────────────
function toTimeLabel(sec) {
    const s = Number(sec || 0);
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const ss = s % 60;
    return [h, m, ss].map(v => String(v).padStart(2, '0')).join(':');
}

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

function useCountUp(target, duration = 1200) {
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

// ── KPI Card ──────────────────────────────────────────────────────────────────
function KpiCard({ icon, label, rawValue, displayValue, sub, iconBg, iconColor, iconShadow, delay }) {
    const count   = useCountUp(rawValue ?? 0);
    const display = rawValue != null ? count.toLocaleString() : (displayValue ?? '—');

    return (
        <div className="kpi-card" style={{ animationDelay: `${delay}ms` }}>
            <div className="kpi-card-top">
                <div className="kpi-icon" style={{ background: iconBg, boxShadow: iconShadow }}>
                    <span className="material-icons" style={{ color: iconColor }}>{icon}</span>
                </div>
            </div>
            <div className="kpi-value">{display}</div>
            <div className="kpi-label">{label}</div>
            {sub && <div style={{ fontSize: 11, color: '#64748b', marginTop: 2 }}>{sub}</div>}
        </div>
    );
}

// ── People & Lead Overview ─────────────────────────────────────────────────────
function PeopleLeadOverview({
    totalManagers, totalTelecallers,
    totalLeadsAll, assignedLeads, unassignedLeads, contactedLeads, convertedLeads,
}) {
    const tiles = [
        { label: 'Total Managers',    value: totalManagers,    icon: 'manage_accounts', color: '#6366F1', bg: 'rgba(99,102,241,0.12)',  border: 'rgba(99,102,241,0.25)' },
        { label: 'Total Telecallers', value: totalTelecallers, icon: 'support_agent',   color: '#8B5CF6', bg: 'rgba(139,92,246,0.12)',  border: 'rgba(139,92,246,0.25)' },
        { label: 'Total Leads',       value: totalLeadsAll,    icon: 'groups',          color: '#06B6D4', bg: 'rgba(6,182,212,0.12)',   border: 'rgba(6,182,212,0.25)'  },
        { label: 'Assigned',          value: assignedLeads,    icon: 'assignment_ind',  color: '#10B981', bg: 'rgba(16,185,129,0.12)', border: 'rgba(16,185,129,0.25)' },
        { label: 'Unassigned',        value: unassignedLeads,  icon: 'assignment_late', color: '#F59E0B', bg: 'rgba(245,158,11,0.12)', border: 'rgba(245,158,11,0.25)' },
        { label: 'Contacted',         value: contactedLeads,   icon: 'call_made',       color: '#EC4899', bg: 'rgba(236,72,153,0.12)', border: 'rgba(236,72,153,0.25)' },
        { label: 'Converted',         value: convertedLeads,   icon: 'verified',        color: '#10B981', bg: 'rgba(16,185,129,0.12)', border: 'rgba(16,185,129,0.25)' },
    ];

    const total = totalLeadsAll || 1;
    const assignedPct   = Math.round((assignedLeads / total) * 100);
    const convertedPct  = Math.round((convertedLeads / total) * 100);
    const contactedPct  = Math.round((contactedLeads / total) * 100);

    return (
        <div className="dark-card mgr-section">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 18 }}>
                <div>
                    <p className="dark-card-title" style={{ marginBottom: 2 }}>People & Lead Overview</p>
                    <p style={{ fontSize: 12, color: '#475569', margin: 0 }}>All-time org-wide snapshot</p>
                </div>
            </div>

            {/* Tiles */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(140px, 1fr))', gap: 12, marginBottom: 24 }}>
                {tiles.map(t => (
                    <Tile key={t.label} {...t} />
                ))}
            </div>

            {/* Lead funnel bars */}
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                <FunnelBar label="Assigned" value={assignedLeads} pct={assignedPct} color="#10B981" />
                <FunnelBar label="Contacted (have calls)" value={contactedLeads} pct={contactedPct} color="#EC4899" />
                <FunnelBar label="Converted" value={convertedLeads} pct={convertedPct} color="#6366F1" />
            </div>
        </div>
    );
}

function Tile({ label, value, icon, color, bg, border }) {
    const count = useCountUp(value);
    return (
        <div style={{
            background: bg, border: `1px solid ${border}`,
            borderRadius: 12, padding: '14px 14px 12px',
            display: 'flex', flexDirection: 'column', gap: 8,
        }}>
            <div style={{ width: 32, height: 32, borderRadius: 8, background: `${color}22`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <span className="material-icons" style={{ fontSize: 17, color }}>{icon}</span>
            </div>
            <div style={{ fontSize: 22, fontWeight: 800, color: '#F1F5F9', lineHeight: 1 }}>{count.toLocaleString()}</div>
            <div style={{ fontSize: 11, fontWeight: 600, color: '#94A3B8' }}>{label}</div>
        </div>
    );
}

function FunnelBar({ label, value, pct, color }) {
    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 4 }}>
                <span style={{ fontSize: 12, color: '#CBD5E1', fontWeight: 600 }}>{label}</span>
                <span style={{ fontSize: 12, color, fontWeight: 700 }}>{value.toLocaleString()} ({pct}%)</span>
            </div>
            <div style={{ height: 7, background: '#1E293B', borderRadius: 4 }}>
                <div style={{ height: 7, width: `${pct}%`, background: color, borderRadius: 4, transition: 'width 1s ease' }} />
            </div>
        </div>
    );
}

// ── Call Performance ──────────────────────────────────────────────────────────
function CallPerformance({ totalCallsMade, answeredCalls, avgCallDurationSec, callStatusBreakdown, callOutcomes, reportsUrl }) {
    const missedCalls = totalCallsMade - answeredCalls;
    const answerRate  = totalCallsMade > 0 ? Math.round((answeredCalls / totalCallsMade) * 100) : 0;

    const OUTCOME_COLORS = {
        interested: '#10B981', converted: '#6366F1', callback: '#F59E0B',
        'not-interested': '#F43F5E', 'no-answer': '#94A3B8', busy: '#F97316',
        failed: '#EF4444',
    };
    const STATUS_COLORS = {
        answered: '#10B981', 'no-answer': '#F43F5E', busy: '#F59E0B',
        failed: '#EF4444', completed: '#6366F1', initiated: '#06B6D4',
    };

    const outcomesTotal = callOutcomes.reduce((a, r) => a + r.total, 0);
    const statusTotal   = callStatusBreakdown.reduce((a, r) => a + r.total, 0);

    return (
        <div className="dark-card mgr-section">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 18 }}>
                <div>
                    <p className="dark-card-title" style={{ marginBottom: 2 }}>Call Performance</p>
                    <p style={{ fontSize: 12, color: '#475569', margin: 0 }}>Selected period call analytics</p>
                </div>
                <a href={reportsUrl.callEfficiency} style={{ fontSize: 12, color: '#6366F1', fontWeight: 600, textDecoration: 'none' }}>
                    Full Report →
                </a>
            </div>

            {/* Summary tiles */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(130px, 1fr))', gap: 12, marginBottom: 24 }}>
                <CallTile icon="call" label="Total Calls" value={totalCallsMade} color="#6366F1" />
                <CallTile icon="call_received" label="Answered" value={answeredCalls} color="#10B981" />
                <CallTile icon="call_missed" label="Missed" value={missedCalls} color="#F43F5E" />
                <CallTile icon="percent" label="Answer Rate" value={null} display={`${answerRate}%`} color="#06B6D4" />
                <CallTile icon="timer" label="Avg Duration" value={null} display={toTimeLabel(avgCallDurationSec)} color="#F59E0B" />
            </div>

            <div className="row g-3">
                {/* Call status breakdown */}
                <div className="col-md-6">
                    <p style={{ fontSize: 13, fontWeight: 700, color: '#CBD5E1', marginBottom: 12 }}>By Call Status</p>
                    {callStatusBreakdown.length > 0 ? (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                            {callStatusBreakdown.map(r => {
                                const color = STATUS_COLORS[r.status] ?? '#6366F1';
                                const pct = statusTotal > 0 ? Math.round((r.total / statusTotal) * 100) : 0;
                                return (
                                    <div key={r.status}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 3 }}>
                                            <span style={{ fontSize: 12, color: '#94A3B8', textTransform: 'capitalize' }}>{r.status}</span>
                                            <span style={{ fontSize: 12, color, fontWeight: 700 }}>{r.total} ({pct}%)</span>
                                        </div>
                                        <div style={{ height: 6, background: '#1E293B', borderRadius: 3 }}>
                                            <div style={{ height: 6, width: `${pct}%`, background: color, borderRadius: 3, transition: 'width 0.8s ease' }} />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="dark-empty"><span className="material-icons">call</span><p>No call data yet</p></div>
                    )}
                </div>

                {/* Call outcomes */}
                <div className="col-md-6">
                    <p style={{ fontSize: 13, fontWeight: 700, color: '#CBD5E1', marginBottom: 12 }}>By Outcome</p>
                    {callOutcomes.length > 0 ? (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                            {callOutcomes.map(r => {
                                const color = OUTCOME_COLORS[r.outcome] ?? '#8B5CF6';
                                const pct = outcomesTotal > 0 ? Math.round((r.total / outcomesTotal) * 100) : 0;
                                return (
                                    <div key={r.outcome}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 3 }}>
                                            <span style={{ fontSize: 12, color: '#94A3B8', textTransform: 'capitalize' }}>{r.outcome}</span>
                                            <span style={{ fontSize: 12, color, fontWeight: 700 }}>{r.total} ({pct}%)</span>
                                        </div>
                                        <div style={{ height: 6, background: '#1E293B', borderRadius: 3 }}>
                                            <div style={{ height: 6, width: `${pct}%`, background: color, borderRadius: 3, transition: 'width 0.8s ease' }} />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="dark-empty"><span className="material-icons">summarize</span><p>No outcome data yet</p></div>
                    )}
                </div>
            </div>
        </div>
    );
}

function CallTile({ icon, label, value, display, color }) {
    const count = useCountUp(value ?? 0);
    const shown = value != null ? count.toLocaleString() : display;
    return (
        <div style={{
            background: `${color}0f`, border: `1px solid ${color}30`,
            borderRadius: 10, padding: '12px 14px',
            display: 'flex', flexDirection: 'column', gap: 6,
        }}>
            <span className="material-icons" style={{ fontSize: 18, color }}>{icon}</span>
            <div style={{ fontSize: 20, fontWeight: 800, color: '#F1F5F9' }}>{shown}</div>
            <div style={{ fontSize: 11, color: '#64748b', fontWeight: 600 }}>{label}</div>
        </div>
    );
}

// ── Lead Source Donut ─────────────────────────────────────────────────────────
function LeadSourceChart({ leadSource }) {
    const containerRef = useRef(null);
    const chartRef     = useRef(null);

    useApexCharts((ApexCharts) => {
        if (!containerRef.current) return;
        if (chartRef.current) { chartRef.current.destroy(); chartRef.current = null; }
        const labels = leadSource.map(r => r.source || 'Unknown');
        const values = leadSource.map(r => Number(r.total));
        const total  = values.reduce((a, b) => a + b, 0);
        if (!total) return;

        chartRef.current = new ApexCharts(containerRef.current, {
            chart:  { type: 'donut', height: 260, background: 'transparent', fontFamily: 'Plus Jakarta Sans, sans-serif' },
            series: values,
            labels,
            colors: ['#6366F1','#10B981','#F59E0B','#F43F5E','#8B5CF6','#06B6D4','#EC4899','#14B8A6'],
            theme:  { mode: 'dark' },
            plotOptions: { pie: { donut: { size: '65%', labels: {
                show: true,
                total: { show: true, label: 'Total', color: '#94A3B8', fontSize: '13px', fontWeight: 600, formatter: () => String(total) },
                value: { color: '#F8FAFC', fontSize: '22px', fontWeight: 800 },
                name:  { color: '#94A3B8', fontSize: '12px' },
            } } } },
            stroke:     { width: 2, colors: ['#111827'] },
            legend:     { position: 'bottom', labels: { colors: '#94A3B8' }, markers: { radius: 3, width: 10, height: 10 }, itemMargin: { horizontal: 8, vertical: 4 }, fontSize: '12px' },
            tooltip:    { theme: 'dark', y: { formatter: (v) => `${v} leads (${total ? Math.round(v / total * 100) : 0}%)` } },
            dataLabels: { enabled: false },
        });
        chartRef.current.render();
    });

    useEffect(() => () => { if (chartRef.current) { chartRef.current.destroy(); chartRef.current = null; } }, []);
    const total = leadSource.reduce((a, r) => a + Number(r.total), 0);

    return (
        <div className="dark-card" style={{ marginBottom: 0, height: '100%' }}>
            <p className="dark-card-title">Lead Source Overview</p>
            <p className="dark-card-sub">{total} total leads by source</p>
            {total > 0
                ? <div ref={containerRef} />
                : <div className="dark-empty"><span className="material-icons">pie_chart</span><p>No source data yet</p></div>
            }
        </div>
    );
}

// ── Status Breakdown ──────────────────────────────────────────────────────────
function StatusBreakdown({ statusBreakdown }) {
    const total = statusBreakdown.reduce((a, r) => a + r.total, 0);
    const COLOR_MAP = {
        converted: '#10B981', new: '#6366F1', interested: '#8B5CF6',
        'not-interested': '#F43F5E', 'follow-up': '#F59E0B', lost: '#94A3B8',
    };

    return (
        <div className="dark-card" style={{ marginBottom: 0, height: '100%' }}>
            <p className="dark-card-title">Lead Status Breakdown</p>
            <p className="dark-card-sub">Org-wide pipeline snapshot</p>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10, marginTop: 12 }}>
                {statusBreakdown.map(r => {
                    const color = COLOR_MAP[r.status] ?? '#6366F1';
                    const pct   = total > 0 ? Math.round((r.total / total) * 100) : 0;
                    return (
                        <div key={r.status}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 4 }}>
                                <span style={{ fontSize: 12, fontWeight: 600, color: '#CBD5E1', textTransform: 'capitalize' }}>
                                    {r.status}
                                </span>
                                <span style={{ fontSize: 12, color, fontWeight: 700 }}>{r.total} ({pct}%)</span>
                            </div>
                            <div style={{ height: 6, background: '#1E293B', borderRadius: 3 }}>
                                <div style={{ height: 6, width: `${pct}%`, background: color, borderRadius: 3, transition: 'width 0.8s ease' }} />
                            </div>
                        </div>
                    );
                })}
                {statusBreakdown.length === 0 && (
                    <div className="dark-empty"><span className="material-icons">donut_large</span><p>No data yet</p></div>
                )}
            </div>
        </div>
    );
}

// ── Manager Performance Table ─────────────────────────────────────────────────
function ManagerTable({ managerStats, reportsUrl }) {
    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
                <div>
                    <p className="dark-card-title" style={{ marginBottom: 2 }}>Manager Performance</p>
                    <p style={{ fontSize: 12, color: '#475569', margin: 0 }}>Lead assignment & conversion by manager</p>
                </div>
                <a href={reportsUrl.managerPerformance}
                    style={{ fontSize: 12, color: '#6366F1', fontWeight: 600, textDecoration: 'none' }}>
                    Full Report →
                </a>
            </div>
            <div className="mgr-table-wrap">
                <table className="mgr-table">
                    <thead>
                        <tr>
                            <th>Manager</th>
                            <th>Total Leads</th>
                            <th>Assigned</th>
                            <th>Unassigned</th>
                            <th>Converted</th>
                            <th>Conv. Rate</th>
                            <th style={{ minWidth: 110 }}>Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        {managerStats.length > 0 ? managerStats.map((m) => {
                            const rate      = parseFloat(m.conversion_rate);
                            const rateClass = rate >= 30 ? 'high' : rate >= 10 ? 'medium' : 'low';
                            const color     = avatarColor(m.name);
                            return (
                                <tr key={m.id}>
                                    <td>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                            <div className="tc-avatar" style={{ background: color }}>
                                                {initials(m.name).toUpperCase()}
                                            </div>
                                            <span style={{ fontWeight: 600, color: '#E2E8F0' }}>{m.name}</span>
                                        </div>
                                    </td>
                                    <td style={{ color: '#94A3B8' }}>{m.total_leads}</td>
                                    <td style={{ color: '#10B981', fontWeight: 600 }}>{m.assigned_leads}</td>
                                    <td style={{ color: '#F59E0B', fontWeight: 600 }}>{m.unassigned_leads}</td>
                                    <td style={{ color: '#6366F1', fontWeight: 600 }}>{m.converted_leads}</td>
                                    <td><span className={`rate-badge ${rateClass}`}>{rate.toFixed(1)}%</span></td>
                                    <td>
                                        <div className="conv-bar-track">
                                            <div className="conv-bar-fill" style={{ width: `${Math.min(rate, 100)}%` }} />
                                        </div>
                                    </td>
                                </tr>
                            );
                        }) : (
                            <tr><td colSpan={7}>
                                <div className="dark-empty"><span className="material-icons">manage_accounts</span><p>No managers found.</p></div>
                            </td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

// ── Telecaller Leaderboard ────────────────────────────────────────────────────
function TelecallerLeaderboard({ telecallerStats, reportsUrl }) {
    const medals = ['🥇','🥈','🥉'];
    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
                <div>
                    <p className="dark-card-title" style={{ marginBottom: 2 }}>Telecaller Performance</p>
                    <p style={{ fontSize: 12, color: '#475569', margin: 0 }}>Ranked by total calls made</p>
                </div>
                <a href={reportsUrl.telecallerPerformance}
                    style={{ fontSize: 12, color: '#6366F1', fontWeight: 600, textDecoration: 'none' }}>
                    Full Report →
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
                            <th>Talk Time</th>
                            <th>Converted</th>
                            <th>Conv. Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        {telecallerStats.length > 0 ? telecallerStats.map((t, idx) => {
                            const rate      = parseFloat(t.conversion_rate);
                            const rateClass = rate >= 30 ? 'high' : rate >= 10 ? 'medium' : 'low';
                            const color     = avatarColor(t.name);
                            return (
                                <tr key={t.id}>
                                    <td>
                                        {idx < 3
                                            ? <span style={{ fontSize: 18 }}>{medals[idx]}</span>
                                            : <span style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: 24, height: 24, borderRadius: '50%', background: '#1E293B', color: '#94A3B8', fontSize: 11, fontWeight: 700 }}>{idx + 1}</span>
                                        }
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
                                    <td style={{ color: '#06B6D4', fontSize: 12, fontWeight: 600, fontVariantNumeric: 'tabular-nums' }}>
                                        {toTimeLabel(t.talk_time_sec)}
                                    </td>
                                    <td style={{ color: '#10B981', fontWeight: 600 }}>{t.converted_count}</td>
                                    <td><span className={`rate-badge ${rateClass}`}>{rate.toFixed(1)}%</span></td>
                                </tr>
                            );
                        }) : (
                            <tr><td colSpan={7}>
                                <div className="dark-empty"><span className="material-icons">leaderboard</span><p>No data yet.</p></div>
                            </td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

// ── Course Performance ────────────────────────────────────────────────────────
function CoursePerformance({ courseStats, reportsUrl }) {
    if (!courseStats.length) return null;
    const maxTotal = Math.max(...courseStats.map(r => r.total), 1);
    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
                <div>
                    <p className="dark-card-title" style={{ marginBottom: 2 }}>Course Performance</p>
                    <p style={{ fontSize: 12, color: '#475569', margin: 0 }}>Lead volume & conversion by course</p>
                </div>
                <a href={reportsUrl.conversion}
                    style={{ fontSize: 12, color: '#6366F1', fontWeight: 600, textDecoration: 'none' }}>
                    Conversion Report →
                </a>
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
                            const rate      = parseFloat(row.rate);
                            const rateClass = rate >= 30 ? 'high' : rate >= 10 ? 'medium' : 'low';
                            const barPct    = Math.round((row.total / maxTotal) * 100);
                            return (
                                <tr key={i}>
                                    <td style={{ fontWeight: 600, color: '#E2E8F0' }}>{row.course}</td>
                                    <td style={{ color: '#94A3B8', fontSize: 12 }}>{row.total}</td>
                                    <td style={{ color: '#94A3B8', fontSize: 12 }}>{row.conversions}</td>
                                    <td><span className={`rate-badge ${rateClass}`}>{rate.toFixed(1)}%</span></td>
                                    <td>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                            <div className="course-bar-track">
                                                <div className="course-bar-fill" style={{ width: `${barPct}%` }} />
                                            </div>
                                            <span style={{ fontSize: 11, color: '#475569' }}>{row.total}</span>
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

// ── Quick Reports Grid ────────────────────────────────────────────────────────
function QuickReports({ reportsUrl }) {
    const reports = [
        { key: 'telecallerPerformance', icon: 'support_agent',   label: 'Telecaller Performance', color: '#6366F1' },
        { key: 'managerPerformance',    icon: 'manage_accounts', label: 'Manager Performance',    color: '#8B5CF6' },
        { key: 'conversion',            icon: 'trending_up',     label: 'Conversion Report',      color: '#10B981' },
        { key: 'leadSource',            icon: 'track_changes',   label: 'Lead Source Report',     color: '#06B6D4' },
        { key: 'period',                icon: 'date_range',      label: 'Period Report',          color: '#F59E0B' },
        { key: 'callEfficiency',        icon: 'call_made',       label: 'Call Efficiency',        color: '#F43F5E' },
        { key: 'responseTime',          icon: 'timer',           label: 'Response Time',          color: '#EC4899' },
    ];

    return (
        <div>
            <p className="dark-card-title" style={{ marginBottom: 4 }}>Quick Reports</p>
            <p style={{ fontSize: 12, color: '#475569', marginBottom: 16 }}>Jump to any detailed report</p>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(160px, 1fr))', gap: 10 }}>
                {reports.map(r => (
                    <a key={r.key} href={reportsUrl[r.key]} style={{ textDecoration: 'none' }}>
                        <div style={{
                            background: '#111827', border: `1px solid ${r.color}33`,
                            borderRadius: 10, padding: '14px 12px',
                            display: 'flex', flexDirection: 'column', alignItems: 'flex-start', gap: 8,
                            transition: 'background 0.15s, border-color 0.15s', cursor: 'pointer',
                        }}
                            onMouseEnter={e => e.currentTarget.style.background = '#1E293B'}
                            onMouseLeave={e => e.currentTarget.style.background = '#111827'}
                        >
                            <div style={{ width: 32, height: 32, borderRadius: 8, background: `${r.color}20`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                <span className="material-icons" style={{ fontSize: 18, color: r.color }}>{r.icon}</span>
                            </div>
                            <span style={{ fontSize: 12, fontWeight: 600, color: '#CBD5E1', lineHeight: 1.3 }}>{r.label}</span>
                            <span style={{ fontSize: 11, color: r.color, display: 'flex', alignItems: 'center', gap: 3 }}>
                                View <span className="material-icons" style={{ fontSize: 13 }}>arrow_forward</span>
                            </span>
                        </div>
                    </a>
                ))}
            </div>
        </div>
    );
}

// ── Main Page ─────────────────────────────────────────────────────────────────
export default function Dashboard({
    period,
    totalManagers, totalTelecallers,
    leadsToday, leadsWeek, leadsMonth,
    totalLeadsAll, assignedLeads, unassignedLeads, contactedLeads, convertedLeads,
    totalCallsMade, totalCallDurationSec, answeredCalls, avgCallDurationSec,
    callStatusBreakdown, callOutcomes,
    conversionRate, missedFollowups,
    leadSource, managerStats, telecallerStats,
    courseStats, statusBreakdown, reportsUrl,
}) {
    const periodLabels = { today: 'Today', week: 'This Week', month: 'This Month' };
    const periodLeads  = { today: leadsToday, week: leadsWeek, month: leadsMonth }[period] ?? leadsToday;

    function changePeriod(e) {
        router.get(window.location.pathname, { period: e.target.value }, { preserveScroll: false });
    }

    const kpiCards = [
        {
            icon: 'groups', label: `New Leads (${periodLabels[period]})`,
            rawValue: periodLeads,
            sub: `T: ${leadsToday} · W: ${leadsWeek} · M: ${leadsMonth}`,
            iconBg: 'linear-gradient(135deg,#6366F1,#4f46e5)', iconColor: '#fff',
            iconShadow: '0 4px 14px rgba(99,102,241,0.4)',
        },
        {
            icon: 'call', label: `Calls Made (${periodLabels[period]})`,
            rawValue: totalCallsMade,
            iconBg: 'linear-gradient(135deg,#8B5CF6,#7C3AED)', iconColor: '#fff',
            iconShadow: '0 4px 14px rgba(139,92,246,0.4)',
        },
        {
            icon: 'timer', label: 'Total Call Duration',
            rawValue: null, displayValue: toTimeLabel(totalCallDurationSec),
            iconBg: 'linear-gradient(135deg,#F59E0B,#D97706)', iconColor: '#fff',
            iconShadow: '0 4px 14px rgba(245,158,11,0.35)',
        },
        {
            icon: 'insights', label: 'Conversion Rate',
            rawValue: null, displayValue: `${parseFloat(conversionRate).toFixed(1)}%`,
            sub: 'Org-wide for selected period',
            iconBg: 'linear-gradient(135deg,#06B6D4,#0891B2)', iconColor: '#fff',
            iconShadow: '0 4px 14px rgba(6,182,212,0.35)',
        },
        {
            icon: 'manage_accounts', label: 'Active Managers',
            rawValue: totalManagers,
            iconBg: 'linear-gradient(135deg,#10B981,#059669)', iconColor: '#fff',
            iconShadow: '0 4px 14px rgba(16,185,129,0.35)',
        },
        {
            icon: 'support_agent', label: 'Active Telecallers',
            rawValue: totalTelecallers,
            iconBg: 'linear-gradient(135deg,#8B5CF6,#6D28D9)', iconColor: '#fff',
            iconShadow: '0 4px 14px rgba(139,92,246,0.35)',
        },
        {
            icon: 'event_busy', label: 'Missed Follow-Ups',
            rawValue: missedFollowups,
            iconBg: 'linear-gradient(135deg,#F43F5E,#E11D48)', iconColor: '#fff',
            iconShadow: '0 4px 14px rgba(244,63,94,0.35)',
        },
    ];

    return (
        <>
            <Head title="Report Viewer Dashboard" />

            <div className="mgr-dash">
                {/* ── Top bar ──────────────────────────────────────────── */}
                <div className="mgr-topbar">
                    <div className="mgr-title">
                        <h2>Analytics Overview</h2>
                        <div style={{
                            display: 'inline-flex', alignItems: 'center', gap: 6,
                            background: 'rgba(99,102,241,0.12)', border: '1px solid rgba(99,102,241,0.3)',
                            borderRadius: 20, padding: '4px 12px', marginLeft: 10,
                        }}>
                            <span className="material-icons" style={{ fontSize: 13, color: '#6366F1' }}>lock</span>
                            <span style={{ fontSize: 11, fontWeight: 700, color: '#6366F1' }}>READ ONLY</span>
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

                {/* ── People & Lead Overview ───────────────────────────── */}
                <PeopleLeadOverview
                    totalManagers={totalManagers}
                    totalTelecallers={totalTelecallers}
                    totalLeadsAll={totalLeadsAll}
                    assignedLeads={assignedLeads}
                    unassignedLeads={unassignedLeads}
                    contactedLeads={contactedLeads}
                    convertedLeads={convertedLeads}
                />

                {/* ── Lead Source + Status Breakdown ───────────────────── */}
                <div className="row g-3 mgr-section">
                    <div className="col-lg-5">
                        <LeadSourceChart leadSource={leadSource} />
                    </div>
                    <div className="col-lg-7">
                        <StatusBreakdown statusBreakdown={statusBreakdown} />
                    </div>
                </div>

                {/* ── Call Performance ─────────────────────────────────── */}
                <CallPerformance
                    totalCallsMade={totalCallsMade}
                    answeredCalls={answeredCalls}
                    avgCallDurationSec={avgCallDurationSec}
                    callStatusBreakdown={callStatusBreakdown}
                    callOutcomes={callOutcomes}
                    reportsUrl={reportsUrl}
                />

                {/* ── Manager Performance ───────────────────────────────── */}
                <div className="dark-card mgr-section">
                    <ManagerTable managerStats={managerStats} reportsUrl={reportsUrl} />
                </div>

                {/* ── Telecaller Performance ────────────────────────────── */}
                <div className="dark-card mgr-section">
                    <TelecallerLeaderboard telecallerStats={telecallerStats} reportsUrl={reportsUrl} />
                </div>

                {/* ── Course Performance ────────────────────────────────── */}
                {courseStats.length > 0 && (
                    <div className="dark-card mgr-section">
                        <CoursePerformance courseStats={courseStats} reportsUrl={reportsUrl} />
                    </div>
                )}

                {/* ── Quick Reports ─────────────────────────────────────── */}
                <div className="dark-card mgr-section">
                    <QuickReports reportsUrl={reportsUrl} />
                </div>
            </div>
        </>
    );
}
