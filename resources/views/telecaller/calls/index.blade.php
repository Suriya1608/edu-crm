@extends('layouts.app')

@section('page_title', $title)

@section('content')
    <div class="chart-card mb-3">
        <div class="chart-header mb-2">
            <h3>{{ $title }}</h3>
            <p>Track all your calls with status and actions</p>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('telecaller.calls.outbound') }}"
                class="btn btn-sm {{ $scope === 'outbound' ? 'btn-primary' : 'btn-outline-primary' }}">
                Outbound Calls
            </a>
            <a href="{{ route('telecaller.calls.inbound') }}"
                class="btn btn-sm {{ $scope === 'inbound' ? 'btn-primary' : 'btn-outline-primary' }}">
                Inbound Calls
            </a>
            <a href="{{ route('telecaller.calls.missed') }}"
                class="btn btn-sm {{ $scope === 'missed' ? 'btn-danger' : 'btn-outline-danger' }}">
                Missed Calls
            </a>
            <a href="{{ route('telecaller.calls.history') }}"
                class="btn btn-sm {{ $scope === 'history' ? 'btn-dark' : 'btn-outline-dark' }}">
                Call History
            </a>
        </div>
    </div>

    <div class="chart-card mb-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="{{ request('date') }}">
            </div>

            <div class="col-md-4">
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

            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Apply</button>
                <a href="{{ route('telecaller.calls.' . $scope) }}" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>

    <div class="custom-table">
        <div class="table-header">
            <h3>{{ $title }} List</h3>
            <span class="text-muted" style="font-size:12px;">{{ $callLogs->total() }} records</span>
        </div>

        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Date</th>
                        <th>Lead</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Duration</th>
                        <th>Recording</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($callLogs as $index => $call)
                        @php
                            $serial = ($callLogs->currentPage() - 1) * $callLogs->perPage() + $index + 1;
                            $type = ($call->direction === 'inbound') ? 'inbound' : 'outbound';
                            $duration = (int) ($call->duration ?? 0);
                            $durationLabel = sprintf('%02d:%02d:%02d', floor($duration / 3600), floor(($duration % 3600) / 60), $duration % 60);

                            $status = strtolower((string) ($call->status ?? ''));
                            $statusClass = 'bg-secondary';
                            if (in_array($status, ['ringing'], true)) {
                                $statusClass = 'bg-warning text-dark';
                            } elseif (in_array($status, ['in-progress', 'answered'], true)) {
                                $statusClass = 'bg-info text-dark';
                            } elseif (in_array($status, ['completed'], true)) {
                                $statusClass = 'bg-success';
                            } elseif (in_array($status, ['missed', 'no-answer', 'busy', 'failed', 'canceled'], true)) {
                                $statusClass = 'bg-danger';
                            }

                            $callbackNumber = $call->lead->phone ?? $call->customer_number;
                        @endphp

                        <tr>
                            <td>{{ $serial }}</td>
                            <td>{{ optional($call->created_at)->format('d M Y, h:i A') }}</td>
                            <td>
                                <div class="fw-semibold">{{ $call->lead->name ?? 'N/A' }}</div>
                                <small class="text-muted">{{ $call->lead->lead_code ?? '-' }} | {{ $call->lead->phone ?? $call->customer_number }}</small>
                            </td>
                            <td>
                                <span class="badge {{ $type === 'outbound' ? 'bg-primary' : 'bg-dark' }}">
                                    {{ ucfirst($type) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $statusClass }}">
                                    {{ $call->status ?? '-' }}
                                </span>
                            </td>
                            <td class="fw-semibold">{{ $durationLabel }}</td>
                            <td>
                                @if (!empty($call->recording_url))
                                    <audio controls preload="none" style="max-width: 170px; height: 32px;">
                                        <source src="{{ $call->recording_url }}">
                                        Your browser does not support audio playback.
                                    </audio>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td>
                                @if (!empty($callbackNumber))
                                    <button type="button"
                                        class="btn btn-sm btn-outline-success integrated-call-btn"
                                        data-lead-id="{{ $call->lead_id }}"
                                        data-phone="{{ $callbackNumber }}"
                                        title="Call back via integration">
                                        <span class="material-icons" style="font-size:16px;">phone_callback</span>
                                    </button>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No calls found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">
                Showing {{ $callLogs->firstItem() ?? 0 }} to {{ $callLogs->lastItem() ?? 0 }} of {{ $callLogs->total() }} results
            </small>
            {{ $callLogs->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>

    <script>
        (function () {
            var activeBtn = null;

            // Initialize calling via GC on page load
            window.GC.initDevice();

            function resetButton(btn) {
                btn.disabled = false;
                btn.classList.remove('btn-warning', 'btn-danger', 'active-call');
                btn.classList.add('btn-outline-success');
                btn.innerHTML = '<span class="material-icons" style="font-size:16px;">phone_callback</span>';
            }

            // Handle call button click — delegate to GC
            document.addEventListener('click', async function (e) {
                var btn = e.target.closest('.integrated-call-btn');
                if (!btn) return;

                if (window.GC.isActive()) {
                    window.GC.endCall();
                    return;
                }

                activeBtn = btn;
                btn.disabled = true;
                btn.classList.remove('btn-outline-success');
                btn.classList.add('btn-warning');
                btn.innerHTML = '<span class="material-icons" style="font-size:16px;">ring_volume</span>';

                try {
                    await window.GC.startCall(btn.dataset.phone, btn.dataset.leadId || null);
                } catch (err) {
                    resetButton(btn);
                    activeBtn = null;
                }
            });

            // Update button when call is accepted
            document.addEventListener('gc:callAccepted', function () {
                if (!activeBtn) return;
                activeBtn.disabled = false;
                activeBtn.classList.remove('btn-warning');
                activeBtn.classList.add('btn-danger', 'active-call');
                activeBtn.innerHTML = '<span class="material-icons" style="font-size:16px;">call_end</span>';
            });

            // Reset button when call ends
            document.addEventListener('gc:callEnded', function () {
                if (activeBtn) {
                    resetButton(activeBtn);
                    activeBtn = null;
                }
            });
        })();
    </script>
@endsection
