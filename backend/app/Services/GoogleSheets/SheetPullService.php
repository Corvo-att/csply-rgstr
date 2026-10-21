<?php

namespace App\Services\GoogleSheets;

use App\Models\Admin;
use App\Models\FormField;
use App\Models\FormSheetLink;
use App\Models\FormSubmission;
use App\Models\SheetPullBatch;
use App\Models\SheetSyncRecord;
use App\Services\SubmissionRevisionRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SheetPullService
{
    public function __construct(
        private readonly SheetsGateway $gateway,
        private readonly SubmissionRowBuilder $rowBuilder,
        private readonly SubmissionRevisionRecorder $revisions,
    ) {}

    /**
     * Reads the Google Sheet, matches rows against database submissions,
     * and produces a field-level diff of all detected edits.
     */
    public function scan(FormSheetLink $link, ?Admin $admin = null): SheetPullBatch
    {
        $link->loadMissing(['connection', 'form.fields', 'activeMappings']);

        if (! $link->connection || ! $link->connection->isConnected()) {
            throw new \RuntimeException('Google account is not connected.');
        }

        $idMapping = $link->activeMappings->first(
            fn ($m) => $m->source_type === 'system' && in_array($m->source_key, ['submission_id', 'uuid', 'entry_number'], true)
        );

        if (! $idMapping) {
            throw new \RuntimeException('Cannot pull edits from sheet without an identifier column (Submission ID, UUID, or Entry #) mapped.');
        }

        $headerRow = $link->header_row ?: 1;
        $rawRows = $this->gateway->readRows($link->connection, $link->spreadsheet_id, $link->sheet_title, $headerRow + 1);

        $submissions = FormSubmission::where('form_id', $link->form_id)
            ->with(['cosplayer.user', 'values'])
            ->get();

        $subMap = $submissions->keyBy(fn ($s) => match ($idMapping->source_key) {
            'uuid' => (string) $s->uuid,
            'entry_number' => (string) $s->entry_number,
            default => (string) $s->id,
        });

        $fieldsByKey = $link->form->fields->keyBy('field_key');

        $changes = [];
        $unlinkedRows = 0;
        $notFoundRows = 0;

        foreach ($rawRows as $idx => $row) {
            $rowNum = $headerRow + 1 + $idx;

            // Check if entire row is empty
            $hasAny = false;
            foreach ($row as $cell) {
                if (trim((string) $cell) !== '') {
                    $hasAny = true;
                    break;
                }
            }
            if (! $hasAny) {
                continue;
            }

            $idVal = trim((string) ($row[$idMapping->column_index] ?? ''));
            if ($idVal === '') {
                $unlinkedRows++;
                continue;
            }

            /** @var FormSubmission|null $sub */
            $sub = $subMap->get($idVal);
            if (! $sub) {
                $notFoundRows++;
                continue;
            }

            $diffs = [];

            foreach ($link->activeMappings as $mapping) {
                // Skip comparing the ID column itself
                if ($mapping->column_index === $idMapping->column_index) {
                    continue;
                }

                $sheetVal = trim((string) ($row[$mapping->column_index] ?? ''));

                if ($mapping->source_type === 'system') {
                    $diff = $this->compareSystemField($mapping->source_key, $sheetVal, $sub);
                    if ($diff) {
                        $diffs[] = $diff;
                    }
                } elseif ($mapping->source_type === 'field') {
                    $field = $fieldsByKey->get($mapping->source_key);
                    if ($field) {
                        $diff = $this->compareFormField($field, $sheetVal, $sub);
                        if ($diff) {
                            $diffs[] = $diff;
                        }
                    }
                }
            }

            if (! empty($diffs)) {
                $changes[] = [
                    'submission_id'  => $sub->id,
                    'entry_number'   => $sub->entry_number,
                    'cosplayer_name' => $sub->cosplayer?->user?->name ?? '—',
                    'sheet_row'      => $rowNum,
                    'diffs'          => $diffs,
                ];
            }
        }

        return SheetPullBatch::create([
            'form_sheet_link_id' => $link->id,
            'admin_id'           => $admin?->id,
            'status'             => 'ready',
            'changes'            => $changes,
            'stats'              => [
                'total_rows_scanned'  => count($rawRows),
                'changed_submissions' => count($changes),
                'total_field_changes' => array_sum(array_map(fn ($c) => count($c['diffs']), $changes)),
                'unlinked_rows'       => $unlinkedRows,
                'not_found_rows'      => $notFoundRows,
            ],
        ]);
    }

    /**
     * Applies the reviewed changes from a SheetPullBatch into the database,
     * recording revisions and updating sheet sync hashes.
     *
     * @param array<int>|null $selectedSubmissionIds
     */
    public function apply(SheetPullBatch $batch, ?array $selectedSubmissionIds = null): array
    {
        if ($batch->status !== 'ready') {
            throw new \RuntimeException("This pull batch cannot be applied because its status is '{$batch->status}'.");
        }

        $link = $batch->link()->with(['connection', 'form.fields', 'activeMappings'])->firstOrFail();
        $fieldsByKey = $link->form->fields->keyBy('field_key');

        $appliedCount = 0;
        $changes = $batch->changes ?? [];

        foreach ($changes as $item) {
            $subId = $item['submission_id'];
            if ($selectedSubmissionIds !== null && ! in_array($subId, $selectedSubmissionIds, true)) {
                continue;
            }

            $sub = FormSubmission::with(['values', 'cosplayer.user'])->find($subId);
            if (! $sub) {
                continue;
            }

            DB::transaction(function () use ($sub, $item, $batch, $link, $fieldsByKey, &$appliedCount) {
                // 1. Snapshot previous state as revision
                $this->revisions->record($sub, 'sheet_pull', 'admin', $batch->admin_id);

                // 2. Apply each diff
                foreach ($item['diffs'] as $diff) {
                    if ($diff['type'] === 'system') {
                        match ($diff['key']) {
                            'status'         => $sub->status = strtolower($diff['new']),
                            'cosplayer_name' => $sub->cosplayer?->user?->update(['name' => $diff['new']]),
                            'email'          => $sub->cosplayer?->user?->update(['email' => $diff['new']]),
                            'character'      => $sub->cosplayer?->update(['character_name' => $diff['new']]),
                            'series'         => $sub->cosplayer?->update(['series' => $diff['new']]),
                            default          => null,
                        };
                    } elseif ($diff['type'] === 'field') {
                        $field = $fieldsByKey->get($diff['key']);
                        $newVal = $diff['new'];

                        if ($field && in_array($field->field_type, FormField::MULTI_TYPES, true)) {
                            if (! str_starts_with($newVal, '[')) {
                                $arr = array_values(array_filter(array_map('trim', explode(',', $newVal))));
                                $newVal = json_encode($arr, JSON_UNESCAPED_UNICODE);
                            }
                        }

                        $sub->values()->updateOrCreate(
                            ['form_field_id' => $diff['field_id']],
                            ['value' => $newVal, 'field_key_snapshot' => $diff['key']]
                        );
                    }
                }

                $sub->last_edited_at = now();
                $sub->version = $sub->version + 1;
                $sub->save();

                // 3. Mark the sync record as synced with the new row hash so FlushSheetLink won't re-push
                $row = $this->rowBuilder->buildRow($sub->fresh(['values', 'cosplayer.user']), $link);
                $hash = $this->rowBuilder->computeHash($row);

                SheetSyncRecord::updateOrCreate(
                    ['form_submission_id' => $sub->id, 'form_sheet_link_id' => $link->id],
                    [
                        'status'       => 'synced',
                        'sheet_row'    => $item['sheet_row'],
                        'synced_at'    => now(),
                        'payload_hash' => $hash,
                        'last_error'   => null,
                    ]
                );

                $appliedCount++;
            });
        }

        $batch->update([
            'status'        => 'applied',
            'applied_at'    => now(),
            'applied_stats' => ['applied_count' => $appliedCount],
        ]);

        return ['applied_count' => $appliedCount];
    }

    private function compareSystemField(string $key, string $sheetVal, FormSubmission $sub): ?array
    {
        $user = $sub->cosplayer?->user;
        $cosplayer = $sub->cosplayer;

        return match ($key) {
            'status' => (strtolower($sheetVal) !== '' && strtolower($sheetVal) !== strtolower($sub->status ?? 'pending')) ? [
                'type'  => 'system',
                'key'   => 'status',
                'label' => 'Status',
                'old'   => ucfirst($sub->status ?? 'pending'),
                'new'   => ucfirst(strtolower($sheetVal)),
            ] : null,

            'cosplayer_name' => ($sheetVal !== '' && $sheetVal !== ($user?->name ?? '')) ? [
                'type'  => 'system',
                'key'   => 'cosplayer_name',
                'label' => 'Cosplayer Name',
                'old'   => $user?->name ?? '',
                'new'   => $sheetVal,
            ] : null,

            'email' => ($sheetVal !== '' && $sheetVal !== ($user?->email ?? '')) ? [
                'type'  => 'system',
                'key'   => 'email',
                'label' => 'Email',
                'old'   => $user?->email ?? '',
                'new'   => $sheetVal,
            ] : null,

            'character' => ($sheetVal !== '' && $sheetVal !== ($cosplayer?->character_name ?? '')) ? [
                'type'  => 'system',
                'key'   => 'character',
                'label' => 'Character',
                'old'   => $cosplayer?->character_name ?? '',
                'new'   => $sheetVal,
            ] : null,

            'series' => ($sheetVal !== '' && $sheetVal !== ($cosplayer?->series ?? '')) ? [
                'type'  => 'system',
                'key'   => 'series',
                'label' => 'Series',
                'old'   => $cosplayer?->series ?? '',
                'new'   => $sheetVal,
            ] : null,

            default => null,
        };
    }

    private function compareFormField(FormField $field, string $sheetVal, FormSubmission $sub): ?array
    {
        $valRow = $sub->values->firstWhere('form_field_id', $field->id);
        $rawDbVal = $valRow?->value ?? '';

        // Normalize DB value for comparison if it's JSON
        $normalizedDbVal = $rawDbVal;
        if (in_array($field->field_type, FormField::MULTI_TYPES, true)) {
            $decoded = json_decode($rawDbVal, true);
            if (is_array($decoded)) {
                $normalizedDbVal = implode(', ', $decoded);
            }
        }

        // Clean comparison
        if (trim($sheetVal) !== trim($normalizedDbVal)) {
            return [
                'type'     => 'field',
                'key'      => $field->field_key,
                'field_id' => $field->id,
                'label'    => $field->label,
                'old'      => $normalizedDbVal,
                'new'      => $sheetVal,
            ];
        }

        return null;
    }
}
