import { Head } from '@inertiajs/react';
import { useEffect } from 'react';

export default function Print({ title, headers, rows }) {
    useEffect(() => {
        const style = document.createElement('style');
        style.id = 'print-only-styles';
        style.textContent = `
            @media print {
                #sidebar, #sidebarBackdrop, .top-header { display: none !important; }
                #mainContent { margin-left: 0 !important; padding: 0 !important; }
                .print-table { font-size: 11px; }
            }
        `;
        document.head.appendChild(style);
        window.print();
        return () => document.getElementById('print-only-styles')?.remove();
    }, []);

    return (
        <>
            <Head title={title} />

            <div style={{ padding: '16px 0' }}>
                <div className="d-flex align-items-center justify-content-between mb-3">
                    <h5 className="fw-bold mb-0">{title}</h5>
                    <button className="btn btn-sm btn-outline-secondary d-print-none"
                        onClick={() => window.print()}>
                        <span className="material-icons me-1" style={{ fontSize: 15, verticalAlign: -3 }}>print</span>
                        Print
                    </button>
                </div>

                <div className="table-responsive">
                    <table className="table table-bordered table-sm align-middle print-table mb-0"
                        style={{ fontSize: 12 }}>
                        <thead style={{ background: '#f2f2f2' }}>
                            <tr>
                                {headers.map((h, i) => <th key={i}>{h}</th>)}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td colSpan={headers.length} className="text-center text-muted py-3">
                                        No records.
                                    </td>
                                </tr>
                            ) : rows.map((row, i) => (
                                <tr key={i}>
                                    {row.map((cell, j) => <td key={j}>{cell}</td>)}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
