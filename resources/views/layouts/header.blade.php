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
                    style="display:inline-flex;align-items:center;gap:6px;background:#475569;color:#fff;
                           border:none;border-radius:22px;padding:0 16px 0 10px;height:36px;
                           font-size:11.5px;font-weight:600;cursor:pointer;
                           transition:background .25s,box-shadow .25s;
                           box-shadow:0 1px 6px rgba(0,0,0,.18);letter-spacing:0.3px;flex-shrink:0;">
                    <span id="tcnStatusDot" style="width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.35);flex-shrink:0;transition:background .25s;"></span>
                    <span class="material-icons" style="font-size:14px;line-height:1;" id="tcnReadyIco">phone_disabled</span>
                    <span id="tcnReadyLabel">Not Ready</span>
                </button>
            @endif

            @if (auth()->check() && auth()->user()->role === 'manager')
                {{-- Manager Notification Bell --}}
                <div class="dropdown">
                    <button class="btn btn-sm position-relative"
                        style="width:36px;height:36px;padding:0;border-radius:9px;background:var(--background-light);border:1px solid var(--border-color);display:flex;align-items:center;justify-content:center;transition:all .15s;"
                        type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                        onclick="if(window.mgrFetchNotifs) window.mgrFetchNotifs();"
                        onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='var(--background-light)'"
                        title="Notifications">
                        <span class="material-icons" style="font-size:18px;color:var(--text-muted);">notifications</span>
                        <span id="mgrNotifBadge"
                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                            style="display:none;font-size:9px;min-width:16px;height:16px;padding:0 4px;line-height:16px;">0</span>
                    </button>

                    <div class="dropdown-menu dropdown-menu-end p-0 shadow-lg" style="width:370px;max-width:95vw;border-radius:14px;border:1px solid var(--border-color);overflow:hidden;">
                        {{-- Header --}}
                        <div class="p-3 d-flex justify-content-between align-items-center"
                            style="background:var(--background-light);border-bottom:1px solid var(--border-color);">
                            <div>
                                <h6 class="mb-0 fw-bold" style="font-size:14px;">Notifications</h6>
                                <small class="text-muted" style="font-size:11px;">Leads, follow-ups & escalations</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 fw-semibold"
                                style="font-size:12px;color:var(--primary-color);"
                                id="mgrNotifMarkRead">Mark all read</button>
                        </div>

                        <div style="max-height:400px;overflow-y:auto;">
                            <div class="px-3 pt-2 pb-2">
                                <small class="text-uppercase fw-bold d-flex align-items-center gap-1"
                                    style="font-size:9.5px;letter-spacing:1px;color:var(--text-muted);">
                                    <span class="material-icons" style="font-size:12px;color:#6366f1;">person_add</span>Lead Assignments
                                </small>
                                <div id="mgrNotifLeads" class="mt-1 small text-muted">No lead assignments.</div>
                            </div>
                            <div class="px-3 py-2" style="border-top:1px solid var(--border-color);">
                                <small class="text-uppercase fw-bold d-flex align-items-center gap-1"
                                    style="font-size:9.5px;letter-spacing:1px;color:var(--text-muted);">
                                    <span class="material-icons" style="font-size:12px;color:#f59e0b;">event</span>Follow-ups
                                </small>
                                <div id="mgrNotifFollowups" class="mt-1 small text-muted">No follow-up alerts.</div>
                            </div>
                            <div class="px-3 py-2" style="border-top:1px solid var(--border-color);">
                                <small class="text-uppercase fw-bold d-flex align-items-center gap-1"
                                    style="font-size:9.5px;letter-spacing:1px;color:var(--text-muted);">
                                    <span class="material-icons" style="font-size:12px;color:#ef4444;">warning</span>SLA Escalations
                                </small>
                                <div id="mgrNotifSla" class="mt-1 small text-muted">No SLA alerts.</div>
                            </div>
                            <div class="px-3 py-2" style="border-top:1px solid var(--border-color);">
                                <small class="text-uppercase fw-bold d-flex align-items-center gap-1"
                                    style="font-size:9.5px;letter-spacing:1px;color:var(--text-muted);">
                                    <span class="material-icons" style="font-size:12px;color:#25D366;">chat</span>WhatsApp
                                </small>
                                <div id="mgrNotifWhatsapp" class="mt-1 small text-muted">No WhatsApp messages.</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if (auth()->check() && auth()->user()->role === 'telecaller')
                {{-- Live clock pill --}}
                <span class="tc-live-clock" id="tcLiveClock">
                    <span class="material-icons">schedule</span>
                    <span id="tcClockTime">--:--:-- --</span>
                </span>

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

            @if(auth()->check() && auth()->user()->role === 'telecaller')
                <div style="width:1px;height:22px;background:var(--border-color);flex-shrink:0;"></div>
                @php $hdrInitials = strtoupper(substr(auth()->user()->name, 0, 1)); @endphp
                <div class="dropdown">
                    <button type="button"
                        data-bs-toggle="dropdown"
                        data-bs-auto-close="outside"
                        title="{{ auth()->user()->name }}"
                        style="width:36px;height:36px;padding:0;border:none;border-radius:10px;background:linear-gradient(135deg,#FF5C1A,#FF8042);color:#fff;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(255,92,26,.35);flex-shrink:0;letter-spacing:0;">
                        {{ $hdrInitials }}
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0 shadow-lg"
                        style="width:230px;border-radius:14px;border:1px solid var(--border-color);overflow:hidden;">
                        {{-- User info --}}
                        <div class="p-3 d-flex align-items-center gap-3"
                            style="background:linear-gradient(135deg,#FF5C1A,#FF8042);">
                            <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,.2);color:#fff;font-size:16px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                {{ $hdrInitials }}
                            </div>
                            <div style="overflow:hidden;min-width:0;">
                                <div style="color:#fff;font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    {{ auth()->user()->name }}
                                </div>
                                <div style="color:rgba(255,255,255,.75);font-size:11px;display:flex;align-items:center;gap:3px;margin-top:2px;">
                                    <span class="material-icons" style="font-size:11px;">headset_mic</span>
                                    Telecaller
                                </div>
                            </div>
                        </div>
                        {{-- Actions --}}
                        <div class="py-1" style="background:#fff;">
                            <a href="{{ route('password.change') }}"
                                class="dropdown-item d-flex align-items-center gap-2 py-2 px-3"
                                style="font-size:13px;color:#0f172a;font-weight:500;">
                                <span class="material-icons" style="font-size:17px;color:#FF5C1A;">lock_reset</span>
                                Change Password
                            </a>
                        </div>
                        <div style="border-top:1px solid var(--border-color);" class="py-1 bg-white">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                    class="dropdown-item d-flex align-items-center gap-2 py-2 px-3"
                                    style="font-size:13px;color:#ef4444;font-weight:500;background:transparent;border:none;width:100%;text-align:left;">
                                    <span class="material-icons" style="font-size:17px;color:#ef4444;">logout</span>
                                    Sign Out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>

    </div>
    @yield('header_actions1')
</header>
