<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BlockedIp;
use App\Models\FailedLoginAttempt;
use App\Models\User;
use Carbon\Carbon;

class LoginSecurityService
{
    public const IP_MAX_FAILURES = 10;

    public const IP_WINDOW_MINUTES = 60;

    public const IP_BLOCK_HOURS = 24;

    public function ipBlockedUntil(?string $ip): ?Carbon
    {
        if (! $ip) {
            return null;
        }

        $block = BlockedIp::where('ip_address', $ip)->first();

        return $block?->isBlocked() ? $block->blocked_until : null;
    }

    /**
     * Per-account lock after failed logins is disabled; always allows retry with correct password.
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
            'ip_blocked_until' => $this->registerIpFailure($ip),
        ];
    }

    public function unblockIp(BlockedIp $block): void
    {
        $block->update(['blocked_until' => null, 'cleared_at' => now(), 'failed_attempts' => 0]);
    }

    private function registerIpFailure(?string $ip): ?Carbon
    {
        if (! $ip) {
            return null;
        }

        $block = BlockedIp::firstOrNew(['ip_address' => $ip]);
        if ($block->isBlocked()) {
            return $block->blocked_until;
        }

        $since = now()->subMinutes(self::IP_WINDOW_MINUTES);
        $operator = '>=';
        if ($block->cleared_at && $block->cleared_at->gte($since)) {
            $since = $block->cleared_at;
            $operator = '>';
        }

        $failures = FailedLoginAttempt::where('ip_address', $ip)->where('attempted_at', $operator, $since)->count();

        if ($failures < self::IP_MAX_FAILURES) {
            return null;
        }

        $block->fill(['failed_attempts' => $failures, 'blocked_until' => now()->addHours(self::IP_BLOCK_HOURS)])->save();
        AuditLog::log('IP_BLOCKED', "IP {$ip} blocked after {$failures} failed logins in ".self::IP_WINDOW_MINUTES.' minutes');

        return $block->blocked_until;
    }
}
