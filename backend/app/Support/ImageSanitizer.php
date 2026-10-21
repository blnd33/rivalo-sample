<?php

namespace App\Support;

use App\Exceptions\DomainRuleException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Re-encodes uploaded player images (avatars, team logos) before they become public.
 * Re-encoding drops EXIF metadata, including the GPS location many phones embed in photos,
 * and any data hidden after the image. Production must have the GD extension
 * (`php artisan rivalo:launch-check` verifies it).
 */
class ImageSanitizer
{
    /** @return string|null JPEG bytes, or null when GD is missing (development only) */
    public static function reencode(UploadedFile $file, int $maxPx = 512): ?string
    {
        if (! extension_loaded('gd')) {
            Log::warning('GD extension missing: image stored without re-encoding. Enable extension=gd in php.ini.');

            return null;
        }

        $source = @imagecreatefromstring((string) $file->get());
        if ($source === false) {
            throw DomainRuleException::make('invalid_image', 'The image could not be read.');
        }

        $w = imagesx($source);
        $h = imagesy($source);
        $scale = min(1, $maxPx / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $canvas = imagecreatetruecolor($nw, $nh);
        // Transparent areas become the app background colour (RIVALO navy).
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 11, 16, 32));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagejpeg($canvas, null, 85);
        $out = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return $out;
    }
}
