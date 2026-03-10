@extends('layouts.app')

@section('page_title', 'Campaign Contacts')

@section('content')
    {{-- Page Header --}}
    <div class="lead-profile-nav mb-3">
        <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <div>
                    <h2 class="page-header-title mb-0">Campaign Contacts</h2>
                    <p class="page-header-subtitle mb-0">All contacts across every campaign</p>
                </div>
            </div>
            <a href="{{ route('admin.campaigns.performance') }}" class="btn btn-sm btn-outline-primary">
                <span class="material-icons me-1" style="font-size:15px;">insights</span>Performance
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="chart-card mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold mb-1">Campaign</label>
                <select name="campaign" class="form-select form-select-sm">
                    <option value="">All Campaigns</option>
                    @foreach ($campaigns as $c)
                        <option value="{{ $c->id }}" {{ request('campaign') == $c->id ? 'selected' : '' }}>
                            {{ $c->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small fw-semibold mb-1">Telecaller</label>
                <select name="telecaller" class="form-select form-select-sm">
                    <option value="">All Telecallers</option>
                    @foreach ($telecallers as $tc)
                        <option value="{{ $tc->id }}" {{ request('telecaller') == $tc->id ? 'selected' : '' }}>
                            {{ $tc->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small fw-semibold mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    @foreach (['pending','called','interested','not_interested','no_answer','callback','converted'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $s)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold mb-1">Search</label>
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Name or phone…" value="{{ request('search') }}">
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm flex-grow-1">
                    <span class="material-icons me-1" style="font-size:15px;">filter_list</span>Filter
                </button>
                <a href="{{ route('admin.campaigns.contacts') }}" class="btn btn-light btn-sm">Clear</a>
            </div>
        </form>
    </div>

    {{-- Contact Table --}}
    <div class="chart-card">
        <div class="chart-header mb-3">
            <h3>Contacts <span class="badge bg-secondary ms-1" style="font-size:13px;">{{ $contacts->total() }}</span></h3>
        </div>

        @if ($contacts->isEmpty())
            <div class="text-center py-5">
                <span class="material-icons" style="font-size:48px;color:#cbd5e1;">people</span>
                <p class="text-muted mt-2">No contacts found matching your filters.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Campaign</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Calls</th>
                            <th>Next Follow-up</th>
                            <th>Added</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($contacts as $contact)
                            @php
                                $statusColors = [
                                    'pending'       => 'secondary',
                                    'called'        => 'info',
                                    'interested'    => 'success',
                                    'not_interested'=> 'danger',
                                    'no_answer'     => 'warning',
                                    'callback'      => 'primary',
                                    'converted'     => 'success',
                                ];
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $contact->name }}</div>
                                    @if ($contact->course)
                                        <div class="text-muted small">{{ $contact->course }}</div>
                                    @endif
                                </td>
                                <td class="font-monospace small">{{ $contact->phone }}</td>
                                <td class="font-monospace small">{{ $contact->email }}</td>
                                <td>
                                    @if ($contact->campaign)
                                        <span class="badge bg-light text-dark border" style="font-size:12px;">
                                            {{ $contact->campaign->name }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $statusColors[$contact->status] ?? 'secondary' }}" style="font-size:11px;">
                                        {{ ucfirst(str_replace('_', ' ', $contact->status)) }}
                                    </span>
                                </td>
                                <td class="small">
                                    {{ $contact->assignedUser?->name ?? '<span class="text-muted">Unassigned</span>' }}
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border">{{ $contact->call_count }}</span>
                                </td>
                                <td class="small text-muted">
                                    {{ $contact->next_followup ? $contact->next_followup->format('d M Y') : '—' }}
                                </td>
                                <td class="small text-muted">{{ $contact->created_at->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($contacts->hasPages())
                <div class="mt-3">{{ $contacts->links() }}</div>
            @endif
        @endif
    </div>
@endsection
