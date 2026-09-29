<?php

namespace App\Console\Commands;

use App\Models\BlockedIp;
use App\Services\LoginSecurityService;
use Illuminate\Console\Command;

class UnblockLoginIp extends Command
{
    protected $signature = 'login:unblock-ip {ip : IP address that was blocked after failed logins}';

    protected $description = 'Unblock an IP address that was blocked after too many failed login attempts';

    public function handle(LoginSecurityService $security): int
    {
        $block = BlockedIp::where('ip_address', trim($this->argument('ip')))->first();

        if (! $block) {
            $this->error('That IP address is not blocked.');

            return self::FAILURE;
        }

        $security->unblockIp($block);
        $this->info("{$block->ip_address} is unblocked.");

        return self::SUCCESS;
    }
}
