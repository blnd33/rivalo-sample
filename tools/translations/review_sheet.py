"""
Native-speaker review of Arabic and Kurdish (Kurmanji/Badini) text.

    py tools/translations/review_sheet.py export    -> docs/translation-review/rivalo-translations.csv
    py tools/translations/review_sheet.py import FILE

Export writes one row per text (app screens and server messages) with English, the current
Arabic and Kurdish drafts, and empty "corrected" columns. Open it in Excel or Google Sheets
(UTF-8), give reviewers the columns for their language, and save as CSV UTF-8.

Import applies only non-empty "*_corrected" cells, keeps every {placeholder} / :placeholder
check, and prints what changed. Then run `flutter gen-l10n` in mobile/ and the test suites.
"""
import collections
import csv
import json
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
ARB = ROOT / 'mobile' / 'lib' / 'core' / 'l10n'
LANG = ROOT / 'backend' / 'lang'
OUT = ROOT / 'docs' / 'translation-review' / 'rivalo-translations.csv'
FIELDS = ['area', 'key', 'english', 'arabic', 'kurdish', 'arabic_corrected', 'kurdish_corrected', 'notes']


def load(path):
    return json.loads(path.read_text(encoding='utf-8'), object_pairs_hook=collections.OrderedDict)


def save(path, data, indent):
    path.write_text(json.dumps(data, ensure_ascii=False, indent=indent) + '\n', encoding='utf-8', newline='\n')


def placeholders(text, server):
    if server:
        return set(re.findall(r':([a-z_]+)', text))
    branch = re.compile(r'(=\d+|zero|one|two|few|many|other)\s*$')
    return {m.group(1) for m in re.finditer(r'\{(\w+)\s*[,}]', text) if not branch.search(text[:m.start()])}


def export():
    en, ar, kmr = load(ARB / 'app_en.arb'), load(ARB / 'app_ar.arb'), load(ARB / 'app_kmr.arb')
    rows = []
    for key, text in en.items():
        if key.startswith('@'):
            continue
        rows.append({'area': 'app', 'key': key, 'english': text, 'arabic': ar.get(key, ''), 'kurdish': kmr.get(key, '')})

    sar, skmr = load(LANG / 'ar.json'), load(LANG / 'kmr.json')
    for english in sar.keys() | skmr.keys():
        rows.append({
            'area': 'server' if english in skmr else 'admin (Arabic only)',
            'key': english, 'english': english,
            'arabic': sar.get(english, ''), 'kurdish': skmr.get(english, ''),
        })

    OUT.parent.mkdir(parents=True, exist_ok=True)
    # utf-8-sig: Excel on Windows then opens Arabic and Kurdish letters correctly.
    with OUT.open('w', encoding='utf-8-sig', newline='') as f:
        writer = csv.DictWriter(f, fieldnames=FIELDS)
        writer.writeheader()
        for r in sorted(rows, key=lambda r: (r['area'], r['key'].lower())):
            writer.writerow({**{k: '' for k in FIELDS}, **r})
    print(f'{len(rows)} rows -> {OUT.relative_to(ROOT)}')


def import_(path):
    app = {'ar': load(ARB / 'app_ar.arb'), 'kmr': load(ARB / 'app_kmr.arb')}
    server = {'ar': load(LANG / 'ar.json'), 'kmr': load(LANG / 'kmr.json')}
    changed, refused = 0, []
    with open(path, encoding='utf-8-sig', newline='') as f:
        for row in csv.DictReader(f):
            for locale, column in (('ar', 'arabic_corrected'), ('kmr', 'kurdish_corrected')):
                new = (row.get(column) or '').strip()
                if not new:
                    continue
                is_server = row['area'] != 'app'
                if placeholders(new, is_server) != placeholders(row['english'], is_server):
                    refused.append(f"{locale} {row['key']}: placeholders must match the English text")
                    continue
                target = server[locale] if is_server else app[locale]
                if target.get(row['key']) != new:
                    target[row['key']] = new
                    changed += 1
    save(ARB / 'app_ar.arb', app['ar'], 2)
    save(ARB / 'app_kmr.arb', app['kmr'], 2)
    save(LANG / 'ar.json', server['ar'], 4)
    save(LANG / 'kmr.json', server['kmr'], 4)
    print(f'{changed} text(s) updated.')
    for r in refused:
        print('NOT APPLIED:', r)


if __name__ == '__main__':
    if len(sys.argv) >= 2 and sys.argv[1] == 'export':
        export()
    elif len(sys.argv) >= 3 and sys.argv[1] == 'import':
        import_(sys.argv[2])
    else:
        print(__doc__)
        sys.exit(1)
