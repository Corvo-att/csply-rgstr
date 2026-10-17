<?php

namespace Tests\Concerns;

use App\Models\Admin;
use App\Models\Cosplayer;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Small helpers so each test reads like a story instead of a pile of Model::create() calls. */
trait BuildsFixtures
{
    protected Admin $owner;

    protected function setUpFixtures(): void
    {
        // Never touch the real storage folder from a test.
        Storage::fake('public', ['url' => 'http://localhost/storage']);

        $this->owner = $this->makeAdmin('admin');
    }

    protected function makeAdmin(string $role = 'admin'): Admin
    {
        static $n = 0;

        return Admin::create(['name' => "Staff {$role}", 'email' => "{$role}".(++$n).'@test.local', 'password' => 'password-123456', 'role' => $role]);
    }

    protected function makeEvent(string $status = 'published'): Event
    {
        static $n = 0;
        $n++;

        return Event::create([
            'admin_id' => $this->owner->id, 'name' => "Event {$n}", 'slug' => "event-{$n}", 'status' => $status,
        ]);
    }

    protected function makeForm(?Event $event = null, array $attributes = []): Form
    {
        return ($event ?? $this->makeEvent())->forms()->create($attributes + ['name' => 'Contest Entry', 'is_active' => true]);
    }

    protected function addField(Form $form, string $type, string $label, bool $required = false, array $options = []): FormField
    {
        $sort = $form->fields()->count() + 1;

        return $form->fields()->create([
            'label' => $label,
            'field_key' => str($label)->snake()->toString().'_'.$sort,
            'field_type' => $type,
            'options' => $options,
            'is_required' => $required,
            'sort_order' => $sort,
        ]);
    }

    /** A logged-in-able cosplayer with a completed character profile. */
    protected function makeCosplayer(string $name = 'Nezuko Fan'): User
    {
        static $n = 0;

        $user = User::create(['name' => $name, 'email' => 'cosplayer'.(++$n).'@test.local', 'password' => 'password-123456']);
        Cosplayer::create(['user_id' => $user->id, 'character_name' => 'Nezuko', 'series' => 'Demon Slayer', 'experience_level' => 'beginner']);

        return $user;
    }

    /**
     * An upload whose MIME type is sniffed from its CONTENT, like a real browser upload.
     * (UploadedFile::fake() guesses the type from the file NAME, which would hide exactly
     * the "script renamed to .png" attack these tests are about.)
     */
    protected function realUpload(string $clientName, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $content);

        return new UploadedFile($path, $clientName, null, null, true);
    }

    protected function submitUrl(Form $form): string
    {
        return "/events/{$form->event_id}/forms/{$form->id}/submit";
    }
}
