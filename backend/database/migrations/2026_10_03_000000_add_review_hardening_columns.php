<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dateTime('opens_at')->nullable()->after('is_active');
            $table->dateTime('closes_at')->nullable()->after('opens_at');
            $table->unsignedInteger('max_submissions')->nullable()->after('closes_at');
        });

        Schema::table('form_submissions', function (Blueprint $table) {
            $table->unsignedInteger('entry_number')->nullable()->after('cosplayer_id');
            $table->string('status', 20)->default('pending')->after('entry_number');
            $table->text('admin_notes')->nullable()->after('status');
        });

        // Give every pre-existing submission an entry number (per form, by id)
        // and drop duplicates by the same cosplayer BEFORE adding the unique indexes.
        $seen = [];
        $counter = [];
        foreach (DB::table('form_submissions')->orderBy('id')->get(['id', 'form_id', 'cosplayer_id']) as $row) {
            $key = $row->form_id.':'.$row->cosplayer_id;
            if (isset($seen[$key])) {
                // keep the earliest submission, remove later duplicates (values cascade)
                DB::table('form_submissions')->where('id', $row->id)->delete();

                continue;
            }
            $seen[$key] = true;
            $counter[$row->form_id] = ($counter[$row->form_id] ?? 0) + 1;
            DB::table('form_submissions')->where('id', $row->id)->update(['entry_number' => $counter[$row->form_id]]);
        }

        Schema::table('form_submissions', function (Blueprint $table) {
            $table->unique(['form_id', 'cosplayer_id']);
            $table->unique(['form_id', 'entry_number']);
        });

        Schema::table('form_submission_values', function (Blueprint $table) {
            // null for ordinary answers; pending|processing|ready|failed for videos
            $table->string('processing_status', 20)->nullable()->after('value');
            $table->json('meta')->nullable()->after('processing_status');
            $table->unique(['form_submission_id', 'form_field_id']);
        });

        // Field keys become unique per form; suffix any existing duplicates first.
        $keys = [];
        foreach (DB::table('form_fields')->orderBy('id')->get(['id', 'form_id', 'field_key']) as $row) {
            $key = $row->form_id.':'.$row->field_key;
            if (isset($keys[$key])) {
                DB::table('form_fields')->where('id', $row->id)->update(['field_key' => $row->field_key.'_'.$row->id]);
            }
            $keys[$key] = true;
        }

        Schema::table('form_fields', function (Blueprint $table) {
            $table->unique(['form_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::table('form_fields', fn (Blueprint $t) => $t->dropUnique(['form_id', 'field_key']));

        Schema::table('form_submission_values', function (Blueprint $table) {
            $table->dropUnique(['form_submission_id', 'form_field_id']);
            $table->dropColumn(['processing_status', 'meta']);
        });

        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropUnique(['form_id', 'cosplayer_id']);
            $table->dropUnique(['form_id', 'entry_number']);
            $table->dropColumn(['entry_number', 'status', 'admin_notes']);
        });

        Schema::table('forms', fn (Blueprint $t) => $t->dropColumn(['opens_at', 'closes_at', 'max_submissions']));
    }
};
