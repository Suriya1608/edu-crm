@extends('layouts.manager.app')

@section('page_title', 'Conversion Report')

@section('content')
    @include('manager.reports.partials.toolbar', ['filters'=>$filters,'filterOptions'=>$filterOptions,'reportKey'=>'conversion','baseRoute'=>route('manager.reports.conversion')])

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="custom-table">
                <div class="table-header"><h3>Conversion by Status</h3></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Status</th><th>Count</th></tr></thead>
                        <tbody>
                            @forelse($statusRows as $r)
                                <tr><td>{{ ucfirst(str_replace('_',' ',$r->status)) }}</td><td>{{ $r->total }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-center py-4 text-muted">No records.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="custom-table">
                <div class="table-header"><h3>Conversion by Telecaller</h3></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Telecaller</th><th>Total Leads</th><th>Converted</th><th>Rate</th></tr></thead>
                        <tbody>
                            @forelse($teleRows as $r)
                                <tr><td>{{ $r['name'] }}</td><td>{{ $r['total'] }}</td><td>{{ $r['converted'] }}</td><td><span class="badge bg-success">{{ $r['rate'] }}%</span></td></tr>
                            @empty
                                <tr><td colspan="4" class="text-center py-4 text-muted">No records.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

