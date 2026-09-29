<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UnlockUserLogin extends Command
{
    protected $signature = 'users:unlock {email : Email of the locked account}';

    protected $description = 'Unlock an account that was locked after failed login attempts';

    public function handle(): int
    {
        $user = User::where('email', strtolower(trim($this->argument('email'))))->first();

        if (! $user) {
            $this->error('No user found with that email.');

            return self::FAILURE;
        }

        $user->clearLoginLock();
        $this->info("{$user->email} is unlocked.");

        return self::SUCCESS;
    }
}
