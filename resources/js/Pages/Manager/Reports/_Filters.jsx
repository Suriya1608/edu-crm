import { router } from '@inertiajs/react';
import { useState } from 'react';

const DATE_OPTS = [
    { value: '7',       label: 'Last 7 Days'  },
    { value: '30',      label: 'Last 30 Days' },
    { value: '90',      label: 'Last 90 Days' },
    { value: 'quarter', label: 'This Quarter' },
    { value: 'year',    label: 'This Year'    },
];

export default function ReportFilters({ filters, filterOptions, url, extras = null }) {
    const [dateRange, setDateRange]   = useState(filters?.date_range  ?? '30');
    const [source, setSource]         = useState(filters?.source      ?? 'all');
    const [telecaller, setTelecaller] = useState(filters?.telecaller  ?? 'all');

    function apply(e) {
        e.preventDefault();
        router.get(url, { date_range: dateRange, source, telecaller }, { preserveState: false });
    }

    function reset() {
        setDateRange('30'); setSource('all'); setTelecaller('all');
        router.get(url, {}, { preserveState: false });
    }

    return (
        <div className="chart-card mb-4">
            <form onSubmit={apply} className="row g-3 align-items-end">
                <div className="col-md-3">
                    <label className="form-label">Period</label>
                    <select className="form-select" value={dateRange} onChange={e => setDateRange(e.target.value)}>
                        {DATE_OPTS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                    </select>
                </div>
                <div className="col-md-3">
                    <label className="form-label">Source</label>
                    <select className="form-select" value={source} onChange={e => setSource(e.target.value)}>
                        <option value="all">All Sources</option>
                        {(filterOptions?.sources ?? []).map(s => <option key={s} value={s}>{s}</option>)}
                    </select>
                </div>
                <div className="col-md-3">
                    <label className="form-label">Telecaller</label>
                    <select className="form-select" value={telecaller} onChange={e => setTelecaller(e.target.value)}>
                        <option value="all">All Telecallers</option>
                        {(filterOptions?.telecallers ?? []).map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                    </select>
                </div>
                {extras}
                <div className="col-md-3 d-flex gap-2">
                    <button type="submit" className="btn btn-primary w-100">Apply</button>
                    <button type="button" className="btn btn-outline-secondary w-100" onClick={reset}>Reset</button>
                </div>
            </form>
        </div>
    );
}
