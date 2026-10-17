<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sheet_sync_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_submission_id')->constrained('form_submissions')->cascadeOnDelete();
            $table->foreignId('form_sheet_link_id')->constrained('form_sheet_links')->cascadeOnDelete();
            $table->unsignedInteger('sheet_row')->nullable();
            $table->string('status', 20)->default('pending'); // pending, synced, failed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['form_submission_id', 'form_sheet_link_id'], 'sub_link_unique');
            $table->index(['form_sheet_link_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sheet_sync_records');
    }
};
