<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\CommitImportBatch;
use App\Jobs\ParseImportFile;
use App\Jobs\RollbackImportBatch;
use App\Models\Form;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Services\Import\ImportBatchProcessor;
use App\Services\Import\ImportErrorReportGenerator;
use App\Services\Import\ImportTemplateGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ImportController extends Controller
{
    /**
     * Download pre-formatted Excel template for the form.
     */
    public function template(Form $form, ImportTemplateGenerator $generator)
    {
        return $generator->downloadTemplate($form);
    }

    /**
     * Upload an Excel/CSV file to begin an import batch.
     */
    public function store(Request $request, Form $form)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'], // max 20MB
        ]);

        $admin = Auth::guard('admin')->user();
        $file = $request->file('file');
        $originalFilename = $file->getClientOriginalName();
        $storedPath = $file->store('imports');
        $fullPath = storage_path('app/' . $storedPath);
        $fileHash = hash_file('sha256', $fullPath);

        $batch = ImportBatch::create([
            'admin_id'          => $admin->id,
            'form_id'           => $form->id,
            'event_id'          => $form->event_id,
            'original_filename' => $originalFilename,
            'stored_path'       => $storedPath,
            'file_hash'         => $fileHash,
            'status'            => 'uploaded',
            'options'           => [
                'duplicate_strategy' => 'skip',
            ],
        ]);

        // Parse file to read headers and rows
        (new ParseImportFile($batch->id))->handle();

        return redirect()->route('admin.imports.show', $batch->id)
            ->with('success', "File uploaded. Please confirm column mappings.");
    }

    /**
     * Connect a Google Sheet to begin an import batch.
     */
    public function storeGoogleSheet(Request $request, Form $form)
    {
        $request->validate([
            'spreadsheet_id' => ['required', 'string'],
            'sheet_name'     => ['nullable', 'string'],
            'header_row'     => ['nullable', 'integer', 'min:1'],
        ]);

        $admin = Auth::guard('admin')->user();
        $connection = \App\Models\GoogleConnection::where('admin_id', $admin->id)
            ->whereNull('revoked_at')
            ->first();

        if (! $connection || ! $connection->isConnected()) {
            return back()->withErrors(['google' => 'Please connect your Google Account first.']);
        }

        $batch = ImportBatch::create([
            'admin_id'             => $admin->id,
            'form_id'              => $form->id,
            'event_id'             => $form->event_id,
            'source_type'          => 'google_sheet',
            'spreadsheet_id'       => $request->spreadsheet_id,
            'sheet_name'           => $request->sheet_name ?: null,
            'header_row'           => (int) ($request->header_row ?: 1),
            'google_connection_id' => $connection->id,
            'status'               => 'uploaded',
            'options'              => [
                'duplicate_strategy' => 'skip',
            ],
        ]);

        // Parse Google Sheet rows
        (new ParseImportFile($batch->id))->handle();

        return redirect()->route('admin.imports.show', $batch->id)
            ->with('success', 'Google Sheet connected. Please confirm column mappings.');
    }

    /**
     * Show import wizard (mapping & dry-run validation preview).
     */
    public function show(Request $request, ImportBatch $batch, ImportBatchProcessor $processor)
    {
        $batch->load(['form.fields' => fn($q) => $q->orderBy('sort_order'), 'event', 'admin']);

        // If batch has a column map and is in mapping status, run initial validation
        if ($batch->status === 'mapping' && !empty($batch->column_map) && !empty($batch->column_map['email'])) {
            $processor->validateBatch($batch);
            $batch->refresh();
        }

        // Available headers from the first row raw keys
        $firstRow = $batch->rows()->first();
        $sheetHeaders = $firstRow ? array_keys($firstRow->raw ?? []) : [];

        // Filter rows by status if requested
        $statusFilter = $request->query('status', 'all');
        $rowsQuery = $batch->rows()->orderBy('row_number');

        if ($statusFilter !== 'all') {
            $rowsQuery->where('status', $statusFilter);
        }

        $rows = $rowsQuery->paginate(50)->withQueryString();

        return Inertia::render('Admin/Imports/Wizard', [
            'batch'        => $batch,
            'form'         => $batch->form,
            'event'        => $batch->event,
            'sheetHeaders' => $sheetHeaders,
            'rows'         => $rows,
            'statusFilter' => $statusFilter,
            'stats'        => [
                'total'    => $batch->total_rows,
                'valid'    => $batch->valid_rows,
                'invalid'  => $batch->invalid_rows,
                'skipped'  => $batch->skipped_rows,
                'imported' => $batch->imported_rows,
            ],
        ]);
    }

    /**
     * Update column mappings and duplicate strategy, then re-validate batch.
     */
    public function updateMapping(Request $request, ImportBatch $batch, ImportBatchProcessor $processor)
    {
        $request->validate([
            'column_map'                   => ['required', 'array'],
            'column_map.email'             => ['required', 'string'],
            'options'                      => ['nullable', 'array'],
            'options.duplicate_strategy'   => ['required', 'in:skip,update,create'],
        ]);

        $batch->update([
            'column_map' => $request->column_map,
            'options'    => $request->options ?? ['duplicate_strategy' => 'skip'],
        ]);

        $processor->validateBatch($batch);

        return back()->with('success', 'Mapping updated and data re-validated.');
    }

    /**
     * Inline update a single row and re-validate.
     */
    public function updateRow(Request $request, ImportRow $row, ImportBatchProcessor $processor)
    {
        $request->validate([
            'identity' => ['required', 'array'],
            'values'   => ['nullable', 'array'],
        ]);

        $row->update([
            'normalized' => [
                'identity' => $request->identity,
                'values'   => $request->values ?? [],
            ],
        ]);

        $processor->validateSingleRow($row);

        return back()->with('success', "Row #{$row->row_number} updated.");
    }

    /**
     * Commit valid rows to database and Google Sheet.
     */
    public function commit(ImportBatch $batch)
    {
        if ($batch->status !== 'ready' && $batch->status !== 'mapping') {
            return back()->withErrors(['error' => 'Batch is not ready to be committed.']);
        }

        if ($batch->valid_rows === 0) {
            return back()->withErrors(['error' => 'No valid rows available to import.']);
        }

        // Run commit job
        (new CommitImportBatch($batch->id))->handle(
            app(\App\Services\Import\AccountResolver::class),
            app(\App\Services\SubmissionWriter::class),
            app(\App\Services\GoogleSheets\SheetsGateway::class),
            app(\App\Services\GoogleSheets\SubmissionRowBuilder::class),
        );

        return redirect()->route('admin.imports.show', $batch->id)
            ->with('success', "Import completed! {$batch->fresh()->imported_rows} rows imported.");
    }

    /**
     * Rollback a completed import batch (within 24 hours).
     */
    public function rollback(ImportBatch $batch)
    {
        if ($batch->status !== 'completed') {
            return back()->withErrors(['error' => 'Only completed batches can be rolled back.']);
        }

        try {
            (new RollbackImportBatch($batch->id))->handle();
            return redirect()->route('admin.imports.show', $batch->id)
                ->with('success', 'Batch successfully rolled back. Submissions removed.');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Export error report spreadsheet for failed rows.
     */
    public function errorReport(ImportBatch $batch, ImportErrorReportGenerator $generator)
    {
        return $generator->downloadErrorReport($batch);
    }
}
