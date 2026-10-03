<?php

namespace App\Jobs;

use App\Models\FormSubmissionValue;
use App\Services\MediaStorage;
use App\Services\VideoProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Normalises one uploaded show video in the background (trim / downscale / H.264 mp4),
 * so the cosplayer's request returns immediately.
 *
 * Lifecycle of FormSubmissionValue.processing_status:
 *   pending -> processing -> ready   (value now points at the processed file)
 *                         \-> failed (original upload is kept so nothing is lost)
 *
 * Run a worker for this to happen:  php artisan queue:work
 */
class ProcessVideoUpload implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(
        public int $valueId,
        public ?float $trimTo = null,
        public ?int $maxHeight = null,
    ) {
        $this->timeout = (int) config('media.video.timeout');
    }

    public function handle(VideoProcessor $videos, MediaStorage $storage): void
    {
        $value = FormSubmissionValue::find($this->valueId);
        if (! $value) {
            return; // submission was deleted while the job was waiting
        }

        $inputPath = $storage->pathFromUrl($value->value);
        if (! $inputPath) {
            $this->markFailed($value, 'Source file not found.');

            return;
        }

        $value->update(['processing_status' => 'processing']);

        $outputPath = $storage->newPath('mp4');
        $storage->disk()->makeDirectory(dirname($outputPath));

        try {
            $videos->transcode(
                $storage->absolutePath($inputPath),
                $storage->absolutePath($outputPath),
                $this->trimTo,
                $this->maxHeight,
            );
            $probe = $videos->probe($storage->absolutePath($outputPath));
        } catch (\Throwable $e) {
            $storage->disk()->delete($outputPath);
            $this->markFailed($value, $e->getMessage());

            return;
        }

        $meta = array_merge($value->meta ?? [], [
            'original_bytes' => $storage->disk()->size($inputPath),
            'bytes' => $storage->disk()->size($outputPath),
            'duration' => $probe['duration'] ?? null,
            'width' => $probe['width'] ?? null,
            'height' => $probe['height'] ?? null,
            'trimmed' => $this->trimTo !== null,
        ]);

        $oldUrl = $value->value;
        $value->update([
            'value' => $storage->url($outputPath),
            'processing_status' => 'ready',
            'meta' => $meta,
        ]);
        $storage->deleteByUrl($oldUrl);
    }

    public function failed(\Throwable $e): void
    {
        if ($value = FormSubmissionValue::find($this->valueId)) {
            $this->markFailed($value, $e->getMessage());
        }
    }

    private function markFailed(FormSubmissionValue $value, string $message): void
    {
        Log::warning("[ProcessVideoUpload] value {$value->id} failed: {$message}");
        $value->update([
            'processing_status' => 'failed',
            'meta' => array_merge($value->meta ?? [], ['error' => mb_substr($message, 0, 500)]),
        ]);
    }
}
