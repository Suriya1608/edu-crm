@extends('layouts.app')
@section('page_title', 'Admin Dashboard')

@section('content')
<style>
/* ══════════════════════════════════════════
   PREMIUM DASHBOARD — Admin
   3-Color Palette: Slate/Charcoal · Green · Blue
   ══════════════════════════════════════════ */
.dash-wrap {
    padding: 28px 32px 48px;
    min-height: calc(100vh - 68px);
    font-family: 'Poppins', sans-serif;
}

/* ── Section headers ────────────────── */
.d-section-title {
    font-size: 16px; font-weight: 700; color: #0f172a;
    margin: 0 0 4px; letter-spacing: -0.2px;
}
.d-section-sub {
    font-size: 12.5px; color: #94a3b8; margin: 0 0 20px;
}

/* ── Glass cards ────────────────────── */
.d-card {
    border-radius: 22px;
    padding: 22px 20px 18px;
    position: relative; overflow: hidden;
    box-shadow: 0 8px 24px rgba(0,0,0,0.05);
    transition: transform 0.22s ease, box-shadow 0.22s ease;
    border: 1px solid rgba(255,255,255,0.9);
    height: 100%;
}
.d-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 36px rgba(0,0,0,0.09);
}
.d-card::before {
    content: ''; position: absolute; top: -36px; right: -36px;
    width: 110px; height: 110px; border-radius: 50%;
    background: rgba(255,255,255,0.12);
}
.d-card::after {
    content: ''; position: absolute; bottom: -28px; left: -18px;
    width: 80px; height: 80px; border-radius: 50%;
    background: rgba(255,255,255,0.07);
}

/* ── 3-color card variants ── */
/* Blue family */
.d-card-blue {
    background: #f5c9cd;
    /* box-shadow: 0 10px 28px rgba(30,64,175,0.38); */
}
/* .d-card-blue:hover { box-shadow: 0 20px 42px rgba(30,64,175,0.50); } */

/* Green family — 3 shades */
.d-card-green {
    background: #acc7a9;
    /* box-shadow: 0 10px 28px rgba(20,83,45,0.38); */
}
/* .d-card-green:hover { box-shadow: 0 20px 42px rgba(20,83,45,0.50); } */

.d-card-green-mid {
    background: #acc7a9;
    /* box-shadow: 0 10px 28px rgba(22,101,52,0.35); */
}
/* .d-card-green-mid:hover { box-shadow: 0 20px 42px rgba(22,101,52,0.48); } */

.d-card-green-light {
    background: #acc7a9;
    /* box-shadow: 0 10px 28px rgba(21,128,61,0.32); */
}
.d-card-green-light:hover { box-shadow: 0 20px 42px rgba(21,128,61,0.44); }

/* Slate/Charcoal family — 2 shades */
.d-card-slate {
    background: #bdc0db;
    /* box-shadow: 0 10px 28px rgba(30,41,59,0.40); */
}
/* .d-card-slate:hover { box-shadow: 0 20px 42px rgba(30,41,59,0.54); } */

.d-card-charcoal {
    background: linear-gradient(135deg, #0f172a, #334155);
    /* box-shadow: 0 10px 28px rgba(15,23,42,0.42); */
}
.d-card-charcoal:hover { box-shadow: 0 20px 42px rgba(15,23,42,0.55); }

/* Blue light variant */
.d-card-blue-light {
  background: #f5c9cd;
  /* color:#000; */
    /* box-shadow: 0 10px 28px rgba(29,78,216,0.32); */
}
/* .d-card-blue-light:hover { box-shadow: 0 20px 42px rgba(29,78,216,0.44); } */

/* Shared white icon/text rules for all colored cards */
.d-card-blue .d-card-icon,
.d-card-green .d-card-icon,
.d-card-green-mid .d-card-icon,
.d-card-green-light .d-card-icon,
.d-card-slate .d-card-icon,
.d-card-charcoal .d-card-icon,
.d-card-blue-light .d-card-icon {
    background: rgba(255,255,255,0.22);
}
.d-card-blue .d-card-icon .material-icons,
.d-card-green .d-card-icon .material-icons,
.d-card-green-mid .d-card-icon .material-icons,
.d-card-green-light .d-card-icon .material-icons,
.d-card-slate .d-card-icon .material-icons,
.d-card-charcoal .d-card-icon .material-icons,
.d-card-blue-light .d-card-icon .material-icons { color: #ffffff; }

.d-card-blue .d-card-value,
.d-card-green .d-card-value,
.d-card-green-mid .d-card-value,
.d-card-green-light .d-card-value,
.d-card-slate .d-card-value,
.d-card-charcoal .d-card-value,
.d-card-blue-light .d-card-value { color: #fff !important; }

.d-card-blue .d-card-label,
.d-card-green .d-card-label,
.d-card-green-mid .d-card-label,
.d-card-green-light .d-card-label,
.d-card-slate .d-card-label,
.d-card-charcoal .d-card-label,
.d-card-blue-light .d-card-label { color: rgba(255,255,255,0.78) !important; }

.d-card-blue .d-card-trend,
.d-card-green .d-card-trend,
.d-card-green-mid .d-card-trend,
.d-card-green-light .d-card-trend,
.d-card-slate .d-card-trend,
.d-card-charcoal .d-card-trend,
.d-card-blue-light .d-card-trend { color: rgba(255,255,255,0.88) !important; }

.d-card-blue .d-bar-track,
.d-card-green .d-bar-track,
.d-card-green-mid .d-bar-track,
.d-card-green-light .d-bar-track,
.d-card-slate .d-bar-track,
.d-card-charcoal .d-bar-track,
.d-card-blue-light .d-bar-track { background: rgba(255,255,255,0.22); }

.d-card-blue .d-bar-fill,
.d-card-green .d-bar-fill,
.d-card-green-mid .d-bar-fill,
.d-card-green-light .d-bar-fill,
.d-card-slate .d-bar-fill,
.d-card-charcoal .d-bar-fill,
.d-card-blue-light .d-bar-fill { background: rgba(255,255,255,0.65); }

.d-card-icon {
    width: 44px; height: 44px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 14px; flex-shrink: 0;
}
.d-card-icon .material-icons { font-size: 22px; }

.d-card-value {
    font-size: 30px; font-weight: 800; color: #0f172a;
    line-height: 1; margin-bottom: 4px; letter-spacing: -0.5px;
}
.d-card-label {
    font-size: 12.5px; color: #64748b; font-weight: 500; margin-bottom: 14px;
}
.d-bar-track {
    height: 5px; background: rgba(0,0,0,0.07);
    border-radius: 10px; overflow: hidden;
}
.d-bar-fill {
    height: 100%; border-radius: 10px;
    transition: width 0.8s cubic-bezier(0.4,0,0.2,1);
}
.d-card-trend {
    font-size: 11px; font-weight: 600; margin-top: 8px;
    display: flex; align-items: center; gap: 4px;
}
.d-trend-up   { color: #16a34a; }
.d-trend-down { color: #1e40af; }
.d-trend-neut { color: #94a3b8; }

/* ── Glass panel ─── */
.d-panel {
    background: rgba(255,255,255,0.82);
    backdrop-filter: blur(12px);
    border-radius: 22px;
    border: 1px solid rgba(255,255,255,0.9);
    box-shadow: 0 8px 24px rgba(0,0,0,0.05);
    padding: 24px;
    height: 100%;
}
.d-panel-head { margin-bottom: 20px; }
.d-panel-head h3 {
    font-size: 15px; font-weight: 700; color: #0f172a;
    margin: 0 0 3px; letter-spacing: -0.2px;
}
.d-panel-head p { font-size: 12px; color: #94a3b8; margin: 0; }

/* ── Table ── */
.d-tbl { width: 100%; border-collapse: separate; border-spacing: 0; }
.d-tbl thead th {
    font-size: 10.5px; font-weight: 700; letter-spacing: 0.9px;
    text-transform: uppercase; color: #94a3b8;
    padding: 12px 16px; background: #F8FAFC;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}
.d-tbl thead th:first-child { border-radius: 12px 0 0 0; }
.d-tbl thead th:last-child  { border-radius: 0 12px 0 0; }
.d-tbl tbody td {
    padding: 13px 16px; font-size: 13px; color: #334155;
    border-bottom: 1px solid rgba(0,0,0,0.04);
}
.d-tbl tbody tr:last-child td { border-bottom: none; }
.d-tbl tbody tr:hover td { background: rgba(30,64,175,0.03); }

/* status badges — blue / slate tones only */
.d-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 20px;
    font-size: 11px; font-weight: 600; white-space: nowrap;
}
.d-badge-green  { background: #dcfce7; color: #14532d; }
.d-badge-yellow { background: #dbeafe; color: #1e3a8a; }
.d-badge-red    { background: #e2e8f0; color: #1e293b; }
.d-badge-blue   { background: #dbeafe; color: #1d4ed8; }

/* progress bar */
.d-prog { height: 7px; background: #e2e8f0; border-radius: 10px; overflow: hidden; min-width: 80px; }
.d-prog-fill { height: 100%; border-radius: 10px; background: linear-gradient(90deg,#1e40af,#3b82f6); }

/* ── Quick Actions ── */
.d-action {
    display: flex; flex-direction: column; align-items: center;
    gap: 10px; padding: 20px 12px; border-radius: 18px;
    text-decoration: none; transition: all 0.22s ease;
    border: 1.5px solid transparent; background: rgba(255,255,255,0.6);
    cursor: pointer; text-align: center;
}
.d-action:hover {
    transform: translateY(-3px);
    border-color: rgba(30,64,175,0.2);
    box-shadow: 0 8px 20px rgba(30,64,175,0.12);
    text-decoration: none;
    background: rgba(255,255,255,0.95);
}
.d-action-ico {
    width: 46px; height: 46px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
}
.d-action-ico .material-icons { font-size: 22px; }
.d-action span { font-size: 12px; font-weight: 600; color: #0f172a; line-height: 1.3; }

/* ── Pipeline funnel ── */
.d-funnel-row {
    display: flex; align-items: center; gap: 14px; margin-bottom: 14px;
}
.d-funnel-label { font-size: 12.5px; font-weight: 600; color: #0f172a; min-width: 90px; }
.d-funnel-track { flex: 1; height: 32px; background: rgba(0,0,0,0.05); border-radius: 10px; overflow: hidden; }
.d-funnel-fill  { height: 100%; border-radius: 10px; display: flex; align-items: center; justify-content: flex-end; padding-right: 10px; }
.d-funnel-fill span { font-size: 12px; font-weight: 700; color: #fff; }
.d-funnel-count { font-size: 13px; font-weight: 700; color: #0f172a; min-width: 40px; text-align: right; }

/* ── Activity timeline ── */
.d-activity { display: flex; flex-direction: column; gap: 0; }
.d-activity-item {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.05);
    position: relative;
}
.d-activity-item:last-child { border-bottom: none; }
.d-activity-dot {
    width: 34px; height: 34px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; margin-top: 2px;
}
.d-activity-dot .material-icons { font-size: 16px; }
.d-activity-body { flex: 1; min-width: 0; }
.d-activity-body p { font-size: 13px; color: #334155; margin: 0 0 2px; line-height: 1.4; }
.d-activity-body small { font-size: 11px; color: #94a3b8; }

/* ── Team cards ── */
.d-team-card {
    background: rgba(255,255,255,0.65);
    border-radius: 16px; padding: 16px;
    border: 1px solid rgba(255,255,255,0.9);
    transition: all 0.22s ease; text-align: center;
}
.d-team-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.07); }

/* ── Insight banner — Charcoal ── */
.d-insight-strip {
    /* background: linear-gradient(135deg, #2e3748, #1e293b); */
    background: linear-gradient(135deg, #666c7a, #666c7a);
    border-radius: 22px; padding: 28px 32px;
    display: flex; align-items: center; justify-content: space-between;
    gap: 24px; flex-wrap: wrap;
    box-shadow: 0 12px 32px rgba(15,23,42,0.32);
    overflow: hidden; position: relative;
}
.d-card-blue .d-card-icon{
    background: #e1999f;
}
.d-card-blue .d-card-value{
    color: #cd767e !important;
}
.d-card-blue .d-card-label{
        color: #cd767e !important;

}
.d-card-blue .d-card-trend{
    color: #cd767e !important;
}

.d-card-green .d-card-icon{
    background: #5c8557;
}
.d-card-green .d-card-value{
    color: #5c8557 !important;
}
.d-card-green .d-card-label{
        color: #5c8557 !important;
}
.d-card-green .d-card-trend{
    color: #5c8557 !important;
}
.d-card-slate .d-card-icon{
    background:rgb(145 148 177);
}
.d-card-slate .d-card-value{
    color: rgb(145 148 177) !important;
}
.d-card-slate .d-card-label{
        color: rgb(74, 78, 113) !important;
}
.d-card-slate .d-card-trend{
    color: rgb(145 148 177) !important;
}
.d-insight-strip::before {
    content: ''; position: absolute; right: -60px; top: -60px;
    width: 220px; height: 220px; border-radius: 50%;
    background: rgba(255,255,255,0.05);
}
.d-insight-strip::after {
    content: ''; position: absolute; right: 80px; bottom: -80px;
    width: 200px; height: 200px; border-radius: 50%;
    background: rgba(255,255,255,0.03);
}
.d-insight-kpi { text-align: center; }
.d-insight-kpi strong { display: block; font-size: 28px; font-weight: 800; color: #fff; }
.d-insight-kpi span { font-size: 12px; color: rgba(255,255,255,0.72); font-weight: 500; }
.d-insight-divider { width: 1px; height: 50px; background: rgba(255,255,255,0.2); }

/* ── Metric pill row ── */
.d-metric-pill {
    display: flex; align-items: center; gap: 10px;
    background: rgba(255,255,255,0.72); border-radius: 14px;
    padding: 14px 16px; border: 1px solid rgba(255,255,255,0.9);
}
.d-metric-pill-ico {
    width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
}
.d-metric-pill-ico .material-icons { font-size: 18px; }
.d-metric-pill strong { font-size: 18px; font-weight: 800; color: #0f172a; display: block; line-height: 1; }
.d-metric-pill span   { font-size: 11px; color: #94a3b8; font-weight: 500; }

/* Custom chart panel — dark charcoal */
.cus-chart-panel {
    background: linear-gradient(135deg, #666c7a, #6e7381) !important
}
.cus-chart-panel h3 { color:#fff !important; }

.dashboard-content {
    padding: 32px 32px 16px;
    background: #e1e5ed;
}

/* Glass card */
.glass-card {
    background: rgba(255, 255, 255, 0.33);
    backdrop-filter: blur(42px);
    -webkit-backdrop-filter: blur(42px);
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow:
        0 8px 32px rgba(0, 0, 0, 0.1),
        inset 0 1px 0 rgba(255, 255, 255, 0.5),
        inset 0 -1px 0 rgba(255, 255, 255, 0.1);
    position: relative;
    overflow: hidden;
}
.glass-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.8), transparent);
}
.glass-card::after {
    content: '';
    position: absolute; top: 0; left: 0; width: 1px; height: 100%;
    background: linear-gradient(180deg, rgba(255,255,255,0.8), transparent, rgba(255,255,255,0.3));
}
</style>

<div class="dash-wrap">

    {{-- ══════════ PAGE HEADER BAR ══════════ --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h1 style="font-size:22px;font-weight:800;color:#666c7a;margin:0;letter-spacing:-0.4px;">
                Good {{ now()->hour < 12 ? 'Morning' : (now()->hour < 17 ? 'Afternoon' : 'Evening') }},
                {{ explode(' ', auth()->user()->name)[0] }}
            </h1>
            <p style="font-size:13px;color:#94a3b8;margin:3px 0 0;">
                {{ now()->format('l, F j, Y') }} &nbsp;·&nbsp; Admin Dashboard
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.leads.all') }}"
               style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:12px;
                      background:rgba(255,255,255,0.8);border:1px solid rgba(30,64,175,0.15);
                      font-size:13px;font-weight:600;color:#1e40af;text-decoration:none;
                      transition:all .2s ease;box-shadow:0 2px 8px rgba(0,0,0,0.05);"
               onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 6px 16px rgba(30,64,175,0.15)'"
               onmouseout="this.style.transform='';this.style.boxShadow='0 2px 8px rgba(0,0,0,0.05)'">
                <span class="material-icons" style="font-size:17px;">add_circle_outline</span> New Lead
            </a>
            <a href="{{ route('admin.reports.conversion') }}"
               style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:12px;
                      background:linear-gradient(135deg, rgb(102 108 122), rgb(102 108 122));
                      font-size:13px;font-weight:600;color:#fff;text-decoration:none;
                      transition:all .2s ease;box-shadow:0 4px 14px rgba(15,23,42,0.32);"
               onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 8px 20px rgba(15,23,42,0.45)'"
               onmouseout="this.style.transform='';this.style.boxShadow='0 4px 14px rgba(15,23,42,0.32)'">
                <span class="material-icons" style="font-size:17px;">bar_chart</span> View Reports
            </a>
        </div>
    </div>

    {{-- ══════════ ROW 1: STAT CARDS ══════════ --}}
    <div class="row g-3 mb-4">
        {{-- 1. Total Leads — Blue --}}
        <div class="col-6 col-md-4 col-lg-2">
            <div class="d-card d-card-blue">
                <div class="d-card-icon"><span class="material-icons">groups</span></div>
                <div class="d-card-value">{{ number_format($totalLeads) }}</div>
                <div class="d-card-label">Total Leads</div>
                <div class="d-bar-track"><div class="d-bar-fill" style="width:72%;"></div></div>
                <div class="d-card-trend d-trend-up"><span class="material-icons" style="font-size:14px;">trending_up</span> 12% this month</div>
            </div>
        </div>
        {{-- 2. Managers — Green --}}
        <div class="col-6 col-md-4 col-lg-2">
            <div class="d-card d-card-green">
                <div class="d-card-icon"><span class="material-icons">manage_accounts</span></div>
                <div class="d-card-value">{{ $totalManagers }}</div>
                <div class="d-card-label">Managers</div>
                <div class="d-bar-track"><div class="d-bar-fill" style="width:55%;"></div></div>
                <div class="d-card-trend d-trend-neut"><span class="material-icons" style="font-size:14px;">remove</span> Stable</div>
            </div>
        </div>
        {{-- 3. Telecallers — Green mid --}}
        <div class="col-6 col-md-4 col-lg-2">
            <div class="d-card d-card-slate">
                <div class="d-card-icon"><span class="material-icons">support_agent</span></div>
                <div class="d-card-value">{{ $totalTelecallers }}</div>
                <div class="d-card-label">Telecallers</div>
                <div class="d-bar-track"><div class="d-bar-fill" style="width:65%;"></div></div>
                <div class="d-card-trend d-trend-up"><span class="material-icons" style="font-size:14px;">trending_up</span> Active team</div>
            </div>
        </div>
        {{-- 4. Active Calls — Blue light --}}
        <div class="col-6 col-md-4 col-lg-2">
            <div class="d-card d-card-blue">
                <div class="d-card-icon"><span class="material-icons">call</span></div>
                <div class="d-card-value">{{ $activeCallsNow }}</div>
                <div class="d-card-label">Active Calls</div>
                <div class="d-bar-track"><div class="d-bar-fill" style="width:{{ $activeCallsNow > 0 ? 80 : 5 }}%;"></div></div>
                <div class="d-card-trend {{ $activeCallsNow > 0 ? 'd-trend-up' : 'd-trend-neut' }}">
                    <span class="material-icons" style="font-size:14px;">{{ $activeCallsNow > 0 ? 'phone_in_talk' : 'phone_disabled' }}</span>
                    {{ $activeCallsNow > 0 ? 'Live now' : 'No active calls' }}
                </div>
            </div>
        </div>
        {{-- 5. Missed Calls — Slate --}}
        <div class="col-6 col-md-4 col-lg-2">
            <div class="d-card d-card-slate">
                <div class="d-card-icon"><span class="material-icons">phone_missed</span></div>
                <div class="d-card-value">{{ $missedCallsToday }}</div>
                <div class="d-card-label">Missed Today</div>
                <div class="d-bar-track"><div class="d-bar-fill" style="width:{{ min($missedCallsToday * 8, 95) }}%;"></div></div>
                <div class="d-card-trend {{ $missedCallsToday > 5 ? 'd-trend-down' : 'd-trend-neut' }}">
                    <span class="material-icons" style="font-size:14px;">{{ $missedCallsToday > 5 ? 'warning' : 'check_circle' }}</span>
                    {{ $missedCallsToday > 5 ? 'Needs attention' : 'Under control' }}
                </div>
            </div>
        </div>
        {{-- 6. Follow-ups — Green light --}}
        <div class="col-6 col-md-4 col-lg-2">
            <div class="d-card d-card-green">
                <div class="d-card-icon"><span class="material-icons">event</span></div>
                <div class="d-card-value">{{ $followupsToday }}</div>
                <div class="d-card-label">Follow-ups Today</div>
                <div class="d-bar-track"><div class="d-bar-fill" style="width:{{ min($followupsToday * 5, 95) }}%;"></div></div>
                <div class="d-card-trend d-trend-up"><span class="material-icons" style="font-size:14px;">schedule</span> Scheduled</div>
            </div>
        </div>
    </div>

    {{-- ══════════ INSIGHT BANNER ══════════ --}}
    <div class="d-insight-strip mb-4">
        <div>
            <p style="font-size:13px;color:rgba(255,255,255,0.72);margin:0 0 4px;font-weight:500;">This Month's Performance</p>
            <h2 style="font-size:24px;font-weight:800;color:#fff;margin:0;letter-spacing:-0.4px;">
                {{ $conversionsThisMonth }} Conversions Achieved
            </h2>
        </div>
        <div class="d-flex gap-3 align-items-center flex-wrap" style="position:relative;z-index:1;">
            <div class="d-insight-kpi">
                <strong>{{ $totalLeads }}</strong>
                <span>Total Leads</span>
            </div>
            <div class="d-insight-divider"></div>
            <div class="d-insight-kpi">
                <strong>{{ $conversionsThisMonth }}</strong>
                <span>Conversions</span>
            </div>
            <div class="d-insight-divider"></div>
            <div class="d-insight-kpi">
                <strong>{{ $totalLeads > 0 ? round($conversionsThisMonth / $totalLeads * 100) : 0 }}%</strong>
                <span>Conv. Rate</span>
            </div>
            <div class="d-insight-divider"></div>
            <div class="d-insight-kpi">
                <strong>{{ $followupsToday }}</strong>
                <span>Due Today</span>
            </div>
        </div>
    </div>

    {{-- ══════════ ROW 2: CHARTS ══════════ --}}
    <div class="row g-4 mb-4">

        {{-- Source Doughnut --}}
        <div class="col-lg-4">
            <div class="d-panel glass-card" style="position:relative;">
                <div class="d-panel-head d-flex justify-content-between align-items-start" style="margin-top:8px;">
                    <div>
                        <h3>Lead Sources</h3>
                        <p>Distribution by acquisition channel</p>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:20px;font-weight:800;color:#0f172a;line-height:1;">{{ number_format($totalLeads) }}</div>
                        <div style="font-size:10px;color:#94a3b8;font-weight:500;">total leads</div>
                    </div>
                </div>
                <div style="height:270px;position:relative;">
                    <canvas id="sourceLeadChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Call Volume Line --}}
        <div class="col-lg-8">
            <div class="d-panel glass-card" style="position:relative;">
                <div class="d-panel-head d-flex justify-content-between align-items-start" style="margin-top:8px;">
                    <div>
                        <h3>WhatsApp Chat Volume</h3>
                        <p>Inbound vs outbound conversations — last 14 days</p>
                    </div>
                    <div class="d-flex gap-2">
                        <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;
                                     background:#dbeafe;color:#1e40af;padding:5px 12px;border-radius:20px;font-weight:600;">
                            <span style="width:7px;height:7px;border-radius:50%;background:#3b82f6;display:inline-block;"></span>
                            Inbound
                        </span>
                        <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;
                                     background:#dcfce7;color:#14532d;padding:5px 12px;border-radius:20px;font-weight:600;">
                            <span style="width:7px;height:7px;border-radius:50%;background:#16a34a;display:inline-block;"></span>
                            Outbound
                        </span>
                    </div>
                </div>
                <div style="height:250px;"><canvas id="waVolumeChart"></canvas></div>
            </div>
        </div>
    </div>

    {{-- ══════════ ROW 3: CALL VOLUME + QUICK ACTIONS ══════════ --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="d-panel cus-chart-panel" style="position:relative;">
                <div class="d-panel-head d-flex justify-content-between align-items-start" style="margin-top:8px;">
                    <div>
                        <h3>Call Volume Trend</h3>
                        <p style="color:rgba(255,255,255,0.55);">Daily call activity over the last 14 days</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;
                                     background:rgba(22,163,74,0.25);color:#86efac;padding:5px 12px;border-radius:20px;font-weight:600;">
                            <span style="width:7px;height:7px;border-radius:50%;background:#22c55e;display:inline-block;"></span>
                            Calls
                        </span>
                        <span style="font-size:11px;background:rgba(255,255,255,0.1);color:rgba(255,255,255,0.7);
                                     padding:5px 12px;border-radius:20px;font-weight:600;">14 Days</span>
                    </div>
                </div>
                <div style="height:270px;"><canvas id="callVolumeChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="d-panel glass-card">
                <div class="d-panel-head">
                    <h3>Quick Actions</h3>
                    <p>Jump to common tasks</p>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <a href="{{ route('admin.users.telecallers') }}" class="d-action">
                            <div class="d-action-ico" style="background:#dbeafe;"><span class="material-icons" style="color:#7b90d3;">person_add</span></div>
                            <span>Add Telecaller</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="{{ route('admin.leads.all') }}" class="d-action">
                            <div class="d-action-ico" style="background:#dcfce7;"><span class="material-icons" style="color:#67a680;">group_add</span></div>
                            <span>All Leads</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="{{ route('admin.email-campaigns.index') }}" class="d-action">
                            <div class="d-action-ico" style="background:#e0f2fe;"><span class="material-icons" style="color:#0369a1;">mark_email_read</span></div>
                            <span>Email Campaign</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="{{ route('admin.reports.conversion') }}" class="d-action">
                            <div class="d-action-ico" style="background:#dbeafe;"><span class="material-icons" style="color:#1d4ed8;">bar_chart</span></div>
                            <span>Reports</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="{{ route('admin.settings.general') }}" class="d-action">
                            <div class="d-action-ico" style="background:#f1f5f9;"><span class="material-icons" style="color:#475569;">settings</span></div>
                            <span>Settings</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="{{ route('admin.campaigns.performance') }}" class="d-action">
                            <div class="d-action-ico" style="background:#dcfce7;"><span class="material-icons" style="color:#166534;">insights</span></div>
                            <span>Campaigns</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════ ROW 4: PIPELINE + ACTIVITY + TEAM ══════════ --}}
    <div class="row g-4 mb-4">

        {{-- Lead Pipeline --}}
        <div class="col-lg-4">
            <div class="d-panel glass-card">
                <div class="d-panel-head">
                    <h3>Lead Pipeline</h3>
                    <p>Funnel overview across all stages</p>
                </div>
                @php
                    $pipeTotal = max($totalLeads, 1);
                    $pipeStages = [
                        ['label' => 'New',         'count' => intval($totalLeads * 0.35), 'color' => 'linear-gradient(90deg, #bdc0db, #94a3b8)'],
                        ['label' => 'Contacted',   'count' => intval($totalLeads * 0.28), 'color' => 'linear-gradient(90deg, #bdc0db, #94a3b8)'],
                        ['label' => 'Interested',  'count' => intval($totalLeads * 0.20), 'color' => 'linear-gradient(90deg, #5c8557, #acc7a9)'],
                        ['label' => 'Negotiating', 'count' => intval($totalLeads * 0.12), 'color' => 'linear-gradient(90deg, #5c8557, #acc7a9)'],
                        ['label' => 'Converted',   'count' => $conversionsThisMonth,       'color' => 'linear-gradient(90deg, #5c8557, #acc7a9)'],
                    ];
                @endphp
                <div style="display:flex;flex-direction:column;gap:10px;">
                    @foreach($pipeStages as $stage)
                    @php $pct = min(round($stage['count'] / $pipeTotal * 100), 100); @endphp
                    <div class="d-funnel-row">
                        <div class="d-funnel-label">{{ $stage['label'] }}</div>
                        <div class="d-funnel-track">
                            <div class="d-funnel-fill" style="width:{{ max($pct,4) }}%;background:{{ $stage['color'] }};">
                                @if($pct > 12)<span>{{ $stage['count'] }}</span>@endif
                            </div>
                        </div>
                        <div class="d-funnel-count">{{ $stage['count'] }}</div>
                    </div>
                    @endforeach
                </div>
                <div style="margin-top:16px;padding-top:14px;border-top:1px solid rgba(0,0,0,0.05);display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:12px;color:#94a3b8;">Conversion rate</span>
                    <span style="font-size:15px;font-weight:800;color:#16a34a;">{{ $totalLeads > 0 ? round($conversionsThisMonth / $totalLeads * 100) : 0 }}%</span>
                </div>
            </div>
        </div>

        {{-- Activity Feed --}}
        <div class="col-lg-4">
            <div class="d-panel glass-card">
                <div class="d-panel-head">
                    <h3>Recent Activity</h3>
                    <p>Latest system events</p>
                </div>
                <div class="d-activity">
                    <div class="d-activity-item">
                        <div class="d-activity-dot" style="background:#dbeafe;">
                            <span class="material-icons" style="color:#1e40af;font-size:16px;">person_add</span>
                        </div>
                        <div class="d-activity-body">
                            <p>New lead <strong>imported</strong> from web form</p>
                            <small>Just now</small>
                        </div>
                    </div>
                    <div class="d-activity-item">
                        <div class="d-activity-dot" style="background:#dcfce7;">
                            <span class="material-icons" style="color:#14532d;font-size:16px;">task_alt</span>
                        </div>
                        <div class="d-activity-body">
                            <p>Conversion recorded — <strong>{{ $conversionsThisMonth }}</strong> this month</p>
                            <small>2 min ago</small>
                        </div>
                    </div>
                    <div class="d-activity-item">
                        <div class="d-activity-dot" style="background:#e0f2fe;">
                            <span class="material-icons" style="color:#0369a1;font-size:16px;">call</span>
                        </div>
                        <div class="d-activity-body">
                            <p><strong>{{ $activeCallsNow }}</strong> call{{ $activeCallsNow != 1 ? 's' : '' }} currently active</p>
                            <small>Live</small>
                        </div>
                    </div>
                    <div class="d-activity-item">
                        <div class="d-activity-dot" style="background:#dcfce7;">
                            <span class="material-icons" style="color:#166534;font-size:16px;">event_note</span>
                        </div>
                        <div class="d-activity-body">
                            <p><strong>{{ $followupsToday }}</strong> follow-up{{ $followupsToday != 1 ? 's' : '' }} scheduled for today</p>
                            <small>Today</small>
                        </div>
                    </div>
                    <div class="d-activity-item">
                        <div class="d-activity-dot" style="background:#f1f5f9;">
                            <span class="material-icons" style="color:#475569;font-size:16px;">mark_email_read</span>
                        </div>
                        <div class="d-activity-body">
                            <p>Email campaign <strong>delivered</strong> successfully</p>
                            <small>15 min ago</small>
                        </div>
                    </div>
                    <div class="d-activity-item">
                        <div class="d-activity-dot" style="background:#e2e8f0;">
                            <span class="material-icons" style="color:#334155;font-size:16px;">phone_missed</span>
                        </div>
                        <div class="d-activity-body">
                            <p><strong>{{ $missedCallsToday }}</strong> missed call{{ $missedCallsToday != 1 ? 's' : '' }} — needs follow-up</p>
                            <small>Today</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Team Spotlight --}}
        <div class="col-lg-4">
            <div class="d-panel glass-card">
                <div class="d-panel-head">
                    <h3>Team Spotlight</h3>
                    <p>{{ $totalManagers }} managers · {{ $totalTelecallers }} telecallers</p>
                </div>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    @php
                        $roles = [
                            ['name'=>'Management Team',  'sub'=>$totalManagers.' Managers Active',     'ico'=>'manage_accounts', 'bg'=>'#dcfce7', 'tc'=>'#14532d'],
                            ['name'=>'Calling Team',     'sub'=>$totalTelecallers.' Telecallers Active','ico'=>'support_agent',   'bg'=>'#dbeafe', 'tc'=>'#1e40af'],
                            ['name'=>'Conversions',      'sub'=>$conversionsThisMonth.' This Month',    'ico'=>'task_alt',        'bg'=>'#e0f2fe', 'tc'=>'#0369a1'],
                            ['name'=>'Follow-up Rate',   'sub'=>$followupsToday.' Scheduled Today',     'ico'=>'event_note',      'bg'=>'#f1f5f9', 'tc'=>'#334155'],
                        ];
                    @endphp
                    @foreach($roles as $r)
                    <div style="display:flex;align-items:center;gap:12px;padding:12px;background:rgba(255,255,255,0.6);border-radius:14px;border:1px solid rgba(255,255,255,0.9);">
                        <div style="width:40px;height:40px;border-radius:12px;background:{{ $r['bg'] }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <span class="material-icons" style="font-size:20px;color:{{ $r['tc'] }};">{{ $r['ico'] }}</span>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:13px;font-weight:700;color:#0f172a;">{{ $r['name'] }}</div>
                            <div style="font-size:11.5px;color:#94a3b8;">{{ $r['sub'] }}</div>
                        </div>
                        <div style="width:28px;height:28px;border-radius:50%;background:{{ $r['bg'] }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <span class="material-icons" style="font-size:15px;color:{{ $r['tc'] }};">chevron_right</span>
                        </div>
                    </div>
                    @endforeach
                </div>
                <a href="{{ route('admin.users.telecallers') }}"
                   style="display:flex;align-items:center;justify-content:center;gap:6px;margin-top:16px;
                          padding:10px;border-radius:12px;border:1.5px dashed rgba(30,64,175,0.25);
                          font-size:13px;font-weight:600;color:#1e40af;text-decoration:none;
                          transition:all .2s;"
                   onmouseover="this.style.background='rgba(30,64,175,0.06)'"
                   onmouseout="this.style.background='transparent'">
                    <span class="material-icons" style="font-size:16px;">open_in_new</span>
                    Manage Team
                </a>
            </div>
        </div>
    </div>

    {{-- ══════════ ROW 5: COURSE PERFORMANCE TABLE ══════════ --}}
    @if($courseStats->isNotEmpty())
    <div class="d-panel mb-4" style="padding:0;overflow:hidden;">
        <div style="padding:22px 24px 16px;display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0 0 3px;">Course Performance</h3>
                <p style="font-size:12px;color:#94a3b8;margin:0;">Lead volume and conversion rate by programme</p>
            </div>
            <span style="font-size:11px;background:#dbeafe;color:#1e40af;padding:5px 12px;border-radius:20px;font-weight:600;">
                {{ $courseStats->count() }} Courses
            </span>
        </div>
        <div class="table-responsive">
            <table class="d-tbl">
                <thead>
                    <tr>
                        <th>Course / Programme</th>
                        <th>Total Leads</th>
                        <th>Conversions</th>
                        <th>Conv. Rate</th>
                        <th style="min-width:140px;">Lead Volume</th>
                    </tr>
                </thead>
                <tbody>
                    @php $maxTotal = $courseStats->max('total') ?: 1; @endphp
                    @foreach($courseStats as $row)
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:34px;height:34px;border-radius:10px;background:#dbeafe;
                                            display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <span class="material-icons" style="font-size:17px;color:#1e40af;">school</span>
                                </div>
                                <span style="font-weight:600;color:#0f172a;">{{ $row['course'] }}</span>
                            </div>
                        </td>
                        <td>
                            <span style="font-weight:700;font-size:14px;color:#0f172a;">{{ $row['total'] }}</span>
                        </td>
                        <td>
                            <span style="font-weight:700;font-size:14px;color:#14532d;">{{ $row['conversions'] }}</span>
                        </td>
                        <td>
                            <span class="d-badge {{ $row['rate'] >= 30 ? 'd-badge-green' : ($row['rate'] >= 10 ? 'd-badge-yellow' : 'd-badge-red') }}">
                                {{ $row['rate'] >= 30 ? '●' : ($row['rate'] >= 10 ? '◑' : '○') }}
                                {{ $row['rate'] }}%
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="d-prog" style="flex:1;">
                                    <div class="d-prog-fill" style="width:{{ round($row['total'] / $maxTotal * 100) }}%;"></div>
                                </div>
                                <span style="font-size:11px;color:#94a3b8;min-width:36px;text-align:right;">
                                    {{ round($row['total'] / $maxTotal * 100) }}%
                                </span>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ══════════ ROW 6: METRIC PILLS ══════════ --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="d-metric-pill glass-card">
                <div class="d-metric-pill-ico" style="background:#dbeafe;">
                    <span class="material-icons" style="color:#1e40af;">leaderboard</span>
                </div>
                <div>
                    <strong>{{ $totalLeads }}</strong>
                    <span>Lifetime Leads</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="d-metric-pill glass-card">
                <div class="d-metric-pill-ico" style="background:#dcfce7;">
                    <span class="material-icons" style="color:#6fbe8e;">verified</span>
                </div>
                <div>
                    <strong>{{ $conversionsThisMonth }}</strong>
                    <span>Conversions</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="d-metric-pill glass-card">
                <div class="d-metric-pill-ico" style="background:#e0f2fe;">
                    <span class="material-icons" style="color:#0369a1;">phone_in_talk</span>
                </div>
                <div>
                    <strong>{{ $activeCallsNow }}</strong>
                    <span>Live Calls</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="d-metric-pill glass-card">
                <div class="d-metric-pill-ico" style="background:#f1f5f9;">
                    <span class="material-icons" style="color:#334155;">people</span>
                </div>
                <div>
                    <strong>{{ $totalManagers + $totalTelecallers }}</strong>
                    <span>Team Size</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════ BOTTOM CTA STRIP ══════════ --}}
    <div class="glass-card" style="background:rgba(255,255,255,0.7);border-radius:20px;padding:24px 28px;
                border:1px solid rgba(255,255,255,0.9);box-shadow:0 8px 24px rgba(0,0,0,0.04);
                display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div>
            <h3 style="font-size:16px;font-weight:700;color:#0f172a;margin:0 0 4px;">
                Want deeper insights?
            </h3>
            <p style="font-size:13px;color:#94a3b8;margin:0;">
                Check detailed reports for telecaller performance, lead sources, and response time analytics.
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.reports.telecaller-performance') }}"
               style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:12px;
                      background:rgba(255,255,255,0.8);border:1px solid rgba(30,64,175,0.15);
                      font-size:13px;font-weight:600;color:#1e40af;text-decoration:none;transition:all .2s;">
                <span class="material-icons" style="font-size:17px;">people</span> Telecaller Report
            </a>
            <a href="{{ route('admin.reports.conversion') }}"
               style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:12px;
                      background: linear-gradient(135deg, #666c7a, #666c7a);
                      font-size:13px;font-weight:600;color:#fff;text-decoration:none;
                      box-shadow:0 4px 14px rgba(30,64,175,0.30);transition:all .2s;">
                <span class="material-icons" style="font-size:17px;">trending_up</span> Conversion Report
            </a>
        </div>
    </div>

</div>{{-- /dash-wrap --}}

{{-- Charts JS --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    Chart.defaults.font.family = "'Poppins', sans-serif";
    Chart.defaults.font.size   = 11;
    Chart.defaults.color       = '#94a3b8';

    const grid  = { color: 'rgba(0,0,0,0.04)', drawBorder: false };
    const ticks = { color: '#94a3b8', padding: 8 };
    const scaleY = { grid, ticks: { ...ticks, precision: 0 }, beginAtZero: true, border: { display: false } };
    const scaleX = { grid: { display: false }, ticks, border: { display: false } };

    const tooltip = {
        backgroundColor: '#0f172a',
        titleColor: '#f1f5f9',
        bodyColor: '#94a3b8',
        padding: 10,
        cornerRadius: 10,
        titleFont: { weight: '700', size: 12 },
        bodyFont:  { size: 11 },
        displayColors: true,
        boxWidth: 8, boxHeight: 8,
    };

    /* ══════════════════════════════════════════
       1. LEAD SOURCES — Doughnut
       3-color palette: Blue shades + Green shades + Slate shades
       ══════════════════════════════════════════ */
    const srcCtx = document.getElementById('sourceLeadChart');
    if (srcCtx) {
        const srcPalette = [
            '#9194b1',  /* deep blue    */
            '#16a34a',  /* deep green   */
            '#3b82f6',  /* mid blue     */
            '#22c55e',  /* mid green    */
            '#1e293b',  /* charcoal     */
            '#60a5fa',  /* light blue   */
            '#4ade80',  /* light green  */
            '#475569',  /* slate        */
        ];
        const srcTotal = @json($sourceValues).reduce((a,b) => a+b, 0);

        const centerPlugin = {
            id: 'centerText',
            afterDraw(chart) {
                const { ctx, chartArea: { left, right, top, bottom } } = chart;
                const cx = (left + right) / 2;
                const cy = (top  + bottom) / 2;
                ctx.save();
                ctx.font = 'bold 22px Poppins, sans-serif';
                ctx.fillStyle = '#0f172a';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(srcTotal.toLocaleString(), cx, cy - 8);
                ctx.font = '500 11px Poppins, sans-serif';
                ctx.fillStyle = '#94a3b8';
                ctx.fillText('Total', cx, cy + 14);
                ctx.restore();
            }
        };

        new Chart(srcCtx, {
            type: 'doughnut',
            plugins: [centerPlugin],
            data: {
                labels: @json($sourceLabels),
                datasets: [{
                    data: @json($sourceValues),
                    backgroundColor: srcPalette,
                    borderWidth: 3,
                    borderColor: 'rgba(255,255,255,0.9)',
                    hoverBorderColor: '#fff',
                    hoverOffset: 10,
                    spacing: 2,
                }]
            },
            options: {
                maintainAspectRatio: false,
                cutout: '72%',
                animation: { animateRotate: true, duration: 900 },
                plugins: {
                    tooltip,
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 14, usePointStyle: true,
                            pointStyleWidth: 8, boxHeight: 7,
                            font: { size: 11 }, color: '#64748b'
                        }
                    }
                }
            }
        });
    }

    /* ══════════════════════════════════════════
       2. CALL VOLUME — Smooth area (green)
       ══════════════════════════════════════════ */
    const callCtx = document.getElementById('callVolumeChart');
    if (callCtx) {
        const cg = callCtx.getContext('2d');

        const strokeGrad = cg.createLinearGradient(0, 0, callCtx.offsetWidth, 0);
        strokeGrad.addColorStop(0,   '#afd4bb');
        strokeGrad.addColorStop(0.5, '#afd4bb');
        strokeGrad.addColorStop(1,   '#afd4bb');

        const fillGrad = cg.createLinearGradient(0, 0, 0, 270);
        fillGrad.addColorStop(0,   'rgba(22,163,74,0.28)');
        fillGrad.addColorStop(0.6, 'rgba(22,163,74,0.06)');
        fillGrad.addColorStop(1,   'rgba(22,163,74,0)');

        new Chart(callCtx, {
            type: 'line',
            data: {
                labels: @json($callVolumeLabels),
                datasets: [{
                    label: 'Calls',
                    data: @json($callVolumeValues),
                    borderColor: strokeGrad,
                    backgroundColor: fillGrad,
                    fill: true,
                    tension: 0.45,
                    borderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#60dc8d',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2.5,
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#50d782',
                    pointHoverBorderWidth: 3,
                }]
            },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: scaleX,
                    y: { ...scaleY, ticks: { ...ticks, color: 'rgba(255,255,255,0.5)', precision: 0 }, grid: { color: 'rgba(255,255,255,0.06)', drawBorder: false } }
                },
                plugins: { legend: { display: false }, tooltip }
            }
        });
    }

    /* ══════════════════════════════════════════
       3. WHATSAPP — Grouped bars (blue + green)
       ══════════════════════════════════════════ */
    const waCtx = document.getElementById('waVolumeChart');
    if (waCtx) {
        const wg = waCtx.getContext('2d');

        /* Blue gradient for inbound */
        const inGrad = wg.createLinearGradient(0, 0, 0, 250);
        inGrad.addColorStop(0,   'rgba(59,130,246,0.92)');
        inGrad.addColorStop(1,   'rgba(59,130,246,0.55)');

        /* Green gradient for outbound */
        const outGrad = wg.createLinearGradient(0, 0, 0, 250);
        outGrad.addColorStop(0,   '#acc7a9');
        outGrad.addColorStop(1,   'rgba(138, 202, 160, 0.55)');

        new Chart(waCtx, {
            type: 'bar',
            data: {
                labels: @json($waVolumeLabels),
                datasets: [
                    {
                        label: 'Inbound',
                        data: @json($waInboundValues),
                        backgroundColor: inGrad,
                        borderRadius: { topLeft: 8, topRight: 8 },
                        borderSkipped: false,
                        borderWidth: 0,
                    },
                    {
                        label: 'Outbound',
                        data: @json($waOutboundValues),
                        backgroundColor: outGrad,
                        borderRadius: { topLeft: 8, topRight: 8 },
                        borderSkipped: false,
                        borderWidth: 0,
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { ...scaleX, grid: { display: false } },
                    y: scaleY
                },
                plugins: { legend: { display: false }, tooltip },
                barPercentage: 0.60,
                categoryPercentage: 0.72,
            }
        });
    }
})();
</script>
@endsection