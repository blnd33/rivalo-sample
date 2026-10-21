# Arabic and Kurdish review

Every text in the app and every server message now exists in **English, Arabic and Kurdish (Kurmanji, Badini dialect, Latin script)**. The admin panel exists in English and Arabic. All Arabic and Kurdish text is a **draft written without a native speaker**. It needs a native-speaker review before release.

## For reviewers

Open [`rivalo-translations.csv`](rivalo-translations.csv) in Excel or Google Sheets. It's saved as UTF-8, so Arabic and Kurdish letters display correctly.

| Column | Meaning |
|---|---|
| `area` | `app` = phone screens · `server` = messages the server sends to the app (errors, notifications) · `admin (Arabic only)` = staff admin panel |
| `key` | Internal name. Don't change it. |
| `english` | The source text. |
| `arabic`, `kurdish` | The current draft. |
| `arabic_corrected`, `kurdish_corrected` | **Write your correction here** only where the draft is wrong or unnatural. Leave it empty if the draft is fine. |
| `notes` | Questions or context. |

Rules:
- Keep placeholders exactly as they are: `{name}`, `{count}` (app) and `:team`, `:count` (server). They are replaced with real values. Corrections that change a placeholder are refused.
- Keep "RIVALO" in Latin letters.
- Kurdish: Kurmanji (Badini), Latin script, left to right.
- Text that appears on buttons should stay short.

Suggested order: `app` rows first (what every player sees), then `server`, then `admin`.

## Applying a review

```bash
py tools/translations/review_sheet.py import path/to/reviewed.csv
cd mobile && flutter gen-l10n && flutter test
cd ../backend && php -d extension=intl -d extension=gd vendor/bin/phpunit
```

Regenerate the sheet after text changes: `py tools/translations/review_sheet.py export`.

## Checks that run automatically

- `mobile/test/safety_test.dart`: every app text exists in Arabic and Kurdish with the same placeholders.
- `backend/tests/Feature/TranslationCoverageTest.php`: every server text has Arabic, every message the app can show has Kurdish, and no placeholder was lost.
- `py tools/translations/check_server_strings.py` lists anything missing on the server.
