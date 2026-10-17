<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ParseImportFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public int $batchId)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $batch = ImportBatch::with('form.fields')->find($this->batchId);
        if (!$batch) {
            return;
        }

        $filePath = storage_path('app/' . $batch->stored_path);
        if (!file_exists($filePath)) {
            $batch->update(['status' => 'failed']);
            Log::error("Import batch file not found: {$filePath}");
            return;
        }

        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);

            $sheet = !empty($batch->sheet_name)
                ? ($spreadsheet->getSheetByName($batch->sheet_name) ?: $spreadsheet->getActiveSheet())
                : $spreadsheet->getActiveSheet();

            $sheetName = $sheet->getTitle();
            $headerRowNumber = $batch->header_row ?: 1;
            $highestColumn = $sheet->getHighestColumn();
            $highestRow = $sheet->getHighestRow();

            // Read header row
            $headerRowData = $sheet->rangeToArray("A{$headerRowNumber}:{$highestColumn}{$headerRowNumber}", null, true, false)[0] ?? [];
            $headers = [];
            foreach ($headerRowData as $colIndex => $colVal) {
                $trimmed = trim((string) $colVal);
                if ($trimmed !== '') {
                    $headers[$colIndex] = $trimmed;
                }
            }

            // Clean previous rows if re-parsing
            $batch->rows()->delete();

            $insertBuffer = [];
            $totalCount = 0;

            for ($r = $headerRowNumber + 1; $r <= $highestRow; $r++) {
                $rowData = $sheet->rangeToArray("A{$r}:{$highestColumn}{$r}", null, true, false)[0] ?? [];
                
                // Skip if completely empty row
                $hasContent = false;
                foreach ($rowData as $val) {
                    if ($val !== null && trim((string) $val) !== '') {
                        $hasContent = true;
                        break;
                    }
                }

                if (!$hasContent) {
                    continue;
                }

                $raw = [];
                foreach ($rowData as $cIdx => $val) {
                    $headerName = $headers[$cIdx] ?? "Col_" . ($cIdx + 1);
                    $raw[$headerName] = $val !== null ? (string) $val : '';
                }

                $insertBuffer[] = [
                    'import_batch_id'    => $batch->id,
                    'row_number'         => $r,
                    'raw'                => json_encode($raw),
                    'normalized'         => null,
                    'errors'             => null,
                    'status'             => 'pending',
                    'form_submission_id' => null,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ];
                $totalCount++;

                if (count($insertBuffer) >= 500) {
                    ImportRow::insert($insertBuffer);
                    $insertBuffer = [];
                }
            }

            if (!empty($insertBuffer)) {
                ImportRow::insert($insertBuffer);
            }

            // Propose initial column mapping if none set
            $columnMap = $batch->column_map ?: $this->proposeColumnMap(array_values($headers), $batch->form);

            $batch->update([
                'sheet_name' => $sheetName,
                'column_map' => $columnMap,
                'total_rows' => $totalCount,
                'status'     => 'mapping',
            ]);

            Log::info("Parsed import batch {$batch->id}: {$totalCount} rows from '{$sheetName}'");

        } catch (\Throwable $e) {
            Log::error("Failed to parse import batch {$batch->id}: " . $e->getMessage());
            $batch->update(['status' => 'failed']);
            throw $e;
        }
    }

    /**
     * Heuristically match spreadsheet headers to target form / identity fields.
     */
    private function proposeColumnMap(array $headers, $form): array
    {
        $map = [];
        $targetFields = [
            'email'            => ['email', 'e-mail', 'mail', 'email address'],
            'name'             => ['name', 'full name', 'cosplayer name', 'applicant name', 'username'],
            'character_name'   => ['character', 'character name', 'char name', 'character_name'],
            'series'           => ['series', 'origin', 'franchise', 'source', 'anime', 'game'],
            'experience_level' => ['experience', 'experience level', 'level', 'skill level'],
            'bio'              => ['bio', 'about', 'description', 'notes'],
        ];

        // Dynamic form fields
        foreach ($form->fields as $field) {
            $key = "field_{$field->id}";
            $targetFields[$key] = [
                strtolower($field->field_key),
                strtolower($field->label),
            ];
        }

        foreach ($targetFields as $targetKey => $synonyms) {
            foreach ($headers as $header) {
                $headerClean = strtolower(trim($header));
                foreach ($synonyms as $synonym) {
                    if ($headerClean === $synonym || str_contains($headerClean, $synonym)) {
                        $map[$targetKey] = $header;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }
}
