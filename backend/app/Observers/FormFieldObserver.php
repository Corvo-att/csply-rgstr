<?php

namespace App\Observers;

use App\Jobs\SyncSheetHeaders;
use App\Models\FormField;
use App\Models\FormSheetLink;
use App\Models\SheetColumnMapping;
use App\Services\GoogleSheets\ColumnAutoMapper;

class FormFieldObserver
{
    /**
     * Handle the FormField "created" event.
     */
    public function created(FormField $field): void
    {
        $link = FormSheetLink::where('form_id', $field->form_id)
            ->where('auto_add_new_fields', true)
            ->first();

        if (!$link) {
            return;
        }

        // Check if mapping for this field_key already exists
        $exists = $link->mappings()->where('source_key', $field->field_key)->exists();
        if ($exists) {
            return;
        }

        $maxColIndex = $link->mappings()->max('column_index') ?? -1;
        $autoMapper = app(ColumnAutoMapper::class);

        SheetColumnMapping::create([
            'form_sheet_link_id' => $link->id,
            'source_type'        => 'field',
            'source_key'         => $field->field_key,
            'column_index'       => $maxColIndex + 1,
            'header_label'       => $field->label,
            'transform'          => $autoMapper->detectDefaultTransform($field->field_type),
            'is_active'          => true,
        ]);

        SyncSheetHeaders::dispatch($field->form_id);
    }

    /**
     * Handle the FormField "updated" event.
     */
    public function updated(FormField $field): void
    {
        if (!$field->wasChanged('label')) {
            return;
        }

        $link = FormSheetLink::where('form_id', $field->form_id)->first();
        if (!$link) {
            return;
        }

        $mapping = $link->mappings()
            ->where('source_type', 'field')
            ->where('source_key', $field->field_key)
            ->first();

        if ($mapping) {
            $mapping->update(['header_label' => $field->label]);
            SyncSheetHeaders::dispatch($field->form_id);
        }
    }

    /**
     * Handle the FormField "deleted" event.
     */
    public function deleted(FormField $field): void
    {
        $link = FormSheetLink::where('form_id', $field->form_id)->first();
        if (!$link) {
            return;
        }

        // Deactivated mapping so it's not written for new submissions,
        // but column remains preserved in existing sheet.
        $link->mappings()
            ->where('source_type', 'field')
            ->where('source_key', $field->field_key)
            ->update(['is_active' => false]);
    }
}
