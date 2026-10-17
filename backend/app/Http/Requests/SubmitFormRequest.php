<?php

namespace App\Http\Requests;

use App\Models\Cosplayer;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Services\VideoProcessor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Validates a cosplayer's answers to a dynamic form.
 *
 * The rules are BUILT FROM THE FORM'S FIELD DEFINITIONS (FormField::validationRules()),
 * so "required", choices, number ranges, upload sizes and allowed file types are all
 * enforced on the server - the React form checks them too, but only as a convenience.
 */
class SubmitFormRequest extends FormRequest
{
    private ?Collection $fields = null;

    /** Result of ffprobe per video field id, filled during validation and reused by the controller. */
    public array $probes = [];

    public function authorize(): bool
    {
        // The form must belong to the event in the URL (otherwise pretend it does not exist).
        return $this->route('form')->event_id === $this->route('event')->id;
    }

    protected function failedAuthorization(): void
    {
        throw new NotFoundHttpException;
    }

    public function form(): Form
    {
        return $this->route('form');
    }

    /** @return Collection<int, FormField> keyed by field id */
    public function fields(): Collection
    {
        return $this->fields ??= $this->form()->fields()->get()->keyBy('id');
    }

    public function cosplayer(): ?Cosplayer
    {
        return Cosplayer::where('user_id', $this->user()->id)->first();
    }

    public function rules(): array
    {
        $rules = ['values' => ['sometimes', 'array']];

        foreach ($this->fields() as $field) {
            $rules = array_merge($rules, $field->validationRules());
        }

        return $rules;
    }

    public function attributes(): array
    {
        $names = [];
        foreach ($this->fields() as $field) {
            $names["values.{$field->id}"] = $field->label;
        }

        return $names;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $this->checkEligibility($validator);
                $this->checkUnknownFields($validator);
                $this->checkVideos($validator);
            },
        ];
    }

    private function checkEligibility(Validator $validator): void
    {
        if ($reason = $this->form()->closedReason()) {
            $validator->errors()->add('values', $reason);

            return;
        }

        $cosplayer = $this->cosplayer();
        if (! $cosplayer) {
            $validator->errors()->add('values', 'Please complete your cosplay registration before submitting this form.');

            return;
        }

        if (FormSubmission::where('form_id', $this->form()->id)->where('cosplayer_id', $cosplayer->id)->exists()) {
            $validator->errors()->add('values', 'You have already submitted this form.');
        }
    }

    /** Reject answers for fields that do not exist on THIS form (or that hold no answer). */
    private function checkUnknownFields(Validator $validator): void
    {
        $answerable = $this->fields()->filter->holdsAnswer()->keys()->map(fn ($id) => (string) $id);

        foreach (array_keys((array) $this->input('values', [])) as $key) {
            if (! $answerable->contains((string) $key)) {
                $validator->errors()->add('values', 'The form contains an unexpected field. Please reload the page and try again.');

                return;
            }
        }
    }

    /** Duration / readability checks for video uploads (needs ffprobe). */
    private function checkVideos(Validator $validator): void
    {
        $videos = app(VideoProcessor::class);

        foreach ($this->fields()->where('field_type', 'video') as $field) {
            $key = "values.{$field->id}";
            $file = $this->file($key);

            if (! $file instanceof UploadedFile || $validator->errors()->has($key)) {
                continue;
            }

            if (! $videos->isAvailable()) {
                if (config('media.video.require_ffmpeg')) {
                    $validator->errors()->add($key, 'Video uploads are temporarily unavailable. Please try again later.');
                } else {
                    Log::warning("[video] ffmpeg/ffprobe not available - video for field {$field->id} accepted unchecked.");
                }

                continue;
            }

            $probe = $videos->probe($file->getRealPath());
            if (! $probe) {
                $validator->errors()->add($key, "{$field->label} is not a valid video file.");

                continue;
            }

            $opts = $field->options ?? [];
            $max = (float) ($opts['max_duration_seconds'] ?? 0);
            $min = (float) ($opts['min_duration_seconds'] ?? 0);

            if ($max > 0 && $probe['duration'] > $max + 0.5 && ($opts['over_length_action'] ?? 'reject') !== 'trim') {
                $validator->errors()->add($key, sprintf(
                    'Your video is %s long. The maximum is %s.',
                    self::seconds($probe['duration']),
                    self::seconds($max)
                ));

                continue;
            }

            if ($min > 0 && $probe['duration'] < $min) {
                $validator->errors()->add($key, sprintf('Your video is too short. The minimum is %s.', self::seconds($min)));

                continue;
            }

            $this->probes[$field->id] = $probe;
        }
    }

    private static function seconds(float $s): string
    {
        $s = (int) round($s);

        return $s >= 60 ? sprintf('%d:%02d min', intdiv($s, 60), $s % 60) : "{$s} sec";
    }
}
