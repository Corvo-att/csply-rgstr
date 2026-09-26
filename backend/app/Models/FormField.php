<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormField extends Model
{
    protected $fillable = [
        'form_id', 'label', 'field_key', 'field_type',
        'options', 'validation_rules', 'is_required', 'sort_order', 'help_text',
    ];

    protected function casts(): array
    {
        return [
            'options'          => 'array',
            'validation_rules' => 'array',
            'is_required'      => 'boolean',
        ];
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Build the Laravel validation rule string for this field dynamically.
     * Called at submission time to validate incoming values.
     */
    public function buildValidationRule(): string
    {
        $rules = [];

        if ($this->is_required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        $opts = $this->options ?? [];

        switch ($this->field_type) {
            case 'email':
                $rules[] = 'email';
                break;
            case 'url':
                $rules[] = 'url';
                break;
            case 'number':
            case 'range':
            case 'rating':
                $rules[] = 'numeric';
                if (isset($opts['min'])) $rules[] = "min:{$opts['min']}";
                if (isset($opts['max'])) $rules[] = "max:{$opts['max']}";
                break;
            case 'date':
                $rules[] = 'date';
                break;
            case 'datetime-local':
                $rules[] = 'date';
                break;
            case 'dropdown':
            case 'radio':
                if (!empty($opts['choices'])) {
                    $rules[] = 'in:' . implode(',', $opts['choices']);
                }
                break;
            case 'checkbox':
            case 'toggle':
            case 'terms':
                $rules[] = 'in:0,1';
                break;
            case 'file':
            case 'image':
                $rules[] = 'file';
                if (!empty($opts['accept'])) {
                    $rules[] = 'mimes:' . implode(',', array_map(
                        fn($m) => explode('/', $m)[1],
                        $opts['accept']
                    ));
                }
                if (!empty($opts['max_size_kb'])) {
                    $rules[] = "max:{$opts['max_size_kb']}";
                }
                break;
            case 'text':
            case 'textarea':
            case 'password':
            case 'phone':
                $rules[] = 'string';
                if (isset($opts['min_length'])) $rules[] = "min:{$opts['min_length']}";
                if (isset($opts['max_length'])) $rules[] = "max:{$opts['max_length']}";
                break;
            default:
                $rules[] = 'string';
        }

        // Merge any extra rules stored in validation_rules column
        if (!empty($this->validation_rules)) {
            $rules = array_merge($rules, $this->validation_rules);
        }

        return implode('|', $rules);
    }
}
