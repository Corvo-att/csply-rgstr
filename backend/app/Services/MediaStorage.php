<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The ONLY place that decides where an uploaded file lives and what it is called.
 *
 * Security rules enforced here (see config/media.php):
 *  - the stored extension comes from the file's real (content-sniffed) MIME type
 *    via an allow-list, never from the client-supplied filename;
 *  - the file name is random, so URLs cannot be guessed;
 *  - a MIME type that is not on the allow-list is refused outright.
 */
class MediaStorage
{
    public function disk(): Filesystem
    {
        return Storage::disk(config('media.disk'));
    }

    /** Extension for a MIME type, or null if we do not accept that type at all. */
    public function extensionFor(?string $mime): ?string
    {
        return config('media.mime_extensions')[$mime ?? ''] ?? null;
    }

    /**
     * Store an uploaded file untouched (apart from the safe name).
     *
     * @return array{path: string, url: string, mime: string}
     */
    public function store(UploadedFile $file): array
    {
        $mime = $file->getMimeType();
        $ext = $this->extensionFor($mime);

        if ($ext === null) {
            throw new \InvalidArgumentException("File type [{$mime}] is not allowed.");
        }

        $path = $this->newPath($ext);
        $this->disk()->putFileAs(dirname($path), $file, basename($path));

        return ['path' => $path, 'url' => $this->url($path), 'mime' => $mime];
    }

    /** @return array{path: string, url: string} */
    public function putContents(string $contents, string $ext): array
    {
        $path = $this->newPath($ext);
        $this->disk()->put($path, $contents);

        return ['path' => $path, 'url' => $this->url($path)];
    }

    public function newPath(string $ext): string
    {
        return trim(config('media.directory'), '/').'/'.Str::random(40).'.'.$ext;
    }

    public function url(string $path): string
    {
        return $this->disk()->url($path);
    }

    public function absolutePath(string $path): string
    {
        return $this->disk()->path($path);
    }

    /** Turn a stored URL back into a disk path (null if it is not one of ours). */
    public function pathFromUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $prefix = rtrim($this->disk()->url(''), '/').'/';
        if (! str_starts_with($url, $prefix)) {
            return null;
        }

        $path = substr($url, strlen($prefix));

        // never allow path traversal out of the uploads directory
        return (str_contains($path, '..') || ! str_starts_with($path, trim(config('media.directory'), '/').'/'))
            ? null
            : $path;
    }

    public function deleteByUrl(?string $url): void
    {
        if ($path = $this->pathFromUrl($url)) {
            $this->disk()->delete($path);
        }
    }
}
