@extends('layouts.manager.app')

@section('page_title', 'Source Performance Report')

@section('content')
    @include('manager.reports.partials.toolbar', ['filters'=>$filters,'filterOptions'=>$filterOptions,'reportKey'=>'source-performance','baseRoute'=>route('manager.reports.source-performance')])

    <div class="custom-table">
        <div class="table-header"><h3>Landing Page / Ads Source Performance</h3></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Source</th><th>Total Leads</th><th>Converted</th><th>Conversion Rate</th></tr></thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr><td>{{ $r->source }}</td><td>{{ $r->total_leads }}</td><td>{{ $r->converted_leads }}</td><td><span class="badge bg-primary">{{ $r->conversion_rate }}%</span></td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-4 text-muted">No records.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

