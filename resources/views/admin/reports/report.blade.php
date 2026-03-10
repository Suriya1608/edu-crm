@extends('layouts.app')

@section('page_title', $title)

@section('content')
    <div class="chart-card mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Time Period</label>
                <select name="date_range" class="form-select form-select-sm">
                    <option value="7" {{ ($filters['date_range'] ?? '30') === '7' ? 'selected' : '' }}>Last 7 Days</option>
                    <option value="30" {{ ($filters['date_range'] ?? '30') === '30' ? 'selected' : '' }}>Last 30 Days</option>
                    <option value="90" {{ ($filters['date_range'] ?? '30') === '90' ? 'selected' : '' }}>Last 90 Days</option>
                    <option value="quarter" {{ ($filters['date_range'] ?? '30') === 'quarter' ? 'selected' : '' }}>This Quarter</option>
                    <option value="year" {{ ($filters['date_range'] ?? '30') === 'year' ? 'selected' : '' }}>This Year</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Source</label>
                <select name="source" class="form-select form-select-sm">
                    <option value="all">All Sources</option>
                    @foreach (($filterOptions['sources'] ?? collect()) as $source)
                        <option value="{{ $source }}" {{ ($filters['source'] ?? 'all') === $source ? 'selected' : '' }}>
                            {{ $source }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Telecaller</label>
                <select name="telecaller" class="form-select form-select-sm">
                    <option value="all">All Telecallers</option>
                    @foreach (($filterOptions['telecallers'] ?? collect()) as $telecaller)
                        <option value="{{ $telecaller->id }}"
                            {{ (string) ($filters['telecaller'] ?? 'all') === (string) $telecaller->id ? 'selected' : '' }}>
                            {{ $telecaller->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Manager</label>
                <select name="manager" class="form-select form-select-sm">
                    <option value="all">All Managers</option>
                    @foreach (($filterOptions['managers'] ?? collect()) as $manager)
                        <option value="{{ $manager->id }}"
                            {{ (string) ($filters['manager'] ?? 'all') === (string) $manager->id ? 'selected' : '' }}>
                            {{ $manager->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2 mt-2">
                <button class="btn btn-primary btn-sm w-100">Apply</button>
                <a href="{{ $baseRoute }}" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
            </div>
        </form>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <a class="btn btn-sm btn-outline-secondary"
                href="{{ route('admin.reports.export', ['report' => $reportKey, 'format' => 'excel'] + request()->query()) }}">
                <span class="material-icons me-1" style="font-size:16px;">file_download</span>
                Export Excel
            </a>
            <a class="btn btn-sm btn-primary"
                href="{{ route('admin.reports.export', ['report' => $reportKey, 'format' => 'pdf'] + request()->query()) }}"
                target="_blank">
                <span class="material-icons me-1" style="font-size:16px;">picture_as_pdf</span>
                Export PDF
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>{{ $title }} Chart</h3>
                    <p>Visual trend for current filters</p>
                </div>
                <div style="height: 320px;">
                    <canvas id="adminReportChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="custom-table">
        <div class="table-header">
            <h3>{{ $title }}</h3>
            <span class="badge bg-light text-dark">{{ count($tableRows) }} records</span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        @foreach ($tableHeaders as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tableRows as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($tableHeaders) }}" class="text-center py-4 text-muted">No records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        (function() {
            const ctx = document.getElementById('adminReportChart');
            if (!ctx) return;

            const chartConfig = @json($chartConfig);
            if (!chartConfig || !Array.isArray(chartConfig.labels) || !Array.isArray(chartConfig.datasets)) return;

            const palettes = ['#2A7DE1', '#29B173', '#F4A11A', '#D94F4F', '#6F7C8E', '#00A3A3', '#A36A00', '#5057a6'];
            const datasetDefaults = {
                borderWidth: 2,
                borderRadius: 6
            };

            const datasets = chartConfig.datasets.map((dataset, index) => {
                const baseColor = palettes[index % palettes.length];
                return Object.assign({}, datasetDefaults, dataset, {
                    borderColor: dataset.borderColor || baseColor,
                    backgroundColor: dataset.backgroundColor || (
                        ['line'].includes(chartConfig.type) ? 'rgba(42,125,225,0.16)' : baseColor
                    ),
                    fill: chartConfig.type === 'line' ? (dataset.fill ?? true) : false,
                    tension: chartConfig.type === 'line' ? 0.35 : 0
                });
            });

            new Chart(ctx, {
                type: chartConfig.type || 'bar',
                data: {
                    labels: chartConfig.labels,
                    datasets
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: chartConfig.type === 'doughnut' ? {} : {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        })();
    </script>
@endsection
