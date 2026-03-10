@extends('layouts.manager.app')

@section('page_title', 'Campaigns')

@section('header_actions')
    <a href="{{ route('manager.campaigns.create') }}" class="btn btn-primary d-flex align-items-center gap-1">
        <span class="material-icons" style="font-size:16px;">add</span>
        New Campaign
    </a>
@endsection

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">campaign</span></div>
                <div class="stat-label">Total Campaigns</div>
                <div class="stat-value">{{ $totalStats['total'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon green"><span class="material-icons">play_circle</span></div>
                <div class="stat-label">Active</div>
                <div class="stat-value">{{ $totalStats['active'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon amber"><span class="material-icons">pause_circle</span></div>
                <div class="stat-label">Paused</div>
                <div class="stat-value">{{ $totalStats['paused'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon red"><span class="material-icons">check_circle</span></div>
                <div class="stat-label">Completed</div>
                <div class="stat-value">{{ $totalStats['completed'] }}</div>
            </div>
        </div>
    </div>

    <div class="chart-card">
        <div class="chart-header mb-3">
            <h3>All Campaigns</h3>
        </div>

        @if ($campaigns->isEmpty())
            <div class="text-center py-5">
                <span class="material-icons" style="font-size:48px; color:#cbd5e1;">campaign</span>
                <p class="text-muted mt-2">No campaigns yet. Create your first campaign to get started.</p>
                <a href="{{ route('manager.campaigns.create') }}" class="btn btn-primary mt-2">
                    <span class="material-icons me-1" style="font-size:16px;">add</span>New Campaign
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Campaign Name</th>
                            <th>Status</th>
                            <th>Total Contacts</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($campaigns as $campaign)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $campaign->name }}</div>
                                    @if ($campaign->description)
                                        <div class="text-muted small">{{ Str::limit($campaign->description, 60) }}</div>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $colors = ['active'=>'success','paused'=>'warning','completed'=>'secondary','draft'=>'light'];
                                        $c = $colors[$campaign->status] ?? 'secondary';
                                    @endphp
                                    <span class="badge bg-{{ $c }}">{{ ucfirst($campaign->status) }}</span>
                                </td>
                                <td>
                                    <span class="fw-semibold">{{ number_format($campaign->contacts_count) }}</span>
                                </td>
                                <td class="text-muted small">{{ $campaign->created_at->format('d M Y') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('manager.campaigns.show', encrypt($campaign->id)) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        <span class="material-icons" style="font-size:15px;">visibility</span>
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($campaigns->hasPages())
                <div class="mt-3">{{ $campaigns->links() }}</div>
            @endif
        @endif
    </div>
@endsection
