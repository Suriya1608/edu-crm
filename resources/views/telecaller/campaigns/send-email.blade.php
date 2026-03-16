@extends('layouts.app')

@section('page_title', 'Send Email — ' . $contact->name)

@section('content')
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('telecaller.campaigns.contact', [encrypt($campaign->id), encrypt($contact->id)]) }}"
            class="btn btn-sm btn-light">
            <span class="material-icons me-1" style="font-size:16px;">arrow_back</span>Back to Contact
        </a>
        <div>
            <h2 class="page-header-title mb-0">Send Email</h2>
            <p class="page-header-subtitle mb-0">
                {{ $contact->name }}
                @if ($contact->email)
                    &mdash; <span class="text-muted">{{ $contact->email }}</span>
                @else
                    &mdash; <span class="text-danger">No email address</span>
                @endif
            </p>
        </div>
    </div>

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (empty($contact->email))
        <div class="chart-card text-center py-5">
            <span class="material-icons text-muted" style="font-size:48px;opacity:.3;">email</span>
            <p class="mt-2 text-muted">This contact has no email address on record.</p>
            <a href="{{ route('telecaller.campaigns.contact', [encrypt($campaign->id), encrypt($contact->id)]) }}"
                class="btn btn-light btn-sm mt-1">Go Back</a>
        </div>
    @elseif ($templates->isEmpty())
        <div class="chart-card text-center py-5">
            <span class="material-icons text-muted" style="font-size:48px;opacity:.3;">email</span>
            <p class="mt-2 text-muted">No active email templates available. Please contact your manager.</p>
        </div>
    @else
        <div class="row">
            <div class="col-lg-6">
                <div class="chart-card">
                    <h6 class="fw-semibold mb-3">Send Email to {{ $contact->name }}</h6>

                    <form action="{{ route('telecaller.campaigns.contact.send-email.store', [encrypt($campaign->id), encrypt($contact->id)]) }}"
                        method="POST" id="sendForm">
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

                        <div class="alert alert-info py-2" style="font-size:13px;">
                            <span class="material-icons me-1" style="font-size:15px;vertical-align:middle;">info</span>
                            Email will be sent to: <strong>{{ $contact->email }}</strong>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary" id="sendBtn">
                                <span class="material-icons me-1" style="font-size:16px;">send</span>Send Email
                            </button>
                            <a href="{{ route('telecaller.campaigns.contact', [encrypt($campaign->id), encrypt($contact->id)]) }}"
                                class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-6">
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
    function previewTemplate(select) {
        const opt = select.options[select.selectedIndex];
        const box = document.getElementById('previewBox');
        if (!opt.value) {
            box.innerHTML = '<span class="material-icons" style="font-size:36px;opacity:.3;">preview</span><p class="mt-1">Select a template to preview</p>';
            return;
        }
        box.innerHTML =
            '<div class="mb-2"><strong style="font-size:13px;">Subject:</strong> ' +
            '<span style="font-size:13px;">' + opt.dataset.subject + '</span></div>' +
            '<div style="border:1px solid #e2e8f0;border-radius:6px;padding:12px;max-height:380px;overflow:auto;font-size:13px;">' +
            opt.dataset.body + '</div>';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const sel = document.getElementById('templateSelect');
        if (sel && sel.value) previewTemplate(sel);

        const form = document.getElementById('sendForm');
        if (form) {
            form.addEventListener('submit', function () {
                const btn = document.getElementById('sendBtn');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
            });
        }
    });
</script>
@endpush
