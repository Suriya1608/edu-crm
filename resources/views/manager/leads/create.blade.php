@extends('layouts.manager.app')

@section('page_title', 'Add Lead')

@section('content')

<div class="card p-4">

<form method="POST"
    action="{{ route('manager.leads.store') }}">
@csrf

<div class="row g-3">

    <div class="col-md-6">
        <label>Name *</label>
        <input type="text"
            name="name"
            class="form-control"
            required>
    </div>

    <div class="col-md-6">
        <label>Phone *</label>
        <div class="input-group">
            <span class="input-group-text">+91</span>
            <input type="tel"
                name="phone"
                id="phoneInput"
                class="form-control"
                placeholder="10-digit mobile number"
                maxlength="10"
                pattern="[0-9]{10}"
                inputmode="numeric"
                required>
        </div>
        <div class="form-text">Enter 10-digit number — +91 is added automatically.</div>
    </div>

    <div class="col-md-6">
        <label>Email</label>
        <input type="email"
            name="email"
            class="form-control">
    </div>

    <div class="col-md-6">
        <label>Course</label>
        <input type="text"
            name="course"
            class="form-control">
    </div>

    <div class="col-md-6">
        <label>Source</label>
        <input type="text"
            name="source"
            class="form-control"
            value="manual">
    </div>

</div>

<div class="mt-4">
    <button class="btn btn-primary">
        Save Lead
    </button>

    <a href="{{ route('manager.leads') }}"
        class="btn btn-secondary">
        Cancel
    </a>
</div>

</form>

</div>

@endsection
