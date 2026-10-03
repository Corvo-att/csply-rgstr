<?php

namespace Tests\Feature;

use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Services\MediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

/**
 * The most important tests in the project: nobody may get an executable or
 * script file onto the server by filling in a registration form.
 */
class UploadSecurityTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixtures();
    }

    public function test_the_old_anonymous_upload_endpoint_is_gone(): void
    {
        $this->actingAs($this->makeCosplayer())
            ->post('/upload', ['file' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;')])
            ->assertStatus(404);
    }

    public function test_a_php_script_is_rejected_by_an_image_field_even_when_renamed(): void
    {
        $form = $this->makeForm();
        $field = $this->addField($form, 'image', 'Reference');
        $user = $this->makeCosplayer();

        $evil = $this->realUpload('photo.png', '<?php system($_GET["c"]); ?>');

        $this->actingAs($user)
            ->post($this->submitUrl($form), ['values' => [$field->id => $evil]])
            ->assertSessionHasErrors("values.{$field->id}");

        $this->assertSame(0, FormSubmissionValue::count());
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_html_and_svg_uploads_are_rejected_in_a_generic_file_field(): void
    {
        $form = $this->makeForm();
        $field = $this->addField($form, 'file', 'Attachment');
        $user = $this->makeCosplayer();

        foreach ([
            $this->realUpload('page.html', '<html><script>alert(1)</script></html>'),
            $this->realUpload('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ] as $file) {
            $this->actingAs($user)
                ->post($this->submitUrl($form), ['values' => [$field->id => $file]])
                ->assertSessionHasErrors("values.{$field->id}");
        }

        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_a_file_named_dot_php_is_rejected_outright(): void
    {
        $form = $this->makeForm();
        $field = $this->addField($form, 'file', 'Notes');

        $this->actingAs($this->makeCosplayer())
            ->post($this->submitUrl($form), ['values' => [$field->id => $this->realUpload('definitely-not-php.php', 'hello cosplay')]])
            ->assertSessionHasErrors("values.{$field->id}");

        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_the_stored_extension_comes_from_the_content_not_the_client_filename(): void
    {
        $form = $this->makeForm();
        $field = $this->addField($form, 'file', 'Notes');

        // Plain text that CLAIMS to be a PDF: allowed (it is just text) but stored as .txt, never .pdf.
        $this->actingAs($this->makeCosplayer())
            ->post($this->submitUrl($form), ['values' => [$field->id => $this->realUpload('lies.pdf', 'hello cosplay')]])
            ->assertSessionHasNoErrors();

        $files = Storage::disk('public')->allFiles();
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.txt', $files[0]);
    }

    public function test_images_are_optimised_and_resized(): void
    {
        $form = $this->makeForm();
        $field = $this->addField($form, 'image', 'Reference');
        $user = $this->makeCosplayer();

        $this->actingAs($user)
            ->post($this->submitUrl($form), ['values' => [$field->id => UploadedFile::fake()->image('big.jpg', 4000, 3000)]])
            ->assertSessionHasNoErrors();

        $value = FormSubmissionValue::firstOrFail();
        $path = app(MediaStorage::class)->pathFromUrl($value->value);

        $this->assertNotNull($path);
        $this->assertStringEndsWith('.webp', $path);

        [$w, $h] = getimagesize(Storage::disk('public')->path($path));
        $this->assertLessThanOrEqual(2400, max($w, $h));
    }

    public function test_the_manager_set_size_limit_is_enforced_on_the_server(): void
    {
        $form = $this->makeForm();
        $field = $this->addField($form, 'image', 'Reference', options: ['max_size_kb' => 50]);
        $user = $this->makeCosplayer();

        $tooBig = UploadedFile::fake()->image('big.jpg', 1500, 1500)->size(200);

        $this->actingAs($user)
            ->post($this->submitUrl($form), ['values' => [$field->id => $tooBig]])
            ->assertSessionHasErrors("values.{$field->id}");
    }

    public function test_deleting_a_submission_removes_its_files(): void
    {
        $form = $this->makeForm();
        $field = $this->addField($form, 'image', 'Reference');
        $user = $this->makeCosplayer();

        $this->actingAs($user)->post($this->submitUrl($form), ['values' => [$field->id => UploadedFile::fake()->image('a.jpg')]]);
        $this->assertCount(1, Storage::disk('public')->allFiles());

        $submission = FormSubmission::firstOrFail();
        $this->actingAs($this->owner, 'admin')->delete("/admin/forms/{$form->id}/submissions/{$submission->id}")->assertRedirect();

        $this->assertEmpty(Storage::disk('public')->allFiles());
    }
}
