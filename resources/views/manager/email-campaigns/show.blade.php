@extends('layouts.manager.app')

@section('page_title', $campaign->name . ' — Analytics')

@section('content')
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('manager.email-campaigns.index') }}" class="btn btn-sm btn-light">
            <span class="material-icons me-1" style="font-size:16px;">arrow_back</span>Back
        </a>
        <div>
            <h2 class="page-header-title mb-0">{{ $campaign->name }}</h2>
            <p class="page-header-subtitle mb-0">
                @php
                    $statusColors = ['draft'=>'secondary','scheduled'=>'info','sending'=>'warning','completed'=>'success','failed'=>'danger'];
                @endphp
                <span class="badge bg-{{ $statusColors[$campaign->status] ?? 'secondary' }} me-2">{{ ucfirst($campaign->status) }}</span>
                Template: <strong>{{ $campaign->template_name }}</strong>
                @if ($campaign->course_filter)
                    &mdash; Course: <strong>{{ $campaign->course_filter }}</strong>
                @endif
            </p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">people</span></div>
                <div class="stat-label">Recipients</div>
                <div class="stat-value">{{ number_format($campaign->recipients_count) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card">
                <div class="stat-icon green"><span class="material-icons">check_circle</span></div>
                <div class="stat-label">Sent</div>
                <div class="stat-value">{{ number_format($campaign->sent_count) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">visibility</span></div>
                <div class="stat-label">Opened</div>
                <div class="stat-value">{{ number_format($campaign->opened_count) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card">
                <div class="stat-icon red"><span class="material-icons">error_outline</span></div>
                <div class="stat-label">Failed</div>
                <div class="stat-value">{{ number_format($campaign->failed_count) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card highlight-success">
                <div class="stat-icon green"><span class="material-icons">local_post_office</span></div>
                <div class="stat-label">Delivery Rate</div>
                <div class="stat-value">{{ $campaign->delivery_rate }}%</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">drafts</span></div>
                <div class="stat-label">Open Rate</div>
                <div class="stat-value">{{ $campaign->open_rate }}%</div>
            </div>
        </div>
    </div>

    {{-- Progress bars --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="chart-card">
                <div class="d-flex justify-content-between mb-1" style="font-size:13px;">
                    <span class="fw-semibold">Delivery Rate</span>
                    <span>{{ $campaign->sent_count }} / {{ $campaign->recipients_count }}</span>
                </div>
                <div class="progress" style="height:8px;">
                    <div class="progress-bar bg-success" style="width:{{ $campaign->delivery_rate }}%"></div>
                </div>
                <div class="text-muted mt-1" style="font-size:12px;">{{ $campaign->delivery_rate }}% delivered</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-card">
                <div class="d-flex justify-content-between mb-1" style="font-size:13px;">
                    <span class="fw-semibold">Open Rate</span>
                    <span>{{ $campaign->opened_count }} / {{ $campaign->sent_count }}</span>
                </div>
                <div class="progress" style="height:8px;">
                    <div class="progress-bar bg-primary" style="width:{{ $campaign->open_rate }}%"></div>
                </div>
                <div class="text-muted mt-1" style="font-size:12px;">{{ $campaign->open_rate }}% opened</div>
            </div>
        </div>
    </div>

    {{-- Recipients table --}}
    <div class="chart-card">
        <h6 class="fw-semibold mb-3">Recipients
            <span class="text-muted fw-normal" style="font-size:13px;">({{ number_format($campaign->recipients_count) }} total)</span>
        </h6>
        @if ($recipients->isEmpty())
            <div class="text-center py-4 text-muted" style="font-size:13px;">No recipients found.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Email</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Sent At</th>
                            <th>Opened At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recipients as $r)
                            @php
                                $rColors = ['pending'=>'secondary','sent'=>'success','failed'=>'danger','bounced'=>'warning'];
                            @endphp
                            <tr>
                                <td style="font-size:13px;">{{ $r->email }}</td>
                                <td class="text-muted" style="font-size:13px;">{{ $r->name ?: '—' }}</td>
                                <td>
                                    <span class="badge bg-{{ $rColors[$r->status] ?? 'secondary' }}">
                                        {{ ucfirst($r->status) }}
                                    </span>
                                    @if ($r->opened_at)
                                        <span class="badge bg-primary ms-1">Opened</span>
                                    @endif
                                </td>
                                <td class="text-muted" style="font-size:12px;">
                                    {{ $r->sent_at?->format('d M, h:i A') ?? '—' }}
                                </td>
                                <td class="text-muted" style="font-size:12px;">
                                    {{ $r->opened_at?->format('d M, h:i A') ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($recipients->hasPages())
                <div class="mt-3 px-2">{{ $recipients->links() }}</div>
            @endif
        @endif
    </div>
@endsection
