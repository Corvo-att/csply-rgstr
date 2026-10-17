<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deleting a field must never delete the answers already collected.
        Schema::table('form_submission_values', function (Blueprint $table) {
            $table->dropForeign(['form_field_id']);
        });
        Schema::table('form_submission_values', function (Blueprint $table) {
            $table->unsignedBigInteger('form_field_id')->nullable()->change();
            $table->foreign('form_field_id')->references('id')->on('form_fields')->nullOnDelete();
            // Keep a snapshot so orphaned answers remain readable/exportable.
            $table->string('field_key_snapshot')->nullable()->after('form_field_id');
        });

        // Stable identity for column mapping.
        Schema::table('form_fields', function (Blueprint $table) {
            $table->unique(['form_id', 'field_key'], 'form_fields_form_key_unique');
        });

        // Where did a submission come from? (used by import + sheet sync)
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->string('source', 20)->default('web')->after('cosplayer_id');
            $table->unsignedBigInteger('import_batch_id')->nullable()->after('source')->index();
        });

        // Accounts created from imports that the person can claim later.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_placeholder')->default(false)->after('password');
            $table->timestamp('invited_at')->nullable()->after('is_placeholder');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_placeholder', 'invited_at']);
        });
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropColumn(['source', 'import_batch_id']);
        });
        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropUnique('form_fields_form_key_unique');
        });
        Schema::table('form_submission_values', function (Blueprint $table) {
            $table->dropForeign(['form_field_id']);
            $table->dropColumn('field_key_snapshot');
        });
        Schema::table('form_submission_values', function (Blueprint $table) {
            $table->unsignedBigInteger('form_field_id')->nullable(false)->change();
            $table->foreign('form_field_id')->references('id')->on('form_fields')->cascadeOnDelete();
        });
    }
};
