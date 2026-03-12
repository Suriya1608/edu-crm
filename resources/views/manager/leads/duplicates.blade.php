@extends('layouts.manager.app')

@section('page_title', 'Duplicate Leads')

@section('content')
    <div class="chart-card mb-3">
        <div class="chart-header mb-3">
            <div>
                <h3>Duplicate Leads</h3>
                <p class="text-muted mb-0" style="font-size:13px;">Leads sharing the same mobile number or email address.</p>
            </div>
        </div>

        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                style="max-width:320px;" placeholder="Search Lead Code / Name / Phone / Email">
            <button class="btn btn-primary btn-sm px-3">Search</button>
            <a href="{{ route('manager.leads.duplicates') }}" class="btn btn-outline-secondary btn-sm px-3">Reset</a>
        </form>
    </div>

    <div class="custom-table">
        <div class="table-header">
            <h3>Duplicate Lead List</h3>
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
                        <th>Email</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Created</th>
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
                                    <span class="badge" style="background:#fff7ed; color:#ea580c; border:1px solid #fed7aa; font-size:10px; font-weight:600; padding:2px 6px; border-radius:5px;">DUPLICATE</span>
                                </div>
                                <x-aging-badge :days="$lead->days_aged" />
                            </td>
                            <td><span class="fw-semibold">{{ $lead->phone }}</span></td>
                            <td>{{ $lead->email ?: '-' }}</td>
                            <td><span class="badge bg-light text-dark">{{ $lead->source }}</span></td>

                            <td>
                                @php
                                    $statusColors = [
                                        'new' => 'bg-primary',
                                        'assigned' => 'bg-info',
                                        'contacted' => 'bg-secondary',
                                        'interested' => 'bg-success',
                                        'not_interested' => 'bg-danger',
                                        'converted' => 'bg-dark',
                                        'follow_up' => 'bg-warning text-dark',
                                    ];
                                    $badgeClass = $statusColors[$lead->status] ?? 'bg-secondary';
                                @endphp
                                <span class="badge {{ $badgeClass }}">
                                    {{ ucfirst(str_replace('_', ' ', $lead->status)) }}
                                </span>
                            </td>

                            <td>{{ $lead->assignedUser->name ?? '-' }}</td>
                            <td>{{ $lead->created_at->format('d M Y') }}</td>

                            <td>
                                <a href="{{ route('manager.leads.show', encrypt($lead->id)) }}"
                                    class="btn btn-sm btn-outline-primary">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <span class="material-icons d-block mb-2" style="font-size:40px;opacity:0.3;">content_copy</span>
                                No duplicate leads found
                            </td>
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
