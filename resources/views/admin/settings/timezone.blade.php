@extends('layouts.app')

@section('page_title', 'Timezone Settings')

@section('content')
    @include('admin.settings.partials.nav')

    <div class="chart-card">
        <form method="POST" action="{{ route('admin.settings.timezone.update') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">System Timezone</label>
                    <select name="system_timezone" class="form-select" required>
                        @foreach ($timezones as $tz)
                            <option value="{{ $tz }}" {{ \App\Models\Setting::get('system_timezone', config('app.timezone')) === $tz ? 'selected' : '' }}>
                                {{ $tz }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-3"><button class="btn btn-primary">Save Timezone</button></div>
        </form>
    </div>
@endsection
