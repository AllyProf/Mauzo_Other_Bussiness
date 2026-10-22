<?php

namespace App\Services;

use App\Models\BlockedIp;
use App\Models\FailedLoginAttempt;
use App\Models\User;
use Carbon\Carbon;

class LoginSecurityService
{
    /**
     * IP blocking after failed logins is disabled.
     */
    public function ipBlockedUntil(?string $ip): ?Carbon
    {
        return null;
    }

    /**
     * Per-account lock after failed logins is disabled.
     */
    public function lockedUntil(string $email, ?User $user): ?Carbon
    {
        return null;
    }

    /**
     * @return array{locked_until: ?Carbon, remaining: int, ip_blocked_until: ?Carbon}
     */
    public function registerFailure(string $email, ?User $user, ?string $ip, ?string $userAgent): array
    {
        FailedLoginAttempt::record($email, $ip, $userAgent);

        return [
            'locked_until' => null,
            'remaining' => 0,
            'ip_blocked_until' => null,
        ];
    }

    public function unblockIp(BlockedIp $block): void
    {
        $block->update(['blocked_until' => null, 'cleared_at' => now(), 'failed_attempts' => 0]);
    }
}
