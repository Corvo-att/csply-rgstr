<?php

namespace App\Services\Import;

use App\Models\Form;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportTemplateGenerator
{
    /**
     * Generate an Excel template (.xlsx) customized for the specified Form.
     */
    public function downloadTemplate(Form $form): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($form->name, 0, 30));

        // Standard identity columns
        $columns = [
            ['header' => 'Email', 'example' => 'cosplayer@example.com', 'required' => true],
            ['header' => 'Name', 'example' => 'Jane Doe', 'required' => false],
            ['header' => 'Character Name', 'example' => 'Sailor Moon', 'required' => false],
            ['header' => 'Series', 'example' => 'Sailor Moon', 'required' => false],
            ['header' => 'Experience Level', 'example' => 'Intermediate', 'required' => false],
            ['header' => 'Bio', 'example' => 'Cosplayer since 2020', 'required' => false],
        ];

        // Dynamic form fields
        foreach ($form->fields()->orderBy('sort_order')->get() as $field) {
            $example = match ($field->field_type) {
                'number', 'rating', 'range' => '5',
                'date' => '2026-10-15',
                'dropdown', 'radio' => !empty($field->options['choices']) ? $field->options['choices'][0] : 'Option 1',
                'checkbox', 'toggle' => '1',
                'file', 'image' => 'https://example.com/photo.jpg',
                default => 'Sample text',
            };

            $columns[] = [
                'header'   => $field->label ?: $field->field_key,
                'example'  => $example,
                'required' => (bool) $field->is_required,
            ];
        }

        // Write Headers (Row 1) & Sample Data (Row 2)
        $colIndex = 1;
        foreach ($columns as $col) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            
            // Header
            $sheet->setCellValue("{$colLetter}1", $col['header'] . ($col['required'] ? ' *' : ''));
            
            // Example
            $sheet->setCellValue("{$colLetter}2", $col['example']);

            // Auto-width
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            $colIndex++;
        }

        // Header style
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($columns));
        $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'], // Indigo-600
            ],
        ]);

        $fileName = 'import_template_' . \Illuminate\Support\Str::slug($form->name) . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
