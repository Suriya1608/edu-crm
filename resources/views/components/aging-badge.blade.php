@props(['days' => 0])

@if($days >= 6)
    <span class="badge" style="background:#fef2f2; color:#dc2626; border:1px solid #fecaca; font-size:11px; font-weight:600; padding:2px 7px; border-radius:6px;">
        {{ $days }}d old
    </span>
@elseif($days >= 3)
    <span class="badge" style="background:#fffbeb; color:#d97706; border:1px solid #fde68a; font-size:11px; font-weight:600; padding:2px 7px; border-radius:6px;">
        {{ $days }}d old
    </span>
@endif
