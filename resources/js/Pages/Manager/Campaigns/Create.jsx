import { Head, Link, useForm } from '@inertiajs/react';

export default function Create() {
    const form = useForm({ name: '', description: '' });

    function submit(e) {
        e.preventDefault();
        form.post('/manager/campaigns');
    }

    return (
        <>
            <Head title="New Campaign" />

            <div className="d-flex align-items-center gap-3 mb-4">
                <Link href="/manager/campaigns" className="btn btn-sm btn-light">
                    <span className="material-icons me-1" style={{ fontSize: 18 }}>arrow_back</span>
                    Back to Campaigns
                </Link>
                <h2 className="mb-0" style={{ fontSize: 20, fontWeight: 700 }}>New Campaign</h2>
            </div>

            <div className="card p-4" style={{ maxWidth: 560 }}>
                <form onSubmit={submit}>
                    <div className="mb-3">
                        <label className="form-label fw-semibold">Campaign Name *</label>
                        <input type="text" className={`form-control${form.errors.name ? ' is-invalid' : ''}`}
                            value={form.data.name} onChange={e => form.setData('name', e.target.value)} required />
                        {form.errors.name && <div className="invalid-feedback">{form.errors.name}</div>}
                    </div>

                    <div className="mb-4">
                        <label className="form-label fw-semibold">Description</label>
                        <textarea className="form-control" rows={3}
                            value={form.data.description} onChange={e => form.setData('description', e.target.value)} />
                        {form.errors.description && <div className="text-danger small mt-1">{form.errors.description}</div>}
                    </div>

                    <div className="d-flex gap-2">
                        <button type="submit" className="btn btn-primary" disabled={form.processing}>
                            {form.processing ? 'Creating…' : 'Create Campaign'}
                        </button>
                        <Link href="/manager/campaigns" className="btn btn-secondary">Cancel</Link>
                    </div>
                </form>
            </div>
        </>
    );
}
