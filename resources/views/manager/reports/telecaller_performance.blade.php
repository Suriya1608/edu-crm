@extends('layouts.manager.app')

@section('page_title', 'Telecaller Performance Report')

@section('content')
    @include('manager.reports.partials.toolbar', ['filters'=>$filters,'filterOptions'=>$filterOptions,'reportKey'=>'telecaller-performance','baseRoute'=>route('manager.reports.telecaller-performance')])
    <div class="custom-table">
        <div class="table-header"><h3>Telecaller Performance</h3></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Telecaller</th><th>Assigned</th><th>Calls</th><th>Avg Talk Time</th><th>Follow-ups</th><th>Conversions</th><th>Efficiency</th></tr></thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr><td>{{ $row['name'] }}</td><td>{{ $row['assigned'] }}</td><td>{{ $row['calls'] }}</td><td>{{ $row['avg_talk_time'] }}</td><td>{{ $row['followups'] }}</td><td>{{ $row['converted'] }}</td><td><span class="badge bg-primary">{{ $row['efficiency_score'] }}</span></td></tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-4 text-muted">No records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

