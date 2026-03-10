@extends('layouts.manager.app')

@section('page_title', 'Daily / Weekly / Monthly Report')

@section('content')
    @include('manager.reports.partials.toolbar', ['filters'=>$filters,'filterOptions'=>$filterOptions,'reportKey'=>'period','baseRoute'=>route('manager.reports.period')])

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="custom-table">
                <div class="table-header"><h3>Daily</h3></div>
                <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Date</th><th>Total</th><th>Converted</th></tr></thead><tbody>@forelse($daily as $r)<tr><td>{{ $r->period_date }}</td><td>{{ $r->total }}</td><td>{{ $r->converted }}</td></tr>@empty<tr><td colspan="3" class="text-center py-3 text-muted">No data</td></tr>@endforelse</tbody></table></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="custom-table">
                <div class="table-header"><h3>Weekly</h3></div>
                <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Week</th><th>Total</th><th>Converted</th></tr></thead><tbody>@forelse($weekly as $r)<tr><td>{{ $r->period_week }}</td><td>{{ $r->total }}</td><td>{{ $r->converted }}</td></tr>@empty<tr><td colspan="3" class="text-center py-3 text-muted">No data</td></tr>@endforelse</tbody></table></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="custom-table">
                <div class="table-header"><h3>Monthly</h3></div>
                <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Month</th><th>Total</th><th>Converted</th></tr></thead><tbody>@forelse($monthly as $r)<tr><td>{{ $r->period_month }}</td><td>{{ $r->total }}</td><td>{{ $r->converted }}</td></tr>@empty<tr><td colspan="3" class="text-center py-3 text-muted">No data</td></tr>@endforelse</tbody></table></div>
            </div>
        </div>
    </div>
@endsection

