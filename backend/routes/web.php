<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminFormController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\FormBuilderController;
use App\Http\Controllers\Admin\FormFieldController;
use App\Http\Controllers\Admin\SubmissionController as AdminSubmissionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CosplayerController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FormController;
use Illuminate\Support\Facades\Route;

// ── Public ────────────────────────────────────────────────────────────────────
Route::get('/', [EventController::class, 'welcome'])->name('welcome');

// ── Guest-only (cosplayer) ────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);   // throttled inside the controller
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});

// ── Authenticated (cosplayer) ─────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/register-cosplay', [CosplayerController::class, 'create'])->name('cosplay.register');
    Route::post('/register-cosplay', [CosplayerController::class, 'store']);

    Route::get('/profile-page', [CosplayerController::class, 'profile'])->name('cosplay.profile');

    Route::get('/events/{event}/forms/{form}', [FormController::class, 'fill'])->name('forms.fill');
    Route::post('/events/{event}/forms/{form}/submit', [FormController::class, 'submit'])
        ->middleware('throttle:20,1')
        ->name('forms.submit');
});

// ── Admin ─────────────────────────────────────────────────────────────────────
// Roles: admin = everything · manager = run events/forms/entries · judge = read-only.
// The role checks are enforced HERE on the server; the React UI only hides buttons for convenience.
Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'create'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'store']);
    });

    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');

        // Write access (declared first so /events/create is not swallowed by /events/{event})
        Route::middleware('admin.role:admin,manager')->group(function () {
            Route::resource('events', AdminEventController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);

            Route::post('events/{event}/forms', [AdminFormController::class, 'store'])->name('forms.store');
            Route::patch('events/{event}/forms/{form}', [AdminFormController::class, 'update'])->name('forms.update');
            Route::delete('events/{event}/forms/{form}', [FormBuilderController::class, 'destroy'])->name('forms.destroy');

            Route::get('events/{event}/forms/{form}/builder', [FormBuilderController::class, 'edit'])->name('forms.builder');
            Route::put('forms/{form}/fields', [FormFieldController::class, 'saveBatch'])->name('form-fields.save-batch');

            Route::patch('forms/{form}/submissions/{submission}', [AdminSubmissionController::class, 'update'])->name('forms.submissions.update');
            Route::delete('forms/{form}/submissions/{submission}', [AdminSubmissionController::class, 'destroy'])->name('forms.submissions.destroy');
        });

        // Read access (judges included)
        Route::middleware('admin.role:admin,manager,judge')->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
            Route::get('/cosplayers/export', [DashboardController::class, 'exportCosplayers'])->name('cosplayers.export');

            Route::resource('events', AdminEventController::class)->only(['index', 'show']);

            // export MUST be declared before the {submission} wildcard
            Route::get('forms/{form}/submissions', [AdminSubmissionController::class, 'index'])->name('forms.submissions');
            Route::get('forms/{form}/submissions/export', [AdminSubmissionController::class, 'export'])->name('forms.submissions.export');
            Route::get('forms/{form}/submissions/{submission}', [AdminSubmissionController::class, 'show'])->name('forms.submissions.show');
        });
    });
});
