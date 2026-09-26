<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\FormBuilderController;
use App\Http\Controllers\Admin\FormFieldController;
use App\Http\Controllers\Admin\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {

    // ── Guest-only (admin not logged in) ──────────────────────────────────────
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login',  [AdminAuthController::class, 'create'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'store']);
    });

    // ── Authenticated admin ───────────────────────────────────────────────────
    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');

        // Dashboard + cosplayer export
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/cosplayers/export', [DashboardController::class, 'exportCosplayers'])->name('cosplayers.export');

        // Events CRUD
        Route::resource('events', EventController::class)->except(['edit']);

        // Form builder + delete form
        Route::get('events/{event}/forms/{form}/builder', [FormBuilderController::class, 'edit'])->name('forms.builder');
        Route::delete('events/{event}/forms/{form}',     [FormBuilderController::class, 'destroy'])->name('forms.destroy');

        // Form fields CRUD + reorder
        Route::post('forms/{form}/fields',           [FormFieldController::class, 'store'])->name('form-fields.store');
        Route::patch('form-fields/{field}',           [FormFieldController::class, 'update'])->name('form-fields.update');
        Route::delete('form-fields/{field}',          [FormFieldController::class, 'destroy'])->name('form-fields.destroy');
        Route::patch('form-fields/reorder',           [FormFieldController::class, 'reorder'])->name('form-fields.reorder');

        // Submissions
        Route::get('forms/{form}/submissions',        [SubmissionController::class, 'index'])->name('forms.submissions');
        Route::get('forms/{form}/submissions/export', [SubmissionController::class, 'export'])->name('forms.submissions.export');
    });
});
