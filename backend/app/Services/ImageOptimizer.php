<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

/**
 * Optimises any uploaded image before it is stored.
 *
 * Pipeline (each step is skipped gracefully if the condition is not met):
 *  1. Strip EXIF metadata (orientation, GPS, etc.)
 *  2. Auto-rotate based on EXIF orientation tag
 *  3. Resize to max dimensions (default 2 400 × 2 400 px) — keeps aspect ratio
 *  4. Re-encode as WebP when the driver supports it, otherwise keep original MIME
 *  5. Compress (default quality 82 %)
 *
 * Returns the absolute path of the saved file and the public URL.
 */
class ImageOptimizer
{
    /** Default max dimension (px) for either side */
    private const MAX_DIM = 2400;

    /** Default JPEG / WebP quality (0-100) */
    private const QUALITY = 82;

    /**
     * Optimise an uploaded image and save it to public/uploads/.
     *
     * @param  UploadedFile $file        The uploaded file.
     * @param  int          $maxDim      Max width/height in pixels (0 = no resize).
     * @param  int          $quality     Encode quality 0-100.
     * @param  bool         $toWebP      Convert to WebP if GD supports it.
     * @return array{path: string, url: string, originalBytes: int, savedBytes: int}
     */
    public function optimize(
        UploadedFile $file,
        int  $maxDim  = self::MAX_DIM,
        int  $quality = self::QUALITY,
        bool $toWebP  = true
    ): array {
        $manager  = new ImageManager(new Driver());
        $image    = $manager->read($file->getRealPath());
        $origSize = filesize($file->getRealPath());

        // ── 1 + 2: Strip EXIF & auto-rotate ──────────────────────────────────
        // Intervention v3 automatically handles EXIF orientation on read().
        // orientate() + removeExifData() are the explicit v3 calls:
        $image->orientate();   // rotate to match EXIF orientation tag
        $image->removeExifData();

        // ── 3: Resize to max dimensions (scale down only) ─────────────────────
        if ($maxDim > 0) {
            $w = $image->width();
            $h = $image->height();

            if ($w > $maxDim || $h > $maxDim) {
                $image->scaleDown($maxDim, $maxDim);
            }
        }

        // ── 4 + 5: Encode & write ─────────────────────────────────────────────
        $destination = public_path('uploads');
        if (! file_exists($destination)) {
            mkdir($destination, 0777, true);
        }

        // Decide output format
        $mime = $file->getMimeType();
        if ($toWebP && function_exists('imagewebp')) {
            $ext      = 'webp';
            $encoded  = $image->toWebp($quality);
        } elseif (str_contains($mime, 'png')) {
            $ext      = 'png';
            // PNG quality is 0-9; map our 0-100 scale to 0-9
            $encoded  = $image->toPng();
        } elseif (str_contains($mime, 'gif')) {
            $ext      = 'gif';
            $encoded  = $image->toGif();
        } else {
            // Default: JPEG
            $ext      = 'jpg';
            $encoded  = $image->toJpeg($quality);
        }

        $fileName  = \Illuminate\Support\Str::random(24) . '.' . $ext;
        $filePath  = $destination . DIRECTORY_SEPARATOR . $fileName;

        file_put_contents($filePath, (string) $encoded);

        $savedSize = filesize($filePath);

        return [
            'path'          => $filePath,
            'url'           => url('uploads/' . $fileName),
            'originalBytes' => $origSize,
            'savedBytes'    => $savedSize,
        ];
    }

    /**
     * Quick helper: returns true if the given MIME type is an image we can process.
     */
    public static function isOptimisableImage(string $mime): bool
    {
        return in_array($mime, [
            'image/jpeg', 'image/jpg', 'image/png',
            'image/gif',  'image/webp', 'image/bmp',
            'image/tiff',
        ], true);
    }
}
