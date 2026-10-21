<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // ── Stable identity + edit counter on every submission ────────────────
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->unsignedInteger('version')->default(1)->after('admin_notes');
            $table->timestamp('last_edited_at')->nullable()->after('submitted_at');
        });

        DB::table('form_submissions')->whereNull('uuid')->orderBy('id')->select('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('form_submissions')->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
            }
        });

        // Entries created by an import before this migration have no entry number: give them one.
        $counters = DB::table('form_submissions')->selectRaw('form_id, max(entry_number) as m')->groupBy('form_id')->pluck('m', 'form_id');
        DB::table('form_submissions')->whereNull('entry_number')->orderBy('id')->select(['id', 'form_id'])->chunkById(500, function ($rows) use (&$counters) {
            foreach ($rows as $row) {
                $counters[$row->form_id] = ($counters[$row->form_id] ?? 0) + 1;
                DB::table('form_submissions')->where('id', $row->id)->update(['entry_number' => $counters[$row->form_id]]);
            }
        });

        // ── Full edit history: nothing an edit overwrites is ever lost ────────
        Schema::create('submission_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_submission_id')->constrained('form_submissions')->cascadeOnDelete();
            $table->unsignedInteger('version');                 // the version that was REPLACED
            $table->json('snapshot');                           // status, admin_notes, values keyed by field_key
            $table->string('source', 30);                       // admin | import | sheet_pull | web
            $table->string('actor_type', 20)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['form_submission_id', 'version']);
        });

        // ── Deleted submissions are marked "Deleted" in the sheet, never removed ─
        Schema::create('sheet_pending_deletes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_sheet_link_id')->constrained('form_sheet_links')->cascadeOnDelete();
            $table->unsignedBigInteger('submission_id');
            $table->unsignedInteger('entry_number')->nullable();
            $table->timestamps();

            $table->unique(['form_sheet_link_id', 'submission_id'], 'spd_link_sub_unique');
        });

        // ── Sheet -> database: review changes made in the sheet, then apply ───
        Schema::create('sheet_pull_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_sheet_link_id')->constrained('form_sheet_links')->cascadeOnDelete();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('status', 20)->default('reading');   // reading | ready | applied | failed
            $table->json('changes')->nullable();
            $table->json('stats')->nullable();
            $table->json('applied_stats')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['form_sheet_link_id', 'status']);
        });

        // ── Imports may now come from a Google Sheet as well as a file ────────
        Schema::table('import_batches', function (Blueprint $table) {
            $table->string('source_type', 20)->default('file')->after('event_id');
            $table->string('spreadsheet_id')->nullable()->after('source_type');
            $table->unsignedBigInteger('google_connection_id')->nullable()->after('spreadsheet_id');
        });
        Schema::table('import_batches', function (Blueprint $table) {
            $table->string('original_filename')->nullable()->change();
            $table->string('stored_path')->nullable()->change();
            $table->string('file_hash', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'spreadsheet_id', 'google_connection_id']);
        });
        Schema::dropIfExists('sheet_pull_batches');
        Schema::dropIfExists('sheet_pending_deletes');
        Schema::dropIfExists('submission_revisions');
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'version', 'last_edited_at']);
        });
    }
};
