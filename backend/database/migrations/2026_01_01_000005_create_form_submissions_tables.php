<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('cosplayer_id')->constrained('cosplayers')->cascadeOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('form_submission_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_submission_id')->constrained('form_submissions')->cascadeOnDelete();
            $table->foreignId('form_field_id')->constrained('form_fields')->cascadeOnDelete();
            $table->text('value')->nullable(); // scalar or JSON-encoded array
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submission_values');
        Schema::dropIfExists('form_submissions');
    }
};
