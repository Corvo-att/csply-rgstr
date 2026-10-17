<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_sheet_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->unique()->constrained('forms')->cascadeOnDelete();
            $table->foreignId('google_connection_id')->constrained('google_connections')->cascadeOnDelete();
            $table->string('spreadsheet_id');
            $table->string('spreadsheet_url', 1000);
            $table->string('spreadsheet_title');
            $table->unsignedBigInteger('sheet_gid')->default(0);
            $table->string('sheet_title');
            $table->unsignedSmallInteger('header_row')->default(1);
            $table->boolean('sync_enabled')->default(true);
            $table->boolean('auto_add_new_fields')->default(true);
            $table->json('include_system_columns')->nullable();
            $table->string('status', 30)->default('active'); // active, paused, error, disconnected
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('sheet_column_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_sheet_link_id')->constrained('form_sheet_links')->cascadeOnDelete();
            $table->enum('source_type', ['field', 'system'])->default('field');
            $table->string('source_key');
            $table->unsignedSmallInteger('column_index');
            $table->string('header_label');
            $table->string('transform', 30)->nullable(); // auto, text, hyperlink, image, boolean, date
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['form_sheet_link_id', 'column_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sheet_column_mappings');
        Schema::dropIfExists('form_sheet_links');
    }
};
