@extends('layouts.manager.app')

@section('page_title', $campaign->name)

@section('content')
    {{-- Sub-nav --}}
    <div class="lead-profile-nav mb-3">
        <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('manager.campaigns.index') }}" class="btn btn-sm btn-light">
                    <span class="material-icons me-1" style="font-size:16px;">arrow_back</span>Back to Campaigns
                </a>
                <div>
                    <h2 class="page-header-title mb-0">{{ $campaign->name }}</h2>
                    <p class="page-header-subtitle mb-0">
                        @php
                            $colors = ['active'=>'success','paused'=>'warning','completed'=>'secondary','draft'=>'secondary'];
                        @endphp
                        <span class="badge bg-{{ $colors[$campaign->status] ?? 'secondary' }} me-2">{{ ucfirst($campaign->status) }}</span>
                        Created {{ $campaign->created_at->format('d M Y') }}
                    </p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('manager.campaigns.import', encrypt($campaign->id)) }}" class="btn btn-sm btn-outline-primary">
                    <span class="material-icons me-1" style="font-size:15px;">upload_file</span>Upload More
                </a>

                {{-- Status change --}}
                <form action="{{ route('manager.campaigns.status', encrypt($campaign->id)) }}" method="POST" class="d-inline">
                    @csrf @method('PATCH')
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach (['active','paused','completed','draft'] as $s)
                            <option value="{{ $s }}" {{ $campaign->status === $s ? 'selected' : '' }}>
                                {{ ucfirst($s) }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">people</span></div>
                <div class="stat-label">Total</div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card">
                <div class="stat-icon amber"><span class="material-icons">hourglass_empty</span></div>
                <div class="stat-label">Pending</div>
                <div class="stat-value">{{ number_format($stats['pending']) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue"><span class="material-icons">phone_in_talk</span></div>
                <div class="stat-label">Contacted</div>
                <div class="stat-value">{{ number_format($stats['called']) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card">
                <div class="stat-icon green"><span class="material-icons">thumb_up</span></div>
                <div class="stat-label">Interested</div>
                <div class="stat-value">{{ number_format($stats['interested']) }}</div>
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

    <div class="row g-4">
        {{-- Contacts Table --}}
        <div class="col-lg-8">
            <div class="chart-card">
                <div class="chart-header mb-3">
                    <h3>Contacts</h3>
                </div>

                {{-- Filters --}}
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-12 col-md-4">
                        <input type="text" name="search" class="form-control form-control-sm"
                            placeholder="Search name, phone, email..." value="{{ request('search') }}">
                    </div>
                    <div class="col-6 col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            @foreach (['pending','called','interested','not_interested','no_answer','callback','converted'] as $s)
                                <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_', ' ', $s)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <select name="telecaller" class="form-select form-select-sm">
                            <option value="">All Telecallers</option>
                            @foreach ($telecallers as $tc)
                                <option value="{{ $tc->id }}" {{ request('telecaller') == $tc->id ? 'selected' : '' }}>
                                    {{ $tc->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-1">
                        <button class="btn btn-sm btn-primary flex-grow-1">Filter</button>
                        <a href="{{ route('manager.campaigns.show', encrypt($campaign->id)) }}" class="btn btn-sm btn-light">Clear</a>
                    </div>
                </form>

                @if ($contacts->isEmpty())
                    <div class="text-center py-4">
                        <span class="material-icons" style="font-size:40px; color:#cbd5e1;">people</span>
                        <p class="text-muted mt-2">No contacts found.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Mobile</th>
                                    <th>Email</th>
                                    <th>Course</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Follow-up</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($contacts as $contact)
                                    <tr>
                                        <td class="fw-semibold">{{ $contact->name }}</td>
                                        <td>{{ $contact->phone }}</td>
                                        <td class="text-muted small">{{ $contact->email ?: '—' }}</td>
                                        <td class="text-muted small">{{ $contact->course ?: '—' }}</td>
                                        <td>
                                            <span class="badge bg-{{ App\Models\CampaignContact::statusColor($contact->status) }}">
                                                {{ App\Models\CampaignContact::statusLabel($contact->status) }}
                                            </span>
                                        </td>
                                        <td class="text-muted small">
                                            {{ $contact->assignedUser?->name ?? '—' }}
                                        </td>
                                        <td class="text-muted small">
                                            {{ $contact->next_followup ? $contact->next_followup->format('d M') : '—' }}
                                            @if ($contact->followup_time)
                                                <span class="text-primary" style="font-size:11px;">
                                                    {{ date('h:i A', strtotime($contact->followup_time)) }}
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('manager.campaigns.contact', [encrypt($campaign->id), encrypt($contact->id)]) }}"
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
        </div>

        {{-- Distribute Panel --}}
        <div class="col-lg-4">
            <div class="chart-card mb-4">
                <div class="chart-header mb-3">
                    <h3>Distribute Contacts</h3>
                </div>
                @php
                    $unassignedCount = $campaign->contacts()->whereNull('assigned_to')->count();
                @endphp
                <p class="text-muted small mb-3">
                    <strong>{{ number_format($unassignedCount) }}</strong> unassigned contact(s) ready to distribute.
                </p>

                @if ($unassignedCount > 0)
                    <form action="{{ route('manager.campaigns.distribute', encrypt($campaign->id)) }}" method="POST">
                        @csrf
                        <label class="form-label fw-semibold small">Select Telecallers</label>
                        @foreach ($telecallers as $tc)
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" name="telecaller_ids[]"
                                    value="{{ $tc->id }}" id="tc_{{ $tc->id }}">
                                <label class="form-check-label small" for="tc_{{ $tc->id }}">
                                    {{ $tc->name }}
                                    @if ($tc->is_online)
                                        <span class="badge bg-success" style="font-size:10px;">Online</span>
                                    @endif
                                </label>
                            </div>
                        @endforeach

                        @if ($telecallers->isEmpty())
                            <p class="text-muted small">No telecallers found.</p>
                        @else
                            <button type="submit" class="btn btn-primary btn-sm mt-3 w-100"
                                onclick="return confirm('Distribute {{ $unassignedCount }} contacts among selected telecallers?')">
                                <span class="material-icons me-1" style="font-size:15px;">shuffle</span>
                                Auto-Distribute
                            </button>
                        @endif
                    </form>
                @else
                    <p class="text-success small">
                        <span class="material-icons align-middle" style="font-size:16px;">check_circle</span>
                        All contacts have been assigned.
                    </p>
                @endif
            </div>

            {{-- Assignment Summary --}}
            <div class="chart-card">
                <div class="chart-header mb-3">
                    <h3>Assignment Summary</h3>
                </div>
                @php
                    $summary = $campaign->contacts()
                        ->selectRaw('assigned_to, count(*) as cnt')
                        ->with('assignedUser:id,name')
                        ->groupBy('assigned_to')
                        ->get();
                @endphp
                @foreach ($summary as $row)
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="small">{{ $row->assignedUser?->name ?? 'Unassigned' }}</span>
                        <span class="badge bg-light text-dark border">{{ $row->cnt }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
