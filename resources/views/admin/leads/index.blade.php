@extends('layouts.app')

@section('page_title', 'Lead Management')

@section('header_actions')
    <a href="{{ route('admin.leads.import.form') }}" class="btn btn-sm btn-outline-secondary">Import Leads</a>
    <a href="{{ route('admin.leads.export', request()->only(['search','manager_id','telecaller_id','status','date_range','date_from','date_to','course_id','academic_year_id','quota','source','gender','state','city','district','followup','no_activity_days','sla','is_duplicate','is_active','aged_min','aged_max'])) }}"
        class="btn btn-sm btn-outline-success">Export Excel</a>
    <a href="{{ route('admin.leads.export', array_merge(request()->only(['search','manager_id','telecaller_id','status','date_range','date_from','date_to','course_id','academic_year_id','quota','source','gender','state','city','district','followup','no_activity_days','sla','is_duplicate','is_active','aged_min','aged_max']), ['format'=>'pdf'])) }}"
        class="btn btn-sm btn-outline-danger" target="_blank">Export PDF</a>
@endsection

@section('content')
    {{-- ── Scope tabs ───────────────────────────────────────────────────── --}}
    <div class="chart-card mb-3">
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.leads.all') }}"        class="btn btn-sm {{ $scope === 'all'        ? 'btn-primary'                : 'btn-outline-primary' }}">All Leads</a>
            <a href="{{ route('admin.leads.unassigned') }}" class="btn btn-sm {{ $scope === 'unassigned' ? 'btn-primary'                : 'btn-outline-primary' }}">Unassigned</a>
            <a href="{{ route('admin.leads.assigned') }}"   class="btn btn-sm {{ $scope === 'assigned'   ? 'btn-primary'                : 'btn-outline-primary' }}">Assigned</a>
            <a href="{{ route('admin.leads.converted') }}"  class="btn btn-sm {{ $scope === 'converted'  ? 'btn-success'                : 'btn-outline-success' }}">Converted</a>
            <a href="{{ route('admin.leads.lost') }}"       class="btn btn-sm {{ $scope === 'lost'       ? 'btn-danger'                 : 'btn-outline-danger'  }}">Lost</a>
            <a href="{{ route('admin.leads.duplicates') }}" class="btn btn-sm {{ $scope === 'duplicates' ? 'btn-warning text-dark'       : 'btn-outline-warning text-dark' }}">Duplicates</a>
        </div>
    </div>

    {{-- ── Filter panel ─────────────────────────────────────────────────── --}}
    @php
        $advKeys = ['manager_id','telecaller_id','status','course_id','academic_year_id','quota','source','gender','state','city','district','followup','no_activity_days','sla','is_duplicate','is_active','aged_min','aged_max'];
        $hasAdv  = collect($advKeys)->some(fn($k) => request()->filled($k));
        $activeCount = count($activeFilters ?? []);
    @endphp

    <div class="chart-card mb-3">
        <div class="d-flex align-items-start justify-content-between mb-3">
            <div>
                <div class="fw-bold" style="font-size:15px;color:#0f172a;">Filter Leads</div>
                <div class="text-muted" style="font-size:12px;">Narrow results across all {{ $leads->total() }} leads</div>
            </div>
            @if($activeCount > 0)
                <span class="badge rounded-pill" style="background:#6366f1;font-size:12px;padding:5px 12px;">
                    {{ $activeCount }} active filter{{ $activeCount > 1 ? 's' : '' }}
                </span>
            @endif
        </div>

        <form method="GET" id="filterForm">
            {{-- Basic row --}}
            <div class="row g-2 mb-2">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm"
                        value="{{ request('search') }}"
                        placeholder="Lead code, name, phone, email…">
                </div>
                <div class="col-md-2">
                    <select name="date_range" class="form-select form-select-sm" id="dateRangeSelect">
                        <option value="">Any Date</option>
                        <option value="today" {{ request('date_range')=='today' ? 'selected' : '' }}>Today</option>
                        <option value="7"     {{ request('date_range')=='7'     ? 'selected' : '' }}>Last 7 Days</option>
                        <option value="30"    {{ request('date_range')=='30'    ? 'selected' : '' }}>Last 30 Days</option>
                        <option value="custom"{{ request('date_range')=='custom'? 'selected' : '' }}>Custom Range</option>
                    </select>
                </div>
                <div class="col-md-2" id="dateFromWrap" style="{{ request('date_range')==='custom' ? '' : 'display:none' }}">
                    <input type="date" name="date_from" class="form-control form-control-sm"
                        value="{{ request('date_from') }}" title="From date">
                </div>
                <div class="col-md-2" id="dateToWrap" style="{{ request('date_range')==='custom' ? '' : 'display:none' }}">
                    <input type="date" name="date_to" class="form-control form-control-sm"
                        value="{{ request('date_to') }}" title="To date">
                </div>
            </div>

            {{-- Advanced toggle --}}
            <div class="mb-2">
                <button type="button"
                    class="btn btn-sm {{ $hasAdv ? 'btn-outline-primary' : 'btn-outline-secondary' }} d-inline-flex align-items-center gap-1"
                    style="font-size:12px;"
                    data-bs-toggle="collapse" data-bs-target="#advFilters"
                    aria-expanded="{{ $hasAdv ? 'true' : 'false' }}">
                    <span class="material-icons" style="font-size:14px;">tune</span>
                    Advanced Filters
                    @if($hasAdv)
                        <span style="background:#6366f1;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;margin-left:2px;">ON</span>
                    @endif
                </button>
            </div>

            {{-- Advanced panel --}}
            <div class="collapse {{ $hasAdv ? 'show' : '' }}" id="advFilters">
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;margin-bottom:8px;">

                    {{-- Row 1: People --}}
                    <div class="row g-2 mb-2">
                        <div class="col-md-3 col-6">
                            <label class="d-block mb-1" style="font-size:11px;color:#64748b;font-weight:600;">MANAGER</label>
                            <select name="manager_id" class="form-select form-select-sm">
                                <option value="">All Managers</option>
                                @foreach($managers as $m)
                                    <option value="{{ $m->id }}" {{ request('manager_id')==$m->id ? 'selected' : '' }}>{{ $m->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="d-block mb-1" style="font-size:11px;color:#64748b;font-weight:600;">TELECALLER</label>
                            <select name="telecaller_id" class="form-select form-select-sm">
                                <option value="">All Telecallers</option>
                                @foreach($telecallers as $t)
                                    <option value="{{ $t->id }}" {{ request('telecaller_id')==$t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="d-block mb-1" style="font-size:11px;color:#64748b;font-weight:600;">STATUS</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All Statuses</option>
                                @foreach(['new'=>'New','assigned'=>'Assigned','contacted'=>'Contacted','interested'=>'Interested','follow_up'=>'Follow-up','not_interested'=>'Not Interested','converted'=>'Converted'] as $val => $lbl)
                                    <option value="{{ $val }}" {{ request('status')===$val ? 'selected' : '' }}>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="d-block mb-1" style="font-size:11px;color:#64748b;font-weight:600;">FOLLOW-UP</label>
                            <select name="followup" class="form-select form-select-sm">
                                <option value="">Any</option>
                                <option value="today"     {{ request('followup')==='today'     ? 'selected' : '' }}>Due Today</option>
                                <option value="overdue"   {{ request('followup')==='overdue'   ? 'selected' : '' }}>Overdue</option>
                                <option value="this_week" {{ request('followup')==='this_week' ? 'selected' : '' }}>This Week</option>
                                <option value="none"      {{ request('followup')==='none'      ? 'selected' : '' }}>No Follow-up Set</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="d-block mb-1" style="font-size:11px;color:#64748b;font-weight:600;">NO ACTIVITY (DAYS)</label>
                            <input type="number" name="no_activity_days" class="form-control form-control-sm"
                                min="1" max="365" placeholder="e.g. 7" value="{{ request('no_activity_days') }}">
                        </div>
                    </div>

                    {{-- Row 2: Course / Year / Quota / Source / Gender --}}
                    <div class="row g-2 mb-2">
                        <div class="col-md-3 col-6">
                            <label class="d-block mb-1" style="font-size:11px;color:#64748b;font-weight:600;">COURSE</label>
                            <select name="course_id" class="form-select form-select-sm">
                                <option value="">All Courses</option>
                                @foreach($courses as $c)
                                    <option value="{{ $c->id }}" {{ request('course_id')==$c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="d-block mb-1" style="font-size:11px;color:#64748b;font-weight:600;">ACADEMIC YEAR</label>
                            <select name="academic_year_id" class="form-select form-select-sm">
                                <option value="">All Years</option>
                                @foreach($academicYears as $y)
                                    <option value="{{ $y->id }}" {{ request('academic_year_id')==$y->id ? 'selected' : '' }}>{{ $y->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="d-block mb-1" style="font-size:11px;color:#64748b;font-weight:600;">QUOTA</label>
                            <select name="quota" class="form-select form-select-sm">
                                <option value="">All Quotas</option>
                                <option value="management"  {{ request('quota')==='management'  ? 'selected' : '' }}>Management</option>
                                <option value="counselling" {{ request('quota')==='counselling' ? 'selected' : '' }}>Counselling</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="d-block mb-1" style="font-size:11px;color:#64748b;font-weight:600;">SOURCE</label>
                            <select name="source" class="form-select form-select-sm">
                                <option value="">All Sources</option>
                                @foreach($sources as $src)
                                    <option value="{{ $src }}" {{ request('source')===$src ? 'selected' : '' }}>{{ $src }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="d-block mb-1" style="font-size:11px;color:#64748b;font-weight:600;">GENDER</label>
                            <select name="gender" class="form-select form-select-sm">
                                <option value="">All Genders</option>
                                <option value="male"   {{ request('gender')==='male'   ? 'selected' : '' }}>Male</option>
                                <option value="female" {{ request('gender')==='female' ? 'selected' : '' }}>Female</option>
                                <option value="other"  {{ request('gender')==='other'  ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                    </div>

                    {{-- Row 3: Geography / SLA / Flags / Aged --}}
                    <div class="row g-2">
                        <div class="col-md-2 col-6">
                            <label class="d-block mb-1" style="font-size:11px;color:#64748b;font-weight:600;">STATE</label>
                            <input type="text" name="state" class="form-control form-control-sm"
                                placeholder="e.g. Tamil Nadu" value="{{ request('state') }}">
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="d-block mb-1" style="font-size:11px;color:#64748b;font-weight:600;">CITY</label>
                            <input type="text" name="city" class="form-control form-control-sm"
                                placeholder="e.g. Chennai" value="{{ request('city') }}">
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="d-block mb-1" style="font-size:11px;color:#64748b;font-weight:600;">DISTRICT</label>
                            <input type="text" name="district" class="form-control form-control-sm"
                                placeholder="e.g. Coimbatore" value="{{ request('district') }}">
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="d-block mb-1" style="font-size:11px;color:#64748b;font-weight:600;">SLA STATUS</label>
                            <select name="sla" class="form-select form-select-sm">
                                <option value="">Any</option>
                                <option value="escalated" {{ request('sla')==='escalated' ? 'selected' : '' }}>Escalated</option>
                                <option value="1"         {{ request('sla')==='1'         ? 'selected' : '' }}>Level 1+</option>
                                <option value="2"         {{ request('sla')==='2'         ? 'selected' : '' }}>Level 2+</option>
                            </select>
                        </div>
                        <div class="col-md-1 col-6">
                            <label class="d-block mb-1" style="font-size:11px;color:#64748b;font-weight:600;">DUPLICATE</label>
                            <select name="is_duplicate" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="1" {{ request('is_duplicate')==='1' ? 'selected' : '' }}>Yes</option>
                                <option value="0" {{ request('is_duplicate')==='0' ? 'selected' : '' }}>No</option>
                            </select>
                        </div>
                        <div class="col-md-1 col-6">
                            <label class="d-block mb-1" style="font-size:11px;color:#64748b;font-weight:600;">ACTIVE</label>
                            <select name="is_active" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="1" {{ request('is_active')==='1' ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ request('is_active')==='0' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-1 col-6">
                            <label class="d-block mb-1" style="font-size:11px;color:#64748b;font-weight:600;">MIN AGE (d)</label>
                            <input type="number" name="aged_min" class="form-control form-control-sm"
                                min="0" placeholder="7" value="{{ request('aged_min') }}">
                        </div>
                        <div class="col-md-1 col-6">
                            <label class="d-block mb-1" style="font-size:11px;color:#64748b;font-weight:600;">MAX AGE (d)</label>
                            <input type="number" name="aged_max" class="form-control form-control-sm"
                                min="0" placeholder="30" value="{{ request('aged_max') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 flex-wrap align-items-center mt-2">
                <button type="submit" class="btn btn-primary btn-sm px-4 d-flex align-items-center gap-1">
                    <span class="material-icons" style="font-size:15px;">filter_list</span>
                    Apply Filters
                </button>
                <a href="{{ route('admin.leads.' . $scope) }}" class="btn btn-outline-secondary btn-sm px-3">Reset</a>
                @if($activeCount > 0)
                    <small class="text-muted">{{ $leads->total() }} result{{ $leads->total() !== 1 ? 's' : '' }} match your filters</small>
                @endif
            </div>
        </form>
    </div>

    {{-- ── Bulk Assign ──────────────────────────────────────────────────── --}}
    <div class="chart-card mb-3">
        <div class="chart-header mb-2">
            <h3>Bulk Assign</h3>
            <p>Assign selected leads to manager and/or telecaller</p>
        </div>
        <form method="POST" action="{{ route('admin.leads.bulk-assign') }}" id="bulkAssignForm" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-5">
                <label class="form-label">Manager</label>
                <select class="form-select" name="manager_id">
                    <option value="">Keep unchanged</option>
                    @foreach ($managers as $manager)
                        <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Telecaller</label>
                <select class="form-select" name="telecaller_id">
                    <option value="">Keep unchanged</option>
                    @foreach ($telecallers as $telecaller)
                        <option value="{{ $telecaller->id }}">{{ $telecaller->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">Bulk Assign</button>
            </div>
        </form>
    </div>

    {{-- ── Leads table ──────────────────────────────────────────────────── --}}
    <div class="custom-table">
        <div class="table-header">
            <h3>{{ $title }}</h3>
            <span class="text-muted" style="font-size:12px;">{{ $leads->total() }} records</span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAllLeads"></th>
                        <th>S.No</th>
                        <th>Lead</th>
                        <th>Phone</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Manager</th>
                        <th>Telecaller</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leads as $index => $lead)
                        <tr>
                            <td><input type="checkbox" class="lead-checkbox" value="{{ $lead->id }}"></td>
                            <td>{{ ($leads->currentPage() - 1) * $leads->perPage() + $index + 1 }}</td>
                            <td>
                                <div class="fw-semibold d-flex align-items-center gap-1 flex-wrap">
                                    {{ $lead->name }}
                                    @if($scope === 'duplicates')
                                        @php $isPhoneDup = $duplicatePhones->contains($lead->phone); $isEmailDup = $duplicateEmails->contains($lead->email); @endphp
                                        @if($isPhoneDup && $isEmailDup)
                                            <span class="badge" style="background:#fff7ed;color:#ea580c;border:1px solid #fed7aa;font-size:10px;font-weight:600;padding:2px 6px;border-radius:5px;">DUP PHONE+EMAIL</span>
                                        @elseif($isPhoneDup)
                                            <span class="badge" style="background:#fff7ed;color:#ea580c;border:1px solid #fed7aa;font-size:10px;font-weight:600;padding:2px 6px;border-radius:5px;">DUP PHONE</span>
                                        @elseif($isEmailDup)
                                            <span class="badge" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;font-size:10px;font-weight:600;padding:2px 6px;border-radius:5px;">DUP EMAIL</span>
                                        @endif
                                    @elseif($lead->is_duplicate)
                                        <span class="badge" style="background:#fff7ed;color:#ea580c;border:1px solid #fed7aa;font-size:10px;font-weight:600;padding:2px 6px;border-radius:5px;">DUPLICATE</span>
                                    @endif
                                    @if($lead->sla_level >= 2)
                                        <span style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;font-size:10px;font-weight:700;padding:2px 7px;border-radius:6px;">SLA L2</span>
                                    @elseif($lead->sla_level >= 1)
                                        <span style="background:#fff7ed;color:#ea580c;border:1px solid #fed7aa;font-size:10px;font-weight:700;padding:2px 7px;border-radius:6px;">SLA L1</span>
                                    @elseif($lead->sla_escalated_at)
                                        <span style="background:#fefce8;color:#ca8a04;border:1px solid #fde68a;font-size:10px;font-weight:700;padding:2px 7px;border-radius:6px;">ESCALATED</span>
                                    @endif
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-wrap mt-1">
                                    <small class="text-muted">{{ $lead->lead_code }}</small>
                                    <x-aging-badge :days="$lead->days_aged" />
                                </div>
                            </td>
                            <td>{{ $lead->phone }}</td>
                            <td><small class="text-muted">{{ $lead->enrolledCourse?->name ?? '—' }}</small></td>
                            <td>
                                @php $stCls = str_replace('_', '-', $lead->status); @endphp
                                <span class="lead-status status-{{ $stCls }}">{{ ucfirst(str_replace('_', ' ', $lead->status)) }}</span>
                            </td>
                            <td>{{ $lead->assignedBy?->name ?? '—' }}</td>
                            <td>{{ $lead->assignedUser?->name ?? '—' }}</td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <a href="{{ route('admin.leads.show', encrypt($lead->id)) }}" class="btn btn-sm btn-outline-primary">View</a>
                                    <button class="btn btn-sm btn-outline-secondary assign-manager-btn"
                                        data-id="{{ encrypt($lead->id) }}" data-manager-id="{{ $lead->assigned_by ?? '' }}"
                                        data-bs-toggle="modal" data-bs-target="#assignManagerModal">Manager</button>
                                    <button class="btn btn-sm btn-outline-dark assign-telecaller-btn"
                                        data-id="{{ encrypt($lead->id) }}" data-telecaller-id="{{ $lead->assigned_to ?? '' }}"
                                        data-bs-toggle="modal" data-bs-target="#assignTelecallerModal">Telecaller</button>
                                    @if($scope === 'duplicates')
                                        <button class="btn btn-sm btn-outline-warning merge-btn"
                                            data-source="{{ $lead->id }}"
                                            data-bs-toggle="modal" data-bs-target="#mergeModal">Merge</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center py-4 text-muted">No leads found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">
                Showing {{ $leads->firstItem() ?? 0 }} to {{ $leads->lastItem() ?? 0 }} of {{ $leads->total() }} results
            </small>
            {{ $leads->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>

    {{-- ── Assign Manager modal ─────────────────────────────────────────── --}}
    <div class="modal fade" id="assignManagerModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" id="assignManagerForm">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Assign Lead to Manager</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <select class="form-select" name="manager_id" required>
                            <option value="">Select Manager</option>
                            @foreach ($managers as $manager)
                                <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Assign Telecaller modal ──────────────────────────────────────── --}}
    <div class="modal fade" id="assignTelecallerModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" id="assignTelecallerForm">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reassign Telecaller</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <select class="form-select" name="telecaller_id" required>
                            <option value="">Select Telecaller</option>
                            @foreach ($telecallers as $telecaller)
                                <option value="{{ $telecaller->id }}">{{ $telecaller->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Merge modal ──────────────────────────────────────────────────── --}}
    <div class="modal fade" id="mergeModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" id="mergeForm">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Merge Duplicate Lead</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-3">Select the <strong>target</strong> lead to keep. All activities, call logs, and follow-ups from the source lead will be moved to the target.</p>
                        <label class="form-label">Target Lead ID (to merge INTO)</label>
                        <input type="number" class="form-control" id="mergeTargetId" placeholder="Enter target Lead ID" required min="1">
                        <div class="form-text text-danger mt-1">The source lead will be marked as "merged" and hidden.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Confirm Merge</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function () {
        // Custom date range toggle
        const dateRangeSelect = document.getElementById('dateRangeSelect');
        const dateFromWrap    = document.getElementById('dateFromWrap');
        const dateToWrap      = document.getElementById('dateToWrap');
        dateRangeSelect?.addEventListener('change', function () {
            const show = this.value === 'custom';
            dateFromWrap.style.display = show ? '' : 'none';
            dateToWrap.style.display   = show ? '' : 'none';
        });

        // Bulk assign — inject hidden lead_id inputs
        const bulkAssignForm = document.getElementById('bulkAssignForm');
        const selectAll      = document.getElementById('selectAllLeads');
        const checkboxes     = () => Array.from(document.querySelectorAll('.lead-checkbox'));

        selectAll?.addEventListener('change', function () {
            checkboxes().forEach(cb => cb.checked = this.checked);
        });

        bulkAssignForm?.addEventListener('submit', function (e) {
            checkboxes().filter(cb => cb.checked).forEach(cb => {
                const input = document.createElement('input');
                input.type  = 'hidden';
                input.name  = 'lead_ids[]';
                input.value = cb.value;
                this.appendChild(input);
            });
        });

        // Assign Manager modal
        document.querySelectorAll('.assign-manager-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.getElementById('assignManagerForm').action =
                    '{{ url("admin/leads") }}/' + this.dataset.id + '/assign-manager';
                const select = document.querySelector('#assignManagerModal select[name="manager_id"]');
                Array.from(select.options).forEach(opt => { opt.disabled = false; opt.selected = false; opt.textContent = opt.textContent.replace(' (current)', ''); });
                const current = this.dataset.managerId;
                if (current) {
                    const opt = select.querySelector('option[value="' + current + '"]');
                    if (opt) { opt.selected = true; opt.disabled = true; opt.textContent += ' (current)'; }
                }
            });
        });
        document.getElementById('assignManagerModal')?.addEventListener('hidden.bs.modal', function () {
            Array.from(this.querySelectorAll('select[name="manager_id"] option')).forEach(o => { o.disabled = false; o.textContent = o.textContent.replace(' (current)', ''); });
        });

        // Assign Telecaller modal
        document.querySelectorAll('.assign-telecaller-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.getElementById('assignTelecallerForm').action =
                    '{{ url("admin/leads") }}/' + this.dataset.id + '/reassign-telecaller';
                const select = document.querySelector('#assignTelecallerModal select[name="telecaller_id"]');
                Array.from(select.options).forEach(opt => { opt.disabled = false; opt.selected = false; opt.textContent = opt.textContent.replace(' (current)', ''); });
                const current = this.dataset.telecallerId;
                if (current) {
                    const opt = select.querySelector('option[value="' + current + '"]');
                    if (opt) { opt.selected = true; opt.disabled = true; opt.textContent += ' (current)'; }
                }
            });
        });
        document.getElementById('assignTelecallerModal')?.addEventListener('hidden.bs.modal', function () {
            Array.from(this.querySelectorAll('select[name="telecaller_id"] option')).forEach(o => { o.disabled = false; o.textContent = o.textContent.replace(' (current)', ''); });
        });

        // Merge modal
        document.querySelectorAll('.merge-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const sourceId = this.dataset.source;
                document.getElementById('mergeTargetId').value = '';
                const form = document.getElementById('mergeForm');
                form.onsubmit = function (e) {
                    e.preventDefault();
                    const targetId = document.getElementById('mergeTargetId').value;
                    if (!targetId) return;
                    form.action = '{{ url("admin/leads") }}/' + sourceId + '/merge/' + targetId;
                    form.submit();
                };
            });
        });
    })();
    </script>
@endsection
