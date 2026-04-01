@props([
    'calendarData',   // Collection/array keyed 'YYYY-MM-DD' => count
    'fetchUrl',       // JSON endpoint: GET ?year=&month=
    'todayUrl',       // link when clicking today
    'overdueUrl',     // link when clicking past days
    'upcomingUrl',    // link when clicking future days
    'title' => 'Follow-Up Calendar',
    'uid'   => 'fc',  // unique suffix — set per usage to avoid JS conflicts
])
@php
    $initYear  = now()->year;
    $initMonth = now()->month;
    $todayStr  = now()->toDateString();
@endphp

<div class="chart-card" id="cal-wrap-{{ $uid }}">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h3 class="mb-0">{{ $title }}</h3>
            <p class="text-muted mb-0" style="font-size:12px;">Follow-up density per day — click any highlighted day</p>
        </div>
        <div class="d-flex align-items-center gap-1">
            <button class="btn btn-sm btn-outline-secondary cal-prev px-2" style="line-height:1;" title="Previous month">
                <span class="material-icons" style="font-size:18px;vertical-align:middle;">chevron_left</span>
            </button>
            <span class="cal-month-label fw-semibold px-2" style="min-width:130px;text-align:center;font-size:14px;"></span>
            <button class="btn btn-sm btn-outline-secondary cal-next px-2" style="line-height:1;" title="Next month">
                <span class="material-icons" style="font-size:18px;vertical-align:middle;">chevron_right</span>
            </button>
        </div>
    </div>

    <div class="cal-grid-wrap" style="min-height:220px;"></div>

    <div class="d-flex gap-3 mt-3 flex-wrap" style="font-size:12px;">
        <span class="d-flex align-items-center gap-1">
            <span style="width:12px;height:12px;border-radius:3px;background:#dcfce7;border:1px solid #bbf7d0;display:inline-block;flex-shrink:0;"></span>
            Low (1–3)
        </span>
        <span class="d-flex align-items-center gap-1">
            <span style="width:12px;height:12px;border-radius:3px;background:#fef9c3;border:1px solid #fde68a;display:inline-block;flex-shrink:0;"></span>
            Medium (4–7)
        </span>
        <span class="d-flex align-items-center gap-1">
            <span style="width:12px;height:12px;border-radius:3px;background:#fee2e2;border:1px solid #fecaca;display:inline-block;flex-shrink:0;"></span>
            High (8+)
        </span>
        <span class="d-flex align-items-center gap-1">
            <span style="width:12px;height:12px;border-radius:3px;background:#fff;border:2px solid #137fec;display:inline-block;flex-shrink:0;"></span>
            Today
        </span>
    </div>
</div>

<script>
(function () {
    const ROOT = document.getElementById('cal-wrap-{{ $uid }}');
    if (!ROOT) return;

    const FETCH_URL    = @json($fetchUrl);
    const TODAY_URL    = @json($todayUrl);
    const OVERDUE_URL  = @json($overdueUrl);
    const UPCOMING_URL = @json($upcomingUrl);
    const TODAY_STR    = @json($todayStr);

    const MONTH_NAMES = [
        'January','February','March','April','May','June',
        'July','August','September','October','November','December'
    ];
    const DOW_LABELS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

    let state = {
        year:  {{ $initYear }},
        month: {{ $initMonth }},
        days:  @json((object) $calendarData),
    };

    const todayParts = TODAY_STR.split('-').map(Number); // [Y, M, D]

    function densityStyle(count) {
        if (!count) return null;
        if (count <= 3) return { bg: '#dcfce7', border: '#bbf7d0', color: '#16a34a' };
        if (count <= 7) return { bg: '#fef9c3', border: '#fde68a', color: '#92400e' };
        return { bg: '#fee2e2', border: '#fecaca', color: '#dc2626' };
    }

    function pad(n) { return String(n).padStart(2, '0'); }

    function buildGrid(year, month, dayMap) {
        const wrap  = ROOT.querySelector('.cal-grid-wrap');
        const label = ROOT.querySelector('.cal-month-label');
        label.textContent = MONTH_NAMES[month - 1] + ' ' + year;

        const daysInMonth = new Date(year, month, 0).getDate();
        const firstDow    = new Date(year, month - 1, 1).getDay(); // 0 = Sunday
        const isThisMonth = year === todayParts[0] && month === todayParts[1];

        let html = '<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;">';

        // Day-of-week headers
        DOW_LABELS.forEach(function(d) {
            html += '<div style="text-align:center;font-size:11px;font-weight:600;color:#64748b;padding:3px 0;">' + d + '</div>';
        });

        // Leading empty cells
        for (let i = 0; i < firstDow; i++) {
            html += '<div></div>';
        }

        for (let d = 1; d <= daysInMonth; d++) {
            const key   = year + '-' + pad(month) + '-' + pad(d);
            const count = dayMap[key] || 0;
            const ds    = densityStyle(count);

            const isToday = isThisMonth && d === todayParts[2];
            const cellDate = new Date(year, month - 1, d);
            const todayDate = new Date(todayParts[0], todayParts[1] - 1, todayParts[2]);
            const isPast  = cellDate < todayDate;

            const bg     = ds ? ds.bg     : (isPast ? '#f8fafc' : '#ffffff');
            const border = ds ? ds.border : (isToday ? '#137fec' : '#e2e8f0');
            const numColor = ds ? ds.color : (isPast ? '#94a3b8' : '#334155');
            const ring   = isToday ? ';outline:2px solid #137fec;outline-offset:1px;' : '';
            const cursor = count > 0 ? 'pointer' : 'default';
            const title  = count > 0 ? 'title="' + count + ' follow-up' + (count > 1 ? 's' : '') + '"' : '';

            let clickUrl = '';
            if (count > 0) {
                clickUrl = isToday ? TODAY_URL : (isPast ? OVERDUE_URL : UPCOMING_URL);
            }
            const click = clickUrl ? 'onclick="location.href=\'' + clickUrl + '\'"' : '';

            html += '<div ' + click + ' ' + title + ' style="border-radius:6px;border:1px solid ' + border + ';background:' + bg + ';padding:5px 2px;text-align:center;cursor:' + cursor + ring + '">'
                + '<div style="font-size:12px;font-weight:' + (isToday ? 700 : 500) + ';color:' + numColor + ';">' + d + '</div>'
                + (count > 0 ? '<div style="font-size:11px;font-weight:700;color:' + ds.color + ';line-height:1.2;">' + count + '</div>' : '<div style="height:15px;"></div>')
                + '</div>';
        }

        html += '</div>';
        wrap.innerHTML = html;
    }

    async function navigate(year, month) {
        ROOT.querySelector('.cal-grid-wrap').style.opacity = '0.5';
        try {
            const res  = await fetch(FETCH_URL + '?year=' + year + '&month=' + month, {
                headers: { Accept: 'application/json' }
            });
            const data = await res.json();
            state = data;
            buildGrid(data.year, data.month, data.days || {});
        } catch (e) {}
        ROOT.querySelector('.cal-grid-wrap').style.opacity = '1';
    }

    ROOT.querySelector('.cal-prev').addEventListener('click', function () {
        let { year, month } = state;
        month--;
        if (month < 1) { month = 12; year--; }
        navigate(year, month);
    });

    ROOT.querySelector('.cal-next').addEventListener('click', function () {
        let { year, month } = state;
        month++;
        if (month > 12) { month = 1; year++; }
        navigate(year, month);
    });

    buildGrid(state.year, state.month, state.days || {});
})();
</script>
