<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\FormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixtures();
    }

    public function test_logged_out_staff_are_sent_to_the_admin_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/admin/login');
        $this->get('/admin/events')->assertRedirect('/admin/login');
    }

    public function test_cosplayers_cannot_use_the_admin_area(): void
    {
        $this->actingAs($this->makeCosplayer())->get('/admin/dashboard')->assertRedirect('/admin/login');
    }

    public function test_judges_can_read_but_not_change_anything(): void
    {
        $judge = $this->makeAdmin('judge');
        $form = $this->makeForm();
        $this->actingAs($judge, 'admin');

        $this->get('/admin/dashboard')->assertOk();
        $this->get('/admin/events')->assertOk();
        $this->get("/admin/forms/{$form->id}/submissions")->assertOk();

        $this->post('/admin/events', ['name' => 'Hack', 'status' => 'published'])->assertForbidden();
        $this->patch("/admin/events/{$form->event_id}", ['status' => 'closed'])->assertForbidden();
        $this->delete("/admin/events/{$form->event_id}")->assertForbidden();
        $this->get("/admin/events/{$form->event_id}/forms/{$form->id}/builder")->assertForbidden();
        $this->put("/admin/forms/{$form->id}/fields", ['fields' => []])->assertForbidden();

        $this->assertNotNull($form->event->fresh());
    }

    public function test_managers_can_run_events_and_review_entries(): void
    {
        $manager = $this->makeAdmin('manager');
        $this->actingAs($manager, 'admin');

        $this->post('/admin/events', ['name' => 'EGYCON 2027', 'status' => 'draft'])->assertRedirect('/admin/events');
        $event = Event::where('name', 'EGYCON 2027')->firstOrFail();
        $this->assertSame('egycon-2027', $event->slug);

        // second event with the same name must not collide on the unique slug
        $this->post('/admin/events', ['name' => 'EGYCON 2027', 'status' => 'draft'])->assertRedirect('/admin/events');
        $this->assertSame(2, Event::where('name', 'EGYCON 2027')->count());

        $form = $this->makeForm($event);
        $user = $this->makeCosplayer();
        $event->update(['status' => 'published']);
        $this->actingAs($user, 'web')->post($this->submitUrl($form), ['values' => []])->assertSessionHasNoErrors();
        $submission = FormSubmission::firstOrFail();

        $this->actingAs($manager, 'admin')
            ->patch("/admin/forms/{$form->id}/submissions/{$submission->id}", ['status' => 'approved', 'admin_notes' => 'Great'])
            ->assertSessionHasNoErrors();
        $this->assertSame('approved', $submission->fresh()->status);

        $this->patch("/admin/forms/{$form->id}/submissions/{$submission->id}", ['status' => 'banana'])->assertSessionHasErrors('status');
    }

    public function test_form_settings_can_be_edited(): void
    {
        $form = $this->makeForm();

        $this->actingAs($this->makeAdmin('manager'), 'admin')
            ->patch("/admin/events/{$form->event_id}/forms/{$form->id}", [
                'is_active' => false, 'max_submissions' => 40, 'closes_at' => now()->addWeek()->format('Y-m-d\TH:i'),
            ])->assertSessionHasNoErrors();

        $form->refresh();
        $this->assertFalse($form->is_active);
        $this->assertSame(40, $form->max_submissions);
        $this->assertNotNull($form->closes_at);
    }

    public function test_repeated_wrong_passwords_are_throttled(): void
    {
        RateLimiter::clear('x');
        $admin = $this->makeAdmin('admin');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }

        // 6th attempt is refused even with the CORRECT password
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password-123456'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    public function test_cosplayer_login_is_throttled_too(): void
    {
        $user = $this->makeCosplayer();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password-123456'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_registration_requires_a_reasonable_password(): void
    {
        $this->post('/register', ['name' => 'A', 'email' => 'a@test.local', 'password' => '123456', 'password_confirmation' => '123456'])
            ->assertSessionHasErrors('password');

        $this->post('/register', ['name' => 'A', 'email' => 'a@test.local', 'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1'])
            ->assertRedirect('/register-cosplay');
    }

    public function test_csv_export_neutralises_spreadsheet_formulas(): void
    {
        $form = $this->makeForm();
        $name = $this->addField($form, 'text', 'Act name');
        $user = $this->makeCosplayer();
        $user->cosplayer->update(['character_name' => '=HYPERLINK("http://evil","click")']);

        $this->actingAs($user)->post($this->submitUrl($form), ['values' => [$name->id => '+SUM(1,1)']]);

        $csv = $this->actingAs($this->owner, 'admin')->get("/admin/forms/{$form->id}/submissions/export")
            ->assertOk()->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'+SUM", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
    }
}
