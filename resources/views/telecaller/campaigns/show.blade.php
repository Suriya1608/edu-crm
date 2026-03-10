@extends('layouts.app')

@section('page_title', $campaign->name)

@section('content')
    <div class="mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <a href="{{ route('telecaller.campaigns.index') }}" class="btn btn-sm btn-light">
            <span class="material-icons me-1" style="font-size:16px;">arrow_back</span>My Campaigns
        </a>
        <h5 class="mb-0 fw-semibold">{{ $campaign->name }}</h5>
    </div>

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">people</span></div>
                <div class="stat-label">My Contacts</div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon amber"><span class="material-icons">hourglass_empty</span></div>
                <div class="stat-label">Pending</div>
                <div class="stat-value">{{ number_format($stats['pending']) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon green"><span class="material-icons">phone_in_talk</span></div>
                <div class="stat-label">Contacted</div>
                <div class="stat-value">{{ number_format($stats['called']) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card highlight-success">
                <div class="stat-icon green"><span class="material-icons">check_circle</span></div>
                <div class="stat-label">Converted</div>
                <div class="stat-value">{{ number_format($stats['converted']) }}</div>
            </div>
        </div>
    </div>

    <div class="chart-card">
        <div class="chart-header mb-3">
            <h3>Contact List</h3>
        </div>

        {{-- Filters --}}
        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-md-5">
                <input type="text" name="search" class="form-control form-control-sm"
                    placeholder="Search name, phone..." value="{{ request('search') }}">
            </div>
            <div class="col-6 col-md-4">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    @foreach (['pending','called','interested','not_interested','no_answer','callback','converted'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $s)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 d-flex gap-1">
                <button class="btn btn-sm btn-primary flex-grow-1">Filter</button>
                <a href="{{ route('telecaller.campaigns.show', encrypt($campaign->id)) }}" class="btn btn-sm btn-light">Clear</a>
            </div>
        </form>

        @if ($contacts->isEmpty())
            <div class="text-center py-5">
                <span class="material-icons" style="font-size:40px; color:#cbd5e1;">people</span>
                <p class="text-muted mt-2">No contacts match your filters.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Mobile</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Follow-up</th>
                            <th>Calls</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($contacts as $contact)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $contact->name }}</div>
                                    @if ($contact->city)
                                        <div class="text-muted small">{{ $contact->city }}</div>
                                    @endif
                                </td>
                                <td>
                                    <a href="tel:{{ $contact->phone }}" class="text-decoration-none">
                                        {{ $contact->phone }}
                                    </a>
                                </td>
                                <td class="text-muted small">{{ $contact->course ?: '—' }}</td>
                                <td>
                                    <span class="badge bg-{{ App\Models\CampaignContact::statusColor($contact->status) }}">
                                        {{ App\Models\CampaignContact::statusLabel($contact->status) }}
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    {{ $contact->next_followup ? $contact->next_followup->format('d M Y') : '—' }}
                                </td>
                                <td class="text-muted small">{{ $contact->call_count }}</td>
                                <td>
                                    <a href="{{ route('telecaller.campaigns.contact', [encrypt($campaign->id), encrypt($contact->id)]) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        <span class="material-icons" style="font-size:15px;">open_in_new</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $contacts->links() }}
            </div>
        @endif
    </div>
@endsection
