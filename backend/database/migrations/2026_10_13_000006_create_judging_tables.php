<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('judging_rubrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('judging_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('judging_rubric_id')->constrained('judging_rubrics')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('max_points')->default(20);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('judging_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('judging_rubric_id')->constrained('judging_rubrics')->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('google_connection_id')->nullable()->constrained('google_connections')->nullOnDelete();
            $table->string('spreadsheet_id')->nullable();
            $table->string('spreadsheet_url')->nullable();
            $table->json('judge_names');
            $table->json('options')->nullable();
            $table->string('status', 30)->default('generated');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judging_sheets');
        Schema::dropIfExists('judging_criteria');
        Schema::dropIfExists('judging_rubrics');
    }
};
