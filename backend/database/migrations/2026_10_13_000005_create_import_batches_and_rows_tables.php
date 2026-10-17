<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('file_hash', 64);
            $table->string('sheet_name')->nullable();
            $table->unsignedSmallInteger('header_row')->default(1);
            $table->json('column_map')->nullable();
            $table->json('options')->nullable(); // duplicate_strategy, create_accounts, etc.
            $table->string('status', 30)->default('uploaded'); // uploaded, mapping, validating, ready, importing, completed, failed, rolled_back
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw');
            $table->json('normalized')->nullable();
            $table->json('errors')->nullable();
            $table->string('status', 30)->default('pending'); // pending, valid, invalid, imported, skipped, duplicate
            $table->unsignedBigInteger('form_submission_id')->nullable()->index();
            $table->timestamps();

            $table->index(['import_batch_id', 'status']);
            $table->index(['import_batch_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
    }
};
