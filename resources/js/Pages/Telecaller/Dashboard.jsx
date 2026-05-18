import { Head, Link } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';

/* ── helpers ─────────────────────────────────────────────────────────────── */
function pad(n) { return String(n).padStart(2, '0'); }

const MONTH_NAMES = ['January','February','March','April','May','June',
    'July','August','September','October','November','December'];
const DOW_CAL = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

function useLiveClock() {
    const [now, setNow] = useState(new Date());
    useEffect(() => {
        const t = setInterval(() => setNow(new Date()), 1000);
        return () => clearInterval(t);
    }, []);
    const h = now.getHours(), ampm = h >= 12 ? 'PM' : 'AM', h12 = h % 12 || 12;
    return `${pad(h12)}:${pad(now.getMinutes())}:${pad(now.getSeconds())} ${ampm}`;
}

/* ── design tokens ───────────────────────────────────────────────────────── */
const ORG    = '#FF5C1A';
const ORGL   = '#FF8042';
const DARKC  = '#1A1A2E';
const BORDER = '#EAEAED';
const TEXT   = '#1A1A2E';
const MUTED  = '#9EA3B0';
const WHITE  = '#FFFFFF';
const BGGRAY = '#F5F5F7';

/* chevron-down arrow for <select> as inline data URI */
const SEL_ARROW = `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24'%3E%3Cpath fill='%239EA3B0' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E") no-repeat right 8px center`;

const card = (extra = {}) => ({
    background: WHITE,
    borderRadius: 14,
    boxShadow: '0 2px 16px rgba(0,0,0,0.07)',
    border: `1px solid ${BORDER}`,
    ...extra,
});

const selStyle = (extra = {}) => ({
    fontSize: 12, fontWeight: 600, color: TEXT,
    border: `1px solid ${BORDER}`, borderRadius: 8,
    padding: '7px 28px 7px 12px',
    background: `${WHITE} ${SEL_ARROW}`,
    cursor: 'pointer', appearance: 'none', WebkitAppearance: 'none',
    ...extra,
});

/* ─────────────────────────────────────────────────────────────────────────── */
/*  SVG Donut                                                                  */
/*  - 3px gap between segments                                                 */
/*  - hover: hovered segment keeps full opacity, others dim to 0.35           */
/*  - strokeDashoffset = -cumLen (correct formula)                             */
/* ─────────────────────────────────────────────────────────────────────────── */
function Donut({ data, size, stroke, center }) {
    const [hovIdx, setHovIdx] = useState(-1);
    const r     = (size - stroke) / 2;
    const circ  = 2 * Math.PI * r;
    const total = data.reduce((s, d) => s + d.v, 0);
    const nSegs = data.filter(d => d.v > 0).length;
    const GAP   = nSegs > 1 ? 3 : 0;
    let cumLen  = 0;

    return (
        <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`}>
            {/* Background track */}
            <circle cx={size/2} cy={size/2} r={r}
                fill="none" stroke="#EFEFEF" strokeWidth={stroke} />

            {data.map((d, i) => {
                if (!d.v || d.v <= 0) return null;
                const segLen  = (d.v / total) * circ;
                const dashLen = Math.max(0, segLen - GAP);
                const offset  = -cumLen;
                cumLen += segLen;

                const isHov = hovIdx === i;
                const isDim = hovIdx !== -1 && !isHov;

                return (
                    <circle key={i}
                        cx={size/2} cy={size/2} r={r}
                        fill="none"
                        stroke={d.c}
                        strokeWidth={isHov ? stroke + 1 : stroke - 2}
                        strokeDasharray={`${dashLen.toFixed(2)} ${(circ - dashLen).toFixed(2)}`}
                        strokeDashoffset={offset.toFixed(2)}
                        transform={`rotate(-90 ${size/2} ${size/2})`}
                        opacity={isDim ? 0.35 : 1}
                        style={{ cursor: 'pointer', transition: 'stroke-width 0.18s ease, opacity 0.18s ease' }}
                        onMouseEnter={() => setHovIdx(i)}
                        onMouseLeave={() => setHovIdx(-1)}
                    />
                );
            })}

            {center && <>
                <text
                    x={size/2} y={center.sub ? size/2 - 5 : size/2 + 7}
                    textAnchor="middle"
                    fontSize={center.valSize || 22}
                    fontWeight="800" fill={TEXT}
                    fontFamily="Work Sans, sans-serif">
                    {center.val}
                </text>
                {center.sub && (
                    <text x={size/2} y={size/2 + 15}
                        textAnchor="middle" fontSize="10"
                        fill={MUTED} fontFamily="Work Sans, sans-serif">
                        {center.sub}
                    </text>
                )}
            </>}
        </svg>
    );
}

/* ─────────────────────────────────────────────────────────────────────────── */
/*  Calls Heatmap — CSS grid with PIXEL-SIZED inner squares                    */
/*  Squares use px not % so they actually render at the right size.            */
/* ─────────────────────────────────────────────────────────────────────────── */
const TIMES = ['18:00pm','16:00pm','14:00pm','12:00pm','10:00am','8:00am'];
const DAYS  = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

function seededRng(seed) {
    let s = ((seed || 0) + 9973) | 0;
    return () => {
        s = Math.imul(s, 1664525) + 1013904223 | 0;
        return (s >>> 0) / 4294967296;
    };
}

function buildGrid(seed) {
    const rng = seededRng(seed);
    return TIMES.map(() =>
        DAYS.map(() => {
            const r = rng();
            if (r < 0.32) return null;
            const type = r < 0.44 ? 'holiday' : r < 0.56 ? 'waiting' : 'assigned';
            return { type, sz: Math.round(10 + rng() * 18) }; // px: 10–28
        })
    );
}

/* Individual heatmap cell — solid tinted background, no inner square */
function HeatmapCell({ cell, cellH }) {
    const [hov, setHov] = useState(false);

    const color = cell
        ? cell.type === 'assigned' ? ORG
        : cell.type === 'waiting'  ? '#D4A000'
        : '#ABABBE'
        : null;

    const bg = cell
        ? cell.type === 'assigned' ? `${ORG}28`
        : cell.type === 'waiting'  ? 'rgba(212,160,0,0.20)'
        : 'rgba(171,171,190,0.22)'
        : BGGRAY;

    const bgHov = cell
        ? cell.type === 'assigned' ? `${ORG}42`
        : cell.type === 'waiting'  ? 'rgba(212,160,0,0.34)'
        : 'rgba(171,171,190,0.36)'
        : '#ECEEF2';

    return (
        <div
            onMouseEnter={() => setHov(true)}
            onMouseLeave={() => setHov(false)}
            style={{
                height: cellH, borderRadius: 8,
                background: hov ? bgHov : bg,
                border: `1.5px solid ${color ? (hov ? color + '55' : color + '22') : BORDER}`,
                cursor: cell ? 'pointer' : 'default',
                transition: 'background 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease',
                boxShadow: hov && cell ? `0 3px 12px ${color}30` : 'none',
            }}
        />
    );
}

function CallsHeatmap({ seed }) {
    const grid  = buildGrid(seed);
    const COLS  = '70px repeat(7, 1fr)';
    const CELL_H = 42;

    return (
        <div style={{ overflowX: 'auto' }}>
            <div style={{ minWidth: 440 }}>

                {/* ── Time rows (day labels moved to BOTTOM — no awkward top gap) ── */}
                {TIMES.map((t, ti) => (
                    <div key={t} style={{ display: 'grid', gridTemplateColumns: COLS, gap: '6px', marginBottom: 6 }}>
                        {/* Time label — LEFT column */}
                        <div style={{
                            height: CELL_H,
                            display: 'flex', alignItems: 'center', justifyContent: 'flex-end',
                            paddingRight: 10, fontSize: 10, color: MUTED, whiteSpace: 'nowrap',
                        }}>
                            {t}
                        </div>
                        {/* Day cells */}
                        {grid[ti].map((cell, di) => (
                            <HeatmapCell key={di} cell={cell} cellH={CELL_H} />
                        ))}
                    </div>
                ))}

                {/* ── Day-of-week labels at BOTTOM ── */}
                <div style={{ display: 'grid', gridTemplateColumns: COLS, gap: '6px', marginTop: 8 }}>
                    <div />{/* spacer aligns with time-label column */}
                    {DAYS.map(d => (
                        <div key={d} style={{ textAlign: 'center', fontSize: 11, fontWeight: 600, color: MUTED }}>
                            {d}
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

/* ─────────────────────────────────────────────────────────────────────────── */
/*  Lead Pipeline Donut + Legend                                               */
/* ─────────────────────────────────────────────────────────────────────────── */
const OUTCOME_CFG = {
    interested:      { label: 'Interested',      c: '#C8CAD4' },
    not_interested:  { label: 'Not Interested',  c: '#B0B3BF' },
    wrong_number:    { label: 'Wrong Number',    c: ORG       },
    call_back_later: { label: 'Call Back Later', c: '#E8660A' },
    switched_off:    { label: 'Switched Off',    c: '#C04000' },
};

function LeadPipeline({ outcomes }) {
    const entries = Object.entries(outcomes ?? {}).filter(([, v]) => v > 0);
    const total   = entries.reduce((s, [, v]) => s + Number(v), 0);
    const data    = entries.length > 0
        ? entries.map(([k, v]) => ({ v: Number(v), c: OUTCOME_CFG[k]?.c ?? '#64748b', label: OUTCOME_CFG[k]?.label ?? k }))
        : [{ v: 1, c: '#E8E8EC', label: '' }];

    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 22 }}>
            <div style={{ flexShrink: 0 }}>
                <Donut data={data} size={130} stroke={22}
                    center={{ val: entries.length > 0 ? total : 0, sub: 'Leads', valSize: 24 }} />
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>
                {entries.length === 0
                    ? <div style={{ fontSize: 12, color: MUTED }}>No call data yet</div>
                    : entries.map(([k, v]) => {
                        const cfg = OUTCOME_CFG[k] ?? { label: k, c: '#64748b' };
                        return (
                            <div key={k} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 9 }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12, color: TEXT, minWidth: 0 }}>
                                    <div style={{ width: 10, height: 10, borderRadius: 3, background: cfg.c, flexShrink: 0 }} />
                                    <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{cfg.label}</span>
                                </div>
                                <span style={{ fontSize: 12, fontWeight: 700, color: TEXT, marginLeft: 10, flexShrink: 0 }}>{v}</span>
                            </div>
                        );
                    })
                }
                {entries.length > 0 && (
                    <div style={{ paddingTop: 9, borderTop: `1px solid ${BORDER}`, display: 'flex', justifyContent: 'space-between', fontSize: 12 }}>
                        <span style={{ color: MUTED }}>Total Calls</span>
                        <span style={{ fontWeight: 700, color: TEXT }}>{total}</span>
                    </div>
                )}
            </div>
        </div>
    );
}

/* ─────────────────────────────────────────────────────────────────────────── */
/*  Weekly Target — tabs LEFT, SVG line chart RIGHT (Y-axis inside SVG)        */
/* ─────────────────────────────────────────────────────────────────────────── */
function WeeklyTarget({ stats }) {
    const [tab,    setTab]    = useState(0);
    const [hovDot, setHovDot] = useState(-1);
    const TABS = ['Total Calls','Overall Success Rate','New Leads Generated','Missed Call Reduction'];

    const pts    = [18, 42, 28, 60, 33, 78, 50, 68, 39, 85, 55, 62];
    const CW = 200, CH = 88;
    const maxV   = Math.max(...pts);
    const coords = pts.map((p, i) => [
        +((i / (pts.length - 1)) * CW).toFixed(1),
        +((CH - (p / maxV) * CH)).toFixed(1),
    ]);
    const lineD = coords.map(([x, y], i) => `${i === 0 ? 'M' : 'L'}${x},${y}`).join(' ');
    const areaD = `M0,${CH} ` + coords.map(([x, y]) => `L${x},${y}`).join(' ') + ` L${CW},${CH} Z`;

    return (
        <div style={{ display: 'flex', gap: 14, minHeight: 185 }}>

            {/* ── Tab column ── */}
            <div style={{ display: 'flex', flexDirection: 'column', gap: 6, width: '52%', flexShrink: 0 }}>
                {TABS.map((label, i) => (
                    <button key={i} onClick={() => setTab(i)} style={{
                        display: 'flex', alignItems: 'center', gap: 10,
                        padding: '10px 12px', borderRadius: 10, border: 'none',
                        cursor: 'pointer', textAlign: 'left', width: '100%',
                        background: tab === i ? `${ORG}28` : 'rgba(255,255,255,0.07)',
                        transition: 'background 0.18s',
                    }}>
                        <div style={{
                            width: 28, height: 28, borderRadius: 8, flexShrink: 0,
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            background: tab === i ? ORG : 'rgba(255,255,255,0.14)',
                        }}>
                            <span className="material-icons" style={{ fontSize: 14, color: WHITE }}>phone</span>
                        </div>
                        <span style={{
                            fontSize: 11, fontWeight: 600, lineHeight: 1.3,
                            color: tab === i ? WHITE : 'rgba(255,255,255,0.50)',
                        }}>
                            {label}
                        </span>
                    </button>
                ))}
            </div>

            {/* ── Chart column ── */}
            <div style={{ flex: 1, minWidth: 0, alignSelf: 'center' }}>
                {/* style width/height instead of SVG attrs — reliable in flex */}
                <svg
                    viewBox={`-28 -8 ${CW + 32} ${CH + 10}`}
                    style={{ width: '100%', height: 'auto', display: 'block', overflow: 'visible' }}
                >
                    <defs>
                        <linearGradient id="wkArea" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor={ORG} stopOpacity="0.28" />
                            <stop offset="100%" stopColor={ORG} stopOpacity="0.02" />
                        </linearGradient>
                    </defs>

                    {/* Y-axis labels */}
                    {[['$90', 0], ['$30', CH * 0.45], ['$10', CH]].map(([lbl, y]) => (
                        <text key={lbl} x={-4} y={+y + 4}
                            textAnchor="end" fontSize="9"
                            fill="rgba(255,255,255,0.32)"
                            fontFamily="Work Sans, sans-serif">
                            {lbl}
                        </text>
                    ))}

                    {/* Area fill */}
                    <path d={areaD} fill="url(#wkArea)" />
                    {/* Line */}
                    <path d={lineD} fill="none" stroke={ORG} strokeWidth="2"
                        strokeLinecap="round" strokeLinejoin="round" />
                    {/* Dots — enlarge + white ring on hover */}
                    {coords.map(([x, y], i) => {
                        const isHov      = hovDot === i;
                        const isPrimary  = i === 8;
                        const r = isHov ? 7 : isPrimary ? 5 : 3;
                        return (
                            <circle key={i} cx={x} cy={y}
                                r={r}
                                fill={isHov || isPrimary ? ORG : `${ORG}88`}
                                stroke={isHov || isPrimary ? WHITE : 'none'}
                                strokeWidth={isHov ? 2.5 : 1.5}
                                style={{ cursor: 'pointer', transition: 'r 0.15s ease' }}
                                onMouseEnter={() => setHovDot(i)}
                                onMouseLeave={() => setHovDot(-1)}
                            />
                        );
                    })}
                </svg>
            </div>
        </div>
    );
}

/* ─────────────────────────────────────────────────────────────────────────── */
/*  Call History Table                                                         */
/* ─────────────────────────────────────────────────────────────────────────── */
const S_COLORS = {
    Success: { bg: '#DCFCE7', c: '#16A34A', bd: '#BBF7D0' },
    Waiting: { bg: '#FEF9C3', c: '#A16207', bd: '#FDE68A' },
    Failed:  { bg: '#FFE4E6', c: '#DC2626', bd: '#FECACA' },
};
const CYCLE = ['Success','Waiting','Success','Failed','Waiting','Success'];

function CallHistoryTable({ callbacks }) {
    const rows = (callbacks ?? []).slice(0, 6).map((cb, i) => {
        const dt = cb.scheduled_at ? new Date(cb.scheduled_at) : null;
        return {
            name:   cb.lead_name || 'Unknown',
            date:   dt ? dt.toLocaleDateString('en-GB').replace(/\//g, '-') : '16-08-2026',
            time:   dt ? dt.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) : '14:00pm',
            lead:   cb.lead_code || 'Lead',
            status: CYCLE[i % CYCLE.length],
            link:   cb.encrypted_lead_id,
            faded:  i >= 4,
        };
    });

    return (
        <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 540 }}>
                <thead>
                    <tr style={{ borderBottom: `1px solid ${BORDER}` }}>
                        {['Name','Date','Time','Lead Name','Status',''].map((h, i) => (
                            <th key={i} style={{
                                padding: '10px 14px', fontSize: 10.5, fontWeight: 700,
                                color: MUTED, textAlign: 'left',
                                textTransform: 'uppercase', letterSpacing: '0.4px',
                            }}>
                                {h}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 && (
                        <tr>
                            <td colSpan={6} style={{ padding: 28, textAlign: 'center', color: MUTED, fontSize: 13 }}>
                                No call records yet
                            </td>
                        </tr>
                    )}
                    {rows.map((r, i) => {
                        const sc = S_COLORS[r.status];
                        return (
                            <tr key={i} style={{ borderBottom: `1px solid ${BORDER}`, opacity: r.faded ? 0.32 : 1 }}>
                                <td style={{ padding: '12px 14px', fontSize: 13, fontWeight: 600, color: TEXT }}>{r.name}</td>
                                <td style={{ padding: '12px 14px', fontSize: 12, color: MUTED }}>{r.date}</td>
                                <td style={{ padding: '12px 14px', fontSize: 12, color: MUTED }}>{r.time}</td>
                                <td style={{ padding: '12px 14px', fontSize: 12, color: MUTED }}>{r.lead}</td>
                                <td style={{ padding: '12px 14px' }}>
                                    <span style={{
                                        fontSize: 11, fontWeight: 700, padding: '4px 13px',
                                        borderRadius: 20, background: sc.bg, color: sc.c,
                                        border: `1px solid ${sc.bd}`, whiteSpace: 'nowrap',
                                    }}>
                                        {r.status}
                                    </span>
                                </td>
                                <td style={{ padding: '12px 14px' }}>
                                    {r.link ? (
                                        <Link href={`/telecaller/leads/${r.link}`}
                                            style={{ display: 'flex', alignItems: 'center', gap: 4, fontSize: 12, color: ORG, fontWeight: 700, textDecoration: 'none', whiteSpace: 'nowrap' }}>
                                            View Records
                                            <span className="material-icons" style={{ fontSize: 13 }}>open_in_new</span>
                                        </Link>
                                    ) : (
                                        <span style={{ display: 'flex', alignItems: 'center', gap: 4, fontSize: 12, color: MUTED, whiteSpace: 'nowrap' }}>
                                            View Records
                                            <span className="material-icons" style={{ fontSize: 13 }}>open_in_new</span>
                                        </span>
                                    )}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

/* ─────────────────────────────────────────────────────────────────────────── */
/*  Call Outcomes Donut                                                        */
/* ─────────────────────────────────────────────────────────────────────────── */
const O_CFG = {
    interested:      { label: 'Interested',      c: '#C8CAD4' },
    not_interested:  { label: 'Not Interested',  c: '#ABADB8' },
    wrong_number:    { label: 'Wrong Number',    c: ORG       },
    call_back_later: { label: 'Call Back Later', c: ORGL      },
    switched_off:    { label: 'Switched Off',    c: '#C04000' },
};

function CallOutcomes({ outcomes }) {
    const entries = Object.entries(outcomes ?? {}).filter(([, v]) => v > 0);
    const total   = entries.reduce((s, [, v]) => s + Number(v), 0);
    /* Placeholder shows 3 distinct arcs so the donut looks attractive with no real data */
    const data = entries.length > 0
        ? entries.map(([k, v]) => ({ v: Number(v), c: O_CFG[k]?.c ?? '#64748b', label: O_CFG[k]?.label ?? k }))
        : [{ v: 6, c: ORG, label: '' }, { v: 4, c: ORGL, label: '' }, { v: 3, c: '#C8CAD4', label: '' }];

    return (
        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 16 }}>
            <Donut data={data} size={158} stroke={32}
                center={entries.length > 0
                    ? { val: total, sub: 'Total Calls', valSize: 26 }
                    : { val: '–', valSize: 26 }} />
            {entries.length > 0 && (
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px 14px', justifyContent: 'center', width: '100%' }}>
                    {data.map((d, i) => (
                        <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 5, fontSize: 11, color: MUTED }}>
                            <div style={{ width: 8, height: 8, borderRadius: '50%', background: d.c, flexShrink: 0 }} />
                            {d.label}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

/* ─────────────────────────────────────────────────────────────────────────── */
/*  Missed Callbacks panel                                                     */
/* ─────────────────────────────────────────────────────────────────────────── */
function MissedCallbacks({ callbacks }) {
    const rows = (callbacks ?? []).slice(0, 4);
    return (
        <div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 94px 68px 74px', gap: 8, paddingBottom: 8, borderBottom: `1px solid ${BORDER}` }}>
                {['Name','Date','Time',''].map((h, i) => (
                    <span key={i} style={{ fontSize: 10, fontWeight: 700, color: MUTED, textTransform: 'uppercase', letterSpacing: '0.4px' }}>{h}</span>
                ))}
            </div>
            {rows.length === 0 && (
                <div style={{ textAlign: 'center', padding: '20px 0', fontSize: 12, color: MUTED }}>
                    No missed callbacks
                </div>
            )}
            {rows.map((cb, i) => {
                const dt = cb.scheduled_at ? new Date(cb.scheduled_at) : null;
                return (
                    <div key={i} style={{
                        display: 'grid', gridTemplateColumns: '1fr 94px 68px 74px', gap: 8, alignItems: 'center',
                        padding: '10px 0',
                        borderBottom: i < rows.length - 1 ? `1px solid ${BORDER}` : 'none',
                        opacity: i >= 2 ? 0.32 : 1,
                    }}>
                        <span style={{ fontSize: 12, fontWeight: 600, color: TEXT, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                            {cb.lead_name || 'Unknown'}
                        </span>
                        <span style={{ fontSize: 11, color: MUTED }}>
                            {dt ? dt.toLocaleDateString('en-GB').replace(/\//g, '-') : '16-08-2026'}
                        </span>
                        <span style={{ fontSize: 11, color: MUTED }}>
                            {dt ? dt.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) : '14:00pm'}
                        </span>
                        {cb.encrypted_lead_id ? (
                            <Link href={`/telecaller/leads/${cb.encrypted_lead_id}`}
                                style={{ fontSize: 10, fontWeight: 700, color: WHITE, textDecoration: 'none', background: '#22C55E', borderRadius: 7, padding: '5px 10px', textAlign: 'center', whiteSpace: 'nowrap', display: 'block' }}>
                                Call Back
                            </Link>
                        ) : <div />}
                    </div>
                );
            })}
        </div>
    );
}

/* ─────────────────────────────────────────────────────────────────────────── */
/*  Follow-up Calendar  (Mon-first, prev/next month filler days)               */
/* ─────────────────────────────────────────────────────────────────────────── */
function FollowupCalendar({ initialData }) {
    const today  = new Date();
    const todayY = today.getFullYear(), todayM = today.getMonth() + 1, todayD = today.getDate();
    const [state,   setState]   = useState({ year: todayY, month: todayM, days: initialData ?? {} });
    const [loading, setLoading] = useState(false);

    async function navigate(year, month) {
        setLoading(true);
        try {
            const res  = await fetch(`/telecaller/followups/calendar-data?year=${year}&month=${month}`, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            setState({ year: data.year, month: data.month, days: data.days || {} });
        } catch (_) {}
        setLoading(false);
    }

    const { year, month, days } = state;
    const daysInMonth = new Date(year, month, 0).getDate();
    const daysInPrev  = new Date(year, month - 1, 0).getDate();
    const rawFirst    = new Date(year, month - 1, 1).getDay();   // 0=Sun
    const firstDow    = (rawFirst + 6) % 7;                       // Mon=0
    const isThisMonth = year === todayY && month === todayM;

    /* Build cells: leading fillers → current month days → trailing fillers */
    const cells = [];
    for (let i = 0; i < firstDow; i++)
        cells.push({ d: daysInPrev - firstDow + 1 + i, filler: true });
    for (let d = 1; d <= daysInMonth; d++)
        cells.push({ d, filler: false });
    const trailing = (7 - (cells.length % 7)) % 7;
    for (let i = 1; i <= trailing; i++)
        cells.push({ d: i, filler: true });

    const dotColor = count => !count ? null : count <= 3 ? '#22C55E' : count <= 7 ? '#F59E0B' : ORG;

    function go(delta) {
        let { year: y, month: m } = state;
        m += delta;
        if (m < 1)  { m = 12; y--; }
        if (m > 12) { m = 1;  y++; }
        navigate(y, m);
    }

    return (
        <div style={{ opacity: loading ? 0.5 : 1, transition: 'opacity 0.2s' }}>
            {/* Header */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
                <span style={{ fontSize: 14, fontWeight: 700, color: TEXT }}>
                    {MONTH_NAMES[month - 1]} {year}
                </span>
                <div style={{ display: 'flex', gap: 6 }}>
                    {[-1, 1].map(delta => (
                        <button key={delta} onClick={() => go(delta)} style={{
                            width: 28, height: 28, borderRadius: 8, border: `1px solid ${BORDER}`,
                            background: WHITE, fontSize: 17, cursor: 'pointer',
                            display: 'flex', alignItems: 'center', justifyContent: 'center', color: TEXT,
                        }}>
                            {delta === -1 ? '‹' : '›'}
                        </button>
                    ))}
                </div>
            </div>

            {/* Day-of-week headers */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 4, marginBottom: 6 }}>
                {DOW_CAL.map(d => (
                    <div key={d} style={{ textAlign: 'center', fontSize: 10, fontWeight: 700, color: MUTED }}>{d}</div>
                ))}
            </div>

            {/* Day cells */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 4 }}>
                {cells.map((cell, idx) => {
                    const { d, filler } = cell;
                    const key    = filler ? null : `${year}-${pad(month)}-${pad(d)}`;
                    const count  = key ? (days[key] || 0) : 0;
                    const isToday = !filler && isThisMonth && d === todayD;
                    const isPast  = !filler && new Date(year, month - 1, d) < new Date(todayY, todayM - 1, todayD);
                    const dot    = dotColor(count);

                    let href = '';
                    if (!filler && count > 0) {
                        href = isToday      ? '/telecaller/followups/today'
                             : isPast       ? '/telecaller/followups/overdue'
                             :                '/telecaller/followups/upcoming';
                    }

                    const cellSt = {
                        borderRadius: 8, padding: '6px 2px', textAlign: 'center',
                        border: isToday ? `2px solid ${ORG}` : `1px solid ${filler ? '#F0F0F0' : BORDER}`,
                        background: isToday ? `${ORG}15` : filler ? '#FAFAFA' : isPast ? '#FAFAFA' : WHITE,
                        cursor: (!filler && count > 0) ? 'pointer' : 'default',
                        display: 'block', textDecoration: 'none',
                    };
                    const inner = (
                        <>
                            <div style={{
                                fontSize: 11,
                                fontWeight: isToday ? 800 : 500,
                                color: filler ? '#D1D5DB' : isToday ? ORG : isPast ? '#CBD5E1' : TEXT,
                            }}>
                                {d}
                            </div>
                            {(dot && !filler)
                                ? <div style={{ width: 5, height: 5, borderRadius: '50%', background: dot, margin: '2px auto 0' }} />
                                : <div style={{ height: 7 }} />
                            }
                        </>
                    );
                    return href
                        ? <Link key={idx} href={href} style={cellSt}>{inner}</Link>
                        : <div key={idx} style={cellSt}>{inner}</div>;
                })}
            </div>
        </div>
    );
}

/* ─────────────────────────────────────────────────────────────────────────── */
/*  Main Dashboard                                                             */
/* ─────────────────────────────────────────────────────────────────────────── */
export default function Dashboard({
    stats: initialStats,
    missed_callbacks: initialCallbacks,
    followup_calendar,
    call_outcomes: initialOutcomes,
}) {
    const [stats,     setStats]     = useState(initialStats     ?? {});
    const [callbacks, setCallbacks] = useState(initialCallbacks ?? []);
    const [outcomes,  setOutcomes]  = useState(initialOutcomes  ?? {});
    const timeStr = useLiveClock();

    const fetchSnapshot = useCallback(async () => {
        try {
            const res  = await fetch('/telecaller/panel/snapshot', { headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (!data?.ok) return;
            setStats({
                assigned:       Number(data.total_assigned_leads    || 0),
                new_leads:      Number(data.new_leads               || 0),
                followups:      Number(data.today_followup_count    || 0),
                overdue:        Number(data.overdue_followup_count  || 0),
                calls:          Number(data.total_calls_today       || 0),
                talk_time_secs: Number(data.talk_time_today_seconds || 0),
                active_calls:   Number(data.active_call_count       || 0),
            });
            if (Array.isArray(data.missed_callbacks))
                setCallbacks(data.missed_callbacks);
            if (data.call_outcomes && typeof data.call_outcomes === 'object')
                setOutcomes(data.call_outcomes);
        } catch (_) {}
    }, []);

    useEffect(() => {
        fetchSnapshot();
        const t   = setInterval(() => { if (!document.hidden) fetchSnapshot(); }, 30_000);
        const vis = () => { if (!document.hidden) fetchSnapshot(); };
        document.addEventListener('visibilitychange', vis);
        return () => { clearInterval(t); document.removeEventListener('visibilitychange', vis); };
    }, [fetchSnapshot]);

    /* Derived values */
    const talkSecs     = stats.talk_time_secs ?? 0;
    const talkH        = Math.floor(talkSecs / 3600);
    const talkM        = Math.floor((talkSecs % 3600) / 60);
    const talkPct      = Math.min(100, Math.round((talkSecs / (8 * 3600)) * 100));
    const targetReached = talkH >= 8;
    const needHrs      = Math.max(0, 8 - talkH);
    const hasFollowups = (stats.followups ?? 0) > 0;
    const hasOverdue   = (stats.overdue   ?? 0) > 0;
    /* Use a seed ≥ 7 so the heatmap always looks populated */
    const hmSeed = Math.max(7, (stats.calls ?? 0) + 7);

    const statCards = [
        { icon: 'phone',          label: 'Calls Today',    value: stats.calls     ?? 0, primary: true },
        { icon: 'assignment_ind', label: 'Assigned Tasks', value: stats.assigned  ?? 0 },
        { icon: 'person',         label: 'Follow Ups',     value: stats.followups ?? 0 },
        { icon: 'schedule',       label: 'Overdue',        value: stats.overdue   ?? 0 },
    ];

    return (
        <>
            <Head title="Dashboard" />

            {/* ── Global styles ─────────────────────────────────────────────── */}
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Work+Sans:wght@300;400;500;600;700;800;900&display=swap');
                *, *::before, *::after {
                    font-family: 'Work Sans', sans-serif !important;
                    box-sizing: border-box;
                }
                @keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }

                /* Layout grids */
                .db-top    { display:grid; grid-template-columns:195px 1fr; gap:18px; align-items:start; }
                .db-row2   { display:grid; grid-template-columns:1fr 1fr;   gap:18px; align-items:stretch; }
                .db-row3   { display:grid; grid-template-columns:1fr 1fr 1fr; gap:18px; align-items:stretch; }
                .db-rowcal { display:grid; grid-template-columns:1fr 1fr;   gap:18px; }
                .db-stats  { display:flex; flex-direction:column; gap:12px; }

                @media(max-width:1024px){
                    .db-top  { grid-template-columns:1fr; }
                    .db-row2 { grid-template-columns:1fr; }
                    .db-stats{ flex-direction:row; flex-wrap:wrap; }
                }
                @media(max-width:768px){
                    .db-row3   { grid-template-columns:1fr; }
                    .db-rowcal { grid-template-columns:1fr; }
                }

                /* Table row hover */
                tbody tr { transition: background 0.12s; }
                tbody tr:hover { background: #FAFAFA; }

                /* Select reset */
                select { appearance:none; -webkit-appearance:none; }
                button:focus, select:focus { outline:none; }
            `}</style>

            {/* ═══════════════════════════════════════════════════════════════ */}
            {/* Upcoming Event Banner — always visible                          */}
            {/* ═══════════════════════════════════════════════════════════════ */}
            <div style={{
                background: DARKC, borderRadius: 14,
                padding: '16px 24px', marginBottom: 18,
                display: 'flex', alignItems: 'center',
                justifyContent: 'space-between', gap: 16,
                boxShadow: '0 4px 20px rgba(26,26,46,0.22)',
            }}>
                <div>
                    <div style={{ fontSize: 10, fontWeight: 700, color: ORG, textTransform: 'uppercase', letterSpacing: '1.2px', marginBottom: 5 }}>
                        Upcoming Event
                    </div>
                    <div style={{ fontSize: 15, fontWeight: 700, color: WHITE }}>
                        {(hasFollowups || hasOverdue) ? (
                            <>
                                {hasFollowups && `${stats.followups} Follow-up${stats.followups !== 1 ? 's' : ''} due Today!`}
                                {hasOverdue && (
                                    <span style={{ color: '#FF8080', marginLeft: hasFollowups ? 10 : 0 }}>
                                        {hasFollowups ? '•' : ''} {stats.overdue} Overdue
                                    </span>
                                )}
                            </>
                        ) : (
                            'All caught up — no pending follow-ups today!'
                        )}
                    </div>
                </div>
                {(hasFollowups || hasOverdue) && (
                    <Link href="/telecaller/leads?status=follow_up" style={{
                        textDecoration: 'none', background: ORG, color: WHITE,
                        fontSize: 13, fontWeight: 700, padding: '11px 24px',
                        borderRadius: 10, whiteSpace: 'nowrap',
                        boxShadow: `0 4px 14px ${ORG}55`,
                        flexShrink: 0,
                    }}>
                        Remind me
                    </Link>
                )}
            </div>

            {/* ═══════════════════════════════════════════════════════════════ */}
            {/* TOP: Stat Cards (left) + Calls Dashboard (right)               */}
            {/* ═══════════════════════════════════════════════════════════════ */}
            <div className="db-top" style={{ marginBottom: 18 }}>

                {/* Stat cards column */}
                <div className="db-stats">
                    {statCards.map((sc, i) => (
                        <div key={i} style={{
                            ...card(),
                            padding: '14px 18px',
                            background: sc.primary ? `linear-gradient(135deg, ${ORG}18, ${ORG}08)` : WHITE,
                            border: sc.primary ? `1.5px solid ${ORG}38` : `1px solid ${BORDER}`,
                        }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                                <div style={{
                                    width: 38, height: 38, borderRadius: 10, flexShrink: 0,
                                    background: sc.primary ? ORG : BGGRAY,
                                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                                }}>
                                    <span className="material-icons" style={{ fontSize: 18, color: sc.primary ? WHITE : MUTED }}>
                                        {sc.icon}
                                    </span>
                                </div>
                                <div>
                                    <div style={{ fontSize: 10, fontWeight: 600, color: sc.primary ? ORG : MUTED, textTransform: 'uppercase', letterSpacing: '0.5px', lineHeight: 1.2 }}>
                                        {sc.label}
                                    </div>
                                    <div style={{ fontSize: 28, fontWeight: 800, color: sc.primary ? ORG : TEXT, lineHeight: 1.15, marginTop: 2 }}>
                                        {sc.value}
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Calls Dashboard */}
                <div style={{ ...card(), padding: '20px 22px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 18, flexWrap: 'wrap', gap: 10 }}>
                        <div style={{ fontSize: 19, fontWeight: 800, color: TEXT }}>Calls Dashboard</div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 16, flexWrap: 'wrap' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
                                {[{ c: ORG, l: 'Assigned' }, { c: '#DAA520', l: 'Waiting List' }, { c: '#BBBCC8', l: 'Holiday' }].map(({ c, l }) => (
                                    <span key={l} style={{ display: 'flex', alignItems: 'center', gap: 5, fontSize: 11, color: MUTED }}>
                                        <span style={{ width: 9, height: 9, borderRadius: '50%', background: c, display: 'inline-block' }} />
                                        {l}
                                    </span>
                                ))}
                            </div>
                            <select style={selStyle()}>
                                <option>Weekly</option>
                                <option>Monthly</option>
                            </select>
                        </div>
                    </div>
                    <CallsHeatmap seed={hmSeed} />
                </div>
            </div>

            {/* ═══════════════════════════════════════════════════════════════ */}
            {/* MID: Lead Pipeline + Weekly Target                             */}
            {/* ═══════════════════════════════════════════════════════════════ */}
            <div className="db-row2" style={{ marginBottom: 18 }}>
                <div style={{ ...card(), padding: '20px 22px' }}>
                    <div style={{ fontSize: 15, fontWeight: 800, color: TEXT, marginBottom: 2 }}>Lead Pipeline</div>
                    <div style={{ fontSize: 11, color: MUTED, marginBottom: 18 }}>Call outcome distribution</div>
                    <LeadPipeline outcomes={outcomes} />
                </div>

                <div style={{ background: DARKC, borderRadius: 14, padding: '20px 22px', boxShadow: '0 6px 28px rgba(0,0,0,0.22)' }}>
                    <div style={{ fontSize: 15, fontWeight: 800, color: WHITE, marginBottom: 2 }}>Weekly Target Overview</div>
                    <div style={{ fontSize: 11, color: 'rgba(255,255,255,0.38)', marginBottom: 16 }}>Performance metrics this week</div>
                    <WeeklyTarget stats={stats} />
                </div>
            </div>

            {/* ═══════════════════════════════════════════════════════════════ */}
            {/* Call History Table                                              */}
            {/* ═══════════════════════════════════════════════════════════════ */}
            <div style={{ ...card(), padding: '20px 22px', marginBottom: 18 }}>
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: 16, flexWrap: 'wrap', gap: 10 }}>
                    <div>
                        <div style={{ fontSize: 15, fontWeight: 800, color: TEXT }}>Call History</div>
                        <div style={{ fontSize: 11, color: MUTED, marginTop: 2 }}>Recent call records</div>
                    </div>
                    <div style={{ display: 'flex', gap: 10 }}>
                        <select style={selStyle({ padding: '7px 28px 7px 12px' })}>
                            <option>Today</option>
                            <option>This Week</option>
                        </select>
                        <Link href="/telecaller/calls/missed" style={{
                            display: 'flex', alignItems: 'center', gap: 6,
                            fontSize: 12, fontWeight: 700, color: WHITE,
                            textDecoration: 'none', background: ORG,
                            borderRadius: 8, padding: '7px 16px',
                            boxShadow: `0 3px 12px ${ORG}44`, whiteSpace: 'nowrap',
                        }}>
                            Export Table
                        </Link>
                    </div>
                </div>
                <CallHistoryTable callbacks={callbacks} />
            </div>

            {/* ═══════════════════════════════════════════════════════════════ */}
            {/* BOTTOM: Talk Time | Call Outcomes | Missed Callbacks            */}
            {/* ═══════════════════════════════════════════════════════════════ */}
            <div className="db-row3" style={{ marginBottom: 18 }}>

                {/* Overall Talk Time */}
                <div style={{ ...card(), padding: '20px 22px' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 14 }}>
                        <div>
                            <div style={{ fontSize: 14, fontWeight: 800, color: TEXT }}>Overall Talk Time</div>
                            <div style={{ fontSize: 11, color: MUTED, marginTop: 2 }}>Today's call duration</div>
                        </div>
                        <select style={selStyle({ fontSize: 11, padding: '5px 24px 5px 10px' })}>
                            <option>Today</option>
                        </select>
                    </div>

                    <div style={{ display: 'flex', alignItems: 'baseline', gap: 3, marginBottom: 14 }}>
                        <span style={{ fontSize: 44, fontWeight: 800, color: TEXT, lineHeight: 1 }}>{talkH}</span>
                        <span style={{ fontSize: 15, fontWeight: 700, color: TEXT, marginRight: 4 }}>hrs</span>
                        <span style={{ fontSize: 20, color: MUTED, marginRight: 4 }}>:</span>
                        <span style={{ fontSize: 44, fontWeight: 800, color: TEXT, lineHeight: 1 }}>{pad(talkM)}</span>
                        <span style={{ fontSize: 15, fontWeight: 700, color: TEXT }}>mins</span>
                    </div>

                    <div style={{ height: 7, background: '#EFEFEF', borderRadius: 20, overflow: 'hidden', marginBottom: 5 }}>
                        <div style={{
                            height: '100%', width: `${talkPct}%`,
                            background: `linear-gradient(90deg, ${ORG}, ${ORGL})`,
                            borderRadius: 20, transition: 'width 0.8s ease',
                        }} />
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', fontSize: 10, color: MUTED, marginBottom: 14 }}>
                        8hrs
                    </div>

                    <div style={{
                        display: 'inline-flex', alignItems: 'center', gap: 7,
                        background: targetReached ? '#DCFCE7' : `${ORG}14`,
                        border: `1px solid ${targetReached ? '#BBF7D0' : ORG + '28'}`,
                        borderRadius: 8, padding: '6px 12px', marginBottom: 10,
                    }}>
                        <span style={{ fontSize: 12, fontWeight: 700, color: targetReached ? '#16A34A' : ORG }}>
                            {targetReached ? '✓ Target Reached!' : `Need ${needHrs}hrs`}
                        </span>
                        {!targetReached && (
                            <span style={{ fontSize: 11, color: MUTED }}>to complete today's target</span>
                        )}
                    </div>

                    <div style={{ fontSize: 11, color: MUTED, lineHeight: 1.7 }}>
                        Track your daily talk time and hit your target for consistent performance results.
                    </div>
                </div>

                {/* Call Outcomes */}
                <div style={{ ...card(), padding: '20px 22px' }}>
                    <div style={{ fontSize: 14, fontWeight: 800, color: TEXT, marginBottom: 2 }}>Call Outcomes</div>
                    <div style={{ fontSize: 11, color: MUTED, marginBottom: 18 }}>Result breakdown for today</div>
                    <CallOutcomes outcomes={outcomes} />
                </div>

                {/* Missed Callbacks */}
                <div style={{ ...card(), padding: '20px 22px' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 14 }}>
                        <div>
                            <div style={{ fontSize: 14, fontWeight: 800, color: TEXT }}>Missed Callbacks</div>
                            <div style={{ fontSize: 11, color: MUTED, marginTop: 2 }}>Pending callbacks</div>
                        </div>
                        <select style={selStyle({ fontSize: 11, padding: '5px 24px 5px 10px' })}>
                            <option>Today</option>
                        </select>
                    </div>
                    <MissedCallbacks callbacks={callbacks} />
                </div>
            </div>

            {/* ═══════════════════════════════════════════════════════════════ */}
            {/* Upcoming Calendar                                               */}
            {/* ═══════════════════════════════════════════════════════════════ */}
            <div className="db-rowcal" style={{ marginBottom: 24 }}>
                {/* Description + legend */}
                <div>
                    <div style={{ fontSize: 27, fontWeight: 800, color: TEXT, lineHeight: 1.2, marginBottom: 12 }}>
                        Upcoming Calendar
                    </div>
                    <div style={{ fontSize: 13, color: MUTED, lineHeight: 1.75, marginBottom: 24 }}>
                        Track your scheduled follow-ups and upcoming calls.
                        Stay organized and never miss an important callback or meeting.
                        Highlighted dates show your follow-up load — click to view details.
                    </div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                        {[
                            { c: ORG,       bg: `${ORG}20`,              label: 'High Call Count',     desc: 'More than 8 follow-ups scheduled'  },
                            { c: '#F59E0B', bg: 'rgba(245,158,11,0.16)', label: 'Moderate Call Count', desc: '4–7 follow-ups scheduled'          },
                            { c: '#22C55E', bg: 'rgba(34,197,94,0.14)',  label: 'Low Call Count',       desc: '1–3 follow-ups scheduled'          },
                        ].map(({ c, bg, label, desc }) => (
                            <div key={label} style={{ display: 'flex', alignItems: 'flex-start', gap: 12 }}>
                                <div style={{ width: 14, height: 14, borderRadius: 4, background: bg, border: `2px solid ${c}`, flexShrink: 0, marginTop: 2 }} />
                                <div>
                                    <div style={{ fontSize: 12, fontWeight: 700, color: TEXT }}>{label}</div>
                                    <div style={{ fontSize: 11, color: MUTED, marginTop: 1 }}>{desc}</div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Calendar grid */}
                <div style={{ ...card(), padding: '20px 22px' }}>
                    <FollowupCalendar initialData={followup_calendar} />
                </div>
            </div>
        </>
    );
}
