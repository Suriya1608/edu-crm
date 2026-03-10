@extends('layouts.manager.app')

@section('content')
<div class="container">

    <h4>Preview Leads</h4>

    <form method="POST"
          action="{{ route('manager.leads.import.store') }}">
        @csrf

        <input type="hidden"
               name="leads_data"
               value="{{ json_encode($rows) }}">

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Course</th>
                    <th>Source</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row[0] ?? '' }}</td>
                        <td>{{ $row[1] ?? '' }}</td>
                        <td>{{ $row[2] ?? '' }}</td>
                        <td>{{ $row[3] ?? '' }}</td>
                        <td>{{ $row[4] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <button class="btn btn-success">
            Confirm & Save
        </button>

        <a href="{{ route('manager.leads.import') }}"
           class="btn btn-secondary">
           Cancel
        </a>

    </form>

</div>
@endsection
