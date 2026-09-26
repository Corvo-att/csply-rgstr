<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\SubmissionController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

// ── Authentication & Profiles ─────────────────────────────────────────────────
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/admin/login', [AuthController::class, 'adminLogin']);
Route::get('/cosplayers/profile', [AuthController::class, 'getCosplayerProfile']);
Route::post('/cosplayers/profile', [AuthController::class, 'saveCosplayerProfile']);

// ── Events ────────────────────────────────────────────────────────────────────
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{id}', [EventController::class, 'show']);
Route::post('/events', [EventController::class, 'store']);
Route::patch('/events/{id}/status', [EventController::class, 'updateStatus']);
Route::delete('/events/{id}', [EventController::class, 'destroy']);

// ── Forms & Form Builder ──────────────────────────────────────────────────────
Route::get('/events/{eventId}/forms', [FormController::class, 'indexByEvent']);
Route::post('/events/{eventId}/forms', [FormController::class, 'store']);
Route::get('/forms/{id}', [FormController::class, 'show']);
Route::patch('/forms/{id}/status', [FormController::class, 'toggleStatus']);
Route::delete('/forms/{id}', [FormController::class, 'destroy']);
Route::put('/forms/{id}/fields', [FormController::class, 'saveFieldsBatch']);

// ── Submissions & Dashboard ───────────────────────────────────────────────────
Route::post('/forms/{id}/submit', [SubmissionController::class, 'submit']);
Route::get('/forms/{id}/submissions', [SubmissionController::class, 'getSubmissions']);
Route::get('/admin/dashboard', [SubmissionController::class, 'dashboard']);

// ── File & Video Uploads ──────────────────────────────────────────────────────
Route::post('/upload', [UploadController::class, 'upload']);
