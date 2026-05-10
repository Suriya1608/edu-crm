import { Head, Link, useForm, router } from '@inertiajs/react';

function StatCard({ icon, iconCls, label, value, sub }) {
    return (
        <div className="stat-card">
            <div className={`stat-icon ${iconCls}`}><span className="material-icons">{icon}</span></div>
            <div className="stat-label">{label}</div>
            <div className="stat-value">{value}</div>
            {sub && <div className="text-muted" style={{ fontSize: 11, marginTop: 2 }}>{sub}</div>}
        </div>
    );
}

function rowStatus(row) {
    if (row.is_duplicate) return { cls: 'bg-danger', label: `Duplicate (${row.dup_reason})` };
    if (row.is_invalid && row.invalid_reason === 'invalid_phone') return { cls: 'bg-warning text-dark', label: 'Invalid Phone' };
    if (row.is_invalid) return { cls: 'bg-warning text-dark', label: 'Invalid Email' };
    return { cls: 'bg-success', label: 'New' };
}

// ── Step 1: Upload form ───────────────────────────────────────────────────────
function UploadStep({ campaign }) {
    const form = useForm({ file: null });

    function submit(e) {
        e.preventDefault();
        form.post(`/manager/campaigns/${campaign.encrypted_id}/import/preview`, {
            forceFormData: true,
        });
    }

    return (
        <div className="row justify-content-center">
            <div className="col-lg-7">
                <div className="chart-card">
                    <div className="chart-header mb-4">
                        <h3>Upload Student Database</h3>
                        <p className="text-muted small mb-0">
                            Upload an Excel or CSV file. Duplicates (same phone/email within this campaign),
                            invalid phone numbers, and invalid emails will be automatically skipped.
                        </p>
                    </div>

                    <div className="alert alert-info d-flex gap-2 align-items-start mb-4">
                        <span className="material-icons" style={{ fontSize: 18, marginTop: 1 }}>info</span>
                        <div>
                            <strong>Expected column order:</strong><br />
                            <code>Name | Mobile Number | Email ID | Course | City</code><br />
                            <small className="text-muted">
                                Row 1 should be the header row. Only Name and Mobile Number are required.
                                Numbers must have exactly 10 digits (with or without +91/0 prefix).
                                Invalid phones and emails are skipped.
                            </small>
                        </div>
                    </div>

                    <form onSubmit={submit}>
                        <div className="mb-4">
                            <label className="form-label fw-semibold">
                                Select File <span className="text-danger">*</span>
                            </label>
                            <input type="file" accept=".xlsx,.xls,.csv" required
                                className={`form-control${form.errors.file ? ' is-invalid' : ''}`}
                                onChange={e => form.setData('file', e.target.files[0])} />
                            {form.errors.file && <div className="invalid-feedback">{form.errors.file}</div>}
                        </div>

                        <button type="submit" className="btn btn-primary" disabled={form.processing}>
                            {form.processing
                                ? <><span className="spinner-border spinner-border-sm me-1" />Uploading…</>
                                : <><span className="material-icons me-1" style={{ fontSize: 16, verticalAlign: -3 }}>preview</span>Preview &amp; Validate</>
                            }
                        </button>
                    </form>
                </div>
            </div>
        </div>
    );
}

// ── Step 2: Preview & confirm ─────────────────────────────────────────────────
function PreviewStep({ campaign, preview, preview_total, total, duplicates, invalid,
    invalid_phone, invalid_email, insertable, valid_rows }) {

    function confirmImport() {
        router.post(`/manager/campaigns/${campaign.encrypted_id}/import/store`, {
            contacts_data: JSON.stringify(valid_rows),
        });
    }

    const subInvalid = [
        invalid_phone > 0 ? `${invalid_phone} phone` : null,
        invalid_email > 0 ? `${invalid_email} email` : null,
    ].filter(Boolean).join(' · ');

    return (
        <>
            <div className="chart-card mb-4">
                <div className="chart-header mb-3"><h3>Import Preview</h3></div>

                <div className="row g-3 mb-4">
                    <div className="col-6 col-md-2">
                        <StatCard icon="upload_file" iconCls="blue" label="Total in File" value={total} />
                    </div>
                    <div className="col-6 col-md-2">
                        <StatCard icon="check_circle" iconCls="green" label="Will Be Inserted" value={insertable} />
                    </div>
                    <div className="col-6 col-md-3">
                        <StatCard icon="block" iconCls="red" label="Duplicates" value={duplicates} />
                    </div>
                    <div className="col-6 col-md-3">
                        <StatCard icon="warning" iconCls="amber" label="Invalid" value={invalid} sub={subInvalid || null} />
                    </div>
                    <div className="col-6 col-md-2">
                        {insertable > 0
                            ? <StatCard icon="check" iconCls="green" label="Status" value="Ready" />
                            : <StatCard icon="warning" iconCls="red" label="Status" value="Nothing to Import" />
                        }
                    </div>
                </div>

                {insertable > 0 ? (
                    <button className="btn btn-success mb-3" onClick={confirmImport}>
                        <span className="material-icons me-1" style={{ fontSize: 16, verticalAlign: -3 }}>save</span>
                        Confirm &amp; Import {insertable} Record(s)
                    </button>
                ) : (
                    <div className="alert alert-warning mb-3">
                        No valid records to import. All records are either duplicates or have invalid data.
                    </div>
                )}

                <Link href={`/manager/campaigns/${campaign.encrypted_id}/import`}
                    className="btn btn-sm btn-outline-secondary mb-3 ms-2">
                    Upload a different file
                </Link>
            </div>

            <div className="chart-card">
                <div className="chart-header mb-3">
                    <h3>Record Preview (first 100 rows shown)</h3>
                    <small className="text-muted">
                        <span className="badge bg-danger me-1">Duplicate</span>
                        <span className="badge bg-warning text-dark me-1">Invalid Phone</span>
                        <span className="badge bg-warning text-dark me-1">Invalid Email</span>
                        rows will be skipped.
                    </small>
                </div>
                <div className="table-responsive">
                    <table className="table table-sm table-hover align-middle mb-0">
                        <thead className="table-light">
                            <tr>
                                <th>#</th><th>Name</th><th>Mobile (stored as)</th>
                                <th>Email</th><th>Course</th><th>City</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {preview.map((row, i) => {
                                const st = rowStatus(row);
                                return (
                                    <tr key={i} className={row.is_duplicate ? 'table-danger' : row.is_invalid ? 'table-warning' : ''}>
                                        <td className="text-muted small">{i + 1}</td>
                                        <td>{row.name}</td>
                                        <td><code style={{ fontSize: 12 }}>{row.phone}</code></td>
                                        <td>{row.email || '—'}</td>
                                        <td>{row.course || '—'}</td>
                                        <td>{row.city || '—'}</td>
                                        <td><span className={`badge ${st.cls}`}>{st.label}</span></td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
                {preview_total > 100 && (
                    <p className="text-muted small mt-2 px-2">
                        Showing 100 of {preview_total} rows. All valid records will be imported.
                    </p>
                )}
            </div>
        </>
    );
}

// ── Main ──────────────────────────────────────────────────────────────────────
export default function Import({ campaign, step, ...props }) {
    return (
        <>
            <Head title={`Import Contacts — ${campaign.name}`} />

            <div className="mb-3">
                <Link href={campaign.show_url} className="btn btn-sm btn-light d-inline-flex align-items-center gap-1">
                    <span className="material-icons" style={{ fontSize: 16 }}>arrow_back</span>
                    Back to Campaign
                </Link>
            </div>

            {step === 'upload'
                ? <UploadStep campaign={campaign} />
                : <PreviewStep campaign={campaign} {...props} />
            }
        </>
    );
}
