@extends('layouts.manager.app')

@section('page_title', 'Email History — ' . $campaign->name)

@section('content')
    {{-- Sub-nav --}}
    <div class="lead-profile-nav mb-3">
        <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('manager.campaigns.show', encrypt($campaign->id)) }}" class="btn btn-sm btn-light">
                    <span class="material-icons me-1" style="font-size:16px;">arrow_back</span>Back to Campaign
                </a>
                <div>
                    <h2 class="page-header-title mb-0">Email History</h2>
                    <p class="page-header-subtitle mb-0">{{ $campaign->name }}</p>
                </div>
            </div>
            <a href="{{ route('manager.campaigns.send-email', encrypt($campaign->id)) }}"
                class="btn btn-sm btn-primary">
                <span class="material-icons me-1" style="font-size:15px;">send</span>Send New Email
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="chart-card">
        @if ($logs->isEmpty())
            <div class="text-center py-5 text-muted">
                <span class="material-icons" style="font-size:48px;opacity:.3;">email</span>
                <p class="mt-2">No emails have been sent for this campaign yet.</p>
                <a href="{{ route('manager.campaigns.send-email', encrypt($campaign->id)) }}"
                    class="btn btn-primary btn-sm mt-1">Send First Email</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Template</th>
                            <th>Subject</th>
                            <th>Sent By</th>
                            <th>Recipients</th>
                            <th>Sent</th>
                            <th>Failed</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            @php
                                $statusColors = [
                                    'completed' => 'success',
                                    'failed'    => 'danger',
                                    'sending'   => 'warning',
                                    'pending'   => 'secondary',
                                ];
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $log->template_name }}</td>
                                <td class="text-muted" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    {{ $log->template_subject }}
                                </td>
                                <td>{{ $log->sender?->name ?? '—' }}</td>
                                <td>{{ number_format($log->recipients_count) }}</td>
                                <td>
                                    <span class="text-success fw-semibold">{{ number_format($log->sent_count) }}</span>
                                </td>
                                <td>
                                    @if ($log->failed_count > 0)
                                        <span class="text-danger fw-semibold">{{ number_format($log->failed_count) }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $statusColors[$log->status] ?? 'secondary' }}">
                                        {{ ucfirst($log->status) }}
                                    </span>
                                </td>
                                <td class="text-muted" style="font-size:13px;">
                                    {{ $log->sent_at?->format('d M Y, h:i A') ?? $log->created_at->format('d M Y, h:i A') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="mt-3 px-2">
                    {{ $logs->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
