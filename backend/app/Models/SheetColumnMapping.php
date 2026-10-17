<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SheetColumnMapping extends Model
{
    protected $fillable = [
        'form_sheet_link_id',
        'source_type',
        'source_key',
        'column_index',
        'header_label',
        'transform',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'column_index' => 'integer',
            'is_active'    => 'boolean',
        ];
    }

    public function link()
    {
        return $this->belongsTo(FormSheetLink::class, 'form_sheet_link_id');
    }

    /**
     * Convert a 0-based column index to an A1-notation column letter (e.g., 0 -> A, 27 -> AB).
     */
    public static function indexToLetter(int $index): string
    {
        $letter = '';
        $index += 1; // 1-based for conversion

        while ($index > 0) {
            $modulo = ($index - 1) % 26;
            $letter = chr(65 + $modulo) . $letter;
            $index  = (int) (($index - $modulo) / 26);
        }

        return $letter;
    }

    /**
     * Convert an A1 column letter to 0-based index (e.g., 'A' -> 0, 'AB' -> 27).
     */
    public static function letterToIndex(string $letter): int
    {
        $letter = strtoupper(trim($letter));
        $length = strlen($letter);
        $index  = 0;

        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($letter[$i]) - 64);
        }

        return $index - 1;
    }

    public function getColumnLetterAttribute(): string
    {
        return self::indexToLetter($this->column_index);
    }
}
