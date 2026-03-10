@extends('layouts.manager.app')

@section('page_title', 'Dashboard Overview')

@section('header_actions')
    <a href="{{ route('manager.leads.create') }}" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
        <span class="material-icons" style="font-size:16px;">add</span>
        New Lead
    </a>
@endsection

@section('content')
    @php
        $durationLabel = sprintf(
            '%02d:%02d:%02d',
            floor($totalCallDurationSec / 3600),
            floor(($totalCallDurationSec % 3600) / 60),
            $totalCallDurationSec % 60,
        );

        $periodLabels = [
            'today' => 'Today',
            'week' => 'This Week',
            'month' => 'This Month',
        ];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="page-title">
            <h2>Manager Dashboard</h2>
            <span class="realtime-badge">Live</span>
        </div>

        <form method="GET" class="date-filter">
            <span class="material-icons">calendar_today</span>
            <select name="period" onchange="this.form.submit()">
                <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Today</option>
                <option value="week" {{ $period === 'week' ? 'selected' : '' }}>This Week</option>
                <option value="month" {{ $period === 'month' ? 'selected' : '' }}>This Month</option>
            </select>
            <span class="material-icons">expand_more</span>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-lg-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="stat-icon blue">
                        <span class="material-icons">groups</span>
                    </div>
                </div>
                <div class="stat-label">Total Leads (Today / Week / Month)</div>
                <div class="stat-value">{{ $leadsToday }} / {{ $leadsWeek }} / {{ $leadsMonth }}</div>
            </div>
        </div>

        <div class="col-6 col-md-6 col-lg-4">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <span class="material-icons">call</span>
                </div>
                <div class="stat-label">Total Calls Made ({{ $periodLabels[$period] }})</div>
                <div class="stat-value">{{ $totalCallsMade }}</div>
            </div>
        </div>

        <div class="col-6 col-md-6 col-lg-4">
            <div class="stat-card">
                <div class="stat-icon amber">
                    <span class="material-icons">timer</span>
                </div>
                <div class="stat-label">Total Call Duration ({{ $periodLabels[$period] }})</div>
                <div class="stat-value">{{ $durationLabel }}</div>
            </div>
        </div>

        <div class="col-6 col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <span class="material-icons">chat</span>
                </div>
                <div class="stat-label">WhatsApp Conversations</div>
                <div class="stat-value">{{ $whatsAppConversations }}</div>
            </div>
        </div>

        <div class="col-6 col-md-6 col-lg-3">
            <div class="stat-card highlight-success">
                <div class="stat-icon blue">
                    <span class="material-icons">insights</span>
                </div>
                <div class="stat-label">Conversion Rate %</div>
                <div class="stat-value">{{ rtrim(rtrim(number_format($conversionRate, 2), '0'), '.') }}%</div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <span class="material-icons">emoji_events</span>
                </div>
                <div class="stat-label">Best Performing Telecaller</div>
                <div class="stat-value" style="font-size: 18px;">
                    {{ $bestPerformingTelecaller?->name ?? '-' }}
                </div>
                <div class="stat-trend up">
                    {{ $bestPerformingTelecaller ? number_format($bestPerformingTelecaller->conversion_rate, 2) . '% conversion' : 'No data' }}
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
            <div class="stat-card highlight-danger">
                <div class="stat-icon red">
                    <span class="material-icons">event_busy</span>
                </div>
                <div class="stat-label">Missed Followups</div>
                <div class="stat-value">{{ $missedFollowups }}</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="chart-card">
                <div class="chart-header d-flex justify-content-between align-items-start">
                    <div>
                        <h3>Lead Source Overview</h3>
                        <p>{{ $periodLabels[$period] }} distribution by source</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="leadSourceChart"></canvas>
                </div>
            </div>

            <div class="custom-table">
                <div class="table-header">
                    <h3>Telecaller Performance</h3>
                    <a href="{{ route('manager.telecallers') }}" class="text-primary text-decoration-none fw-bold"
                        style="font-size: 12px;">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Staff Name</th>
                                <th>Assigned</th>
                                <th>Total Calls</th>
                                <th class="text-center">Pending FU</th>
                                <th class="text-end">Conv. Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($telecallerStats as $telecaller)
                                <tr>
                                    <td>{{ $telecaller->name }}</td>
                                    <td>{{ $telecaller->assigned_count }}</td>
                                    <td>{{ $telecaller->total_calls }}</td>
                                    <td class="text-center">{{ $telecaller->pending_followups }}</td>
                                    <td class="text-end">{{ number_format($telecaller->conversion_rate, 2) }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No telecaller stats available.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="alerts-panel mb-4">
                <div class="alerts-header">
                    <h3>
                        Telecaller Availability
                        <span class="material-icons" style="color: var(--primary-color); font-size: 20px;">support_agent</span>
                    </h3>
                </div>
                <div id="telecallerPresenceList" class="d-flex flex-column gap-2">
                    @foreach ($telecallerPresence as $presence)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span class="fw-semibold">{{ $presence['name'] }}</span>
                            <span class="badge {{ $presence['is_online'] ? 'bg-success' : 'bg-secondary' }}">
                                {{ $presence['is_online'] ? 'Online' : 'Offline' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="alerts-panel">
                <div class="alerts-header">
                    <h3>
                        Missed Followups
                        <span class="material-icons" style="color: var(--warning-color); font-size: 20px;">campaign</span>
                    </h3>
                    <span class="alert-count">{{ $missedFollowups }}</span>
                </div>

                <div class="alerts-content">
                    @forelse($missedFollowupList as $followup)
                        <div class="alert-item critical">
                            <div class="alert-item-header">
                                <p class="alert-item-name">{{ $followup->lead->name ?? 'Lead #' . $followup->lead_id }}</p>
                                <span class="alert-time">{{ \Carbon\Carbon::parse($followup->next_followup)->format('d M') }}</span>
                            </div>
                            <p class="alert-description">
                                Assigned: {{ $followup->lead->assignedUser->name ?? 'Unassigned' }}
                            </p>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No missed followups.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-12">
            <div class="custom-table">
                <div class="table-header">
                    <h3>Missed Inbound Callbacks</h3>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Lead</th>
                                <th>Customer Number</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($missedInboundCalls as $missedCall)
                                <tr>
                                    <td>{{ $missedCall->created_at?->format('d M, h:i A') }}</td>
                                    <td>{{ $missedCall->lead?->lead_code ?? '-' }} - {{ $missedCall->lead?->name ?? 'Unknown' }}</td>
                                    <td>{{ $missedCall->customer_number ?? $missedCall->lead?->phone ?? '-' }}</td>
                                    <td><span class="badge bg-danger">Missed</span></td>
                                    <td>
                                        @if ($missedCall->lead_id)
                                            <a class="btn btn-sm btn-primary"
                                                href="{{ route('manager.leads.show', encrypt($missedCall->lead_id)) }}">
                                                Call Back
                                            </a>
                                        @else
                                            <button class="btn btn-sm btn-secondary" disabled>Call Back</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No missed inbound callbacks.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Course Performance Widget --}}
    @if(!empty($courseStats) && $courseStats->isNotEmpty())
    <div class="row g-4 mt-1">
        <div class="col-12">
            <div class="custom-table">
                <div class="table-header">
                    <div>
                        <h3>Course Performance</h3>
                        <p class="text-muted mb-0" style="font-size:12px;">Lead volume and conversion rate by course</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0" style="font-size:13px;">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Total Leads</th>
                                <th>Conversions</th>
                                <th>Rate</th>
                                <th style="min-width:180px;">Volume</th>
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
                                    <span class="badge" style="background:{{ $row['rate'] >= 30 ? '#dcfce7' : ($row['rate'] >= 10 ? '#fef9c3' : '#fee2e2') }}; color:{{ $row['rate'] >= 30 ? '#16a34a' : ($row['rate'] >= 10 ? '#92400e' : '#dc2626') }}; padding:3px 8px; border-radius:6px; font-size:12px;">
                                        {{ $row['rate'] }}%
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="flex:1; background:#e2e8f0; border-radius:4px; height:10px; overflow:hidden; min-width:100px;">
                                            <div style="background:#137fec; height:100%; width:{{ round($row['total'] / $maxTotal * 100) }}%; border-radius:4px; min-width:{{ $row['total'] > 0 ? '4px' : '0' }};"></div>
                                        </div>
                                        <span style="font-size:11px; color:#64748b; white-space:nowrap;">{{ $row['total'] }} leads</span>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        (function() {
            const ctx = document.getElementById('leadSourceChart');
            if (!ctx || typeof window.Chart === 'undefined') return;

            const labels = @json($leadSource->pluck('source')->map(fn($v) => $v ?: 'unknown')->values());
            const values = @json($leadSource->pluck('total')->values());

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: ['#137fec', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#0ea5e9'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        })();

        (function() {
            const url = @json(route('manager.telecaller-status.snapshot'));
            const container = document.getElementById('telecallerPresenceList');
            if (!container) return;

            async function refreshPresence() {
                try {
                    const res = await fetch(url, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    const rows = (data.telecallers || []).map(function(t) {
                        const badge = t.is_online ? 'bg-success' : 'bg-secondary';
                        const label = t.is_online ? 'Online' : 'Offline';
                        return '<div class="d-flex justify-content-between align-items-center py-2 border-bottom">' +
                            '<span class="fw-semibold">' + t.name + '</span>' +
                            '<span class="badge ' + badge + '">' + label + '</span>' +
                            '</div>';
                    });
                    container.innerHTML = rows.join('');
                } catch (e) {}
            }

            setInterval(refreshPresence, 30000);
        })();
    </script>
@endpush
