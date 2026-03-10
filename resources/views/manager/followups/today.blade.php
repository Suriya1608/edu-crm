@extends('layouts.manager.app')

@section('page_title', 'Today Follow-ups')

@section('content')
    <div class="chart-card mb-3">
        <div class="chart-header mb-0">
            <h3>Today Follow-ups</h3>
            <p>All follow-ups scheduled for {{ now()->format('d M Y') }}</p>
        </div>
    </div>

    <div class="custom-table">
        <div class="table-header">
            <h3>Follow-up List</h3>
            <span class="text-muted" style="font-size:12px;">{{ $followups->total() }} records</span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Lead</th>
                        <th>Telecaller</th>
                        <th>Follow-up Date</th>
                        <th>Remarks</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($followups as $index => $followup)
                        <tr>
                            <td>{{ ($followups->currentPage() - 1) * $followups->perPage() + $index + 1 }}</td>
                            <td>{{ $followup->lead?->lead_code ?? '-' }} - {{ $followup->lead?->name ?? 'N/A' }}</td>
                            <td>{{ $followup->lead?->assignedUser?->name ?? $followup->user?->name ?? 'Unassigned' }}</td>
                            <td>{{ $followup->next_followup?->format('d M Y') }}</td>
                            <td>{{ $followup->remarks }}</td>
                            <td>
                                @if ($followup->lead_id)
                                    <a href="{{ route('manager.leads.show', encrypt($followup->lead_id)) }}" class="btn btn-sm btn-outline-primary">View Lead</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No follow-ups for today.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">
            {{ $followups->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection

