@props(['height' => 28, 'tagline' => false])
{{-- RIVALO wordmark: temporary vector treatment (same geometry as website/index.html). Off-white letters, lime V. --}}
<span {{ $attributes->merge(['class' => 'rivalo-wordmark']) }} style="display:inline-flex;flex-direction:column;align-items:flex-start;gap:4px;line-height:1">
    <svg role="img" aria-label="RIVALO" viewBox="0 0 232 44" height="{{ $height }}" style="overflow:hidden;display:block" xmlns="http://www.w3.org/2000/svg">
        <g fill="none" stroke="#F5F7FA" stroke-width="8" stroke-linejoin="miter" stroke-linecap="butt">
            <path d="M4 48 V4 H18 A10 10 0 0 1 18 24 H4 M17 24 L32 48"/>
            <path d="M46 -4 V48"/>
            <path d="M110 48 L126 0 L142 48 M115 31 H137"/>
            <path d="M157 -4 V40 H178"/>
            <circle cx="208" cy="22" r="18"/>
        </g>
        <path d="M62 -4 L78 44 L94 -4" fill="none" stroke="#C7FF3D" stroke-width="8" stroke-linejoin="miter"/>
    </svg>
    @if ($tagline)
        <span style="font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:#A6B0C3;font-weight:700">Play. Win. Rise.</span>
    @endif
</span>
