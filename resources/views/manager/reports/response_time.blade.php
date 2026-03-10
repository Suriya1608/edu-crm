@extends('layouts.manager.app')

@section('page_title', 'Lead Response Time Report')

@section('content')
    @include('manager.reports.partials.toolbar', ['filters'=>$filters,'filterOptions'=>$filterOptions,'reportKey'=>'response-time','baseRoute'=>route('manager.reports.response-time')])

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon amber"><span class="material-icons">timer</span></div>
                <div class="stat-label">Average Response Time</div>
                <div class="stat-value">{{ number_format($avgResponse,2) }} min</div>
            </div>
        </div>
    </div>

    <div class="custom-table">
        <div class="table-header"><h3>Lead Response Detail</h3></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Lead Code</th><th>Lead</th><th>Telecaller</th><th>Created At</th><th>First Response</th><th>Response Minutes</th></tr></thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr><td>{{ $r['lead_code'] }}</td><td>{{ $r['lead_name'] }}</td><td>{{ $r['telecaller'] }}</td><td>{{ $r['created_at'] }}</td><td>{{ $r['first_response_at'] ?? '-' }}</td><td>{{ $r['response_minutes'] ?? '-' }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-4 text-muted">No records.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

