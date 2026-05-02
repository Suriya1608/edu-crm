<header class="top-header">
    <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-3">

        <div class="d-flex align-items-center gap-3">
            <button class="mobile-menu-btn" onclick="toggleSidebar()">
                <span class="material-icons">menu</span>
            </button>

            <div>
                <h2 class="page-header-title mb-0" id="pageHeaderTitle">
                    @yield('page_title', 'Dashboard')
                </h2>
                <p class="page-header-subtitle mb-0" style="margin-top:2px;">
                    Welcome back, <strong style="color:var(--text-dark);">{{ auth()->user()->name }}</strong>
                </p>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">

            @if (auth()->check() && auth()->user()->role === 'telecaller' && \App\Models\Setting::get('primary_call_provider') === 'tcn')
                {{-- TCN Ready / Not Ready toggle --}}
                <button id="tcnReadyBtn" type="button"
                    title="Start / Stop calling session"
                    style="display:inline-flex;align-items:center;gap:6px;background:#64748b;color:#fff;
                           border:none;border-radius:8px;padding:6px 14px;font-family:'Plus Jakarta Sans','Manrope',sans-serif;
                           font-size:12.5px;font-weight:600;cursor:pointer;transition:background .2s,box-shadow .2s;
                           line-height:1.4;box-shadow:0 2px 8px rgba(0,0,0,.14);">
                    <span class="material-icons" style="font-size:15px;line-height:1;" id="tcnReadyIco">phone_disabled</span>
                    <span id="tcnReadyLabel">Not Ready</span>
                </button>
            @endif

            @if (auth()->check() && auth()->user()->role === 'telecaller')
                {{-- Notification Bell --}}
                <div class="dropdown">
                    <button class="btn btn-sm position-relative"
                        style="width:36px;height:36px;padding:0;border-radius:9px;background:var(--background-light);border:1px solid var(--border-color);display:flex;align-items:center;justify-content:center;transition:all .15s;"
                        type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                        onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='var(--background-light)'">
                        <span class="material-icons" style="font-size:18px;color:var(--text-muted);">notifications</span>
                        <span id="teleNotifBadge"
                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                            style="display:none;font-size:9px;min-width:16px;height:16px;padding:0 4px;line-height:16px;">0</span>
                    </button>

                    <div class="dropdown-menu dropdown-menu-end p-0 shadow-lg" style="width:360px;max-width:95vw;border-radius:14px;border:1px solid var(--border-color);overflow:hidden;">
                        {{-- Header --}}
                        <div class="p-3 d-flex justify-content-between align-items-center"
                            style="background:var(--background-light);border-bottom:1px solid var(--border-color);">
                            <div>
                                <h6 class="mb-0 fw-bold" style="font-size:14px;">Notifications</h6>
                                <small class="text-muted" style="font-size:11px;">Missed calls, follow-ups & alerts</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 fw-semibold"
                                style="font-size:12px;color:var(--primary-color);"
                                id="teleNotifMarkRead">Mark all read</button>
                        </div>

                        {{-- Sound toggle --}}
                        <div class="px-3 py-2 d-flex align-items-center justify-content-between"
                            style="background:#fff;border-bottom:1px solid var(--border-color);">
                            <small class="fw-semibold" style="font-size:12px;color:var(--text-dark);">
                                <span class="material-icons align-middle" style="font-size:14px;vertical-align:middle;">volume_up</span>
                                Sound alerts
                            </small>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" id="teleNotifSoundToggle" style="cursor:pointer;">
                            </div>
                        </div>

                        <div style="max-height:340px;overflow-y:auto;">
                            <div class="px-3 pt-2 pb-1">
                                <small class="text-uppercase fw-bold" style="font-size:9.5px;letter-spacing:1px;color:var(--text-muted);">Missed Calls</small>
                                <div id="teleNotifMissedCalls" class="mt-1 small text-muted">No missed calls.</div>
                            </div>
                            <div class="px-3 py-1" style="border-top:1px solid var(--border-color);">
                                <small class="text-uppercase fw-bold" style="font-size:9.5px;letter-spacing:1px;color:var(--text-muted);">Follow-up Reminders</small>
                                <div id="teleNotifFollowups" class="mt-1 small text-muted">No reminders.</div>
                            </div>
                            <div class="px-3 py-1" style="border-top:1px solid var(--border-color);">
                                <small class="text-uppercase fw-bold" style="font-size:9.5px;letter-spacing:1px;color:var(--text-muted);">WhatsApp Messages</small>
                                <div id="teleNotifWhatsapp" class="mt-1 small text-muted">No WhatsApp messages.</div>
                            </div>
                            <div class="px-3 py-2" style="border-top:1px solid var(--border-color);">
                                <small class="text-uppercase fw-bold" style="font-size:9.5px;letter-spacing:1px;color:var(--text-muted);">System</small>
                                <div id="teleNotifSystem" class="mt-1 small text-muted">No system notifications.</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if(auth()->check() && auth()->user()->role !== 'admin')
                {{-- Documents Quick Access --}}
                <button class="btn btn-sm position-relative"
                    style="width:36px;height:36px;padding:0;border-radius:9px;background:var(--background-light);border:1px solid var(--border-color);display:flex;align-items:center;justify-content:center;transition:all .15s;"
                    type="button" data-bs-toggle="modal" data-bs-target="#docsModal" title="Documents"
                    onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='var(--background-light)'">
                    <span class="material-icons" style="font-size:18px;color:var(--text-muted);">folder_open</span>
                </button>
            @endif

            @yield('header_actions')
        </div>

    </div>
    @yield('header_actions1')
</header>
