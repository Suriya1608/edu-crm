@extends('layouts.app')

@section('page_title', 'Lead Import')

@section('content')
    <div class="chart-card mb-3">
        <div class="chart-header mb-2">
            <h3>Import Leads (Excel / CSV)</h3>
            <p>Columns: Name, Phone, Email, Course, Source</p>
        </div>

        <form method="POST" action="{{ route('admin.leads.import.preview') }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-3 align-items-end">
                <div class="col-md-9">
                    <label class="form-label">Select File</label>
                    <input type="file" name="file" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100">Preview</button>
                </div>
            </div>
        </form>
    </div>

    @if (isset($rows) && count($rows) > 0)
        <div class="custom-table">
            <div class="table-header">
                <h3>Preview Leads</h3>
                <span class="text-muted" style="font-size:12px;">{{ count($rows) }} rows</span>
            </div>

            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Course</th>
                            <th>Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $row[0] ?? '' }}</td>
                                <td>{{ $row[1] ?? '' }}</td>
                                <td>{{ $row[2] ?? '' }}</td>
                                <td>{{ $row[3] ?? '' }}</td>
                                <td>{{ $row[4] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-3">
                <form method="POST" action="{{ route('admin.leads.import.store') }}">
                    @csrf
                    <input type="hidden" name="leads_data" value='@json($rows)'>
                    <button class="btn btn-success">Confirm & Save</button>
                </form>
            </div>
        </div>
    @endif
@endsection

