import { Head, Link, useForm } from '@inertiajs/react';

const MANUAL_CATEGORIES = [
    { value: 'social_media', label: 'Social Media', detailPlaceholder: 'e.g. Facebook, Instagram, LinkedIn' },
    { value: 'newspaper',    label: 'Newspaper',    detailPlaceholder: 'e.g. The Hindu, Times of India' },
    { value: 'tv',           label: 'TV Advertisement', detailPlaceholder: 'e.g. Sun TV, Vijay TV' },
    { value: 'referral',     label: 'Referral',     detailPlaceholder: 'Referrer name & contact (e.g. John Doe – 9876543210)' },
    { value: 'walk_in',      label: 'Walk-in / Self', detailPlaceholder: null },
    { value: 'other',        label: 'Other',        detailPlaceholder: 'Please specify' },
];

export default function Create({ courses, academic_years, store_url }) {
    const activeYear = academic_years?.find(y => y.is_active);

    const form = useForm({
        name:             '',
        phone:            '',
        email:            '',
        gender:           '',
        dob:              '',
        address:          '',
        city:             '',
        district:         '',
        state:            '',
        pincode:          '',
        course_id:        '',
        academic_year_id: activeYear ? String(activeYear.id) : '',
        source_category:  '',
        source_detail:    '',
    });

    function submit(e) {
        e.preventDefault();
        form.post(store_url);
    }

    const selectedCat     = MANUAL_CATEGORIES.find(c => c.value === form.data.source_category);
    const showDetailField = selectedCat && selectedCat.detailPlaceholder !== null;

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

                        {/* ── Contact ── */}
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

                        {/* ── Demographics ── */}
                        <div className="col-12">
                            <hr className="my-1" />
                            <p className="fw-semibold mb-2" style={{ fontSize: 13, color: '#64748b', textTransform: 'uppercase', letterSpacing: '.5px' }}>
                                Demographics
                            </p>
                        </div>

                        <div className="col-md-4">
                            <label className="form-label">Gender</label>
                            <select className="form-select" value={form.data.gender}
                                onChange={e => form.setData('gender', e.target.value)}>
                                <option value="">— Select —</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                            {form.errors.gender && <div className="invalid-feedback d-block">{form.errors.gender}</div>}
                        </div>

                        <div className="col-md-4">
                            <label className="form-label">Date of Birth</label>
                            <input type="date" className={`form-control${form.errors.dob ? ' is-invalid' : ''}`}
                                value={form.data.dob}
                                max={new Date().toISOString().split('T')[0]}
                                onChange={e => form.setData('dob', e.target.value)} />
                            {form.errors.dob && <div className="invalid-feedback">{form.errors.dob}</div>}
                        </div>

                        <div className="col-md-4">
                            <label className="form-label">Pincode</label>
                            <input type="text" className={`form-control${form.errors.pincode ? ' is-invalid' : ''}`}
                                placeholder="e.g. 600001" maxLength={10}
                                value={form.data.pincode}
                                onChange={e => form.setData('pincode', e.target.value)} />
                            {form.errors.pincode && <div className="invalid-feedback">{form.errors.pincode}</div>}
                        </div>

                        <div className="col-md-4">
                            <label className="form-label">City</label>
                            <input type="text" className={`form-control${form.errors.city ? ' is-invalid' : ''}`}
                                placeholder="e.g. Chennai"
                                value={form.data.city}
                                onChange={e => form.setData('city', e.target.value)} />
                            {form.errors.city && <div className="invalid-feedback">{form.errors.city}</div>}
                        </div>

                        <div className="col-md-4">
                            <label className="form-label">District</label>
                            <input type="text" className={`form-control${form.errors.district ? ' is-invalid' : ''}`}
                                placeholder="e.g. Chennai"
                                value={form.data.district}
                                onChange={e => form.setData('district', e.target.value)} />
                            {form.errors.district && <div className="invalid-feedback">{form.errors.district}</div>}
                        </div>

                        <div className="col-md-4">
                            <label className="form-label">State</label>
                            <input type="text" className={`form-control${form.errors.state ? ' is-invalid' : ''}`}
                                placeholder="e.g. Tamil Nadu"
                                value={form.data.state}
                                onChange={e => form.setData('state', e.target.value)} />
                            {form.errors.state && <div className="invalid-feedback">{form.errors.state}</div>}
                        </div>

                        <div className="col-12">
                            <label className="form-label">Address</label>
                            <textarea className={`form-control${form.errors.address ? ' is-invalid' : ''}`}
                                rows={2} placeholder="Street address, landmark…"
                                value={form.data.address}
                                onChange={e => form.setData('address', e.target.value)} />
                            {form.errors.address && <div className="invalid-feedback">{form.errors.address}</div>}
                        </div>

                        {/* ── Enrolment ── */}
                        <div className="col-md-6">
                            <label className="form-label">Academic Year</label>
                            <select className="form-select" value={form.data.academic_year_id}
                                onChange={e => form.setData('academic_year_id', e.target.value)}>
                                <option value="">— Select Year —</option>
                                {(academic_years || []).map(y => (
                                    <option key={y.id} value={y.id}>
                                        {y.name}{y.is_active ? ' (Current)' : ''}
                                    </option>
                                ))}
                            </select>
                            {activeYear && !form.data.academic_year_id && (
                                <div className="form-text">Current year: <strong>{activeYear.name}</strong></div>
                            )}
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

                        {/* ── Source ── */}
                        <div className="col-12">
                            <hr className="my-1" />
                            <p className="fw-semibold mb-2" style={{ fontSize: 13, color: '#64748b', textTransform: 'uppercase', letterSpacing: '.5px' }}>
                                Lead Source
                            </p>
                        </div>

                        <div className="col-md-6">
                            <label className="form-label">Source Category</label>
                            <select className={`form-select${form.errors.source_category ? ' is-invalid' : ''}`}
                                value={form.data.source_category}
                                onChange={e => {
                                    form.setData('source_category', e.target.value);
                                    form.setData('source_detail', '');
                                }}>
                                <option value="">— Select Source —</option>
                                {MANUAL_CATEGORIES.map(c => (
                                    <option key={c.value} value={c.value}>{c.label}</option>
                                ))}
                            </select>
                            {form.errors.source_category && <div className="invalid-feedback">{form.errors.source_category}</div>}
                        </div>

                        {showDetailField && (
                            <div className="col-md-6">
                                <label className="form-label">
                                    {form.data.source_category === 'referral' ? 'Referrer Details' : 'Specify'}
                                </label>
                                <input type="text"
                                    className={`form-control${form.errors.source_detail ? ' is-invalid' : ''}`}
                                    placeholder={selectedCat.detailPlaceholder}
                                    value={form.data.source_detail}
                                    onChange={e => form.setData('source_detail', e.target.value)} />
                                {form.errors.source_detail && <div className="invalid-feedback">{form.errors.source_detail}</div>}
                            </div>
                        )}
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
