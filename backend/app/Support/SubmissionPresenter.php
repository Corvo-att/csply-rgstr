<?php

namespace App\Support;

use App\Models\FormField;
use App\Models\FormSubmission;
use Illuminate\Support\Collection;

/**
 * Turns the raw rows in form_submission_values into something a human (admin pages,
 * CSV export) or a machine (voting API) can use. Keeps that logic out of controllers.
 */
class SubmissionPresenter
{
    /** Human-readable text for one stored answer. */
    public static function display(FormField $field, ?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (in_array($field->field_type, FormField::MULTI_TYPES, true)) {
            $list = json_decode($raw, true);

            return is_array($list) ? implode(', ', $list) : $raw;
        }

        if ($field->field_type === 'address') {
            $address = json_decode($raw, true);

            return is_array($address) ? implode(', ', array_filter($address)) : $raw;
        }

        return $raw;
    }

    /**
     * field id => display text, for every answer field. The submission must have `values` loaded.
     *
     * @param  Collection<int, FormField>  $fields
     */
    public static function values(Collection $fields, FormSubmission $submission): array
    {
        $byField = $submission->values->keyBy('form_field_id');
        $out = [];

        foreach ($fields as $field) {
            $row = $byField->get($field->id);
            if ($row) {
                $out[$field->id] = self::display($field, $row->value);
            }
        }

        return $out;
    }

    /** field id => ['status' => ..., 'meta' => ...] for answers that went through background processing. */
    public static function processing(FormSubmission $submission): array
    {
        return $submission->values
            ->filter(fn ($v) => $v->processing_status !== null)
            ->mapWithKeys(fn ($v) => [$v->form_field_id => ['status' => $v->processing_status, 'meta' => $v->meta]])
            ->all();
    }
}
