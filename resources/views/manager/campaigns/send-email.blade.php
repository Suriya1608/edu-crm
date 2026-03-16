@extends('layouts.manager.app')

@section('page_title', 'Send Campaign Email — ' . $campaign->name)

@section('content')
    {{-- Sub-nav --}}
    <div class="lead-profile-nav mb-3">
        <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('manager.campaigns.show', encrypt($campaign->id)) }}" class="btn btn-sm btn-light">
                    <span class="material-icons me-1" style="font-size:16px;">arrow_back</span>Back to Campaign
                </a>
                <div>
                    <h2 class="page-header-title mb-0">Send Campaign Email</h2>
                    <p class="page-header-subtitle mb-0">{{ $campaign->name }}</p>
                </div>
            </div>
            <a href="{{ route('manager.campaigns.email-history', encrypt($campaign->id)) }}"
                class="btn btn-sm btn-outline-primary">
                <span class="material-icons me-1" style="font-size:15px;">history</span>Email History
            </a>
        </div>
    </div>

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($templates->isEmpty())
        <div class="chart-card text-center py-5">
            <span class="material-icons text-muted" style="font-size:48px;opacity:.3;">email</span>
            <p class="mt-2 text-muted">No active email templates found.</p>
            <p class="text-muted" style="font-size:13px;">Ask your admin to create an active email template before sending.</p>
        </div>
    @else
        <div class="row">
            <div class="col-lg-7">
                <div class="chart-card">
                    <h6 class="fw-semibold mb-3">Configure Email Blast</h6>

                    <form action="{{ route('manager.campaigns.send-email.store', encrypt($campaign->id)) }}"
                        method="POST" id="sendEmailForm">
                        @csrf

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Select Template <span class="text-danger">*</span></label>
                            <select name="template_id" id="templateSelect"
                                class="form-select @error('template_id') is-invalid @enderror"
                                onchange="previewTemplate(this)">
                                <option value="">— Choose a template —</option>
                                @foreach ($templates as $tpl)
                                    <option value="{{ $tpl->id }}"
                                        data-subject="{{ $tpl->subject }}"
                                        data-body="{{ htmlspecialchars($tpl->body, ENT_QUOTES) }}"
                                        {{ old('template_id') == $tpl->id ? 'selected' : '' }}>
                                        {{ $tpl->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('template_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Send To</label>
                            <select name="contact_status" class="form-select" id="statusFilter"
                                onchange="updateCount(this.value)">
                                <option value="all">All contacts with email</option>
                                <option value="pending">Pending</option>
                                <option value="called">Called</option>
                                <option value="interested">Interested</option>
                                <option value="not_interested">Not Interested</option>
                                <option value="no_answer">No Answer</option>
                                <option value="callback">Callback</option>
                                <option value="converted">Converted</option>
                            </select>
                            <div class="form-text" id="recipientCountText">
                                Loading recipient count...
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary" id="sendBtn">
                                <span class="material-icons me-1" style="font-size:16px;">send</span>Send Email
                            </button>
                            <a href="{{ route('manager.campaigns.show', encrypt($campaign->id)) }}"
                                class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="chart-card">
                    <h6 class="fw-semibold mb-3">Template Preview</h6>
                    <div id="previewBox" class="text-muted text-center py-4" style="font-size:13px;">
                        <span class="material-icons" style="font-size:36px;opacity:.3;">preview</span>
                        <p class="mt-1">Select a template to preview</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
    @php
        $contactStatusCounts = $campaign->contacts()
            ->whereNotNull('email')->where('email', '!=', '')
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();
    @endphp
    const contactData = @json($contactStatusCounts);

    function getTotalByStatus(status) {
        if (status === 'all') {
            return Object.values(contactData).reduce((a, b) => a + b, 0);
        }
        return contactData[status] || 0;
    }

    function updateCount(status) {
        const count = getTotalByStatus(status);
        document.getElementById('recipientCountText').textContent =
            count + ' contact' + (count !== 1 ? 's' : '') + ' with email will receive this message.';
    }

    function previewTemplate(select) {
        const opt = select.options[select.selectedIndex];
        const box = document.getElementById('previewBox');
        if (!opt.value) {
            box.innerHTML = '<span class="material-icons" style="font-size:36px;opacity:.3;">preview</span><p class="mt-1">Select a template to preview</p>';
            return;
        }
        const subject = opt.dataset.subject;
        const body    = opt.dataset.body;
        box.innerHTML =
            '<div class="mb-2"><strong style="font-size:13px;">Subject:</strong> ' +
            '<span style="font-size:13px;">' + subject + '</span></div>' +
            '<div style="border:1px solid #e2e8f0;border-radius:6px;padding:12px;max-height:380px;overflow:auto;font-size:13px;">' +
            body + '</div>';
    }

    document.addEventListener('DOMContentLoaded', function () {
        updateCount('all');
        const sel = document.getElementById('templateSelect');
        if (sel.value) previewTemplate(sel);

        document.getElementById('sendEmailForm').addEventListener('submit', function () {
            const btn = document.getElementById('sendBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
        });
    });
</script>
@endpush
