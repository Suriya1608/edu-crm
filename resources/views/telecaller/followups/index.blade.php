@extends('layouts.app')

@section('page_title', $title)

@section('content')
    <div class="chart-card mb-3">
        <div class="chart-header mb-2">
            <h3>{{ $title }}</h3>
            <p>Manage followups with quick actions</p>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('telecaller.followups.today') }}"
                class="btn btn-sm {{ $scope === 'today' ? 'btn-primary' : 'btn-outline-primary' }}">
                Today
            </a>
            <a href="{{ route('telecaller.followups.overdue') }}"
                class="btn btn-sm {{ $scope === 'overdue' ? 'btn-danger' : 'btn-outline-danger' }}">
                Overdue
            </a>
            <a href="{{ route('telecaller.followups.upcoming') }}"
                class="btn btn-sm {{ $scope === 'upcoming' ? 'btn-warning text-dark' : 'btn-outline-warning text-dark' }}">
                Upcoming
            </a>
            <a href="{{ route('telecaller.followups.completed') }}"
                class="btn btn-sm {{ $scope === 'completed' ? 'btn-success' : 'btn-outline-success' }}">
                Completed
            </a>
        </div>
    </div>

    <div class="custom-table">
        <div class="table-header">
            <h3>Followup List</h3>
            <span class="text-muted" style="font-size:12px;">{{ $followups->total() }} records</span>
        </div>

        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Date & Time</th>
                        <th>Lead</th>
                        <th>Phone</th>
                        <th>Remarks</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($followups as $item)
                        @php
                            $isCompleted = !empty($item->completed_at);
                            $followupDatetime = $item->next_followup && $item->followup_time
                                ? \Carbon\Carbon::parse($item->next_followup->format('Y-m-d') . ' ' . $item->followup_time)
                                : optional($item->next_followup);
                            $isOverdue = !$isCompleted && (
                                optional($item->next_followup)->lt(today()) ||
                                ($item->followup_time && optional($item->next_followup)->isToday() && $followupDatetime->isPast())
                            );
                        @endphp
                        <tr>
                            <td>{{ ($followups->currentPage() - 1) * $followups->perPage() + $loop->iteration }}</td>
                            <td>
                                {{ optional($item->next_followup)->format('d M Y') }}
                                @if ($item->followup_time)
                                    <br><small class="text-muted">{{ \Carbon\Carbon::parse($item->followup_time)->format('h:i A') }}</small>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $item->lead->name ?? '-' }}</div>
                                <small class="text-muted">{{ $item->lead->lead_code ?? '-' }}</small>
                            </td>
                            <td>{{ $item->lead->phone ?? '-' }}</td>
                            <td>{{ $item->remarks ?: '-' }}</td>
                            <td>
                                @if ($isCompleted)
                                    <span class="badge bg-success">completed</span>
                                @elseif ($isOverdue)
                                    <span class="badge bg-danger">overdue</span>
                                @elseif (optional($item->next_followup)->isToday())
                                    <span class="badge bg-warning text-dark">
                                        {{ ($item->followup_time && \Carbon\Carbon::parse($item->followup_time)->isFuture()) ? 'upcoming' : 'today' }}
                                    </span>
                                @else
                                    <span class="badge bg-info text-dark">upcoming</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    @if (!empty($item->lead->phone))
                                        <button type="button"
                                            class="btn btn-sm btn-outline-success integrated-call-btn"
                                            data-lead-id="{{ $item->lead_id }}"
                                            data-phone="{{ $item->lead->phone }}"
                                            title="One-click Call via integration">
                                            <span class="material-icons" style="font-size:16px;">call</span>
                                        </button>
                                    @endif

                                    @if (!empty($item->lead_id))
                                        <a href="{{ route('telecaller.leads.show', encrypt($item->lead_id)) }}"
                                            class="btn btn-sm btn-outline-primary" title="Open Lead">
                                            <span class="material-icons" style="font-size:16px;">open_in_new</span>
                                        </a>
                                    @endif

                                    @if (!$isCompleted)
                                        <button class="btn btn-sm btn-outline-warning text-dark reschedule-btn"
                                            data-id="{{ $item->id }}"
                                            data-date="{{ optional($item->next_followup)->format('Y-m-d') }}"
                                            data-time="{{ $item->followup_time ? \Carbon\Carbon::parse($item->followup_time)->format('H:i') : '' }}"
                                            data-remarks="{{ $item->remarks }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#rescheduleModal"
                                            title="Reschedule">
                                            <span class="material-icons" style="font-size:16px;">event_repeat</span>
                                        </button>

                                        <form method="POST" action="{{ route('telecaller.followups.mark-complete', $item->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success"
                                                title="Mark as completed">
                                                <span class="material-icons" style="font-size:16px;">task_alt</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No followups found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">
                Showing {{ $followups->firstItem() ?? 0 }} to {{ $followups->lastItem() ?? 0 }} of {{ $followups->total() }} results
            </small>
            {{ $followups->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>

    <div class="modal fade" id="rescheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" id="rescheduleForm">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reschedule Followup</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @if ($errors->has('next_followup'))
                            <div class="alert alert-danger py-2 mb-3">{{ $errors->first('next_followup') }}</div>
                        @endif
                        <div class="row g-3 mb-3">
                            <div class="col-7">
                                <label class="form-label">Next Followup Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="next_followup" id="rescheduleDate" required>
                            </div>
                            <div class="col-5">
                                <label class="form-label">Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="followup_time" id="rescheduleTime" required>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="rescheduleRemarks" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary" type="submit">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            const form = document.getElementById('rescheduleForm');
            const dateInput = document.getElementById('rescheduleDate');
            const timeInput = document.getElementById('rescheduleTime');
            const remarksInput = document.getElementById('rescheduleRemarks');

            document.querySelectorAll('.reschedule-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const date = this.dataset.date || '';
                    const time = this.dataset.time || '';
                    const remarks = this.dataset.remarks || '';

                    form.action = "{{ url('telecaller/followups') }}/" + id + "/reschedule";
                    dateInput.value = date;
                    timeInput.value = time;
                    remarksInput.value = remarks;
                });
            });

            form.addEventListener('submit', function(e) {
                const dateVal = dateInput.value;
                const timeVal = timeInput.value;
                if (!dateVal || !timeVal) return; // let server validate

                const chosen = new Date(dateVal + 'T' + timeVal);
                if (chosen <= new Date()) {
                    e.preventDefault();
                    alert('The scheduled date & time cannot be in the past.');
                    timeInput.focus();
                }
            });
        })();
    </script>

    <script>
        (function () {
            var activeBtn = null;

            // Initialize Twilio via GC on page load
            GC.initDevice();

            function resetButton(btn) {
                btn.disabled = false;
                btn.classList.remove('btn-warning', 'btn-danger', 'active-call');
                btn.classList.add('btn-outline-success');
                btn.innerHTML = '<span class="material-icons" style="font-size:16px;">call</span>';
            }

            // Handle call button click — delegate to GC
            document.addEventListener('click', async function (e) {
                var btn = e.target.closest('.integrated-call-btn');
                if (!btn) return;

                if (GC.isActive()) {
                    GC.endCall();
                    return;
                }

                activeBtn = btn;
                btn.disabled = true;
                btn.classList.remove('btn-outline-success');
                btn.classList.add('btn-warning');
                btn.innerHTML = '<span class="material-icons" style="font-size:16px;">ring_volume</span>';

                try {
                    await GC.startCall(btn.dataset.phone, btn.dataset.leadId || null);
                } catch (err) {
                    resetButton(btn);
                    activeBtn = null;
                }
            });

            // Update button when call is accepted
            document.addEventListener('gc:callAccepted', function () {
                if (!activeBtn) return;
                activeBtn.disabled = false;
                activeBtn.classList.remove('btn-warning');
                activeBtn.classList.add('btn-danger', 'active-call');
                activeBtn.innerHTML = '<span class="material-icons" style="font-size:16px;">call_end</span>';
            });

            // Reset button when call ends
            document.addEventListener('gc:callEnded', function () {
                if (activeBtn) {
                    resetButton(activeBtn);
                    activeBtn = null;
                }
            });
        })();
    </script>
@endsection
