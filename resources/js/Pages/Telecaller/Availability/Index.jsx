import { Head, router } from '@inertiajs/react';
import { useState, useRef, useEffect, useCallback } from 'react';

const MONTH_NAMES = [
    'January','February','March','April','May','June',
    'July','August','September','October','November','December',
];
const DOW = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

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

    // Drag state (refs so mouseenter handlers don't go stale)
    const isDragging  = useRef(false);
    const dragMode    = useRef('select'); // 'select' | 'deselect'

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

    function dayStyle(date) {
        if (!date) return {};
        const isPast    = date < today;
        const isToday   = date === today;
        const isBlocked = blockedMap[date] !== undefined;
        const isSel     = selectedSet.has(date);

        if (isSel) return {
            background: '#6366f1', color: '#fff',
            border: '2px solid #4f46e5', borderRadius: 10,
            cursor: 'pointer', userSelect: 'none',
        };
        if (isBlocked) return {
            background: '#fef2f2', color: '#ef4444',
            border: '2px solid #fca5a5', borderRadius: 10,
            cursor: 'pointer', fontWeight: 700, userSelect: 'none',
        };
        if (isToday) return {
            background: '#eff6ff', color: '#6366f1',
            border: '2px solid #c7d2fe', borderRadius: 10,
            cursor: 'pointer', fontWeight: 700, userSelect: 'none',
        };
        if (isPast) return {
            color: '#cbd5e1', borderRadius: 10,
            cursor: 'default', userSelect: 'none',
        };
        return {
            borderRadius: 10, cursor: 'pointer',
            border: '1px solid transparent', userSelect: 'none',
        };
    }

    const selCount = selectedSet.size;

    return (
        <>
            <Head title="My Availability" />

            <div style={{ maxWidth: 800, margin: '0 auto', padding: '24px 16px' }}>

                {/* Header */}
                <div style={{ marginBottom: 24 }}>
                    <h1 style={{ fontSize: 22, fontWeight: 700, color: '#0f172a', margin: 0 }}>
                        My Availability Calendar
                    </h1>
                    <p style={{ color: '#64748b', marginTop: 4, fontSize: 14 }}>
                        Click or drag across future dates to select them, then block all at once.
                    </p>
                </div>

                <div style={{ display: 'flex', gap: 20, flexWrap: 'wrap' }}>

                    {/* Calendar */}
                    <div style={{ flex: '1 1 420px', background: '#fff', borderRadius: 14,
                        border: '1px solid #e2e8f0', padding: 20,
                        boxShadow: '0 1px 4px rgba(0,0,0,0.05)', userSelect: 'none' }}>

                        {/* Month nav */}
                        <div style={{ display: 'flex', alignItems: 'center',
                            justifyContent: 'space-between', marginBottom: 16 }}>
                            <button onClick={prevMonth}
                                style={{ background: 'none', border: '1px solid #e2e8f0', borderRadius: 8,
                                    padding: '4px 12px', cursor: 'pointer', color: '#64748b', fontSize: 18 }}>
                                ‹
                            </button>
                            <span style={{ fontWeight: 700, color: '#0f172a', fontSize: 16 }}>
                                {MONTH_NAMES[month - 1]} {year}
                            </span>
                            <button onClick={nextMonth}
                                style={{ background: 'none', border: '1px solid #e2e8f0', borderRadius: 8,
                                    padding: '4px 12px', cursor: 'pointer', color: '#64748b', fontSize: 18 }}>
                                ›
                            </button>
                        </div>

                        {/* DOW headers */}
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7,1fr)', gap: 4, marginBottom: 6 }}>
                            {DOW.map(d => (
                                <div key={d} style={{ textAlign: 'center', fontSize: 11, fontWeight: 600,
                                    color: '#94a3b8', padding: '4px 0' }}>{d}</div>
                            ))}
                        </div>

                        {/* Day grid */}
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7,1fr)', gap: 4 }}>
                            {days.map((date, i) => (
                                <div key={i}
                                    onMouseDown={() => handleMouseDown(date)}
                                    onMouseEnter={() => handleMouseEnter(date)}
                                    onClick={() => {
                                        // Click on an already-blocked date → unblock
                                        if (date && date >= today && blockedMap[date] !== undefined) {
                                            unblockDate(date);
                                        }
                                    }}
                                    style={{
                                        height: 44, display: 'flex', alignItems: 'center',
                                        justifyContent: 'center', fontSize: 13, fontWeight: 500,
                                        position: 'relative', transition: 'background 0.1s, transform 0.1s',
                                        ...(date ? dayStyle(date) : {}),
                                    }}>
                                    {date ? parseInt(date.slice(-2)) : ''}
                                    {date && blockedMap[date] !== undefined && (
                                        <span style={{ position: 'absolute', bottom: 4, left: '50%',
                                            transform: 'translateX(-50%)',
                                            width: 5, height: 5, borderRadius: '50%', background: '#ef4444' }} />
                                    )}
                                </div>
                            ))}
                        </div>

                        {/* Legend */}
                        <div style={{ display: 'flex', gap: 16, marginTop: 16, flexWrap: 'wrap' }}>
                            {[
                                { color: '#ef4444', bg: '#fef2f2', label: 'Blocked' },
                                { color: '#6366f1', bg: '#eff6ff', label: 'Today' },
                                { color: '#4f46e5', bg: '#6366f1', label: 'Selected' },
                            ].map(l => (
                                <div key={l.label} style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12 }}>
                                    <div style={{ width: 16, height: 16, borderRadius: 4,
                                        background: l.bg, border: `2px solid ${l.color}` }} />
                                    <span style={{ color: '#64748b' }}>{l.label}</span>
                                </div>
                            ))}
                            <span style={{ fontSize: 12, color: '#94a3b8', marginLeft: 'auto' }}>
                                Click blocked date to unblock
                            </span>
                        </div>
                    </div>

                    {/* Side panel */}
                    <div style={{ flex: '0 1 250px', display: 'flex', flexDirection: 'column', gap: 16 }}>

                        {/* Block form (shown when dates are selected) */}
                        {selCount > 0 ? (
                            <div style={{ background: '#fff', borderRadius: 14, border: '1px solid #e2e8f0',
                                padding: 20, boxShadow: '0 1px 4px rgba(0,0,0,0.05)' }}>
                                <div style={{ display: 'flex', alignItems: 'center',
                                    justifyContent: 'space-between', marginBottom: 10 }}>
                                    <h3 style={{ fontSize: 14, fontWeight: 700, color: '#0f172a', margin: 0 }}>
                                        Block {selCount} Date{selCount > 1 ? 's' : ''}
                                    </h3>
                                    <span style={{ fontSize: 12, fontWeight: 700, background: '#6366f1',
                                        color: '#fff', borderRadius: 20, padding: '2px 10px' }}>
                                        {selCount}
                                    </span>
                                </div>

                                {/* Selected date chips */}
                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 5, marginBottom: 12,
                                    maxHeight: 100, overflowY: 'auto' }}>
                                    {[...selectedSet].sort().map(d => (
                                        <span key={d} style={{ fontSize: 11, fontWeight: 600,
                                            background: '#ede9fe', color: '#6366f1',
                                            borderRadius: 20, padding: '3px 8px',
                                            display: 'inline-flex', alignItems: 'center', gap: 4 }}>
                                            {formatDate(d)}
                                            <span
                                                style={{ cursor: 'pointer', fontWeight: 700, fontSize: 13,
                                                    lineHeight: 1, color: '#8b5cf6' }}
                                                onClick={() => setSelectedSet(prev => {
                                                    const n = new Set(prev); n.delete(d); return n;
                                                })}>×</span>
                                        </span>
                                    ))}
                                </div>

                                <form onSubmit={submitBlock}>
                                    <label style={{ fontSize: 12, color: '#64748b', fontWeight: 600,
                                        display: 'block', marginBottom: 4 }}>
                                        Reason (optional, applies to all)
                                    </label>
                                    <input
                                        type="text"
                                        value={reason}
                                        onChange={e => setReason(e.target.value)}
                                        placeholder="e.g. Medical leave"
                                        maxLength={191}
                                        style={{ width: '100%', padding: '8px 10px', borderRadius: 8,
                                            border: '1px solid #e2e8f0', fontSize: 13, marginBottom: 12,
                                            outline: 'none', boxSizing: 'border-box' }}
                                    />
                                    <div style={{ display: 'flex', gap: 8 }}>
                                        <button type="submit" disabled={submitting}
                                            style={{ flex: 1, padding: '9px 0', borderRadius: 8,
                                                background: submitting ? '#fca5a5' : '#ef4444',
                                                color: '#fff', border: 'none', fontWeight: 600,
                                                fontSize: 13, cursor: submitting ? 'not-allowed' : 'pointer' }}>
                                            {submitting ? 'Saving…' : `Block ${selCount > 1 ? selCount + ' Dates' : 'Date'}`}
                                        </button>
                                        <button type="button" onClick={clearSelection}
                                            style={{ padding: '9px 12px', borderRadius: 8,
                                                background: '#f1f5f9', color: '#64748b', border: 'none',
                                                fontWeight: 600, fontSize: 13, cursor: 'pointer' }}>
                                            Clear
                                        </button>
                                    </div>
                                </form>
                            </div>
                        ) : (
                            <div style={{ background: '#f8fafc', borderRadius: 14,
                                border: '2px dashed #e2e8f0', padding: 24, textAlign: 'center' }}>
                                <span className="material-icons"
                                    style={{ fontSize: 40, color: '#cbd5e1', display: 'block', marginBottom: 8 }}>
                                    touch_app
                                </span>
                                <p style={{ color: '#94a3b8', fontSize: 13, margin: 0, lineHeight: 1.5 }}>
                                    Click dates or<br/>drag to select multiple
                                </p>
                            </div>
                        )}

                        {/* Blocked this month list */}
                        <div style={{ background: '#fff', borderRadius: 14, border: '1px solid #e2e8f0',
                            padding: 20, boxShadow: '0 1px 4px rgba(0,0,0,0.05)' }}>
                            <h3 style={{ fontSize: 13, fontWeight: 700, color: '#0f172a', margin: '0 0 12px' }}>
                                Blocked This Month
                                {Object.keys(blockedMap).length > 0 && (
                                    <span style={{ marginLeft: 8, fontSize: 11, fontWeight: 700,
                                        background: '#fef2f2', color: '#ef4444',
                                        borderRadius: 20, padding: '2px 8px' }}>
                                        {Object.keys(blockedMap).length}
                                    </span>
                                )}
                            </h3>
                            {Object.keys(blockedMap).length === 0 ? (
                                <p style={{ color: '#94a3b8', fontSize: 12, margin: 0 }}>No blocked dates.</p>
                            ) : (
                                <ul style={{ margin: 0, padding: 0, listStyle: 'none',
                                    display: 'flex', flexDirection: 'column', gap: 8,
                                    maxHeight: 200, overflowY: 'auto' }}>
                                    {Object.entries(blockedMap).sort().map(([date, rsn]) => (
                                        <li key={date} style={{ display: 'flex', alignItems: 'flex-start',
                                            justifyContent: 'space-between', gap: 6 }}>
                                            <div>
                                                <span style={{ fontSize: 12, fontWeight: 600, color: '#ef4444' }}>
                                                    {new Date(date + 'T00:00:00').toLocaleDateString('en-IN',
                                                        { day: 'numeric', month: 'short', weekday: 'short' })}
                                                </span>
                                                {rsn && (
                                                    <span style={{ fontSize: 11, color: '#94a3b8', display: 'block' }}>
                                                        {rsn}
                                                    </span>
                                                )}
                                            </div>
                                            <button disabled={submitting} onClick={() => unblockDate(date)}
                                                style={{ background: 'none', border: 'none', color: '#cbd5e1',
                                                    cursor: 'pointer', padding: 0, fontSize: 18,
                                                    lineHeight: 1, flexShrink: 0 }}
                                                title="Unblock">×</button>
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
