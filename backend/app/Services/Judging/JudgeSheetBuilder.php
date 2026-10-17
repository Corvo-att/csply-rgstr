<?php

namespace App\Services\Judging;

use App\Models\Form;
use App\Models\GoogleConnection;
use App\Models\JudgingRubric;
use App\Models\JudgingSheet;
use App\Services\GoogleSheets\SheetsGateway;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JudgeSheetBuilder
{
    public function __construct(private readonly SheetsGateway $gateway) {}

    /**
     * Build the PhpSpreadsheet object containing Roster, Per-Judge tabs, and Results leaderboard.
     */
    public function buildSpreadsheet(
        Form $form,
        JudgingRubric $rubric,
        array $judgeNames,
        array $options = []
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet();
        $scoreMode = $options['score_mode'] ?? 'average'; // 'average' | 'sum'

        // Load submissions with cosplayers and dynamic field values
        $submissions = $form->submissions()
            ->with(['cosplayer.user', 'values.field'])
            ->orderBy('id', 'asc')
            ->get();

        $criteria = $rubric->criteria()->orderBy('sort_order')->get();
        if ($criteria->isEmpty()) {
            // Default criteria if none defined
            $criteria = collect([
                (object) ['name' => 'Craftsmanship & Accuracy', 'max_points' => 35],
                (object) ['name' => 'Stage Presence & Performance', 'max_points' => 30],
                (object) ['name' => 'Prop & Detailing', 'max_points' => 20],
                (object) ['name' => 'Complexity & Difficulty', 'max_points' => 15],
            ]);
        }

        // ── 1. Tab: Roster ──────────────────────────────────────────────
        $rosterSheet = $spreadsheet->getActiveSheet();
        $rosterSheet->setTitle('Roster');

        $rosterHeaders = [
            'Contestant #',
            'Photo',
            'Cosplayer Name',
            'Character Name',
            'Series / Origin',
            'Experience Level',
            'Reference Photos',
            'Audio / Video',
            'Check-in Status',
        ];

        $this->applyHeaderStyle($rosterSheet, $rosterHeaders, '1E1B4B'); // Dark Indigo

        $rowIdx = 2;
        foreach ($submissions as $index => $sub) {
            $cos = $sub->cosplayer;
            $user = $cos?->user;
            $contestantNum = '#' . ($index + 1);
            $cosName = $user?->name ?: ($cos?->character_name ?: "Contestant {$sub->id}");
            $charName = $cos?->character_name ?: '—';
            $series = $cos?->series ?: '—';
            $level = $cos?->experience_level ?: '—';

            // Find image and file URLs from dynamic field values
            $photoUrl = '';
            $refUrl = '';
            $audioUrl = '';

            foreach ($sub->values as $val) {
                $type = $val->field?->field_type ?? '';
                $valStr = (string) $val->value;
                if (filter_var($valStr, FILTER_VALIDATE_URL)) {
                    if ($type === 'image' || preg_match('/\.(jpg|jpeg|png|webp)/i', $valStr)) {
                        if (empty($photoUrl)) {
                            $photoUrl = $valStr;
                        } else {
                            $refUrl = $valStr;
                        }
                    } elseif ($type === 'video' || preg_match('/\.(mp3|wav|mp4|mov|webm)/i', $valStr)) {
                        $audioUrl = $valStr;
                    }
                }
            }

            $rosterSheet->setCellValue("A{$rowIdx}", $contestantNum);

            if (!empty($photoUrl)) {
                $rosterSheet->setCellValue("B{$rowIdx}", '=IMAGE("' . $photoUrl . '")');
            } else {
                $rosterSheet->setCellValue("B{$rowIdx}", '—');
            }

            $rosterSheet->setCellValue("C{$rowIdx}", $cosName);
            $rosterSheet->setCellValue("D{$rowIdx}", $charName);
            $rosterSheet->setCellValue("E{$rowIdx}", $series);
            $rosterSheet->setCellValue("F{$rowIdx}", $level);

            if (!empty($refUrl)) {
                $rosterSheet->setCellValue("G{$rowIdx}", '=HYPERLINK("' . $refUrl . '", "View Reference")');
            } else {
                $rosterSheet->setCellValue("G{$rowIdx}", '—');
            }

            if (!empty($audioUrl)) {
                $rosterSheet->setCellValue("H{$rowIdx}", '=HYPERLINK("' . $audioUrl . '", "Play Media")');
            } else {
                $rosterSheet->setCellValue("H{$rowIdx}", '—');
            }

            $rosterSheet->setCellValue("I{$rowIdx}", 'Registered');

            // Format row height if photo present
            $rosterSheet->getRowDimension($rowIdx)->setRowHeight(55);
            $rowIdx++;
        }

        $lastRow = max(2, $rowIdx - 1);
        $this->autoFitColumns($rosterSheet, count($rosterHeaders));
        $rosterSheet->getColumnDimension('B')->setWidth(18); // Photo thumbnail width

        // ── 2. Per-Judge Scoring Tabs ───────────────────────────────────
        $judgeTabNames = [];
        foreach ($judgeNames as $judgeName) {
            $cleanName = 'Judge_' . Str::slug(trim($judgeName), '_');
            $judgeTabNames[$judgeName] = $cleanName;

            $judgeSheet = $spreadsheet->createSheet();
            $judgeSheet->setTitle(substr($cleanName, 0, 31));

            // Judge headers
            $judgeHeaders = ['Contestant #', 'Cosplayer', 'Character'];
            foreach ($criteria as $crit) {
                $judgeHeaders[] = "{$crit->name} (max {$crit->max_points})";
            }
            $totalColIndex = count($judgeHeaders) + 1;
            $totalColLetter = Coordinate::stringFromColumnIndex($totalColIndex);
            $judgeHeaders[] = 'TOTAL SCORE';
            $judgeHeaders[] = 'Judge Notes & Feedback';

            $this->applyHeaderStyle($judgeSheet, $judgeHeaders, '4338CA'); // Indigo-700

            $firstCritCol = 'D';
            $lastCritCol = Coordinate::stringFromColumnIndex(count($criteria) + 3);

            for ($r = 2; $r <= $lastRow; $r++) {
                $judgeSheet->setCellValue("A{$r}", "=Roster!A{$r}");
                $judgeSheet->setCellValue("B{$r}", "=Roster!C{$r}");
                $judgeSheet->setCellValue("C{$r}", "=Roster!D{$r}");

                // Total formula summing all criteria
                $judgeSheet->setCellValue("{$totalColLetter}{$r}", "=SUM({$firstCritCol}{$r}:{$lastCritCol}{$r})");

                // Highlight total column in soft accent
                $judgeSheet->getStyle("{$totalColLetter}{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '4338CA']],
                ]);
            }

            $this->autoFitColumns($judgeSheet, count($judgeHeaders));
            $notesColLetter = Coordinate::stringFromColumnIndex($totalColIndex + 1);
            $judgeSheet->getColumnDimension($notesColLetter)->setWidth(30);
        }

        // ── 3. Results / Leaderboard Tab ────────────────────────────────
        $resultsSheet = $spreadsheet->createSheet();
        $resultsSheet->setTitle('Results');

        $resultHeaders = ['Rank', 'Contestant #', 'Cosplayer', 'Character', 'FINAL SCORE'];
        foreach ($judgeNames as $jName) {
            $resultHeaders[] = "{$jName} (Score)";
        }

        $this->applyHeaderStyle($resultsSheet, $resultHeaders, '047857'); // Emerald-700

        for ($r = 2; $r <= $lastRow; $r++) {
            $resultsSheet->setCellValue("A{$r}", "=RANK(E{$r}, E$2:E\${$lastRow})");
            $resultsSheet->setCellValue("B{$r}", "=Roster!A{$r}");
            $resultsSheet->setCellValue("C{$r}", "=Roster!C{$r}");
            $resultsSheet->setCellValue("D{$r}", "=Roster!D{$r}");

            $judgeScoreCells = [];
            $cIdx = 6;
            foreach ($judgeTabNames as $jName => $tTitle) {
                $totalColLetter = Coordinate::stringFromColumnIndex(count($criteria) + 4);
                $cellLetter = Coordinate::stringFromColumnIndex($cIdx);
                $resultsSheet->setCellValue("{$cellLetter}{$r}", "='{$tTitle}'!{$totalColLetter}{$r}");
                $judgeScoreCells[] = "{$cellLetter}{$r}";
                $cIdx++;
            }

            // Final score formula (AVERAGE or SUM of judge scores)
            $firstJudgeCell = Coordinate::stringFromColumnIndex(6) . $r;
            $lastJudgeCell = Coordinate::stringFromColumnIndex(5 + count($judgeNames)) . $r;

            if ($scoreMode === 'sum') {
                $resultsSheet->setCellValue("E{$r}", "=SUM({$firstJudgeCell}:{$lastJudgeCell})");
            } else {
                $resultsSheet->setCellValue("E{$r}", "=AVERAGE({$firstJudgeCell}:{$lastJudgeCell})");
            }

            // Bold styling on Rank and Final Score
            $resultsSheet->getStyle("A{$r}")->getFont()->setBold(true);
            $resultsSheet->getStyle("E{$r}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '047857']],
            ]);
        }

        $this->autoFitColumns($resultsSheet, count($resultHeaders));

        // Set Roster as default active tab
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Download as offline .xlsx file.
     */
    public function downloadOfflineXlsx(
        Form $form,
        JudgingRubric $rubric,
        array $judgeNames,
        array $options = []
    ): StreamedResponse {
        $spreadsheet = $this->buildSpreadsheet($form, $rubric, $judgeNames, $options);
        $fileName = 'judging_sheet_' . Str::slug($form->name) . '_' . date('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Generate live Google Sheet with all tabs and formulas, saving record in judging_sheets table.
     */
    public function exportToGoogle(
        GoogleConnection $connection,
        Form $form,
        JudgingRubric $rubric,
        array $judgeNames,
        array $options = []
    ): JudgingSheet {
        $title = "Judging Sheet: {$form->name} (" . date('M j, Y') . ")";
        $spreadsheet = $this->buildSpreadsheet($form, $rubric, $judgeNames, $options);

        // 1. Create Google Spreadsheet
        $meta = $this->gateway->createSpreadsheet($connection, $title, 'Roster');
        $spreadsheetId = $meta['spreadsheet_id'];
        $spreadsheetUrl = $meta['spreadsheet_url'];

        // 2. Iterate each tab in PhpSpreadsheet and sync to Google Sheets
        $sheetCount = $spreadsheet->getSheetCount();
        for ($i = 0; $i < $sheetCount; $i++) {
            $sheet = $spreadsheet->getSheet($i);
            $tabTitle = $sheet->getTitle();

            if ($i > 0) {
                // Add tab to Google Sheet
                $this->gateway->addTab($connection, $spreadsheetId, $tabTitle);
            }

            // Extract values and formulas
            $highestColumn = $sheet->getHighestColumn();
            $highestRow = $sheet->getHighestRow();
            $data = $sheet->rangeToArray("A1:{$highestColumn}{$highestRow}", null, false, false);

            $this->gateway->updateRange(
                $connection,
                $spreadsheetId,
                "'{$tabTitle}'!A1:{$highestColumn}{$highestRow}",
                $data
            );
        }

        // 3. Save JudgingSheet record
        return JudgingSheet::create([
            'admin_id'             => Auth::guard('admin')->id(),
            'form_id'              => $form->id,
            'judging_rubric_id'    => $rubric->id,
            'title'                => $title,
            'google_connection_id' => $connection->id,
            'spreadsheet_id'       => $spreadsheetId,
            'spreadsheet_url'      => $spreadsheetUrl,
            'judge_names'          => $judgeNames,
            'options'              => $options,
            'status'               => 'generated',
        ]);
    }

    private function applyHeaderStyle($sheet, array $headers, string $hexBgColor): void
    {
        $colIdx = 1;
        foreach ($headers as $h) {
            $letter = Coordinate::stringFromColumnIndex($colIdx);
            $sheet->setCellValue("{$letter}1", $h);
            $colIdx++;
        }

        $lastLetter = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastLetter}1")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $hexBgColor]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->freezePane('A2');
    }

    private function autoFitColumns($sheet, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $letter = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }
    }
}
