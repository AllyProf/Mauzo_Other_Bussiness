<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class RegistrationVerificationService
{
    private const TTL_MINUTES = 10;

    public function cacheKey(string $phone255): string
    {
        return 'registration_verify:'.hash('sha256', $phone255);
    }

    public function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function store(string $phone255, array $payload, string $code): void
    {
        Cache::put($this->cacheKey($phone255), [
            'code' => $code,
            'payload' => $payload,
            'verified' => false,
        ], now()->addMinutes(self::TTL_MINUTES));
    }

    public function verify(string $phone255, string $code): bool
    {
        $entry = Cache::get($this->cacheKey($phone255));

        if (! is_array($entry) || ! isset($entry['code'])) {
            return false;
        }

        return hash_equals((string) $entry['code'], trim($code));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function payload(string $phone255): ?array
    {
        $entry = Cache::get($this->cacheKey($phone255));

        if (! is_array($entry) || ! isset($entry['payload'])) {
            return null;
        }

        return $entry['payload'];
    }

    public function forget(string $phone255): void
    {
        Cache::forget($this->cacheKey($phone255));
    }

    public function displayPhone(string $phone255): string
    {
        if (str_starts_with($phone255, '255')) {
            return '+'.substr($phone255, 0, 3).' '.substr($phone255, 3);
        }

        return '+'.$phone255;
    }
}
