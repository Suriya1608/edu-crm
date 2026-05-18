import { useState } from 'react';
import { Head } from '@inertiajs/react';

// ── Design tokens ────────────────────────────────────────────────
const ORG   = '#FF5C1A';
const ORGL  = '#FF8042';
const DARKC = '#1A1A2E';
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
const STATUSES = ['new', 'assigned', 'contacted', 'interested', 'converted', 'not_interested'];

// ── Shared field styles ──────────────────────────────────────────
const labelStyle = {
    fontSize:      11,
    fontWeight:    700,
    color:         MUTED,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
    display:       'block',
    marginBottom:  5,
};

const fieldStyle = {
    width:        '100%',
    border:       `1px solid ${BORDER}`,
    borderRadius: 10,
    padding:      '9px 14px',
    fontSize:     13,
    color:        TEXT,
    background:   WHITE,
    outline:      'none',
    cursor:       'pointer',
    boxSizing:    'border-box',
};

// ── Section header ───────────────────────────────────────────────
function SectionHeader({ title }) {
    return (
        <div style={{
            fontSize:     13,
            fontWeight:   700,
            color:        TEXT,
            paddingBottom: 10,
            marginBottom:  16,
            borderBottom:  `1px solid ${BORDER}`,
        }}>
            {title}
        </div>
    );
}

// ── Component ────────────────────────────────────────────────────
export default function Index({ courseWiseRows = [], finalCourseRows = [] }) {
    const today        = new Date().toISOString().slice(0, 10);
    const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1)
        .toISOString().slice(0, 10);

    const [f, setF] = useState({
        date_from:       firstOfMonth,
        date_to:         today,
        status:          'all',
        gender:          'all',
        course_id:       'all',
        final_course_id: 'all',
        quota:           'all',
    });

    const set = (k, v) => setF(prev => ({ ...prev, [k]: v }));

    const buildUrl = (format) => {
        const p = new URLSearchParams({ format });
        Object.entries(f).forEach(([k, v]) => { if (v && v !== 'all') p.set(k, v); });
        return `/telecaller/reports/download?${p}`;
    };

    const resetFilters = () => setF({
        date_from:       firstOfMonth,
        date_to:         today,
        status:          'all',
        gender:          'all',
        course_id:       'all',
        final_course_id: 'all',
        quota:           'all',
    });

    const activeFilters = Object.entries(f).filter(
        ([k, v]) => v && v !== 'all' && k !== 'date_from' && k !== 'date_to'
    ).length;

    return (
        <>
            <Head title="My Reports" />

            {/* Global font injection */}
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Work+Sans:wght@300;400;500;600;700;800;900&display=swap');
                *, *::before, *::after { font-family: 'Work Sans', sans-serif !important; }
                .rpt-select:focus, .rpt-input:focus { border-color: ${ORG} !important; box-shadow: 0 0 0 3px ${ORG}18 !important; }
                .rpt-dl-btn:hover { opacity: 0.88; transform: translateY(-1px); }
                .rpt-dl-btn { transition: opacity 0.15s, transform 0.15s; }
                .rpt-clear-btn:hover { color: ${ORG} !important; }
            `}</style>

            <div style={{ padding: '28px 24px', maxWidth: 900, margin: '0 auto' }}>

                {/* ── Page header ── */}
                <div style={{ marginBottom: 28 }}>
                    <h1 style={{ fontSize: 22, fontWeight: 800, color: TEXT, margin: 0 }}>
                        My Reports
                    </h1>
                    <p style={{ fontSize: 13, color: MUTED, marginTop: 4, marginBottom: 0 }}>
                        Filter your leads and download as Excel or PDF
                    </p>
                </div>

                {/* ── Filter card ── */}
                <div style={{ ...card(), padding: '20px 22px', marginBottom: 20 }}>
                    <SectionHeader title="Filter Leads" />

                    {/* Date range */}
                    <div style={{
                        display:             'grid',
                        gridTemplateColumns: 'repeat(auto-fill, minmax(160px, 1fr))',
                        gap:                 14,
                        marginBottom:        18,
                    }}>
                        <div>
                            <label style={labelStyle}>Date From</label>
                            <input
                                type="date"
                                className="rpt-input"
                                value={f.date_from}
                                onChange={e => set('date_from', e.target.value)}
                                style={fieldStyle}
                            />
                        </div>
                        <div>
                            <label style={labelStyle}>Date To</label>
                            <input
                                type="date"
                                className="rpt-input"
                                value={f.date_to}
                                onChange={e => set('date_to', e.target.value)}
                                style={fieldStyle}
                            />
                        </div>
                        <div>
                            <label style={labelStyle}>Status</label>
                            <select
                                className="rpt-select"
                                value={f.status}
                                onChange={e => set('status', e.target.value)}
                                style={fieldStyle}
                            >
                                <option value="all">All Statuses</option>
                                {STATUSES.map(s => (
                                    <option key={s} value={s}>
                                        {s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label style={labelStyle}>Gender</label>
                            <select
                                className="rpt-select"
                                value={f.gender}
                                onChange={e => set('gender', e.target.value)}
                                style={fieldStyle}
                            >
                                <option value="all">All Genders</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="not_specified">Not Specified</option>
                            </select>
                        </div>
                        <div>
                            <label style={labelStyle}>Quota</label>
                            <select
                                className="rpt-select"
                                value={f.quota}
                                onChange={e => set('quota', e.target.value)}
                                style={fieldStyle}
                            >
                                <option value="all">All Quotas</option>
                                <option value="management">Management</option>
                                <option value="counselling">Counselling</option>
                            </select>
                        </div>
                        <div>
                            <label style={labelStyle}>Enquired Course</label>
                            <select
                                className="rpt-select"
                                value={f.course_id}
                                onChange={e => set('course_id', e.target.value)}
                                style={fieldStyle}
                            >
                                <option value="all">All Courses</option>
                                {courseWiseRows.map(r => (
                                    <option key={r.course_id} value={r.course_id}>{r.course}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label style={labelStyle}>Final Course</label>
                            <select
                                className="rpt-select"
                                value={f.final_course_id}
                                onChange={e => set('final_course_id', e.target.value)}
                                style={fieldStyle}
                            >
                                <option value="all">All Final Courses</option>
                                {finalCourseRows.map(r => (
                                    <option key={r.course_id} value={r.course_id}>{r.course}</option>
                                ))}
                            </select>
                        </div>
                    </div>

                    {/* Active filter summary */}
                    {activeFilters > 0 ? (
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                            <span style={{
                                fontSize:     12,
                                fontWeight:   600,
                                color:        ORG,
                                background:   `${ORG}12`,
                                borderRadius: 20,
                                padding:      '3px 10px',
                            }}>
                                {activeFilters} filter{activeFilters > 1 ? 's' : ''} active
                            </span>
                            <button
                                className="rpt-clear-btn"
                                onClick={resetFilters}
                                style={{
                                    background:     'none',
                                    border:         'none',
                                    cursor:         'pointer',
                                    fontSize:       12,
                                    color:          MUTED,
                                    padding:        0,
                                    textDecoration: 'underline',
                                    transition:     'color 0.15s',
                                }}
                            >
                                Clear all
                            </button>
                        </div>
                    ) : (
                        <p style={{ fontSize: 12, color: MUTED, margin: 0 }}>
                            No extra filters applied — showing all leads in date range.
                        </p>
                    )}
                </div>

                {/* ── Download card ── */}
                <div style={{ ...card(), padding: '20px 22px' }}>
                    <SectionHeader title="Download Report" />

                    <div style={{ display: 'flex', gap: 14, flexWrap: 'wrap' }}>
                        {/* Excel */}
                        <a
                            href={buildUrl('excel')}
                            target="_blank"
                            rel="noreferrer"
                            className="rpt-dl-btn"
                            style={{
                                display:        'inline-flex',
                                alignItems:     'center',
                                gap:            8,
                                padding:        '10px 20px',
                                borderRadius:   10,
                                background:     ORG,
                                color:          WHITE,
                                textDecoration: 'none',
                                fontSize:       13,
                                fontWeight:     600,
                            }}
                        >
                            <span className="material-icons" style={{ fontSize: 18 }}>download</span>
                            Download Excel
                        </a>

                        {/* PDF */}
                        <a
                            href={buildUrl('pdf')}
                            target="_blank"
                            rel="noreferrer"
                            className="rpt-dl-btn"
                            style={{
                                display:        'inline-flex',
                                alignItems:     'center',
                                gap:            8,
                                padding:        '10px 20px',
                                borderRadius:   10,
                                background:     ORG,
                                color:          WHITE,
                                textDecoration: 'none',
                                fontSize:       13,
                                fontWeight:     600,
                            }}
                        >
                            <span className="material-icons" style={{ fontSize: 18 }}>download</span>
                            Download PDF
                        </a>
                    </div>

                    <p style={{ fontSize: 12, color: MUTED, marginTop: 14, marginBottom: 0, display: 'flex', alignItems: 'center', gap: 4 }}>
                        <span className="material-icons" style={{ fontSize: 14, verticalAlign: 'middle' }}>info</span>
                        Reports include only your assigned leads matching the selected filters.
                    </p>
                </div>

            </div>
        </>
    );
}
