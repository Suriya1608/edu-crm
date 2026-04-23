import { Head } from '@inertiajs/react';

function formatDuration(seconds) {
    const s = parseInt(seconds) || 0;
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    return [h, m, sec].map(v => String(v).padStart(2, '0')).join(':');
}

const RATING_CLS = {
    'A+': 'bg-success',
    'A':  'bg-success',
    'B':  'bg-primary',
    'C':  'bg-warning text-dark',
    'D':  'bg-danger',
};

export default function Index({
    telecallers,
    totalTelecallers,
    onlineTelecallers,
    offlineTelecallers,
    onCallTelecallers,
    idleTelecallers,
}) {
    return (
        <>
            <Head title="Telecaller Management" />

            {/* ── Stat cards ─────────────────────────────────────────────── */}
            <div className="row g-3 mb-4">
                <div className="col-6 col-md-3">
                    <div className="stat-card">
                        <div className="stat-icon blue"><span className="material-icons">support_agent</span></div>
                        <div className="stat-label">Total Telecallers</div>
                        <div className="stat-value">{totalTelecallers}</div>
                    </div>
                </div>
                <div className="col-6 col-md-3">
                    <div className="stat-card">
                        <div className="stat-icon green"><span className="material-icons">wifi</span></div>
                        <div className="stat-label">Online</div>
                        <div className="stat-value">{onlineTelecallers}</div>
                    </div>
                </div>
                <div className="col-6 col-md-3">
                    <div className="stat-card">
                        <div className="stat-icon amber"><span className="material-icons">phone_in_talk</span></div>
                        <div className="stat-label">On Call</div>
                        <div className="stat-value">{onCallTelecallers}</div>
                    </div>
                </div>
                <div className="col-6 col-md-3">
                    <div className="stat-card">
                        <div className="stat-icon red"><span className="material-icons">pause_circle</span></div>
                        <div className="stat-label">Idle / Offline</div>
                        <div className="stat-value">{idleTelecallers + offlineTelecallers}</div>
                    </div>
                </div>
            </div>

            {/* ── Table ──────────────────────────────────────────────────── */}
            <div className="custom-table">
                <div className="table-header">
                    <h3>Telecaller Live Performance Board</h3>
                </div>
                <div className="table-responsive">
                    <table className="table mb-0">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Name</th>
                                <th>Online / Offline</th>
                                <th>Active Call</th>
                                <th>Total Calls</th>
                                <th>Total Duration</th>
                                <th>Today Calls</th>
                                <th>Today Talk Time</th>
                                <th>Performance</th>
                                <th>Break / Status</th>
                                <th>Missed Follow-up</th>
                            </tr>
                        </thead>
                        <tbody>
                            {telecallers.length === 0 ? (
                                <tr>
                                    <td colSpan={11} className="text-center py-5 text-muted">
                                        <span className="material-icons d-block mb-2" style={{ fontSize: 40, opacity: 0.3 }}>support_agent</span>
                                        No telecallers found.
                                    </td>
                                </tr>
                            ) : telecallers.map((tele, idx) => (
                                <tr key={tele.id}>
                                    <td>{idx + 1}</td>
                                    <td>
                                        <div className="fw-semibold">{tele.name}</div>
                                        <small className="text-muted">Conv. {Number(tele.conversion_rate).toFixed(2)}%</small>
                                    </td>
                                    <td>
                                        {tele.online_offline_status === 'online'
                                            ? <span className="badge bg-success">Online</span>
                                            : <span className="badge bg-secondary">Offline</span>
                                        }
                                    </td>
                                    <td>
                                        {tele.active_call_indicator
                                            ? <span className="badge bg-primary">Live Call</span>
                                            : <span className="badge bg-light text-dark">No Active Call</span>
                                        }
                                    </td>
                                    <td><span className="badge bg-dark">{tele.total_call_count}</span></td>
                                    <td><span className="fw-semibold">{formatDuration(tele.total_talk_time_sec)}</span></td>
                                    <td><span className="badge bg-primary">{tele.today_call_count}</span></td>
                                    <td><span className="fw-semibold">{formatDuration(tele.today_talk_time_sec)}</span></td>
                                    <td>
                                        <span className={`badge ${RATING_CLS[tele.performance_rating] ?? 'bg-secondary'}`}>
                                            {tele.performance_rating}
                                        </span>
                                    </td>
                                    <td>
                                        {tele.break_tracking_status === 'on_call' && <span className="badge bg-primary">On Call</span>}
                                        {tele.break_tracking_status === 'online'  && <span className="badge bg-success">Online</span>}
                                        {tele.break_tracking_status === 'idle'    && <span className="badge bg-warning text-dark">Idle / Break</span>}
                                        {tele.break_tracking_status === 'offline' && <span className="badge bg-secondary">Offline</span>}
                                    </td>
                                    <td>
                                        <span className="badge bg-danger">{tele.missed_followup_count}</span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
