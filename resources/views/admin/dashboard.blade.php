@extends('layouts.app')

@section('page_title', 'Admin Dashboard')

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3 col-lg-2">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">groups</span></div>
                <div class="stat-label">Total Leads</div>
                <div class="stat-value">{{ $totalLeads }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <div class="stat-card">
                <div class="stat-icon green"><span class="material-icons">manage_accounts</span></div>
                <div class="stat-label">Total Managers</div>
                <div class="stat-value">{{ $totalManagers }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <div class="stat-card">
                <div class="stat-icon amber"><span class="material-icons">support_agent</span></div>
                <div class="stat-label">Total Telecallers</div>
                <div class="stat-value">{{ $totalTelecallers }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <div class="stat-card {{ $activeCallsNow > 0 ? 'highlight-danger' : '' }}">
                <div class="stat-icon red"><span class="material-icons">call</span></div>
                <div class="stat-label">Active Calls Now</div>
                <div class="stat-value">{{ $activeCallsNow }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <div class="stat-card">
                <div class="stat-icon red"><span class="material-icons">phone_missed</span></div>
                <div class="stat-label">Missed Calls Today</div>
                <div class="stat-value">{{ $missedCallsToday }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <div class="stat-card">
                <div class="stat-icon amber"><span class="material-icons">event</span></div>
                <div class="stat-label">Followups Today</div>
                <div class="stat-value">{{ $followupsToday }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card highlight-success">
                <div class="stat-icon green"><span class="material-icons">task_alt</span></div>
                <div class="stat-label">Conversions This Month</div>
                <div class="stat-value">{{ $conversionsThisMonth }}</div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="chart-card h-100 d-flex flex-column justify-content-center">
                <div class="chart-header mb-0">
                    <h3>System Health Snapshot</h3>
                    <p>Live indicators for calls, followups, conversions, and channel activity</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Source-wise Leads</h3>
                    <p>Lead distribution by source</p>
                </div>
                <div style="height: 320px;">
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
                <div style="height: 320px;">
                    <canvas id="callVolumeChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>WhatsApp Chat Volume</h3>
                    <p>Inbound vs outbound WhatsApp chats (last 14 days)</p>
                </div>
                <div style="height: 320px;">
                    <canvas id="waVolumeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        (function() {
            const sourceLabels = @json($sourceLabels);
            const sourceValues = @json($sourceValues);
            const callLabels = @json($callVolumeLabels);
            const callValues = @json($callVolumeValues);
            const waLabels = @json($waVolumeLabels);
            const waInbound = @json($waInboundValues);
            const waOutbound = @json($waOutboundValues);

            new Chart(document.getElementById('sourceLeadChart'), {
                type: 'doughnut',
                data: {
                    labels: sourceLabels,
                    datasets: [{
                        data: sourceValues,
                        backgroundColor: ['#2A7DE1', '#29B173', '#F4A11A', '#D94F4F', '#6F7C8E', '#7C4DFF', '#00A3A3', '#A36A00']
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            new Chart(document.getElementById('callVolumeChart'), {
                type: 'line',
                data: {
                    labels: callLabels,
                    datasets: [{
                        label: 'Calls',
                        data: callValues,
                        borderColor: '#2A7DE1',
                        backgroundColor: 'rgba(42,125,225,0.18)',
                        fill: true,
                        tension: 0.35
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });

            new Chart(document.getElementById('waVolumeChart'), {
                type: 'bar',
                data: {
                    labels: waLabels,
                    datasets: [{
                            label: 'Inbound',
                            data: waInbound,
                            backgroundColor: '#29B173'
                        },
                        {
                            label: 'Outbound',
                            data: waOutbound,
                            backgroundColor: '#2A7DE1'
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        })();
    </script>

    {{-- Course Performance Widget --}}
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
                            <span class="badge" style="background:{{ $row['rate'] >= 30 ? '#dcfce7' : ($row['rate'] >= 10 ? '#fef9c3' : '#fee2e2') }}; color:{{ $row['rate'] >= 30 ? '#16a34a' : ($row['rate'] >= 10 ? '#92400e' : '#dc2626') }}; padding:3px 8px; border-radius:6px;">
                                {{ $row['rate'] }}%
                            </span>
                        </td>
                        <td style="width:160px;">
                            <div style="background:#e2e8f0; border-radius:4px; height:8px; overflow:hidden;">
                                <div style="background:#137fec; height:100%; width:{{ round($row['total'] / $maxTotal * 100) }}%; border-radius:4px;"></div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
@endsection

