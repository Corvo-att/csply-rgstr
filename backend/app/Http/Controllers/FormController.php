<?php

namespace App\Http\Controllers;

use App\Models\Cosplayer;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Services\SubmissionWriter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FormController extends Controller
{
    public function __construct(private readonly SubmissionWriter $writer) {}

    /**
     * Show the form fill page.
     */
    public function fill(Event $event, Form $form)
    {
        abort_if($form->event_id !== $event->id, 404);
        abort_if(! $form->is_active, 403, 'This form is not currently accepting responses.');

        $cosplayer = Cosplayer::where('user_id', Auth::id())->first();

        return Inertia::render('Forms/Fill', [
            'event'     => $event->only('id', 'name'),
            'form'      => $form->only('id', 'name', 'description'),
            'fields'    => $form->fields()->orderBy('sort_order')->get(),
            'cosplayer' => $cosplayer,
        ]);
    }

    /**
     * Handle form submission.
     *
     * Images (field type = 'image', or 'file' fields containing an image MIME)
     * are automatically processed through the ImageOptimizer pipeline:
     *   • EXIF stripped & orientation corrected
     *   • Downscaled to max 2 400 × 2 400 px if larger
     *   • Re-encoded as WebP at quality 82 %
     * Videos and other binary files are stored as-is.
     * If optimisation throws for any reason the original file is stored so the
     * submission is never lost.
     */
    public function submit(Request $request, Event $event, Form $form)
    {
        abort_if($form->event_id !== $event->id, 404);
        abort_if(! $form->is_active, 403);

        $cosplayer = Cosplayer::where('user_id', Auth::id())->firstOrFail();

        // ── Per-field validation rules (size limits from field options) ────────
        $fieldMap = $form->fields()->get()->keyBy('id');
        $rules    = ['values' => ['required', 'array']];

        foreach ($fieldMap as $fieldId => $field) {
            $opts = $field->options ?? [];

            if (in_array($field->field_type, ['file', 'image', 'video'])) {
                $fieldRules = ['nullable', 'file'];

                if ($field->field_type === 'image') {
                    $fieldRules[] = 'image';
                } elseif ($field->field_type === 'video') {
                    $fieldRules[] = 'mimetypes:video/mp4,video/webm,video/ogg,video/quicktime,video/x-msvideo,video/avi';
                }

                // Size: KB takes priority over MB
                if (! empty($opts['max_size_kb'])) {
                    $fieldRules[] = 'max:' . intval($opts['max_size_kb']);
                } elseif (! empty($opts['max_size_mb'])) {
                    $fieldRules[] = 'max:' . intval($opts['max_size_mb'] * 1024);
                }

                $rules["values.{$fieldId}"] = $fieldRules;
            }
        }

        $request->validate($rules);

        // ── Persist atomically via the shared writer (also fires the sync event) ─
        $this->writer->write($form, $cosplayer, $request->values);

        return redirect()->route('cosplay.profile')->with('success', 'Form submitted successfully!');
    }
}
