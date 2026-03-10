@extends('layouts.manager.app')

@section('page_title', 'Lead Bulk Import')

@section('content')
    <div class="container">
        <h4>Bulk Import Leads</h4>

        <form method="POST" action="{{ route('manager.leads.import.preview') }}" enctype="multipart/form-data">
            @csrf

            <div class="mb-3">
                <label>Select Excel File</label>
                <input type="file" name="file" class="form-control" required>
            </div>

            <button class="btn btn-primary" id="previewBtn">
                <span class="spinner-border spinner-border-sm d-none me-1" id="previewSpinner" role="status"></span>
                Preview
            </button>
            <a href="{{ route('manager.leads.import.sample') }}" class="btn btn-success btn-sm">
                Download Sample Excel
            </a>

        </form>
    </div>
    @if (isset($rows) && count($rows) > 0)

        <hr>
        <h5>Preview Leads ({{ count($rows) }} rows)</h5>

        <form method="POST" action="{{ route('manager.leads.import.store') }}">
            @csrf

            <input type="hidden" name="leads_data" value="{{ json_encode($rows) }}">

            <table id="previewTable" class="table table-bordered table-striped">
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

            <button class="btn btn-success mt-3" id="confirmBtn">
                <span class="spinner-border spinner-border-sm d-none me-1" id="confirmSpinner" role="status"></span>
                Confirm & Save
            </button>

    @endif
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#previewTable').DataTable({ pageLength: 10 });

            // Preview form spinner
            $('form[action="{{ route('manager.leads.import.preview') }}"]').on('submit', function() {
                $('#previewSpinner').removeClass('d-none');
                $('#previewBtn').prop('disabled', true);
            });

            // Confirm form spinner
            $('form[action="{{ route('manager.leads.import.store') }}"]').on('submit', function() {
                $('#confirmSpinner').removeClass('d-none');
                $('#confirmBtn').prop('disabled', true);
            });
        });
    </script>

@endsection
