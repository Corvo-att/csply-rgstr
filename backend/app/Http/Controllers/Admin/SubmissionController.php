<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSubmissionRequest;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\SubmissionFiles;
use App\Support\CsvDownload;
use App\Support\SubmissionPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubmissionController extends Controller
{
    public function index(Request $request, Form $form)
    {
        $fields = $form->fields()->get();

        $query = $form->submissions()->with(['cosplayer.user', 'values']);
        $this->applyFilters($query, $request);

        $submissions = $query->orderBy('entry_number')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (FormSubmission $sub) => [
                'id' => $sub->id,
                'entry_number' => $sub->entry_number,
                'status' => $sub->status,
                'cosplayer' => [
                    'name' => $sub->cosplayer->user->name,
                    'character_name' => $sub->cosplayer->character_name,
                    'series' => $sub->cosplayer->series,
                ],
                'submitted_at' => $sub->submitted_at?->toDateTimeString(),
                'values' => SubmissionPresenter::values($fields, $sub),
                'processing' => SubmissionPresenter::processing($sub),
            ]);

        return Inertia::render('Admin/Forms/Submissions', [
            'form' => $form,
            'fields' => $fields,
            'submissions' => $submissions,
            'filters' => $request->only('q', 'status'),
            'counts' => $form->submissions()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    /** One submission in full (admin / judge view). */
    public function show(Form $form, FormSubmission $submission)
    {
        abort_if($submission->form_id !== $form->id, 404);

        $submission->load(['cosplayer.user', 'values']);
        $fields = $form->fields()->get();

        return Inertia::render('Admin/Forms/SubmissionDetail', [
            'form' => $form->only('id', 'name', 'event_id'),
            'fields' => $fields,
            'submission' => [
                'id' => $submission->id,
                'entry_number' => $submission->entry_number,
                'status' => $submission->status,
                'admin_notes' => $submission->admin_notes,
                'submitted_at' => $submission->submitted_at?->toDateTimeString(),
                'cosplayer' => [
                    'name' => $submission->cosplayer->user->name,
                    'email' => $submission->cosplayer->user->email,
                    'character_name' => $submission->cosplayer->character_name,
                    'series' => $submission->cosplayer->series,
                    'experience_level' => $submission->cosplayer->experience_level,
                    'bio' => $submission->cosplayer->bio,
                ],
                'values' => SubmissionPresenter::values($fields, $submission),
                'processing' => SubmissionPresenter::processing($submission),
            ],
        ]);
    }

    /** Approve / reject an entry, or save internal notes. */
    public function update(UpdateSubmissionRequest $request, Form $form, FormSubmission $submission)
    {
        abort_if($submission->form_id !== $form->id, 404);

        $submission->update($request->validated());

        return back()->with('success', 'Entry updated.');
    }

    /** Remove an entry (lets the cosplayer submit again) together with its uploaded files. */
    public function destroy(Form $form, FormSubmission $submission, SubmissionFiles $files)
    {
        abort_if($submission->form_id !== $form->id, 404);

        $files->purgeForSubmissions([$submission->id]);
        $submission->delete();

        return redirect()->route('admin.forms.submissions', $form)->with('success', 'Entry deleted.');
    }

    /** CSV of every entry (respects the current search / status filter). */
    public function export(Request $request, Form $form)
    {
        $fields = $form->fields()->get()->filter->holdsAnswer()->values();

        $query = $form->submissions()->with(['cosplayer.user', 'values'])->orderBy('entry_number');
        $this->applyFilters($query, $request);

        $rows = (function () use ($query, $fields) {
            foreach ($query->lazyById(200) as $sub) {
                $answers = SubmissionPresenter::values($fields, $sub);

                yield [
                    $sub->entry_number,
                    $sub->status,
                    $sub->cosplayer->user->name,
                    $sub->cosplayer->user->email,
                    $sub->cosplayer->character_name,
                    $sub->cosplayer->series,
                    $sub->submitted_at?->format('Y-m-d H:i'),
                    ...$fields->map(fn ($f) => $answers[$f->id] ?? '')->all(),
                ];
            }
        })();

        return CsvDownload::make(
            str($form->name)->slug().'_entries.csv',
            ['Entry #', 'Status', 'Name', 'Email', 'Character', 'Series', 'Submitted At', ...$fields->pluck('label')->all()],
            $rows
        );
    }

    private function applyFilters($query, Request $request): void
    {
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($q = trim((string) $request->query('q'))) {
            $like = '%'.addcslashes($q, '%_\\').'%';

            $query->where(function ($w) use ($q, $like) {
                $w->whereHas('cosplayer', fn ($c) => $c->where('character_name', 'like', $like)->orWhere('series', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)));

                if (ctype_digit($q)) {
                    $w->orWhere('entry_number', (int) $q);
                }
            });
        }
    }
}
