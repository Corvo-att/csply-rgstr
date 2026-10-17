<?php

namespace App\Services\GoogleSheets;

use App\Models\GoogleConnection;

interface SheetsGateway
{
    /**
     * Create a new Google Spreadsheet and return metadata.
     *
     * @return array{spreadsheet_id: string, spreadsheet_url: string, sheet_gid: int, sheet_title: string}
     */
    public function createSpreadsheet(GoogleConnection $connection, string $title, string $sheetTitle = 'Responses'): array;

    /**
     * Add a new worksheet tab to an existing spreadsheet.
     *
     * @return array{sheet_gid: int, sheet_title: string}
     */
    public function addTab(GoogleConnection $connection, string $spreadsheetId, string $tabTitle): array;

    /**
     * List all worksheet tabs in a spreadsheet.
     *
     * @return array<int, array{sheet_gid: int, title: string, index: int}>
     */
    public function listTabs(GoogleConnection $connection, string $spreadsheetId): array;

    /**
     * Read the header row from a given worksheet tab.
     *
     * @return array<int, string>
     */
    public function readHeaderRow(GoogleConnection $connection, string $spreadsheetId, string $tabTitle, int $headerRow = 1): array;

    /**
     * Write or overwrite the header row in a given worksheet tab.
     *
     * @param array<int, string> $headers
     */
    public function writeHeaderRow(GoogleConnection $connection, string $spreadsheetId, string $tabTitle, array $headers, int $headerRow = 1): void;

    /**
     * Freeze the header row and format with distinct styling (bold text, background accent).
     */
    public function freezeAndStyleHeader(GoogleConnection $connection, string $spreadsheetId, int $sheetGid, int $columnCount, int $headerRow = 1): void;

    /**
     * Append rows to a given worksheet tab.
     *
     * @param array<int, array<int, mixed>> $rows
     * @return array{updatedRange: string, updatedRows: int}
     */
    public function appendRows(GoogleConnection $connection, string $spreadsheetId, string $tabTitle, array $rows): array;

    /**
     * Update an exact range of cells in a worksheet tab.
     *
     * @param array<int, array<int, mixed>> $values
     */
    public function updateRange(GoogleConnection $connection, string $spreadsheetId, string $range, array $values): void;
}
