import { Head, Link, useForm } from '@inertiajs/react';

export default function Create({ courses, store_url }) {
    const form = useForm({
        name:      '',
        phone:     '',
        email:     '',
        course_id: '',
        source:    'manual',
    });

    function submit(e) {
        e.preventDefault();
        form.post(store_url);
    }

    return (
        <>
            <Head title="Add Lead" />

            <div className="d-flex align-items-center gap-3 mb-4">
                <Link href="/manager/leads" className="btn btn-sm btn-light">
                    <span className="material-icons me-1" style={{ fontSize: 18 }}>arrow_back</span>
                    Back to Leads
                </Link>
                <h2 className="mb-0" style={{ fontSize: 20, fontWeight: 700 }}>Add Lead</h2>
            </div>

            <div className="card p-4">
                <form onSubmit={submit}>
                    <div className="row g-3">
                        <div className="col-md-6">
                            <label className="form-label">Name *</label>
                            <input type="text" className={`form-control${form.errors.name ? ' is-invalid' : ''}`}
                                value={form.data.name} onChange={e => form.setData('name', e.target.value)} required />
                            {form.errors.name && <div className="invalid-feedback">{form.errors.name}</div>}
                        </div>

                        <div className="col-md-6">
                            <label className="form-label">Phone *</label>
                            <div className="input-group">
                                <span className="input-group-text">+91</span>
                                <input type="tel" className={`form-control${form.errors.phone ? ' is-invalid' : ''}`}
                                    placeholder="10-digit mobile number" maxLength={10} pattern="[0-9]{10}"
                                    inputMode="numeric" required
                                    value={form.data.phone} onChange={e => form.setData('phone', e.target.value)} />
                                {form.errors.phone && <div className="invalid-feedback">{form.errors.phone}</div>}
                            </div>
                            <div className="form-text">Enter 10-digit number — +91 is added automatically.</div>
                        </div>

                        <div className="col-md-6">
                            <label className="form-label">Email</label>
                            <input type="email" className={`form-control${form.errors.email ? ' is-invalid' : ''}`}
                                value={form.data.email} onChange={e => form.setData('email', e.target.value)} />
                            {form.errors.email && <div className="invalid-feedback">{form.errors.email}</div>}
                        </div>

                        <div className="col-md-6">
                            <label className="form-label">Course</label>
                            <select className="form-select" value={form.data.course_id}
                                onChange={e => form.setData('course_id', e.target.value)}>
                                <option value="">— Select Course —</option>
                                {courses.map(c => (
                                    <option key={c.id} value={c.id}>{c.name}</option>
                                ))}
                            </select>
                        </div>

                        <div className="col-md-6">
                            <label className="form-label">Source</label>
                            <input type="text" className="form-control"
                                value={form.data.source} onChange={e => form.setData('source', e.target.value)} />
                        </div>
                    </div>

                    <div className="mt-4 d-flex gap-2">
                        <button type="submit" className="btn btn-primary" disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save Lead'}
                        </button>
                        <Link href="/manager/leads" className="btn btn-secondary">Cancel</Link>
                    </div>
                </form>
            </div>
        </>
    );
}
