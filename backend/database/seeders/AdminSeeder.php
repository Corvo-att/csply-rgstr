<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the first admin account.
 *
 * No password is hard-coded any more: set SEED_ADMIN_EMAIL / SEED_ADMIN_PASSWORD in .env,
 * or let this generate a random password and print it once. For real staff accounts use:
 *     php artisan admin:create someone@egycon.com --role=manager
 */
class AdminSeeder extends Seeder
{
    public function run(): Admin
    {
        $email = env('SEED_ADMIN_EMAIL', 'admin@egycon.com');
        $password = env('SEED_ADMIN_PASSWORD');

        $admin = Admin::where('email', $email)->first();
        if ($admin) {
            return $admin;
        }

        $generated = $password === null || $password === '';
        $password = $generated ? Str::password(16, symbols: false) : $password;

        $admin = Admin::create([
            'name' => 'EGYCON Admin',
            'email' => $email,
            'password' => $password,
            'role' => 'admin',
        ]);

        if ($generated) {
            $this->command?->warn("Created admin {$email} with generated password: {$password}  (shown once - save it now)");
        }

        return $admin;
    }
}
