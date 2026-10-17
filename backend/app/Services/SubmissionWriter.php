<?php

namespace App\Services;

use App\Events\FormSubmissionCreated;
use App\Models\Cosplayer;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Persists a form submission + its values atomically.
 *
 * Used by the public web form and (later) the Excel import so both paths
 * produce identical data and both trigger the Google Sheets sync event.
 */
class SubmissionWriter
{
    public function __construct(private readonly ImageOptimizer $optimizer) {}

    /**
     * @param array<int|string, mixed> $values  form_field_id => value (scalar, array or UploadedFile)
     */
    public function write(
        Form $form,
        Cosplayer $cosplayer,
        array $values,
        string $source = 'web',
        ?int $importBatchId = null,
        bool $dispatchEvent = true,
    ): FormSubmission {
        $fieldMap = $form->fields()->get()->keyBy('id');

        $submission = DB::transaction(function () use ($form, $cosplayer, $values, $source, $importBatchId, $fieldMap) {
            $submission = FormSubmission::create([
                'form_id'         => $form->id,
                'cosplayer_id'    => $cosplayer->id,
                'source'          => $source,
                'import_batch_id' => $importBatchId,
                'submitted_at'    => now(),
            ]);

            foreach ($values as $fieldId => $value) {
                $field = $fieldMap->get($fieldId);
                if (! $field) {
                    continue; // ignore ids that don't belong to this form
                }

                FormSubmissionValue::create([
                    'form_submission_id' => $submission->id,
                    'form_field_id'      => $field->id,
                    'field_key_snapshot' => $field->field_key,
                    'value'              => $this->normalizeValue($value, $field->field_type, $fieldId),
                ]);
            }

            return $submission;
        });

        if ($dispatchEvent) {
            FormSubmissionCreated::dispatch($submission);
        }

        return $submission;
    }

    /**
     * Update an existing submission and replace its values.
     */
    public function update(
        FormSubmission $submission,
        array $values,
        ?int $importBatchId = null,
        bool $dispatchEvent = true,
    ): FormSubmission {
        $fieldMap = $submission->form->fields()->get()->keyBy('id');

        DB::transaction(function () use ($submission, $values, $importBatchId, $fieldMap) {
            $submission->update([
                'import_batch_id' => $importBatchId ?? $submission->import_batch_id,
                'submitted_at'    => now(),
            ]);

            $submission->values()->delete();

            foreach ($values as $fieldId => $value) {
                $field = $fieldMap->get($fieldId);
                if (! $field) {
                    continue;
                }

                FormSubmissionValue::create([
                    'form_submission_id' => $submission->id,
                    'form_field_id'      => $field->id,
                    'field_key_snapshot' => $field->field_key,
                    'value'              => $this->normalizeValue($value, $field->field_type, $fieldId),
                ]);
            }
        });

        if ($dispatchEvent) {
            FormSubmissionCreated::dispatch($submission->fresh());
        }

        return $submission;
    }

    /**
     * Create or update submission based on whether one already exists for this cosplayer.
     */
    public function writeOrUpdate(
        Form $form,
        Cosplayer $cosplayer,
        array $values,
        string $source = 'web',
        ?int $importBatchId = null,
        bool $dispatchEvent = true,
        bool $allowUpdate = false,
    ): FormSubmission {
        if ($allowUpdate) {
            $existing = FormSubmission::where('form_id', $form->id)
                ->where('cosplayer_id', $cosplayer->id)
                ->first();

            if ($existing) {
                return $this->update($existing, $values, $importBatchId, $dispatchEvent);
            }
        }

        return $this->write($form, $cosplayer, $values, $source, $importBatchId, $dispatchEvent);
    }

    private function normalizeValue(mixed $value, string $fieldType, mixed $fieldId): ?string
    {
        if (is_array($value)) {
            return implode(', ', $value);
        }

        if (! $value instanceof UploadedFile) {
            return $value;
        }

        $mime    = $value->getMimeType() ?? '';
        $isImage = ($fieldType === 'image')
            || ($fieldType === 'file' && ImageOptimizer::isOptimisableImage($mime));

        if (! $isImage) {
            return $this->storeRaw($value);
        }

        try {
            $result  = $this->optimizer->optimize($value);
            $origKb  = round($result['originalBytes'] / 1024);
            $finalKb = round($result['savedBytes'] / 1024);
            $savedKb = $origKb - $finalKb;

            Log::info(sprintf(
                '[ImageOptimizer] field=%s | original=%d KB → optimised=%d KB (saved %d KB, %.0f%%)',
                $fieldId, $origKb, $finalKb, $savedKb, $origKb > 0 ? ($savedKb / $origKb * 100) : 0
            ));

            return $result['url'];
        } catch (\Throwable $e) {
            // Never lose a submission because optimisation failed.
            Log::warning('[ImageOptimizer] Failed, storing original: ' . $e->getMessage());

            return $this->storeRaw($value);
        }
    }

    /** Move an uploaded file to public/uploads untouched and return its URL. */
    private function storeRaw(UploadedFile $file): string
    {
        $fileName    = Str::random(24) . '.' . $file->getClientOriginalExtension();
        $destination = public_path('uploads');

        if (! file_exists($destination)) {
            mkdir($destination, 0777, true);
        }

        $file->move($destination, $fileName);

        return url('uploads/' . $fileName);
    }
}
