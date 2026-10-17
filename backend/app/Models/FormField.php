<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class FormField extends Model
{
    /** Types that only display something - they never hold an answer. */
    public const LAYOUT_TYPES = ['section', 'instructions'];

    public const FILE_TYPES = ['file', 'image', 'video'];

    public const CHOICE_TYPES = ['dropdown', 'radio', 'multiselect', 'checkbox_group'];

    public const MULTI_TYPES = ['multiselect', 'checkbox_group'];

    /** Every type the builder may create. Anything else is rejected on save. */
    public const TYPES = [
        'text', 'textarea', 'email', 'url', 'password', 'phone',
        'number', 'range', 'rating',
        'date', 'time', 'datetime-local',
        'dropdown', 'radio', 'checkbox', 'checkbox_group', 'multiselect', 'toggle',
        'file', 'image', 'video', 'color',
        'address', 'hidden', 'section', 'instructions', 'terms',
    ];

    protected $fillable = [
        'form_id', 'label', 'field_key', 'field_type',
        'options', 'validation_rules', 'is_required', 'sort_order', 'help_text',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'validation_rules' => 'array',
            'is_required' => 'boolean',
        ];
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function values()
    {
        return $this->hasMany(FormSubmissionValue::class);
    }

    public function hasAnswers(): bool
    {
        return $this->values()->exists();
    }

    public function holdsAnswer(): bool
    {
        return ! in_array($this->field_type, self::LAYOUT_TYPES, true);
    }

    public function isFile(): bool
    {
        return in_array($this->field_type, self::FILE_TYPES, true);
    }

    /** Effective upload limit in KB. KB wins over MB; blank falls back to the config default. */
    public function maxSizeKb(): int
    {
        $opts = $this->options ?? [];

        if (! empty($opts['max_size_kb'])) {
            return max(1, (int) $opts['max_size_kb']);
        }
        if (! empty($opts['max_size_mb'])) {
            return max(1, (int) round((float) $opts['max_size_mb'] * 1024));
        }

        return (int) config('media.default_max_kb.'.$this->field_type, config('media.default_max_kb.file'));
    }

    /**
     * MIME types this upload field accepts. Starts from the server-side allow-list for
     * the field type, then narrows it to the manager's "accepted formats" if set.
     *
     * @return string[]
     */
    public function allowedMimes(): array
    {
        $base = match ($this->field_type) {
            'image' => config('media.image_mimes'),
            'video' => config('media.video_mimes'),
            default => array_keys(config('media.mime_extensions')),
        };

        $accepted = array_filter(array_map('trim', explode(',', (string) ($this->options['accepted_formats'] ?? ''))));
        if (! $accepted) {
            return array_values($base);
        }

        return array_values(array_filter($base, function (string $mime) use ($accepted) {
            foreach ($accepted as $pattern) {
                if ($pattern === $mime || (str_ends_with($pattern, '/*') && str_starts_with($mime, substr($pattern, 0, -1)))) {
                    return true;
                }
            }

            return false;
        }));
    }

    /** Choices as a clean list of strings. */
    public function choices(): array
    {
        return array_values(array_map('strval', $this->options['choices'] ?? []));
    }

    /**
     * Laravel validation rules for this field, keyed by input name
     * (values.{id} plus nested keys for array answers).
     *
     * This is what makes the server the source of truth: the React form validates
     * too, but only for convenience - a browser can send anything.
     */
    public function validationRules(): array
    {
        if (! $this->holdsAnswer()) {
            return [];
        }

        $opts = $this->options ?? [];
        $key = "values.{$this->id}";
        $req = $this->is_required ? ['required'] : ['nullable'];

        switch ($this->field_type) {
            case 'email':
                return [$key => [...$req, 'string', 'email', 'max:255']];

            case 'url':
                return [$key => [...$req, 'string', 'url', 'max:2000']];

            case 'phone':
                return [$key => [...$req, 'string', 'regex:/^[0-9+()\-\s]{5,30}$/']];

            case 'number':
            case 'range':
            case 'rating':
                $rules = [...$req, 'numeric'];
                $min = $opts['min'] ?? ($this->field_type === 'rating' ? 1 : null);
                $max = $opts['max'] ?? ($this->field_type === 'rating' ? ($opts['max_stars'] ?? 5) : null);
                if (is_numeric($min)) {
                    $rules[] = 'min:'.$min;
                }
                if (is_numeric($max)) {
                    $rules[] = 'max:'.$max;
                }

                return [$key => $rules];

            case 'date':
            case 'datetime-local':
                return [$key => [...$req, 'date']];

            case 'time':
                return [$key => [...$req, 'date_format:H:i']];

            case 'color':
                return [$key => [...$req, 'regex:/^#[0-9a-fA-F]{6}$/']];

            case 'dropdown':
            case 'radio':
                return [$key => [...$req, 'string', Rule::in($this->choices())]];

            case 'multiselect':
            case 'checkbox_group':
                return [
                    $key => $this->is_required ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
                    "{$key}.*" => ['string', Rule::in($this->choices())],
                ];

            case 'toggle':
                return [$key => [...$req, 'in:0,1']];

            case 'checkbox':
            case 'terms':
                // A required checkbox / terms box must actually be ticked.
                return [$key => $this->is_required ? ['required', 'accepted'] : ['nullable', 'in:0,1']];

            case 'address':
                return [
                    $key => [...$req, 'array'],
                    "{$key}.street" => ['nullable', 'string', 'max:255'],
                    "{$key}.city" => ['nullable', 'string', 'max:255'],
                    "{$key}.state" => ['nullable', 'string', 'max:255'],
                    "{$key}.zip" => ['nullable', 'string', 'max:50'],
                    "{$key}.country" => ['nullable', 'string', 'max:255'],
                ];

            case 'hidden':
                // The value always comes from the field definition, never from the browser.
                return [];

            case 'file':
            case 'image':
            case 'video':
                return [$key => [...$req, 'file', 'mimetypes:'.implode(',', $this->allowedMimes()), 'max:'.$this->maxSizeKb()]];

            case 'textarea':
                $rules = [...$req, 'string', 'max:'.(int) ($opts['max_length'] ?? 10000)];
                if (! empty($opts['min_length'])) {
                    $rules[] = 'min:'.(int) $opts['min_length'];
                }

                return [$key => $rules];

            case 'text':
            case 'password':
            default:
                $rules = [...$req, 'string', 'max:'.(int) ($opts['max_length'] ?? 1000)];
                if (! empty($opts['min_length'])) {
                    $rules[] = 'min:'.(int) $opts['min_length'];
                }

                return [$key => $rules];
        }
    }
}
