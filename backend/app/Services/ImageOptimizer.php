<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Optimises an uploaded image before it is stored:
 *  1. (EXIF auto-rotation is done by Intervention 3 itself when the file is read)
 *  2. downscale to a max width/height (never upscale)
 *  3. re-encode as WebP (or JPEG/PNG when GD has no WebP support) - re-encoding
 *     through GD also drops EXIF/GPS metadata.
 */
class ImageOptimizer
{
    public function __construct(private readonly MediaStorage $storage) {}

    /**
     * @return array{path: string, url: string, originalBytes: int, savedBytes: int}
     */
    public function optimize(UploadedFile $file, ?int $maxDim = null, ?int $quality = null): array
    {
        $maxDim ??= (int) config('media.image.max_dimension');
        $quality ??= (int) config('media.image.quality');

        $origSize = filesize($file->getRealPath());
        $image = (new ImageManager(new Driver))->read($file->getRealPath());

        if ($maxDim > 0 && ($image->width() > $maxDim || $image->height() > $maxDim)) {
            $image->scaleDown($maxDim, $maxDim);
        }

        $mime = $file->getMimeType() ?? '';
        if (function_exists('imagewebp')) {
            $ext = 'webp';
            $encoded = $image->toWebp($quality);
        } elseif (str_contains($mime, 'png')) {
            $ext = 'png';
            $encoded = $image->toPng();
        } else {
            $ext = 'jpg';
            $encoded = $image->toJpeg($quality);
        }

        $stored = $this->storage->putContents((string) $encoded, $ext);

        return [
            'path' => $stored['path'],
            'url' => $stored['url'],
            'originalBytes' => $origSize,
            'savedBytes' => $this->storage->disk()->size($stored['path']),
        ];
    }
}
