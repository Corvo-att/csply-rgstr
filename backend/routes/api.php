<?php

use App\Http\Controllers\Api\EntryController;
use Illuminate\Support\Facades\Route;

// Read-only feed for the EGYCON Voting system. Authenticated with a shared bearer token.
Route::prefix('v1')->middleware(['voting.token', 'throttle:120,1'])->group(function () {
    Route::get('forms/{form}/entries', [EntryController::class, 'index'])->name('api.entries.index');
    Route::get('forms/{form}/entries/{entryNumber}', [EntryController::class, 'show'])->whereNumber('entryNumber')->name('api.entries.show');
});
