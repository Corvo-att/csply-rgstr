<?php

namespace App\Exports;

use App\Models\Form;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DynamicFormSubmissionsExport implements FromCollection, WithHeadings, WithMapping
{
    protected Form $form;
    protected $fields;

    public function __construct(Form $form)
    {
        $this->form   = $form;
        $this->fields = $form->fields()->get(); // sorted by sort_order
    }

    public function collection()
    {
        return $this->form->submissions()
            ->with(['cosplayer.user', 'values'])
            ->get();
    }

    public function headings(): array
    {
        return array_merge(
            ['Submission ID', 'Cosplayer Name', 'Character', 'Submitted At'],
            $this->fields->pluck('label')->toArray()
        );
    }

    public function map($submission): array
    {
        $cosplayer = $submission->cosplayer;
        $valuesByField = $submission->values->keyBy('form_field_id');

        $fieldValues = $this->fields->map(function ($field) use ($valuesByField) {
            return $valuesByField[$field->id]->value ?? '';
        })->toArray();

        return array_merge([
            $submission->id,
            $cosplayer->user->name,
            $cosplayer->character_name,
            $submission->submitted_at?->format('Y-m-d H:i'),
        ], $fieldValues);
    }
}
