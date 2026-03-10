@extends('layouts.app')

@section('page_title', 'Facebook Lead Ads Settings')

@section('content')
    @include('admin.settings.partials.nav')

    <div class="chart-card">
        <div class="chart-header mb-3">
            <h3>Facebook & Instagram Lead Ads</h3>
            <p>Connect your Meta Facebook Page to automatically capture leads from Facebook and Instagram lead forms into the CRM.</p>
        </div>

        <form method="POST" action="{{ route('admin.settings.facebook-leads.update') }}">
            @csrf
            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label fw-semibold">App ID</label>
                    <input type="text" class="form-control font-monospace"
                           name="fb_leads_app_id"
                           value="{{ $appId }}"
                           placeholder="1234567890123456">
                    <small class="text-muted">
                        Found in developers.facebook.com → Your App → Settings → Basic.
                    </small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">App Secret</label>
                    <input type="password" class="form-control font-monospace"
                           name="fb_leads_app_secret"
                           value="{{ $appSecret }}"
                           placeholder="Leave blank to keep existing">
                    <small class="text-muted">
                        Same page → click <strong>Show</strong> next to App Secret. Used to verify webhook signatures.
                    </small>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Page Access Token</label>
                    <input type="password" class="form-control font-monospace"
                           name="fb_leads_page_token"
                           value="{{ $pageToken }}"
                           placeholder="Leave blank to keep existing">
                    <small class="text-muted">
                        Generate a <strong>long-lived Page Access Token</strong> in Graph API Explorer with
                        <code>leads_retrieval</code>, <code>pages_show_list</code>, <code>pages_read_engagement</code>,
                        and <code>pages_manage_metadata</code> permissions selected.
                    </small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Page ID</label>
                    <input type="text" class="form-control font-monospace"
                           name="fb_leads_page_id"
                           value="{{ $pageId }}"
                           placeholder="1234567890123456">
                    <small class="text-muted">
                        Facebook Page → About → Page ID (or Page Settings → Page Info).
                    </small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Webhook Verify Token</label>
                    <input type="text" class="form-control"
                           name="fb_leads_verify_token"
                           value="{{ $verifyToken }}"
                           placeholder="crm_fb_verify_token">
                    <small class="text-muted">
                        A secret string you choose. Enter the same value in the Meta Webhook configuration.
                    </small>
                </div>

                <div class="col-12">
                    <div class="alert alert-info mb-0">
                        <strong>Webhook URL — register this in Meta Developer Console:</strong><br>
                        <code>{{ route('meta.facebook.webhook') }}</code>
                        <hr class="my-2">
                        <strong>Setup steps:</strong>
                        <ol class="mb-0 mt-1 ps-3" style="font-size:13px;">
                            <li>Go to developers.facebook.com → Your App → Webhooks → <strong>Page</strong></li>
                            <li>Set the Webhook URL above and enter your Verify Token, then click Verify</li>
                            <li>Subscribe to webhook field: <strong>leadgen</strong></li>
                            <li>Add these permissions to your app: <code>leads_retrieval</code>, <code>pages_show_list</code>, <code>pages_read_engagement</code>, <code>pages_manage_metadata</code></li>
                            <li>Generate a long-lived Page Access Token via Graph API Explorer and paste it above</li>
                        </ol>
                        <hr class="my-2">
                        <strong>Instagram Lead Ads:</strong> Automatic — if your Instagram Business account is linked to the same Facebook Page, Instagram lead form submissions will arrive via the same webhook. No separate setup needed.
                    </div>
                </div>

            </div>

            <div class="mt-4">
                <button class="btn btn-primary">Save Facebook Lead Ads Settings</button>
            </div>
        </form>
    </div>
@endsection
