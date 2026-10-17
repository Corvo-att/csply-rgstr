<?php

namespace App\Services;

use Symfony\Component\Process\Process;

/**
 * Thin wrapper around the ffprobe / ffmpeg command-line tools.
 *
 * ffprobe  -> read duration / resolution of an upload (cheap, done during the request
 *             so we can reject over-length videos immediately).
 * ffmpeg   -> normalise the video for the show (trim, downscale, H.264 + AAC .mp4);
 *             slow, so it runs in a queued job (see App\Jobs\ProcessVideoUpload).
 */
class VideoProcessor
{
    private ?bool $available = null;

    /** True when both ffmpeg and ffprobe can be executed on this machine. */
    public function isAvailable(): bool
    {
        return $this->available ??= $this->works(config('media.video.ffprobe'))
            && $this->works(config('media.video.ffmpeg'));
    }

    /**
     * @return array{duration: float, width: int, height: int, codec: string}|null
     *                                                                             null when the file is not a readable video.
     */
    public function probe(string $path): ?array
    {
        $process = new Process([
            config('media.video.ffprobe'), '-v', 'error',
            '-print_format', 'json', '-show_format', '-show_streams', $path,
        ]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $info = json_decode($process->getOutput(), true) ?: [];
        $video = collect($info['streams'] ?? [])->firstWhere('codec_type', 'video');
        if (! $video) {
            return null;
        }

        $duration = (float) ($info['format']['duration'] ?? $video['duration'] ?? 0);

        return [
            'duration' => $duration,
            'width' => (int) ($video['width'] ?? 0),
            'height' => (int) ($video['height'] ?? 0),
            'codec' => (string) ($video['codec_name'] ?? ''),
        ];
    }

    /**
     * Re-encode $input into $output (.mp4, H.264 + AAC, web-optimised).
     *
     * @param  float|null  $trimTo  cut the video after this many seconds
     * @param  int|null  $maxHeight  scale down so the height is at most this (never up)
     */
    public function transcode(string $input, string $output, ?float $trimTo = null, ?int $maxHeight = null): void
    {
        $cmd = [config('media.video.ffmpeg'), '-y', '-v', 'error', '-i', $input];

        if ($trimTo !== null && $trimTo > 0) {
            array_push($cmd, '-t', (string) $trimTo);
        }

        // -2 keeps the width divisible by 2 (required by H.264); min() never upscales.
        if ($maxHeight !== null && $maxHeight > 0) {
            array_push($cmd, '-vf', "scale=-2:min(ih\\,{$maxHeight})");
        }

        array_push(
            $cmd,
            '-map', '0:v:0', '-map', '0:a:0?',
            '-c:v', 'libx264', '-preset', config('media.video.preset'),
            '-crf', (string) config('media.video.crf'), '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-b:a', '128k',
            '-movflags', '+faststart',
            $output
        );

        $process = new Process($cmd);
        $process->setTimeout((float) config('media.video.timeout'));
        $process->mustRun();
    }

    private function works(string $binary): bool
    {
        try {
            $process = new Process([$binary, '-version']);
            $process->setTimeout(10);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }
}
