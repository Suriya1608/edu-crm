@extends('layouts.app')

@section('page_title', 'WhatsApp Settings')

@section('content')
    @include('admin.settings.partials.nav')

    {{-- Active provider banner --}}
    <div class="alert alert-primary mb-3 d-flex align-items-center gap-2" style="border-radius:10px;">
        <span class="material-icons" style="font-size:20px;">check_circle</span>
        <div>
            <strong>Active Provider:</strong> Meta (Facebook) WhatsApp Cloud API
        </div>
    </div>

    <form method="POST" action="{{ route('admin.settings.whatsapp.update') }}">
        @csrf

        {{-- ── Meta WhatsApp ────────────────────────────────────────────── --}}
        <div class="chart-card mb-3">
            <div class="chart-header mb-3">
                <h3>
                    <span class="material-icons align-middle" style="font-size:20px;color:#1877F2;">facebook</span>
                    Meta WhatsApp Business API
                </h3>
                <p>Connect your Meta (Facebook) WhatsApp Business account to send and receive messages via the Cloud API.</p>
            </div>

            <div class="row g-3">

                <div class="col-12">
                    <label class="form-label fw-semibold">Access Token (Permanent)</label>
                    <input type="text" class="form-control font-monospace"
                           name="meta_whatsapp_token"
                           value="{{ $token }}"
                           placeholder="EAAxxxxxxx...">
                    <small class="text-muted">
                        Generate a <strong>permanent access token</strong> in Meta Business Manager → System Users. Leave blank to keep the existing token.
                    </small>
                    @error('meta_whatsapp_token')
                        <div class="text-danger" style="font-size:13px;">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Phone Number ID</label>
                    <input type="text" class="form-control font-monospace"
                           name="meta_whatsapp_phone_number_id"
                           value="{{ $phoneId }}"
                           placeholder="1234567890123456">
                    <small class="text-muted">Found in Meta Developer Console → WhatsApp → API Setup → Phone Number ID.</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Webhook Verify Token</label>
                    <input type="text" class="form-control"
                           name="meta_whatsapp_webhook_verify_token"
                           value="{{ $verifyToken }}"
                           placeholder="crm_verify_token">
                    <small class="text-muted">A secret string you choose. Enter the same value in the Meta Webhook configuration.</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Default Template Name</label>
                    <input type="text" class="form-control font-monospace"
                           name="meta_whatsapp_template_name"
                           value="{{ $templateName }}"
                           placeholder="welcome_template">
                    <small class="text-muted">Template used when no 24h inbound window exists. Must be approved in WhatsApp Manager.</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Template Language Code</label>
                    <input type="text" class="form-control font-monospace"
                           name="meta_whatsapp_template_language"
                           value="{{ $templateLanguage }}"
                           placeholder="en">
                    <small class="text-muted">
                        Exact code from WhatsApp Manager template details. Common: <code>en</code>, <code>en_US</code>, <code>en_GB</code>.
                        Check your template's language in WhatsApp Manager → Message Templates.
                    </small>
                </div>

                <div class="col-12">
                    <div class="alert alert-warning mb-0 border-0" style="background:#fff8e1;">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="material-icons text-warning" style="font-size:20px;">webhook</span>
                            <strong>Webhook URL — must be registered in Meta Developer Console to receive incoming messages</strong>
                        </div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <code class="flex-grow-1 p-2 rounded" style="background:#f0f4f8;font-size:13px;">{{ route('meta.whatsapp.webhook') }}</code>
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                onclick="navigator.clipboard.writeText('{{ route('meta.whatsapp.webhook') }}');this.textContent='Copied!'">
                                Copy
                            </button>
                        </div>
                        <ol class="mb-0 ps-3" style="font-size:13px;">
                            <li>Go to <strong>developers.facebook.com → your App → WhatsApp → Configuration</strong></li>
                            <li>Under <strong>Webhook</strong>, click <strong>Edit</strong> and paste the URL above</li>
                            <li>Set Verify Token to: <code>{{ $verifyToken }}</code></li>
                            <li>Click <strong>Verify and Save</strong>, then subscribe to: <strong>messages</strong></li>
                        </ol>
                    </div>
                </div>

            </div>
        </div>

        <div class="mt-2">
            <button class="btn btn-primary">
                <span class="material-icons me-1" style="font-size:16px;vertical-align:middle;">save</span>
                Save WhatsApp Settings
            </button>
        </div>

    </form>
@endsection
