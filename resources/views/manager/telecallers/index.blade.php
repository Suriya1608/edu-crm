@extends('layouts.manager.app')

@section('page_title', 'Telecaller Management')

@section('content')

    @php
        $formatDuration = function ($seconds) {
            $seconds = (int) $seconds;
            return sprintf('%02d:%02d:%02d', floor($seconds / 3600), floor(($seconds % 3600) / 60), $seconds % 60);
        };
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">support_agent</span></div>
                <div class="stat-label">Total Telecallers</div>
                <div class="stat-value">{{ $totalTelecallers }}</div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon green"><span class="material-icons">wifi</span></div>
                <div class="stat-label">Online</div>
                <div class="stat-value">{{ $onlineTelecallers }}</div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon amber"><span class="material-icons">phone_in_talk</span></div>
                <div class="stat-label">On Call</div>
                <div class="stat-value">{{ $onCallTelecallers }}</div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon red"><span class="material-icons">pause_circle</span></div>
                <div class="stat-label">Idle / Offline</div>
                <div class="stat-value">{{ $idleTelecallers + $offlineTelecallers }}</div>
            </div>
        </div>
    </div>

    <div class="custom-table">
        <div class="table-header">
            <h3>Telecaller Live Performance Board</h3>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
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
                        <th>Pause / Break Tracking</th>
                        <th>Missed Follow Up</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($telecallers as $tele)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                <div class="fw-semibold">{{ $tele->name }}</div>
                                <small class="text-muted">Conv. {{ number_format($tele->conversion_rate, 2) }}%</small>
                            </td>
                            <td>
                                @if ($tele->online_offline_status === 'online')
                                    <span class="badge bg-success">Online</span>
                                @else
                                    <span class="badge bg-secondary">Offline</span>
                                @endif
                            </td>
                            <td>
                                @if ($tele->active_call_indicator)
                                    <span class="badge bg-primary">Live Call</span>
                                @else
                                    <span class="badge bg-light text-dark">No Active Call</span>
                                @endif
                            </td>
                            <td><span class="badge bg-dark">{{ $tele->total_call_count }}</span></td>
                            <td><span class="fw-semibold">{{ $formatDuration($tele->total_talk_time_sec) }}</span></td>
                            <td><span class="badge bg-primary">{{ $tele->today_call_count }}</span></td>
                            <td><span class="fw-semibold">{{ $formatDuration($tele->today_talk_time_sec) }}</span></td>
                            <td>
                                @php
                                    $ratingColor = match ($tele->performance_rating) {
                                        'A+', 'A' => 'bg-success',
                                        'B' => 'bg-primary',
                                        'C' => 'bg-warning text-dark',
                                        default => 'bg-danger',
                                    };
                                @endphp
                                <span class="badge {{ $ratingColor }}">{{ $tele->performance_rating }}</span>
                            </td>
                            <td>
                                @if ($tele->break_tracking_status === 'on_call')
                                    <span class="badge bg-primary">On Call</span>
                                @elseif($tele->break_tracking_status === 'online')
                                    <span class="badge bg-success">Online</span>
                                @elseif($tele->break_tracking_status === 'idle')
                                    <span class="badge bg-warning text-dark">Idle / Break</span>
                                @else
                                    <span class="badge bg-secondary">Offline</span>
                                @endif
                            </td>
                            <td><span class="badge bg-danger">{{ $tele->missed_followup_count }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-4">No Telecallers Found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
