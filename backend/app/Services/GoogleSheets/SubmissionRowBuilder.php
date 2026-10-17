<?php

namespace App\Services\GoogleSheets;

use App\Models\FormSheetLink;
use App\Models\FormSubmission;
use Illuminate\Support\Str;

class SubmissionRowBuilder
{
    /**
     * Build an array of cells matching the active column mappings for a submission.
     *
     * @return array<int, mixed>
     */
    public function buildRow(FormSubmission $submission, FormSheetLink $link): array
    {
        $mappings = $link->activeMappings;
        if ($mappings->isEmpty()) {
            return [];
        }

        // Map submission values by field_key_snapshot and form_field_id
        $values = $submission->values;
        $valuesByKey = [];
        foreach ($values as $val) {
            if ($val->field_key_snapshot) {
                $valuesByKey[$val->field_key_snapshot] = $val->value;
            }
            if ($val->form_field_id) {
                $valuesByKey['id_' . $val->form_field_id] = $val->value;
            }
        }

        $cosplayer = $submission->cosplayer;
        $user      = $cosplayer?->user;

        $maxColIndex = $mappings->max('column_index') ?? 0;
        $row = array_fill(0, $maxColIndex + 1, '');

        foreach ($mappings as $mapping) {
            $colIndex = $mapping->column_index;
            $rawVal = '';

            if ($mapping->source_type === 'system') {
                $rawVal = match ($mapping->source_key) {
                    'submission_id'  => (string) $submission->id,
                    'submitted_at'   => ($submission->submitted_at ?? $submission->created_at)?->format('Y-m-d H:i:s') ?? '',
                    'cosplayer_name' => $user?->name ?? '—',
                    'email'          => $user?->email ?? '—',
                    'character'      => $cosplayer?->character_name ?? '—',
                    'series'         => $cosplayer?->series ?? '—',
                    default          => '',
                };
            } else {
                $rawVal = $valuesByKey[$mapping->source_key] ?? '';
            }

            $transformed = $this->applyTransform($rawVal, $mapping->transform);
            $row[$colIndex] = $transformed;
        }

        return $row;
    }

    /**
     * Apply column-specific transformation rules.
     */
    public function applyTransform(mixed $value, ?string $transform): mixed
    {
        if ($value === null || $value === '') {
            return '';
        }

        $str = (string) $value;

        switch ($transform) {
            case 'hyperlink':
                if (filter_var($str, FILTER_VALIDATE_URL)) {
                    $cleanUrl = str_replace('"', '""', $str);
                    return "=HYPERLINK(\"{$cleanUrl}\", \"View\")";
                }
                return CellSanitizer::sanitize($str);

            case 'image':
                if (filter_var($str, FILTER_VALIDATE_URL)) {
                    $cleanUrl = str_replace('"', '""', $str);
                    return "=IMAGE(\"{$cleanUrl}\")";
                }
                return CellSanitizer::sanitize($str);

            case 'boolean':
                $lower = strtolower(trim($str));
                if (in_array($lower, ['1', 'true', 'yes', 'on'], true)) {
                    return true;
                }
                if (in_array($lower, ['0', 'false', 'no', 'off'], true)) {
                    return false;
                }
                return CellSanitizer::sanitize($str);

            case 'date':
                // Pass ISO string, Sheets parses standard ISO with USER_ENTERED
                return $str;

            case 'text':
            case 'auto':
            default:
                return CellSanitizer::sanitize($str);
        }
    }

    /**
     * Compute a SHA-256 hash of the generated row data to detect duplicates / no-ops.
     */
    public function computeHash(array $row): string
    {
        return hash('sha256', json_encode($row));
    }
}
