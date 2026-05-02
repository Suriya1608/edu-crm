@extends('layouts.app')

@section('page_title', 'Course Intakes')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h2 class="page-header-title mb-0">Course Intakes</h2>
            <p class="page-header-subtitle mb-0">Seat allocation per quota per academic year</p>
        </div>
        <a href="{{ route('admin.course-intakes.create') }}" class="btn btn-primary btn-sm">
            <span class="material-icons me-1" style="font-size:16px;">add</span>Add Intake
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Year filter --}}
    <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
        <span class="text-muted fw-semibold" style="font-size:13px;">Academic Year:</span>
        @foreach ($years as $y)
            <a href="{{ route('admin.course-intakes.index', ['year_id' => $y->id]) }}"
                class="btn btn-sm {{ $selectedYear?->id === $y->id ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $y->name }}
                @if ($y->is_active) <span class="badge bg-white text-primary ms-1" style="font-size:10px;">Current</span> @endif
            </a>
        @endforeach
        @if ($years->isEmpty())
            <span class="text-muted" style="font-size:13px;">No academic years found.
                <a href="{{ route('admin.academic-years.create') }}">Add one first.</a>
            </span>
        @endif
    </div>

    <div class="chart-card">
        @if (!$selectedYear)
            <div class="text-center py-5 text-muted">
                <span class="material-icons" style="font-size:48px;opacity:.3;">event_seat</span>
                <p class="mt-2">Select an academic year above to view its intakes.</p>
            </div>
        @elseif ($intakes->isEmpty())
            <div class="text-center py-5 text-muted">
                <span class="material-icons" style="font-size:48px;opacity:.3;">event_seat</span>
                <p class="mt-2">No intakes defined for <strong>{{ $selectedYear->name }}</strong> yet.</p>
                <a href="{{ route('admin.course-intakes.create') }}" class="btn btn-primary btn-sm mt-1">Add Intake</a>
            </div>
        @else
            {{-- Summary bar --}}
            @php
                $totalMgmtSeats  = $intakes->sum('management_seats');
                $totalCounSeats  = $intakes->sum('counselling_seats');
                $totalSeats      = $totalMgmtSeats + $totalCounSeats;
                $totalMgmtEnr    = $intakes->sum('management_enrolled');
                $totalCounEnr    = $intakes->sum('counselling_enrolled');
                $totalEnrolled   = $totalMgmtEnr + $totalCounEnr;
                $totalBalance    = $totalSeats - $totalEnrolled;
                $fillPct         = $totalSeats > 0 ? round($totalEnrolled / $totalSeats * 100) : 0;
            @endphp

            {{-- Overall summary --}}
            <div class="row g-3 mb-3">
                <div class="col-sm-3">
                    <div class="p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;">
                        <div class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Total Seats</div>
                        <div class="fw-bold" style="font-size:22px;">{{ number_format($totalSeats) }}</div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;">
                        <div class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Enrolled</div>
                        <div class="fw-bold text-success" style="font-size:22px;">{{ number_format($totalEnrolled) }}</div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;">
                        <div class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Balance</div>
                        <div class="fw-bold {{ $totalBalance <= 0 ? 'text-danger' : 'text-primary' }}" style="font-size:22px;">
                            {{ number_format($totalBalance) }}
                        </div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;">
                        <div class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Fill Rate</div>
                        <div class="fw-bold" style="font-size:22px;">{{ $fillPct }}%</div>
                        <div class="progress mt-1" style="height:4px;">
                            <div class="progress-bar {{ $fillPct >= 90 ? 'bg-danger' : ($fillPct >= 70 ? 'bg-warning' : 'bg-success') }}"
                                style="width:{{ $fillPct }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Quota summary --}}
            <div class="row g-3 mb-4">
                <div class="col-sm-6">
                    <div class="p-3 rounded" style="background:#eef2ff;border:1px solid #c7d2fe;">
                        <div class="fw-semibold mb-2" style="font-size:12px;color:#4f46e5;">
                            <span class="badge me-1" style="background:#6366f1;">M</span>Management Quota
                        </div>
                        <div class="d-flex gap-4">
                            <div>
                                <div style="font-size:11px;color:#64748b;">Seats</div>
                                <div class="fw-bold" style="font-size:18px;">{{ number_format($totalMgmtSeats) }}</div>
                            </div>
                            <div>
                                <div style="font-size:11px;color:#64748b;">Enrolled</div>
                                <div class="fw-bold text-success" style="font-size:18px;">{{ number_format($totalMgmtEnr) }}</div>
                            </div>
                            <div>
                                <div style="font-size:11px;color:#64748b;">Balance</div>
                                <div class="fw-bold {{ ($totalMgmtSeats - $totalMgmtEnr) <= 0 ? 'text-danger' : 'text-primary' }}" style="font-size:18px;">
                                    {{ number_format($totalMgmtSeats - $totalMgmtEnr) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="p-3 rounded" style="background:#ecfdf5;border:1px solid #a7f3d0;">
                        <div class="fw-semibold mb-2" style="font-size:12px;color:#059669;">
                            <span class="badge me-1" style="background:#10b981;">C</span>Counselling Quota
                        </div>
                        <div class="d-flex gap-4">
                            <div>
                                <div style="font-size:11px;color:#64748b;">Seats</div>
                                <div class="fw-bold" style="font-size:18px;">{{ number_format($totalCounSeats) }}</div>
                            </div>
                            <div>
                                <div style="font-size:11px;color:#64748b;">Enrolled</div>
                                <div class="fw-bold text-success" style="font-size:18px;">{{ number_format($totalCounEnr) }}</div>
                            </div>
                            <div>
                                <div style="font-size:11px;color:#64748b;">Balance</div>
                                <div class="fw-bold {{ ($totalCounSeats - $totalCounEnr) <= 0 ? 'text-danger' : 'text-success' }}" style="font-size:18px;">
                                    {{ number_format($totalCounSeats - $totalCounEnr) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Course</th>
                            <th class="text-center" colspan="3" style="background:#eef2ff;color:#4f46e5;font-size:12px;">
                                <span class="badge me-1" style="background:#6366f1;">M</span>Management
                            </th>
                            <th class="text-center" colspan="3" style="background:#ecfdf5;color:#059669;font-size:12px;">
                                <span class="badge me-1" style="background:#10b981;">C</span>Counselling
                            </th>
                            <th>Overall Fill</th>
                            <th></th>
                        </tr>
                        <tr class="text-muted" style="font-size:11px;">
                            <th></th>
                            <th class="text-end" style="background:#eef2ff;">Seats</th>
                            <th class="text-end" style="background:#eef2ff;">Enrolled</th>
                            <th class="text-end" style="background:#eef2ff;">Balance</th>
                            <th class="text-end" style="background:#ecfdf5;">Seats</th>
                            <th class="text-end" style="background:#ecfdf5;">Enrolled</th>
                            <th class="text-end" style="background:#ecfdf5;">Balance</th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($intakes as $intake)
                            @php
                                $pct      = $intake->total_seats > 0
                                    ? round($intake->total_enrolled / $intake->total_seats * 100)
                                    : 0;
                                $barClass = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success');
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $intake->course?->name ?? '—' }}</td>
                                {{-- Management --}}
                                <td class="text-end" style="background:#f8f9ff;">{{ $intake->management_seats }}</td>
                                <td class="text-end text-success fw-semibold" style="background:#f8f9ff;">{{ $intake->management_enrolled }}</td>
                                <td class="text-end fw-semibold {{ $intake->management_balance <= 0 ? 'text-danger' : 'text-primary' }}" style="background:#f8f9ff;">
                                    {{ $intake->management_balance }}
                                </td>
                                {{-- Counselling --}}
                                <td class="text-end" style="background:#f0fdf9;">{{ $intake->counselling_seats }}</td>
                                <td class="text-end text-success fw-semibold" style="background:#f0fdf9;">{{ $intake->counselling_enrolled }}</td>
                                <td class="text-end fw-semibold {{ $intake->counselling_balance <= 0 ? 'text-danger' : 'text-success' }}" style="background:#f0fdf9;">
                                    {{ $intake->counselling_balance }}
                                </td>
                                {{-- Overall fill --}}
                                <td style="min-width:110px;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height:6px;">
                                            <div class="progress-bar {{ $barClass }}" style="width:{{ $pct }}%"></div>
                                        </div>
                                        <span style="font-size:11px;white-space:nowrap;">{{ $pct }}%</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="{{ route('admin.course-intakes.edit', $intake->id) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            <span class="material-icons" style="font-size:15px;">edit</span>
                                        </a>
                                        <form action="{{ route('admin.course-intakes.destroy', $intake->id) }}"
                                            method="POST"
                                            onsubmit="return confirm('Remove this intake? Historical lead data is preserved.')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">
                                                <span class="material-icons" style="font-size:15px;">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
