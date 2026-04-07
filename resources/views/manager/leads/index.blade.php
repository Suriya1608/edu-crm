@extends('layouts.manager.app')

@section('page_title', 'Lead Management')

@section('header_actions')
    <a href="{{ route('manager.leads.create') }}" class="btn btn-primary d-flex align-items-center gap-1">
        <span class="material-icons" style="font-size:16px;">add</span>
        Add Lead
    </a>
@endsection

@section('header_actions1')
    <div class="d-flex align-items-center gap-2 flex-wrap mt-2">
        {{-- View Toggle --}}
        <div class="btn-group btn-group-sm" role="group">
            <a href="{{ route('manager.leads') }}" class="btn btn-primary d-flex align-items-center gap-1" title="List View">
                <span class="material-icons" style="font-size:15px;">view_list</span>
                List
            </a>
            <a href="{{ route('manager.leads.pipeline') }}" class="btn btn-outline-primary d-flex align-items-center gap-1" title="Pipeline View">
                <span class="material-icons" style="font-size:15px;">view_kanban</span>
                Pipeline
            </a>
        </div>

        <a href="{{ route('manager.leads.import') }}" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
            <span class="material-icons" style="font-size:16px;">upload_file</span>
            Import Excel
        </a>

        <a href="{{ route('manager.leads.export', array_filter(request()->only(['search', 'telecaller', 'status', 'date_range']))) }}"
            class="btn btn-sm btn-outline-success d-flex align-items-center gap-1">
            <span class="material-icons" style="font-size:16px;">download</span>
            Export Excel
        </a>

        <a href="{{ route('manager.leads.export', array_merge(array_filter(request()->only(['search', 'telecaller', 'status', 'date_range'])), ['format' => 'pdf'])) }}"
            class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1" target="_blank">
            <span class="material-icons" style="font-size:16px;">picture_as_pdf</span>
            Export PDF
        </a>
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
                    <span class="material-icons">assignment_ind</span>
                </div>
                <div class="stat-label">Assigned Leads</div>
                <div class="stat-value">{{ $assignedLeads }}</div>
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
            <h3>Filter Leads</h3>
            <p>Refine by date, telecaller, status, and search terms</p>
        </div>

        <form method="GET">
            <div class="row g-3">
                <div class="col-md-2 col-6">
                    <select name="date_range" class="form-select">
                        <option value="">Date</option>
                        <option value="7" {{ request('date_range') == '7' ? 'selected' : '' }}>Last 7 Days</option>
                        <option value="30" {{ request('date_range') == '30' ? 'selected' : '' }}>Last 30 Days</option>
                        <option value="today" {{ request('date_range') == 'today' ? 'selected' : '' }}>Today</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <select name="telecaller" class="form-select">
                        <option value="">Telecaller</option>
                        @foreach ($telecallers as $tele)
                            <option value="{{ $tele->id }}" {{ request('telecaller') == $tele->id ? 'selected' : '' }}>
                                {{ $tele->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Status</option>
                        <option value="new" {{ request('status') == 'new' ? 'selected' : '' }}>New</option>
                        <option value="assigned" {{ request('status') == 'assigned' ? 'selected' : '' }}>Assigned</option>
                        <option value="contacted" {{ request('status') == 'contacted' ? 'selected' : '' }}>Contacted</option>
                        <option value="interested" {{ request('status') == 'interested' ? 'selected' : '' }}>Interested</option>
                        <option value="follow_up" {{ request('status') == 'follow_up' ? 'selected' : '' }}>Follow Up</option>
                        <option value="not_interested" {{ request('status') == 'not_interested' ? 'selected' : '' }}>Not Interested</option>
                        <option value="converted" {{ request('status') == 'converted' ? 'selected' : '' }}>Converted</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                        placeholder="Search Lead Code / Name / Phone / Email / Course / Source">
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary btn-sm px-3">Apply</button>
                <a href="{{ route('manager.leads') }}" class="btn btn-outline-secondary btn-sm px-3">Reset</a>
            </div>
        </form>
    </div>

    <div class="custom-table">
        <div class="table-header">
            <h3>Lead List</h3>
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
                        <th>Source</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Assigned To</th>
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
                                    @if($lead->is_duplicate)
                                        <span class="badge" style="background:#fff7ed; color:#ea580c; border:1px solid #fed7aa; font-size:10px; font-weight:600; padding:2px 6px; border-radius:5px;">DUPLICATE</span>
                                    @endif
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-wrap mt-1">
                                    <small class="text-muted">{{ $lead->email ?? '-' }}</small>
                                    <x-aging-badge :days="$lead->days_aged" />
                                </div>
                            </td>
                            <td><span class="fw-semibold">{{ $lead->phone }}</span></td>
                            <td><span class="badge bg-light text-dark">{{ $lead->source }}</span></td>
                            <td>{{ $lead->course ?: '-' }}</td>

                            <td>
                                @php $stCls = str_replace('_', '-', $lead->status); @endphp
                                <span class="lead-status status-{{ $stCls }}">{{ ucfirst(str_replace('_', ' ', $lead->status)) }}</span>
                            </td>

                            <td>{{ $lead->assignedUser->name ?? '-' }}</td>

                            <td>
                                @php
                                    $latestFollowup = $lead->followups->sortByDesc('next_followup')->first();
                                @endphp
                                {{ $latestFollowup?->next_followup ? \Carbon\Carbon::parse($latestFollowup->next_followup)->format('d M Y') : '-' }}
                            </td>

                            <td>
                                <a href="{{ route('manager.leads.show', encrypt($lead->id)) }}" class="btn btn-sm btn-outline-primary">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">No Leads Found</td>
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
@endsection
