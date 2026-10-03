<?php

namespace Tests\Feature;

use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/** "Never trust data from the browser" - the server must enforce every rule the form shows. */
class SubmissionValidationTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixtures();
    }

    public function test_required_fields_are_enforced_by_the_server(): void
    {
        $form = $this->makeForm();
        $name = $this->addField($form, 'text', 'Act name', required: true);
        $this->addField($form, 'terms', 'Rules', required: true);

        $this->actingAs($this->makeCosplayer())
            ->post($this->submitUrl($form), ['values' => []])
            ->assertSessionHasErrors(["values.{$name->id}"]);

        $this->assertSame(0, FormSubmission::count());
    }

    public function test_required_checkbox_must_actually_be_ticked(): void
    {
        $form = $this->makeForm();
        $terms = $this->addField($form, 'terms', 'Rules', required: true);
        $user = $this->makeCosplayer();

        $this->actingAs($user)->post($this->submitUrl($form), ['values' => [$terms->id => '0']])
            ->assertSessionHasErrors("values.{$terms->id}");

        $this->actingAs($user)->post($this->submitUrl($form), ['values' => [$terms->id => '1']])
            ->assertSessionHasNoErrors();
    }

    public function test_choice_email_and_number_rules(): void
    {
        $form = $this->makeForm();
        $category = $this->addField($form, 'dropdown', 'Category', required: true, options: ['choices' => ['Solo', 'Duo']]);
        $email = $this->addField($form, 'email', 'Contact');
        $age = $this->addField($form, 'number', 'Age', options: ['min' => 12, 'max' => 99]);

        $this->actingAs($this->makeCosplayer())
            ->post($this->submitUrl($form), ['values' => [$category->id => 'Army', $email->id => 'nope', $age->id => '5']])
            ->assertSessionHasErrors(["values.{$category->id}", "values.{$email->id}", "values.{$age->id}"]);
    }

    public function test_unknown_field_ids_and_other_forms_fields_are_rejected(): void
    {
        $form = $this->makeForm();
        $this->addField($form, 'text', 'Act name');
        $other = $this->addField($this->makeForm(), 'text', 'Someone elses field');

        $this->actingAs($this->makeCosplayer())
            ->post($this->submitUrl($form), ['values' => [$other->id => 'injected', 999999 => 'junk']])
            ->assertSessionHasErrors('values');

        $this->assertSame(0, FormSubmissionValue::count());
    }

    public function test_layout_fields_cannot_receive_answers(): void
    {
        $form = $this->makeForm();
        $section = $this->addField($form, 'section', 'Part 1');

        $this->actingAs($this->makeCosplayer())
            ->post($this->submitUrl($form), ['values' => [$section->id => 'x']])
            ->assertSessionHasErrors('values');
    }

    public function test_a_valid_submission_is_stored_with_sequential_entry_numbers(): void
    {
        $form = $this->makeForm();
        $name = $this->addField($form, 'text', 'Act name', required: true);
        $tags = $this->addField($form, 'checkbox_group', 'Props', options: ['choices' => ['Sword', 'Wig']]);

        $this->actingAs($this->makeCosplayer('A'))
            ->post($this->submitUrl($form), ['values' => [$name->id => 'First act', $tags->id => ['Sword', 'Wig']]])
            ->assertRedirect('/profile-page')->assertSessionHasNoErrors();
        $this->actingAs($this->makeCosplayer('B'))
            ->post($this->submitUrl($form), ['values' => [$name->id => 'Second act']])->assertSessionHasNoErrors();

        $this->assertSame([1, 2], FormSubmission::orderBy('id')->pluck('entry_number')->all());
        $this->assertSame('["Sword","Wig"]', FormSubmissionValue::where('form_field_id', $tags->id)->value('value'));
    }

    public function test_a_cosplayer_cannot_submit_the_same_form_twice(): void
    {
        $form = $this->makeForm();
        $name = $this->addField($form, 'text', 'Act name');
        $user = $this->makeCosplayer();

        $this->actingAs($user)->post($this->submitUrl($form), ['values' => [$name->id => 'one']])->assertSessionHasNoErrors();
        $this->actingAs($user)->post($this->submitUrl($form), ['values' => [$name->id => 'two']])->assertSessionHasErrors('values');

        $this->assertSame(1, FormSubmission::count());
    }

    public function test_closed_events_inactive_forms_and_schedule_are_respected(): void
    {
        $user = $this->makeCosplayer();

        $cases = [
            'draft event' => $this->makeForm($this->makeEvent('draft')),
            'closed event' => $this->makeForm($this->makeEvent('closed')),
            'inactive form' => $this->makeForm(null, ['is_active' => false]),
            'not open yet' => $this->makeForm(null, ['opens_at' => now()->addDay()]),
            'deadline passed' => $this->makeForm(null, ['closes_at' => now()->subMinute()]),
        ];

        foreach ($cases as $label => $form) {
            $this->actingAs($user)->post($this->submitUrl($form), ['values' => []])
                ->assertSessionHasErrors('values');
        }

        $this->assertSame(0, FormSubmission::count());
    }

    public function test_max_submissions_limit_closes_the_form(): void
    {
        $form = $this->makeForm(null, ['max_submissions' => 1]);

        $this->actingAs($this->makeCosplayer('A'))->post($this->submitUrl($form), ['values' => []])->assertSessionHasNoErrors();
        $this->actingAs($this->makeCosplayer('B'))->post($this->submitUrl($form), ['values' => []])->assertSessionHasErrors('values');
    }

    public function test_a_form_must_belong_to_the_event_in_the_url(): void
    {
        $form = $this->makeForm();
        $wrongEvent = $this->makeEvent();

        $this->actingAs($this->makeCosplayer())
            ->post("/events/{$wrongEvent->id}/forms/{$form->id}/submit", ['values' => []])
            ->assertNotFound();
    }

    public function test_a_user_without_a_cosplayer_profile_cannot_submit(): void
    {
        $form = $this->makeForm();
        $user = User::create(['name' => 'No profile', 'email' => 'np@test.local', 'password' => 'password-123456']);

        $this->actingAs($user)->post($this->submitUrl($form), ['values' => []])->assertSessionHasErrors('values');
    }

    public function test_guests_cannot_submit(): void
    {
        $form = $this->makeForm();

        $this->post($this->submitUrl($form), ['values' => []])->assertRedirect('/login');
    }
}
