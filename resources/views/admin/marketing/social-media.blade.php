@extends('layouts.app')

@section('page_title', 'Social Media')

@section('content')
    <!-- Platform Connectivity Section -->
    <section class="mb-5">
        <h3 class="section-title mb-4">
            <span class="material-icons text-primary me-2">link</span>
            Platform Connectivity
        </h3>

        <div class="row g-4">
            <!-- Facebook Card -->
            <div class="col-md-6">
                <div class="platform-card {{ $facebookConnected ? 'connected' : 'not-connected' }}">
                    <div class="platform-icon facebook">
                        <svg class="w-100 h-100" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                        </svg>
                    </div>
                    <h4 class="platform-title">Facebook Lead Ads</h4>
                    @if($facebookConnected)
                        <span class="status-badge status-connected">Connected</span>
                        <a href="{{ route('admin.settings.facebook-leads') }}" class="btn btn-outline-primary btn-sm w-100 mt-3">
                            Manage Settings
                        </a>
                    @else
                        <span class="status-badge status-not-connected">Not Configured</span>
                        <a href="{{ route('admin.settings.facebook-leads') }}" class="btn btn-primary btn-sm w-100 mt-3">
                            Configure
                        </a>
                    @endif
                </div>
            </div>

            <!-- Instagram Card -->
            <div class="col-md-6">
                <div class="platform-card {{ $facebookConnected ? 'connected' : 'not-connected' }}">
                    <div class="platform-icon instagram">
                        <svg class="w-100 h-100" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.981 1.28.058 1.688.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z" />
                        </svg>
                    </div>
                    <h4 class="platform-title">Instagram Lead Ads</h4>
                    @if($facebookConnected)
                        <span class="status-badge status-connected">Connected via Facebook</span>
                        <a href="{{ route('admin.settings.facebook-leads') }}" class="btn btn-outline-primary btn-sm w-100 mt-3">
                            Manage Settings
                        </a>
                    @else
                        <span class="status-badge status-not-connected">Requires Facebook Setup</span>
                        <a href="{{ route('admin.settings.facebook-leads') }}" class="btn btn-primary btn-sm w-100 mt-3">
                            Configure via Facebook
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <!-- Education Lead Portals Section -->
    <section class="mb-5">
        <h3 class="section-title mb-4">
            <span class="material-icons text-primary me-2">school</span>
            Education Lead Portals
        </h3>

        @php
            $sikshaConfigured  = !empty(\App\Models\Setting::get('siksha_api_key'));
            $cduniaConfigured  = !empty(\App\Models\Setting::get('college_dunia_api_key'));
            $cdekhoConfigured  = !empty(\App\Models\Setting::get('college_dekho_api_key'));
        @endphp

        <div class="row g-4">
            <!-- Siksha Card -->
            <div class="col-md-4">
                <div class="platform-card {{ $sikshaConfigured ? 'connected' : 'not-connected' }}">
                    <div class="platform-icon" style="background:linear-gradient(135deg,#f97316,#ea580c); color:#fff; width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:800; margin:0 auto 12px; letter-spacing:-1px;">
                        S
                    </div>
                    <h4 class="platform-title">Siksha</h4>
                    <p class="text-muted mb-3" style="font-size:12px;">Receive leads from Siksha.com via webhook integration.</p>
                    @if($sikshaConfigured)
                        <span class="status-badge status-connected">Configured</span>
                    @else
                        <span class="status-badge status-not-connected">Not Configured</span>
                    @endif
                    <a href="{{ route('admin.settings.lead-portals') }}" class="btn {{ $sikshaConfigured ? 'btn-outline-primary' : 'btn-primary' }} btn-sm w-100 mt-3">
                        {{ $sikshaConfigured ? 'Manage Credentials' : 'Configure' }}
                    </a>
                </div>
            </div>

            <!-- CollegeDunia Card -->
            <div class="col-md-4">
                <div class="platform-card {{ $cduniaConfigured ? 'connected' : 'not-connected' }}">
                    <div class="platform-icon" style="background:linear-gradient(135deg,#6366f1,#4f46e5); color:#fff; width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:800; margin:0 auto 12px; letter-spacing:-1px;">
                        CD
                    </div>
                    <h4 class="platform-title">CollegeDunia</h4>
                    <p class="text-muted mb-3" style="font-size:12px;">Receive leads from CollegeDunia partner portal via webhook.</p>
                    @if($cduniaConfigured)
                        <span class="status-badge status-connected">Configured</span>
                    @else
                        <span class="status-badge status-not-connected">Not Configured</span>
                    @endif
                    <a href="{{ route('admin.settings.lead-portals') }}" class="btn {{ $cduniaConfigured ? 'btn-outline-primary' : 'btn-primary' }} btn-sm w-100 mt-3">
                        {{ $cduniaConfigured ? 'Manage Credentials' : 'Configure' }}
                    </a>
                </div>
            </div>

            <!-- CollegeDekho Card -->
            <div class="col-md-4">
                <div class="platform-card {{ $cdekhoConfigured ? 'connected' : 'not-connected' }}">
                    <div class="platform-icon" style="background:linear-gradient(135deg,#10b981,#059669); color:#fff; width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:800; margin:0 auto 12px; letter-spacing:-1px;">
                        CD
                    </div>
                    <h4 class="platform-title">CollegeDekho</h4>
                    <p class="text-muted mb-3" style="font-size:12px;">Receive leads from CollegeDekho partner portal via webhook.</p>
                    @if($cdekhoConfigured)
                        <span class="status-badge status-connected">Configured</span>
                    @else
                        <span class="status-badge status-not-connected">Not Configured</span>
                    @endif
                    <a href="{{ route('admin.settings.lead-portals') }}" class="btn {{ $cdekhoConfigured ? 'btn-outline-primary' : 'btn-primary' }} btn-sm w-100 mt-3">
                        {{ $cdekhoConfigured ? 'Manage Credentials' : 'Configure' }}
                    </a>
                </div>
            </div>
        </div>

        <div class="chart-card mt-4">
            <div class="chart-header mb-3">
                <h3>How Portal Lead Capture Works</h3>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="d-flex gap-3 align-items-start">
                        <span class="material-icons text-primary" style="font-size:32px; flex-shrink:0;">person_add</span>
                        <div>
                            <h6 class="fw-bold mb-1">Student Enquires on Portal</h6>
                            <p class="text-muted mb-0" style="font-size:13px;">A student submits an enquiry form on Siksha, CollegeDunia, or CollegeDekho expressing interest in your institution.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-3 align-items-start">
                        <span class="material-icons text-primary" style="font-size:32px; flex-shrink:0;">webhook</span>
                        <div>
                            <h6 class="fw-bold mb-1">Portal Sends Webhook</h6>
                            <p class="text-muted mb-0" style="font-size:13px;">The portal instantly pushes the lead data to your CRM webhook URL — no manual export needed.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-3 align-items-start">
                        <span class="material-icons text-primary" style="font-size:32px; flex-shrink:0;">assignment_turned_in</span>
                        <div>
                            <h6 class="fw-bold mb-1">Lead Created in CRM</h6>
                            <p class="text-muted mb-0" style="font-size:13px;">A lead record is created automatically and enters your assignment &amp; follow-up automation pipeline.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section (Meta/Facebook) -->
    <section class="mb-5">
        <div class="chart-card">
            <div class="chart-header mb-3">
                <h3>How Facebook / Instagram Lead Capture Works</h3>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="d-flex gap-3 align-items-start">
                        <span class="material-icons text-primary" style="font-size:32px; flex-shrink:0;">person_add</span>
                        <div>
                            <h6 class="fw-bold mb-1">Lead Submits Form</h6>
                            <p class="text-muted mb-0" style="font-size:13px;">A prospect fills out your Facebook or Instagram Lead Ad form with their name, phone, and email.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-3 align-items-start">
                        <span class="material-icons text-primary" style="font-size:32px; flex-shrink:0;">webhook</span>
                        <div>
                            <h6 class="fw-bold mb-1">Meta Sends Webhook</h6>
                            <p class="text-muted mb-0" style="font-size:13px;">Meta instantly pushes the lead event to your CRM webhook URL in real time — no polling needed.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-3 align-items-start">
                        <span class="material-icons text-primary" style="font-size:32px; flex-shrink:0;">assignment_turned_in</span>
                        <div>
                            <h6 class="fw-bold mb-1">Lead Created in CRM</h6>
                            <p class="text-muted mb-0" style="font-size:13px;">The CRM fetches the lead details, creates a new lead record, and it enters your assignment &amp; follow-up automation.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info mt-4 mb-0" style="font-size:13px;">
                <strong>Webhook URL for Meta Developer Console:</strong>
                <code class="ms-2">{{ route('meta.facebook.webhook') }}</code>
                <br><span class="text-muted">Configure this in your Facebook App → Webhooks → Page → Subscribe to <strong>leadgen</strong> field.</span>
            </div>
        </div>
    </section>

    <!-- Recent Leads from Social Section -->
    <section>
        <div class="chart-card">
            <div class="chart-header mb-3">
                <h3>Recent Leads from Social Media</h3>
                <a href="{{ route('admin.leads.all') }}" class="btn btn-sm btn-outline-primary">View All Leads</a>
            </div>
            @php
                $recentLeads = \App\Models\Lead::whereIn('source', ['facebook_ads', 'instagram_ads', 'siksha', 'college_dunia', 'college_dekho'])
                    ->latest()
                    ->limit(5)
                    ->get();
            @endphp

            @if($recentLeads->isEmpty())
                <p class="text-muted mb-0">No leads captured yet. Configure your platform settings and webhook URLs to start receiving leads automatically.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Source</th>
                                <th>Status</th>
                                <th>Received</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentLeads as $lead)
                                @php
                                    $sourceLabels = [
                                        'facebook_ads'   => ['Facebook', 'bg-primary'],
                                        'instagram_ads'  => ['Instagram', 'bg-warning text-dark'],
                                        'siksha'         => ['Siksha', 'bg-orange text-white'],
                                        'college_dunia'  => ['CollegeDunia', 'bg-purple text-white'],
                                        'college_dekho'  => ['CollegeDekho', 'bg-success'],
                                    ];
                                    $srcInfo = $sourceLabels[$lead->source] ?? [ucfirst($lead->source), 'bg-secondary'];
                                @endphp
                                <tr>
                                    <td>{{ $lead->name }}</td>
                                    <td>{{ $lead->phone }}</td>
                                    <td>
                                        <span class="badge {{ $srcInfo[1] }}" style="font-size:11px;">{{ $srcInfo[0] }}</span>
                                    </td>
                                    <td><span class="badge bg-secondary" style="font-size:11px;">{{ ucfirst($lead->status) }}</span></td>
                                    <td style="font-size:12px;" class="text-muted">{{ $lead->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection
