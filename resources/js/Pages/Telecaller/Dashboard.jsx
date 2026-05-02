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
const DOW_LABELS = ['S','M','T','W','T','F','S'];

// ─── Hook: greeting + date ────────────────────────────────────────────────────
function useLiveClock() {
    const [time, setTime] = useState(new Date());
    useEffect(() => {
        const t = setInterval(() => setTime(new Date()), 1_000);
        return () => clearInterval(t);
    }, []);
    const h        = time.getHours();
    const greeting = h < 12 ? 'Good Morning' : h < 17 ? 'Good Afternoon' : 'Good Evening';
    const greetIcon = h < 12 ? 'wb_sunny' : h < 17 ? 'light_mode' : 'nights_stay';
    const dateStr  = time.toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const timeStr  = time.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    return { greeting, greetIcon, dateStr, timeStr };
}

// ─── Design tokens ────────────────────────────────────────────────────────────
const G = {
    violet:  { grad: 'linear-gradient(135deg,#6366f1 0%,#4f46e5 100%)', soft: 'rgba(99,102,241,0.12)',  shadow: 'rgba(99,102,241,0.28)',  text: '#4f46e5' },
    emerald: { grad: 'linear-gradient(135deg,#10b981 0%,#059669 100%)', soft: 'rgba(16,185,129,0.12)',  shadow: 'rgba(16,185,129,0.28)',  text: '#059669' },
    amber:   { grad: 'linear-gradient(135deg,#f59e0b 0%,#d97706 100%)', soft: 'rgba(245,158,11,0.12)',  shadow: 'rgba(245,158,11,0.28)',  text: '#d97706' },
    rose:    { grad: 'linear-gradient(135deg,#f43f5e 0%,#e11d48 100%)', soft: 'rgba(244,63,94,0.12)',   shadow: 'rgba(244,63,94,0.28)',   text: '#e11d48' },
    sky:     { grad: 'linear-gradient(135deg,#0ea5e9 0%,#0284c7 100%)', soft: 'rgba(14,165,233,0.12)',  shadow: 'rgba(14,165,233,0.28)',  text: '#0284c7' },
    purple:  { grad: 'linear-gradient(135deg,#8b5cf6 0%,#7c3aed 100%)', soft: 'rgba(139,92,246,0.12)',  shadow: 'rgba(139,92,246,0.28)',  text: '#7c3aed' },
};

const glass = (opacity = 0.78, blur = 24) => ({
    background: `rgba(255,255,255,${opacity})`,
    backdropFilter: `blur(${blur}px)`,
    WebkitBackdropFilter: `blur(${blur}px)`,
    border: '1px solid rgba(255,255,255,0.68)',
});

// ─── KPI Stat Card ────────────────────────────────────────────────────────────
function KpiCard({ label, value, icon, tone = 'violet', badge }) {
    const [hov, setHov] = useState(false);
    const t = G[tone] ?? G.violet;
    return (
        <div
            onMouseEnter={() => setHov(true)}
            onMouseLeave={() => setHov(false)}
            style={{
                ...glass(0.82),
                borderRadius: 22,
                padding: '22px 22px 20px',
                boxShadow: hov
                    ? `0 16px 48px ${t.shadow}, 0 4px 16px rgba(0,0,0,0.06)`
                    : '0 4px 24px rgba(99,102,241,0.07), 0 1px 4px rgba(0,0,0,0.04)',
                transform: hov ? 'translateY(-3px) scale(1.01)' : 'translateY(0) scale(1)',
                transition: 'all 0.28s cubic-bezier(.4,0,.2,1)',
                position: 'relative',
                overflow: 'hidden',
                height: '100%',
                cursor: 'default',
            }}
        >
            {/* top accent line */}
            <div style={{
                position: 'absolute', top: 0, left: 0, right: 0, height: 3,
                background: t.grad, opacity: hov ? 1 : 0.6,
                transition: 'opacity 0.28s',
                borderRadius: '22px 22px 0 0',
            }} />

            {/* Background blob */}
            <div style={{
                position: 'absolute', right: -18, top: -18, width: 80, height: 80,
                borderRadius: '50%', background: t.soft,
                filter: 'blur(20px)',
                pointerEvents: 'none',
            }} />

            <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: 16 }}>
                {/* Icon */}
                <div style={{
                    width: 44, height: 44, borderRadius: 14,
                    background: t.grad,
                    boxShadow: `0 6px 18px ${t.shadow}`,
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    flexShrink: 0,
                }}>
                    <span className="material-icons" style={{ fontSize: 20, color: '#fff' }}>{icon}</span>
                </div>
                {badge && (
                    <span style={{
                        fontSize: 9.5, fontWeight: 700, color: t.text,
                        background: t.soft, border: `1px solid ${t.shadow}`,
                        borderRadius: 20, padding: '2px 8px',
                        textTransform: 'uppercase', letterSpacing: '0.5px',
                    }}>{badge}</span>
                )}
            </div>

            <div style={{
                fontSize: 32, fontWeight: 800, color: '#0f172a',
                letterSpacing: '-1px', lineHeight: 1,
                fontVariantNumeric: 'tabular-nums',
            }}>
                {value ?? '—'}
            </div>
            <div style={{
                fontSize: 11, fontWeight: 600, color: '#94a3b8',
                textTransform: 'uppercase', letterSpacing: '0.7px', marginTop: 7,
            }}>
                {label}
            </div>
        </div>
    );
}

// ─── Glass Card wrapper ───────────────────────────────────────────────────────
function GlassCard({ title, subtitle, icon, tone = 'emerald', children, minH, action }) {
    const t = G[tone] ?? G.emerald;
    return (
        <div style={{
            ...glass(0.80),
            borderRadius: 24,
            padding: '24px 26px',
            boxShadow: '0 6px 32px rgba(99,102,241,0.07), 0 2px 8px rgba(0,0,0,0.04)',
            height: '100%',
            minHeight: minH,
            position: 'relative',
            overflow: 'hidden',
        }}>
            {/* Subtle corner blob */}
            <div style={{
                position: 'absolute', right: -24, bottom: -24, width: 96, height: 96,
                borderRadius: '50%', background: t.soft, filter: 'blur(28px)',
                pointerEvents: 'none',
            }} />

            {(title || action) && (
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: 18, gap: 8 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                        {icon && (
                            <div style={{
                                width: 34, height: 34, borderRadius: 10,
                                background: t.soft, display: 'flex', alignItems: 'center', justifyContent: 'center',
                                border: `1.5px solid ${t.shadow.replace('0.28','0.18')}`,
                            }}>
                                <span className="material-icons" style={{ fontSize: 17, color: t.text }}>{icon}</span>
                            </div>
                        )}
                        <div>
                            <div style={{ fontSize: 13.5, fontWeight: 700, color: '#0f172a', lineHeight: 1.2 }}>{title}</div>
                            {subtitle && (
                                <div style={{ fontSize: 11, color: '#94a3b8', marginTop: 2 }}>{subtitle}</div>
                            )}
                        </div>
                    </div>
                    {action}
                </div>
            )}
            <div style={{ position: 'relative' }}>
                {children}
            </div>
        </div>
    );
}

// ─── Animated Progress Bar ────────────────────────────────────────────────────
function ProgressBar({ pct, color, label, count }) {
    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 5, alignItems: 'center' }}>
                <span style={{ fontSize: 12, fontWeight: 600, color: '#334155' }}>{label}</span>
                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                    <span style={{ fontSize: 11, fontWeight: 700, color, background: `${color}18`, borderRadius: 20, padding: '1px 8px' }}>{count}</span>
                    <span style={{ fontSize: 10.5, color: '#b0bec5', fontWeight: 500 }}>{pct}%</span>
                </div>
            </div>
            <div style={{ background: 'rgba(241,245,249,0.9)', borderRadius: 8, height: 7, overflow: 'hidden', boxShadow: 'inset 0 1px 3px rgba(0,0,0,0.06)' }}>
                <div style={{
                    width: `${pct}%`, height: '100%',
                    background: `linear-gradient(90deg, ${color}cc, ${color})`,
                    borderRadius: 8,
                    transition: 'width 0.7s cubic-bezier(.4,0,.2,1)',
                    boxShadow: `0 0 8px ${color}55`,
                }} />
            </div>
        </div>
    );
}

// ─── Call Outcome Chart ───────────────────────────────────────────────────────
const OUTCOME_CFG = {
    interested:      { label: 'Interested',      color: '#10b981' },
    not_interested:  { label: 'Not Interested',  color: '#f43f5e' },
    wrong_number:    { label: 'Wrong Number',    color: '#f59e0b' },
    call_back_later: { label: 'Call Back Later', color: '#6366f1' },
    switched_off:    { label: 'Switched Off',    color: '#94a3b8' },
};

function CallOutcomeChart({ outcomes }) {
    const entries = Object.entries(outcomes ?? {}).filter(([, v]) => v > 0);
    if (entries.length === 0) {
        return (
            <div style={{ textAlign: 'center', padding: '28px 0', color: '#cbd5e1' }}>
                <div style={{
                    width: 52, height: 52, borderRadius: 16,
                    background: 'rgba(241,245,249,0.9)',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    margin: '0 auto 10px',
                }}>
                    <span className="material-icons" style={{ fontSize: 26, color: '#cbd5e1' }}>bar_chart</span>
                </div>
                <span style={{ fontSize: 12, color: '#94a3b8' }}>No calls recorded today</span>
            </div>
        );
    }
    const total = entries.reduce((s, [, v]) => s + Number(v), 0);
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            {entries.map(([key, count]) => {
                const cfg = OUTCOME_CFG[key] ?? { label: key, color: '#64748b' };
                const pct = total > 0 ? Math.round((count / total) * 100) : 0;
                return <ProgressBar key={key} pct={pct} color={cfg.color} label={cfg.label} count={count} />;
            })}
            <div style={{
                marginTop: 4, paddingTop: 12, borderTop: '1px solid rgba(226,232,240,0.6)',
                fontSize: 11, color: '#94a3b8', display: 'flex', justifyContent: 'space-between', alignItems: 'center',
            }}>
                <span>Total recorded outcomes</span>
                <span style={{ fontWeight: 700, color: '#475569', fontSize: 13 }}>{total}</span>
            </div>
        </div>
    );
}

// ─── Missed Callbacks Panel ───────────────────────────────────────────────────
function MissedCallbacksPanel({ callbacks }) {
    if (!callbacks || callbacks.length === 0) {
        return (
            <div style={{ textAlign: 'center', padding: '28px 0', color: '#cbd5e1' }}>
                <div style={{
                    width: 52, height: 52, borderRadius: 16,
                    background: 'rgba(241,245,249,0.9)',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    margin: '0 auto 10px',
                }}>
                    <span className="material-icons" style={{ fontSize: 26, color: '#cbd5e1' }}>phone_enabled</span>
                </div>
                <span style={{ fontSize: 12, color: '#94a3b8' }}>All clear — no missed callbacks</span>
            </div>
        );
    }
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8, maxHeight: 230, overflowY: 'auto' }}>
            {callbacks.map(item => (
                <div key={item.id} style={{
                    background: 'rgba(255,241,242,0.85)',
                    border: '1px solid rgba(254,205,211,0.7)',
                    borderRadius: 14,
                    padding: '11px 14px',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    gap: 8,
                    transition: 'box-shadow 0.2s',
                }}>
                    <div style={{ display: 'flex', gap: 10, alignItems: 'center', minWidth: 0 }}>
                        <div style={{
                            width: 34, height: 34, borderRadius: 10, flexShrink: 0,
                            background: 'linear-gradient(135deg,#f43f5e,#e11d48)',
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                        }}>
                            <span className="material-icons" style={{ fontSize: 16, color: '#fff' }}>person</span>
                        </div>
                        <div style={{ minWidth: 0 }}>
                            <div style={{ fontSize: 12, fontWeight: 700, color: '#0f172a', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                                {item.lead_name || 'Unknown Lead'}
                            </div>
                            <div style={{ fontSize: 10.5, color: '#94a3b8', marginTop: 1 }}>
                                {item.lead_code || '-'} · {item.lead_phone || item.phone || '-'}
                            </div>
                        </div>
                    </div>
                    {item.encrypted_lead_id && (
                        <Link
                            href={`/telecaller/leads/${item.encrypted_lead_id}`}
                            style={{
                                display: 'flex', alignItems: 'center', gap: 4,
                                fontSize: 11, fontWeight: 700, color: '#fff',
                                textDecoration: 'none', whiteSpace: 'nowrap',
                                background: 'linear-gradient(135deg,#f43f5e,#e11d48)',
                                borderRadius: 10, padding: '5px 11px',
                                boxShadow: '0 3px 10px rgba(244,63,94,0.3)',
                            }}
                        >
                            <span className="material-icons" style={{ fontSize: 12 }}>call</span>
                            Call
                        </Link>
                    )}
                </div>
            ))}
        </div>
    );
}

// ─── Talk Time Display ────────────────────────────────────────────────────────
function TalkTimeDisplay({ stats, callbacks }) {
    const secs = stats.talk_time_secs ?? 0;
    const maxSecs = 8 * 3600;
    const pct = Math.min(100, Math.round((secs / maxSecs) * 100));

    return (
        <div>
            {/* Big time display */}
            <div style={{
                display: 'inline-flex', alignItems: 'baseline', gap: 6,
                background: 'linear-gradient(135deg,#6366f1,#4f46e5)',
                WebkitBackgroundClip: 'text',
                WebkitTextFillColor: 'transparent',
                backgroundClip: 'text',
                marginBottom: 6,
            }}>
                <span style={{ fontSize: 38, fontWeight: 800, letterSpacing: '-2px', lineHeight: 1, fontVariantNumeric: 'tabular-nums' }}>
                    {toTimeLabel(secs)}
                </span>
            </div>
            <div style={{ fontSize: 11, color: '#94a3b8', marginBottom: 16 }}>Total talk time today</div>

            {/* Progress arc represented as a bar */}
            <div style={{ marginBottom: 18 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6, fontSize: 10.5, color: '#b0bec5' }}>
                    <span>0h</span><span>{pct}% of 8h goal</span><span>8h</span>
                </div>
                <div style={{ background: 'rgba(241,245,249,0.9)', borderRadius: 20, height: 8, overflow: 'hidden', boxShadow: 'inset 0 2px 4px rgba(0,0,0,0.06)' }}>
                    <div style={{
                        width: `${pct}%`, height: '100%',
                        background: 'linear-gradient(90deg,#6366f1,#8b5cf6)',
                        borderRadius: 20,
                        boxShadow: '0 0 10px rgba(99,102,241,0.4)',
                        transition: 'width 0.8s cubic-bezier(.4,0,.2,1)',
                    }} />
                </div>
            </div>

            {/* Mini metrics */}
            <div style={{
                display: 'grid', gridTemplateColumns: '1fr 1fr 1fr',
                gap: 10, paddingTop: 14,
                borderTop: '1px solid rgba(226,232,240,0.6)',
            }}>
                {[
                    { val: stats.calls ?? 0,        label: 'Total Calls',  color: '#6366f1', bg: 'rgba(99,102,241,0.08)',  icon: 'phone' },
                    { val: callbacks.length,         label: 'Missed',       color: '#f43f5e', bg: 'rgba(244,63,94,0.08)',   icon: 'phone_missed' },
                    { val: stats.active_calls ?? 0, label: 'Active Now',   color: '#10b981', bg: 'rgba(16,185,129,0.08)',  icon: 'phone_in_talk' },
                ].map(m => (
                    <div key={m.label} style={{ textAlign: 'center', background: m.bg, borderRadius: 12, padding: '10px 6px' }}>
                        <span className="material-icons" style={{ fontSize: 15, color: m.color, display: 'block', marginBottom: 4 }}>{m.icon}</span>
                        <div style={{ fontSize: 20, fontWeight: 800, color: m.color, lineHeight: 1 }}>{m.val}</div>
                        <div style={{ fontSize: 9.5, fontWeight: 600, color: '#94a3b8', textTransform: 'uppercase', letterSpacing: '0.4px', marginTop: 4 }}>
                            {m.label}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

// ─── Follow-up Calendar ───────────────────────────────────────────────────────
function FollowupCalendar({ initialData }) {
    const todayDate = new Date();
    const todayY    = todayDate.getFullYear();
    const todayM    = todayDate.getMonth() + 1;
    const todayD    = todayDate.getDate();

    const [state,   setState]   = useState({ year: todayY, month: todayM, days: initialData ?? {} });
    const [loading, setLoading] = useState(false);

    function densityStyle(count) {
        if (!count) return null;
        if (count <= 3) return { bg: 'rgba(16,185,129,0.1)', border: 'rgba(16,185,129,0.3)',  color: '#065f46', dot: '#10b981' };
        if (count <= 7) return { bg: 'rgba(245,158,11,0.1)', border: 'rgba(245,158,11,0.3)',  color: '#92400e', dot: '#f59e0b' };
        return              { bg: 'rgba(244,63,94,0.1)',  border: 'rgba(244,63,94,0.3)',   color: '#be123c', dot: '#f43f5e' };
    }

    async function navigate(year, month) {
        setLoading(true);
        try {
            const res  = await fetch(`/telecaller/followups/calendar-data?year=${year}&month=${month}`, { headers: { Accept: 'application/json' } });
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
        <div style={{
            ...glass(0.80),
            borderRadius: 24,
            padding: '24px 26px',
            boxShadow: '0 6px 32px rgba(99,102,241,0.07), 0 2px 8px rgba(0,0,0,0.04)',
            position: 'relative', overflow: 'hidden',
        }}>
            {/* Background blob */}
            <div style={{
                position: 'absolute', left: -32, bottom: -32, width: 120, height: 120,
                borderRadius: '50%', background: 'rgba(16,185,129,0.08)', filter: 'blur(32px)',
                pointerEvents: 'none',
            }} />

            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20, flexWrap: 'wrap', gap: 12 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                    <div style={{
                        width: 34, height: 34, borderRadius: 10,
                        background: 'rgba(16,185,129,0.12)', display: 'flex', alignItems: 'center', justifyContent: 'center',
                        border: '1.5px solid rgba(16,185,129,0.22)',
                    }}>
                        <span className="material-icons" style={{ fontSize: 17, color: '#059669' }}>calendar_month</span>
                    </div>
                    <div>
                        <div style={{ fontSize: 13.5, fontWeight: 700, color: '#0f172a' }}>Follow-Up Calendar</div>
                        <div style={{ fontSize: 11, color: '#94a3b8', marginTop: 1 }}>Click highlighted dates to view follow-ups</div>
                    </div>
                </div>

                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                    {[
                        { bg: 'rgba(16,185,129,0.1)', border: 'rgba(16,185,129,0.3)', label: 'Low (1–3)', dot: '#10b981' },
                        { bg: 'rgba(245,158,11,0.1)', border: 'rgba(245,158,11,0.3)', label: 'Med (4–7)', dot: '#f59e0b' },
                        { bg: 'rgba(244,63,94,0.1)',  border: 'rgba(244,63,94,0.3)',  label: 'High (8+)', dot: '#f43f5e' },
                    ].map(({ dot, label }) => (
                        <span key={label} style={{ display: 'flex', alignItems: 'center', gap: 4, fontSize: 10.5, color: '#64748b' }}>
                            <span style={{ width: 8, height: 8, borderRadius: '50%', background: dot, display: 'inline-block', flexShrink: 0 }} />
                            {label}
                        </span>
                    ))}
                </div>

                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                    {[
                        { fn: prevMonth, icon: 'chevron_left' },
                        { fn: nextMonth, icon: 'chevron_right' },
                    ].map(({ fn, icon }, i) => (
                        <button key={i} onClick={fn} style={{
                            width: 30, height: 30, borderRadius: 9,
                            border: '1px solid rgba(226,232,240,0.8)',
                            background: 'rgba(255,255,255,0.8)',
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                            cursor: 'pointer', boxShadow: '0 2px 6px rgba(0,0,0,0.04)',
                        }}>
                            <span className="material-icons" style={{ fontSize: 16, color: '#64748b' }}>{icon}</span>
                        </button>
                    ))}
                    <span style={{ fontSize: 13, fontWeight: 700, color: '#334155', minWidth: 130, textAlign: 'center' }}>
                        {MONTH_NAMES[month - 1]} {year}
                    </span>
                </div>
            </div>

            <div style={{ opacity: loading ? 0.4 : 1, transition: 'opacity 0.2s' }}>
                {/* Day-of-week headers */}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7,1fr)', gap: 4, marginBottom: 6 }}>
                    {DOW_LABELS.map((d, i) => (
                        <div key={i} style={{
                            textAlign: 'center', fontSize: 10, fontWeight: 700,
                            color: '#b0bec5', padding: '3px 0',
                            textTransform: 'uppercase', letterSpacing: '0.4px',
                        }}>
                            {d}
                        </div>
                    ))}
                </div>

                {/* Day cells */}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7,1fr)', gap: 4 }}>
                    {cells.map((d, i) => {
                        if (d === null) return <div key={`e${i}`} />;
                        const key   = `${year}-${pad(month)}-${pad(d)}`;
                        const count = days[key] || 0;
                        const ds    = densityStyle(count);
                        const isToday = isThisMonth && d === todayD;
                        const isPast  = new Date(year, month - 1, d) < new Date(todayY, todayM - 1, todayD);
                        const bg = ds ? ds.bg : isToday ? 'rgba(99,102,241,0.08)' : isPast ? 'rgba(248,250,252,0.7)' : 'rgba(255,255,255,0.5)';
                        const border = ds ? ds.border : isToday ? 'rgba(99,102,241,0.4)' : 'rgba(226,232,240,0.6)';
                        const numColor = ds ? ds.color : isPast ? '#cbd5e1' : '#475569';
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
                                    borderRadius: 10, border: `1.5px solid ${border}`,
                                    background: bg, padding: '5px 2px',
                                    textAlign: 'center',
                                    cursor: count > 0 ? 'pointer' : 'default',
                                    outline: isToday ? '2px solid rgba(99,102,241,0.5)' : 'none',
                                    outlineOffset: 2,
                                    transition: 'all 0.15s',
                                    boxShadow: isToday ? '0 0 0 3px rgba(99,102,241,0.1)' : 'none',
                                }}
                            >
                                <div style={{ fontSize: 11, fontWeight: isToday ? 800 : 500, color: numColor, lineHeight: 1.4 }}>
                                    {d}
                                </div>
                                {count > 0
                                    ? <div style={{
                                        width: 5, height: 5, borderRadius: '50%',
                                        background: ds?.dot, margin: '2px auto 0',
                                    }} />
                                    : <div style={{ height: 9 }} />
                                }
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

// ─── Quick Action Pill ────────────────────────────────────────────────────────
function QuickActionPill({ href, icon, label, tone = 'violet', onClick }) {
    const [hov, setHov] = useState(false);
    const t = G[tone] ?? G.violet;
    const inner = (
        <div
            onMouseEnter={() => setHov(true)}
            onMouseLeave={() => setHov(false)}
            style={{
                display: 'flex', alignItems: 'center', gap: 6,
                padding: '8px 16px', borderRadius: 14,
                background: hov ? t.grad : `rgba(255,255,255,0.85)`,
                border: `1.5px solid ${hov ? 'transparent' : t.shadow.replace('0.28','0.2')}`,
                boxShadow: hov ? `0 6px 18px ${t.shadow}` : '0 2px 8px rgba(0,0,0,0.04)',
                fontSize: 12, fontWeight: 700,
                color: hov ? '#fff' : t.text,
                textDecoration: 'none',
                transition: 'all 0.22s cubic-bezier(.4,0,.2,1)',
                transform: hov ? 'translateY(-2px)' : 'none',
                cursor: 'pointer',
                whiteSpace: 'nowrap',
            }}
        >
            <span className="material-icons" style={{ fontSize: 15 }}>{icon}</span>
            {label}
        </div>
    );
    if (onClick) return <button onClick={onClick} style={{ background: 'none', border: 'none', padding: 0 }}>{inner}</button>;
    return <Link href={href} style={{ textDecoration: 'none' }}>{inner}</Link>;
}

// ─── Main Dashboard ───────────────────────────────────────────────────────────
export default function Dashboard({ stats: initialStats, missed_callbacks: initialCallbacks, followup_calendar, call_outcomes: initialOutcomes }) {
    const [stats,       setStats]       = useState(initialStats     ?? {});
    const [callbacks,   setCallbacks]   = useState(initialCallbacks ?? []);
    const [outcomes,    setOutcomes]    = useState(initialOutcomes  ?? {});
    const [lastRefresh, setLastRefresh] = useState(new Date());
    const [refreshAnim, setRefreshAnim] = useState(false);
    const missedRef = useRef(null);
    const { greeting, greetIcon, dateStr, timeStr } = useLiveClock();

    const fetchSnapshot = useCallback(async () => {
        setRefreshAnim(true);
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
            if (Array.isArray(data.missed_callbacks)) setCallbacks(data.missed_callbacks);
            if (data.call_outcomes && typeof data.call_outcomes === 'object') setOutcomes(data.call_outcomes);
            setLastRefresh(new Date());
        } catch (_) {}
        setTimeout(() => setRefreshAnim(false), 600);
    }, []);

    useEffect(() => {
        fetchSnapshot();
        const t = setInterval(fetchSnapshot, 45_000);
        return () => clearInterval(t);
    }, [fetchSnapshot]);

    const showAlert = (stats.followups ?? 0) > 0 || (stats.overdue ?? 0) > 0;

    return (
        <>
            <Head title="Dashboard" />

            {/* ── Hero greeting card ─────────────────────────────────────────── */}
            <div style={{
                background: 'linear-gradient(135deg,#4f46e5 0%,#6366f1 40%,#8b5cf6 100%)',
                borderRadius: 28,
                padding: '28px 32px',
                marginBottom: 22,
                position: 'relative',
                overflow: 'hidden',
                boxShadow: '0 16px 56px rgba(99,102,241,0.3), 0 4px 16px rgba(79,70,229,0.2)',
            }}>
                {/* Decorative blobs */}
                <div style={{ position:'absolute', top:-40, right:-20, width:180, height:180, borderRadius:'50%', background:'rgba(255,255,255,0.06)', pointerEvents:'none' }} />
                <div style={{ position:'absolute', bottom:-50, right:60, width:140, height:140, borderRadius:'50%', background:'rgba(255,255,255,0.04)', pointerEvents:'none' }} />
                <div style={{ position:'absolute', top:10, left:'40%', width:80, height:80, borderRadius:'50%', background:'rgba(255,255,255,0.05)', pointerEvents:'none' }} />

                <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center', flexWrap:'wrap', gap:16, position:'relative' }}>
                    <div>
                        <div style={{ display:'flex', alignItems:'center', gap:8, marginBottom:6 }}>
                            <span className="material-icons" style={{ fontSize:18, color:'rgba(255,255,255,0.85)' }}>{greetIcon}</span>
                            <span style={{ fontSize:12, fontWeight:600, color:'rgba(255,255,255,0.75)', textTransform:'uppercase', letterSpacing:'1.2px' }}>
                                {greeting}
                            </span>
                        </div>
                        <h2 style={{ fontSize:26, fontWeight:800, color:'#fff', margin:0, letterSpacing:'-0.5px', lineHeight:1.2 }}>
                            Telecaller Dashboard
                        </h2>
                        <div style={{ fontSize:12, color:'rgba(255,255,255,0.65)', marginTop:5 }}>{dateStr}</div>
                    </div>

                    <div style={{ display:'flex', alignItems:'center', gap:12 }}>
                        {/* Live clock */}
                        <div style={{
                            ...glass(0.12, 12),
                            border: '1px solid rgba(255,255,255,0.25)',
                            borderRadius: 16, padding:'10px 18px',
                            textAlign:'center',
                        }}>
                            <div style={{ fontSize:22, fontWeight:800, color:'#fff', letterSpacing:'-0.5px', fontVariantNumeric:'tabular-nums', lineHeight:1 }}>
                                {timeStr}
                            </div>
                            <div style={{ fontSize:10, color:'rgba(255,255,255,0.6)', marginTop:3, textTransform:'uppercase', letterSpacing:'0.8px' }}>Live Clock</div>
                        </div>

                        <button
                            onClick={fetchSnapshot}
                            style={{
                                display:'flex', alignItems:'center', gap:6,
                                ...glass(0.15, 12),
                                border:'1px solid rgba(255,255,255,0.25)',
                                borderRadius:12, padding:'9px 16px',
                                fontSize:12, fontWeight:700, color:'#fff',
                                cursor:'pointer',
                            }}
                        >
                            <span className="material-icons" style={{ fontSize:15, animation: refreshAnim ? 'spin 0.5s linear' : 'none' }}>refresh</span>
                            Refresh
                        </button>
                    </div>
                </div>

                {/* Summary strip */}
                <div style={{
                    display:'flex', gap:10, marginTop:20, flexWrap:'wrap',
                }}>
                    {[
                        { val: stats.assigned  ?? 0, label:'Assigned',    icon:'assignment_ind' },
                        { val: stats.calls     ?? 0, label:'Calls Today', icon:'phone_in_talk'  },
                        { val: stats.followups ?? 0, label:'Follow-ups',  icon:'event'          },
                        { val: callbacks.length,      label:'Missed',      icon:'phone_missed'   },
                    ].map(s => (
                        <div key={s.label} style={{
                            ...glass(0.12, 10),
                            border:'1px solid rgba(255,255,255,0.2)',
                            borderRadius:14, padding:'8px 16px',
                            display:'flex', alignItems:'center', gap:8,
                        }}>
                            <span className="material-icons" style={{ fontSize:15, color:'rgba(255,255,255,0.8)' }}>{s.icon}</span>
                            <div>
                                <div style={{ fontSize:18, fontWeight:800, color:'#fff', lineHeight:1 }}>{s.val}</div>
                                <div style={{ fontSize:9.5, color:'rgba(255,255,255,0.6)', textTransform:'uppercase', letterSpacing:'0.5px', marginTop:2 }}>{s.label}</div>
                            </div>
                        </div>
                    ))}
                    <div style={{ marginLeft:'auto', display:'flex', alignItems:'flex-end' }}>
                        <span style={{ fontSize:10, color:'rgba(255,255,255,0.45)' }}>
                            Updated {lastRefresh.toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'})}
                        </span>
                    </div>
                </div>
            </div>

            {/* ── Follow-up alert ────────────────────────────────────────────── */}
            {showAlert && (
                <div style={{
                    ...glass(0.85),
                    border:'1px solid rgba(245,158,11,0.25)',
                    borderRadius:16, padding:'12px 18px',
                    marginBottom:18,
                    display:'flex', alignItems:'center', gap:10,
                    boxShadow:'0 4px 16px rgba(245,158,11,0.1)',
                }}>
                    <div style={{
                        width:34, height:34, borderRadius:10, flexShrink:0,
                        background:'linear-gradient(135deg,#f59e0b,#d97706)',
                        display:'flex', alignItems:'center', justifyContent:'center',
                        boxShadow:'0 4px 12px rgba(245,158,11,0.3)',
                    }}>
                        <span className="material-icons" style={{ fontSize:17, color:'#fff' }}>notifications_active</span>
                    </div>
                    <span style={{ fontSize:12.5, color:'#78350f', flex:1 }}>
                        <strong>Reminder:</strong> {stats.followups ?? 0} follow-up{(stats.followups ?? 0) !== 1 ? 's' : ''} due today
                        {(stats.overdue ?? 0) > 0 && <span style={{ color:'#e11d48', fontWeight:700 }}>, {stats.overdue} overdue</span>}.
                    </span>
                    <Link
                        href="/telecaller/leads?status=follow_up"
                        style={{
                            fontSize:12, fontWeight:700, color:'#fff', textDecoration:'none', whiteSpace:'nowrap',
                            background:'linear-gradient(135deg,#f59e0b,#d97706)',
                            borderRadius:10, padding:'6px 14px',
                            boxShadow:'0 3px 10px rgba(245,158,11,0.3)',
                        }}
                    >
                        View →
                    </Link>
                </div>
            )}

            {/* ── KPI stat row ──────────────────────────────────────────────── */}
            <div className="row g-3 mb-4">
                {[
                    { label:'Assigned Leads',   value:stats.assigned,  icon:'assignment_ind', tone:'violet'  },
                    { label:'New Leads',         value:stats.new_leads, icon:'fiber_new',      tone:'emerald', badge:'New' },
                    { label:'Follow-ups Today',  value:stats.followups, icon:'event',          tone:'amber'   },
                    { label:'Overdue',           value:stats.overdue,   icon:'warning_amber',  tone:'rose',   badge:'Urgent' },
                    { label:'Calls Today',       value:stats.calls,     icon:'phone_in_talk',  tone:'sky'     },
                ].map(c => (
                    <div key={c.label} className="col-6 col-md-4 col-lg">
                        <KpiCard {...c} />
                    </div>
                ))}
            </div>

            {/* ── Analytics row ─────────────────────────────────────────────── */}
            <div className="row g-3 mb-4">
                {/* Talk Time */}
                <div className="col-md-4">
                    <GlassCard title="Talk Time Today" subtitle="Live call tracking" icon="timer" tone="violet" minH={220}>
                        <TalkTimeDisplay stats={stats} callbacks={callbacks} />
                    </GlassCard>
                </div>

                {/* Call Outcomes */}
                <div className="col-md-4">
                    <GlassCard title="Today's Outcomes" subtitle="Result breakdown for all calls made" icon="bar_chart" tone="emerald" minH={220}>
                        <CallOutcomeChart outcomes={outcomes} />
                    </GlassCard>
                </div>

                {/* Missed Callbacks */}
                <div className="col-md-4" ref={missedRef}>
                    <GlassCard
                        title="Missed Callbacks"
                        subtitle={`${callbacks.length} pending callback${callbacks.length !== 1 ? 's' : ''}`}
                        icon="phone_missed"
                        tone="rose"
                        minH={220}
                    >
                        <MissedCallbacksPanel callbacks={callbacks} />
                    </GlassCard>
                </div>
            </div>

            {/* ── Quick actions strip ───────────────────────────────────────── */}
            <div style={{
                ...glass(0.80),
                borderRadius:20,
                padding:'16px 22px',
                marginBottom:22,
                display:'flex', alignItems:'center', gap:10, flexWrap:'wrap',
                boxShadow:'0 4px 20px rgba(99,102,241,0.06)',
            }}>
                <span style={{ fontSize:10, fontWeight:800, color:'#b0bec5', textTransform:'uppercase', letterSpacing:'1.2px', marginRight:4 }}>
                    Quick Actions
                </span>
                <QuickActionPill href="/telecaller/leads?status=new"       icon="fiber_new"    label="New Leads"      tone="violet"  />
                <QuickActionPill href="/telecaller/leads?status=follow_up" icon="event"        label="Follow-ups Due" tone="amber"   />
                <QuickActionPill href="/telecaller/calls/missed"           icon="phone_missed" label="Missed Calls"   tone="rose"    />
                <QuickActionPill href="/telecaller/performance/daily"      icon="trending_up"  label="Performance"    tone="emerald" />
                <QuickActionPill
                    icon="arrow_downward" label="Missed Panel" tone="sky"
                    onClick={() => missedRef.current?.scrollIntoView({ behavior:'smooth', block:'start' })}
                />
            </div>

            {/* ── Follow-up calendar ────────────────────────────────────────── */}
            <FollowupCalendar initialData={followup_calendar} />

            <style>{`
                @keyframes spin { from { transform:rotate(0deg); } to { transform:rotate(360deg); } }
            `}</style>
        </>
    );
}
