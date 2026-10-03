<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitFormRequest;
use App\Jobs\ProcessVideoUpload;
use App\Models\Cosplayer;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Services\ImageOptimizer;
use App\Services\MediaStorage;
use App\Services\VideoProcessor;
use App\Support\ServerLimits;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class FormController extends Controller
{
    public function __construct(
        private readonly ImageOptimizer $images,
        private readonly MediaStorage $storage,
        private readonly VideoProcessor $videos,
    ) {}

    /** Show the form fill page. */
    public function fill(Event $event, Form $form)
    {
        abort_if($form->event_id !== $event->id, 404);

        $cosplayer = Cosplayer::where('user_id', Auth::id())->first();
        $submission = $cosplayer
            ? FormSubmission::where('form_id', $form->id)->where('cosplayer_id', $cosplayer->id)->first()
            : null;

        // Already submitted? Then the reason it is "closed" is that they are done.
        $closedReason = $submission ? null : $form->closedReason();

        return Inertia::render('Forms/Fill', [
            'event' => $event->only('id', 'name'),
            'form' => $form->only('id', 'name', 'description', 'closes_at'),
            'fields' => ($closedReason || $submission) ? [] : $form->fields()->get()->map(fn (FormField $f) => [
                ...$f->toArray(),
                // the server's own limits, so the browser can warn about exactly what the server will enforce
                'max_kb' => $f->isFile() ? $f->maxSizeKb() : null,
                'accepted_mimes' => $f->isFile() ? $f->allowedMimes() : null,
            ]),
            'cosplayer' => $cosplayer,
            'closed_reason' => $closedReason,
            'submission' => $submission?->only('entry_number', 'status', 'submitted_at'),
            'upload_limit_mb' => ServerLimits::maxUploadMb(),
        ]);
    }

    /**
     * Store a submission. By the time this runs SubmitFormRequest has already checked
     * every answer against the form's field definitions (required, choices, sizes, file
     * types, video length ...), that the form is open, and that this cosplayer has not
     * submitted before.
     *
     * Images are optimised immediately (fast). Videos are stored as uploaded and then
     * normalised by a queued job (slow) - see ProcessVideoUpload.
     */
    public function submit(SubmitFormRequest $request, Event $event, Form $form)
    {
        $cosplayer = $request->cosplayer();
        $values = (array) $request->input('values', []);
        $stored = [];   // URLs written during this request, deleted again if anything fails
        $jobs = [];   // video jobs to dispatch once the data is safely committed

        try {
            DB::transaction(function () use ($request, $form, $cosplayer, $values, &$stored, &$jobs) {
                // Next free entry number for this form (locked so two people cannot get the same one).
                $entryNumber = (int) FormSubmission::where('form_id', $form->id)->lockForUpdate()->max('entry_number') + 1;

                $submission = FormSubmission::create([
                    'form_id' => $form->id,
                    'cosplayer_id' => $cosplayer->id,
                    'entry_number' => $entryNumber,
                    'status' => 'pending',
                    'submitted_at' => now(),
                ]);

                foreach ($request->fields() as $field) {
                    if (! $field->holdsAnswer()) {
                        continue;
                    }

                    $raw = $field->field_type === 'hidden'
                        ? ($field->options['default_value'] ?? null)
                        : ($request->file("values.{$field->id}") ?? ($values[$field->id] ?? null));

                    $row = $this->buildRow($field, $raw, $request->probes[$field->id] ?? null, $stored);
                    if ($row === null) {
                        continue;
                    }

                    $value = $submission->values()->create($row['attributes']);

                    if ($row['job']) {
                        $jobs[] = [$value->id, ...$row['job']];
                    }
                }
            });
        } catch (UniqueConstraintViolationException) {
            $this->discard($stored);

            return back()->withErrors(['values' => 'You have already submitted this form.']);
        } catch (\Throwable $e) {
            $this->discard($stored);
            throw $e;
        }

        foreach ($jobs as [$valueId, $trimTo, $maxHeight]) {
            ProcessVideoUpload::dispatch($valueId, $trimTo, $maxHeight);
        }

        return redirect()->route('cosplay.profile')->with('success', 'Form submitted successfully!');
    }

    /**
     * Turn one raw answer into the attributes of a FormSubmissionValue.
     *
     * @return array{attributes: array, job: array|null}|null null = nothing to store
     */
    private function buildRow(FormField $field, mixed $raw, ?array $probe, array &$stored): ?array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }

        $attributes = ['form_field_id' => $field->id, 'value' => null];
        $job = null;

        if ($raw instanceof UploadedFile) {
            $isImage = $field->field_type === 'image'
                || ($field->field_type === 'file' && in_array($raw->getMimeType(), config('media.image_mimes'), true));

            if ($isImage) {
                $attributes['value'] = $this->storeImage($raw, $field, $stored);
            } else {
                $file = $this->storage->store($raw);
                $stored[] = $file['url'];
                $attributes['value'] = $file['url'];

                if ($field->field_type === 'video') {
                    [$attributes, $job] = $this->prepareVideo($field, $attributes, $probe);
                }
            }
        } elseif (is_array($raw)) {
            // Multi-select answers and addresses are stored as JSON (decoded again by SubmissionPresenter).
            $attributes['value'] = json_encode($raw, JSON_UNESCAPED_UNICODE);
        } else {
            $attributes['value'] = (string) $raw;
        }

        return ['attributes' => $attributes, 'job' => $job];
    }

    private function storeImage(UploadedFile $file, FormField $field, array &$stored): string
    {
        $opts = $field->options ?? [];

        try {
            $result = $this->images->optimize($file, ! empty($opts['max_dimension']) ? (int) $opts['max_dimension'] : null);
            $stored[] = $result['url'];

            Log::info(sprintf(
                '[ImageOptimizer] field=%d original=%d KB -> %d KB',
                $field->id,
                round($result['originalBytes'] / 1024),
                round($result['savedBytes'] / 1024)
            ));

            return $result['url'];
        } catch (\Throwable $e) {
            // Never lose a submission because optimisation failed: keep the (validated) original.
            Log::warning('[ImageOptimizer] failed, storing original: '.$e->getMessage());
            $original = $this->storage->store($file);
            $stored[] = $original['url'];

            return $original['url'];
        }
    }

    /**
     * Decide whether (and how) a freshly stored video should be processed in the background.
     *
     * @return array{0: array, 1: array|null} [value attributes, [trimTo, maxHeight] or null]
     */
    private function prepareVideo(FormField $field, array $attributes, ?array $probe): array
    {
        $opts = $field->options ?? [];

        if ($probe) {
            $attributes['meta'] = ['duration' => $probe['duration'], 'width' => $probe['width'], 'height' => $probe['height']];
        }

        // Processing is opt-out per field ("normalize" = false) and only possible when ffmpeg is installed.
        if (($opts['normalize'] ?? true) === false || ! $probe || ! $this->videos->isAvailable()) {
            return [$attributes, null];
        }

        $max = (float) ($opts['max_duration_seconds'] ?? 0);
        $trimTo = ($max > 0 && $probe['duration'] > $max + 0.5) ? $max : null;   // validation let it through => action is "trim"
        $height = array_key_exists('max_height', $opts) && $opts['max_height'] !== '' ? (int) $opts['max_height'] : 1080;

        $attributes['processing_status'] = 'pending';

        return [$attributes, [$trimTo, $height > 0 ? $height : null]];
    }

    /** @param string[] $urls */
    private function discard(array $urls): void
    {
        foreach ($urls as $url) {
            $this->storage->deleteByUrl($url);
        }
    }
}
