<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/**
 * The React pages rely on the exact props the controllers send. These tests pin that
 * "contract" so a backend refactor cannot silently break a page.
 */
class PageContractTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixtures();
    }

    public function test_fill_page_sends_server_side_upload_limits_for_each_file_field(): void
    {
        $form = $this->makeForm();
        $this->addField($form, 'video', 'Show video', options: ['max_size_mb' => 100, 'accepted_formats' => 'video/mp4']);
        $this->addField($form, 'text', 'Act name');
        $user = $this->makeCosplayer();

        $this->actingAs($user)->get("/events/{$form->event_id}/forms/{$form->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Forms/Fill')
                ->where('closed_reason', null)
                ->where('submission', null)
                ->has('fields', 2)
                ->where('fields.0.max_kb', 102400)
                ->where('fields.0.accepted_mimes', ['video/mp4'])
                ->where('fields.1.max_kb', null)
                ->has('upload_limit_mb'));
    }

    public function test_fill_page_explains_when_the_form_is_closed_or_already_submitted(): void
    {
        $closed = $this->makeForm(null, ['is_active' => false]);
        $user = $this->makeCosplayer();

        $this->actingAs($user)->get("/events/{$closed->event_id}/forms/{$closed->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('closed_reason', 'This form is not currently accepting responses.')
                ->where('fields', []));

        $open = $this->makeForm();
        $this->actingAs($user)->post($this->submitUrl($open), ['values' => []]);

        $this->actingAs($user)->get("/events/{$open->event_id}/forms/{$open->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('submission.entry_number', 1)
                ->where('fields', []));
    }

    public function test_profile_lists_forms_with_entry_number_and_closed_reason(): void
    {
        $event = $this->makeEvent();
        $open = $this->makeForm($event, ['name' => 'Open one']);
        $full = $this->makeForm($event, ['name' => 'Full one', 'max_submissions' => 1]);
        $user = $this->makeCosplayer('A');

        $this->actingAs($user)->post($this->submitUrl($open), ['values' => []]);
        $this->actingAs($this->makeCosplayer('B'))->post($this->submitUrl($full), ['values' => []]);   // fills "Full one"

        $this->actingAs($user)->get('/profile-page')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cosplay/Profile')
                ->where('events.0.forms.0.submission.entry_number', 1)
                ->where('events.0.forms.1.closed_reason', 'This form has reached its maximum number of entries.'));
    }

    public function test_admin_entries_list_is_paginated_and_searchable(): void
    {
        $form = $this->makeForm();
        foreach (range(1, 30) as $i) {
            $this->actingAs($this->makeCosplayer("Person {$i}"), 'web')->post($this->submitUrl($form), ['values' => []]);
        }

        $this->actingAs($this->owner, 'admin')->get("/admin/forms/{$form->id}/submissions")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Forms/Submissions')
                ->has('submissions.data', 25)
                ->where('submissions.total', 30)
                ->has('submissions.links')
                ->where('counts.pending', 30));

        $this->get("/admin/forms/{$form->id}/submissions?q=Person 7")
            ->assertInertia(fn (Assert $page) => $page->has('submissions.data', 1)->where('submissions.data.0.cosplayer.name', 'Person 7'));

        $this->get("/admin/forms/{$form->id}/submissions?q=30")
            ->assertInertia(fn (Assert $page) => $page->where(
                'submissions.data',
                fn ($rows) => collect($rows)->pluck('entry_number')->contains(30)   // numeric search also matches entry numbers
            ));
    }

    public function test_builder_page_receives_what_the_save_warning_needs(): void
    {
        $form = $this->makeForm();
        $this->addField($form, 'text', 'Act name');

        $this->actingAs($this->owner, 'admin')->get("/admin/events/{$form->event_id}/forms/{$form->id}/builder")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Forms/Builder')
                ->has('fields', 1)
                ->where('submission_count', 0)
                ->has('upload_limit_mb'));
    }

    public function test_dashboard_is_paginated(): void
    {
        foreach (range(1, 27) as $i) {
            $this->makeCosplayer("Cosplayer {$i}");
        }

        $this->actingAs($this->owner, 'admin')->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('cosplayers.data', 25)
                ->where('cosplayers.total', 27)
                ->where('stats.total_cosplayers', 27));
    }

    public function test_shared_props_expose_the_admin_role_but_no_password_data(): void
    {
        $this->actingAs($this->makeAdmin('judge'), 'admin')->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.adminUser.role', 'judge')
                ->where('auth.adminUser.can_manage', false)
                ->missing('auth.adminUser.password'));
    }

    public function test_event_edit_page_gets_datetime_input_values(): void
    {
        $event = $this->makeEvent();
        $event->update(['starts_at' => '2026-08-15 09:30:00']);

        $this->actingAs($this->owner, 'admin')->get("/admin/events/{$event->id}/edit")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Events/Create')
                ->where('event.starts_at', '2026-08-15T09:30'));
    }
}
