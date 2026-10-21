# RIVALO mobile (Flutter)

One codebase for **Android and iOS**, designed to feel native on iPhone:

- Collapsing large titles (`CupertinoSliverNavigationBar`) and a frosted-glass tab bar.
- Edge swipe-back on every screen, plus iOS action sheets and alerts.
- The HIG Dynamic Type scale in the system font (SF Pro on iPhone, Roboto on Android). Text scales with the user's setting.
- 44pt touch targets, haptics, VoiceOver and TalkBack labels, status shown as text plus icon (never color alone), reduced motion respected.

## Structure

```
lib/
  main.dart · app/ (app, router with auth redirects, tab shell)
  core/
    config/env.dart            API_BASE_URL via --dart-define (no secrets in the app)
    design/                    tokens.dart (mirrors design-system/tokens.json), theme.dart, widgets/
    l10n/                      app_en.arb, app_ar.arb (draft) → generated/
    network/ storage/ ui/ utils/
  features/
    auth/ onboarding/ home/ tournaments/ teams/ profile/ rankings/ news/ catalog/ dev/
```

**State management: Riverpod 3.** Providers give compile-safe dependency injection, `AsyncValue` covers loading, error and data states on every screen, and tests swap in fakes with `overrideWith`. No code generation, so there's no `build_runner` step on Windows.

## Run

```bash
flutter pub get
flutter run
```

The default API is `http://10.0.2.2:8000/api/v1` on the Android emulator and `http://localhost:8000/api/v1` on the iOS simulator. To override it:

```bash
flutter run --dart-define=API_BASE_URL=https://api.staging.example.com/api/v1
```

Debug builds show **Settings → Developer → Design system gallery**, which displays every component and state.

## Quality

```bash
flutter analyze
```

```bash
flutter test
```

## Notes
- `web/` exists only for quick visual previews during development. It is not a product target.
- Arabic strings are a draft for native-speaker review. Kurdish is pending decision D8.
- `applicationId` / bundle id `app.rivalo.rivalo` is a placeholder. Change it to your domain before the first store upload.
