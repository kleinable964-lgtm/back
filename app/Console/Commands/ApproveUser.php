<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ApproveUser extends Command
{
    protected $signature = 'crm:approve-user {email : Registered coworker email} {--admin : Grant the admin role}';

    protected $description = 'Activate a registered coworker through trusted server access';

    public function handle(): int
    {
        $user = User::where('email', strtolower(trim($this->argument('email'))))->first();
        if (! $user) {
            $this->error('Coworker not found. Create it through an administrator or use crm:create-admin to bootstrap the first admin.');

            return self::FAILURE;
        }
        $user->status = 'active';
        $user->role = $this->option('admin') ? 'admin' : ($user->role ?? 'agent');
        $user->save();
        $this->info('Coworker activated.');

        return self::SUCCESS;
    }
}
