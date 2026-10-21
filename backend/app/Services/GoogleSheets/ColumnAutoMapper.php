<?php

namespace App\Services\GoogleSheets;

use App\Models\Form;
use App\Models\FormField;
use Illuminate\Support\Str;

class ColumnAutoMapper
{
    /**
     * Standard system columns available for inclusion in form sheets.
     */
    public const SYSTEM_COLUMNS = [
        'submission_id'   => ['label' => 'Submission ID',   'transform' => 'text'],
        'status'          => ['label' => 'Status',          'transform' => 'text'],
        'entry_number'    => ['label' => 'Entry #',         'transform' => 'text'],
        'submitted_at'    => ['label' => 'Submitted At',    'transform' => 'date'],
        'cosplayer_name'  => ['label' => 'Cosplayer Name',  'transform' => 'text'],
        'email'           => ['label' => 'Email',           'transform' => 'text'],
        'character'       => ['label' => 'Character',       'transform' => 'text'],
        'series'          => ['label' => 'Series',          'transform' => 'text'],
        'uuid'            => ['label' => 'UUID',            'transform' => 'text'],
    ];

    /**
     * Generate default sequential column mappings for a fresh spreadsheet.
     *
     * @param array<int, string>|null $includedSystemKeys
     * @return array<int, array{source_type: string, source_key: string, column_index: int, header_label: string, transform: string}>
     */
    public function generateDefaultMappings(Form $form, ?array $includedSystemKeys = null): array
    {
        $includedSystemKeys ??= ['submission_id', 'status', 'entry_number', 'submitted_at', 'cosplayer_name', 'email', 'character'];
        $mappings = [];
        $colIndex = 0;

        // 1. Add requested system columns
        foreach ($includedSystemKeys as $sysKey) {
            if (isset(self::SYSTEM_COLUMNS[$sysKey])) {
                $mappings[] = [
                    'source_type'   => 'system',
                    'source_key'    => $sysKey,
                    'column_index'  => $colIndex++,
                    'header_label'  => self::SYSTEM_COLUMNS[$sysKey]['label'],
                    'transform'     => self::SYSTEM_COLUMNS[$sysKey]['transform'],
                    'is_active'     => true,
                ];
            }
        }

        // 2. Add dynamic form fields in sort order
        $fields = $form->fields()->orderBy('sort_order')->get();
        foreach ($fields as $field) {
            $mappings[] = [
                'source_type'   => 'field',
                'source_key'    => $field->field_key,
                'column_index'  => $colIndex++,
                'header_label'  => $field->label,
                'transform'     => $this->detectDefaultTransform($field->field_type),
                'is_active'     => true,
            ];
        }

        return $mappings;
    }

    /**
     * Auto-match form fields and system columns against existing sheet headers.
     *
     * @param array<int, string> $existingHeaders
     * @return array<int, array{column_index: int, header_label: string, source_type: string, source_key: string, transform: string, confidence: string}>
     */
    public function matchHeaders(Form $form, array $existingHeaders): array
    {
        $fields = $form->fields()->orderBy('sort_order')->get();
        $results = [];
        $matchedSources = [];

        foreach ($existingHeaders as $colIdx => $rawHeader) {
            $cleanHeader = trim((string) $rawHeader);
            if ($cleanHeader === '') {
                continue;
            }

            $match = $this->findBestMatch($cleanHeader, $fields, $matchedSources);

            if ($match) {
                $matchedSources[] = $match['source_type'] . ':' . $match['source_key'];
                $results[] = [
                    'column_index' => $colIdx,
                    'header_label' => $cleanHeader,
                    'source_type'  => $match['source_type'],
                    'source_key'   => $match['source_key'],
                    'transform'    => $match['transform'],
                    'confidence'   => $match['confidence'],
                    'is_active'    => true,
                ];
            } else {
                $results[] = [
                    'column_index' => $colIdx,
                    'header_label' => $cleanHeader,
                    'source_type'  => 'field',
                    'source_key'   => '',
                    'transform'    => 'auto',
                    'confidence'   => 'none',
                    'is_active'    => false,
                ];
            }
        }

        return $results;
    }

    /**
     * Find best match for a given header label against system columns and form fields.
     */
    private function findBestMatch(string $header, $fields, array $alreadyMatched): ?array
    {
        $normHeader = $this->normalizeString($header);

        // 1. Check system columns
        foreach (self::SYSTEM_COLUMNS as $sysKey => $meta) {
            if (in_array("system:{$sysKey}", $alreadyMatched, true)) {
                continue;
            }

            $normLabel = $this->normalizeString($meta['label']);
            $normKey   = $this->normalizeString($sysKey);

            if ($normHeader === $normLabel || $normHeader === $normKey) {
                return [
                    'source_type' => 'system',
                    'source_key'  => $sysKey,
                    'transform'   => $meta['transform'],
                    'confidence'  => 'exact',
                ];
            }
        }

        // 2. Check form fields exact match
        foreach ($fields as $field) {
            if (in_array("field:{$field->field_key}", $alreadyMatched, true)) {
                continue;
            }

            $normLabel = $this->normalizeString($field->label);
            $normKey   = $this->normalizeString($field->field_key);

            if ($normHeader === $normLabel || $normHeader === $normKey) {
                return [
                    'source_type' => 'field',
                    'source_key'  => $field->field_key,
                    'transform'   => $this->detectDefaultTransform($field->field_type),
                    'confidence'  => 'exact',
                ];
            }
        }

        // 3. Fuzzy match (Levenshtein <= 2 or substring)
        foreach ($fields as $field) {
            if (in_array("field:{$field->field_key}", $alreadyMatched, true)) {
                continue;
            }

            $normLabel = $this->normalizeString($field->label);
            if (levenshtein($normHeader, $normLabel) <= 2 || str_contains($normLabel, $normHeader) || str_contains($normHeader, $normLabel)) {
                return [
                    'source_type' => 'field',
                    'source_key'  => $field->field_key,
                    'transform'   => $this->detectDefaultTransform($field->field_type),
                    'confidence'  => 'fuzzy',
                ];
            }
        }

        return null;
    }

    /**
     * Normalize a string for relaxed comparison.
     */
    public function normalizeString(string $str): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $str));
    }

    /**
     * Detect default transformation based on form field type.
     */
    public function detectDefaultTransform(string $fieldType): string
    {
        return match ($fieldType) {
            'image', 'file', 'video'     => 'hyperlink',
            'date', 'datetime-local'     => 'date',
            'checkbox', 'toggle', 'terms'=> 'boolean',
            'number', 'range', 'rating'  => 'text',
            default                      => 'auto',
        };
    }
}
