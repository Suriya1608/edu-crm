@extends('layouts.app')

@section('page_title', 'Admin Dashboard')

@section('content')

    {{-- ======================================================
         ROW 1 — 4 Primary KPI cards
         ====================================================== --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">groups</span></div>
                <div class="stat-label">Total Leads</div>
                <div class="stat-value">{{ $totalLeads }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card {{ $activeCallsNow > 0 ? 'active-call-card' : '' }}">
                <div class="stat-icon red">
                    <span class="material-icons">call</span>
                </div>
                <div class="stat-label">Active Calls Now</div>
                <div class="stat-value d-flex align-items-center gap-2">
                    {{ $activeCallsNow }}
                    @if($activeCallsNow > 0)
                        <span class="live-dot"></span>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon green"><span class="material-icons">task_alt</span></div>
                <div class="stat-label">Conversions This Month</div>
                <div class="stat-value">{{ $conversionsThisMonth }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon amber"><span class="material-icons">event</span></div>
                <div class="stat-label">Followups Today</div>
                <div class="stat-value">{{ $followupsToday }}</div>
            </div>
        </div>
    </div>

    {{-- ======================================================
         ROW 2 — 3 Secondary KPI cards + System Health panel
         ====================================================== --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon purple"><span class="material-icons">manage_accounts</span></div>
                <div class="stat-label">Total Managers</div>
                <div class="stat-value">{{ $totalManagers }}</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon cyan"><span class="material-icons">support_agent</span></div>
                <div class="stat-label">Total Telecallers</div>
                <div class="stat-value">{{ $totalTelecallers }}</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon rose"><span class="material-icons">phone_missed</span></div>
                <div class="stat-label">Missed Calls Today</div>
                <div class="stat-value">{{ $missedCallsToday }}</div>
            </div>
        </div>

        {{-- System Health mini-panel --}}
        <div class="col-12 col-lg-3">
            <div class="health-panel">
                <div class="health-panel-title">
                    <span class="material-icons">monitor_heart</span>
                    System Health
                </div>
                <div class="health-row">
                    <span class="health-label">Active Calls</span>
                    <span class="health-badge {{ $activeCallsNow > 0 ? 'badge-live' : 'badge-ok' }}">
                        {{ $activeCallsNow > 0 ? $activeCallsNow . ' live' : 'Idle' }}
                    </span>
                </div>
                <div class="health-row">
                    <span class="health-label">Missed Today</span>
                    <span class="health-badge {{ $missedCallsToday > 0 ? 'badge-warn' : 'badge-ok' }}">
                        {{ $missedCallsToday > 0 ? $missedCallsToday : 'None' }}
                    </span>
                </div>
                <div class="health-row">
                    <span class="health-label">Pending Followups</span>
                    <span class="health-badge {{ $followupsToday > 0 ? 'badge-warn' : 'badge-ok' }}">
                        {{ $followupsToday > 0 ? $followupsToday . ' due' : 'Clear' }}
                    </span>
                </div>
                <div class="health-row">
                    <span class="health-label">Conversions (MTD)</span>
                    <span class="health-badge {{ $conversionsThisMonth > 0 ? 'badge-success' : 'badge-ok' }}">
                        {{ $conversionsThisMonth > 0 ? '+' . $conversionsThisMonth : '0' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- ======================================================
         CHARTS ROW — Source Leads + Call Volume
         ====================================================== --}}
    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Source-wise Leads</h3>
                    <p>Lead distribution by source</p>
                </div>
                <div style="height:300px;">
                    <canvas id="sourceLeadChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Call Volume Graph</h3>
                    <p>Last 14 days total call trend</p>
                </div>
                <div style="height:300px;">
                    <canvas id="callVolumeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- WhatsApp Volume --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>WhatsApp Chat Volume</h3>
                    <p>Inbound vs outbound WhatsApp chats (last 14 days)</p>
                </div>
                <div style="height:280px;">
                    <canvas id="waVolumeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Course Performance --}}
    @if($courseStats->isNotEmpty())
    <div class="chart-card mb-4">
        <div class="chart-header mb-3">
            <h3>Course Performance</h3>
            <p>Lead volume and conversion rate by course</p>
        </div>
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:13px;">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Total Leads</th>
                        <th>Conversions</th>
                        <th>Rate</th>
                        <th>Volume</th>
                    </tr>
                </thead>
                <tbody>
                    @php $maxTotal = $courseStats->max('total') ?: 1; @endphp
                    @foreach($courseStats as $row)
                    <tr>
                        <td class="fw-semibold">{{ $row['course'] }}</td>
                        <td>{{ $row['total'] }}</td>
                        <td>{{ $row['conversions'] }}</td>
                        <td>
                            <span class="badge" style="background:{{ $row['rate'] >= 30 ? '#dcfce7' : ($row['rate'] >= 10 ? '#fef9c3' : '#fee2e2') }}; color:{{ $row['rate'] >= 30 ? '#16a34a' : ($row['rate'] >= 10 ? '#92400e' : '#dc2626') }}; padding:4px 10px; border-radius:20px; font-weight:700;">
                                {{ $row['rate'] }}%
                            </span>
                        </td>
                        <td style="width:180px; vertical-align:middle;">
                            <div style="background:#e2e8f0; border-radius:6px; height:7px; overflow:hidden;">
                                <div style="background:linear-gradient(90deg,#6366f1,#8b5cf6); height:100%; width:{{ round($row['total'] / $maxTotal * 100) }}%; border-radius:6px; transition:width 0.6s ease;"></div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ============ Inline styles for dashboard-specific elements ============ --}}
    <style>
        /* Active call card — subtle glow instead of bottom border */
        .active-call-card {
            border-color: rgba(239,68,68,0.25);
            box-shadow: 0 0 0 3px rgba(239,68,68,0.06);
        }

        /* Live pulse dot */
        .live-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            background: #ef4444;
            border-radius: 50%;
            animation: livePulse 1.4s ease-in-out infinite;
            flex-shrink: 0;
        }

        @keyframes livePulse {
            0%, 100% { transform: scale(1);   opacity: 1; box-shadow: 0 0 0 0 rgba(239,68,68,0.4); }
            50%       { transform: scale(1.2); opacity: 0.85; box-shadow: 0 0 0 6px rgba(239,68,68,0); }
        }

        /* System Health mini-panel */
        .health-panel {
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 20px;
            height: 100%;
            box-shadow: 0 2px 16px rgba(15,23,42,0.07);
        }

        .health-panel-title {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 14px;
        }

        .health-panel-title .material-icons {
            font-size: 18px;
            color: #6366f1;
        }

        .health-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 7px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .health-row:last-child { border-bottom: none; }

        .health-label {
            font-size: 12.5px;
            color: #64748b;
            font-weight: 500;
        }

        .health-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
        }

        .badge-ok      { background: #f1f5f9;          color: #64748b; }
        .badge-live    { background: rgba(239,68,68,0.1); color: #ef4444; }
        .badge-warn    { background: rgba(245,158,11,0.1); color: #d97706; }
        .badge-success { background: rgba(16,185,129,0.1); color: #059669; }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        (function() {
            const sourceLabels = @json($sourceLabels);
            const sourceValues = @json($sourceValues);
            const callLabels   = @json($callVolumeLabels);
            const callValues   = @json($callVolumeValues);
            const waLabels     = @json($waVolumeLabels);
            const waInbound    = @json($waInboundValues);
            const waOutbound   = @json($waOutboundValues);

            // Shared grid style
            const gridColor = 'rgba(226,232,240,0.7)';
            const fontFamily = "'Plus Jakarta Sans', sans-serif";

            Chart.defaults.font.family = fontFamily;
            Chart.defaults.color       = '#64748b';

            // Source-wise Doughnut
            new Chart(document.getElementById('sourceLeadChart'), {
                type: 'doughnut',
                data: {
                    labels: sourceLabels,
                    datasets: [{
                        data: sourceValues,
                        backgroundColor: ['#6366f1','#10b981','#f59e0b','#ef4444','#06b6d4','#8b5cf6','#f43f5e','#0ea5e9'],
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverOffset: 6
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 16, font: { size: 12, weight: '600' }, boxWidth: 10, boxHeight: 10 }
                        }
                    }
                }
            });

            // Call Volume Line
            new Chart(document.getElementById('callVolumeChart'), {
                type: 'line',
                data: {
                    labels: callLabels,
                    datasets: [{
                        label: 'Calls',
                        data: callValues,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,0.08)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#6366f1',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderWidth: 2.5
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        x: { grid: { color: gridColor }, ticks: { font: { size: 11 } } },
                        y: { beginAtZero: true, ticks: { precision: 0, font: { size: 11 } }, grid: { color: gridColor } }
                    },
                    plugins: { legend: { labels: { font: { size: 12, weight: '600' } } } }
                }
            });

            // WhatsApp Bar
            new Chart(document.getElementById('waVolumeChart'), {
                type: 'bar',
                data: {
                    labels: waLabels,
                    datasets: [
                        {
                            label: 'Inbound',
                            data: waInbound,
                            backgroundColor: 'rgba(16,185,129,0.85)',
                            borderRadius: 5,
                            borderSkipped: false
                        },
                        {
                            label: 'Outbound',
                            data: waOutbound,
                            backgroundColor: 'rgba(99,102,241,0.85)',
                            borderRadius: 5,
                            borderSkipped: false
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                        y: { beginAtZero: true, ticks: { precision: 0, font: { size: 11 } }, grid: { color: gridColor } }
                    },
                    plugins: { legend: { labels: { font: { size: 12, weight: '600' }, boxWidth: 10, boxHeight: 10 } } }
                }
            });
        })();
    </script>
@endsection
