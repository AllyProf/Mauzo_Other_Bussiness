<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BlockedIp;
use App\Models\FailedLoginAttempt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

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
     * Unknown emails are tracked in the cache so the login screen behaves the same whether or not
     * the account exists.
     */
    public function lockedUntil(string $email, ?User $user): ?Carbon
    {
        if ($user) {
            return $user->isLoginLocked() ? $user->locked_until : null;
        }

        $state = Cache::get($this->unknownKey($email));
        $until = isset($state['locked_until']) ? Carbon::createFromTimestamp($state['locked_until'], config('app.timezone')) : null;

        return $until?->isFuture() ? $until : null;
    }

    /**
     * @return array{locked_until: ?Carbon, remaining: int, ip_blocked_until: ?Carbon}
     */
    public function registerFailure(string $email, ?User $user, ?string $ip, ?string $userAgent): array
    {
        FailedLoginAttempt::record($email, $ip, $userAgent);

        if ($user) {
            if ($user->registerFailedLogin()) {
                AuditLog::log('ACCOUNT_LOCKED', "Account {$user->email} locked after ".User::MAX_FAILED_LOGINS.' failed login attempts', $user->business_id, $user->id);
            }
            $lockedUntil = $user->isLoginLocked() ? $user->locked_until : null;
            $remaining = $user->remainingLoginAttempts();
        } else {
            [$lockedUntil, $remaining] = $this->registerUnknownFailure($email);
        }

        return [
            'locked_until' => $lockedUntil,
            'remaining' => $remaining,
            'ip_blocked_until' => $this->registerIpFailure($ip),
        ];
    }

    public function unblockIp(BlockedIp $block): void
    {
        $block->update(['blocked_until' => null, 'cleared_at' => now(), 'failed_attempts' => 0]);
    }

    private function registerUnknownFailure(string $email): array
    {
        $key = $this->unknownKey($email);
        $state = Cache::get($key, ['count' => 0, 'locked_until' => null]);

        if ($state['locked_until'] && $state['locked_until'] <= now()->timestamp) {
            $state = ['count' => 0, 'locked_until' => null];
        }

        $state['count']++;
        if ($state['count'] >= User::MAX_FAILED_LOGINS) {
            $state['locked_until'] = now()->addHours(User::LOGIN_LOCK_HOURS)->timestamp;
        }

        Cache::put($key, $state, now()->addHours(User::LOGIN_LOCK_HOURS));

        return [
            $state['locked_until'] ? Carbon::createFromTimestamp($state['locked_until'], config('app.timezone')) : null,
            max(0, User::MAX_FAILED_LOGINS - $state['count']),
        ];
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

    private function unknownKey(string $email): string
    {
        return 'login_lock:unknown:'.sha1(strtolower(trim($email)));
    }
}
