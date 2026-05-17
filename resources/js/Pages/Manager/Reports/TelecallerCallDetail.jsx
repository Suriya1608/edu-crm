import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import ReportFilters from './_Filters';

// ─── Helpers ─────────────────────────────────────────────────────────────────

function fmtMins(m) {
    m = Math.max(0, parseInt(m) || 0);
    if (m < 60)  return `${m}m`;
    return `${Math.floor(m / 60)}h ${m % 60}m`;
}

function scoreGrade(score) {
    if (score >= 70) return { grade: 'A', label: 'Excellent',  color: '#10b981' };
    if (score >= 40) return { grade: 'B', label: 'Good',       color: '#6366f1' };
    if (score >= 20) return { grade: 'C', label: 'Average',    color: '#f59e0b' };
    return               { grade: 'D', label: 'Needs Work',  color: '#ef4444' };
}

const OUTCOME_META = {
    interested:      { label: 'Interested',      color: '#10b981' },
    not_interested:  { label: 'Not Interested',  color: '#ef4444' },
    call_back_later: { label: 'Call Back Later', color: '#f59e0b' },
    switched_off:    { label: 'Switched Off',    color: '#64748b' },
    wrong_number:    { label: 'Wrong Number',    color: '#8b5cf6' },
};

const STATUS_META = {
    new:           { color: '#6366f1' },
    assigned:      { color: '#06b6d4' },
    contacted:     { color: '#0ea5e9' },
    interested:    { color: '#10b981' },
    follow_up:     { color: '#f59e0b' },
    converted:     { color: '#10b981' },
    not_interested:{ color: '#ef4444' },
};

const CALL_STATUS_COLOR = {
    'completed':  'success',
    'no-answer':  'secondary',
    'busy':       'warning',
    'failed':     'danger',
    'canceled':   'secondary',
};

// ─── Sub-components ───────────────────────────────────────────────────────────

function Section({ children, style }) {
    return (
        <div style={{ background: '#fff', borderRadius: 16, padding: '20px 22px', boxShadow: '0 1px 6px rgba(15,23,42,.07)', marginBottom: 20, ...style }}>
            {children}
        </div>
    );
}

function SectionTitle({ icon, title, right }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <span className="material-icons" style={{ fontSize: 20, color: '#6366f1' }}>{icon}</span>
                <span style={{ fontWeight: 700, fontSize: 15, color: '#0f172a' }}>{title}</span>
            </div>
            {right && <span style={{ fontSize: 12, color: '#94a3b8' }}>{right}</span>}
        </div>
    );
}

function KpiCard({ icon, color, label, value, sub, badge, badgeColor }) {
    return (
        <div className="col-6 col-lg-3">
            <div style={{
                background: '#fff', borderRadius: 14, padding: '18px 16px',
                boxShadow: '0 1px 6px rgba(15,23,42,.07)', height: '100%', position: 'relative', overflow: 'hidden',
            }}>
                <div style={{
                    width: 40, height: 40, borderRadius: 11, background: color + '18',
                    display: 'flex', alignItems: 'center', justifyContent: 'center', marginBottom: 10,
                }}>
                    <span className="material-icons" style={{ color, fontSize: 20 }}>{icon}</span>
                </div>
                <div style={{ fontSize: 10, fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: .7, marginBottom: 4 }}>
                    {label}
                </div>
                <div style={{ fontSize: 24, fontWeight: 800, color: '#0f172a', lineHeight: 1.1 }}>{value ?? 0}</div>
                {sub   && <div style={{ fontSize: 11, color: '#94a3b8', marginTop: 4 }}>{sub}</div>}
                {badge && (
                    <span style={{
                        position: 'absolute', top: 12, right: 12,
                        background: (badgeColor || '#6366f1') + '18', color: badgeColor || '#6366f1',
                        fontSize: 11, fontWeight: 700, padding: '2px 8px', borderRadius: 20,
                    }}>{badge}</span>
                )}
            </div>
        </div>
    );
}

function DirectionSplit({ inbound, outbound, inboundSecs, outboundSecs }) {
    const total  = inbound + outbound;
    const inPct  = total > 0 ? Math.round((inbound  / total) * 100) : 0;
    const outPct = total > 0 ? Math.round((outbound / total) * 100) : 0;
    const fmtS   = s => { s = Math.max(0, s || 0); return [Math.floor(s/3600), Math.floor((s%3600)/60), s%60].map(v => String(v).padStart(2,'0')).join(':'); };

    return (
        <Section>
            <SectionTitle icon="swap_vert" title="Inbound vs Outbound" right={`${total} total calls`} />
            {total === 0 ? (
                <p style={{ textAlign: 'center', color: '#94a3b8', fontSize: 13, margin: 0 }}>No call data for this period</p>
            ) : (
                <>
                    <div style={{ display: 'flex', borderRadius: 99, overflow: 'hidden', height: 10, marginBottom: 18 }}>
                        <div style={{ width: outPct + '%', background: '#6366f1', transition: 'width .6s' }} />
                        <div style={{ width: inPct  + '%', background: '#10b981', transition: 'width .6s' }} />
                    </div>
                    <div className="row g-3">
                        <div className="col-6">
                            <div style={{ background: '#6366f108', borderRadius: 12, padding: '14px 16px', borderLeft: '3px solid #6366f1' }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 8 }}>
                                    <span className="material-icons" style={{ fontSize: 16, color: '#6366f1' }}>call_made</span>
                                    <span style={{ fontSize: 12, fontWeight: 700, color: '#6366f1', textTransform: 'uppercase' }}>Outbound</span>
                                    <span style={{ marginLeft: 'auto', fontSize: 11, fontWeight: 700, background: '#6366f118', color: '#6366f1', padding: '1px 8px', borderRadius: 20 }}>{outPct}%</span>
                                </div>
                                <div style={{ fontSize: 28, fontWeight: 800, color: '#0f172a', lineHeight: 1 }}>{outbound}</div>
                                <div style={{ fontSize: 11, color: '#64748b', marginTop: 4 }}>calls · {fmtS(outboundSecs)} talk time</div>
                            </div>
                        </div>
                        <div className="col-6">
                            <div style={{ background: '#10b98108', borderRadius: 12, padding: '14px 16px', borderLeft: '3px solid #10b981' }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 8 }}>
                                    <span className="material-icons" style={{ fontSize: 16, color: '#10b981' }}>call_received</span>
                                    <span style={{ fontSize: 12, fontWeight: 700, color: '#10b981', textTransform: 'uppercase' }}>Inbound</span>
                                    <span style={{ marginLeft: 'auto', fontSize: 11, fontWeight: 700, background: '#10b98118', color: '#10b981', padding: '1px 8px', borderRadius: 20 }}>{inPct}%</span>
                                </div>
                                <div style={{ fontSize: 28, fontWeight: 800, color: '#0f172a', lineHeight: 1 }}>{inbound}</div>
                                <div style={{ fontSize: 11, color: '#64748b', marginTop: 4 }}>calls · {fmtS(inboundSecs)} talk time</div>
                            </div>
                        </div>
                    </div>
                </>
            )}
        </Section>
    );
}

function WhatsAppStats({ sent, received }) {
    const total   = sent + received;
    const sentPct = total > 0 ? Math.round((sent / total) * 100) : 0;
    const recvPct = total > 0 ? Math.round((received / total) * 100) : 0;

    return (
        <Section>
            <SectionTitle icon="chat" title="WhatsApp Activity" right={`${total} messages`} />
            {total === 0 ? (
                <p style={{ textAlign: 'center', color: '#94a3b8', fontSize: 13, margin: 0 }}>No WhatsApp activity for this period</p>
            ) : (
                <>
                    <div style={{ display: 'flex', borderRadius: 99, overflow: 'hidden', height: 10, marginBottom: 18 }}>
                        <div style={{ width: sentPct + '%', background: '#25d366', transition: 'width .6s' }} />
                        <div style={{ width: recvPct + '%', background: '#128c7e', transition: 'width .6s' }} />
                    </div>
                    <div className="row g-3">
                        <div className="col-6">
                            <div style={{ background: '#25d36608', borderRadius: 12, padding: '14px 16px', borderLeft: '3px solid #25d366' }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 8 }}>
                                    <span className="material-icons" style={{ fontSize: 15, color: '#25d366' }}>send</span>
                                    <span style={{ fontSize: 12, fontWeight: 700, color: '#25d366', textTransform: 'uppercase' }}>Sent</span>
                                    <span style={{ marginLeft: 'auto', fontSize: 11, fontWeight: 700, background: '#25d36618', color: '#25d366', padding: '1px 8px', borderRadius: 20 }}>{sentPct}%</span>
                                </div>
                                <div style={{ fontSize: 28, fontWeight: 800, color: '#0f172a' }}>{sent}</div>
                            </div>
                        </div>
                        <div className="col-6">
                            <div style={{ background: '#128c7e08', borderRadius: 12, padding: '14px 16px', borderLeft: '3px solid #128c7e' }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 8 }}>
                                    <span className="material-icons" style={{ fontSize: 15, color: '#128c7e' }}>mark_chat_read</span>
                                    <span style={{ fontSize: 12, fontWeight: 700, color: '#128c7e', textTransform: 'uppercase' }}>Received</span>
                                    <span style={{ marginLeft: 'auto', fontSize: 11, fontWeight: 700, background: '#128c7e18', color: '#128c7e', padding: '1px 8px', borderRadius: 20 }}>{recvPct}%</span>
                                </div>
                                <div style={{ fontSize: 28, fontWeight: 800, color: '#0f172a' }}>{received}</div>
                            </div>
                        </div>
                    </div>
                </>
            )}
        </Section>
    );
}

function StatusBreakdown({ rows }) {
    if (!rows || rows.length === 0) return null;
    const total = rows.reduce((s, r) => s + r.total, 0);
    return (
        <Section>
            <SectionTitle icon="donut_small" title="Lead Status Breakdown" right={`${total} total leads`} />
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                {rows.map(r => {
                    const pct   = total > 0 ? Math.round((r.total / total) * 100) : 0;
                    const color = STATUS_META[r.status]?.color ?? '#94a3b8';
                    return (
                        <div key={r.status}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 4 }}>
                                <span style={{ fontSize: 13, fontWeight: 600, color: '#334155', textTransform: 'capitalize' }}>
                                    {r.status.replace(/_/g, ' ')}
                                </span>
                                <span style={{ fontSize: 12, color: '#64748b' }}>{r.total} ({pct}%)</span>
                            </div>
                            <div style={{ height: 7, borderRadius: 99, background: '#f1f5f9' }}>
                                <div style={{ width: pct + '%', height: '100%', borderRadius: 99, background: color, transition: 'width .5s' }} />
                            </div>
                        </div>
                    );
                })}
            </div>
        </Section>
    );
}

function OutcomeBreakdown({ rows }) {
    if (!rows || rows.length === 0) return null;
    const total = rows.reduce((s, r) => s + r.total, 0);
    return (
        <Section>
            <SectionTitle icon="analytics" title="Call Outcomes" right={`${total} classified`} />
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                {rows.map(r => {
                    const meta  = OUTCOME_META[r.outcome] ?? { label: r.outcome, color: '#64748b' };
                    const pct   = total > 0 ? Math.round((r.total / total) * 100) : 0;
                    return (
                        <div key={r.outcome}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 4 }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                    <div style={{ width: 10, height: 10, borderRadius: '50%', background: meta.color }} />
                                    <span style={{ fontSize: 13, fontWeight: 600, color: '#334155' }}>{meta.label}</span>
                                </div>
                                <span style={{ fontSize: 12, color: '#64748b' }}>{r.total} ({pct}%)</span>
                            </div>
                            <div style={{ height: 7, borderRadius: 99, background: '#f1f5f9' }}>
                                <div style={{ width: pct + '%', height: '100%', borderRadius: 99, background: meta.color, transition: 'width .5s' }} />
                            </div>
                        </div>
                    );
                })}
            </div>
        </Section>
    );
}

// ─── Main component ───────────────────────────────────────────────────────────

const NAV = [
    { href: '/manager/reports',                        label: 'Overview'              },
    { href: '/manager/reports/telecaller-performance', label: 'Telecaller Performance' },
    { href: '/manager/reports/conversion',             label: 'Conversion'            },
    { href: '/manager/reports/source-performance',     label: 'Source Performance'    },
    { href: '/manager/reports/period',                 label: 'Period Analysis'       },
    { href: '/manager/reports/response-time',          label: 'Response Time'         },
    { href: '/manager/reports/call-efficiency',        label: 'Call Efficiency'       },
];

const PERIOD_TABS = [
    { label: 'Today',      value: '1'     },
    { label: 'This Week',  value: 'week'  },
    { label: 'This Month', value: 'month' },
    { label: 'Last 30d',   value: '30'    },
];

export default function TelecallerCallDetail({ filters, filterOptions, telecaller, periodLabel, metrics, leadStatusBreakdown, outcomeBreakdown, leadCallData }) {
    const [expanded, setExpanded] = useState({});
    const toggle = (id) => setExpanded(prev => ({ ...prev, [id]: !prev[id] }));

    const m    = metrics ?? {};
    const rows = leadCallData ?? [];
    const sg   = scoreGrade(m.efficiency_score ?? 0);

    // Export URL helpers
    const exportParams = new URLSearchParams({
        date_range: filters?.date_range ?? '30',
        source:     filters?.source     ?? 'all',
        telecaller: filters?.telecaller ?? 'all',
        campaign:   filters?.campaign   ?? 'all',
        call_type:  filters?.call_type  ?? 'all',
    }).toString();

    // Period tab navigation
    const goTab = (val) => {
        const params = { date_range: val, telecaller: filters?.telecaller ?? 'all', source: filters?.source ?? 'all', campaign: filters?.campaign ?? 'all', call_type: filters?.call_type ?? 'all' };
        router.get('/manager/reports/telecaller-detail', params, { preserveState: false });
    };

    const activeTab = PERIOD_TABS.find(t => t.value === filters?.date_range)?.value ?? null;

    return (
        <>
            <Head title={`${telecaller?.name ?? 'Telecaller'} – Performance`} />

            {/* Nav */}
            <div className="d-flex gap-2 flex-wrap mb-4">
                {NAV.map(n => (
                    <Link key={n.href} href={n.href} className="btn btn-sm btn-outline-secondary">{n.label}</Link>
                ))}
            </div>

            {/* Back + Hero header */}
            <div style={{ background: 'linear-gradient(135deg, #4f46e5 0%, #6366f1 60%, #818cf8 100%)', borderRadius: 18, padding: '24px 28px', marginBottom: 24, position: 'relative', overflow: 'hidden' }}>
                {/* decorative blobs */}
                <div style={{ position: 'absolute', top: -40, right: -40, width: 160, height: 160, borderRadius: '50%', background: 'rgba(255,255,255,.06)' }} />
                <div style={{ position: 'absolute', bottom: -30, right: 80, width: 100, height: 100, borderRadius: '50%', background: 'rgba(255,255,255,.04)' }} />

                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16, position: 'relative' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
                        <Link href="/manager/reports/telecaller-performance"
                            style={{ background: 'rgba(255,255,255,.15)', borderRadius: 10, padding: '6px 8px', display: 'flex', alignItems: 'center', textDecoration: 'none' }}>
                            <span className="material-icons" style={{ fontSize: 18, color: '#fff' }}>arrow_back</span>
                        </Link>
                        <div style={{ width: 52, height: 52, borderRadius: 14, background: 'rgba(255,255,255,.2)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                            <span className="material-icons" style={{ fontSize: 28, color: '#fff' }}>person</span>
                        </div>
                        <div>
                            <div style={{ fontSize: 22, fontWeight: 800, color: '#fff', lineHeight: 1.1 }}>{telecaller?.name}</div>
                            <div style={{ fontSize: 13, color: 'rgba(255,255,255,.75)', marginTop: 3 }}>
                                <span className="material-icons" style={{ fontSize: 13, verticalAlign: -2, marginRight: 4 }}>calendar_today</span>
                                {periodLabel}
                            </div>
                        </div>
                    </div>

                    {/* Score badge */}
                    <div style={{ background: 'rgba(255,255,255,.15)', borderRadius: 14, padding: '14px 18px', textAlign: 'center' }}>
                        <div style={{ fontSize: 36, fontWeight: 900, color: '#fff', lineHeight: 1 }}>{m.efficiency_score ?? 0}</div>
                        <div style={{ fontSize: 11, color: 'rgba(255,255,255,.7)', marginTop: 2 }}>/ 100 score</div>
                        <div style={{ marginTop: 6, background: sg.color + '30', color: '#fff', fontSize: 11, fontWeight: 700, padding: '2px 10px', borderRadius: 20, display: 'inline-block' }}>
                            {sg.grade} · {sg.label}
                        </div>
                    </div>
                </div>

                {/* Period quick tabs */}
                <div style={{ display: 'flex', gap: 8, marginTop: 20, flexWrap: 'wrap' }}>
                    {PERIOD_TABS.map(t => (
                        <button key={t.value} onClick={() => goTab(t.value)}
                            style={{
                                padding: '6px 16px', borderRadius: 20, border: 'none', cursor: 'pointer', fontSize: 13, fontWeight: 600,
                                background: activeTab === t.value ? '#fff' : 'rgba(255,255,255,.18)',
                                color: activeTab === t.value ? '#4f46e5' : '#fff',
                                transition: 'all .2s',
                            }}>
                            {t.label}
                        </button>
                    ))}

                    {/* Exports */}
                    <div style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
                        <a href={`/manager/reports/export/telecaller-detail/excel?${exportParams}`}
                            style={{ padding: '6px 14px', borderRadius: 20, background: 'rgba(255,255,255,.18)', color: '#fff', textDecoration: 'none', fontSize: 13, fontWeight: 600, display: 'flex', alignItems: 'center', gap: 4 }}>
                            <span className="material-icons" style={{ fontSize: 15 }}>download</span>Excel
                        </a>
                        <button onClick={() => router.visit(`/manager/reports/export/telecaller-detail/pdf?${exportParams}`)}
                            style={{ padding: '6px 14px', borderRadius: 20, background: 'rgba(255,255,255,.18)', color: '#fff', border: 'none', cursor: 'pointer', fontSize: 13, fontWeight: 600, display: 'flex', alignItems: 'center', gap: 4 }}>
                            <span className="material-icons" style={{ fontSize: 15 }}>picture_as_pdf</span>PDF
                        </button>
                    </div>
                </div>
            </div>

            {/* Filters */}
            <ReportFilters filters={filters} filterOptions={filterOptions} url="/manager/reports/telecaller-detail" showCampaign showCallType />

            {/* KPI Grid – Row 1: Call metrics */}
            <div className="row g-3 mb-3">
                <KpiCard icon="call"           color="#6366f1" label="Total Calls"      value={m.total_calls}      sub={`${m.calls_connected ?? 0} connected`} />
                <KpiCard icon="call_received"  color="#10b981" label="Inbound"          value={m.calls_inbound}    badge={m.total_calls > 0 ? Math.round(((m.calls_inbound ?? 0) / m.total_calls) * 100) + '%' : '0%'} badgeColor="#10b981" />
                <KpiCard icon="call_made"      color="#6366f1" label="Outbound"         value={m.calls_outbound}   badge={m.total_calls > 0 ? Math.round(((m.calls_outbound ?? 0) / m.total_calls) * 100) + '%' : '0%'} badgeColor="#6366f1" />
                <KpiCard icon="call_missed"    color="#ef4444" label="Missed Calls"     value={m.calls_missed}     badge={m.total_calls > 0 ? Math.round(((m.calls_missed ?? 0) / m.total_calls) * 100) + '%' : '0%'} badgeColor="#ef4444" />
            </div>

            {/* KPI Grid – Row 2: Time metrics */}
            <div className="row g-3 mb-3">
                <KpiCard icon="timer"          color="#06b6d4" label="Total Talk Time"  value={m.total_talk_time}  sub="across all calls" />
                <KpiCard icon="schedule"       color="#8b5cf6" label="Avg Duration"     value={m.avg_duration}     sub="per call" />
                <KpiCard icon="speed"          color="#f59e0b" label="Avg Response"     value={fmtMins(m.avg_response_mins)} sub="lead → first call" />
                <KpiCard icon="campaign"       color="#0ea5e9" label="Campaign Calls"   value={m.campaign_calls}   sub={`${m.campaign_converted ?? 0} converted`} />
            </div>

            {/* KPI Grid – Row 3: Lead & conversion metrics */}
            <div className="row g-3 mb-4">
                <KpiCard icon="people"         color="#6366f1" label="Leads Assigned"   value={m.leads_assigned}   />
                <KpiCard icon="touch_app"      color="#10b981" label="Leads Attended"   value={m.leads_attended}   badge={m.leads_assigned > 0 ? Math.round(((m.leads_attended ?? 0) / m.leads_assigned) * 100) + '%' : '0%'} badgeColor="#10b981" />
                <KpiCard icon="check_circle"   color="#8b5cf6" label="Converted"        value={m.leads_converted}  badge={(m.conversion_rate ?? 0) + '%'} badgeColor="#8b5cf6" />
                <KpiCard icon="pending_actions" color="#f59e0b" label="Follow-ups Pending" value={m.followups_pending} sub={`${m.followups_done ?? 0} done this period`} badge={m.followups_pending > 0 ? 'Pending' : null} badgeColor="#f59e0b" />
            </div>

            {/* WhatsApp row (full width) */}
            <div className="row g-3 mb-4">
                <div className="col-md-4">
                    <div style={{ background: '#fff', borderRadius: 14, padding: '18px 16px', boxShadow: '0 1px 6px rgba(15,23,42,.07)', height: '100%' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 12 }}>
                            <div style={{ width: 40, height: 40, borderRadius: 11, background: '#25d36618', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                <span className="material-icons" style={{ color: '#25d366', fontSize: 20 }}>chat</span>
                            </div>
                            <div>
                                <div style={{ fontSize: 10, fontWeight: 700, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: .7 }}>WhatsApp Sent</div>
                                <div style={{ fontSize: 24, fontWeight: 800, color: '#0f172a' }}>{m.whatsapp_sent ?? 0}</div>
                            </div>
                        </div>
                        <div style={{ fontSize: 12, color: '#64748b' }}>{m.whatsapp_received ?? 0} messages received</div>
                    </div>
                </div>
                <div className="col-md-8">
                    <DirectionSplit
                        inbound={m.calls_inbound ?? 0}
                        outbound={m.calls_outbound ?? 0}
                        inboundSecs={m.inbound_secs ?? 0}
                        outboundSecs={m.outbound_secs ?? 0}
                    />
                </div>
            </div>

            {/* Status & Outcome breakdown */}
            <div className="row g-3 mb-4">
                <div className="col-md-6">
                    <StatusBreakdown rows={leadStatusBreakdown} />
                </div>
                <div className="col-md-6">
                    <OutcomeBreakdown rows={outcomeBreakdown} />
                </div>
            </div>

            {/* Per-lead call detail table */}
            <div className="custom-table">
                <div className="table-header">
                    <h3>
                        <span className="material-icons me-2" style={{ verticalAlign: -5, fontSize: 20 }}>call_made</span>
                        Per-Lead Call Breakdown
                        <span style={{ marginLeft: 10, fontSize: 13, fontWeight: 400, color: '#94a3b8' }}>Click a row to expand individual calls</span>
                    </h3>
                    <a href={`/manager/reports/export/telecaller-detail/excel?${exportParams}`}
                        className="btn btn-sm btn-outline-success">
                        <span className="material-icons me-1" style={{ fontSize: 15 }}>download</span>Excel
                    </a>
                    <button className="btn btn-sm btn-primary"
                        onClick={() => router.visit(`/manager/reports/export/telecaller-detail/pdf?${exportParams}`)}>
                        <span className="material-icons me-1" style={{ fontSize: 15 }}>picture_as_pdf</span>PDF
                    </button>
                </div>

                <div className="table-responsive">
                    <table className="table mb-0">
                        <thead>
                            <tr>
                                <th style={{ width: 36 }}></th>
                                <th>Lead</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Calls</th>
                                <th>Talk Time</th>
                                <th>Answered</th>
                                <th>Missed</th>
                                <th>Last Call</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="text-center py-5 text-muted">
                                        No calls for selected period.
                                    </td>
                                </tr>
                            ) : rows.map(row => (
                                <React.Fragment key={row.lead_id}>
                                    {/* Lead summary row */}
                                    <tr onClick={() => toggle(row.lead_id)}
                                        style={{ cursor: 'pointer', background: expanded[row.lead_id] ? '#f8fafc' : '' }}>
                                        <td style={{ textAlign: 'center', verticalAlign: 'middle' }}>
                                            <span className="material-icons" style={{
                                                fontSize: 18, color: '#6366f1', display: 'block',
                                                transition: 'transform .2s',
                                                transform: expanded[row.lead_id] ? 'rotate(90deg)' : 'rotate(0deg)',
                                            }}>chevron_right</span>
                                        </td>
                                        <td>
                                            <div className="fw-semibold">{row.lead_name}</div>
                                            <div style={{ fontSize: 11, color: '#94a3b8' }}>{row.lead_code}</div>
                                        </td>
                                        <td style={{ fontSize: 13 }}>{row.phone}</td>
                                        <td>
                                            <span className="badge" style={{
                                                background: (STATUS_META[row.lead_status]?.color ?? '#64748b') + '18',
                                                color: STATUS_META[row.lead_status]?.color ?? '#64748b',
                                                fontSize: 11,
                                            }}>
                                                {row.lead_status?.replace(/_/g, ' ')}
                                            </span>
                                        </td>
                                        <td className="fw-bold">{row.total_calls}</td>
                                        <td style={{ fontWeight: 600, color: '#6366f1', fontSize: 13 }}>{row.total_duration_label}</td>
                                        <td><span className="badge bg-success">{row.answered}</span></td>
                                        <td><span className="badge bg-danger">{row.missed}</span></td>
                                        <td style={{ fontSize: 12, color: '#64748b' }}>
                                            {row.last_call_at ? new Date(row.last_call_at).toLocaleString() : '—'}
                                        </td>
                                    </tr>

                                    {/* Individual call rows */}
                                    {expanded[row.lead_id] && (row.calls ?? []).map(call => (
                                        <tr key={call.id} style={{ background: '#f0f4ff' }}>
                                            <td></td>
                                            <td colSpan={2} style={{ paddingLeft: 36, fontSize: 13 }}>
                                                <span className="material-icons me-1"
                                                    style={{ fontSize: 14, verticalAlign: -3, color: '#6366f1' }}>
                                                    {call.direction === 'inbound' ? 'call_received' : 'call_made'}
                                                </span>
                                                {call.called_at}
                                            </td>
                                            <td>
                                                <span className={`badge bg-${CALL_STATUS_COLOR[call.status] ?? 'secondary'}`}
                                                    style={{ fontSize: 11 }}>
                                                    {call.status}
                                                </span>
                                            </td>
                                            <td></td>
                                            <td style={{ fontWeight: 600, fontSize: 13 }}>{call.duration_label}</td>
                                            <td colSpan={3}>
                                                {call.outcome ? (
                                                    <span className="badge"
                                                        style={{ background: (OUTCOME_META[call.outcome]?.color ?? '#64748b') + '18', color: OUTCOME_META[call.outcome]?.color ?? '#64748b', fontSize: 11 }}>
                                                        {OUTCOME_META[call.outcome]?.label ?? call.outcome.replace(/_/g, ' ')}
                                                    </span>
                                                ) : <span style={{ fontSize: 12, color: '#94a3b8' }}>—</span>}
                                            </td>
                                        </tr>
                                    ))}
                                </React.Fragment>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
