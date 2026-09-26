<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('label');
            $table->string('field_key');
            $table->string('field_type', 50);
            $table->json('options')->nullable();            // choices, min/max, accept[], etc.
            $table->json('validation_rules')->nullable();  // extra rule strings
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->string('help_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};
