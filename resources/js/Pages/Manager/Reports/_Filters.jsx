import { router } from '@inertiajs/react';
import { useState } from 'react';

const DATE_OPTS = [
    { value: '1',       label: 'Today'          },
    { value: '7',       label: 'Last 7 Days'    },
    { value: '30',      label: 'Last 30 Days'   },
    { value: '90',      label: 'Last 90 Days'   },
    { value: 'week',    label: 'This Week'      },
    { value: 'month',   label: 'This Month'     },
    { value: 'quarter', label: 'This Quarter'   },
    { value: 'year',    label: 'This Year'      },
];

const CALL_TYPE_OPTS = [
    { value: 'all',      label: 'All Calls'  },
    { value: 'inbound',  label: 'Inbound'    },
    { value: 'outbound', label: 'Outbound'   },
];

export default function ReportFilters({ filters, filterOptions, url, showCampaign = false, showCallType = false }) {
    const [dateRange,  setDateRange]  = useState(filters?.date_range ?? '30');
    const [source,     setSource]     = useState(filters?.source     ?? 'all');
    const [telecaller, setTelecaller] = useState(filters?.telecaller ?? 'all');
    const [campaign,   setCampaign]   = useState(filters?.campaign   ?? 'all');
    const [callType,   setCallType]   = useState(filters?.call_type  ?? 'all');

    function apply(e) {
        e.preventDefault();
        const params = { date_range: dateRange, source, telecaller };
        if (showCampaign)  params.campaign  = campaign;
        if (showCallType)  params.call_type = callType;
        router.get(url, params, { preserveState: false });
    }

    function reset() {
        setDateRange('30'); setSource('all'); setTelecaller('all');
        setCampaign('all'); setCallType('all');
        router.get(url, {}, { preserveState: false });
    }

    const extraCols = (showCampaign ? 1 : 0) + (showCallType ? 1 : 0);
    const colClass  = extraCols >= 2 ? 'col-md-2' : extraCols === 1 ? 'col-md-2' : 'col-md-3';

    return (
        <div className="chart-card mb-4">
            <form onSubmit={apply} className="row g-3 align-items-end">
                <div className={colClass}>
                    <label className="form-label">Period</label>
                    <select className="form-select" value={dateRange} onChange={e => setDateRange(e.target.value)}>
                        {DATE_OPTS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                    </select>
                </div>
                <div className={colClass}>
                    <label className="form-label">Source</label>
                    <select className="form-select" value={source} onChange={e => setSource(e.target.value)}>
                        <option value="all">All Sources</option>
                        {(filterOptions?.sources ?? []).map(s => <option key={s} value={s}>{s}</option>)}
                    </select>
                </div>
                <div className={colClass}>
                    <label className="form-label">Telecaller</label>
                    <select className="form-select" value={telecaller} onChange={e => setTelecaller(e.target.value)}>
                        <option value="all">All Telecallers</option>
                        {(filterOptions?.telecallers ?? []).map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                    </select>
                </div>
                {showCampaign && (
                    <div className={colClass}>
                        <label className="form-label">Campaign</label>
                        <select className="form-select" value={campaign} onChange={e => setCampaign(e.target.value)}>
                            <option value="all">All Campaigns</option>
                            {(filterOptions?.campaigns ?? []).map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </div>
                )}
                {showCallType && (
                    <div className={colClass}>
                        <label className="form-label">Call Type</label>
                        <select className="form-select" value={callType} onChange={e => setCallType(e.target.value)}>
                            {CALL_TYPE_OPTS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                        </select>
                    </div>
                )}
                <div className="col d-flex gap-2">
                    <button type="submit" className="btn btn-primary w-100">Apply</button>
                    <button type="button" className="btn btn-outline-secondary w-100" onClick={reset}>Reset</button>
                </div>
            </form>
        </div>
    );
}
