<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create
                            {email : Login email}
                            {--name= : Display name}
                            {--role=manager : admin, manager or judge}
                            {--password= : Leave empty to be prompted (recommended)}';

    protected $description = 'Create an admin-panel account (admin, manager or judge)';

    public function handle(): int
    {
        $password = $this->option('password') ?: $this->secret('Password (min 12 characters)');

        $data = [
            'email' => $this->argument('email'),
            'name' => $this->option('name') ?: $this->argument('email'),
            'role' => $this->option('role'),
            'password' => $password,
        ];

        $validator = Validator::make($data, [
            'email' => ['required', 'email', 'unique:admins,email'],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(Admin::ROLES)],
            'password' => ['required', Password::min(12)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        Admin::create($data);   // password is hashed by the model cast
        $this->info("Created {$data['role']} account for {$data['email']}.");

        return self::SUCCESS;
    }
}
