<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Admin::firstOrCreate(
            ['email' => 'admin@egycon.com'],
            [
                'name'     => 'EGYCON Admin',
                'password' => Hash::make('admin123'),
                'role'     => 'admin',
            ]
        );

        // Seed initial event if not present
        $event = Event::firstOrCreate(
            ['slug' => 'egycon-2026'],
            [
                'admin_id'    => $admin->id,
                'name'        => 'EGYCON 2026',
                'description' => 'The main annual convention event celebrating gaming and cosplay.',
                'starts_at'   => '2026-08-15 09:00:00',
                'ends_at'     => '2026-08-17 21:00:00',
                'location'    => 'Cairo International Convention Centre',
                'status'      => 'published',
            ]
        );

        $form = Form::firstOrCreate(
            ['event_id' => $event->id, 'name' => 'Cosplay Contest Entry'],
            [
                'description' => 'Official registration form for the EGYCON 2026 Cosplay Championship.',
                'is_active'   => true,
            ]
        );

        if ($form->fields()->count() === 0) {
            FormField::create([
                'form_id'    => $form->id,
                'label'      => 'Performance Title / Act Name',
                'field_key'  => 'act_name',
                'field_type' => 'text',
                'is_required'=> true,
                'sort_order' => 1,
            ]);

            FormField::create([
                'form_id'    => $form->id,
                'label'      => 'Category',
                'field_key'  => 'category',
                'field_type' => 'select',
                'options'    => ['Solo', 'Duo', 'Group (3+)'],
                'is_required'=> true,
                'sort_order' => 2,
            ]);

            FormField::create([
                'form_id'    => $form->id,
                'label'      => 'Reference Image',
                'field_key'  => 'ref_image',
                'field_type' => 'image',
                'is_required'=> false,
                'sort_order' => 3,
            ]);

            FormField::create([
                'form_id'    => $form->id,
                'label'      => 'Audition / Demo Video',
                'field_key'  => 'demo_video',
                'field_type' => 'video',
                'is_required'=> false,
                'sort_order' => 4,
            ]);
        }
    }
}
