@extends('layouts.app')

@section('page_title', 'Instagram Settings')

@section('content')
    @include('admin.settings.partials.nav')

    <div class="chart-card">
        <div class="chart-header mb-3">
            <h3>Instagram Business API</h3>
            <p>Connect your Instagram Business account via the Meta Graph API to read and reply to DMs inside the CRM.</p>
        </div>

        <form method="POST" action="{{ route('admin.settings.instagram.update') }}">
            @csrf
            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Account Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror"
                           name="name"
                           value="{{ old('name', $account?->name) }}"
                           placeholder="My Instagram Business Account">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Facebook Page ID <span class="text-danger">*</span></label>
                    <input type="text" class="form-control font-monospace @error('page_id') is-invalid @enderror"
                           name="page_id"
                           value="{{ old('page_id', $account?->page_id) }}"
                           placeholder="123456789012345">
                    <small class="text-muted">Found in Meta Developer Console → App → Instagram → API Setup.</small>
                    @error('page_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Instagram User ID</label>
                    <input type="text" class="form-control font-monospace"
                           name="instagram_user_id"
                           value="{{ old('instagram_user_id', $account?->instagram_user_id) }}"
                           placeholder="Optional — Instagram-scoped User ID">
                    <small class="text-muted">Optional. Used for reference only.</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Webhook Verify Token <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('verify_token') is-invalid @enderror"
                           name="verify_token"
                           value="{{ old('verify_token', $account?->verify_token ?? \Illuminate\Support\Str::random(32)) }}"
                           placeholder="crm_instagram_verify_token">
                    <small class="text-muted">A secret string you choose. Enter the same value in the Meta Webhook configuration.</small>
                    @error('verify_token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Access Token (Permanent) {{ $account ? '— leave blank to keep current' : '<span class="text-danger">*</span>' }}</label>
                    <input type="text" class="form-control font-monospace @error('access_token') is-invalid @enderror"
                           name="access_token"
                           value=""
                           placeholder="{{ $account ? 'Leave blank to keep existing token' : 'EAAxxxxxxx...' }}">
                    <small class="text-muted">
                        Generate a <strong>permanent access token</strong> in Meta Business Manager → System Users. Never use a temporary token in production.
                    </small>
                    @error('access_token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">App Secret {{ $account ? '— leave blank to keep current' : '' }}</label>
                    <input type="text" class="form-control font-monospace"
                           name="app_secret"
                           value=""
                           placeholder="{{ $account ? 'Leave blank to keep existing secret' : 'Optional — used for webhook signature verification' }}">
                    <small class="text-muted">Found in Meta Developer Console → App → Settings → Basic. Used to verify webhook payloads (recommended).</small>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                               {{ old('is_active', $account?->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">Account Active</label>
                    </div>
                    <small class="text-muted">Only one account can be active at a time. Incoming webhooks and outbound replies use this account.</small>
                </div>

                <div class="col-12">
                    <div class="alert alert-info mb-0">
                        <strong>Webhook URL — register this in Meta Developer Console:</strong><br>
                        <code>{{ route('meta.instagram.webhook') }}</code>
                        <hr class="my-2">
                        <strong>Setup steps:</strong>
                        <ol class="mb-0 mt-1 ps-3" style="font-size:13px;">
                            <li>Go to developers.facebook.com → your App → Instagram → Configuration</li>
                            <li>Set the Webhook URL above and enter your Verify Token</li>
                            <li>Subscribe to webhook field: <strong>messages</strong></li>
                            <li>Add your Facebook Page ID above (found in API Setup)</li>
                            <li>Generate a permanent System User access token in Business Manager and paste above</li>
                            <li>Optionally add the App Secret for webhook payload verification</li>
                        </ol>
                    </div>
                </div>

            </div>

            <div class="mt-4">
                <button class="btn btn-primary">Save Instagram Settings</button>
                @if($account)
                    <span class="ms-3 text-muted" style="font-size:13px;">
                        Last updated: {{ $account->updated_at->diffForHumans() }}
                    </span>
                @endif
            </div>
        </form>
    </div>
@endsection
