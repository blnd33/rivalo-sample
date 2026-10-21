"""
Lists server text (admin panel, API messages, notifications) that has no Arabic or Kurmanji
translation yet. Run from the repository root:

    py tools/translations/check_server_strings.py

Arabic covers everything (admin panel + app-facing messages). Kurmanji covers only text players
can see: business-rule messages from services/controllers and notification texts. The admin
panel is English/Arabic only (decision D8).
"""
import json
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2] / 'backend'
CALL = re.compile(r"""__\(\s*(['"])((?:\\.|(?!\1).)*)\1""", re.S)
RULE = re.compile(r"""DomainRuleException::make\(\s*'[^']*'\s*,\s*(['"])((?:\\.|(?!\1).)*)\1""", re.S)
ENUM_CASE = re.compile(r"""case\s+\w+\s*=\s*'([a-z0-9_]+)'""")


def unescape(s: str, quote: str) -> str:
    return s.replace('\\' + quote, quote).replace('\\\\', '\\')


def keys_in(paths, pattern):
    found = {}
    for path in paths:
        text = path.read_text(encoding='utf-8')
        for m in pattern.finditer(text):
            key = unescape(m.group(2), m.group(1))
            if '{$' in key or '$' in key and m.group(1) == '"':
                continue  # interpolated: not a literal key
            if re.fullmatch(r'[a-z_]+\.[a-z_.]+', key) or not re.search(r'[A-Za-z]', key):
                continue  # Laravel lang-file keys (validation.unique) and symbols such as an em dash
            found.setdefault(key, str(path.relative_to(ROOT)))
    return found


def enum_labels():
    """EnumHelpers::label() turns 'registration_open' into 'Registration open'."""
    labels = {}
    for path in (ROOT / 'app' / 'Enums').glob('*.php'):
        text = path.read_text(encoding='utf-8')
        if 'use EnumHelpers;' not in text:
            continue
        for value in ENUM_CASE.findall(text):
            label = value.replace('_', ' ')
            labels.setdefault(label[:1].upper() + label[1:], str(path.relative_to(ROOT)))
    return labels


def main() -> int:
    php = [p for d in ('app', 'routes', 'resources/views', 'database/seeders') for p in (ROOT / d).rglob('*.php')]
    all_keys = keys_in(php, CALL) | keys_in(php, RULE) | enum_labels()

    # Services that answer the app's own requests. Finance, fixtures, points and announcements
    # are staff-only (admin panel: English and Arabic).
    app_services = {'Notifier.php', 'AccountService.php', 'TeamService.php', 'RegistrationService.php', 'ResultService.php', 'ModerationService.php', 'StoreService.php', 'InventoryService.php', 'SellerService.php'}
    player_facing = [p for p in php if p.parts[-2] == 'V1' or (p.parts[-2] == 'Services' and p.name in app_services)]
    kmr_keys = keys_in(player_facing, CALL) | keys_in(player_facing, RULE)

    ar = json.loads((ROOT / 'lang' / 'ar.json').read_text(encoding='utf-8'))
    kmr = json.loads((ROOT / 'lang' / 'kmr.json').read_text(encoding='utf-8'))

    missing_ar = {k: v for k, v in all_keys.items() if k not in ar}
    missing_kmr = {k: v for k, v in kmr_keys.items() if k not in kmr}

    if '--json' in sys.argv:
        print(json.dumps({'ar': missing_ar, 'kmr': missing_kmr}, ensure_ascii=False, indent=1))
    else:
        print(f'Arabic: {len(missing_ar)} missing of {len(all_keys)}')
        for k, v in sorted(missing_ar.items(), key=lambda kv: kv[1]):
            print(f'  [{v}] {k}')
        print(f'Kurmanji (player-facing): {len(missing_kmr)} missing of {len(kmr_keys)}')
        for k, v in sorted(missing_kmr.items(), key=lambda kv: kv[1]):
            print(f'  [{v}] {k}')
    return 1 if missing_ar or missing_kmr else 0


if __name__ == '__main__':
    sys.exit(main())
