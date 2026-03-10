@extends('layouts.manager.app')

@section('page_title', 'Reports & Analytics')

@section('content')
    @include('manager.reports.partials.toolbar', [
        'filters' => $filters,
        'filterOptions' => $filterOptions,
        'reportKey' => 'telecaller-performance',
        'baseRoute' => route('manager.reports.home'),
    ])

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">person_add</span></div>
                <div class="stat-label">Total Leads</div>
                <div class="stat-value">{{ $totalLeads }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon amber"><span class="material-icons">support_agent</span></div>
                <div class="stat-label">Contacted Leads</div>
                <div class="stat-value">{{ $contactedLeads }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card highlight-success">
                <div class="stat-icon green"><span class="material-icons">task_alt</span></div>
                <div class="stat-label">Conversion Rate</div>
                <div class="stat-value">{{ number_format($conversionRate, 2) }}%</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon purple"><span class="material-icons">wifi</span></div>
                <div class="stat-label">Active Telecallers</div>
                <div class="stat-value">{{ $activeTelecallers }}</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Lead Conversion Funnel</h3>
                    <p>Relevant CRM flow (removed revenue-only funnel)</p>
                </div>
                <div class="chart-container"><canvas id="reportFunnelChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Source Performance</h3>
                    <p>Landing page / Ads source lead contribution</p>
                </div>
                <div class="chart-container"><canvas id="reportSourceChart"></canvas></div>
            </div>
        </div>
    </div>

    <div class="custom-table">
        <div class="table-header">
            <h3>Telecaller Effectiveness Snapshot</h3>
            <a href="{{ route('manager.reports.telecaller-performance') }}" class="text-primary text-decoration-none fw-bold"
                style="font-size: 12px;">Open Full Report</a>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Telecaller</th>
                        <th>Assigned</th>
                        <th>Calls</th>
                        <th>Avg Talk Time</th>
                        <th>Follow-ups</th>
                        <th>Conversions</th>
                        <th>Efficiency Score</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($telecallerRows as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td>{{ $row['assigned'] }}</td>
                            <td>{{ $row['calls'] }}</td>
                            <td>{{ $row['avg_talk_time'] }}</td>
                            <td>{{ $row['followups'] }}</td>
                            <td>{{ $row['converted'] }}</td>
                            <td><span class="badge bg-primary">{{ $row['efficiency_score'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-4 text-muted">No data available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        (function() {
            const funnelCtx = document.getElementById('reportFunnelChart');
            const sourceCtx = document.getElementById('reportSourceChart');
            if (typeof Chart === 'undefined') return;

            if (funnelCtx) {
                new Chart(funnelCtx, {
                    type: 'bar',
                    data: {
                        labels: ['New/Assigned', 'Contacted', 'Interested', 'Converted'],
                        datasets: [{
                            data: [{{ $funnel['new'] }}, {{ $funnel['contacted'] }}, {{ $funnel['interested'] }}, {{ $funnel['converted'] }}],
                            backgroundColor: ['#94a3b8', '#0ea5e9', '#f59e0b', '#10b981']
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
                });
            }

            if (sourceCtx) {
                new Chart(sourceCtx, {
                    type: 'doughnut',
                    data: {
                        labels: @json($sourceRows->pluck('source')->values()),
                        datasets: [{
                            data: @json($sourceRows->pluck('total')->values()),
                            backgroundColor: ['#137fec', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#0ea5e9']
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
            }
        })();
    </script>
@endpush

