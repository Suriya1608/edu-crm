import { useState } from 'react';
import { Head } from '@inertiajs/react';

const STATUSES = ['new', 'assigned', 'contacted', 'interested', 'converted', 'not_interested'];

const labelStyle = {
    fontSize: 11,
    fontWeight: 700,
    color: '#64748b',
    textTransform: 'uppercase',
    letterSpacing: .5,
    display: 'block',
    marginBottom: 6,
};

const selectStyle = {
    width: '100%',
    border: '1px solid #e2e8f0',
    borderRadius: 10,
    padding: '9px 12px',
    fontSize: 13,
    color: '#334155',
    background: '#fff',
    outline: 'none',
    cursor: 'pointer',
};

const inputStyle = { ...selectStyle };

function SectionTitle({ icon, title, sub }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 20 }}>
            <span className="material-icons" style={{ color: '#6366f1', fontSize: 22 }}>{icon}</span>
            <div>
                <div style={{ fontWeight: 700, fontSize: 15, color: '#0f172a' }}>{title}</div>
                {sub && <div style={{ fontSize: 12, color: '#94a3b8', marginTop: 1 }}>{sub}</div>}
            </div>
        </div>
    );
}

export default function Index({ courseWiseRows = [], finalCourseRows = [] }) {
    const today = new Date().toISOString().slice(0, 10);
    const firstOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10);

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

    const activeFilters = Object.entries(f).filter(([k, v]) => v && v !== 'all' && k !== 'date_from' && k !== 'date_to').length;

    return (
        <>
            <Head title="My Reports" />

            <div style={{ padding: '28px 24px', maxWidth: 900, margin: '0 auto' }}>

                {/* Page header */}
                <div style={{ marginBottom: 28 }}>
                    <h1 style={{ fontSize: 22, fontWeight: 800, color: '#0f172a', margin: 0 }}>My Reports</h1>
                    <p style={{ fontSize: 13, color: '#64748b', marginTop: 4 }}>
                        Filter your leads and download as Excel or PDF
                    </p>
                </div>

                {/* Filter card */}
                <div style={{ background: '#fff', borderRadius: 18, padding: '24px 26px', boxShadow: '0 1px 8px rgba(15,23,42,.08)', marginBottom: 24 }}>
                    <SectionTitle icon="filter_list" title="Filter Leads" sub="Select your criteria below" />

                    {/* Date range */}
                    <div style={{ marginBottom: 20 }}>
                        <div style={{ fontSize: 11, fontWeight: 700, color: '#6366f1', textTransform: 'uppercase', letterSpacing: .6, marginBottom: 10 }}>
                            Date Range
                        </div>
                        <div className="row g-3">
                            <div className="col-6">
                                <label style={labelStyle}>From</label>
                                <input type="date" value={f.date_from} onChange={e => set('date_from', e.target.value)} style={inputStyle} />
                            </div>
                            <div className="col-6">
                                <label style={labelStyle}>To</label>
                                <input type="date" value={f.date_to} onChange={e => set('date_to', e.target.value)} style={inputStyle} />
                            </div>
                        </div>
                    </div>

                    <div style={{ height: 1, background: '#f1f5f9', marginBottom: 20 }} />

                    {/* Lead filters */}
                    <div style={{ fontSize: 11, fontWeight: 700, color: '#6366f1', textTransform: 'uppercase', letterSpacing: .6, marginBottom: 10 }}>
                        Lead Filters
                    </div>
                    <div className="row g-3">
                        <div className="col-md-4 col-6">
                            <label style={labelStyle}>Status</label>
                            <select value={f.status} onChange={e => set('status', e.target.value)} style={selectStyle}>
                                <option value="all">All Statuses</option>
                                {STATUSES.map(s => (
                                    <option key={s} value={s}>
                                        {s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="col-md-4 col-6">
                            <label style={labelStyle}>Gender</label>
                            <select value={f.gender} onChange={e => set('gender', e.target.value)} style={selectStyle}>
                                <option value="all">All Genders</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="not_specified">Not Specified</option>
                            </select>
                        </div>
                        <div className="col-md-4 col-6">
                            <label style={labelStyle}>Quota</label>
                            <select value={f.quota} onChange={e => set('quota', e.target.value)} style={selectStyle}>
                                <option value="all">All Quotas</option>
                                <option value="management">Management</option>
                                <option value="counselling">Counselling</option>
                            </select>
                        </div>
                        <div className="col-md-6 col-12">
                            <label style={labelStyle}>Enquired Course</label>
                            <select value={f.course_id} onChange={e => set('course_id', e.target.value)} style={selectStyle}>
                                <option value="all">All Courses</option>
                                {courseWiseRows.map(r => (
                                    <option key={r.course_id} value={r.course_id}>{r.course}</option>
                                ))}
                            </select>
                        </div>
                        <div className="col-md-6 col-12">
                            <label style={labelStyle}>Final Selected Course</label>
                            <select value={f.final_course_id} onChange={e => set('final_course_id', e.target.value)} style={selectStyle}>
                                <option value="all">All Final Courses</option>
                                {finalCourseRows.map(r => (
                                    <option key={r.course_id} value={r.course_id}>{r.course}</option>
                                ))}
                            </select>
                        </div>
                    </div>

                    {/* Active filter summary */}
                    {activeFilters > 0 && (
                        <div style={{ marginTop: 16, display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                            <span style={{ fontSize: 12, color: '#6366f1', fontWeight: 600 }}>
                                {activeFilters} filter{activeFilters > 1 ? 's' : ''} active
                            </span>
                            <button onClick={resetFilters} style={{ background: 'none', border: 'none', cursor: 'pointer', fontSize: 12, color: '#94a3b8', padding: 0, textDecoration: 'underline' }}>
                                Clear all
                            </button>
                        </div>
                    )}
                </div>

                {/* Download card */}
                <div style={{ background: '#fff', borderRadius: 18, padding: '24px 26px', boxShadow: '0 1px 8px rgba(15,23,42,.08)' }}>
                    <SectionTitle icon="download" title="Download Report" sub="Export filtered leads in your preferred format" />

                    <div className="row g-3">
                        <div className="col-md-6">
                            <a
                                href={buildUrl('excel')}
                                target="_blank"
                                rel="noreferrer"
                                style={{
                                    display: 'flex', alignItems: 'center', gap: 14,
                                    padding: '18px 22px', borderRadius: 14,
                                    background: 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
                                    color: '#fff', textDecoration: 'none',
                                    boxShadow: '0 4px 14px rgba(16,185,129,.3)',
                                    transition: 'opacity .15s',
                                }}
                                onMouseOver={e => e.currentTarget.style.opacity = '.9'}
                                onMouseOut={e => e.currentTarget.style.opacity = '1'}
                            >
                                <span className="material-icons" style={{ fontSize: 32, opacity: .9 }}>table_view</span>
                                <div>
                                    <div style={{ fontWeight: 700, fontSize: 15 }}>Download Excel</div>
                                    <div style={{ fontSize: 12, opacity: .85, marginTop: 2 }}>Spreadsheet (.xlsx) — up to 1,000 leads</div>
                                </div>
                            </a>
                        </div>
                        <div className="col-md-6">
                            <a
                                href={buildUrl('pdf')}
                                target="_blank"
                                rel="noreferrer"
                                style={{
                                    display: 'flex', alignItems: 'center', gap: 14,
                                    padding: '18px 22px', borderRadius: 14,
                                    background: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
                                    color: '#fff', textDecoration: 'none',
                                    boxShadow: '0 4px 14px rgba(239,68,68,.3)',
                                    transition: 'opacity .15s',
                                }}
                                onMouseOver={e => e.currentTarget.style.opacity = '.9'}
                                onMouseOut={e => e.currentTarget.style.opacity = '1'}
                            >
                                <span className="material-icons" style={{ fontSize: 32, opacity: .9 }}>picture_as_pdf</span>
                                <div>
                                    <div style={{ fontWeight: 700, fontSize: 15 }}>Download PDF</div>
                                    <div style={{ fontSize: 12, opacity: .85, marginTop: 2 }}>Printable report (landscape A4)</div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <p style={{ fontSize: 12, color: '#94a3b8', marginTop: 16, marginBottom: 0 }}>
                        <span className="material-icons" style={{ fontSize: 14, verticalAlign: 'middle', marginRight: 4 }}>info</span>
                        Reports include only your assigned leads matching the selected filters.
                    </p>
                </div>

            </div>
        </>
    );
}
