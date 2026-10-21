{{--
    RIVALO admin theme overrides (no Node build required). Values mirror design-system/tokens.json.
    Lime is kept for primary actions; text on lime buttons uses midnight navy for contrast.
--}}
<style>
    :root {
        --font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
    }
    .dark .fi-body,
    .dark .fi-layout { background-color: #0B1020; }
    .dark .fi-sidebar,
    .dark .fi-topbar > nav,
    .dark .fi-topbar { background-color: #0B1020; border-color: #2A3650; }
    .dark .fi-section,
    .dark .fi-ta-ctn,
    .dark .fi-wi-stats-overview-stat,
    .dark .fi-modal-window { background-color: #182238; }
    /* Solid primary buttons: brand lime with midnight-navy text (about 15:1 contrast) in both modes. */
    .fi-btn.fi-color-primary:not(.fi-outlined) { background-color: #C7FF3D !important; color: #0B1020 !important; }
    .fi-btn.fi-color-primary:not(.fi-outlined):hover { background-color: #B5EB2E !important; }
    .fi-btn.fi-color-primary:not(.fi-outlined) .fi-icon { color: #0B1020 !important; }
    .fi-sidebar-item.fi-active .fi-sidebar-item-label,
    .fi-sidebar-item.fi-active .fi-icon { color: #C7FF3D; }
    :focus-visible { outline-color: #C7FF3D; }
    .rivalo-sample-badge {
        display: inline-flex; align-items: center; gap: .25rem; padding: .1rem .5rem; border-radius: 999px;
        font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
        color: #FFBF47; border: 1px solid rgba(255, 191, 71, .45); background: rgba(255, 191, 71, .08);
    }
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { transition-duration: 1ms !important; animation-duration: 1ms !important; }
    }
</style>
