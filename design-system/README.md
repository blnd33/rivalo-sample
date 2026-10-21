# RIVALO design system

`tokens.json` is the single source of truth for brand values. Two hand-maintained mirrors use it:

| Consumer | File |
|---|---|
| Flutter app | `mobile/lib/core/design/tokens.dart` (+ `theme.dart`, `widgets/`) |
| Admin panel (Filament) | `backend/app/Providers/Filament/AdminPanelProvider.php` (palette) + `backend/resources/views/filament/theme.blade.php` |
| Website | `website/style.css` `:root` tokens (pre-existing) |

When you change a token, update every mirror in the same change.

## Principles
- **Dark first.** Midnight navy background, slate cards, restrained 1px borders.
- **Lime is scarce.** Use it for the one primary action, the active tab, selection and the V in the wordmark.
- **Never color alone.** Every status pill has text and an icon.
- **Apple-quality UX on iPhone.** HIG type scale, large titles, frosted bars, swipe-back, haptics, 44pt targets.
- **Honest data.** Sample data is labeled. Unbuilt features say "Coming in a future update". No fabricated stats, sponsors or adoption claims.
- **Calm motion.** 180–260ms, exits faster than entrances, nothing when the OS asks for reduced motion.

## Wordmark
Temporary vector treatment: uppercase monoline geometric letters in off-white with a lime **V** (same geometry as `website/index.html` symbol `#wordmark`). Replace it when final logo assets arrive. It's implemented in `RivaloWordmark` (Flutter) and `<x-wordmark>` (Blade).

## Contrast (computed)
| Pair | Ratio |
|---|---|
| Text #F5F7FA on background #0B1020 | ≈ 18:1 |
| Secondary #A6B0C3 on surface #182238 | ≈ 7.2:1 |
| Tertiary #7D88A0 on background #0B1020 | ≈ 5.3:1 (≈ 4:1 on raised surfaces, so large text only there) |
| Navy #0B1020 on lime #C7FF3D | ≈ 15:1 |
