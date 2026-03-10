@extends('layouts.manager.app')

@section('page_title', 'Missed Follow-ups by Telecaller')

@section('content')
    <div class="chart-card mb-3">
        <div class="chart-header mb-0">
            <h3>Missed Follow-ups by Telecaller</h3>
            <p>Escalated view of missed follow-ups grouped by telecaller.</p>
        </div>
    </div>

    <div class="custom-table">
        <div class="table-header">
            <h3>Escalation Summary</h3>
            <span class="text-muted" style="font-size:12px;">{{ $rows->total() }} records</span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Telecaller</th>
                        <th>Missed Count</th>
                        <th>Oldest Pending</th>
                        <th>Latest Pending</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $index => $row)
                        <tr>
                            <td>{{ ($rows->currentPage() - 1) * $rows->perPage() + $index + 1 }}</td>
                            <td>{{ $row->telecaller_name }}</td>
                            <td><span class="badge bg-danger">{{ $row->missed_count }}</span></td>
                            <td>{{ \Carbon\Carbon::parse($row->oldest_pending)->format('d M Y') }}</td>
                            <td>{{ \Carbon\Carbon::parse($row->latest_pending)->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No missed follow-ups by telecaller.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">
            {{ $rows->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection

