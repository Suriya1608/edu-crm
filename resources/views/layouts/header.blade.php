<style>
/* ═══════════════════════════════════════════
   GLASS TOPBAR — override
   ═══════════════════════════════════════════ */
.top-header {
    background: rgba(255,255,255,0.90) !important;
    backdrop-filter: blur(16px) !important;
    -webkit-backdrop-filter: blur(16px) !important;
    border-bottom: 1px solid rgba(0,0,0,0.05) !important;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04) !important;
    padding: 0 28px !important;
    min-height: 68px !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    width: 100% !important;
    z-index: 1041 !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: center !important;
}

/* Push content below the fixed header */
.main-content {
    padding-top: 68px !important;
}

.hdr-inner {
    display: flex;
    align-items: center;
    width: 100%;
    gap: 16px;
}

/* ── Left ─────────────────────────────────── */
.hdr-left {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    flex-shrink: 0;
}
.hdr-title {
    font-size: 20px !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    letter-spacing: -0.3px !important;
    line-height: 1.2 !important;
    margin: 0 !important;
    white-space: nowrap;
}
.hdr-subtitle {
    font-size: 12.5px !important;
    color: #64748b !important;
    margin: 2px 0 0 !important;
    line-height: 1.3 !important;
    white-space: nowrap;
}

/* ── Center search ────────────────────────── */
.hdr-center {
    flex: 1;
    display: flex;
    justify-content: center;
    min-width: 0;
}
.hdr-search-wrap {
    position: relative;
    width: 100%;
    max-width: 360px;
}
.hdr-search-ico {
    position: absolute;
    left: 13px; top: 50%;
    transform: translateY(-50%);
    font-size: 18px !important;
    color: #94a3b8;
    pointer-events: none;
}
.hdr-search-input {
    width: 100%;
    height: 40px;
    padding: 0 16px 0 42px;
    background: #F1F5F9;
    border: 1.5px solid transparent;
    border-radius: 22px;
    font-size: 13px;
    color: #0f172a;
    font-family: 'Poppins', sans-serif;
    outline: none;
    transition: all 0.22s ease;
}
.hdr-search-input::placeholder { color: #94a3b8; }
.hdr-search-input:focus {
    background: #fff;
    border-color: rgba(99,102,241,0.32);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.09);
}

/* ── Right actions ────────────────────────── */
.hdr-right {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}
.hdr-divider {
    width: 1px; height: 22px;
    background: rgba(0,0,0,0.08);
    flex-shrink: 0; margin: 0 4px;
}

/* Icon buttons */
.hdr-icon-btn {
    width: 38px; height: 38px;
    border-radius: 12px; border: none;
    background: transparent;
    display: flex; align-items: center; justify-content: center;
    color: #64748b; cursor: pointer;
    transition: all 0.2s ease; position: relative; flex-shrink: 0;
    padding: 0;
}
.hdr-icon-btn:hover {
    background: rgba(99,102,241,0.08);
    color: #6366f1;
    transform: translateY(-1px);
}
.hdr-icon-btn .material-icons { font-size: 20px !important; }

/* Profile avatar */
.hdr-avatar-btn {
    width: 36px; height: 36px; border-radius: 50%;
    background: linear-gradient(135deg, #312f2f, #312f2f);
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700; color: #fff;
    cursor: pointer; flex-shrink: 0; border: none; padding: 0;
    box-shadow: 0 2px 8px rgba(99,102,241,0.28);
    transition: all 0.2s ease;
    font-family: 'Poppins', sans-serif;
}
.hdr-avatar-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(99,102,241,0.38);
}

/* Profile dropdown */
.hdr-profile-menu {
    min-width: 210px !important;
    border-radius: 14px !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 8px 28px rgba(0,0,0,0.11) !important;
    padding: 6px !important;
    overflow: hidden;
}
.hdr-profile-user {
    padding: 10px 12px 8px;
    border-bottom: 1px solid #f1f5f9;
    margin-bottom: 4px;
}
.hdr-profile-user strong {
    display: block; font-size: 13px; font-weight: 600;
    color: #0f172a; line-height: 1.3;
}
.hdr-profile-user small {
    font-size: 11px; color: #94a3b8;
}
.hdr-profile-link {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px; border-radius: 9px;
    text-decoration: none; font-size: 13px; font-weight: 500;
    color: #334155; transition: background 0.15s;
}
.hdr-profile-link:hover {
    background: rgba(99,102,241,0.07); color: #6366f1;
    text-decoration: none;
}
.hdr-profile-link .material-icons { font-size: 17px !important; color: #6366f1; }
.hdr-profile-logout {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px; border-radius: 9px;
    font-size: 13px; font-weight: 500;
    color: #ef4444; transition: background 0.15s;
    background: transparent; border: none; width: 100%; cursor: pointer;
    font-family: 'Poppins', sans-serif;
}
.hdr-profile-logout:hover { background: rgba(239,68,68,0.07); }
.hdr-profile-logout .material-icons { font-size: 17px !important; color: #ef4444; }

/* TCN button — keep pill look */
#tcnReadyBtn {
    border-radius: 10px !important;
}

/* Mobile */
@media (max-width: 768px) {
    .hdr-center { display: none !important; }
    .top-header { padding: 0 16px !important; }
    .hdr-subtitle { display: none !important; }
}
</style>

<header class="top-header">
    <div class="hdr-inner">

        {{-- ── Left: hamburger + title ──────────── --}}
        <div class="hdr-left">
            <button class="mobile-menu-btn" onclick="toggleSidebar()" title="Toggle menu"
                    style="display:none;width:36px;height:36px;border:none;background:#f1f5f9;border-radius:10px;align-items:center;justify-content:center;color:#0f172a;cursor:pointer;">
                <span class="material-icons" style="font-size:20px;">menu</span>
            </button>

            <div>
                <h2 class="hdr-title" id="pageHeaderTitle">
                    @yield('page_title', 'Dashboard')
                </h2>
                <p class="hdr-subtitle">
                    Welcome back,&nbsp;<strong style="color:#0f172a;font-weight:600;">{{ auth()->user()->name }}</strong>
                </p>
            </div>
        </div>

        {{-- ── Center: global search ────────────── --}}
        <div class="hdr-center">
            <div class="hdr-search-wrap">
                <span class="material-icons hdr-search-ico">search</span>
                <input type="text" class="hdr-search-input"
                       placeholder="Search leads, users..."
                       autocomplete="off"
                       onkeydown="if(event.key==='Enter' && this.value.trim()){window.location='/search?q='+encodeURIComponent(this.value.trim())}">
            </div>
        </div>

        {{-- ── Right: actions ──────────────────── --}}
        <div class="hdr-right">

            {{-- TCN Ready / Not Ready toggle (telecaller + TCN only) --}}
            @if (auth()->check() && auth()->user()->role === 'telecaller' && \App\Models\Setting::get('primary_call_provider') === 'tcn')
                <button id="tcnReadyBtn" type="button" title="Start / Stop calling session"
                    style="display:inline-flex;align-items:center;gap:6px;background:#64748b;color:#fff;
                           border:none;padding:7px 14px;
                           font-family:'Poppins',sans-serif;
                           font-size:12.5px;font-weight:600;cursor:pointer;line-height:1.4;
                           transition:background .2s,box-shadow .2s;
                           box-shadow:0 2px 8px rgba(0,0,0,.12);">
                    <span class="material-icons" style="font-size:15px;line-height:1;" id="tcnReadyIco">phone_disabled</span>
                    <span id="tcnReadyLabel">Not Ready</span>
                </button>
            @endif

            {{-- Page-level injected actions --}}
            @yield('header_actions')

            @if (auth()->check() && (auth()->user()->role === 'telecaller' || auth()->user()->role !== 'admin'))
                <div class="hdr-divider"></div>
            @endif

            {{-- Notification Bell (telecaller only) --}}
            @if (auth()->check() && auth()->user()->role === 'telecaller')
                <div class="dropdown">
                    <button class="hdr-icon-btn position-relative" type="button"
                        data-bs-toggle="dropdown" data-bs-auto-close="outside"
                        aria-label="Notifications">
                        <span class="material-icons">notifications</span>
                        <span id="teleNotifBadge"
                            class="position-absolute badge rounded-pill bg-danger"
                            style="display:none;top:4px;right:4px;font-size:9px;min-width:16px;height:16px;
                                   padding:0 4px;line-height:16px;border:2px solid #fff;">0</span>
                    </button>

                    <div class="dropdown-menu dropdown-menu-end p-0 shadow-lg"
                         style="width:360px;max-width:95vw;border-radius:16px;border:1px solid #e2e8f0;overflow:hidden;">

                        <div class="p-3 d-flex justify-content-between align-items-center"
                             style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                            <div>
                                <h6 class="mb-0 fw-bold" style="font-size:14px;color:#0f172a;">Notifications</h6>
                                <small class="text-muted" style="font-size:11px;">Missed calls, follow-ups &amp; alerts</small>
                            </div>
                            <button type="button" id="teleNotifMarkRead"
                                    class="btn btn-sm btn-link text-decoration-none p-0 fw-semibold"
                                    style="font-size:12px;color:#6366f1;">Mark all read</button>
                        </div>

                        <div class="px-3 py-2 d-flex align-items-center justify-content-between"
                             style="background:#fff;border-bottom:1px solid #e2e8f0;">
                            <small class="fw-semibold" style="font-size:12px;color:#0f172a;">
                                <span class="material-icons align-middle" style="font-size:14px;">volume_up</span>
                                Sound alerts
                            </small>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" id="teleNotifSoundToggle" style="cursor:pointer;">
                            </div>
                        </div>

                        <div style="max-height:340px;overflow-y:auto;">
                            <div class="px-3 pt-2 pb-1">
                                <small class="text-uppercase fw-bold"
                                       style="font-size:9.5px;letter-spacing:1px;color:#94a3b8;">Missed Calls</small>
                                <div id="teleNotifMissedCalls" class="mt-1 small text-muted">No missed calls.</div>
                            </div>
                            <div class="px-3 py-1" style="border-top:1px solid #e2e8f0;">
                                <small class="text-uppercase fw-bold"
                                       style="font-size:9.5px;letter-spacing:1px;color:#94a3b8;">Follow-up Reminders</small>
                                <div id="teleNotifFollowups" class="mt-1 small text-muted">No reminders.</div>
                            </div>
                            <div class="px-3 py-1" style="border-top:1px solid #e2e8f0;">
                                <small class="text-uppercase fw-bold"
                                       style="font-size:9.5px;letter-spacing:1px;color:#94a3b8;">WhatsApp Messages</small>
                                <div id="teleNotifWhatsapp" class="mt-1 small text-muted">No WhatsApp messages.</div>
                            </div>
                            <div class="px-3 py-2" style="border-top:1px solid #e2e8f0;">
                                <small class="text-uppercase fw-bold"
                                       style="font-size:9.5px;letter-spacing:1px;color:#94a3b8;">System</small>
                                <div id="teleNotifSystem" class="mt-1 small text-muted">No system notifications.</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Documents quick access (non-admin) --}}
            @if (auth()->check() && auth()->user()->role !== 'admin')
                <button class="hdr-icon-btn" type="button"
                    data-bs-toggle="modal" data-bs-target="#docsModal"
                    title="Documents" aria-label="Documents">
                    <span class="material-icons">folder_open</span>
                </button>
            @endif

            {{-- Profile avatar + dropdown --}}
            <div class="dropdown">
                <button class="hdr-avatar-btn" type="button"
                    data-bs-toggle="dropdown" data-bs-offset="0,8"
                    aria-label="Profile menu" aria-expanded="false">
                    @php $hdrInitial = strtoupper(substr(auth()->user()->name, 0, 1)); @endphp
                    {{ $hdrInitial }}
                </button>

                <div class="dropdown-menu dropdown-menu-end hdr-profile-menu">
                    <div class="hdr-profile-user">
                        <strong>{{ auth()->user()->name }}</strong>
                        <small>
                            <span class="material-icons" style="font-size:10px;vertical-align:middle;color:#6366f1;">
                                @if(auth()->user()->role === 'admin') admin_panel_settings
                                @elseif(auth()->user()->role === 'manager') manage_accounts
                                @else headset_mic
                                @endif
                            </span>
                            {{ ucfirst(auth()->user()->role) }}
                        </small>
                    </div>

                    <a href="{{ route('password.change') }}" class="hdr-profile-link">
                        <span class="material-icons">lock_reset</span>
                        Change Password
                    </a>

                    <div style="height:1px;background:#f1f5f9;margin:4px 0;"></div>

                    <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                        @csrf
                        <button type="submit" class="hdr-profile-logout">
                            <span class="material-icons">logout</span>
                            Sign out
                        </button>
                    </form>
                </div>
            </div>

        </div>{{-- /hdr-right --}}
    </div>{{-- /hdr-inner --}}

    @yield('header_actions1')
</header>
