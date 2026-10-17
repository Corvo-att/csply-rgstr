<?php

namespace App\Jobs;

use App\Models\Form;
use App\Models\FormSheetLink;
use App\Services\GoogleSheets\SheetsGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncSheetHeaders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $formId)
    {
        $this->onQueue('sheets');
    }

    public function handle(SheetsGateway $gateway): void
    {
        $link = FormSheetLink::with(['connection', 'activeMappings'])
            ->where('form_id', $this->formId)
            ->first();

        if (!$link || !$link->connection || !$link->connection->isConnected()) {
            return;
        }

        $activeMappings = $link->activeMappings;
        if ($activeMappings->isEmpty()) {
            return;
        }

        $headers = $activeMappings->pluck('header_label')->all();

        try {
            $gateway->writeHeaderRow(
                $link->connection,
                $link->spreadsheet_id,
                $link->sheet_title,
                $headers,
                $link->header_row
            );

            $gateway->freezeAndStyleHeader(
                $link->connection,
                $link->spreadsheet_id,
                $link->sheet_gid,
                count($headers),
                $link->header_row
            );

            Log::info("Synchronized sheet headers for form {$this->formId} ({$link->spreadsheet_title})");
        } catch (\Throwable $e) {
            Log::error("Failed to sync sheet headers for form {$this->formId}: " . $e->getMessage());
        }
    }
}
