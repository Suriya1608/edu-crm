@extends('layouts.app')

@section('page_title', 'Lead Timeline')

@section('content')
    <style>
        .admin-lead-hero {
            background: linear-gradient(135deg, #0b2447 0%, #13467f 55%, #1f77d0 100%);
            border-radius: 16px;
            padding: 22px 24px;
            color: #fff;
            box-shadow: 0 10px 28px rgba(15, 39, 73, 0.22);
        }

        .admin-lead-hero .meta {
            color: rgba(255, 255, 255, 0.85);
            font-size: 13px;
            letter-spacing: 0.01em;
        }

        .admin-lead-hero h2 {
            font-size: calc(2rem - 2pt);
        }

        .admin-lead-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 11px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.38);
            background: rgba(255, 255, 255, 0.1);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .admin-info-card {
            border-radius: 14px;
            border: 1px solid #dbe5f1;
            background: #fff;
            padding: 13px 14px;
            min-height: 96px;
            box-shadow: 0 4px 12px rgba(10, 31, 68, 0.05);
        }

        .admin-info-label {
            color: #5b6f8f;
            font-size: 10px;
            letter-spacing: 0.08em;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .admin-info-value {
            color: #0d1f3a;
            font-weight: 700;
            font-size: 27px;
            line-height: 1.1;
            word-break: break-word;
        }

        .admin-info-value.small {
            font-size: 18px;
        }

        .admin-status-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            background: #eaf2ff;
            color: #1657b8;
        }

        .admin-timeline {
            border-radius: 16px;
            border: 1px solid #dbe5f1;
            background: #fff;
            overflow: hidden;
        }

        .admin-timeline-head {
            padding: 18px 20px;
            border-bottom: 1px solid #e8eef7;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .admin-timeline-count {
            border-radius: 999px;
            background: #edf4ff;
            color: #275ca8;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 10px;
        }

        .admin-timeline-body {
            max-height: 640px;
            overflow-y: auto;
            padding: 12px 20px 20px;
        }

        .admin-timeline-item {
            display: grid;
            grid-template-columns: 36px 1fr;
            gap: 14px;
            padding: 12px 0;
            border-bottom: 1px dashed #e8eef7;
        }

        .admin-timeline-item:last-child {
            border-bottom: 0;
        }

        .admin-timeline-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #edf3fb;
            color: #0f365c;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .admin-timeline-type {
            display: inline-block;
            border-radius: 999px;
            padding: 4px 9px;
            font-size: 11px;
            background: #f0f5ff;
            color: #234d8f;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
    </style>

    <div class="admin-lead-hero mb-3">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="meta mb-2">Lead Profile</div>
                <h2 class="mb-1 fw-bold">{{ $lead->name }}</h2>
                <div class="meta">{{ $lead->lead_code }} | {{ $lead->phone }}</div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="admin-status-pill">{{ str_replace('_', ' ', $lead->status) }}</span>
                <a href="{{ url()->previous() }}" class="btn btn-sm btn-light">Back</a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="admin-info-card">
                <div class="admin-info-label">Mobile Number</div>
                <div class="admin-info-value small">{{ $lead->phone ?: '-' }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="admin-info-card">
                <div class="admin-info-label">Email ID</div>
                <div class="admin-info-value small">{{ $lead->email ?: '-' }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="admin-info-card">
                <div class="admin-info-label">Course Applied</div>
                <div class="admin-info-value small">{{ $lead->course ?: '-' }}</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="admin-info-card">
                <div class="admin-info-label">Status</div>
                <div class="admin-info-value small">{{ str_replace('_', ' ', strtoupper($lead->status)) }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="admin-info-card">
                <div class="admin-info-label">Manager</div>
                <div class="admin-info-value small">{{ $lead->assignedBy->name ?? '-' }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="admin-info-card">
                <div class="admin-info-label">Telecaller</div>
                <div class="admin-info-value small">{{ $lead->assignedUser->name ?? '-' }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="admin-info-card">
                <div class="admin-info-label">Source</div>
                <div class="admin-info-value small">{{ $lead->source }}</div>
            </div>
        </div>
    </div>

    @php
        $activities = $lead->activities()->latest()->get();
    @endphp

    <div class="admin-timeline">
        <div class="admin-timeline-head">
            <h4 class="mb-0 fw-bold">Full Activity Timeline</h4>
            <span class="admin-timeline-count">{{ $activities->count() }} entries</span>
        </div>

        <div class="admin-timeline-body">
            @forelse($activities as $activity)
                <div class="admin-timeline-item">
                    <div class="admin-timeline-icon">
                        <span class="material-icons">
                            @switch($activity->type)
                                @case('call')
                                    call
                                @break
                                @case('note')
                                    description
                                @break
                                @case('whatsapp')
                                    chat
                                @break
                                @case('assignment')
                                    person
                                @break
                                @case('followup')
                                    event
                                @break
                                @default
                                    info
                            @endswitch
                        </span>
                    </div>
                    <div>
                        <span class="admin-timeline-type">{{ str_replace('_', ' ', $activity->type) }}</span>
                        <p class="mb-1 fw-semibold">{{ $activity->description }}</p>
                        <small class="text-muted">{{ $activity->user->name ?? '-' }} | {{ $activity->created_at->diffForHumans() }}</small>
                    </div>
                </div>
            @empty
                <p class="text-muted mb-0 p-3">No activity found for this lead.</p>
            @endforelse
        </div>
    </div>
@endsection
