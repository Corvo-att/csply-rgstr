<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a CSV file straight to the browser row by row, so exporting thousands of
 * entries never loads them all into memory. Opens fine in Excel (UTF-8 BOM added).
 */
class CsvDownload
{
    /**
     * @param  string[]  $headings
     * @param  iterable<string[]>  $rows
     */
    public static function make(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map([self::class, 'safe'], $headings));

            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'safe'], $row));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Cells starting with = + - @ are executed as formulas by Excel ("CSV injection"):
     * a cosplayer could name their character =HYPERLINK(...). Prefix them with a quote.
     */
    public static function safe(mixed $cell): string
    {
        $cell = (string) ($cell ?? '');

        return $cell !== '' && in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$cell : $cell;
    }
}
