import { Head, Link } from '@inertiajs/react';
import ReportFilters from './_Filters';
import { useState } from 'react';

const NAV = [
    { href: '/manager/reports',                        label: 'Overview'              },
    { href: '/manager/reports/telecaller-performance', label: 'Telecaller Performance' },
    { href: '/manager/reports/conversion',             label: 'Conversion'            },
    { href: '/manager/reports/source-performance',     label: 'Source Performance'    },
    { href: '/manager/reports/period',                 label: 'Period Analysis'       },
    { href: '/manager/reports/response-time',          label: 'Response Time'         },
    { href: '/manager/reports/call-efficiency',        label: 'Call Efficiency'       },
];

function PeriodTable({ title, rows, colLabel }) {
    return (
        <div className="custom-table mb-4">
            <div className="table-header"><h3>{title}</h3></div>
            <div className="table-responsive">
                <table className="table mb-0">
                    <thead><tr><th>{colLabel}</th><th>Total Leads</th><th>Converted</th></tr></thead>
                    <tbody>
                        {(rows ?? []).length === 0 ? (
                            <tr><td colSpan={3} className="text-center py-4 text-muted">No data</td></tr>
                        ) : rows.map((r, i) => (
                            <tr key={i}>
                                <td className="fw-semibold">{r.period_date ?? r.period_week ?? r.period_month}</td>
                                <td>{r.total}</td>
                                <td><span className="badge bg-success">{r.converted}</span></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default function Period({ filters, filterOptions, daily, weekly, monthly }) {
    const [tab, setTab] = useState('daily');

    return (
        <>
            <Head title="Period Analysis" />
            <div className="d-flex gap-2 flex-wrap mb-4">
                {NAV.map(n => (
                    <Link key={n.href} href={n.href}
                        className={`btn btn-sm ${n.href === '/manager/reports/period' ? 'btn-primary' : 'btn-outline-secondary'}`}>
                        {n.label}
                    </Link>
                ))}
            </div>

            <ReportFilters filters={filters} filterOptions={filterOptions} url="/manager/reports/period" />

            <div className="d-flex gap-2 mb-3">
                {[['daily', 'Daily'], ['weekly', 'Weekly'], ['monthly', 'Monthly']].map(([key, label]) => (
                    <button key={key} type="button"
                        onClick={() => setTab(key)}
                        className={`btn btn-sm ${tab === key ? 'btn-primary' : 'btn-outline-primary'}`}>
                        {label}
                    </button>
                ))}
                <a href="/manager/reports/export/period/excel" className="btn btn-sm btn-outline-success ms-auto">
                    <span className="material-icons me-1" style={{ fontSize: 15 }}>download</span>Export CSV
                </a>
            </div>

            {tab === 'daily'   && <PeriodTable title="Daily Breakdown"   rows={daily}   colLabel="Date"  />}
            {tab === 'weekly'  && <PeriodTable title="Weekly Breakdown"  rows={weekly}  colLabel="Week"  />}
            {tab === 'monthly' && <PeriodTable title="Monthly Breakdown" rows={monthly} colLabel="Month" />}
        </>
    );
}
