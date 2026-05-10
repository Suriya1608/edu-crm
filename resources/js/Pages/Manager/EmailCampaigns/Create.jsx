import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';

function SourceBadge({ source }) {
    const styles = {
        Lead:     { background: '#dbeafe', color: '#1d4ed8' },
        Excel:    { background: '#dcfce7', color: '#15803d' },
        Campaign: { background: '#ede9fe', color: '#6d28d9' },
    };
    const s = styles[source] ?? styles.Campaign;
    return <span className="badge" style={{ ...s, fontWeight: 600, fontSize: 11 }}>{source}</span>;
}

export default function Create({ templates, courses, campaigns }) {
    const { errors } = usePage().props;

    const [data, setData] = useState({ name: '', description: '', template_id: '', scheduled_at: '' });
    const [allContacts, setAllContacts] = useState([]);
    const [selected, setSelected] = useState(new Set());
    const [loadingEmails, setLoadingEmails] = useState(true);
    const [emailLoadError, setEmailLoadError] = useState(false);
    const [sourceFilter, setSourceFilter] = useState('all');
    const [courseFilter, setCourseFilter] = useState('all');
    const [campaignFilter, setCampaignFilter] = useState('all');
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedTemplate, setSelectedTemplate] = useState(null);
    const [excelStatus, setExcelStatus] = useState(null);
    const [processing, setProcessing] = useState(false);

    const fileInputRef = useRef(null);

    function loadEmails(source, course, campaign) {
        setLoadingEmails(true);
        setEmailLoadError(false);
        const params = new URLSearchParams({
            source:      source ?? sourceFilter,
            course:      course ?? courseFilter,
            campaign_id: campaign ?? campaignFilter,
        });
        fetch(`/manager/email-campaigns/contacts?${params}`)
            .then(r => r.json())
            .then(contacts => { setAllContacts(contacts); setLoadingEmails(false); })
            .catch(() => { setEmailLoadError(true); setLoadingEmails(false); });
    }

    useEffect(() => { loadEmails(); }, []);

    function handleSourceChange(val) { setSourceFilter(val); loadEmails(val, courseFilter, campaignFilter); }
    function handleCourseChange(val) { setCourseFilter(val); loadEmails(sourceFilter, val, campaignFilter); }
    function handleCampaignChange(val) { setCampaignFilter(val); loadEmails(sourceFilter, courseFilter, val); }

    function handleTemplateChange(id) {
        setData(d => ({ ...d, template_id: id }));
        setSelectedTemplate(templates.find(t => String(t.id) === id) ?? null);
    }

    const filteredContacts = searchQuery
        ? allContacts.filter(c => {
            const q = searchQuery.toLowerCase();
            return c.email.toLowerCase().includes(q) ||
                   (c.name || '').toLowerCase().includes(q) ||
                   (c.course || '').toLowerCase().includes(q);
          })
        : allContacts;

    const allVisibleSelected = filteredContacts.length > 0 && filteredContacts.every(c => selected.has(c.email));

    function toggleAll(checked) {
        setSelected(prev => {
            const next = new Set(prev);
            filteredContacts.forEach(c => checked ? next.add(c.email) : next.delete(c.email));
            return next;
        });
    }

    function toggleRow(email, checked) {
        setSelected(prev => {
            const next = new Set(prev);
            checked ? next.add(email) : next.delete(email);
            return next;
        });
    }

    function importExcel(e) {
        const file = e.target.files[0];
        if (!file) return;
        setExcelStatus(null);

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const fd = new FormData();
        fd.append('file', file);
        if (csrfMeta) fd.append('_token', csrfMeta.content);

        fetch('/manager/email-campaigns/parse-excel', { method: 'POST', body: fd })
            .then(async r => {
                const json = await r.json();
                if (!r.ok) {
                    setExcelStatus({ type: 'error', msg: json.error || 'Error parsing file.' });
                    return;
                }
                let added = 0;
                setAllContacts(prev => {
                    const existing = new Set(prev.map(c => c.email));
                    const merged = [...prev];
                    json.forEach(c => {
                        if (!existing.has(c.email)) { merged.push(c); existing.add(c.email); added++; }
                    });
                    return merged;
                });
                setSelected(prev => {
                    const next = new Set(prev);
                    json.forEach(c => next.add(c.email));
                    return next;
                });
                setExcelStatus({ type: 'ok', msg: `${json.length} found, ${added} new added` });
                e.target.value = '';
            })
            .catch(() => setExcelStatus({ type: 'error', msg: 'Upload failed.' }));
    }

    function submit(e) {
        e.preventDefault();
        const nameMap = {};
        allContacts.forEach(c => { nameMap[c.email] = c.name || ''; });
        const emails = [...selected];
        setProcessing(true);
        router.post('/manager/email-campaigns', {
            ...data,
            recipient_emails: emails,
            recipient_names:  emails.map(em => nameMap[em] || ''),
        }, { onFinish: () => setProcessing(false) });
    }

    return (
        <>
            <Head title="Create Email Campaign" />

            <div className="d-flex align-items-center gap-3 mb-4">
                <Link href="/manager/email-campaigns" className="btn btn-sm btn-light d-flex align-items-center gap-1">
                    <span className="material-icons" style={{ fontSize: 16 }}>arrow_back</span>Back
                </Link>
                <div>
                    <h2 className="mb-0" style={{ fontSize: 20, fontWeight: 700 }}>Create Email Campaign</h2>
                    <p className="text-muted mb-0" style={{ fontSize: 13 }}>Select recipients, choose a template and send or schedule</p>
                </div>
            </div>

            {errors && Object.keys(errors).length > 0 && (
                <div className="alert alert-danger mb-3">
                    <ul className="mb-0 ps-3">
                        {Object.values(errors).map((msg, i) => <li key={i}>{msg}</li>)}
                    </ul>
                </div>
            )}

            <form onSubmit={submit}>
                <div className="row g-4">
                    {/* ── Left: Campaign details ───────────────────────── */}
                    <div className="col-lg-5">
                        <div className="chart-card mb-4">
                            <h6 className="fw-semibold mb-3">Campaign Details</h6>

                            <div className="mb-3">
                                <label className="form-label fw-semibold">Campaign Name <span className="text-danger">*</span></label>
                                <input type="text"
                                    className={`form-control${errors.name ? ' is-invalid' : ''}`}
                                    placeholder="e.g. March Admission Drive"
                                    value={data.name}
                                    onChange={e => setData(d => ({ ...d, name: e.target.value }))} />
                                {errors.name && <div className="invalid-feedback">{errors.name}</div>}
                            </div>

                            <div className="mb-3">
                                <label className="form-label fw-semibold">Description</label>
                                <textarea className="form-control" rows={2} placeholder="Optional notes"
                                    value={data.description}
                                    onChange={e => setData(d => ({ ...d, description: e.target.value }))} />
                            </div>

                            <div className="mb-3">
                                <label className="form-label fw-semibold">Email Template <span className="text-danger">*</span></label>
                                <select className={`form-select${errors.template_id ? ' is-invalid' : ''}`}
                                    value={data.template_id}
                                    onChange={e => handleTemplateChange(e.target.value)}>
                                    <option value="">— Choose a template —</option>
                                    {templates.map(tpl => (
                                        <option key={tpl.id} value={tpl.id}>{tpl.name}</option>
                                    ))}
                                </select>
                                {errors.template_id && <div className="invalid-feedback">{errors.template_id}</div>}
                            </div>

                            {selectedTemplate && (
                                <div className="mb-3">
                                    <div className="alert alert-light border p-2 mb-2" style={{ fontSize: 12 }}>
                                        <strong>Subject:</strong> {selectedTemplate.subject}
                                    </div>
                                    <div style={{
                                        border: '1px solid #e2e8f0', borderRadius: 6,
                                        padding: 10, maxHeight: 200, overflow: 'auto', fontSize: 12,
                                    }}
                                        dangerouslySetInnerHTML={{ __html: selectedTemplate.body }}
                                    />
                                </div>
                            )}

                            <div className="mb-3">
                                <label className="form-label fw-semibold">Schedule</label>
                                <input type="datetime-local"
                                    className={`form-control${errors.scheduled_at ? ' is-invalid' : ''}`}
                                    value={data.scheduled_at}
                                    onChange={e => setData(d => ({ ...d, scheduled_at: e.target.value }))} />
                                <div className="form-text">Leave blank to send immediately.</div>
                                {errors.scheduled_at && <div className="invalid-feedback">{errors.scheduled_at}</div>}
                            </div>
                        </div>
                    </div>

                    {/* ── Right: Recipients ────────────────────────────── */}
                    <div className="col-lg-7">
                        <div className="chart-card">
                            <div className="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                <h6 className="fw-semibold mb-0">Select Recipients</h6>
                                <div className="d-flex align-items-center gap-2 flex-wrap">
                                    <select className="form-select form-select-sm" style={{ width: 'auto' }}
                                        value={sourceFilter} onChange={e => handleSourceChange(e.target.value)}>
                                        <option value="all">All Sources</option>
                                        <option value="leads">Leads</option>
                                        <option value="campaign_contacts">Campaign Contacts</option>
                                    </select>
                                    <select className="form-select form-select-sm" style={{ width: 'auto' }}
                                        value={courseFilter} onChange={e => handleCourseChange(e.target.value)}>
                                        <option value="all">All Courses</option>
                                        {courses.map(c => <option key={c} value={c}>{c}</option>)}
                                    </select>
                                    <select className="form-select form-select-sm" style={{ width: 'auto' }}
                                        value={campaignFilter} onChange={e => handleCampaignChange(e.target.value)}>
                                        <option value="all">All Campaigns</option>
                                        {campaigns.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                                    </select>
                                    <span className="badge bg-primary">{selected.size} selected</span>
                                </div>
                            </div>

                            {errors.recipient_emails && (
                                <div className="alert alert-danger py-2 mb-3" style={{ fontSize: 13 }}>
                                    {errors.recipient_emails}
                                </div>
                            )}

                            {/* Excel import */}
                            <div className="mb-3 p-3 rounded" style={{ background: '#f8fafc', border: '1px dashed #cbd5e1' }}>
                                <div className="d-flex align-items-center flex-wrap gap-2">
                                    <span className="material-icons" style={{ fontSize: 20, color: '#6366f1' }}>upload_file</span>
                                    <div>
                                        <span className="fw-semibold" style={{ fontSize: 13 }}>Import from Excel / CSV</span>
                                        <span className="d-block text-muted" style={{ fontSize: 11 }}>
                                            Columns: <code>email</code> (required), <code>name</code> (optional).
                                            First row can be a header or raw emails.
                                        </span>
                                    </div>
                                    <div className="ms-auto d-flex align-items-center gap-2 flex-wrap">
                                        <a href="/manager/email-campaigns/sample-excel"
                                            className="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1"
                                            style={{ fontSize: 12, whiteSpace: 'nowrap' }}>
                                            <span className="material-icons" style={{ fontSize: 15 }}>download</span>
                                            Sample File
                                        </a>
                                        <input ref={fileInputRef} type="file" accept=".xlsx,.xls,.csv"
                                            className="form-control form-control-sm" style={{ maxWidth: 210 }}
                                            onChange={importExcel} />
                                        {excelStatus && (
                                            excelStatus.type === 'ok'
                                                ? <span className="badge" style={{ background: '#d1fae5', color: '#065f46', fontSize: 12 }}>{excelStatus.msg}</span>
                                                : <span className="text-danger" style={{ fontSize: 12 }}>{excelStatus.msg}</span>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="mb-2">
                                <input type="text" className="form-control form-control-sm"
                                    placeholder="Search emails..."
                                    value={searchQuery}
                                    onChange={e => setSearchQuery(e.target.value)} />
                            </div>

                            <div className="table-responsive" style={{ maxHeight: 420, overflowY: 'auto' }}>
                                <table className="table table-sm align-middle mb-0">
                                    <thead className="table-light" style={{ position: 'sticky', top: 0, zIndex: 1 }}>
                                        <tr>
                                            <th style={{ width: 36 }}>
                                                <input type="checkbox" className="form-check-input"
                                                    checked={allVisibleSelected}
                                                    onChange={e => toggleAll(e.target.checked)} />
                                            </th>
                                            <th>Email</th>
                                            <th>Name</th>
                                            <th>Course</th>
                                            <th>Source</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {loadingEmails ? (
                                            <tr>
                                                <td colSpan={5} className="text-center text-muted py-4" style={{ fontSize: 13 }}>
                                                    <span className="spinner-border spinner-border-sm me-2"></span>Loading...
                                                </td>
                                            </tr>
                                        ) : emailLoadError ? (
                                            <tr>
                                                <td colSpan={5} className="text-center text-danger py-3" style={{ fontSize: 13 }}>
                                                    Failed to load contacts.
                                                </td>
                                            </tr>
                                        ) : filteredContacts.length === 0 ? (
                                            <tr>
                                                <td colSpan={5} className="text-center text-muted py-4" style={{ fontSize: 13 }}>
                                                    No email addresses found.
                                                </td>
                                            </tr>
                                        ) : filteredContacts.map(c => (
                                            <tr key={c.email}>
                                                <td>
                                                    <input type="checkbox" className="form-check-input"
                                                        checked={selected.has(c.email)}
                                                        onChange={e => toggleRow(c.email, e.target.checked)} />
                                                </td>
                                                <td style={{ fontSize: 13 }}>{c.email}</td>
                                                <td className="text-muted" style={{ fontSize: 13 }}>{c.name || '—'}</td>
                                                <td className="text-muted" style={{ fontSize: 13 }}>{c.course || '—'}</td>
                                                <td><SourceBadge source={c.source} /></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div className="d-flex gap-2 mt-3">
                            <button type="submit" className="btn btn-primary d-flex align-items-center gap-1" disabled={processing}>
                                {processing ? (
                                    <><span className="spinner-border spinner-border-sm me-1" />&nbsp;Processing...</>
                                ) : (
                                    <><span className="material-icons" style={{ fontSize: 16 }}>send</span>
                                    {data.scheduled_at ? 'Schedule Campaign' : 'Send Campaign'}</>
                                )}
                            </button>
                            <Link href="/manager/email-campaigns" className="btn btn-light">Cancel</Link>
                        </div>
                    </div>
                </div>
            </form>
        </>
    );
}
