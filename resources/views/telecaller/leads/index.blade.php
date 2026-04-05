@extends('layouts.app')

@section('page_title', 'My Leads')

@section('header_actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        {{-- View Toggle --}}
        <div class="btn-group btn-group-sm" role="group">
            <a href="{{ route('telecaller.leads') }}" class="btn btn-primary d-flex align-items-center gap-1" title="List View">
                <span class="material-icons" style="font-size:15px;">view_list</span>
                List
            </a>
            <a href="{{ route('telecaller.leads.pipeline') }}" class="btn btn-outline-primary d-flex align-items-center gap-1" title="Pipeline View">
                <span class="material-icons" style="font-size:15px;">view_kanban</span>
                Pipeline
            </a>
        </div>

        <span class="badge rounded-pill text-bg-light border px-3 py-2 d-flex align-items-center gap-1">
            <span class="material-icons" style="font-size: 15px;">call</span>
            <span id="realtimeCallStatus">{{ $activeCallCount > 0 ? 'On Call' : 'Idle' }}</span>
        </span>
        <span class="badge rounded-pill {{ $activeCallCount > 0 ? 'text-bg-danger' : 'text-bg-success' }} px-3 py-2"
            id="activeCallBadge">
            Active Calls: <span id="activeCallCount">{{ $activeCallCount }}</span>
        </span>
    </div>
@endsection

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <span class="material-icons">groups</span>
                </div>
                <div class="stat-label">Total Leads</div>
                <div class="stat-value">{{ $totalLeads }}</div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <span class="material-icons">fiber_new</span>
                </div>
                <div class="stat-label">New Leads</div>
                <div class="stat-value">{{ $newLeads }}</div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon amber">
                    <span class="material-icons">thumb_up</span>
                </div>
                <div class="stat-label">Interested</div>
                <div class="stat-value">{{ $interestedLeads }}</div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card highlight-success">
                <div class="stat-icon red">
                    <span class="material-icons">event</span>
                </div>
                <div class="stat-label">Follow-up Today</div>
                <div class="stat-value">{{ $followupToday }}</div>
            </div>
        </div>
    </div>

    <div class="chart-card mb-3">
        <div class="chart-header mb-3">
            <h3>Filter My Leads</h3>
            <p>Refine by date, status, and lead details</p>
        </div>

        <form method="GET">
            <div class="row g-3">
                <div class="col-md-3">
                    <select name="date_range" class="form-select">
                        <option value="">Date</option>
                        <option value="7" {{ request('date_range') == '7' ? 'selected' : '' }}>Last 7 Days</option>
                        <option value="30" {{ request('date_range') == '30' ? 'selected' : '' }}>Last 30 Days</option>
                        <option value="today" {{ request('date_range') == 'today' ? 'selected' : '' }}>Today</option>
                    </select>
                </div>

                <div class="col-md-5">
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                        placeholder="Search Lead Code / Name / Phone">
                </div>

                <div class="col-md-4">
                    <select name="status" class="form-select">
                        <option value="">Status</option>
                        <option value="new" {{ request('status') == 'new' ? 'selected' : '' }}>New</option>
                        <option value="contacted" {{ request('status') == 'contacted' ? 'selected' : '' }}>Contacted</option>
                        <option value="interested" {{ request('status') == 'interested' ? 'selected' : '' }}>Interested</option>
                        <option value="follow_up" {{ request('status') == 'follow_up' ? 'selected' : '' }}>Follow-up</option>
                        <option value="not_interested" {{ request('status') == 'not_interested' ? 'selected' : '' }}>Not Interested</option>
                    </select>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary btn-sm px-3">Apply</button>
                <a href="{{ route('telecaller.leads') }}" class="btn btn-outline-secondary btn-sm px-3">Reset</a>
            </div>
        </form>
    </div>

    <div class="custom-table">
        <div class="table-header">
            <h3>My Lead List</h3>
            <span class="text-muted" style="font-size:12px;">{{ $leads->total() }} records</span>
        </div>

        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Lead Code</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Next Follow-up</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leads as $lead)
                        <tr>
                            <td>{{ ($leads->currentPage() - 1) * $leads->perPage() + $loop->iteration }}</td>
                            <td>{{ $lead->lead_code }}</td>
                            <td>
                                <div class="fw-semibold d-flex align-items-center gap-1 flex-wrap">
                                    {{ $lead->name }}
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-wrap mt-1">
                                    <small class="text-muted">{{ $lead->email ?? '-' }}</small>
                                    <x-aging-badge :days="$lead->days_aged" />
                                </div>
                            </td>
                            <td><span class="fw-semibold">{{ $lead->phone }}</span></td>
                            <td>{{ $lead->course ?: '-' }}</td>
                            <td>
                                @php $stCls = str_replace('_', '-', $lead->status); @endphp
                                <span class="lead-status status-{{ $stCls }}">{{ ucfirst(str_replace('_', ' ', $lead->status)) }}</span>
                            </td>
                            <td>
                                @php
                                    $latestFollowup = $lead->followups->sortByDesc('next_followup')->first();
                                @endphp
                                {{ $latestFollowup?->next_followup ? \Carbon\Carbon::parse($latestFollowup->next_followup)->format('d M Y') : '-' }}
                            </td>
                            <td>
                                <a href="{{ route('telecaller.leads.show', encrypt($lead->id)) }}"
                                    class="btn btn-sm btn-outline-primary">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No Leads Found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">
                Showing {{ $leads->firstItem() ?? 0 }} to {{ $leads->lastItem() ?? 0 }} of {{ $leads->total() }} results
            </small>
            {{ $leads->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>

    <script>
        (function() {
            const snapshotUrl = @json(route('telecaller.panel.snapshot'));
            const realtimeCallStatus = document.getElementById('realtimeCallStatus');
            const activeCallBadge = document.getElementById('activeCallBadge');
            const activeCallCount = document.getElementById('activeCallCount');

            function renderSnapshot(data) {
                if (!data || !data.ok) return;
                const calls = Number(data.active_call_count || 0);
                activeCallCount.textContent = calls;
                realtimeCallStatus.textContent = data.call_status || (calls > 0 ? 'On Call' : 'Idle');
                activeCallBadge.classList.remove('text-bg-danger', 'text-bg-success');
                activeCallBadge.classList.add(calls > 0 ? 'text-bg-danger' : 'text-bg-success');
            }

            async function fetchSnapshot() {
                try {
                    const res = await fetch(snapshotUrl, { headers: { 'Accept': 'application/json' } });
                    renderSnapshot(await res.json());
                } catch (e) {}
            }

            fetchSnapshot();
            setInterval(fetchSnapshot, 45000);
        })();
    </script>
@endsection
