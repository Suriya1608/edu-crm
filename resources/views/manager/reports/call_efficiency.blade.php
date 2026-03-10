@extends('layouts.manager.app')

@section('page_title', 'Call Efficiency Report')

@section('content')
    @include('manager.reports.partials.toolbar', ['filters'=>$filters,'filterOptions'=>$filterOptions,'reportKey'=>'call-efficiency','baseRoute'=>route('manager.reports.call-efficiency')])

    <div class="custom-table">
        <div class="table-header"><h3>Call Efficiency by Telecaller</h3></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Telecaller</th><th>Total Calls</th><th>Completed</th><th>Missed</th><th>Avg Duration (sec)</th><th>Completion Rate</th></tr></thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr><td>{{ $r->telecaller_name }}</td><td>{{ $r->total_calls }}</td><td>{{ $r->completed_calls }}</td><td>{{ $r->missed_calls }}</td><td>{{ round($r->avg_duration,2) }}</td><td><span class="badge bg-success">{{ $r->completion_rate }}%</span></td></tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-4 text-muted">No records.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

