<?php

namespace App\Services\Import;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class ImportValidator
{
    /**
     * Validate an imported row's identity and dynamic field values.
     *
     * @param Form $form
     * @param array $normalizedRow Structure: ['identity' => [...], 'values' => [...]]
     * @param array<string, int> &$seenEmails Email frequency within current batch for in-file duplicate checks
     * @param string $duplicateStrategy 'skip' | 'update' | 'create'
     * @return array{status: string, errors: array<string, array<string>>}
     */
    public function validateRow(
        Form $form,
        array $normalizedRow,
        array &$seenEmails,
        string $duplicateStrategy = 'skip'
    ): array {
        $errors = [];
        $identity = $normalizedRow['identity'] ?? [];
        $values = $normalizedRow['values'] ?? [];

        // 1. Identity validation
        $email = strtolower(trim((string) ($identity['email'] ?? '')));

        if (empty($email)) {
            $errors['email'] = ['Email is required for every imported row.'];
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = ["'{$email}' is not a valid email address."];
        }

        // 2. Duplicate detection
        $isDuplicateInFile = false;
        $isDuplicateInDb = false;

        if (!empty($email) && empty($errors['email'])) {
            if (isset($seenEmails[$email])) {
                $isDuplicateInFile = true;
                $seenEmails[$email]++;
            } else {
                $seenEmails[$email] = 1;
            }

            // Check if user already exists and submitted this form
            $user = User::where('email', $email)->first();
            if ($user && $user->cosplayer) {
                $hasSubmission = FormSubmission::where('form_id', $form->id)
                    ->where('cosplayer_id', $user->cosplayer->id)
                    ->exists();
                if ($hasSubmission) {
                    $isDuplicateInDb = true;
                }
            }

            if (($isDuplicateInFile || $isDuplicateInDb) && $duplicateStrategy === 'skip') {
                $sourceMsg = $isDuplicateInFile ? 'in this file' : 'already registered for this form';
                return [
                    'status' => 'duplicate',
                    'errors' => ['email' => ["Duplicate entry for '{$email}' ({$sourceMsg}). Skipped."]],
                ];
            }
        }

        // 3. Dynamic form field validation
        $fields = $form->fields; // Collection of FormField

        foreach ($fields as $field) {
            $val = $values[$field->id] ?? null;
            $fieldKey = $field->field_key;
            $fieldLabel = $field->label ?: $fieldKey;

            // Required check
            if ($field->is_required && ($val === null || $val === '')) {
                $errors[$fieldKey] = ["The field '{$fieldLabel}' is required."];
                continue;
            }

            if ($val === null || $val === '') {
                continue;
            }

            // Type-specific checks for imported string/scalar values
            $valStr = (string) $val;
            $opts = $field->options ?? [];

            switch ($field->field_type) {
                case 'email':
                    if (!filter_var($valStr, FILTER_VALIDATE_EMAIL)) {
                        $errors[$fieldKey] = ["'{$valStr}' is not a valid email address."];
                    }
                    break;

                case 'url':
                    if (!filter_var($valStr, FILTER_VALIDATE_URL)) {
                        $errors[$fieldKey] = ["'{$valStr}' is not a valid URL."];
                    }
                    break;

                case 'number':
                case 'rating':
                case 'range':
                    if (!is_numeric($valStr)) {
                        $errors[$fieldKey] = ["'{$valStr}' must be a numeric value."];
                    } else {
                        $num = (float) $valStr;
                        if (isset($opts['min']) && $num < (float) $opts['min']) {
                            $errors[$fieldKey] = ["Value {$num} must be at least {$opts['min']}."];
                        }
                        if (isset($opts['max']) && $num > (float) $opts['max']) {
                            $errors[$fieldKey] = ["Value {$num} must not exceed {$opts['max']}."];
                        }
                    }
                    break;

                case 'date':
                case 'datetime-local':
                    if (!strtotime($valStr)) {
                        $errors[$fieldKey] = ["'{$valStr}' is not a valid date format."];
                    }
                    break;

                case 'dropdown':
                case 'radio':
                    $choices = $opts['choices'] ?? [];
                    if (!empty($choices)) {
                        $matched = false;
                        foreach ($choices as $choice) {
                            if (strcasecmp(trim($choice), trim($valStr)) === 0) {
                                $matched = true;
                                break;
                            }
                        }
                        if (!$matched) {
                            $errors[$fieldKey] = ["'{$valStr}' is not one of the allowed options: " . implode(', ', $choices)];
                        }
                    }
                    break;

                case 'checkbox':
                case 'toggle':
                case 'terms':
                    if (!in_array($valStr, ['0', '1', 'true', 'false'], true)) {
                        $errors[$fieldKey] = ["Must be a valid boolean / checkbox value."];
                    }
                    break;

                case 'file':
                case 'image':
                case 'video':
                    // In import spreadsheets, file/image is usually a URL or file path
                    if (strlen($valStr) > 2048) {
                        $errors[$fieldKey] = ["File reference URL must not exceed 2048 characters."];
                    }
                    break;

                default:
                    // Text / Textarea length checks
                    if (isset($opts['min_length']) && mb_strlen($valStr) < (int) $opts['min_length']) {
                        $errors[$fieldKey] = ["Must be at least {$opts['min_length']} characters."];
                    }
                    if (isset($opts['max_length']) && mb_strlen($valStr) > (int) $opts['max_length']) {
                        $errors[$fieldKey] = ["Must not exceed {$opts['max_length']} characters."];
                    }
                    break;
            }
        }

        return [
            'status' => empty($errors) ? 'valid' : 'invalid',
            'errors' => $errors,
        ];
    }
}
