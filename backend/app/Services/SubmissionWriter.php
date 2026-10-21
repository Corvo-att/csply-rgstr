<?php

namespace App\Services;

use App\Events\FormSubmissionCreated;
use App\Models\Cosplayer;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Services\GoogleSheets\SheetSyncDispatcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Persists a form submission + its values atomically.
 *
 * Used by the import (and any future writer) so every path produces identical data:
 * numbered entries, revision history on edits, and the Google Sheets sync flag.
 */
class SubmissionWriter
{
    public function __construct(
        private readonly ImageOptimizer $optimizer,
        private readonly SheetSyncDispatcher $sync,
        private readonly SubmissionRevisionRecorder $revisions,
    ) {}

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
            // Next free entry number for this form (locked so two writers cannot get the same one).
            $entryNumber = (int) FormSubmission::where('form_id', $form->id)->lockForUpdate()->max('entry_number') + 1;

            $submission = FormSubmission::create([
                'form_id'         => $form->id,
                'cosplayer_id'    => $cosplayer->id,
                'entry_number'    => $entryNumber,
                'status'          => 'pending',
                'source'          => $source,
                'import_batch_id' => $importBatchId,
                'submitted_at'    => now(),
            ]);

            foreach ($values as $fieldId => $value) {
                $field = $fieldMap->get($fieldId);
                if (! $field) {
                    continue; // ignore ids that don't belong to this form
                }

                $stored = $this->normalizeValue($value, $field, $fieldId);
                if ($stored === null || $stored === '') {
                    continue; // same as the public form: unanswered fields store nothing
                }

                $submission->values()->create([
                    'form_field_id'      => $field->id,
                    'field_key_snapshot' => $field->field_key,
                    'value'              => $stored,
                ]);
            }

            return $submission;
        });

        if ($dispatchEvent) {
            FormSubmissionCreated::dispatch($submission);
        } else {
            // Bulk callers (imports) flush once at the end instead of once per row.
            $this->sync->changed($submission, flush: false);
        }

        return $submission;
    }

    /**
     * Update an existing submission. Only the fields that are actually provided are touched,
     * an empty cell never wipes an existing answer, and the old state is kept as a revision.
     */
    public function update(
        FormSubmission $submission,
        array $values,
        ?int $importBatchId = null,
        bool $dispatchEvent = true,
        string $source = 'import',
    ): FormSubmission {
        $fieldMap = $submission->form->fields()->get()->keyBy('id');

        DB::transaction(function () use ($submission, $values, $importBatchId, $fieldMap, $source) {
            $this->revisions->record($submission, $source);

            foreach ($values as $fieldId => $value) {
                $field = $fieldMap->get($fieldId);
                if (! $field) {
                    continue;
                }

                $stored = $this->normalizeValue($value, $field, $fieldId);
                if ($stored === null || $stored === '') {
                    continue; // never blank an existing answer because a column was empty/unmapped
                }

                $submission->values()->updateOrCreate(
                    ['form_field_id' => $field->id],
                    ['field_key_snapshot' => $field->field_key, 'value' => $stored],
                );
            }

            $submission->update([
                'import_batch_id' => $importBatchId ?? $submission->import_batch_id,
                'last_edited_at'  => now(),   // submitted_at is history: it is NOT overwritten
                'version'         => $submission->version + 1,
            ]);
        });

        $this->sync->changed($submission, flush: $dispatchEvent);

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
        $existing = FormSubmission::where('form_id', $form->id)
            ->where('cosplayer_id', $cosplayer->id)
            ->first();

        if ($existing && $allowUpdate) {
            return $this->update($existing, $values, $importBatchId, $dispatchEvent, $source);
        }

        // (form_id, cosplayer_id) is unique: a second row would be a constraint error.
        // Callers that chose "skip" never get here for duplicates; guard anyway.
        if ($existing) {
            return $existing;
        }

        return $this->write($form, $cosplayer, $values, $source, $importBatchId, $dispatchEvent);
    }

    private function normalizeValue(mixed $value, FormField $field, mixed $fieldId): ?string
    {
        // Multi-choice answers are stored as JSON (decoded again by SubmissionPresenter), exactly like the public form.
        if (in_array($field->field_type, FormField::MULTI_TYPES, true)) {
            if (is_string($value) && $value !== '' && ! str_starts_with(trim($value), '[')) {
                $value = array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));
            }
        }

        if (is_array($value)) {
            return $value === [] ? null : json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if (! $value instanceof UploadedFile) {
            return $value === null ? null : (string) $value;
        }

        $mime    = $value->getMimeType() ?? '';
        $isImage = ($field->field_type === 'image')
            || ($field->field_type === 'file' && ImageOptimizer::isOptimisableImage($mime));

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
