<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Form;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = (new AdminSeeder)->setCommand($this->command)->run();

        $event = Event::firstOrCreate(
            ['slug' => 'egycon-2026'],
            [
                'admin_id' => $admin->id,
                'name' => 'EGYCON 2026',
                'description' => 'The main annual convention event celebrating gaming and cosplay.',
                'starts_at' => '2026-08-15 09:00:00',
                'ends_at' => '2026-08-17 21:00:00',
                'location' => 'Cairo International Convention Centre',
                'status' => 'published',
            ]
        );

        $form = Form::firstOrCreate(
            ['event_id' => $event->id, 'name' => 'Cosplay Contest Entry'],
            [
                'description' => 'Official registration form for the EGYCON 2026 Cosplay Championship.',
                'is_active' => true,
            ]
        );

        if ($form->fields()->count() > 0) {
            return;
        }

        $fields = [
            ['label' => 'Before you start', 'field_key' => 'rules', 'field_type' => 'instructions',
                'options' => ['text' => "Stage time is strictly 3 minutes.\nYour video must be MP4, landscape, and no longer than 3 minutes - longer videos are trimmed automatically."]],
            ['label' => 'Performance Title / Act Name', 'field_key' => 'act_name', 'field_type' => 'text', 'is_required' => true],
            ['label' => 'Category', 'field_key' => 'category', 'field_type' => 'dropdown', 'is_required' => true,
                'options' => ['choices' => ['Solo', 'Duo', 'Group (3+)']]],
            ['label' => 'Reference Image', 'field_key' => 'ref_image', 'field_type' => 'image',
                'options' => ['max_size_mb' => 10]],
            ['label' => 'Stage Performance Video', 'field_key' => 'stage_video', 'field_type' => 'video', 'is_required' => true,
                'help_text' => 'Maximum 3 minutes. Longer videos are trimmed automatically.',
                'options' => ['max_size_mb' => 300, 'max_duration_seconds' => 180, 'over_length_action' => 'trim', 'max_height' => 1080]],
            ['label' => 'I accept the contest rules', 'field_key' => 'rules_accepted', 'field_type' => 'terms', 'is_required' => true,
                'options' => ['terms_text' => 'Entries must be original work. The organisers may use submitted media to promote the event.']],
        ];

        foreach ($fields as $i => $field) {
            $form->fields()->create($field + ['sort_order' => $i + 1]);
        }
    }
}
