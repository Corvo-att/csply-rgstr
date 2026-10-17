<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\GoogleConnection;
use App\Models\JudgingCriterion;
use App\Models\JudgingRubric;
use App\Models\JudgingSheet;
use App\Services\Judging\JudgeSheetBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class JudgingController extends Controller
{
    /**
     * Show judging sheet generator wizard & existing judging sheets.
     */
    public function index(Form $form)
    {
        $admin = Auth::guard('admin')->user();
        $connection = GoogleConnection::where('admin_id', $admin->id)
            ->whereNull('revoked_at')
            ->first();

        $form->load(['event', 'submissions' => fn($q) => $q->with('cosplayer.user')]);
        $rubrics = JudgingRubric::with('criteria')->where('form_id', $form->id)->get();
        $sheets = JudgingSheet::with('rubric')->where('form_id', $form->id)->latest()->get();

        return Inertia::render('Admin/Forms/Judging', [
            'form'        => $form,
            'event'       => $form->event,
            'rubrics'     => $rubrics,
            'sheets'      => $sheets,
            'connected'   => $connection !== null && $connection->isConnected(),
            'connection'  => $connection ? [
                'email'      => $connection->email,
                'avatar_url' => $connection->avatar_url,
            ] : null,
            'submissionCount' => $form->submissions->count(),
        ]);
    }

    /**
     * Create or update a scoring rubric.
     */
    public function saveRubric(Request $request, Form $form)
    {
        $validated = $request->validate([
            'rubric_id'             => ['nullable', 'integer'],
            'name'                  => ['required', 'string', 'max:150'],
            'description'           => ['nullable', 'string', 'max:500'],
            'criteria'              => ['required', 'array', 'min:1'],
            'criteria.*.name'       => ['required', 'string', 'max:100'],
            'criteria.*.max_points' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $rubric = JudgingRubric::updateOrCreate(
            ['id' => $validated['rubric_id'] ?? null, 'form_id' => $form->id],
            [
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]
        );

        // Sync criteria
        $rubric->criteria()->delete();
        foreach ($validated['criteria'] as $idx => $crit) {
            JudgingCriterion::create([
                'judging_rubric_id' => $rubric->id,
                'name'              => $crit['name'],
                'max_points'        => $crit['max_points'],
                'sort_order'        => $idx,
            ]);
        }

        return back()->with('success', 'Scoring rubric saved successfully.');
    }

    /**
     * Generate live Google Sheet or download offline .xlsx.
     */
    public function generate(Request $request, Form $form, JudgeSheetBuilder $builder)
    {
        $validated = $request->validate([
            'judging_rubric_id' => ['required', 'exists:judging_rubrics,id'],
            'judge_names'       => ['required', 'array', 'min:1'],
            'judge_names.*'     => ['required', 'string', 'max:100'],
            'score_mode'        => ['required', 'in:average,sum'],
            'export_target'     => ['required', 'in:google,offline'],
        ]);

        $rubric = JudgingRubric::with('criteria')->findOrFail($validated['judging_rubric_id']);
        $judgeNames = array_values(array_filter(array_map('trim', $validated['judge_names'])));
        $options = ['score_mode' => $validated['score_mode']];

        if ($validated['export_target'] === 'offline') {
            return $builder->downloadOfflineXlsx($form, $rubric, $judgeNames, $options);
        }

        // Export to Google
        $admin = Auth::guard('admin')->user();
        $connection = GoogleConnection::where('admin_id', $admin->id)
            ->whereNull('revoked_at')
            ->first();

        if (!$connection || !$connection->isConnected()) {
            return back()->withErrors(['google' => 'Google account is not connected. Please connect Google first.']);
        }

        try {
            $sheet = $builder->exportToGoogle($connection, $form, $rubric, $judgeNames, $options);

            return back()->with([
                'success' => "Judge Google Sheet generated successfully!",
                'sheet_url' => $sheet->spreadsheet_url,
            ]);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Failed to generate Google Sheet: ' . $e->getMessage()]);
        }
    }
}
