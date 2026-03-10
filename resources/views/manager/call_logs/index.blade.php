@extends('layouts.manager.app')

@section('page_title', 'Call Logs')

@section('content')
    @php
        $scopeLabels = [
            'all' => 'All Calls',
            'inbound' => 'All Inbound Calls',
            'outbound' => 'All Outbound Calls',
            'missed' => 'Missed Calls',
        ];
    @endphp

    <div class="card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">{{ $scopeLabels[$scope] ?? 'Call Logs' }}</h5>
            <span class="badge bg-light text-dark">{{ $callLogs->total() }} records</span>
        </div>
    </div>

    <div class="card p-3 mb-3">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="scope" value="{{ $scope }}">

            <div class="col-md-3">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="{{ request('date') }}">
            </div>

            <div class="col-md-3">
                <label class="form-label">Telecaller</label>
                <select name="telecaller" class="form-select">
                    <option value="">All</option>
                    @foreach ($telecallers as $telecaller)
                        <option value="{{ $telecaller->id }}" {{ request('telecaller') == $telecaller->id ? 'selected' : '' }}>
                            {{ $telecaller->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    @foreach ($statusOptions as $status)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Apply</button>
                <a href="{{ route('manager.call-logs.index', ['scope' => $scope]) }}" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>

    <div class="card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>S.No</th>
                        <th>Date</th>
                        <th>Lead ID</th>
                        <th>Lead</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Duration</th>
                        <th>Telecaller</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($callLogs as $index => $call)
                        @php
                            $type = $call->user_id ? 'outbound' : 'inbound';
                            $duration = (int) ($call->duration ?? 0);
                            $durationLabel = sprintf('%02d:%02d:%02d', floor($duration / 3600), floor(($duration % 3600) / 60), $duration % 60);
                            $serial = ($callLogs->currentPage() - 1) * $callLogs->perPage() + $index + 1;
                        @endphp
                        <tr>
                            <td>{{ $serial }}</td>
                            <td>{{ optional($call->created_at)->format('d M Y, h:i A') }}</td>
                            <td>{{ $call->lead->lead_code ?? ('#' . $call->lead_id) }}</td>
                            <td>
                                <div class="fw-semibold">{{ $call->lead->name ?? 'N/A' }}</div>
                                <small class="text-muted">{{ $call->lead->phone ?? '-' }}</small>
                            </td>
                            <td>
                                <span class="badge {{ $type === 'outbound' ? 'bg-primary' : 'bg-secondary' }}">
                                    {{ ucfirst($type) }}
                                </span>
                            </td>
                            <td><span class="badge bg-light text-dark">{{ $call->status ?? '-' }}</span></td>
                            <td class="fw-semibold">{{ $durationLabel }}</td>
                            <td>{{ $call->user->name ?? 'Not assigned' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No call logs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">
                Showing {{ $callLogs->firstItem() ?? 0 }} to {{ $callLogs->lastItem() ?? 0 }} of {{ $callLogs->total() }}
                results
            </small>
            {{ $callLogs->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
