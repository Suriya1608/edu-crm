<header class="top-header">
    @php
        $hasNotificationsTable = \Illuminate\Support\Facades\Schema::hasTable('notifications');
        $isManager = auth()->check() && auth()->user()->role === 'manager';
    @endphp

    <div class="d-flex justify-content-between align-items-start w-100 flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3 flex-grow-1">
            <button class="mobile-menu-btn" onclick="toggleSidebar()">
                <span class="material-icons">menu</span>
            </button>

            <div>
                <h2 class="page-header-title mb-1">
                    @yield('page_title', 'Manager Dashboard')
                </h2>
                <p class="page-header-subtitle mb-0">
                    Welcome back, {{ auth()->user()->name }}
                </p>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            @if ($isManager && $hasNotificationsTable)
                <div class="dropdown me-1">
                    <button class="btn btn-sm btn-light position-relative" type="button" data-bs-toggle="dropdown"
                        data-bs-auto-close="outside" id="managerNotifToggle">
                        <span class="material-icons" style="font-size:18px;">notifications</span>
                        <span id="managerNotifBadge"
                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                            style="display:none;">0</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0" style="width:360px; max-width:95vw;">
                        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Notifications</h6>
                                <small class="text-muted">WhatsApp messages and system alerts</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0"
                                id="managerNotifMarkRead">Mark read</button>
                        </div>

                        <div class="p-2 border-bottom">
                            <small class="text-uppercase text-muted fw-semibold">WhatsApp Messages</small>
                            <div id="managerNotifWhatsapp" class="mt-1 small text-muted">No WhatsApp messages.</div>
                        </div>

                        <div class="p-2">
                            <small class="text-uppercase text-muted fw-semibold">System Notifications</small>
                            <div id="managerNotifSystem" class="mt-1 small text-muted">No system notifications.</div>
                        </div>
                    </div>
                </div>
            @endif

            @yield('header_actions')
        </div>
    </div>
    @yield('header_actions1')
</header>
