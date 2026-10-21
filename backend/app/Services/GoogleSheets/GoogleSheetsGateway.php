<?php

namespace App\Services\GoogleSheets;

use App\Models\GoogleConnection;
use App\Models\SheetColumnMapping;
use Google\Service\Sheets as SheetsService;
use Google\Service\Sheets\AddSheetRequest;
use Google\Service\Sheets\BatchUpdateValuesRequest;
use Google\Service\Sheets\ClearValuesRequest;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\CellData;
use Google\Service\Sheets\CellFormat;
use Google\Service\Sheets\Color;
use Google\Service\Sheets\GridCoordinate;
use Google\Service\Sheets\GridProperties;
use Google\Service\Sheets\GridRange;
use Google\Service\Sheets\RepeatCellRequest;
use Google\Service\Sheets\Request as SheetBatchRequest;
use Google\Service\Sheets\Sheet;
use Google\Service\Sheets\SheetProperties;
use Google\Service\Sheets\Spreadsheet;
use Google\Service\Sheets\TextFormat;
use Google\Service\Sheets\UpdateSheetPropertiesRequest;
use Google\Service\Sheets\ValueRange;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleSheetsGateway implements SheetsGateway
{
    public function __construct(private readonly GoogleClientFactory $clientFactory) {}

    private function getSheetsService(GoogleConnection $connection): SheetsService
    {
        $client = $this->clientFactory->createForConnection($connection);
        return new SheetsService($client);
    }

    /**
     * Create a new Google Spreadsheet and return metadata.
     */
    public function createSpreadsheet(GoogleConnection $connection, string $title, string $sheetTitle = 'Responses'): array
    {
        $service = $this->getSheetsService($connection);

        $initialSheet = new Sheet([
            'properties' => new SheetProperties([
                'title'   => $sheetTitle,
                'sheetId' => 0,
            ]),
        ]);

        $spreadsheet = new Spreadsheet([
            'properties' => [
                'title' => $title,
            ],
            'sheets' => [$initialSheet],
        ]);

        $created = $service->spreadsheets->create($spreadsheet);
        $spreadsheetId  = $created->getSpreadsheetId();
        $spreadsheetUrl = $created->getSpreadsheetUrl();

        $sheets = $created->getSheets();
        $firstGid = 0;
        $actualTitle = $sheetTitle;
        if (!empty($sheets) && isset($sheets[0])) {
            $props = $sheets[0]->getProperties();
            $firstGid = (int) $props->getSheetId();
            $actualTitle = $props->getTitle();
        }

        return [
            'spreadsheet_id'  => $spreadsheetId,
            'spreadsheet_url' => $spreadsheetUrl,
            'sheet_gid'       => $firstGid,
            'sheet_title'     => $actualTitle,
        ];
    }

    /**
     * Add a new worksheet tab to an existing spreadsheet.
     */
    public function addTab(GoogleConnection $connection, string $spreadsheetId, string $tabTitle): array
    {
        $service = $this->getSheetsService($connection);

        $addSheetRequest = new AddSheetRequest([
            'properties' => new SheetProperties([
                'title' => $tabTitle,
            ]),
        ]);

        $batchRequest = new BatchUpdateSpreadsheetRequest([
            'requests' => [
                new SheetBatchRequest(['addSheet' => $addSheetRequest]),
            ],
        ]);

        $response = $service->spreadsheets->batchUpdate($spreadsheetId, $batchRequest);
        $replies = $response->getReplies();

        $newProps = $replies[0]->getAddSheet()->getProperties();

        return [
            'sheet_gid'   => (int) $newProps->getSheetId(),
            'sheet_title' => $newProps->getTitle(),
        ];
    }

    /**
     * List all worksheet tabs in a spreadsheet.
     */
    public function listTabs(GoogleConnection $connection, string $spreadsheetId): array
    {
        $service = $this->getSheetsService($connection);
        $spreadsheet = $service->spreadsheets->get($spreadsheetId, [
            'fields' => 'sheets.properties',
        ]);

        $tabs = [];
        foreach ($spreadsheet->getSheets() as $sheet) {
            $props = $sheet->getProperties();
            $tabs[] = [
                'sheet_gid' => (int) $props->getSheetId(),
                'title'     => $props->getTitle(),
                'index'     => (int) $props->getIndex(),
            ];
        }

        return $tabs;
    }

    /**
     * Read the header row from a given worksheet tab.
     */
    public function readHeaderRow(GoogleConnection $connection, string $spreadsheetId, string $tabTitle, int $headerRow = 1): array
    {
        $service = $this->getSheetsService($connection);
        $range = "'" . str_replace("'", "''", $tabTitle) . "'!{$headerRow}:{$headerRow}";

        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues();

        if (empty($values) || !isset($values[0])) {
            return [];
        }

        return array_map('strval', $values[0]);
    }

    /**
     * Write or overwrite the header row in a given worksheet tab.
     */
    public function writeHeaderRow(GoogleConnection $connection, string $spreadsheetId, string $tabTitle, array $headers, int $headerRow = 1): void
    {
        if (empty($headers)) {
            return;
        }

        $service = $this->getSheetsService($connection);
        $lastColLetter = SheetColumnMapping::indexToLetter(count($headers) - 1);
        $range = "'" . str_replace("'", "''", $tabTitle) . "'!A{$headerRow}:{$lastColLetter}{$headerRow}";

        $valueRange = new ValueRange([
            'values' => [array_values($headers)],
        ]);

        $service->spreadsheets_values->update($spreadsheetId, $range, $valueRange, [
            'valueInputOption' => 'USER_ENTERED',
        ]);
    }

    /**
     * Freeze the header row and format with distinct styling (Navy header, bold white text).
     */
    public function freezeAndStyleHeader(GoogleConnection $connection, string $spreadsheetId, int $sheetGid, int $columnCount, int $headerRow = 1): void
    {
        if ($columnCount <= 0) {
            return;
        }

        $service = $this->getSheetsService($connection);

        // 1. Freeze top row
        $freezeRequest = new UpdateSheetPropertiesRequest([
            'properties' => new SheetProperties([
                'sheetId'        => $sheetGid,
                'gridProperties' => new GridProperties([
                    'frozenRowCount' => $headerRow,
                ]),
            ]),
            'fields' => 'gridProperties.frozenRowCount',
        ]);

        // 2. Format header background and text (Dark Navy #0A1128, text white #FFFFFF, bold)
        $styleRange = new GridRange([
            'sheetId'          => $sheetGid,
            'startRowIndex'    => $headerRow - 1,
            'endRowIndex'      => $headerRow,
            'startColumnIndex' => 0,
            'endColumnIndex'   => $columnCount,
        ]);

        $formatCell = new CellData([
            'userEnteredFormat' => new CellFormat([
                'backgroundColor' => new Color([
                    'red'   => 10 / 255,
                    'green' => 17 / 255,
                    'blue'  => 40 / 255,
                ]),
                'textFormat' => new TextFormat([
                    'foregroundColor' => new Color([
                        'red'   => 1.0,
                        'green' => 1.0,
                        'blue'  => 1.0,
                    ]),
                    'bold'     => true,
                    'fontSize' => 10,
                ]),
            ]),
        ]);

        $repeatFormatRequest = new RepeatCellRequest([
            'range'  => $styleRange,
            'cell'   => $formatCell,
            'fields' => 'userEnteredFormat(backgroundColor,textFormat)',
        ]);

        $batchRequest = new BatchUpdateSpreadsheetRequest([
            'requests' => [
                new SheetBatchRequest(['updateSheetProperties' => $freezeRequest]),
                new SheetBatchRequest(['repeatCell' => $repeatFormatRequest]),
            ],
        ]);

        $service->spreadsheets->batchUpdate($spreadsheetId, $batchRequest);
    }

    /**
     * Append rows to a given worksheet tab.
     */
    public function appendRows(GoogleConnection $connection, string $spreadsheetId, string $tabTitle, array $rows): array
    {
        if (empty($rows)) {
            return ['updatedRange' => '', 'updatedRows' => 0];
        }

        $service = $this->getSheetsService($connection);
        $escapedTitle = str_replace("'", "''", $tabTitle);
        $range = "'{$escapedTitle}'!A:A";

        // Sanitize values to prevent formula injection
        $sanitizedRows = [];
        foreach ($rows as $row) {
            $sanitizedRow = [];
            foreach ($row as $cell) {
                $sanitizedRow[] = CellSanitizer::sanitize($cell, allowFormulas: true);
            }
            $sanitizedRows[] = $sanitizedRow;
        }

        $valueRange = new ValueRange([
            'values' => $sanitizedRows,
        ]);

        $response = $service->spreadsheets_values->append($spreadsheetId, $range, $valueRange, [
            'valueInputOption' => 'USER_ENTERED',
            'insertDataOption' => 'INSERT_ROWS',
        ]);

        $updates = $response->getUpdates();

        return [
            'updatedRange' => $updates?->getUpdatedRange() ?? '',
            'updatedRows'  => (int) ($updates?->getUpdatedRows() ?? count($rows)),
        ];
    }

    /**
     * Update an exact range of cells in a worksheet tab.
     */
    public function updateRange(GoogleConnection $connection, string $spreadsheetId, string $range, array $values): void
    {
        if (empty($values)) {
            return;
        }

        $service = $this->getSheetsService($connection);
        $valueRange = new ValueRange([
            'values' => $values,
        ]);

        $service->spreadsheets_values->update($spreadsheetId, $range, $valueRange, [
            'valueInputOption' => 'USER_ENTERED',
        ]);
    }

    private function quoteTab(string $tabTitle): string
    {
        return "'" . str_replace("'", "''", $tabTitle) . "'";
    }

    public function readColumn(GoogleConnection $connection, string $spreadsheetId, string $tabTitle, string $columnLetter, int $startRow): array
    {
        $service = $this->getSheetsService($connection);
        $range = $this->quoteTab($tabTitle) . "!{$columnLetter}{$startRow}:{$columnLetter}";

        $values = $service->spreadsheets_values->get($spreadsheetId, $range, [
            'valueRenderOption' => 'UNFORMATTED_VALUE',
        ])->getValues() ?? [];

        // Google trims trailing blanks and returns [] for blank cells in the middle.
        return array_map(fn ($row) => isset($row[0]) ? (string) (is_float($row[0]) && floor($row[0]) == $row[0] ? (int) $row[0] : $row[0]) : '', $values);
    }

    public function readRows(GoogleConnection $connection, string $spreadsheetId, string $tabTitle, int $startRow): array
    {
        $service = $this->getSheetsService($connection);
        $range = $this->quoteTab($tabTitle) . "!A{$startRow}:ZZ";

        return $service->spreadsheets_values->get($spreadsheetId, $range, [
            'valueRenderOption' => 'UNFORMATTED_VALUE',
        ])->getValues() ?? [];
    }

    public function batchUpdateRows(GoogleConnection $connection, string $spreadsheetId, string $tabTitle, array $rowsByNumber, array $columnIndexes): void
    {
        if (empty($rowsByNumber) || empty($columnIndexes)) {
            return;
        }

        // Group the writable columns into contiguous runs (A:C, E:E, ...) so cells in the gaps are never overwritten.
        $columnIndexes = array_values(array_unique($columnIndexes));
        sort($columnIndexes);
        $runs = [];
        foreach ($columnIndexes as $idx) {
            $last = count($runs) - 1;
            if ($last >= 0 && $runs[$last][1] === $idx - 1) {
                $runs[$last][1] = $idx;
            } else {
                $runs[] = [$idx, $idx];
            }
        }

        $tab = $this->quoteTab($tabTitle);
        $data = [];
        foreach ($rowsByNumber as $rowNumber => $cells) {
            foreach ($runs as [$from, $to]) {
                $values = [];
                for ($c = $from; $c <= $to; $c++) {
                    $values[] = CellSanitizer::sanitize($cells[$c] ?? '', allowFormulas: true);
                }
                $data[] = new ValueRange([
                    'range'  => "{$tab}!" . SheetColumnMapping::indexToLetter($from) . $rowNumber . ':' . SheetColumnMapping::indexToLetter($to) . $rowNumber,
                    'values' => [$values],
                ]);
            }
        }

        $service = $this->getSheetsService($connection);
        foreach (array_chunk($data, 500) as $chunk) {
            $service->spreadsheets_values->batchUpdate($spreadsheetId, new BatchUpdateValuesRequest([
                'valueInputOption' => 'USER_ENTERED',
                'data'             => $chunk,
            ]));
        }
    }

    public function clearDataRows(GoogleConnection $connection, string $spreadsheetId, string $tabTitle, int $fromRow): void
    {
        $service = $this->getSheetsService($connection);
        $service->spreadsheets_values->clear(
            $spreadsheetId,
            $this->quoteTab($tabTitle) . "!A{$fromRow}:ZZ",
            new ClearValuesRequest()
        );
    }
}
