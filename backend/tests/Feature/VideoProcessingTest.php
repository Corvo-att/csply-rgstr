<?php

namespace Tests\Feature;

use App\Models\FormSubmissionValue;
use App\Services\MediaStorage;
use App\Services\VideoProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/**
 * These tests use the REAL ffmpeg/ffprobe, so they are skipped on machines that do not have them.
 * Point at custom binaries with FFMPEG_BINARY / FFPROBE_BINARY.
 */
class VideoProcessingTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    private static ?string $sample = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixtures();

        if (! app(VideoProcessor::class)->isAvailable()) {
            $this->markTestSkipped('ffmpeg / ffprobe are not installed.');
        }
    }

    /** An 8-second 1280x720 clip with sound, generated once per test run. */
    private function sampleVideo(): UploadedFile
    {
        if (! self::$sample || ! file_exists(self::$sample)) {
            self::$sample = sys_get_temp_dir().DIRECTORY_SEPARATOR.'egycon-sample-'.getmypid().'.mp4';

            (new Process([
                config('media.video.ffmpeg'), '-y', '-v', 'error',
                '-f', 'lavfi', '-i', 'testsrc=duration=8:size=1280x720:rate=15',
                '-f', 'lavfi', '-i', 'sine=frequency=440:duration=8',
                '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-shortest', self::$sample,
            ]))->mustRun();
        }

        $copy = tempnam(sys_get_temp_dir(), 'vid');
        copy(self::$sample, $copy);

        return new UploadedFile($copy, 'show.mp4', 'video/mp4', null, true);
    }

    public function test_over_length_video_is_rejected_when_the_field_says_reject(): void
    {
        $form = $this->makeForm();
        $video = $this->addField($form, 'video', 'Show video', options: ['max_duration_seconds' => 3, 'over_length_action' => 'reject']);

        $this->actingAs($this->makeCosplayer())
            ->post($this->submitUrl($form), ['values' => [$video->id => $this->sampleVideo()]])
            ->assertSessionHasErrors("values.{$video->id}");

        $this->assertSame(0, FormSubmissionValue::count());
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_video_within_the_limit_is_accepted_and_normalised(): void
    {
        $form = $this->makeForm();
        $video = $this->addField($form, 'video', 'Show video', options: ['max_duration_seconds' => 30, 'over_length_action' => 'reject']);

        $this->actingAs($this->makeCosplayer())
            ->post($this->submitUrl($form), ['values' => [$video->id => $this->sampleVideo()]])
            ->assertSessionHasNoErrors();

        $value = FormSubmissionValue::firstOrFail();
        $this->assertSame('ready', $value->processing_status);   // queue is "sync" in tests
        $this->assertEqualsWithDelta(8, $value->meta['duration'], 1);
        $this->assertCount(1, Storage::disk('public')->allFiles(), 'the raw upload must be replaced, not kept');
    }

    public function test_over_length_video_is_trimmed_and_downscaled_when_the_field_says_trim(): void
    {
        $form = $this->makeForm();
        $video = $this->addField($form, 'video', 'Show video', options: [
            'max_duration_seconds' => 3, 'over_length_action' => 'trim', 'max_height' => 360,
        ]);

        $this->actingAs($this->makeCosplayer())
            ->post($this->submitUrl($form), ['values' => [$video->id => $this->sampleVideo()]])
            ->assertSessionHasNoErrors();

        $value = FormSubmissionValue::firstOrFail();
        $this->assertSame('ready', $value->processing_status, json_encode($value->meta));
        $this->assertTrue($value->meta['trimmed']);

        $path = app(MediaStorage::class)->pathFromUrl($value->value);
        $probe = app(VideoProcessor::class)->probe(Storage::disk('public')->path($path));

        $this->assertStringEndsWith('.mp4', $path);
        $this->assertEqualsWithDelta(3, $probe['duration'], 0.6);
        $this->assertSame(360, $probe['height']);
        $this->assertSame('h264', $probe['codec']);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_normalisation_can_be_switched_off_per_field(): void
    {
        $form = $this->makeForm();
        $video = $this->addField($form, 'video', 'Show video', options: ['normalize' => false]);

        $this->actingAs($this->makeCosplayer())
            ->post($this->submitUrl($form), ['values' => [$video->id => $this->sampleVideo()]])
            ->assertSessionHasNoErrors();

        $this->assertNull(FormSubmissionValue::firstOrFail()->processing_status);
    }

    public function test_when_ffmpeg_is_missing_production_mode_refuses_videos(): void
    {
        config(['media.video.ffprobe' => 'definitely-not-installed', 'media.video.require_ffmpeg' => true]);

        $form = $this->makeForm();
        $video = $this->addField($form, 'video', 'Show video');

        $this->actingAs($this->makeCosplayer())
            ->post($this->submitUrl($form), ['values' => [$video->id => $this->sampleVideo()]])
            ->assertSessionHasErrors("values.{$video->id}");
    }

    public function test_when_ffmpeg_is_missing_dev_mode_accepts_videos_unchecked(): void
    {
        config(['media.video.ffprobe' => 'definitely-not-installed', 'media.video.require_ffmpeg' => false]);

        $form = $this->makeForm();
        $video = $this->addField($form, 'video', 'Show video');

        $this->actingAs($this->makeCosplayer())
            ->post($this->submitUrl($form), ['values' => [$video->id => $this->sampleVideo()]])
            ->assertSessionHasNoErrors();

        $this->assertNull(FormSubmissionValue::firstOrFail()->processing_status);
    }
}
