<?php

namespace Tests\Feature;

use App\Jobs\BackfillFormToSheet;
use App\Jobs\FlushSheetLink;
use App\Models\Admin;
use App\Models\Cosplayer;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSheetLink;
use App\Models\FormSubmission;
use App\Models\GoogleConnection;
use App\Models\SheetColumnMapping;
use App\Models\SheetPendingDelete;
use App\Models\SheetPullBatch;
use App\Models\SheetSyncRecord;
use App\Models\SubmissionRevision;
use App\Models\User;
use App\Services\GoogleSheets\SheetsGateway;
use App\Services\GoogleSheets\SheetPullService;
use App\Services\GoogleSheets\SubmissionRowBuilder;
use App\Services\SubmissionRevisionRecorder;
use App\Services\SubmissionWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class GoogleSheetsSyncTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected Form $form;
    protected Admin $admin;
    protected GoogleConnection $connection;
    protected FormSheetLink $link;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixtures();

        $this->admin = $this->owner;
        $this->form = $this->makeForm();

        // Create a fake connected Google account
        $this->connection = GoogleConnection::create([
            'admin_id'         => $this->admin->id,
            'google_user_id'   => 'google-user-123',
            'email'            => 'admin@gmail.com',
            'access_token'     => 'test-token',
            'refresh_token'    => 'test-refresh-token',
            'token_expires_at' => now()->addHour(),
            'scopes'           => ['https://www.googleapis.com/auth/spreadsheets'],
        ]);

        // Create a FormSheetLink
        $this->link = FormSheetLink::create([
            'form_id'                => $this->form->id,
            'google_connection_id'   => $this->connection->id,
            'spreadsheet_id'         => 'sheet_test_abc123',
            'spreadsheet_url'        => 'https://docs.google.com/spreadsheets/d/sheet_test_abc123',
            'spreadsheet_title'      => 'Test Sheet',
            'sheet_gid'              => 0,
            'sheet_title'            => 'Responses',
            'header_row'             => 1,
            'sync_enabled'           => true,
            'auto_add_new_fields'    => true,
            'include_system_columns' => ['submission_id', 'entry_number', 'cosplayer_name', 'status'],
            'status'                 => 'connected',
        ]);

        // Setup active column mappings
        SheetColumnMapping::create([
            'form_sheet_link_id' => $this->link->id,
            'column_index'       => 0,
            'header_label'       => 'Submission ID',
            'source_type'        => 'system',
            'source_key'         => 'submission_id',
            'is_active'          => true,
        ]);

        SheetColumnMapping::create([
            'form_sheet_link_id' => $this->link->id,
            'column_index'       => 1,
            'header_label'       => 'Cosplayer Name',
            'source_type'        => 'system',
            'source_key'         => 'cosplayer_name',
            'is_active'          => true,
        ]);

        SheetColumnMapping::create([
            'form_sheet_link_id' => $this->link->id,
            'column_index'       => 2,
            'header_label'       => 'Status',
            'source_type'        => 'system',
            'source_key'         => 'status',
            'is_active'          => true,
        ]);
    }

    public function test_submission_writer_tracks_versioning_and_revisions(): void
    {
        Queue::fake([FlushSheetLink::class]);

        $user = $this->makeCosplayer('Tanjiro Kamado');
        $cosplayer = $user->cosplayer;

        $field = $this->addField($this->form, 'text', 'Bio');

        $writer = app(SubmissionWriter::class);

        $submission = $writer->write(
            form: $this->form,
            cosplayer: $cosplayer,
            values: [$field->id => 'First bio'],
            source: 'admin'
        );

        $this->assertNotNull($submission->uuid);
        $this->assertEquals(1, $submission->version);
        $this->assertEquals('First bio', $submission->values()->where('form_field_id', $field->id)->value('value'));

        // Update the submission
        $updated = $writer->update(
            submission: $submission,
            values: [$field->id => 'Updated bio'],
            source: 'admin'
        );

        $this->assertEquals(2, $updated->version);
        $this->assertNotNull($updated->last_edited_at);
        $this->assertEquals('Updated bio', $updated->values()->where('form_field_id', $field->id)->value('value'));
        $this->assertEquals(1, SubmissionRevision::where('form_submission_id', $submission->id)->count());
    }

    public function test_flush_sheet_link_appends_new_rows(): void
    {
        $user = $this->makeCosplayer('Zenitsu Agatsuma');
        $sub = FormSubmission::create([
            'form_id'      => $this->form->id,
            'cosplayer_id' => $user->cosplayer->id,
            'entry_number' => 101,
            'status'       => 'pending',
            'submitted_at' => now(),
            'version'      => 1,
        ]);

        $record = SheetSyncRecord::create([
            'form_sheet_link_id' => $this->link->id,
            'form_submission_id' => $sub->id,
            'status'             => 'pending',
            'attempts'           => 0,
        ]);

        $mock = Mockery::mock(SheetsGateway::class);
        $mock->shouldReceive('readColumn')
            ->once()
            ->with(Mockery::any(), 'sheet_test_abc123', 'Responses', 'A', 2)
            ->andReturn([]); // No rows exist yet

        $mock->shouldReceive('appendRows')
            ->once()
            ->with(
                Mockery::any(),
                'sheet_test_abc123',
                'Responses',
                Mockery::on(function ($rows) use ($sub) {
                    return count($rows) === 1 && $rows[0][0] == $sub->id && $rows[0][2] === 'Pending';
                })
            )
            ->andReturn(['updatedRange' => 'Responses!A2:C2', 'updatedRows' => 1]);

        $job = new FlushSheetLink($this->link->id);
        $job->handle($mock, app(SubmissionRowBuilder::class));

        $record->refresh();
        $this->assertEquals('synced', $record->status);
        $this->assertEquals(2, $record->sheet_row);
        $this->assertNotNull($record->synced_at);
        $this->assertNotNull($record->payload_hash);
    }

    public function test_flush_sheet_link_updates_existing_rows_safely(): void
    {
        $user = $this->makeCosplayer('Inosuke Hashibira');
        $sub = FormSubmission::create([
            'form_id'      => $this->form->id,
            'cosplayer_id' => $user->cosplayer->id,
            'entry_number' => 102,
            'status'       => 'approved',
            'submitted_at' => now(),
            'version'      => 2,
        ]);

        $record = SheetSyncRecord::create([
            'form_sheet_link_id' => $this->link->id,
            'form_submission_id' => $sub->id,
            'status'             => 'pending',
            'sheet_row'          => 2,
            'attempts'           => 0,
        ]);

        $mock = Mockery::mock(SheetsGateway::class);
        // Suppose the sheet was sorted and $sub is now at row 5 (index 3 in data rows starting at 2)
        $mock->shouldReceive('readColumn')
            ->once()
            ->with(Mockery::any(), 'sheet_test_abc123', 'Responses', 'A', 2)
            ->andReturn(['999', '888', '777', (string) $sub->id]);

        $mock->shouldReceive('batchUpdateRows')
            ->once()
            ->with(
                Mockery::any(),
                'sheet_test_abc123',
                'Responses',
                Mockery::on(function ($rowsByNumber) use ($sub) {
                    // Row 5 should receive the update
                    return isset($rowsByNumber[5]) && $rowsByNumber[5][0] == $sub->id && $rowsByNumber[5][2] === 'Approved';
                }),
                Mockery::any()
            );

        $job = new FlushSheetLink($this->link->id);
        $job->handle($mock, app(SubmissionRowBuilder::class));

        $record->refresh();
        $this->assertEquals('synced', $record->status);
        $this->assertEquals(5, $record->sheet_row); // Verified row dynamically remapped!
    }

    public function test_flush_sheet_link_marks_deleted_rows_as_deleted_status(): void
    {
        $pendingDelete = SheetPendingDelete::create([
            'form_sheet_link_id' => $this->link->id,
            'submission_id'      => 404,
            'entry_number'       => 99,
            'uuid'               => 'fake-uuid-404',
            'cosplayer_name'     => 'Deleted Cosplayer',
        ]);

        $mock = Mockery::mock(SheetsGateway::class);
        $mock->shouldReceive('readColumn')
            ->once()
            ->with(Mockery::any(), 'sheet_test_abc123', 'Responses', 'A', 2)
            ->andReturn(['404']); // Found at row 2

        $mock->shouldReceive('batchUpdateRows')
            ->once()
            ->with(
                Mockery::any(),
                'sheet_test_abc123',
                'Responses',
                Mockery::on(function ($rowsByNumber) {
                    // Row 2 status column should be 'Deleted'
                    return isset($rowsByNumber[2]) && $rowsByNumber[2][2] === 'Deleted';
                }),
                [2] // Column index for status is 2
            );

        $job = new FlushSheetLink($this->link->id);
        $job->handle($mock, app(SubmissionRowBuilder::class));

        $this->assertEquals(0, SheetPendingDelete::where('id', $pendingDelete->id)->count());
    }

    public function test_sheet_pull_service_detects_edits_and_applies_with_revisions(): void
    {
        $user = $this->makeCosplayer('Kanao Tsuyuri');
        $sub = FormSubmission::create([
            'form_id'      => $this->form->id,
            'cosplayer_id' => $user->cosplayer->id,
            'entry_number' => 103,
            'status'       => 'pending',
            'submitted_at' => now(),
            'version'      => 1,
        ]);

        $mock = Mockery::mock(SheetsGateway::class);
        // Column mapping: 0 => submission_id, 1 => cosplayer_name, 2 => status
        // Google Sheet has name changed to 'Kanao Tsuyuri (Updated)' and status changed to 'Approved'
        $mock->shouldReceive('readRows')
            ->once()
            ->with(Mockery::any(), 'sheet_test_abc123', 'Responses', 2)
            ->andReturn([
                [(string) $sub->id, 'Kanao Tsuyuri (Updated)', 'Approved'],
            ]);

        $pullService = new SheetPullService(
            $mock,
            app(SubmissionRowBuilder::class),
            app(SubmissionRevisionRecorder::class)
        );

        $batch = $pullService->scan($this->link, $this->admin);

        $this->assertEquals('ready', $batch->status);
        $this->assertEquals(1, count($batch->changes));
        $this->assertNotEmpty($batch->changes[0]['diffs']);

        // Apply changes
        $res = $pullService->apply($batch, [$sub->id]);
        $this->assertEquals(1, $res['applied_count']);

        $sub->refresh();
        $this->assertEquals('approved', $sub->status);
        $this->assertEquals(2, $sub->version);
        $this->assertEquals('Kanao Tsuyuri (Updated)', $user->fresh()->name);

        // Verify revision was recorded with source = 'sheet_pull'
        $rev = SubmissionRevision::where('form_submission_id', $sub->id)->latest('id')->first();
        $this->assertNotNull($rev);
        $this->assertEquals('sheet_pull', $rev->source);
        $this->assertEquals('admin', $rev->actor_type);
    }

    public function test_backfill_form_to_sheet_modes(): void
    {
        Queue::fake();

        $user1 = $this->makeCosplayer('Shinobu Kocho');
        $sub1 = FormSubmission::create([
            'form_id'      => $this->form->id,
            'cosplayer_id' => $user1->cosplayer->id,
            'entry_number' => 201,
            'status'       => 'approved',
            'submitted_at' => now(),
            'version'      => 1,
        ]);

        $user2 = $this->makeCosplayer('Giyu Tomioka');
        $sub2 = FormSubmission::create([
            'form_id'      => $this->form->id,
            'cosplayer_id' => $user2->cosplayer->id,
            'entry_number' => 202,
            'status'       => 'pending',
            'submitted_at' => now(),
            'version'      => 1,
        ]);

        // sub1 is already synced
        SheetSyncRecord::create([
            'form_sheet_link_id' => $this->link->id,
            'form_submission_id' => $sub1->id,
            'status'             => 'synced',
            'sheet_row'          => 2,
        ]);

        $mock = Mockery::mock(SheetsGateway::class);

        // 1. Missing mode: should only flag sub2
        (new BackfillFormToSheet($this->form->id, 'missing'))->handle($mock);

        $this->assertEquals('synced', SheetSyncRecord::where('form_submission_id', $sub1->id)->value('status'));
        $this->assertEquals('pending', SheetSyncRecord::where('form_submission_id', $sub2->id)->value('status'));

        // 2. Rebuild mode: clears rows, deletes sync records, and re-queues all
        $mock->shouldReceive('clearDataRows')
            ->once()
            ->with(Mockery::any(), 'sheet_test_abc123', 'Responses', 2);

        (new BackfillFormToSheet($this->form->id, 'rebuild'))->handle($mock);

        $this->assertEquals('pending', SheetSyncRecord::where('form_submission_id', $sub1->id)->value('status'));
        $this->assertEquals('pending', SheetSyncRecord::where('form_submission_id', $sub2->id)->value('status'));
    }

    public function test_google_sheet_import_batch_flow(): void
    {
        $mock = Mockery::mock(SheetsGateway::class);
        $this->app->instance(SheetsGateway::class, $mock);

        $mock->shouldReceive('readHeaderRow')
            ->once()
            ->with(Mockery::any(), 'sheet_test_abc123', 'Responses', 1)
            ->andReturn(['Name', 'Character', 'Series', 'Email']);

        $mock->shouldReceive('readRows')
            ->once()
            ->with(Mockery::any(), 'sheet_test_abc123', 'Responses', 2)
            ->andReturn([
                ['Kyojuro Rengoku', 'Rengoku', 'Demon Slayer', 'rengoku@test.local'],
            ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post("/admin/forms/{$this->form->id}/import/google-sheet", [
                'spreadsheet_id' => 'sheet_test_abc123',
                'sheet_name'     => 'Responses',
                'header_row'     => 1,
            ]);

        $response->assertSessionHasNoErrors();
        $batch = \App\Models\ImportBatch::where('form_id', $this->form->id)->where('source_type', 'google_sheet')->latest('id')->first();
        $this->assertNotNull($batch);
        $this->assertEquals('google_sheet', $batch->source_type);
        $this->assertEquals('sheet_test_abc123', $batch->spreadsheet_id);
        $this->assertEquals('mapping', $batch->status);
        $this->assertEquals(1, $batch->rows()->count());

        $firstRow = $batch->rows()->first();
        $this->assertEquals('Kyojuro Rengoku', $firstRow->raw['Name']);
        $this->assertEquals('rengoku@test.local', $firstRow->raw['Email']);
    }
}

