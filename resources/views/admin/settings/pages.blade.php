@extends('layouts.app')

@section('page_title', 'Pages Content')

@section('content')
    @include('admin.settings.partials.nav')

    {{-- Tab Navigation --}}
    <div class="d-flex gap-2 mb-4">
        <button class="btn btn-sm {{ !request()->query('tab') || request()->query('tab') === 'privacy' ? 'btn-primary' : 'btn-outline-secondary' }}"
            onclick="switchTab('privacy')">
            <span class="material-icons me-1" style="font-size:15px;vertical-align:middle;">shield</span>
            Privacy Policy
        </button>
        <button class="btn btn-sm {{ request()->query('tab') === 'terms' ? 'btn-primary' : 'btn-outline-secondary' }}"
            onclick="switchTab('terms')">
            <span class="material-icons me-1" style="font-size:15px;vertical-align:middle;">gavel</span>
            Terms of Service
        </button>
    </div>

    {{-- Privacy Policy Panel --}}
    <div id="panelPrivacy" class="chart-card" style="{{ request()->query('tab') === 'terms' ? 'display:none;' : '' }}">
        <div class="chart-header mb-3">
            <div>
                <h3>Privacy Policy</h3>
                <p>This content will appear on the public <strong>/privacy-policy</strong> page. You can use HTML tags for formatting.</p>
            </div>
            <a href="{{ url('/privacy-policy') }}" target="_blank" class="btn btn-sm btn-outline-primary">
                <span class="material-icons me-1" style="font-size:14px;vertical-align:middle;">open_in_new</span>
                Preview
            </a>
        </div>

        <form method="POST" action="{{ route('admin.settings.pages.update') }}">
            @csrf
            <input type="hidden" name="page" value="privacy_policy">

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label fw-semibold mb-0">Content</label>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary" onclick="formatText('bold')"><b>B</b></button>
                        <button type="button" class="btn btn-outline-secondary" onclick="formatText('heading', 'privacy_content')"><span style="font-size:11px;">H2</span></button>
                        <button type="button" class="btn btn-outline-secondary" onclick="insertHtml('privacy_content', '<ul>\n  <li></li>\n</ul>')"><span class="material-icons" style="font-size:14px;">format_list_bulleted</span></button>
                        <button type="button" class="btn btn-outline-secondary" onclick="insertHtml('privacy_content', '<hr>')"><span style="font-size:11px;">HR</span></button>
                    </div>
                </div>
                <textarea id="privacy_content" name="content" class="form-control font-monospace"
                    rows="22" placeholder="Enter Privacy Policy HTML content..."
                    style="font-size:13px; line-height:1.6;">{{ $privacyContent }}</textarea>
                <small class="text-muted">Supports HTML. Use &lt;h2&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;strong&gt; etc.</small>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <span class="material-icons me-1" style="font-size:18px;">save</span>
                    Save Privacy Policy
                </button>
                <a href="{{ url('/privacy-policy') }}" target="_blank" class="btn btn-outline-secondary">
                    <span class="material-icons me-1" style="font-size:18px;">open_in_new</span>
                    View Live Page
                </a>
            </div>
        </form>
    </div>

    {{-- Terms of Service Panel --}}
    <div id="panelTerms" class="chart-card" style="{{ request()->query('tab') !== 'terms' ? 'display:none;' : '' }}">
        <div class="chart-header mb-3">
            <div>
                <h3>Terms of Service</h3>
                <p>This content will appear on the public <strong>/terms-of-service</strong> page. You can use HTML tags for formatting.</p>
            </div>
            <a href="{{ url('/terms-of-service') }}" target="_blank" class="btn btn-sm btn-outline-primary">
                <span class="material-icons me-1" style="font-size:14px;vertical-align:middle;">open_in_new</span>
                Preview
            </a>
        </div>

        <form method="POST" action="{{ route('admin.settings.pages.update') }}">
            @csrf
            <input type="hidden" name="page" value="terms_of_service">

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label fw-semibold mb-0">Content</label>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary" onclick="formatText('bold')"><b>B</b></button>
                        <button type="button" class="btn btn-outline-secondary" onclick="formatText('heading', 'terms_content')"><span style="font-size:11px;">H2</span></button>
                        <button type="button" class="btn btn-outline-secondary" onclick="insertHtml('terms_content', '<ul>\n  <li></li>\n</ul>')"><span class="material-icons" style="font-size:14px;">format_list_bulleted</span></button>
                        <button type="button" class="btn btn-outline-secondary" onclick="insertHtml('terms_content', '<hr>')"><span style="font-size:11px;">HR</span></button>
                    </div>
                </div>
                <textarea id="terms_content" name="content" class="form-control font-monospace"
                    rows="22" placeholder="Enter Terms of Service HTML content..."
                    style="font-size:13px; line-height:1.6;">{{ $termsContent }}</textarea>
                <small class="text-muted">Supports HTML. Use &lt;h2&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;strong&gt; etc.</small>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <span class="material-icons me-1" style="font-size:18px;">save</span>
                    Save Terms of Service
                </button>
                <a href="{{ url('/terms-of-service') }}" target="_blank" class="btn btn-outline-secondary">
                    <span class="material-icons me-1" style="font-size:18px;">open_in_new</span>
                    View Live Page
                </a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    function switchTab(tab) {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.location.href = url.toString();
    }

    function insertHtml(textareaId, html) {
        const ta = document.getElementById(textareaId);
        if (!ta) return;
        const start = ta.selectionStart;
        const end   = ta.selectionEnd;
        ta.value = ta.value.substring(0, start) + html + ta.value.substring(end);
        ta.selectionStart = ta.selectionEnd = start + html.length;
        ta.focus();
    }

    function formatText(type, textareaId) {
        const activeTA = textareaId
            ? document.getElementById(textareaId)
            : (document.getElementById('privacy_content').closest('.chart-card').style.display !== 'none'
                ? document.getElementById('privacy_content')
                : document.getElementById('terms_content'));
        if (!activeTA) return;

        const start  = activeTA.selectionStart;
        const end    = activeTA.selectionEnd;
        const sel    = activeTA.value.substring(start, end);

        let wrapped;
        if (type === 'bold') {
            wrapped = '<strong>' + (sel || 'Bold text') + '</strong>';
        } else if (type === 'heading') {
            wrapped = '<h2>' + (sel || 'Section Heading') + '</h2>';
        } else {
            wrapped = sel;
        }

        activeTA.value = activeTA.value.substring(0, start) + wrapped + activeTA.value.substring(end);
        activeTA.selectionStart = start;
        activeTA.selectionEnd   = start + wrapped.length;
        activeTA.focus();
    }
</script>
@endpush
