<?php

namespace App\Http\Controllers;

use App\Models\Cosplayer;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Services\ImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FormController extends Controller
{
    public function __construct(private readonly ImageOptimizer $optimizer) {}

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

        // ── Create submission record ───────────────────────────────────────────
        $submission = FormSubmission::create([
            'form_id'      => $form->id,
            'cosplayer_id' => $cosplayer->id,
            'submitted_at' => now(),
        ]);

        // ── Process each field value ───────────────────────────────────────────
        foreach ($request->values as $fieldId => $value) {
            $storeValue = $value;

            if (is_array($value)) {
                $storeValue = implode(', ', $value);

            } elseif ($value instanceof UploadedFile) {
                $field     = $fieldMap->get($fieldId);
                $fieldType = $field?->field_type ?? 'file';
                $mime      = $value->getMimeType() ?? '';

                $isImage = ($fieldType === 'image')
                    || ($fieldType === 'file' && ImageOptimizer::isOptimisableImage($mime));

                if ($isImage) {
                    // ── Image optimisation pipeline ────────────────────────────
                    try {
                        $result     = $this->optimizer->optimize($value);
                        $storeValue = $result['url'];

                        $savedKb = round(($result['originalBytes'] - $result['savedBytes']) / 1024);
                        $origKb  = round($result['originalBytes'] / 1024);
                        $finalKb = round($result['savedBytes']    / 1024);

                        Log::info(sprintf(
                            '[ImageOptimizer] field=%s | original=%d KB → optimised=%d KB (saved %d KB, %.0f%%)',
                            $fieldId,
                            $origKb,
                            $finalKb,
                            $savedKb,
                            $origKb > 0 ? ($savedKb / $origKb * 100) : 0
                        ));
                    } catch (\Throwable $e) {
                        // Fallback: store original so the submission is never lost
                        Log::warning('[ImageOptimizer] Failed, storing original: ' . $e->getMessage());
                        $storeValue = $this->storeRaw($value);
                    }
                } else {
                    // Video / non-image file — store as-is
                    $storeValue = $this->storeRaw($value);
                }
            }

            FormSubmissionValue::create([
                'form_submission_id' => $submission->id,
                'form_field_id'      => $fieldId,
                'value'              => $storeValue,
            ]);
        }

        return redirect()->route('cosplay.profile')->with('success', 'Form submitted successfully!');
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Move an uploaded file to public/uploads/ without any transformation.
     * Returns the public URL.
     */
    private function storeRaw(UploadedFile $file): string
    {
        $ext         = $file->getClientOriginalExtension();
        $fileName    = Str::random(24) . '.' . $ext;
        $destination = public_path('uploads');

        if (! file_exists($destination)) {
            mkdir($destination, 0777, true);
        }

        $file->move($destination, $fileName);

        return url('uploads/' . $fileName);
    }
}
