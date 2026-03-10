@extends('layouts.app')

@section('page_title', 'Campaign Performance')

@section('content')
    {{-- Page Header --}}
    <div class="lead-profile-nav mb-3">
        <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <div>
                    <h2 class="page-header-title mb-0">Campaign Performance</h2>
                    <p class="page-header-subtitle mb-0">Monitor effectiveness across all campaigns</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="chart-card mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-2">
                <label class="form-label small fw-semibold mb-1">Manager</label>
                <select name="manager" class="form-select form-select-sm">
                    <option value="">All Managers</option>
                    @foreach ($managers as $m)
                        <option value="{{ $m->id }}" {{ request('manager') == $m->id ? 'selected' : '' }}>
                            {{ $m->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small fw-semibold mb-1">Campaign</label>
                <select name="campaign" class="form-select form-select-sm">
                    <option value="">All Campaigns</option>
                    @foreach ($campaigns as $c)
                        <option value="{{ $c->id }}" {{ request('campaign') == $c->id ? 'selected' : '' }}>
                            {{ $c->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small fw-semibold mb-1">Telecaller</label>
                <select name="telecaller" class="form-select form-select-sm">
                    <option value="">All Telecallers</option>
                    @foreach ($telecallers as $tc)
                        <option value="{{ $tc->id }}" {{ request('telecaller') == $tc->id ? 'selected' : '' }}>
                            {{ $tc->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">From Date</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">To Date</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm flex-grow-1">
                    <span class="material-icons me-1" style="font-size:15px;">filter_list</span>Filter
                </button>
                <a href="{{ route('admin.campaigns.performance') }}" class="btn btn-light btn-sm">Clear</a>
            </div>
        </form>
    </div>

    {{-- Summary Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">people</span></div>
                <div class="stat-label">Total Contacts</div>
                <div class="stat-value">{{ number_format($stats['total_contacts']) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">assignment_ind</span></div>
                <div class="stat-label">Assigned</div>
                <div class="stat-value">{{ number_format($stats['assigned']) }}</div>
                @if ($stats['total_contacts'] > 0)
                    <div class="stat-label" style="font-size:11px;color:#64748b;">
                        {{ round($stats['assigned'] / $stats['total_contacts'] * 100) }}% of total
                    </div>
                @endif
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon green"><span class="material-icons">phone_in_talk</span></div>
                <div class="stat-label">Calls Completed</div>
                <div class="stat-value">{{ number_format($stats['calls_completed']) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon green"><span class="material-icons">chat</span></div>
                <div class="stat-label">WhatsApp Sent</div>
                <div class="stat-value">{{ number_format($stats['whatsapp_sent']) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon green"><span class="material-icons">thumb_up</span></div>
                <div class="stat-label">Interested</div>
                <div class="stat-value">{{ number_format($stats['interested']) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon red" style="background:#fee2e2;"><span class="material-icons" style="color:#ef4444;">thumb_down</span></div>
                <div class="stat-label">Not Interested</div>
                <div class="stat-value">{{ number_format($stats['not_interested']) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon amber"><span class="material-icons">event_repeat</span></div>
                <div class="stat-label">Follow-ups Pending</div>
                <div class="stat-value">{{ number_format($stats['followups_pending']) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card highlight-success">
                <div class="stat-icon green"><span class="material-icons">check_circle</span></div>
                <div class="stat-label">Converted</div>
                <div class="stat-value">{{ number_format($stats['converted']) }}</div>
                @if ($stats['total_contacts'] > 0)
                    <div class="stat-label" style="font-size:11px;color:#10b981;">
                        {{ round($stats['converted'] / $stats['total_contacts'] * 100, 1) }}% conversion
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Per-Campaign Breakdown Table --}}
    @if (!empty($perCampaign))
        <div class="chart-card">
            <div class="chart-header mb-3">
                <h3>Breakdown by Campaign</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Campaign</th>
                            <th>Manager</th>
                            <th>Status</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Assigned</th>
                            <th class="text-center">Called</th>
                            <th class="text-center">WhatsApp</th>
                            <th class="text-center">Interested</th>
                            <th class="text-center">Not Interested</th>
                            <th class="text-center">Follow-ups</th>
                            <th class="text-center">Converted</th>
                            <th class="text-center">Conv. %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($perCampaign as $row)
                            @php
                                $convPct = $row['total_contacts'] > 0
                                    ? round($row['converted'] / $row['total_contacts'] * 100, 1)
                                    : 0;
                                $statusColors = ['active'=>'success','paused'=>'warning','completed'=>'secondary','draft'=>'secondary'];
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $row['name'] }}</td>
                                <td class="text-muted" style="font-size:13px;">{{ $row['manager'] }}</td>
                                <td>
                                    <span class="badge bg-{{ $statusColors[$row['status']] ?? 'secondary' }}">
                                        {{ ucfirst($row['status']) }}
                                    </span>
                                </td>
                                <td class="text-center">{{ number_format($row['total_contacts']) }}</td>
                                <td class="text-center">{{ number_format($row['assigned']) }}</td>
                                <td class="text-center">{{ number_format($row['calls_completed']) }}</td>
                                <td class="text-center">{{ number_format($row['whatsapp_sent']) }}</td>
                                <td class="text-center text-success fw-semibold">{{ number_format($row['interested']) }}</td>
                                <td class="text-center text-danger">{{ number_format($row['not_interested']) }}</td>
                                <td class="text-center text-warning">{{ number_format($row['followups_pending']) }}</td>
                                <td class="text-center fw-bold text-success">{{ number_format($row['converted']) }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $convPct >= 10 ? 'bg-success' : ($convPct >= 5 ? 'bg-warning text-dark' : 'bg-light text-dark border') }}">
                                        {{ $convPct }}%
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="chart-card text-center py-5">
            <span class="material-icons" style="font-size:48px;color:#cbd5e1;">bar_chart</span>
            <p class="text-muted mt-2">No campaigns found. Managers need to create campaigns first.</p>
        </div>
    @endif
@endsection
