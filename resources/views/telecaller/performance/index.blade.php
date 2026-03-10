@extends('layouts.app')

@section('page_title', $title)

@section('content')
    <div class="chart-card mb-3">
        <div class="chart-header mb-2">
            <h3>{{ $title }}</h3>
            <p>{{ $start->format('d M Y') }} to {{ $end->format('d M Y') }}</p>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('telecaller.performance.daily') }}"
                class="btn btn-sm {{ $scope === 'daily' ? 'btn-primary' : 'btn-outline-primary' }}">
                Daily Performance
            </a>
            <a href="{{ route('telecaller.performance.weekly') }}"
                class="btn btn-sm {{ $scope === 'weekly' ? 'btn-primary' : 'btn-outline-primary' }}">
                Weekly Performance
            </a>
            <a href="{{ route('telecaller.performance.monthly') }}"
                class="btn btn-sm {{ $scope === 'monthly' ? 'btn-primary' : 'btn-outline-primary' }}">
                Monthly Summary
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <span class="material-icons">call</span>
                </div>
                <div class="stat-label">Calls Handled</div>
                <div class="stat-value">{{ $callsHandled }}</div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <span class="material-icons">trending_up</span>
                </div>
                <div class="stat-label">Conversion %</div>
                <div class="stat-value">{{ number_format($conversionPercent, 2) }}%</div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon amber">
                    <span class="material-icons">task_alt</span>
                </div>
                <div class="stat-label">Followups Completed</div>
                <div class="stat-value">{{ $followupsCompleted }}</div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card highlight-success">
                <div class="stat-icon red">
                    <span class="material-icons">timer</span>
                </div>
                <div class="stat-label">Response Time</div>
                <div class="stat-value">{{ $responseTimeLabel }}</div>
            </div>
        </div>
    </div>

    <div class="custom-table">
        <div class="table-header">
            <h3>Call Activity Breakdown</h3>
            <span class="text-muted" style="font-size:12px;">{{ $dailyBreakdown->count() }} day(s)</span>
        </div>

        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Calls Handled</th>
                        <th>Talk Time</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dailyBreakdown as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row->day)->format('d M Y') }}</td>
                            <td>{{ $row->calls }}</td>
                            <td>{{ gmdate('H:i:s', max(0, (int) $row->talk_seconds)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center py-4 text-muted">No activity for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

