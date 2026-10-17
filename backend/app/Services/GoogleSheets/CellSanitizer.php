<?php

namespace App\Services\GoogleSheets;

class CellSanitizer
{
    /**
     * Dangerous starting characters that trigger formula execution in spreadsheet applications.
     */
    private const INJECTION_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Sanitize a cell value to prevent CSV/Spreadsheet Formula Injection.
     * System formulas (like '=HYPERLINK(' or '=IMAGE(') are permitted if marked safe.
     */
    public static function sanitize(mixed $value, bool $allowFormulas = false): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return $value;
        }

        // If explicitly permitted system formulas (e.g. generated =HYPERLINK(...))
        if ($allowFormulas && self::isAllowedFormula($trimmed)) {
            return $value;
        }

        // If the first character matches any injection prefix, escape with a single quote
        $firstChar = $trimmed[0];
        if (in_array($firstChar, self::INJECTION_PREFIXES, true)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Check if a formula matches known safe generated formulas.
     */
    private static function isAllowedFormula(string $value): bool
    {
        $upper = strtoupper($value);
        return str_starts_with($upper, '=HYPERLINK(')
            || str_starts_with($upper, '=IMAGE(')
            || str_starts_with($upper, '=IF(')
            || str_starts_with($upper, '=AVERAGE(')
            || str_starts_with($upper, '=SUM(')
            || str_starts_with($upper, '=RANK(');
    }
}
