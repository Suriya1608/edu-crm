@extends('layouts.app')

@section('page_title', 'Automation - Lead Assignment Rules')

@section('content')
    {{-- Stat cards --}}
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--grad-primary)"><span class="material-icons">alt_route</span></div>
                <div class="stat-label">Active Mode</div>
                <div class="stat-value" style="font-size:1rem;">
                    @if($values['mode'] === 'round_robin') Round Robin
                    @elseif($values['mode'] === 'course_based') Course Based
                    @else Open Pool
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--grad-success)"><span class="material-icons">timer</span></div>
                <div class="stat-label">Auto-Assign Telecaller</div>
                <div class="stat-value" style="font-size:1rem;">{{ $values['auto_assign_tc'] ? $values['auto_assign_tc_hours'].'h timeout' : 'Disabled' }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--grad-primary)"><span class="material-icons">schema</span></div>
                <div class="stat-label">Course Mappings</div>
                <div class="stat-value" style="font-size:1rem;">{{ $mappings->where('is_active', true)->count() }} active</div>
            </div>
        </div>
    </div>

    {{-- Main settings form --}}
    <div class="chart-card mb-4">
        <div class="chart-header">
            <h3>Lead Assignment Rules</h3>
            <p>Choose how incoming leads are distributed to managers.</p>
        </div>

        <form method="POST" action="{{ route('admin.automation.lead-assignment.update') }}">
            @csrf

            {{-- Mode picker --}}
            <div class="mb-4">
                <label class="form-label fw-semibold mb-2">Assignment Mode</label>
                <div class="row g-3">
                    @foreach([
                        ['value' => 'round_robin',  'icon' => 'sync',         'label' => 'Round Robin',   'desc' => 'Leads distributed evenly to all active managers in rotation.'],
                        ['value' => 'course_based', 'icon' => 'school',        'label' => 'Course Based',  'desc' => 'Assign leads to the manager mapped to the lead\'s course. Falls back to round-robin if no mapping found.'],
                        ['value' => 'open_pool',    'icon' => 'inbox',         'label' => 'Open Pool',     'desc' => 'Leads go into an open pool. Any manager can claim a lead first-come first-served.'],
                    ] as $opt)
                    <div class="col-md-4">
                        <label class="d-block border rounded-3 p-3 cursor-pointer {{ $values['mode'] === $opt['value'] ? 'border-primary bg-primary bg-opacity-10' : 'border-secondary' }}"
                            style="cursor:pointer;" for="mode_{{ $opt['value'] }}">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <input class="form-check-input mt-0" type="radio" name="mode"
                                    id="mode_{{ $opt['value'] }}" value="{{ $opt['value'] }}"
                                    {{ $values['mode'] === $opt['value'] ? 'checked' : '' }}>
                                <span class="material-icons" style="font-size:20px;color:var(--primary)">{{ $opt['icon'] }}</span>
                                <span class="fw-semibold">{{ $opt['label'] }}</span>
                            </div>
                            <small class="text-muted">{{ $opt['desc'] }}</small>
                        </label>
                    </div>
                    @endforeach
                </div>
            </div>

            <hr class="my-3">

            {{-- General toggles --}}
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1"
                            {{ $values['enabled'] ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="enabled">Enable auto lead assignment</label>
                    </div>
                    <small class="text-muted">When disabled, no manager is auto-assigned — leads must be manually assigned by admin.</small>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="active_only" name="active_only" value="1"
                            {{ $values['active_only'] ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="active_only">Assign only to active managers</label>
                    </div>
                    <small class="text-muted">Applies to round-robin and course-based fallback.</small>
                </div>
            </div>

            <hr class="my-3">

            {{-- Auto-assign telecaller --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">Auto-Assign Telecaller</label>
                <p class="text-muted small mb-2">
                    If a manager has a lead but hasn't assigned it to a telecaller within the configured time,
                    the system will auto-assign via round-robin to all active telecallers.
                </p>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="auto_assign_tc" name="auto_assign_tc" value="1"
                        {{ $values['auto_assign_tc'] ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="auto_assign_tc">Enable auto-assign to telecaller</label>
                </div>
                <div class="row g-2 align-items-center">
                    <div class="col-auto">
                        <label class="col-form-label fw-semibold" for="auto_assign_tc_hours">Timeout (hours)</label>
                    </div>
                    <div class="col-auto">
                        <input type="number" class="form-control" id="auto_assign_tc_hours" name="auto_assign_tc_hours"
                            value="{{ $values['auto_assign_tc_hours'] }}" min="1" max="720" style="width:100px;">
                    </div>
                    <div class="col-auto">
                        <small class="text-muted">1–720 hours. Runs every minute via scheduler.</small>
                    </div>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save Rules</button>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">Back</a>
            </div>
        </form>
    </div>

    {{-- Course-based mappings --}}
    <div class="chart-card" id="course-mappings">
        <div class="chart-header d-flex justify-content-between align-items-start">
            <div>
                <h3>Course → Manager Mappings</h3>
                <p>Used when assignment mode is <strong>Course Based</strong>. Each active mapping routes leads with that course to the specified manager.</p>
            </div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#addMappingForm">
                <span class="material-icons" style="font-size:16px;vertical-align:-3px;">add</span> Add Mapping
            </button>
        </div>

        {{-- Add mapping form --}}
        <div class="collapse mb-3" id="addMappingForm">
            <div class="border rounded-3 p-3 bg-light">
                <form method="POST" action="{{ route('admin.automation.course-mapping.store') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Course</label>
                        <select name="course_id" class="form-select" required>
                            <option value="">— Select course —</option>
                            @foreach($courses as $course)
                                <option value="{{ $course->id }}">{{ $course->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Manager</label>
                        <select name="manager_id" class="form-select" required>
                            <option value="">— Select manager —</option>
                            @foreach($managers as $manager)
                                <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-success">Add Mapping</button>
                    </div>
                </form>
            </div>
        </div>

        @if($mappings->isEmpty())
            <p class="text-muted">No course mappings configured yet.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Course</th>
                            <th>Manager</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($mappings as $mapping)
                        <tr>
                            <td class="fw-semibold">{{ $mapping->course->name ?? '—' }}</td>
                            <td>{{ $mapping->manager->name ?? '—' }}</td>
                            <td>
                                @if($mapping->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Disabled</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.automation.course-mapping.toggle', $mapping) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $mapping->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                                        {{ $mapping->is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.automation.course-mapping.destroy', $mapping) }}" class="d-inline"
                                    onsubmit="return confirm('Remove this mapping?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
