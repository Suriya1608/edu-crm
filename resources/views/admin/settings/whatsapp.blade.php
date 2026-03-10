@extends('layouts.app')

@section('page_title', 'Meta WhatsApp Settings')

@section('content')
    @include('admin.settings.partials.nav')

    <div class="chart-card">
        <div class="chart-header mb-3">
            <h3>Meta WhatsApp Business API</h3>
            <p>Connect your Meta (Facebook) WhatsApp Business account to send and receive messages via the Cloud API.</p>
        </div>

        <form method="POST" action="{{ route('admin.settings.whatsapp.update') }}">
            @csrf
            <div class="row g-3">

                <div class="col-12">
                    <label class="form-label fw-semibold">Access Token (Permanent)</label>
                    <input type="text" class="form-control font-monospace"
                           name="meta_whatsapp_token"
                           value="{{ $token }}"
                           placeholder="EAAxxxxxxx...">
                    <small class="text-muted">
                        Generate a <strong>permanent access token</strong> in Meta Business Manager → System Users. Never use a temporary token in production.
                    </small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Phone Number ID</label>
                    <input type="text" class="form-control font-monospace"
                           name="meta_whatsapp_phone_number_id"
                           value="{{ $phoneId }}"
                           placeholder="1234567890123456">
                    <small class="text-muted">
                        Found in Meta Developer Console → WhatsApp → API Setup → Phone Number ID.
                    </small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Webhook Verify Token</label>
                    <input type="text" class="form-control"
                           name="meta_whatsapp_webhook_verify_token"
                           value="{{ $verifyToken }}"
                           placeholder="crm_verify_token">
                    <small class="text-muted">
                        A secret string you choose. Enter the same value in the Meta Webhook configuration.
                    </small>
                </div>

                <div class="col-12">
                    <div class="alert alert-info mb-0">
                        <strong>Webhook URL — register this in Meta Developer Console:</strong><br>
                        <code>{{ route('meta.whatsapp.webhook') }}</code>
                        <hr class="my-2">
                        <strong>Setup steps:</strong>
                        <ol class="mb-0 mt-1 ps-3" style="font-size:13px;">
                            <li>Go to developers.facebook.com → your App → WhatsApp → Configuration</li>
                            <li>Set the Webhook URL above and enter your Verify Token</li>
                            <li>Subscribe to webhook field: <strong>messages</strong></li>
                            <li>Copy the Phone Number ID from API Setup and paste above</li>
                            <li>Generate a permanent System User access token in Business Manager and paste above</li>
                        </ol>
                    </div>
                </div>

            </div>

            <div class="mt-4">
                <button class="btn btn-primary">Save Meta WhatsApp Settings</button>
            </div>
        </form>
    </div>
@endsection
