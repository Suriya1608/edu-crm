@extends('layouts.app')

@section('page_title', 'Open Lead Pool')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">Open Lead Pool</h4>
            <p class="text-muted mb-0 small">Unclaimed leads available to pick up. First-come, first-served.</p>
        </div>
        <span class="badge bg-primary fs-6">{{ $leads->total() }} leads</span>
    </div>

    {{-- Search --}}
    <form method="GET" class="mb-3">
        <div class="input-group" style="max-width:360px;">
            <input type="text" name="search" class="form-control" placeholder="Search name, phone, code…"
                value="{{ request('search') }}">
            <button class="btn btn-outline-secondary" type="submit">
                <span class="material-icons" style="font-size:18px;vertical-align:-4px;">search</span>
            </button>
            @if(request('search'))
                <a href="{{ route('manager.leads.pool') }}" class="btn btn-outline-danger">
                    <span class="material-icons" style="font-size:18px;vertical-align:-4px;">close</span>
                </a>
            @endif
        </div>
    </form>

    @if($leads->isEmpty())
        <div class="chart-card text-center py-5">
            <span class="material-icons" style="font-size:48px;color:#cbd5e1;">inbox</span>
            <p class="text-muted mt-2 mb-0">No unclaimed leads in the pool right now.</p>
        </div>
    @else
        <div class="chart-card p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Course</th>
                            <th>Source</th>
                            <th>Age</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($leads as $lead)
                        <tr>
                            <td><span class="badge bg-light text-dark border">{{ $lead->lead_code }}</span></td>
                            <td class="fw-semibold">{{ $lead->name }}</td>
                            <td>{{ $lead->phone }}</td>
                            <td>{{ $lead->enrolledCourse->name ?? '—' }}</td>
                            <td><span class="text-muted small">{{ $lead->source ?? '—' }}</span></td>
                            <td>
                                <span class="text-muted small">
                                    {{ $lead->created_at->diffForHumans(null, true) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('manager.leads.claim', encrypt($lead->id)) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary"
                                        onclick="return confirm('Claim this lead?')">
                                        <span class="material-icons" style="font-size:15px;vertical-align:-3px;">pan_tool</span>
                                        Claim
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
            {{ $leads->links() }}
        </div>
    @endif
@endsection
