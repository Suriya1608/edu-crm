@extends('layouts.app')

@section('page_title', 'Working Days')

@section('content')
    @include('admin.settings.partials.nav')

    @php
        $savedDays = json_decode(\App\Models\Setting::get('working_days', json_encode([1, 2, 3, 4, 5, 6])), true) ?: [1, 2, 3, 4, 5, 6];
        $dayMap = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
    @endphp

    <div class="chart-card">
        <form method="POST" action="{{ route('admin.settings.working-days.update') }}">
            @csrf
            <div class="row g-3">
                @foreach ($dayMap as $id => $name)
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="working_days[]" value="{{ $id }}"
                                id="day_{{ $id }}" {{ in_array($id, $savedDays, true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="day_{{ $id }}">{{ $name }}</label>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-3"><button class="btn btn-primary">Save Working Days</button></div>
        </form>
    </div>
@endsection
