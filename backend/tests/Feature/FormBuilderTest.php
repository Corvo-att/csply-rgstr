<?php

namespace Tests\Feature;

use App\Models\FormField;
use App\Models\FormSubmissionValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class FormBuilderTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixtures();
    }

    /** Build the payload the React builder sends from the fields currently in the database. */
    private function payload($form, ?callable $tweak = null): array
    {
        $fields = $form->fields()->get()->map(fn (FormField $f) => [
            'id' => $f->id, 'label' => $f->label, 'field_key' => $f->field_key, 'field_type' => $f->field_type,
            'options' => $f->options ?? [], 'is_required' => $f->is_required, 'help_text' => $f->help_text,
        ])->all();

        return ['fields' => $tweak ? $tweak($fields) : $fields];
    }

    public function test_saving_the_builder_keeps_existing_answers(): void
    {
        $form = $this->makeForm();
        $name = $this->addField($form, 'text', 'Act name');
        $user = $this->makeCosplayer();
        $this->actingAs($user)->post($this->submitUrl($form), ['values' => [$name->id => 'My act']]);
        $this->assertSame(1, FormSubmissionValue::count());

        // Manager renames the label, adds a field and saves - the old version deleted every answer here.
        $this->actingAs($this->owner, 'admin')->put("/admin/forms/{$form->id}/fields", $this->payload($form, function ($fields) {
            $fields[0]['label'] = 'Act title';
            $fields[] = ['label' => 'Notes', 'field_type' => 'textarea', 'options' => [], 'is_required' => false];

            return $fields;
        }))->assertSessionHasNoErrors();

        $this->assertSame(1, FormSubmissionValue::count());
        $this->assertSame('My act', FormSubmissionValue::first()->value);
        $this->assertSame($name->id, $name->fresh()->id);
        $this->assertSame('Act title', $name->fresh()->label);
        $this->assertSame(2, $form->fields()->count());
    }

    public function test_removing_a_field_deletes_only_that_field(): void
    {
        $form = $this->makeForm();
        $keep = $this->addField($form, 'text', 'Keep');
        $drop = $this->addField($form, 'text', 'Drop');

        $this->actingAs($this->owner, 'admin')->put("/admin/forms/{$form->id}/fields", $this->payload($form, fn ($f) => [$f[0]]))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($keep->fresh());
        $this->assertNull($drop->fresh());
    }

    public function test_changing_the_type_of_a_field_with_answers_is_blocked(): void
    {
        $form = $this->makeForm();
        $name = $this->addField($form, 'text', 'Act name');
        $this->actingAs($this->makeCosplayer())->post($this->submitUrl($form), ['values' => [$name->id => 'x']]);

        $this->actingAs($this->owner, 'admin')->put("/admin/forms/{$form->id}/fields", $this->payload($form, function ($f) {
            $f[0]['field_type'] = 'number';

            return $f;
        }))->assertSessionHasErrors('fields');

        $this->assertSame('text', $name->fresh()->field_type);
    }

    public function test_builder_input_is_validated(): void
    {
        $form = $this->makeForm();
        $this->actingAs($this->owner, 'admin');

        $bad = fn (array $field) => $this->put("/admin/forms/{$form->id}/fields", ['fields' => [$field]]);

        $bad(['label' => 'X', 'field_type' => 'teleporter'])->assertSessionHasErrors('fields.0.field_type');
        $bad(['label' => 'X', 'field_type' => 'dropdown', 'options' => ['choices' => []]])->assertSessionHasErrors('fields.0.options');
        $bad(['label' => '', 'field_type' => 'text'])->assertSessionHasErrors('fields.0.label');
        $bad(['label' => 'X', 'field_type' => 'text', 'field_key' => 'Bad Key!'])->assertSessionHasErrors('fields.0.field_key');
    }

    public function test_duplicate_field_keys_are_made_unique(): void
    {
        $form = $this->makeForm();

        $this->actingAs($this->owner, 'admin')->put("/admin/forms/{$form->id}/fields", ['fields' => [
            ['label' => 'Name', 'field_type' => 'text'],
            ['label' => 'Name', 'field_type' => 'text'],
        ]])->assertSessionHasNoErrors();

        $this->assertSame(['name', 'name_2'], $form->fields()->pluck('field_key')->all());
    }

    public function test_instruction_text_is_stored_as_plain_text_option(): void
    {
        $form = $this->makeForm();

        $this->actingAs($this->owner, 'admin')->put("/admin/forms/{$form->id}/fields", ['fields' => [
            ['label' => 'Rules', 'field_type' => 'instructions', 'options' => ['text' => "Line one\nLine two", 'html' => '<script>x</script>', 'junk' => 1]],
        ]])->assertSessionHasNoErrors();

        $options = $form->fields()->first()->options;
        $this->assertSame("Line one\nLine two", $options['text']);
        $this->assertArrayNotHasKey('html', $options);   // unknown option keys are dropped
        $this->assertArrayNotHasKey('junk', $options);
    }
}
