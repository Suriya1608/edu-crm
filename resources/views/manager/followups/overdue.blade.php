@extends('layouts.manager.app')

@section('page_title', 'Overdue Follow-ups')

@section('content')
    <div class="chart-card mb-3">
        <div class="chart-header mb-0">
            <h3>Overdue Follow-ups</h3>
            <p>These follow-ups passed their scheduled date and need immediate attention.</p>
        </div>
    </div>

    <div class="custom-table">
        <div class="table-header">
            <h3>Overdue List</h3>
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
                        <th>Days Overdue</th>
                        <th>Escalated</th>
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
                            <td><span class="badge bg-danger">{{ $followup->next_followup?->diffInDays(now()) ?? 0 }}d</span></td>
                            <td>
                                @if ($followup->escalated_at)
                                    <span class="badge bg-success">Yes</span>
                                @else
                                    <span class="badge bg-secondary">No</span>
                                @endif
                            </td>
                            <td>
                                @if ($followup->lead_id)
                                    <a href="{{ route('manager.leads.show', encrypt($followup->lead_id)) }}" class="btn btn-sm btn-outline-primary">View Lead</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No overdue follow-ups.</td>
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

