<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CosplayerController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminFormController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\FormBuilderController;
use App\Http\Controllers\Admin\FormFieldController;
use App\Http\Controllers\Admin\SubmissionController as AdminSubmissionController;
use Illuminate\Support\Facades\Route;

// ── Public ────────────────────────────────────────────────────────────────────
Route::get('/', [EventController::class, 'welcome'])->name('welcome');

// ── Guest-only (cosplayer) ────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',    [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',   [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register',[AuthController::class, 'register']);
});

// ── Authenticated (cosplayer) ─────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/register-cosplay', [CosplayerController::class, 'create'])->name('cosplay.register');
    Route::post('/register-cosplay',[CosplayerController::class, 'store']);

    Route::get('/profile-page', [CosplayerController::class, 'profile'])->name('cosplay.profile');

    Route::get('/events/{event}/forms/{form}', [FormController::class, 'fill'])->name('forms.fill');
    Route::post('/events/{event}/forms/{form}/submit', [FormController::class, 'submit'])->name('forms.submit');
});

// ── File uploads ──────────────────────────────────────────────────────────────
Route::post('/upload', [UploadController::class, 'upload'])->middleware('auth');

// ── Admin ─────────────────────────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->group(function () {

    // Guest-only admin
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login',  [AdminAuthController::class, 'create'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'store']);
    });

    // Authenticated admin
    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');

        // Dashboard + export
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/cosplayers/export', [DashboardController::class, 'exportCosplayers'])->name('cosplayers.export');

        // Events CRUD
        Route::resource('events', AdminEventController::class)->except(['edit']);

        // Form creation under an event
        Route::post('events/{event}/forms', [AdminFormController::class, 'store'])->name('forms.store');

        // Form builder
        Route::get('events/{event}/forms/{form}/builder', [FormBuilderController::class, 'edit'])->name('forms.builder');
        Route::delete('events/{event}/forms/{form}',      [FormBuilderController::class, 'destroy'])->name('forms.destroy');

        // Form fields CRUD + batch save from builder
        Route::put('forms/{form}/fields',        [FormFieldController::class, 'saveBatch'])->name('form-fields.save-batch');
        Route::post('forms/{form}/fields',       [FormFieldController::class, 'store'])->name('form-fields.store');
        Route::patch('form-fields/{field}',      [FormFieldController::class, 'update'])->name('form-fields.update');
        Route::delete('form-fields/{field}',     [FormFieldController::class, 'destroy'])->name('form-fields.destroy');

        // Submissions
        Route::get('forms/{form}/submissions',        [AdminSubmissionController::class, 'index'])->name('forms.submissions');
        Route::get('forms/{form}/submissions/export', [AdminSubmissionController::class, 'export'])->name('forms.submissions.export');
    });
});
