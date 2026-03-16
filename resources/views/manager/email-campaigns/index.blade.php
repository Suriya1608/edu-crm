@extends('layouts.manager.app')

@section('page_title', 'Email Campaigns')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h2 class="page-header-title mb-0">Email Campaigns</h2>
            <p class="page-header-subtitle mb-0">Create and track email marketing campaigns</p>
        </div>
        <a href="{{ route('manager.email-campaigns.create') }}" class="btn btn-primary btn-sm">
            <span class="material-icons me-1" style="font-size:16px;">add</span>New Campaign
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="chart-card">
        @if ($campaigns->isEmpty())
            <div class="text-center py-5 text-muted">
                <span class="material-icons" style="font-size:48px;opacity:.3;">mark_email_read</span>
                <p class="mt-2">No email campaigns yet.</p>
                <a href="{{ route('manager.email-campaigns.create') }}" class="btn btn-primary btn-sm mt-1">Create First Campaign</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Campaign</th>
                            <th>Template</th>
                            <th>Status</th>
                            <th>Recipients</th>
                            <th>Sent</th>
                            <th>Opened</th>
                            <th>Failed</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($campaigns as $ec)
                            @php
                                $statusColors = [
                                    'draft'     => 'secondary',
                                    'scheduled' => 'info',
                                    'sending'   => 'warning',
                                    'completed' => 'success',
                                    'failed'    => 'danger',
                                ];
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('manager.email-campaigns.show', $ec->id) }}"
                                        class="fw-semibold text-decoration-none">{{ $ec->name }}</a>
                                    @if ($ec->description)
                                        <div class="text-muted" style="font-size:12px;">{{ Str::limit($ec->description, 60) }}</div>
                                    @endif
                                </td>
                                <td class="text-muted" style="font-size:13px;">{{ $ec->template_name }}</td>
                                <td>
                                    <span class="badge bg-{{ $statusColors[$ec->status] ?? 'secondary' }}">
                                        {{ ucfirst($ec->status) }}
                                    </span>
                                </td>
                                <td>{{ number_format($ec->recipients_count) }}</td>
                                <td>
                                    <span class="text-success fw-semibold">{{ number_format($ec->sent_count) }}</span>
                                    @if ($ec->recipients_count > 0)
                                        <span class="text-muted" style="font-size:11px;">({{ $ec->delivery_rate }}%)</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-primary fw-semibold">{{ number_format($ec->opened_count) }}</span>
                                    @if ($ec->sent_count > 0)
                                        <span class="text-muted" style="font-size:11px;">({{ $ec->open_rate }}%)</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($ec->failed_count > 0)
                                        <span class="text-danger fw-semibold">{{ number_format($ec->failed_count) }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td class="text-muted" style="font-size:13px;">
                                    {{ $ec->scheduled_at
                                        ? 'Sched: ' . $ec->scheduled_at->format('d M, h:i A')
                                        : $ec->created_at->format('d M Y') }}
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="{{ route('manager.email-campaigns.show', $ec->id) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            <span class="material-icons" style="font-size:15px;">bar_chart</span>
                                        </a>
                                        <form action="{{ route('manager.email-campaigns.destroy', $ec->id) }}"
                                            method="POST"
                                            onsubmit="return confirm('Delete this campaign?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">
                                                <span class="material-icons" style="font-size:15px;">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($campaigns->hasPages())
                <div class="mt-3 px-2">{{ $campaigns->links() }}</div>
            @endif
        @endif
    </div>
@endsection
