<?php

namespace Tests\Feature;

use App\Models\FormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class VotingApiTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixtures();
    }

    private function seedEntries(): array
    {
        $form = $this->makeForm();
        $act = $this->addField($form, 'text', 'Act name');
        $tags = $this->addField($form, 'checkbox_group', 'Props', options: ['choices' => ['Sword', 'Wig']]);

        foreach (['A', 'B'] as $name) {
            $this->actingAs($this->makeCosplayer($name))->post($this->submitUrl($form), ['values' => [$act->id => "Act {$name}", $tags->id => ['Sword']]]);
        }
        FormSubmission::where('entry_number', 1)->update(['status' => 'approved']);

        return [$form, $act];
    }

    public function test_the_api_is_switched_off_without_a_configured_token(): void
    {
        config(['services.voting.token' => '']);
        [$form] = $this->seedEntries();

        $this->getJson("/api/v1/forms/{$form->id}/entries")->assertStatus(503);
    }

    public function test_a_valid_token_is_required(): void
    {
        config(['services.voting.token' => 'secret-token']);
        [$form] = $this->seedEntries();

        $this->getJson("/api/v1/forms/{$form->id}/entries")->assertUnauthorized();
        $this->withToken('wrong')->getJson("/api/v1/forms/{$form->id}/entries")->assertUnauthorized();
    }

    public function test_only_approved_entries_are_returned_by_default(): void
    {
        config(['services.voting.token' => 'secret-token']);
        [$form] = $this->seedEntries();

        $response = $this->withToken('secret-token')->getJson("/api/v1/forms/{$form->id}/entries")->assertOk();

        $response->assertJsonCount(1, 'entries')
            ->assertJsonPath('entries.0.entry_number', 1)
            ->assertJsonPath('entries.0.status', 'approved')
            ->assertJsonPath('entries.0.cosplayer.character_name', 'Nezuko')
            ->assertJsonPath('entries.0.answers.0.key', 'act_name_1')
            ->assertJsonPath('entries.0.answers.1.value', ['Sword'])
            ->assertJsonStructure(['entries' => [['media' => ['images', 'videos', 'files']]]]);

        $this->withToken('secret-token')->getJson("/api/v1/forms/{$form->id}/entries?status=all")->assertJsonCount(2, 'entries');
    }

    public function test_a_single_entry_can_be_fetched_by_its_number(): void
    {
        config(['services.voting.token' => 'secret-token']);
        [$form] = $this->seedEntries();

        $this->withToken('secret-token')->getJson("/api/v1/forms/{$form->id}/entries/2")
            ->assertOk()->assertJsonPath('entry.entry_number', 2);
        $this->withToken('secret-token')->getJson("/api/v1/forms/{$form->id}/entries/99")->assertNotFound();
    }

    public function test_exposes_no_private_account_data(): void
    {
        config(['services.voting.token' => 'secret-token']);
        [$form] = $this->seedEntries();

        $body = $this->withToken('secret-token')->getJson("/api/v1/forms/{$form->id}/entries?status=all")->getContent();

        $this->assertStringNotContainsString('@test.local', $body);   // no email addresses
        $this->assertStringNotContainsString('password', $body);
    }
}
