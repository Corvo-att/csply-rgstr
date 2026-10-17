<?php

namespace App\Services\Import;

use App\Models\ImportBatch;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportErrorReportGenerator
{
    /**
     * Generate an Excel file containing all failed/invalid rows and their errors.
     */
    public function downloadErrorReport(ImportBatch $batch): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import Errors');

        $rows = $batch->rows()
            ->whereIn('status', ['invalid', 'duplicate'])
            ->orderBy('row_number')
            ->get();

        if ($rows->isEmpty()) {
            $sheet->setCellValue('A1', 'No errors found in this import batch.');
            $fileName = "import_{$batch->id}_errors_none.xlsx";
            return response()->streamDownload(function () use ($spreadsheet) {
                (new Xlsx($spreadsheet))->save('php://output');
            }, $fileName);
        }

        // Determine all raw column headers from the first row
        $firstRaw = $rows->first()->raw ?? [];
        $rawHeaders = array_keys($firstRaw);

        $headers = array_merge(['Row #', 'Status', 'Validation Errors'], $rawHeaders);

        // Write header row
        $colIdx = 1;
        foreach ($headers as $h) {
            $letter = Coordinate::stringFromColumnIndex($colIdx);
            $sheet->setCellValue("{$letter}1", $h);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
            $colIdx++;
        }

        // Apply error header styling (Rose-600)
        $lastLetter = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastLetter}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E11D48']],
        ]);

        // Write data rows
        $r = 2;
        foreach ($rows as $row) {
            $errors = [];
            if (!empty($row->errors)) {
                foreach ($row->errors as $field => $msgs) {
                    $errors[] = "{$field}: " . implode(', ', (array) $msgs);
                }
            }
            $errorStr = implode(' | ', $errors);

            $sheet->setCellValue("A{$r}", $row->row_number);
            $sheet->setCellValue("B{$r}", strtoupper($row->status));
            $sheet->setCellValue("C{$r}", $errorStr);

            $raw = $row->raw ?? [];
            $c = 4;
            foreach ($rawHeaders as $rh) {
                $letter = Coordinate::stringFromColumnIndex($c);
                $sheet->setCellValue("{$letter}{$r}", $raw[$rh] ?? '');
                $c++;
            }
            $r++;
        }

        $fileName = "import_batch_{$batch->id}_errors.xlsx";

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
