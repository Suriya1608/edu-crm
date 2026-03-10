@extends('layouts.app')

@section('page_title', 'Business Hours')

@section('content')
    @include('admin.settings.partials.nav')

    <div class="chart-card">
        <form method="POST" action="{{ route('admin.settings.business-hours.update') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" name="business_hours_enabled" value="1"
                            {{ \App\Models\Setting::get('business_hours_enabled', '1') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label">Enable Business Hours</label>
                    </div>
                </div>
                <div class="col-md-3"><label class="form-label">Start Time</label><input type="time" name="business_start_time" class="form-control" value="{{ \App\Models\Setting::get('business_start_time', '09:00') }}"></div>
                <div class="col-md-3"><label class="form-label">End Time</label><input type="time" name="business_end_time" class="form-control" value="{{ \App\Models\Setting::get('business_end_time', '18:00') }}"></div>
            </div>
            <div class="mt-3"><button class="btn btn-primary">Save Business Hours</button></div>
        </form>
    </div>
@endsection
