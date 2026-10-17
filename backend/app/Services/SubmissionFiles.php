<?php

namespace App\Services;

use App\Models\FormField;
use App\Models\FormSubmissionValue;

/**
 * Deleting rows in the database does not delete the uploaded files they point to.
 * Call this BEFORE deleting submissions / forms / events so storage does not fill up
 * with orphaned uploads.
 */
class SubmissionFiles
{
    public function __construct(private readonly MediaStorage $storage) {}

    /** @param int[] $submissionIds */
    public function purgeForSubmissions(array $submissionIds): void
    {
        $this->purge(FormSubmissionValue::whereIn('form_submission_id', $submissionIds));
    }

    /** @param int[] $fieldIds */
    public function purgeForFields(array $fieldIds): void
    {
        $this->purge(FormSubmissionValue::whereIn('form_field_id', $fieldIds));
    }

    /** @param int[] $formIds */
    public function purgeForForms(array $formIds): void
    {
        $fieldIds = FormField::whereIn('form_id', $formIds)->whereIn('field_type', FormField::FILE_TYPES)->pluck('id')->all();
        $this->purgeForFields($fieldIds);
    }

    private function purge($query): void
    {
        $query->whereIn('form_field_id', FormField::whereIn('field_type', FormField::FILE_TYPES)->select('id'))
            ->select(['id', 'value'])
            ->chunkById(200, function ($values) {
                foreach ($values as $value) {
                    $this->storage->deleteByUrl($value->value);
                }
            });
    }
}
