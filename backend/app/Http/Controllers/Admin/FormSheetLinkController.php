<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSheetLink;
use App\Models\GoogleConnection;
use App\Models\SheetColumnMapping;
use App\Services\GoogleSheets\ColumnAutoMapper;
use App\Services\GoogleSheets\SheetsGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FormSheetLinkController extends Controller
{
    public function __construct(
        private readonly SheetsGateway $gateway,
        private readonly ColumnAutoMapper $autoMapper,
    ) {}

    /**
     * Get sheet link, mappings, and configuration for a form.
     */
    public function show(Form $form)
    {
        $admin = Auth::guard('admin')->user();
        $connection = GoogleConnection::where('admin_id', $admin->id)
            ->whereNull('revoked_at')
            ->first();

        $link = $form->sheetLink()->with('mappings')->first();

        $fields = $form->fields()->orderBy('sort_order')->get(['id', 'label', 'field_key', 'field_type', 'is_required']);

        return response()->json([
            'connected'      => $connection !== null && $connection->isConnected(),
            'connection'     => $connection ? [
                'id'         => $connection->id,
                'email'      => $connection->email,
                'avatar_url' => $connection->avatar_url,
            ] : null,
            'link'           => $link,
            'mappings'       => $link ? $link->mappings : [],
            'fields'         => $fields,
            'system_columns' => ColumnAutoMapper::SYSTEM_COLUMNS,
            'default_mappings' => $this->autoMapper->generateDefaultMappings($form),
        ]);
    }

    /**
     * Read existing headers from a picked Google Sheet tab and propose auto-mappings.
     */
    public function headers(Request $request, Form $form)
    {
        $data = $request->validate([
            'spreadsheet_id' => ['required', 'string'],
            'sheet_title'    => ['required', 'string'],
            'header_row'     => ['nullable', 'integer', 'min:1'],
        ]);

        $admin = Auth::guard('admin')->user();
        $connection = GoogleConnection::where('admin_id', $admin->id)->whereNull('revoked_at')->firstOrFail();

        $headerRow = (int) ($data['header_row'] ?? 1);

        try {
            $headers = $this->gateway->readHeaderRow($connection, $data['spreadsheet_id'], $data['sheet_title'], $headerRow);
            $matched = $this->autoMapper->matchHeaders($form, $headers);

            return response()->json([
                'headers'            => $headers,
                'suggested_mappings' => $matched,
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to read headers for spreadsheet {$data['spreadsheet_id']}: " . $e->getMessage());
            return response()->json(['error' => 'Unable to read headers from spreadsheet: ' . $e->getMessage()], 422);
        }
    }

    /**
     * Link a Google Spreadsheet to the form.
     */
    public function store(Request $request, Form $form)
    {
        $data = $request->validate([
            'mode'                   => ['required', 'in:create,new_tab,existing'],
            'spreadsheet_id'         => ['required_if:mode,new_tab,existing', 'nullable', 'string'],
            'spreadsheet_url'        => ['nullable', 'string'],
            'spreadsheet_title'      => ['nullable', 'string', 'max:255'],
            'sheet_title'            => ['nullable', 'string', 'max:255'],
            'sheet_gid'              => ['nullable', 'integer'],
            'header_row'             => ['nullable', 'integer', 'min:1'],
            'auto_add_new_fields'    => ['nullable', 'boolean'],
            'include_system_columns' => ['nullable', 'array'],
            'mappings'               => ['nullable', 'array'],
        ]);

        $admin = Auth::guard('admin')->user();
        $connection = GoogleConnection::where('admin_id', $admin->id)->whereNull('revoked_at')->firstOrFail();

        $headerRow = (int) ($data['header_row'] ?? 1);
        $autoAddNew = (bool) ($data['auto_add_new_fields'] ?? true);
        $tabTitle   = trim((string) ($data['sheet_title'] ?? '')) ?: $form->name;

        DB::beginTransaction();
        try {
            if ($data['mode'] === 'create') {
                $sheetDocTitle = trim((string) ($data['spreadsheet_title'] ?? '')) ?: ($form->event?->name ? "{$form->event->name} - {$form->name}" : "EGYCON - {$form->name}");
                $created = $this->gateway->createSpreadsheet($connection, $sheetDocTitle, $tabTitle);

                $spreadsheetId  = $created['spreadsheet_id'];
                $spreadsheetUrl = $created['spreadsheet_url'];
                $sheetGid       = $created['sheet_gid'];
                $sheetTitle     = $created['sheet_title'];

                // Generate mappings and write initial headers
                $mappingsData = !empty($data['mappings'])
                    ? $data['mappings']
                    : $this->autoMapper->generateDefaultMappings($form, $data['include_system_columns'] ?? null);

                $headers = array_column($mappingsData, 'header_label');
                $this->gateway->writeHeaderRow($connection, $spreadsheetId, $sheetTitle, $headers, $headerRow);
                $this->gateway->freezeAndStyleHeader($connection, $spreadsheetId, $sheetGid, count($headers), $headerRow);

            } elseif ($data['mode'] === 'new_tab') {
                $spreadsheetId  = $data['spreadsheet_id'];
                $spreadsheetUrl = $data['spreadsheet_url'] ?? "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/edit";
                $sheetDocTitle  = $data['spreadsheet_title'] ?? 'Google Spreadsheet';

                $addedTab   = $this->gateway->addTab($connection, $spreadsheetId, $tabTitle);
                $sheetGid   = $addedTab['sheet_gid'];
                $sheetTitle = $addedTab['sheet_title'];

                $mappingsData = !empty($data['mappings'])
                    ? $data['mappings']
                    : $this->autoMapper->generateDefaultMappings($form, $data['include_system_columns'] ?? null);

                $headers = array_column($mappingsData, 'header_label');
                $this->gateway->writeHeaderRow($connection, $spreadsheetId, $sheetTitle, $headers, $headerRow);
                $this->gateway->freezeAndStyleHeader($connection, $spreadsheetId, $sheetGid, count($headers), $headerRow);

            } else { // mode === 'existing'
                $spreadsheetId  = $data['spreadsheet_id'];
                $spreadsheetUrl = $data['spreadsheet_url'] ?? "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/edit";
                $sheetDocTitle  = $data['spreadsheet_title'] ?? 'Google Spreadsheet';
                $sheetGid       = (int) ($data['sheet_gid'] ?? 0);
                $sheetTitle     = $tabTitle;

                $mappingsData   = $data['mappings'] ?? $this->autoMapper->generateDefaultMappings($form);
            }

            // Create or replace link
            $link = FormSheetLink::updateOrCreate(
                ['form_id' => $form->id],
                [
                    'google_connection_id'   => $connection->id,
                    'spreadsheet_id'         => $spreadsheetId,
                    'spreadsheet_url'        => $spreadsheetUrl,
                    'spreadsheet_title'      => $sheetDocTitle,
                    'sheet_gid'              => $sheetGid,
                    'sheet_title'            => $sheetTitle,
                    'header_row'             => $headerRow,
                    'sync_enabled'           => true,
                    'auto_add_new_fields'    => $autoAddNew,
                    'include_system_columns' => $data['include_system_columns'] ?? null,
                    'status'                 => 'active',
                    'last_error'             => null,
                ]
            );

            // Recreate mappings
            $link->mappings()->delete();
            foreach ($mappingsData as $m) {
                if (!isset($m['header_label']) || !isset($m['source_key'])) {
                    continue;
                }
                SheetColumnMapping::create([
                    'form_sheet_link_id' => $link->id,
                    'source_type'        => $m['source_type'] ?? 'field',
                    'source_key'         => $m['source_key'],
                    'column_index'       => (int) ($m['column_index'] ?? 0),
                    'header_label'       => $m['header_label'],
                    'transform'          => $m['transform'] ?? 'auto',
                    'is_active'          => (bool) ($m['is_active'] ?? true),
                ]);
            }

            DB::commit();

            return back()->with('success', "Google Sheet linked successfully: {$sheetDocTitle} ({$sheetTitle})");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to link Google Sheet for form {$form->id}: " . $e->getMessage());
            return back()->with('error', 'Failed to link Google Sheet: ' . $e->getMessage());
        }
    }

    /**
     * Update column mappings for an already linked sheet.
     */
    public function updateMappings(Request $request, Form $form)
    {
        $link = $form->sheetLink()->firstOrFail();

        $data = $request->validate([
            'mappings'                 => ['required', 'array'],
            'mappings.*.source_type'   => ['required', 'in:field,system'],
            'mappings.*.source_key'    => ['required', 'string'],
            'mappings.*.column_index'  => ['required', 'integer', 'min:0'],
            'mappings.*.header_label'  => ['required', 'string', 'max:255'],
            'mappings.*.transform'     => ['nullable', 'string', 'max:30'],
            'mappings.*.is_active'     => ['boolean'],
            'auto_add_new_fields'      => ['nullable', 'boolean'],
            'update_sheet_headers'     => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($link, $data, $form) {
            $link->mappings()->delete();

            foreach ($data['mappings'] as $m) {
                SheetColumnMapping::create([
                    'form_sheet_link_id' => $link->id,
                    'source_type'        => $m['source_type'],
                    'source_key'         => $m['source_key'],
                    'column_index'       => (int) $m['column_index'],
                    'header_label'       => $m['header_label'],
                    'transform'          => $m['transform'] ?? 'auto',
                    'is_active'          => (bool) ($m['is_active'] ?? true),
                ]);
            }

            if (isset($data['auto_add_new_fields'])) {
                $link->update(['auto_add_new_fields' => (bool) $data['auto_add_new_fields']]);
            }
        });

        // Optionally write updated headers to the sheet
        if (!empty($data['update_sheet_headers']) && $link->connection) {
            try {
                $headers = collect($data['mappings'])
                    ->where('is_active', true)
                    ->sortBy('column_index')
                    ->pluck('header_label')
                    ->all();

                $this->gateway->writeHeaderRow(
                    $link->connection,
                    $link->spreadsheet_id,
                    $link->sheet_title,
                    $headers,
                    $link->header_row
                );
            } catch (\Throwable $e) {
                Log::warning("Could not update sheet headers after mapping save: " . $e->getMessage());
            }
        }

        return back()->with('success', 'Column mappings updated.');
    }

    /**
     * Toggle sync on or off.
     */
    public function toggle(Request $request, Form $form)
    {
        $link = $form->sheetLink()->firstOrFail();
        $link->update(['sync_enabled' => ! $link->sync_enabled]);

        $status = $link->sync_enabled ? 'enabled' : 'paused';
        return back()->with('success', "Google Sheet synchronization {$status}.");
    }

    /**
     * Unlink spreadsheet from form.
     */
    public function destroy(Form $form)
    {
        $link = $form->sheetLink()->first();
        if ($link) {
            $link->delete();
        }

        return back()->with('success', 'Google Sheet unlinked.');
    }

    /**
     * Dispatch a background job to backfill/resync submissions to the linked Google Sheet.
     */
    public function resync(Request $request, Form $form)
    {
        $link = $form->sheetLink()->firstOrFail();
        $forceAll = (bool) $request->input('force_all', false);

        \App\Jobs\BackfillFormToSheet::dispatch($form->id, $forceAll);

        return back()->with('success', 'Submissions synchronization has been queued in the background.');
    }
}
