<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fails when English server text is added without its translation, so Arabic staff never see
 * English in the admin panel and Kurdish players never get English error messages.
 * Same rules as tools/translations/check_server_strings.py (which also prints what is missing).
 */
class TranslationCoverageTest extends TestCase
{
    private const CALL = '/__\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s';

    private const RULE = '/DomainRuleException::make\(\s*\'[^\']*\'\s*,\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s';

    /** Services that answer the app's own requests (the rest are staff-only: English and Arabic). */
    private const APP_SERVICES = ['Notifier.php', 'AccountService.php', 'TeamService.php', 'RegistrationService.php', 'ResultService.php', 'ModerationService.php', 'StoreService.php', 'InventoryService.php', 'SellerService.php'];

    /** @return array<string, string> key => file */
    private function keys(iterable $files, string $pattern): array
    {
        $found = [];
        foreach ($files as $file) {
            preg_match_all($pattern, File::get($file), $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $key = str_replace(['\\'.$m[1], '\\\\'], [$m[1], '\\'], $m[2]);
                if (str_contains($key, '$') || preg_match('/^[a-z_]+\.[a-z_.]+$/', $key) || ! preg_match('/[A-Za-z]/', $key)) {
                    continue;
                }
                $found[$key] ??= Str::after((string) $file, base_path().DIRECTORY_SEPARATOR);
            }
        }

        return $found;
    }

    /** @return list<\SplFileInfo> */
    private function phpFiles(): array
    {
        return collect(['app', 'routes', 'resources/views', 'database/seeders'])
            ->flatMap(fn ($dir) => File::allFiles(base_path($dir)))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.php'))
            ->values()->all();
    }

    private function enumLabels(): array
    {
        $labels = [];
        foreach (File::files(app_path('Enums')) as $file) {
            $text = File::get($file);
            if (! str_contains($text, 'use EnumHelpers;')) {
                continue;
            }
            preg_match_all("/case\s+\w+\s*=\s*'([a-z0-9_]+)'/", $text, $m);
            foreach ($m[1] as $value) {
                $labels[Str::ucfirst(str_replace('_', ' ', $value))] = $file->getFilename();
            }
        }

        return $labels;
    }

    public function test_every_server_text_has_an_arabic_translation(): void
    {
        $files = $this->phpFiles();
        $keys = $this->keys($files, self::CALL) + $this->keys($files, self::RULE) + $this->enumLabels();
        $ar = json_decode(File::get(lang_path('ar.json')), true);

        $missing = array_diff_key($keys, $ar);
        $this->assertSame([], $missing, 'Add these to lang/ar.json (run tools/translations/check_server_strings.py).');
    }

    public function test_every_message_the_app_can_show_has_a_kurmanji_translation(): void
    {
        $files = array_filter($this->phpFiles(), fn ($f) => str_contains($f->getPath(), 'Api'.DIRECTORY_SEPARATOR.'V1')
            || (basename($f->getPath()) === 'Services' && in_array($f->getFilename(), self::APP_SERVICES, true)));
        $keys = $this->keys($files, self::CALL) + $this->keys($files, self::RULE);
        $kmr = json_decode(File::get(lang_path('kmr.json')), true);

        $this->assertGreaterThan(100, count($keys));
        $missing = array_diff_key($keys, $kmr);
        $this->assertSame([], $missing, 'Add these to lang/kmr.json (run tools/translations/check_server_strings.py).');
    }

    public function test_translations_keep_every_placeholder(): void
    {
        foreach (['ar', 'kmr'] as $locale) {
            foreach (json_decode(File::get(lang_path("{$locale}.json")), true) as $english => $translated) {
                preg_match_all('/:([a-z_]+)/', $english, $a);
                preg_match_all('/:([a-z_]+)/', $translated, $b);
                $this->assertEqualsCanonicalizing(array_unique($a[1]), array_unique($b[1]), "{$locale}: \"{$english}\" lost or renamed a placeholder.");
            }
        }
    }
}
