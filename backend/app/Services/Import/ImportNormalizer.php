<?php

namespace App\Services\Import;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ImportNormalizer
{
    /**
     * Normalize a raw cell value based on field type and options.
     */
    public function normalize(mixed $value, string $targetType, ?array $options = null): mixed
    {
        if ($value === null) {
            return '';
        }

        // Trim string whitespace
        if (is_string($value)) {
            $value = trim(preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $value));
        }

        if ($value === '') {
            return '';
        }

        switch ($targetType) {
            case 'date':
            case 'datetime-local':
                return $this->normalizeDate($value, $targetType);

            case 'checkbox':
            case 'toggle':
            case 'terms':
                return $this->normalizeBoolean($value);

            case 'dropdown':
            case 'radio':
                return $this->normalizeChoice($value, $options['choices'] ?? []);

            case 'number':
            case 'rating':
            case 'range':
                return is_numeric($value) ? (string) $value : $value;

            default:
                return (string) $value;
        }
    }

    /**
     * Normalize Excel date serial numbers or date strings to ISO format.
     */
    private function normalizeDate(mixed $value, string $type): string
    {
        try {
            // Excel numeric serial date (e.g. 45210)
            if (is_numeric($value)) {
                $dt = ExcelDate::excelToDateTimeObject((float) $value);
                return $type === 'date' ? $dt->format('Y-m-d') : $dt->format('Y-m-d\TH:i');
            }

            // String date
            $parsed = Carbon::parse($value);
            return $type === 'date' ? $parsed->format('Y-m-d') : $parsed->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    /**
     * Normalize human-entered booleans to 1 or 0.
     */
    private function normalizeBoolean(mixed $value): string
    {
        $lower = strtolower((string) $value);
        if (in_array($lower, ['1', 'true', 'yes', 'y', 'on', '✓', 'check', 'checked'], true)) {
            return '1';
        }
        if (in_array($lower, ['0', 'false', 'no', 'n', 'off', 'uncheck'], true)) {
            return '0';
        }
        return (string) $value;
    }

    /**
     * Case-insensitively match dropdown/radio choices to canonical values.
     */
    private function normalizeChoice(mixed $value, array $choices): string
    {
        $valStr = (string) $value;
        foreach ($choices as $choice) {
            if (strcasecmp(trim($choice), trim($valStr)) === 0) {
                return $choice;
            }
        }
        return $valStr;
    }
}
