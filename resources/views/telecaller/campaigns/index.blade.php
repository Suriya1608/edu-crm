@extends('layouts.app')

@section('page_title', 'My Campaigns')

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">campaign</span></div>
                <div class="stat-label">Assigned Campaigns</div>
                <div class="stat-value">{{ $totalStats['total'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon amber"><span class="material-icons">people</span></div>
                <div class="stat-label">Total Contacts</div>
                <div class="stat-value">{{ number_format($totalStats['contacts']) }}</div>
            </div>
        </div>
    </div>

    <div class="chart-card">
        <div class="chart-header mb-3">
            <h3>My Campaigns</h3>
        </div>

        @if ($campaigns->isEmpty())
            <div class="text-center py-5">
                <span class="material-icons" style="font-size:48px; color:#cbd5e1;">campaign</span>
                <p class="text-muted mt-2">No campaigns assigned to you yet.</p>
            </div>
        @else
            <div class="row g-3">
                @foreach ($campaigns as $campaign)
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card border h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0 fw-semibold" style="font-size:15px;">
                                        {{ $campaign->name }}
                                    </h5>
                                    @php
                                        $colors = ['active'=>'success','paused'=>'warning','completed'=>'secondary','draft'=>'secondary'];
                                    @endphp
                                    <span class="badge bg-{{ $colors[$campaign->status] ?? 'secondary' }}">
                                        {{ ucfirst($campaign->status) }}
                                    </span>
                                </div>

                                @if ($campaign->description)
                                    <p class="text-muted small mb-3">{{ Str::limit($campaign->description, 80) }}</p>
                                @endif

                                <div class="d-flex align-items-center gap-1 mb-3">
                                    <span class="material-icons text-primary" style="font-size:16px;">people</span>
                                    <span class="fw-semibold">{{ number_format($campaign->my_contacts_count) }}</span>
                                    <span class="text-muted small">contacts assigned to you</span>
                                </div>

                                <a href="{{ route('telecaller.campaigns.show', encrypt($campaign->id)) }}"
                                    class="btn btn-primary btn-sm w-100">
                                    <span class="material-icons me-1" style="font-size:15px;">phone_in_talk</span>
                                    Start Calling
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($campaigns->hasPages())
                <div class="mt-4">{{ $campaigns->links() }}</div>
            @endif
        @endif
    </div>
@endsection
