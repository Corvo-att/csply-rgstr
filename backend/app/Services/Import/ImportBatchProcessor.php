<?php

namespace App\Services\Import;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Support\Facades\Log;

class ImportBatchProcessor
{
    public function __construct(
        private readonly ImportNormalizer $normalizer,
        private readonly ImportValidator $validator,
    ) {}

    /**
     * Run normalization and validation on all rows in the batch using the active column map.
     */
    public function validateBatch(ImportBatch $batch): void
    {
        $batch->loadMissing('form.fields');
        $columnMap = $batch->column_map ?? [];
        $duplicateStrategy = $batch->options['duplicate_strategy'] ?? 'skip';

        $seenEmails = [];
        $validCount = 0;
        $invalidCount = 0;
        $skippedCount = 0;

        $batch->update(['status' => 'validating']);

        $rows = $batch->rows()->orderBy('row_number')->get();

        foreach ($rows as $row) {
            $raw = $row->raw ?? [];

            // Extract identity
            $identity = [
                'email'            => trim((string) ($raw[$columnMap['email'] ?? ''] ?? '')),
                'name'             => trim((string) ($raw[$columnMap['name'] ?? ''] ?? '')),
                'character_name'   => trim((string) ($raw[$columnMap['character_name'] ?? ''] ?? '')),
                'series'           => trim((string) ($raw[$columnMap['series'] ?? ''] ?? '')),
                'experience_level' => trim((string) ($raw[$columnMap['experience_level'] ?? ''] ?? '')),
                'bio'              => trim((string) ($raw[$columnMap['bio'] ?? ''] ?? '')),
            ];

            // Extract dynamic form fields
            $values = [];
            foreach ($batch->form->fields as $field) {
                $colHeader = $columnMap["field_{$field->id}"] ?? $columnMap[$field->field_key] ?? null;
                $rawVal = $colHeader ? ($raw[$colHeader] ?? null) : null;
                $values[$field->id] = $this->normalizer->normalize($rawVal, $field->field_type, $field->options);
            }

            $validation = $this->validator->validateRow(
                $batch->form,
                ['identity' => $identity, 'values' => $values],
                $seenEmails,
                $duplicateStrategy
            );

            $status = $validation['status'];
            if ($status === 'valid') {
                $validCount++;
            } elseif ($status === 'duplicate') {
                $skippedCount++;
            } else {
                $invalidCount++;
            }

            $row->update([
                'normalized' => ['identity' => $identity, 'values' => $values],
                'errors'     => !empty($validation['errors']) ? $validation['errors'] : null,
                'status'     => $status,
            ]);
        }

        $batch->update([
            'valid_rows'   => $validCount,
            'invalid_rows' => $invalidCount,
            'skipped_rows' => $skippedCount,
            'status'       => 'ready',
        ]);

        Log::info("Validated batch {$batch->id}: {$validCount} valid, {$invalidCount} invalid, {$skippedCount} skipped.");
    }

    /**
     * Re-validate a single row after admin manual inline edit.
     */
    public function validateSingleRow(ImportRow $row): void
    {
        $batch = $row->batch()->with('form.fields')->first();
        $seenEmails = [];
        $duplicateStrategy = $batch->options['duplicate_strategy'] ?? 'skip';

        $normalized = $row->normalized ?? [];
        $validation = $this->validator->validateRow($batch->form, $normalized, $seenEmails, $duplicateStrategy);

        $row->update([
            'errors' => !empty($validation['errors']) ? $validation['errors'] : null,
            'status' => $validation['status'],
        ]);

        // Refresh batch summary counts
        $batch->update([
            'valid_rows'   => $batch->rows()->where('status', 'valid')->count(),
            'invalid_rows' => $batch->rows()->where('status', 'invalid')->count(),
            'skipped_rows' => $batch->rows()->whereIn('status', ['skipped', 'duplicate'])->count(),
        ]);
    }
}
