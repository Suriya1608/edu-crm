import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

// ─── Design tokens ────────────────────────────────────────────────────────────
const ORG   = '#FF5C1A';
const ORGL  = '#FF8042';
const DARKC = '#1A1A2E';
const BORDER = '#EAEAED';
const TEXT   = '#1A1A2E';
const MUTED  = '#9EA3B0';
const WHITE  = '#FFFFFF';
const BGGRAY = '#F5F5F7';

const card = (e = {}) => ({
    background: WHITE,
    borderRadius: 14,
    boxShadow: '0 2px 16px rgba(0,0,0,0.07)',
    border: `1px solid ${BORDER}`,
    ...e,
});

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
    new:            { label: 'New',           color: '#6366f1' },
    assigned:       { label: 'Assigned',      color: '#8b5cf6' },
    contacted:      { label: 'Contacted',     color: '#06b6d4' },
    interested:     { label: 'Interested',    color: '#10b981' },
    converted:      { label: 'Converted',     color: '#f59e0b' },
    not_interested: { label: 'Not Interested',color: '#ef4444' },
};

function scoreGrade(score) {
    if (score >= 80) return { grade: 'A', label: 'Excellent',  color: '#10b981' };
    if (score >= 60) return { grade: 'B', label: 'Good',       color: '#6366f1' };
    if (score >= 40) return { grade: 'C', label: 'Average',    color: '#f59e0b' };
    return                  { grade: 'D', label: 'Needs Work', color: '#ef4444' };
}

// ─── Target progress bar ──────────────────────────────────────────────────────
function TargetBar({ current, target, pct }) {
    return (
        <div style={{ marginTop: 12 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 5 }}>
                <span style={{ fontSize: 11, color: MUTED, fontWeight: 600, textTransform: 'uppercase', letterSpacing: 0.4 }}>
                    LEADS CONTACTED
                </span>
                <span style={{ fontSize: 11, fontWeight: 700, color: pct >= 100 ? '#10b981' : pct >= 60 ? '#f59e0b' : '#ef4444' }}>
                    {current} / {target}
                </span>
            </div>
            <div style={{ background: BGGRAY, borderRadius: 20, height: 8, overflow: 'hidden' }}>
                <div style={{
                    width: pct + '%', height: '100%',
                    background: `linear-gradient(90deg, ${ORG}, ${ORGL})`,
                    borderRadius: 20,
                    transition: 'width .6s ease',
                    minWidth: pct > 0 ? 4 : 0,
                }} />
            </div>
            <div style={{ textAlign: 'right', fontSize: 10, color: MUTED, marginTop: 3 }}>
                {pct >= 100 ? '✓ All leads contacted' : `${pct}% of leads contacted`}
            </div>
        </div>
    );
}

// ─── Trend chip ───────────────────────────────────────────────────────────────
function TrendChip({ trend, goodDir = 'up', prevLabel }) {
    if (!trend || trend.dir === 'flat') return null;
    if (trend.dir === 'new') {
        return (
            <div style={{ display: 'flex', alignItems: 'center', gap: 4, marginTop: 6 }}>
                <span style={{ fontSize: 10, fontWeight: 700, background: '#6366f118', color: '#6366f1', padding: '2px 7px', borderRadius: 20 }}>
                    NEW
                </span>
                <span style={{ fontSize: 10, color: MUTED }}>no data {prevLabel}</span>
            </div>
        );
    }
    const isGood = trend.dir === goodDir;
    const color  = isGood ? '#10b981' : '#ef4444';
    const arrow  = trend.dir === 'up' ? '↑' : '↓';
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 4, marginTop: 6 }}>
            <span style={{
                fontSize: 10, fontWeight: 700,
                background: color + '18', color,
                padding: '2px 7px', borderRadius: 20,
                display: 'inline-flex', alignItems: 'center', gap: 2,
            }}>
                {arrow} {trend.pct}%
            </span>
            <span style={{ fontSize: 10, color: MUTED }}>vs {prevLabel}</span>
        </div>
    );
}

// ─── KPI Card ─────────────────────────────────────────────────────────────────
function KpiCard({ icon, label, value, sub, badge, badgeColor, trend, trendGoodDir = 'up', prevLabel, targetBar, noLeads }) {
    return (
        <div className="col-6 col-lg-3">
            <div style={{ ...card(), padding: '20px 18px', height: '100%', position: 'relative', overflow: 'hidden' }}>
                {/* Icon circle */}
                <div style={{
                    width: 40, height: 40, borderRadius: '50%',
                    background: ORG,
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    marginBottom: 14,
                    flexShrink: 0,
                }}>
                    <span className="material-icons" style={{ color: WHITE, fontSize: 20 }}>{icon}</span>
                </div>

                {/* Label */}
                <div style={{ fontSize: 11, fontWeight: 600, color: MUTED, textTransform: 'uppercase', letterSpacing: 0.6, marginBottom: 4 }}>
                    {label}
                </div>

                {/* Value */}
                <div style={{ fontSize: 32, fontWeight: 800, color: TEXT, lineHeight: 1.1, marginBottom: 4 }}>
                    {value}
                </div>

                {sub && <div style={{ fontSize: 12, color: MUTED, marginTop: 2 }}>{sub}</div>}

                <TrendChip trend={trend} goodDir={trendGoodDir} prevLabel={prevLabel} />

                {targetBar && <TargetBar {...targetBar} />}

                {noLeads && (
                    <div style={{
                        marginTop: 10, display: 'flex', alignItems: 'center', gap: 5,
                        background: BGGRAY, borderRadius: 8, padding: '6px 8px',
                    }}>
                        <span className="material-icons" style={{ fontSize: 13, color: MUTED }}>info</span>
                        <span style={{ fontSize: 10, color: MUTED, lineHeight: 1.3 }}>
                            No leads assigned yet — request leads from your manager
                        </span>
                    </div>
                )}

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

// ─── WhatsApp activity card ───────────────────────────────────────────────────
function WhatsAppActivity({ sent, received, total, trend, prevLabel }) {
    const sentPct     = total > 0 ? Math.round((sent     / total) * 100) : 0;
    const receivedPct = total > 0 ? Math.round((received / total) * 100) : 0;

    return (
        <div style={{ ...card(), padding: '20px 22px', marginBottom: 24 }}>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 14 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <div style={{ width: 32, height: 32, borderRadius: '50%', background: ORG, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <span className="material-icons" style={{ fontSize: 17, color: WHITE }}>chat</span>
                    </div>
                    <span style={{ fontWeight: 700, fontSize: 15, color: TEXT }}>WhatsApp Activity</span>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                    <TrendChip trend={trend} goodDir="up" prevLabel={prevLabel} />
                    <span style={{ fontSize: 12, color: MUTED }}>{total} messages</span>
                </div>
            </div>

            {total === 0 ? (
                <div style={{ textAlign: 'center', padding: '20px 0', color: MUTED, fontSize: 13 }}>
                    <span className="material-icons" style={{ fontSize: 36, display: 'block', marginBottom: 8, color: BORDER }}>chat_bubble_outline</span>
                    No WhatsApp activity for this period
                </div>
            ) : (
                <>
                    {/* Split bar */}
                    <div style={{ display: 'flex', borderRadius: 20, overflow: 'hidden', height: 8, marginBottom: 18, background: BGGRAY }}>
                        <div style={{ width: sentPct + '%', background: '#25d366', transition: 'width .6s ease' }} />
                        <div style={{ width: receivedPct + '%', background: '#128c7e', transition: 'width .6s ease' }} />
                    </div>

                    <div style={{ display: 'flex', gap: 16 }}>
                        {/* Sent */}
                        <div style={{ flex: 1, background: '#25d36608', borderRadius: 12, padding: '14px 16px', borderLeft: '3px solid #25d366' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 8 }}>
                                <span className="material-icons" style={{ fontSize: 16, color: '#25d366' }}>send</span>
                                <span style={{ fontSize: 12, fontWeight: 700, color: '#25d366', textTransform: 'uppercase', letterSpacing: .5 }}>Sent</span>
                                <span style={{ marginLeft: 'auto', fontSize: 11, fontWeight: 700, background: '#25d36618', color: '#25d366', padding: '1px 8px', borderRadius: 20 }}>{sentPct}%</span>
                            </div>
                            <div style={{ fontSize: 28, fontWeight: 800, color: TEXT, lineHeight: 1 }}>{sent}</div>
                            <div style={{ fontSize: 11, color: MUTED, marginTop: 4 }}>messages sent</div>
                        </div>

                        {/* Received */}
                        <div style={{ flex: 1, background: '#128c7e08', borderRadius: 12, padding: '14px 16px', borderLeft: '3px solid #128c7e' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 8 }}>
                                <span className="material-icons" style={{ fontSize: 16, color: '#128c7e' }}>mark_chat_read</span>
                                <span style={{ fontSize: 12, fontWeight: 700, color: '#128c7e', textTransform: 'uppercase', letterSpacing: .5 }}>Received</span>
                                <span style={{ marginLeft: 'auto', fontSize: 11, fontWeight: 700, background: '#128c7e18', color: '#128c7e', padding: '1px 8px', borderRadius: 20 }}>{receivedPct}%</span>
                            </div>
                            <div style={{ fontSize: 28, fontWeight: 800, color: TEXT, lineHeight: 1 }}>{received}</div>
                            <div style={{ fontSize: 11, color: MUTED, marginTop: 4 }}>messages received</div>
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}

// ─── Direction split card ─────────────────────────────────────────────────────
function DirectionSplit({ inbound, outbound, inboundSecs, outboundSecs }) {
    const total   = inbound + outbound;
    const inPct   = total > 0 ? Math.round((inbound  / total) * 100) : 0;
    const outPct  = total > 0 ? Math.round((outbound / total) * 100) : 0;
    const fmtTime = s => { s = Math.max(0, s); return `${String(Math.floor(s/3600)).padStart(2,'0')}:${String(Math.floor((s%3600)/60)).padStart(2,'0')}:${String(s%60).padStart(2,'0')}`; };

    return (
        <div style={{ ...card(), padding: '20px 22px', marginBottom: 24 }}>
            <SectionTitle icon="swap_vert" title="Inbound vs Outbound" right={`${total} total calls`} />

            {total === 0 ? (
                <div style={{ textAlign: 'center', padding: '20px 0', color: MUTED, fontSize: 13 }}>No call data for this period</div>
            ) : (
                <>
                    {/* Split bar */}
                    <div style={{ display: 'flex', borderRadius: 20, overflow: 'hidden', height: 8, marginBottom: 18, background: BGGRAY }}>
                        <div style={{ width: outPct + '%', background: `linear-gradient(90deg, ${ORG}, ${ORGL})`, transition: 'width .6s ease' }} />
                        <div style={{ width: inPct  + '%', background: '#10b981', transition: 'width .6s ease' }} />
                    </div>

                    <div style={{ display: 'flex', gap: 16 }}>
                        {/* Outbound */}
                        <div style={{ flex: 1, background: ORG + '0d', borderRadius: 12, padding: '14px 16px', borderLeft: `3px solid ${ORG}` }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 8 }}>
                                <span className="material-icons" style={{ fontSize: 16, color: ORG }}>call_made</span>
                                <span style={{ fontSize: 12, fontWeight: 700, color: ORG, textTransform: 'uppercase', letterSpacing: .5 }}>Outbound</span>
                                <span style={{ marginLeft: 'auto', fontSize: 11, fontWeight: 700, background: ORG + '18', color: ORG, padding: '1px 8px', borderRadius: 20 }}>{outPct}%</span>
                            </div>
                            <div style={{ fontSize: 28, fontWeight: 800, color: TEXT, lineHeight: 1 }}>{outbound}</div>
                            <div style={{ fontSize: 11, color: MUTED, marginTop: 4 }}>calls · {fmtTime(outboundSecs)} talk time</div>
                        </div>

                        {/* Inbound */}
                        <div style={{ flex: 1, background: '#10b98108', borderRadius: 12, padding: '14px 16px', borderLeft: '3px solid #10b981' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 8 }}>
                                <span className="material-icons" style={{ fontSize: 16, color: '#10b981' }}>call_received</span>
                                <span style={{ fontSize: 12, fontWeight: 700, color: '#10b981', textTransform: 'uppercase', letterSpacing: .5 }}>Inbound</span>
                                <span style={{ marginLeft: 'auto', fontSize: 11, fontWeight: 700, background: '#10b98118', color: '#10b981', padding: '1px 8px', borderRadius: 20 }}>{inPct}%</span>
                            </div>
                            <div style={{ fontSize: 28, fontWeight: 800, color: TEXT, lineHeight: 1 }}>{inbound}</div>
                            <div style={{ fontSize: 11, color: MUTED, marginTop: 4 }}>calls · {fmtTime(inboundSecs)} talk time</div>
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}

// ─── Section heading ──────────────────────────────────────────────────────────
function SectionTitle({ icon, title, right }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 14 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <span className="material-icons" style={{ fontSize: 20, color: ORG }}>{icon}</span>
                <span style={{ fontWeight: 700, fontSize: 15, color: TEXT }}>{title}</span>
            </div>
            {right && <span style={{ fontSize: 12, color: MUTED }}>{right}</span>}
        </div>
    );
}

// ─── Outcome bar row ──────────────────────────────────────────────────────────
function OutcomeRow({ outcome, count, total, drilldownHref }) {
    const meta = OUTCOME_META[outcome] || { label: outcome, color: '#64748b', icon: 'call' };
    const pct  = total > 0 ? Math.round((count / total) * 100) : 0;

    const inner = (
        <>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 5 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 7 }}>
                    <span className="material-icons" style={{ fontSize: 16, color: meta.color }}>{meta.icon}</span>
                    <span style={{ fontSize: 13, fontWeight: 600, color: TEXT }}>{meta.label}</span>
                </div>
                <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                    <span style={{ fontSize: 13, color: MUTED }}>{count} calls</span>
                    <span style={{
                        minWidth: 38, textAlign: 'center',
                        background: meta.color + '18', color: meta.color,
                        fontSize: 11, fontWeight: 700,
                        padding: '2px 7px', borderRadius: 20,
                    }}>{pct}%</span>
                    <span className="material-icons" style={{ fontSize: 14, color: MUTED }}>chevron_right</span>
                </div>
            </div>
            <div style={{ background: BGGRAY, borderRadius: 20, height: 8 }}>
                <div style={{
                    width: pct + '%', height: '100%',
                    borderRadius: 20, background: meta.color,
                    transition: 'width .6s ease',
                    minWidth: pct > 0 ? 6 : 0,
                }} />
            </div>
        </>
    );

    const wrapStyle = {
        marginBottom: 14, borderRadius: 10, padding: '10px 10px 10px',
        cursor: 'pointer', transition: 'background .15s',
        textDecoration: 'none', display: 'block',
    };

    return drilldownHref ? (
        <Link href={drilldownHref} style={wrapStyle}
            onMouseEnter={e => e.currentTarget.style.background = BGGRAY}
            onMouseLeave={e => e.currentTarget.style.background = 'transparent'}
        >
            {inner}
        </Link>
    ) : (
        <div style={{ ...wrapStyle, cursor: 'default' }}>{inner}</div>
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
                                background: active ? `linear-gradient(180deg, ${ORG}, ${ORGL})` : BGGRAY,
                                cursor: 'default',
                            }} />
                            <span style={{ fontSize: 9, color: MUTED, whiteSpace: 'nowrap' }}>
                                {h.hour}h
                            </span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

// ─── Calls-per-day line chart (weekly / monthly) ─────────────────────────────
function DailyLineChart({ dailyBreakdown, scope }) {
    if (!dailyBreakdown || dailyBreakdown.length === 0) {
        return (
            <div style={{ textAlign: 'center', padding: '20px 0', color: MUTED, fontSize: 13 }}>
                No call activity for this period
            </div>
        );
    }

    const W = 560, H = 120;
    const pad = { top: 18, right: 16, bottom: 28, left: 34 };
    const iW  = W - pad.left - pad.right;
    const iH  = H - pad.top  - pad.bottom;

    const n        = dailyBreakdown.length;
    const maxCalls = Math.max(...dailyBreakdown.map(d => d.calls), 1);

    const xPos = i  => pad.left + (n <= 1 ? iW / 2 : (i / (n - 1)) * iW);
    const yPos = v  => pad.top  + iH - (v / maxCalls) * iH;

    const pts = dailyBreakdown.map((d, i) => ({
        x: xPos(i), y: yPos(d.calls), calls: d.calls, day: d.day,
    }));

    const linePath = pts.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ');
    const areaPath = `${linePath} L${pts[pts.length - 1].x.toFixed(1)},${(pad.top + iH).toFixed(1)} L${pts[0].x.toFixed(1)},${(pad.top + iH).toFixed(1)} Z`;

    const stride = n > 20 ? 5 : n > 10 ? 3 : 1;
    const yTicks = [0, Math.round(maxCalls / 2), maxCalls];

    const shortLabel = (day) => {
        const parts = day.split(' ');
        return scope === 'weekly' ? `${parts[0]} ${parts[1]}` : parts[0];
    };

    return (
        <div style={{ overflowX: 'auto' }}>
            <svg viewBox={`0 0 ${W} ${H}`} style={{ width: '100%', minWidth: 300, display: 'block' }}>
                <defs>
                    <linearGradient id="dlcGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%"   stopColor={ORG} stopOpacity="0.18" />
                        <stop offset="100%" stopColor={ORG} stopOpacity="0"    />
                    </linearGradient>
                </defs>

                {yTicks.map(v => (
                    <g key={v}>
                        <line
                            x1={pad.left} y1={yPos(v).toFixed(1)}
                            x2={pad.left + iW} y2={yPos(v).toFixed(1)}
                            stroke={BORDER} strokeWidth="1"
                        />
                        <text x={pad.left - 5} y={yPos(v) + 4} textAnchor="end" fontSize="9" fill={MUTED}>
                            {v}
                        </text>
                    </g>
                ))}

                <path d={areaPath} fill="url(#dlcGrad)" />

                <path d={linePath} fill="none" stroke={ORG} strokeWidth="2"
                    strokeLinejoin="round" strokeLinecap="round" />

                {pts.map((p, i) => {
                    const showLabel = i % stride === 0 || i === n - 1;
                    return (
                        <g key={i}>
                            <title>{p.calls} calls · {p.day}</title>
                            <circle
                                cx={p.x.toFixed(1)} cy={p.y.toFixed(1)}
                                r={p.calls > 0 ? 3.5 : 2}
                                fill={p.calls > 0 ? ORG : BGGRAY}
                                stroke={WHITE} strokeWidth="1.5"
                            />
                            {showLabel && (
                                <text x={p.x.toFixed(1)} y={H - 5}
                                    textAnchor="middle" fontSize="9" fill={MUTED}>
                                    {shortLabel(p.day)}
                                </text>
                            )}
                        </g>
                    );
                })}
            </svg>
        </div>
    );
}

// ─── Course breakdown ─────────────────────────────────────────────────────────
function CourseBreakdown({ rows, title, icon, valueKey = 'enquiries', emptyMsg = 'No data for this period' }) {
    const max = Math.max(...rows.map(r => r[valueKey] ?? 0), 1);
    return (
        <div style={{ ...card(), padding: '20px 22px', height: '100%' }}>
            <SectionTitle icon={icon} title={title} right={`${rows.length} course${rows.length !== 1 ? 's' : ''}`} />
            {rows.length === 0 ? (
                <div style={{ textAlign: 'center', padding: '30px 0', color: MUTED, fontSize: 13 }}>
                    <span className="material-icons" style={{ fontSize: 32, display: 'block', marginBottom: 8, color: BORDER }}>school</span>
                    {emptyMsg}
                </div>
            ) : rows.map((r, i) => {
                const val  = r[valueKey] ?? 0;
                const pct  = Math.round((val / max) * 100);
                const conv = r.conversions ?? r.count ?? 0;
                return (
                    <div key={i} style={{ marginBottom: 14 }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 5 }}>
                            <span style={{ fontSize: 13, fontWeight: 600, color: TEXT, flex: 1, marginRight: 8, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                {r.course}
                            </span>
                            <div style={{ display: 'flex', gap: 6, flexShrink: 0, alignItems: 'center' }}>
                                <span style={{ fontSize: 13, color: MUTED, fontWeight: 600 }}>{val}</span>
                                {r.conversions !== undefined && conv > 0 && (
                                    <span style={{ fontSize: 10, background: '#10b98118', color: '#10b981', padding: '1px 7px', borderRadius: 20, fontWeight: 700 }}>
                                        {conv} ✓
                                    </span>
                                )}
                            </div>
                        </div>
                        <div style={{ background: BGGRAY, borderRadius: 20, height: 8 }}>
                            <div style={{
                                width: pct + '%', height: '100%',
                                background: `linear-gradient(90deg, ${ORG}, ${ORGL})`,
                                borderRadius: 20,
                                minWidth: pct > 0 ? 4 : 0,
                                transition: 'width .5s ease',
                            }} />
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

// ─── Gender breakdown ─────────────────────────────────────────────────────────
const GENDER_META = {
    male:          { label: 'Male',          color: '#6366f1', icon: 'male' },
    female:        { label: 'Female',        color: '#ec4899', icon: 'female' },
    not_specified: { label: 'Not Specified', color: MUTED,     icon: 'person' },
};

function GenderBreakdown({ rows }) {
    const total = rows.reduce((s, r) => s + r.total, 0);
    return (
        <div style={{ ...card(), padding: '20px 22px', height: '100%' }}>
            <SectionTitle icon="wc" title="Gender-wise Analysis" right={`${total} leads`} />
            {total === 0 ? (
                <div style={{ textAlign: 'center', padding: '30px 0', color: MUTED, fontSize: 13 }}>No gender data</div>
            ) : rows.map((r, i) => {
                const meta    = GENDER_META[r.gender] || { label: r.gender, color: MUTED, icon: 'person' };
                const pct     = total > 0 ? Math.round((r.total / total) * 100) : 0;
                const convPct = r.total > 0 ? Math.round((r.conversions / r.total) * 100) : 0;
                return (
                    <div key={i} style={{ marginBottom: 18 }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 6 }}>
                            <span className="material-icons" style={{ fontSize: 18, color: meta.color }}>{meta.icon}</span>
                            <span style={{ flex: 1, fontSize: 13, fontWeight: 600, color: TEXT }}>{meta.label}</span>
                            <span style={{ fontSize: 13, color: MUTED, fontWeight: 600 }}>{r.total}</span>
                            <span style={{ fontSize: 11, background: meta.color + '18', color: meta.color, padding: '2px 8px', borderRadius: 20, fontWeight: 700 }}>{pct}%</span>
                        </div>
                        <div style={{ background: BGGRAY, borderRadius: 20, height: 8 }}>
                            <div style={{
                                width: pct + '%', height: '100%',
                                background: `linear-gradient(90deg, ${ORG}, ${ORGL})`,
                                borderRadius: 20,
                                minWidth: pct > 0 ? 4 : 0,
                                transition: 'width .5s ease',
                            }} />
                        </div>
                        {r.conversions > 0 && (
                            <div style={{ fontSize: 11, color: MUTED, marginTop: 3 }}>
                                {r.conversions} converted · {convPct}% conversion rate
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

// ─── Quota breakdown ──────────────────────────────────────────────────────────
const QUOTA_META = {
    management:  { label: 'Management Quota',  color: '#f59e0b', icon: 'business_center' },
    counselling: { label: 'Counselling Quota', color: '#8b5cf6', icon: 'school' },
};

function QuotaBreakdown({ rows }) {
    const total = rows.reduce((s, r) => s + r.total, 0);
    return (
        <div style={{ ...card(), padding: '20px 22px', height: '100%' }}>
            <SectionTitle icon="category" title="Quota Breakdown" right={total > 0 ? `${total} categorised` : ''} />
            {total === 0 ? (
                <div style={{ textAlign: 'center', padding: '30px 0', color: MUTED, fontSize: 13 }}>No quota data for this period</div>
            ) : rows.map((r, i) => {
                const meta    = QUOTA_META[r.quota] || { label: r.quota, color: MUTED, icon: 'label' };
                const pct     = total > 0 ? Math.round((r.total / total) * 100) : 0;
                const convPct = r.total > 0 ? Math.round((r.conversions / r.total) * 100) : 0;
                return (
                    <div key={i} style={{
                        marginBottom: 16, background: meta.color + '0d',
                        borderRadius: 12, padding: '14px 16px',
                        borderLeft: `3px solid ${meta.color}`,
                    }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 10 }}>
                            <span className="material-icons" style={{ fontSize: 18, color: meta.color }}>{meta.icon}</span>
                            <span style={{ flex: 1, fontSize: 13, fontWeight: 700, color: TEXT }}>{meta.label}</span>
                            <span style={{ fontSize: 20, fontWeight: 800, color: meta.color }}>{r.total}</span>
                        </div>
                        <div style={{ display: 'flex', gap: 20, flexWrap: 'wrap' }}>
                            {[
                                { label: 'Converted',  val: r.conversions, color: '#10b981' },
                                { label: 'Conv. Rate', val: convPct + '%', color: meta.color },
                                { label: 'Share',      val: pct + '%',     color: MUTED },
                            ].map(item => (
                                <div key={item.label}>
                                    <div style={{ fontSize: 10, color: MUTED, textTransform: 'uppercase', letterSpacing: .5 }}>{item.label}</div>
                                    <div style={{ fontSize: 16, fontWeight: 700, color: item.color }}>{item.val}</div>
                                </div>
                            ))}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

// ─── Converted leads table ────────────────────────────────────────────────────
function ConvertedLeadsTable({ leads }) {
    return (
        <div style={{ ...card(), overflow: 'hidden', marginBottom: 24 }}>
            <div style={{ padding: '18px 22px', borderBottom: `1px solid ${BORDER}`, display: 'flex', alignItems: 'center', gap: 8 }}>
                <div style={{ width: 30, height: 30, borderRadius: '50%', background: ORG, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <span className="material-icons" style={{ fontSize: 16, color: WHITE }}>check_circle</span>
                </div>
                <span style={{ fontWeight: 700, fontSize: 15, color: TEXT }}>Converted Lead Details</span>
                <span style={{ marginLeft: 'auto', fontSize: 12, color: MUTED }}>{leads.length} lead{leads.length !== 1 ? 's' : ''}</span>
            </div>
            {leads.length === 0 ? (
                <div style={{ padding: '36px 22px', textAlign: 'center', color: MUTED, fontSize: 13 }}>
                    <span className="material-icons" style={{ fontSize: 32, display: 'block', marginBottom: 8, color: BORDER }}>emoji_events</span>
                    No conversions in this period
                </div>
            ) : (
                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ background: BGGRAY, position: 'sticky', top: 0, zIndex: 1 }}>
                                {['Lead', 'Code', 'Gender', 'Enquired Course', 'Final Course', 'Quota', 'Date'].map(h => (
                                    <th key={h} style={{ padding: '10px 16px', textAlign: 'left', fontSize: 11, fontWeight: 700, color: MUTED, textTransform: 'uppercase', letterSpacing: .6, whiteSpace: 'nowrap', borderBottom: `1px solid ${BORDER}` }}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {leads.map((l, i) => (
                                <tr key={i}
                                    style={{ borderTop: `1px solid ${BORDER}`, transition: 'background .15s' }}
                                    onMouseEnter={e => e.currentTarget.style.background = BGGRAY}
                                    onMouseLeave={e => e.currentTarget.style.background = 'transparent'}
                                >
                                    <td style={{ padding: '12px 16px' }}>
                                        <a href={`/telecaller/leads/${l.encrypted_id}`}
                                            style={{ fontWeight: 600, color: ORG, textDecoration: 'none', fontSize: 13 }}>
                                            {l.name}
                                        </a>
                                    </td>
                                    <td style={{ padding: '12px 16px', fontSize: 12, color: MUTED, fontFamily: 'monospace' }}>{l.lead_code}</td>
                                    <td style={{ padding: '12px 16px', fontSize: 13, color: TEXT, textTransform: 'capitalize' }}>
                                        {l.gender || '-'}
                                    </td>
                                    <td style={{ padding: '12px 16px', fontSize: 13, color: TEXT }}>{l.enquired_course}</td>
                                    <td style={{ padding: '12px 16px', fontSize: 13 }}>
                                        {l.final_course !== '-'
                                            ? <span style={{ fontWeight: 600, color: '#10b981' }}>{l.final_course}</span>
                                            : <span style={{ color: MUTED }}>-</span>
                                        }
                                    </td>
                                    <td style={{ padding: '12px 16px' }}>
                                        {l.quota ? (
                                            <span style={{
                                                fontSize: 11, fontWeight: 700, padding: '2px 8px', borderRadius: 20,
                                                background: l.quota === 'management' ? '#f59e0b18' : '#8b5cf618',
                                                color: l.quota === 'management' ? '#f59e0b' : '#8b5cf6',
                                                textTransform: 'capitalize',
                                            }}>{l.quota}</span>
                                        ) : <span style={{ color: MUTED, fontSize: 13 }}>-</span>}
                                    </td>
                                    <td style={{ padding: '12px 16px', fontSize: 12, color: MUTED, whiteSpace: 'nowrap' }}>{l.created_at}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

// ─── Main ─────────────────────────────────────────────────────────────────────
export default function Index({
    title, scope, period,
    callsHandled, talkTimeLabel, talkMinutes, avgCallDuration,
    conversionPercent, totalAssigned,
    followupsCompleted, followupsScheduled, followupCompletionRate, pendingFollowups,
    responseTimeLabel,
    missedCalls, missedRate,
    waSent, waReceived, waTotal,
    inboundCount, outboundCount, inboundTalkSecs, outboundTalkSecs,
    outcomeBreakdown, leadStatusRows,
    dailyBreakdown, hourlyBreakdown, bestDay,
    productivityScore,
    trends, prevPeriodLabel,
    callTarget, callTargetPct, uniqueLeadsCalled, totalLeadsEver,
    dateFrom, dateTo,
    courseWiseRows = [], finalCourseRows = [], genderRows = [],
    quotaRows = [], convertedLeadsList = [],
}) {
    const { grade, label: gradeLabel, color: gradeColor } = scoreGrade(productivityScore);
    const totalOutcomeCalls = Object.values(outcomeBreakdown).reduce((a, b) => a + b, 0);
    const totalLeads = Object.values(leadStatusRows).reduce((a, b) => a + b, 0);

    const [lastUpdated,       setLastUpdated]       = useState(() => new Date());
    const [refreshing,        setRefreshing]        = useState(false);
    const [showCustomPicker,  setShowCustomPicker]  = useState(scope === 'custom');
    const [customFrom,        setCustomFrom]        = useState(dateFrom || '');
    const [customTo,          setCustomTo]          = useState(dateTo   || '');

    const applyCustomRange = () => {
        if (!customFrom || !customTo) return;
        router.visit(`/telecaller/performance/custom?date_from=${customFrom}&date_to=${customTo}`);
    };

    useEffect(() => {
        if (scope !== 'daily') return;
        const INTERVAL = 60_000;

        const tick = () => {
            if (document.hidden) return;
            setRefreshing(true);
            router.reload({
                preserveScroll: true,
                onFinish: () => { setRefreshing(false); setLastUpdated(new Date()); },
            });
        };

        const id = setInterval(tick, INTERVAL);
        return () => clearInterval(id);
    }, [scope]);

    return (
        <>
            <Head title={title} />

            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Work+Sans:wght@300;400;500;600;700;800;900&display=swap');
                *, *::before, *::after { font-family: 'Work Sans', sans-serif !important; }
                @keyframes livePulse {
                    0%, 100% { opacity: 1; transform: scale(1); }
                    50%       { opacity: .7; transform: scale(1.15); }
                }
            `}</style>

            {/* ── Hero header ────────────────────────────────────────────── */}
            <div style={{
                background: `linear-gradient(135deg, ${DARKC} 0%, #2D2D4E 100%)`,
                borderRadius: 20, padding: '24px 28px', marginBottom: 24,
                color: WHITE, position: 'relative', overflow: 'hidden',
            }}>
                {/* Decorative blobs */}
                <div style={{
                    position: 'absolute', top: -40, right: -40,
                    width: 160, height: 160, borderRadius: '50%',
                    background: 'rgba(255,255,255,.06)',
                }} />
                <div style={{
                    position: 'absolute', bottom: -30, right: 80,
                    width: 100, height: 100, borderRadius: '50%',
                    background: `${ORG}22`,
                }} />

                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 16 }}>
                    <div>
                        <div style={{ fontSize: 12, fontWeight: 600, opacity: .7, textTransform: 'uppercase', letterSpacing: 1, marginBottom: 6 }}>
                            My Performance
                        </div>
                        <h1 style={{ fontSize: 26, fontWeight: 800, margin: 0, marginBottom: 6 }}>{title}</h1>
                        <p style={{ margin: 0, opacity: .7, fontSize: 13 }}>{period}</p>

                        {/* Live indicator */}
                        {scope === 'daily' && (
                            <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 8 }}>
                                <span style={{
                                    width: 8, height: 8, borderRadius: '50%',
                                    background: refreshing ? '#f59e0b' : '#10b981',
                                    boxShadow: refreshing ? '0 0 0 3px rgba(245,158,11,.3)' : '0 0 0 3px rgba(16,185,129,.3)',
                                    display: 'inline-block', flexShrink: 0,
                                    animation: 'livePulse 2s ease-in-out infinite',
                                }} />
                                <span style={{ fontSize: 11, opacity: .85 }}>
                                    {refreshing ? 'Refreshing…' : `Live · updated ${lastUpdated.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`}
                                </span>
                            </div>
                        )}
                    </div>

                    {/* Score badge */}
                    <div style={{
                        background: 'rgba(255,255,255,.10)', backdropFilter: 'blur(10px)',
                        border: `1px solid rgba(255,255,255,.15)`,
                        borderRadius: 16, padding: '16px 22px',
                        textAlign: 'center', minWidth: 120,
                    }}>
                        {/* Grade letter */}
                        <div style={{ fontSize: 48, fontWeight: 900, lineHeight: 1, color: gradeColor }}>
                            {grade}
                        </div>
                        <div style={{ fontSize: 28, fontWeight: 800, lineHeight: 1, marginTop: 2 }}>{productivityScore}</div>
                        <div style={{ fontSize: 11, opacity: .7, marginTop: 2 }}>/ 100 score</div>
                        <div style={{
                            marginTop: 6, background: gradeColor,
                            borderRadius: 20, padding: '2px 12px',
                            fontSize: 11, fontWeight: 700, display: 'inline-block', color: WHITE,
                        }}>{gradeLabel}</div>
                    </div>
                </div>

                {/* Tab bar + export */}
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', marginTop: 20, flexWrap: 'wrap', gap: 10 }}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                        {/* Pill-style tab bar */}
                        <div style={{
                            display: 'inline-flex', background: WHITE, borderRadius: 12,
                            padding: 4, gap: 2, flexWrap: 'wrap', alignItems: 'center',
                        }}>
                            {TABS.map(tab => (
                                <Link key={tab.key} href={tab.href} style={{
                                    padding: '6px 18px', borderRadius: 10, fontSize: 13, fontWeight: 600,
                                    textDecoration: 'none', transition: 'all .2s',
                                    background: scope === tab.key ? ORG : 'transparent',
                                    color: scope === tab.key ? WHITE : MUTED,
                                }}>
                                    {tab.label}
                                </Link>
                            ))}
                            <button
                                onClick={() => setShowCustomPicker(v => !v)}
                                style={{
                                    padding: '6px 18px', borderRadius: 10, fontSize: 13, fontWeight: 600,
                                    background: scope === 'custom' ? ORG : 'transparent',
                                    color: scope === 'custom' ? WHITE : MUTED,
                                    border: 'none', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 4,
                                }}>
                                <span className="material-icons" style={{ fontSize: 14 }}>date_range</span>
                                {scope === 'custom' ? period : 'Custom Range'}
                            </button>
                        </div>

                        {/* Custom date picker */}
                        {showCustomPicker && (
                            <div style={{ display: 'flex', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
                                <input
                                    type="date" value={customFrom}
                                    onChange={e => setCustomFrom(e.target.value)}
                                    style={{ borderRadius: 8, border: '1px solid rgba(255,255,255,.4)', background: 'rgba(255,255,255,.12)', color: WHITE, padding: '5px 10px', fontSize: 12, colorScheme: 'dark' }}
                                />
                                <span style={{ color: 'rgba(255,255,255,.7)', fontSize: 12 }}>to</span>
                                <input
                                    type="date" value={customTo}
                                    onChange={e => setCustomTo(e.target.value)}
                                    style={{ borderRadius: 8, border: '1px solid rgba(255,255,255,.4)', background: 'rgba(255,255,255,.12)', color: WHITE, padding: '5px 10px', fontSize: 12, colorScheme: 'dark' }}
                                />
                                <button
                                    onClick={applyCustomRange}
                                    disabled={!customFrom || !customTo}
                                    style={{
                                        padding: '5px 14px', borderRadius: 8, fontSize: 12, fontWeight: 700,
                                        background: ORG, color: WHITE, border: 'none', cursor: 'pointer',
                                        opacity: (!customFrom || !customTo) ? .5 : 1,
                                    }}>
                                    Apply
                                </button>
                            </div>
                        )}
                    </div>

                    {/* Export dropdown */}
                    <div className="dropdown">
                        <button
                            type="button"
                            className="btn btn-sm d-flex align-items-center gap-1"
                            style={{ background: ORG, color: WHITE, border: 'none', borderRadius: 99, padding: '6px 16px', fontSize: 13, fontWeight: 600 }}
                            data-bs-toggle="dropdown"
                        >
                            <span className="material-icons" style={{ fontSize: 15 }}>download</span>
                            Export
                        </button>
                        <ul className="dropdown-menu dropdown-menu-end">
                            <li>
                                <a className="dropdown-item d-flex align-items-center gap-2"
                                    href={`/telecaller/performance/${scope}/export?format=excel${scope === 'custom' ? `&date_from=${dateFrom}&date_to=${dateTo}` : ''}`}
                                    target="_blank" rel="noreferrer">
                                    <span className="material-icons" style={{ fontSize: 16, color: '#10b981' }}>table_view</span>
                                    Excel (.xlsx)
                                </a>
                            </li>
                            <li>
                                <a className="dropdown-item d-flex align-items-center gap-2"
                                    href={`/telecaller/performance/${scope}/export?format=pdf${scope === 'custom' ? `&date_from=${dateFrom}&date_to=${dateTo}` : ''}`}
                                    target="_blank" rel="noreferrer">
                                    <span className="material-icons" style={{ fontSize: 16, color: '#ef4444' }}>picture_as_pdf</span>
                                    PDF
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            {/* ── KPI cards ──────────────────────────────────────────────── */}
            <div className="row g-3 mb-4">
                <KpiCard
                    icon="call"
                    label="Calls Handled"
                    value={callsHandled}
                    sub={`Avg duration: ${avgCallDuration}`}
                    trend={trends?.calls} prevLabel={prevPeriodLabel}
                    targetBar={totalLeadsEver > 0
                        ? { current: uniqueLeadsCalled, target: totalLeadsEver, pct: callTargetPct }
                        : null
                    }
                    noLeads={totalLeadsEver === 0}
                />
                <KpiCard
                    icon="timer"
                    label="Total Talk Time"
                    value={talkTimeLabel}
                    sub={`${talkMinutes} minutes on calls`}
                    trend={trends?.talkTime} prevLabel={prevPeriodLabel}
                />
                <KpiCard
                    icon="trending_up"
                    label="Conversion Rate"
                    value={`${conversionPercent}%`}
                    sub={`${totalAssigned} leads assigned`}
                    badge={conversionPercent > 0 ? `${conversionPercent}%` : null}
                    badgeColor="#10b981"
                    trend={trends?.conversion} prevLabel={prevPeriodLabel}
                />
                <KpiCard
                    icon="task_alt"
                    label="Followups Done"
                    value={followupsCompleted}
                    sub={
                        followupsScheduled > 0
                            ? `of ${followupsScheduled} scheduled · ${followupCompletionRate}%${pendingFollowups > 0 ? ` · ⚠ ${pendingFollowups} overdue` : ''}`
                            : pendingFollowups > 0 ? `⚠ ${pendingFollowups} overdue` : 'None scheduled'
                    }
                    badge={pendingFollowups > 0 ? `${pendingFollowups} overdue` : null}
                    badgeColor="#ef4444"
                    trend={trends?.followups} prevLabel={prevPeriodLabel}
                />
                <KpiCard
                    icon="speed"
                    label="Avg Response Time"
                    value={responseTimeLabel}
                    sub="From lead assignment to first contact"
                />
                <KpiCard
                    icon="phone_missed"
                    label="Missed Calls"
                    value={missedCalls}
                    sub={inboundCount > 0 ? `${missedRate}% of inbound calls` : 'No inbound calls'}
                    badge={missedCalls > 0 ? `${missedRate}%` : null}
                    badgeColor="#ef4444"
                    trend={trends?.missedCalls} trendGoodDir="down" prevLabel={prevPeriodLabel}
                />
                {bestDay && scope !== 'daily' && (
                    <KpiCard
                        icon="star"
                        label="Best Day"
                        value={bestDay.calls + ' calls'}
                        sub={bestDay.day}
                        badge="Peak"
                        badgeColor="#f59e0b"
                    />
                )}
            </div>

            {/* ── Inbound vs Outbound ────────────────────────────────────── */}
            <DirectionSplit
                inbound={inboundCount}
                outbound={outboundCount}
                inboundSecs={inboundTalkSecs}
                outboundSecs={outboundTalkSecs}
            />

            {/* ── WhatsApp activity ───────────────────────────────────────── */}
            <WhatsAppActivity sent={waSent} received={waReceived} total={waTotal}
                trend={trends?.waMessages} prevLabel={prevPeriodLabel} />

            {/* ── Middle row: Outcomes + Lead pipeline ───────────────────── */}
            <div className="row g-3 mb-4">
                {/* Outcome breakdown */}
                <div className="col-md-6">
                    <div style={{ ...card(), padding: '20px 22px', height: '100%' }}>
                        <SectionTitle
                            icon="donut_large"
                            title="Call Outcomes"
                            right={`${totalOutcomeCalls} classified`}
                        />
                        {totalOutcomeCalls === 0 ? (
                            <div style={{ textAlign: 'center', padding: '30px 0', color: MUTED, fontSize: 13 }}>
                                <span className="material-icons" style={{ fontSize: 36, display: 'block', marginBottom: 8, color: BORDER }}>call_end</span>
                                No outcome data yet
                            </div>
                        ) : (
                            Object.entries(outcomeBreakdown).map(([key, count]) => {
                                const params = new URLSearchParams({ outcome: key, date_from: dateFrom, date_to: dateTo });
                                return (
                                    <OutcomeRow
                                        key={key}
                                        outcome={key}
                                        count={count}
                                        total={totalOutcomeCalls}
                                        drilldownHref={count > 0 ? `/telecaller/calls/history?${params}` : null}
                                    />
                                );
                            })
                        )}
                    </div>
                </div>

                {/* Lead pipeline */}
                <div className="col-md-6">
                    <div style={{ ...card(), padding: '20px 22px', height: '100%' }}>
                        <SectionTitle
                            icon="account_tree"
                            title="My Lead Pipeline"
                            right={`${totalLeads} total`}
                        />
                        {totalLeads === 0 ? (
                            <div style={{ textAlign: 'center', padding: '30px 0', color: MUTED, fontSize: 13 }}>
                                <span className="material-icons" style={{ fontSize: 36, display: 'block', marginBottom: 8, color: BORDER }}>person_search</span>
                                No leads assigned yet
                            </div>
                        ) : (
                            Object.entries(leadStatusRows).map(([status, count]) => {
                                const meta = STATUS_META[status] || { label: status, color: MUTED };
                                const pct  = Math.round((count / totalLeads) * 100);
                                return (
                                    <div key={status} style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 12 }}>
                                        <div style={{
                                            width: 10, height: 10, borderRadius: '50%',
                                            background: meta.color, flexShrink: 0,
                                        }} />
                                        <span style={{ flex: 1, fontSize: 13, color: TEXT, fontWeight: 500 }}>{meta.label}</span>
                                        <div style={{ width: 90, background: BGGRAY, borderRadius: 20, height: 8 }}>
                                            <div style={{
                                                width: pct + '%', height: '100%',
                                                background: `linear-gradient(90deg, ${ORG}, ${ORGL})`,
                                                borderRadius: 20,
                                                minWidth: pct > 0 ? 4 : 0,
                                            }} />
                                        </div>
                                        <span style={{ fontSize: 12, color: MUTED, minWidth: 28, textAlign: 'right' }}>{count}</span>
                                    </div>
                                );
                            })
                        )}
                    </div>
                </div>
            </div>

            {/* ── Course-wise enquiry + Final course ─────────────────────── */}
            <div className="row g-3 mb-4">
                <div className="col-md-6">
                    <CourseBreakdown
                        rows={courseWiseRows}
                        title="Enquired Course Breakdown"
                        icon="menu_book"
                        valueKey="enquiries"
                        emptyMsg="No course enquiry data for this period"
                    />
                </div>
                <div className="col-md-6">
                    <CourseBreakdown
                        rows={finalCourseRows}
                        title="Final Selected Course"
                        icon="verified"
                        valueKey="count"
                        emptyMsg="No conversions with final course data"
                    />
                </div>
            </div>

            {/* ── Gender + Quota breakdown ────────────────────────────────── */}
            <div className="row g-3 mb-4">
                <div className="col-md-6">
                    <GenderBreakdown rows={genderRows} />
                </div>
                <div className="col-md-6">
                    <QuotaBreakdown rows={quotaRows} />
                </div>
            </div>

            {/* ── Converted leads detail table ────────────────────────────── */}
            <ConvertedLeadsTable leads={convertedLeadsList} />

            {/* ── Hourly heatmap (daily) / Calls-per-day line (weekly+monthly) */}
            <div style={{ ...card(), padding: '20px 22px', marginBottom: 24 }}>
                {scope === 'daily' ? (
                    <>
                        <SectionTitle icon="bar_chart" title="Call Activity by Hour" right="8AM – 8PM" />
                        {callsHandled === 0
                            ? <div style={{ textAlign: 'center', padding: '20px 0', color: MUTED, fontSize: 13 }}>No call activity for this period</div>
                            : <HourlyChart hourlyBreakdown={hourlyBreakdown} />
                        }
                    </>
                ) : (
                    <>
                        <SectionTitle
                            icon="show_chart"
                            title="Calls per Day"
                            right={`${dailyBreakdown.length} day${dailyBreakdown.length !== 1 ? 's' : ''}`}
                        />
                        <DailyLineChart dailyBreakdown={dailyBreakdown} scope={scope} />
                    </>
                )}
            </div>

            {/* ── Daily breakdown table ───────────────────────────────────── */}
            <div style={{ ...card(), overflow: 'hidden' }}>
                <div style={{ padding: '18px 22px', borderBottom: `1px solid ${BORDER}`, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <div style={{ width: 30, height: 30, borderRadius: '50%', background: ORG, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                            <span className="material-icons" style={{ fontSize: 16, color: WHITE }}>calendar_today</span>
                        </div>
                        <span style={{ fontWeight: 700, fontSize: 15, color: TEXT }}>Call Activity Breakdown</span>
                    </div>
                    <span style={{ fontSize: 12, color: MUTED }}>{dailyBreakdown.length} day(s)</span>
                </div>
                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ background: BGGRAY, position: 'sticky', top: 0, zIndex: 1 }}>
                                {['Date', 'Calls', 'Talk Time', 'Avg/Call'].map(h => (
                                    <th key={h} style={{ padding: '10px 22px', textAlign: 'left', fontSize: 11, fontWeight: 700, color: MUTED, textTransform: 'uppercase', letterSpacing: .6, whiteSpace: 'nowrap', borderBottom: `1px solid ${BORDER}` }}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {dailyBreakdown.length === 0 ? (
                                <tr>
                                    <td colSpan={4} style={{ padding: '36px 22px', textAlign: 'center', color: MUTED, fontSize: 13 }}>
                                        <span className="material-icons" style={{ fontSize: 32, display: 'block', marginBottom: 8, color: BORDER }}>event_busy</span>
                                        No activity for this period
                                    </td>
                                </tr>
                            ) : dailyBreakdown.map((row, i) => {
                                const avgSec = row.answered_calls > 0 ? Math.round(row.talk_secs / row.answered_calls) : 0;
                                const avgFmt = gmdate(avgSec);
                                return (
                                    <tr key={i}
                                        style={{ borderTop: `1px solid ${BORDER}`, transition: 'background .15s' }}
                                        onMouseEnter={e => e.currentTarget.style.background = BGGRAY}
                                        onMouseLeave={e => e.currentTarget.style.background = 'transparent'}
                                    >
                                        <td style={{ padding: '13px 22px', fontWeight: 600, color: TEXT, fontSize: 13 }}>{row.day}</td>
                                        <td style={{ padding: '13px 22px' }}>
                                            <span style={{
                                                background: ORG + '18', color: ORG,
                                                fontWeight: 700, fontSize: 13,
                                                padding: '3px 10px', borderRadius: 20,
                                            }}>{row.calls}</span>
                                        </td>
                                        <td style={{ padding: '13px 22px', fontSize: 13, color: TEXT, fontFamily: 'monospace' }}>{row.talk_time}</td>
                                        <td style={{ padding: '13px 22px', fontSize: 13, color: MUTED, fontFamily: 'monospace' }}>{avgFmt}</td>
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
