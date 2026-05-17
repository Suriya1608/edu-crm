import { Head, router } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';

// ── Design tokens ────────────────────────────────────────────────
const ORG    = '#FF5C1A';
const ORGL   = '#FF8042';
const DARKC  = '#1A1A2E';
const BORDER = '#EAEAED';
const TEXT   = '#1A1A2E';
const MUTED  = '#9EA3B0';
const WHITE  = '#FFFFFF';
const BGGRAY = '#F5F5F7';

const card = (extra = {}) => ({
    background:   WHITE,
    borderRadius: 14,
    boxShadow:    '0 2px 16px rgba(0,0,0,0.07)',
    border:       `1px solid ${BORDER}`,
    ...extra,
});

// ── Static data ──────────────────────────────────────────────────
const MONTH_NAMES = [
    'January','February','March','April','May','June',
    'July','August','September','October','November','December',
];
const DOW = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

// ── Helpers ──────────────────────────────────────────────────────
function buildCalendar(year, month) {
    const first = new Date(year, month - 1, 1);
    const last  = new Date(year, month, 0);
    const days  = [];
    for (let i = 0; i < first.getDay(); i++) days.push(null);
    for (let d = 1; d <= last.getDate(); d++) {
        days.push(`${year}-${String(month).padStart(2,'0')}-${String(d).padStart(2,'0')}`);
    }
    return days;
}

function formatDate(date) {
    return new Date(date + 'T00:00:00').toLocaleDateString('en-IN',
        { weekday: 'short', day: 'numeric', month: 'short' });
}

// ── Component ────────────────────────────────────────────────────
export default function AvailabilityIndex({ blocked_dates, year, month, today, urls }) {
    const [blockedMap, setBlockedMap] = useState(() => {
        const m = {};
        blocked_dates.forEach(b => { m[b.date] = b.reason ?? ''; });
        return m;
    });

    // Multi-select state
    const [selectedSet, setSelectedSet] = useState(new Set());
    const [reason, setReason]           = useState('');
    const [submitting, setSubmitting]   = useState(false);

    // Hover state for day cells
    const [hoveredDay, setHoveredDay]   = useState(null);

    // Drag state (refs so mouseenter handlers don't go stale)
    const isDragging = useRef(false);
    const dragMode   = useRef('select'); // 'select' | 'deselect'

    const days = buildCalendar(year, month);

    // Stop drag on mouseup anywhere
    useEffect(() => {
        const stop = () => { isDragging.current = false; };
        window.addEventListener('mouseup', stop);
        return () => window.removeEventListener('mouseup', stop);
    }, []);

    function prevMonth() {
        let y = year, m = month - 1;
        if (m < 1) { m = 12; y -= 1; }
        router.get(window.location.pathname, { year: y, month: m }, { preserveState: false });
    }
    function nextMonth() {
        let y = year, m = month + 1;
        if (m > 12) { m = 1; y += 1; }
        router.get(window.location.pathname, { year: y, month: m }, { preserveState: false });
    }

    // Called on mousedown — starts drag and toggles the cell
    function handleMouseDown(date) {
        if (!date || date < today || blockedMap[date] !== undefined) return;
        isDragging.current = true;
        const alreadySelected = selectedSet.has(date);
        dragMode.current = alreadySelected ? 'deselect' : 'select';
        setSelectedSet(prev => {
            const next = new Set(prev);
            alreadySelected ? next.delete(date) : next.add(date);
            return next;
        });
    }

    // Called on mouseenter while dragging
    function handleMouseEnter(date) {
        if (!isDragging.current || !date || date < today || blockedMap[date] !== undefined) return;
        setSelectedSet(prev => {
            const next = new Set(prev);
            dragMode.current === 'select' ? next.add(date) : next.delete(date);
            return next;
        });
    }

    // Unblock a single already-blocked date
    function unblockDate(date) {
        if (!confirm('Unblock ' + formatDate(date) + '?')) return;
        setSubmitting(true);
        router.delete(urls.destroy.replace('__DATE__', date), {
            onSuccess: () => {
                setBlockedMap(prev => { const n = { ...prev }; delete n[date]; return n; });
            },
            onFinish: () => setSubmitting(false),
        });
    }

    // Submit all selected dates
    function submitBlock(e) {
        e.preventDefault();
        const dates = [...selectedSet].sort();
        if (!dates.length) return;
        setSubmitting(true);
        router.post(urls.store, { dates, reason }, {
            onSuccess: () => {
                const extra = {};
                dates.forEach(d => { extra[d] = reason; });
                setBlockedMap(prev => ({ ...prev, ...extra }));
                setSelectedSet(new Set());
                setReason('');
            },
            onFinish: () => setSubmitting(false),
        });
    }

    function clearSelection() {
        setSelectedSet(new Set());
        setReason('');
    }

    // Returns style object for each calendar day cell
    function dayStyle(date) {
        if (!date) return {};
        const isPast    = date < today;
        const isToday   = date === today;
        const isBlocked = blockedMap[date] !== undefined;
        const isSel     = selectedSet.has(date);
        const isHovered = hoveredDay === date;

        const base = {
            borderRadius: 10,
            cursor:       'pointer',
            userSelect:   'none',
            transition:   'background 0.12s, border-color 0.12s',
        };

        if (isSel) return {
            ...base,
            background: `${ORG}15`,
            border:     `1.5px solid ${ORG}`,
            color:      ORG,
            fontWeight: 700,
            boxShadow:  `0 0 0 2px ${ORG}30`,
        };
        if (isBlocked) return {
            ...base,
            background: `${ORG}10`,
            border:     `1.5px solid ${ORG}`,
            color:      ORG,
            fontWeight: 700,
        };
        if (isToday) return {
            ...base,
            background: BGGRAY,
            border:     `1px solid ${BORDER}`,
            color:      TEXT,
            fontWeight: 700,
        };
        if (isPast) return {
            ...base,
            color:        MUTED,
            cursor:       'default',
            border:       '1px solid transparent',
            background:   'transparent',
            fontWeight:   400,
        };
        // Normal future date
        return {
            ...base,
            background: isHovered ? BGGRAY : WHITE,
            border:     `1px solid ${BORDER}`,
            color:      TEXT,
            fontWeight: 400,
        };
    }

    const selCount = selectedSet.size;

    return (
        <>
            <Head title="My Availability" />

            {/* Global font injection */}
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Work+Sans:wght@300;400;500;600;700;800;900&display=swap');
                *, *::before, *::after { font-family: 'Work Sans', sans-serif !important; }
                .avl-nav-btn:hover { background: ${BGGRAY} !important; }
                .avl-unblock-btn:hover { color: ${ORG} !important; }
            `}</style>

            <div style={{ padding: '28px 16px' }}>

                {/* ── Page header ── */}
                <div style={{ marginBottom: 24 }}>
                    <h1 style={{ fontSize: 22, fontWeight: 800, color: TEXT, margin: 0 }}>
                        My Availability Calendar
                    </h1>
                    <p style={{ color: MUTED, marginTop: 4, marginBottom: 0, fontSize: 13 }}>
                        Click or drag across future dates to select them, then block all at once.
                    </p>
                </div>

                <div style={{ display: 'flex', gap: 18, flexWrap: 'wrap', alignItems: 'flex-start' }}>

                    {/* ── Calendar card ── */}
                    <div style={{
                        ...card(),
                        flex:       '1 1 420px',
                        padding:    20,
                        userSelect: 'none',
                    }}>

                        {/* Month navigation */}
                        <div style={{
                            display:        'flex',
                            alignItems:     'center',
                            justifyContent: 'space-between',
                            marginBottom:   18,
                        }}>
                            <button
                                className="avl-nav-btn"
                                onClick={prevMonth}
                                style={{
                                    background:   WHITE,
                                    border:       `1px solid ${BORDER}`,
                                    borderRadius: 8,
                                    padding:      '5px 13px',
                                    cursor:       'pointer',
                                    color:        TEXT,
                                    fontSize:     18,
                                    lineHeight:   1,
                                    transition:   'background 0.12s',
                                }}
                            >
                                ‹
                            </button>

                            <span style={{ fontSize: 18, fontWeight: 800, color: TEXT, letterSpacing: '-0.3px' }}>
                                {MONTH_NAMES[month - 1]} {year}
                            </span>

                            <button
                                className="avl-nav-btn"
                                onClick={nextMonth}
                                style={{
                                    background:   WHITE,
                                    border:       `1px solid ${BORDER}`,
                                    borderRadius: 8,
                                    padding:      '5px 13px',
                                    cursor:       'pointer',
                                    color:        TEXT,
                                    fontSize:     18,
                                    lineHeight:   1,
                                    transition:   'background 0.12s',
                                }}
                            >
                                ›
                            </button>
                        </div>

                        {/* DOW headers */}
                        <div style={{
                            display:             'grid',
                            gridTemplateColumns: 'repeat(7,1fr)',
                            gap:                 4,
                            marginBottom:        6,
                        }}>
                            {DOW.map(d => (
                                <div key={d} style={{
                                    textAlign:     'center',
                                    fontSize:      10,
                                    fontWeight:    700,
                                    color:         MUTED,
                                    textTransform: 'uppercase',
                                    letterSpacing: '0.5px',
                                    padding:       '4px 0',
                                }}>
                                    {d}
                                </div>
                            ))}
                        </div>

                        {/* Day grid */}
                        <div style={{
                            display:             'grid',
                            gridTemplateColumns: 'repeat(7,1fr)',
                            gap:                 4,
                        }}>
                            {days.map((date, i) => (
                                <div
                                    key={i}
                                    onMouseDown={() => handleMouseDown(date)}
                                    onMouseEnter={() => {
                                        handleMouseEnter(date);
                                        setHoveredDay(date);
                                    }}
                                    onMouseLeave={() => setHoveredDay(null)}
                                    onClick={() => {
                                        // Click on an already-blocked date → unblock
                                        if (date && date >= today && blockedMap[date] !== undefined) {
                                            unblockDate(date);
                                        }
                                    }}
                                    style={{
                                        height:         44,
                                        display:        'flex',
                                        alignItems:     'center',
                                        justifyContent: 'center',
                                        fontSize:       13,
                                        fontWeight:     500,
                                        position:       'relative',
                                        ...(date ? dayStyle(date) : {}),
                                    }}
                                >
                                    {date ? parseInt(date.slice(-2)) : ''}
                                    {date && blockedMap[date] !== undefined && (
                                        <span style={{
                                            position:        'absolute',
                                            bottom:          4,
                                            left:            '50%',
                                            transform:       'translateX(-50%)',
                                            width:           5,
                                            height:          5,
                                            borderRadius:    '50%',
                                            background:      ORG,
                                        }} />
                                    )}
                                </div>
                            ))}
                        </div>

                        {/* Legend */}
                        <div style={{
                            display:    'flex',
                            gap:        16,
                            marginTop:  16,
                            flexWrap:   'wrap',
                            alignItems: 'center',
                        }}>
                            {[
                                { bg: `${ORG}10`, border: ORG,    label: 'Blocked'  },
                                { bg: BGGRAY,     border: BORDER, label: 'Today'    },
                                { bg: `${ORG}15`, border: ORG,    label: 'Selected' },
                            ].map(l => (
                                <div key={l.label} style={{
                                    display:    'flex',
                                    alignItems: 'center',
                                    gap:        6,
                                    fontSize:   12,
                                }}>
                                    <div style={{
                                        width:        14,
                                        height:       14,
                                        borderRadius: 4,
                                        background:   l.bg,
                                        border:       `1.5px solid ${l.border}`,
                                        flexShrink:   0,
                                    }} />
                                    <span style={{ color: MUTED }}>{l.label}</span>
                                </div>
                            ))}
                            <span style={{ fontSize: 12, color: MUTED, marginLeft: 'auto' }}>
                                Click blocked date to unblock
                            </span>
                        </div>
                    </div>

                    {/* ── Side panel ── */}
                    <div style={{ flex: '0 1 250px', display: 'flex', flexDirection: 'column', gap: 16 }}>

                        {/* Block form (shown when dates are selected) */}
                        {selCount > 0 ? (
                            <div style={{ ...card(), padding: 20 }}>
                                <div style={{
                                    display:        'flex',
                                    alignItems:     'center',
                                    justifyContent: 'space-between',
                                    marginBottom:   12,
                                }}>
                                    <h3 style={{ fontSize: 14, fontWeight: 700, color: TEXT, margin: 0 }}>
                                        Block {selCount} Date{selCount > 1 ? 's' : ''}
                                    </h3>
                                    <span style={{
                                        fontSize:     11,
                                        fontWeight:   700,
                                        background:   `${ORG}15`,
                                        color:        ORG,
                                        borderRadius: 20,
                                        padding:      '2px 10px',
                                    }}>
                                        {selCount}
                                    </span>
                                </div>

                                {/* Selected date chips */}
                                <div style={{
                                    display:      'flex',
                                    flexWrap:     'wrap',
                                    gap:          5,
                                    marginBottom: 14,
                                    maxHeight:    100,
                                    overflowY:    'auto',
                                }}>
                                    {[...selectedSet].sort().map(d => (
                                        <span key={d} style={{
                                            fontSize:    11,
                                            fontWeight:  600,
                                            background:  `${ORG}12`,
                                            color:       ORG,
                                            borderRadius: 20,
                                            padding:     '3px 8px',
                                            display:     'inline-flex',
                                            alignItems:  'center',
                                            gap:         4,
                                        }}>
                                            {formatDate(d)}
                                            <span
                                                style={{ cursor: 'pointer', fontWeight: 700, fontSize: 13, lineHeight: 1 }}
                                                onClick={() => setSelectedSet(prev => {
                                                    const n = new Set(prev); n.delete(d); return n;
                                                })}
                                            >
                                                ×
                                            </span>
                                        </span>
                                    ))}
                                </div>

                                <form onSubmit={submitBlock}>
                                    <label style={{
                                        fontSize:     11,
                                        fontWeight:   700,
                                        color:        MUTED,
                                        textTransform: 'uppercase',
                                        letterSpacing: '0.5px',
                                        display:      'block',
                                        marginBottom: 6,
                                    }}>
                                        Reason (optional)
                                    </label>
                                    <textarea
                                        value={reason}
                                        onChange={e => setReason(e.target.value)}
                                        placeholder="e.g. Medical leave"
                                        maxLength={191}
                                        rows={2}
                                        style={{
                                            width:        '100%',
                                            padding:      '9px 12px',
                                            borderRadius: 10,
                                            border:       `1px solid ${BORDER}`,
                                            fontSize:     13,
                                            color:        TEXT,
                                            marginBottom: 14,
                                            outline:      'none',
                                            boxSizing:    'border-box',
                                            resize:       'vertical',
                                            background:   WHITE,
                                        }}
                                    />
                                    <div style={{ display: 'flex', gap: 8 }}>
                                        {/* Primary – orange */}
                                        <button
                                            type="submit"
                                            disabled={submitting}
                                            style={{
                                                flex:         1,
                                                padding:      '10px 0',
                                                borderRadius: 10,
                                                background:   submitting ? ORGL : ORG,
                                                color:        WHITE,
                                                border:       'none',
                                                fontWeight:   600,
                                                fontSize:     13,
                                                cursor:       submitting ? 'not-allowed' : 'pointer',
                                                transition:   'background 0.15s',
                                            }}
                                        >
                                            {submitting ? 'Saving…' : `Block ${selCount > 1 ? selCount + ' Dates' : 'Date'}`}
                                        </button>
                                        {/* Ghost secondary */}
                                        <button
                                            type="button"
                                            onClick={clearSelection}
                                            style={{
                                                padding:      '10px 14px',
                                                borderRadius: 10,
                                                background:   WHITE,
                                                color:        MUTED,
                                                border:       `1px solid ${BORDER}`,
                                                fontWeight:   600,
                                                fontSize:     13,
                                                cursor:       'pointer',
                                                transition:   'background 0.12s',
                                            }}
                                            onMouseOver={e => e.currentTarget.style.background = BGGRAY}
                                            onMouseOut={e => e.currentTarget.style.background = WHITE}
                                        >
                                            Clear
                                        </button>
                                    </div>
                                </form>
                            </div>
                        ) : (
                            <div style={{
                                background:   BGGRAY,
                                borderRadius: 14,
                                border:       `2px dashed ${BORDER}`,
                                padding:      24,
                                textAlign:    'center',
                            }}>
                                <span
                                    className="material-icons"
                                    style={{ fontSize: 38, color: MUTED, display: 'block', marginBottom: 8 }}
                                >
                                    touch_app
                                </span>
                                <p style={{ color: MUTED, fontSize: 13, margin: 0, lineHeight: 1.6 }}>
                                    Click dates or<br />drag to select multiple
                                </p>
                            </div>
                        )}

                        {/* Blocked this month list */}
                        <div style={{ ...card(), padding: 20 }}>
                            <div style={{
                                fontSize:      13,
                                fontWeight:    700,
                                color:         TEXT,
                                paddingBottom: 10,
                                marginBottom:  12,
                                borderBottom:  `1px solid ${BORDER}`,
                                display:       'flex',
                                alignItems:    'center',
                                gap:           8,
                            }}>
                                Blocked This Month
                                {Object.keys(blockedMap).length > 0 && (
                                    <span style={{
                                        fontSize:     11,
                                        fontWeight:   700,
                                        background:   `${ORG}12`,
                                        color:        ORG,
                                        borderRadius: 20,
                                        padding:      '2px 8px',
                                    }}>
                                        {Object.keys(blockedMap).length}
                                    </span>
                                )}
                            </div>

                            {Object.keys(blockedMap).length === 0 ? (
                                <p style={{ color: MUTED, fontSize: 12, margin: 0 }}>No blocked dates.</p>
                            ) : (
                                <ul style={{
                                    margin:         0,
                                    padding:        0,
                                    listStyle:      'none',
                                    display:        'flex',
                                    flexDirection:  'column',
                                    gap:            8,
                                    maxHeight:      200,
                                    overflowY:      'auto',
                                }}>
                                    {Object.entries(blockedMap).sort().map(([date, rsn]) => (
                                        <li key={date} style={{
                                            display:     'flex',
                                            alignItems:  'flex-start',
                                            justifyContent: 'space-between',
                                            gap:         6,
                                        }}>
                                            <div>
                                                <span style={{ fontSize: 12, fontWeight: 600, color: ORG }}>
                                                    {new Date(date + 'T00:00:00').toLocaleDateString('en-IN',
                                                        { day: 'numeric', month: 'short', weekday: 'short' })}
                                                </span>
                                                {rsn && (
                                                    <span style={{ fontSize: 11, color: MUTED, display: 'block' }}>
                                                        {rsn}
                                                    </span>
                                                )}
                                            </div>
                                            <button
                                                className="avl-unblock-btn"
                                                disabled={submitting}
                                                onClick={() => unblockDate(date)}
                                                style={{
                                                    background:  'none',
                                                    border:      'none',
                                                    color:       MUTED,
                                                    cursor:      'pointer',
                                                    padding:     0,
                                                    fontSize:    18,
                                                    lineHeight:  1,
                                                    flexShrink:  0,
                                                    transition:  'color 0.12s',
                                                }}
                                                title="Unblock"
                                            >
                                                ×
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
