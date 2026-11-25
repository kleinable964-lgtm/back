<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Rules\PasswordByteLength;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'crm:create-admin {email : Coworker email} {--name=Administrator : Display name}';

    protected $description = 'Create an active CRM administrator through trusted server access';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Run this command interactively so the password can be entered privately.');

            return self::FAILURE;
        }

        $data = [
            'name' => $this->option('name'),
            'email' => strtolower(trim($this->argument('email'))),
            'password' => $this->secret('Password'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')],
            'password' => ['required', 'string', 'max:72', new PasswordByteLength, 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = new User;
        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'admin',
            'status' => 'active',
        ])->save();

        $this->info('Administrator created. You can now sign in.');

        return self::SUCCESS;
    }
}
