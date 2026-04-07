@extends('layouts.app')

@section('page_title', 'General Settings')

@section('content')

    @include('admin.settings.partials.nav')

    <div class="card p-4">
        <form method="POST" action="{{ route('admin.settings.general.update') }}" enctype="multipart/form-data">

            @csrf

            <div class="row g-3">

                <!-- Site Name -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Site Name *</label>
                    <input type="text" name="site_name" class="form-control"
                        value="{{ \App\Models\Setting::get('site_name') }}" required>
                </div>

                <!-- Site URL -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Site URL *</label>
                    <input type="text" name="site_url" class="form-control"
                        value="{{ \App\Models\Setting::get('site_url') }}" required>
                </div>

                <!-- Employee ID Prefix -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Employee ID Prefix *</label>
                    <input type="text" name="employee_id_prefix" class="form-control"
                        value="{{ \App\Models\Setting::get('employee_id_prefix', 'EMP') }}"
                        placeholder="e.g. IHCM" maxlength="10" required>
                    <small class="text-muted">IDs will be generated as <strong>PREFIX0001</strong>, <strong>PREFIX0002</strong>, …</small>
                </div>

                <!-- Site Logo -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Site Logo</label>
                    <input type="file" name="site_logo" id="site_logo" class="form-control"
                        accept="image/png,image/jpeg,image/jpg,image/webp">

                    <small class="text-muted">
                        Allowed: JPG, PNG, WEBP | Max: 2MB
                    </small>

                    <!-- Preview -->
                    @if (\App\Models\Setting::get('site_logo'))
                        <div class="mt-2">
                            <img id="logoPreview" src="{{ asset('storage/' . \App\Models\Setting::get('site_logo')) }}"
                                height="60">
                        </div>
                    @else
                        <img id="logoPreview" class="mt-2" height="60" style="display:none;">
                    @endif
                </div>

                <!-- Favicon -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Site Favicon</label>
                    <input type="file" name="site_favicon" id="site_favicon" class="form-control"
                        accept="image/png,image/x-icon">

                    <small class="text-muted">
                        Allowed: PNG, ICO | Max: 512KB
                    </small>

                    <!-- Preview -->
                    @if (\App\Models\Setting::get('site_favicon'))
                        <div class="mt-2">
                            <img id="faviconPreview"
                                src="{{ asset('storage/' . \App\Models\Setting::get('site_favicon')) }}" height="32">
                        </div>
                    @else
                        <img id="faviconPreview" class="mt-2" height="32" style="display:none;">
                    @endif
                </div>

            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <span class="material-icons me-1" style="font-size:18px;">save</span>
                    Save Settings
                </button>

                {{-- <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">
                    Cancel
                </a> --}}
            </div>

        </form>
    </div>

@endsection


@section('scripts')
    <script>
        document.addEventListener("DOMContentLoaded", function() {

            // ===============================
            // LOGO VALIDATION + PREVIEW
            // ===============================
            const logoInput = document.getElementById('site_logo');
            const logoPreview = document.getElementById('logoPreview');

            logoInput.addEventListener('change', function() {

                const file = this.files[0];
                if (!file) return;

                const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                const maxSize = 2 * 1024 * 1024; // 2MB

                if (!allowedTypes.includes(file.type)) {
                    alert("Logo must be JPG, PNG or WEBP format.");
                    this.value = "";
                    return;
                }

                if (file.size > maxSize) {
                    alert("Logo must be less than 2MB.");
                    this.value = "";
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    logoPreview.src = e.target.result;
                    logoPreview.style.display = "block";
                }
                reader.readAsDataURL(file);
            });

            // ===============================
            // FAVICON VALIDATION + PREVIEW
            // ===============================
            const faviconInput = document.getElementById('site_favicon');
            const faviconPreview = document.getElementById('faviconPreview');

            faviconInput.addEventListener('change', function() {

                const file = this.files[0];
                if (!file) return;

                const allowedTypes = ['image/png', 'image/x-icon'];
                const maxSize = 512 * 1024; // 512KB

                if (!allowedTypes.includes(file.type)) {
                    alert("Favicon must be PNG or ICO format.");
                    this.value = "";
                    return;
                }

                if (file.size > maxSize) {
                    alert("Favicon must be less than 512KB.");
                    this.value = "";
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    faviconPreview.src = e.target.result;
                    faviconPreview.style.display = "block";
                }
                reader.readAsDataURL(file);
            });

        });
    </script>
@endsection
