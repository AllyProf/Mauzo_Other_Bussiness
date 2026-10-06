<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Protects counter payments: one mobile-money / bank reference can only be used once,
 * and only one cashier at a time can work on an order's payment.
 */
class CashierPaymentGuard
{
    public const LOCK_SECONDS = 90;

    private const MIN_REFERENCE_LENGTH = 4;

    public static function normalizeReference(?string $reference): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $reference));
    }

    public static function findDuplicateReference(int $businessId, ?string $reference): ?SalePayment
    {
        $normalized = self::normalizeReference($reference);

        if (strlen($normalized) < self::MIN_REFERENCE_LENGTH) {
            return null;
        }

        return SalePayment::query()
            ->whereNotNull('transaction_reference')
            ->where('payment_method', '!=', 'cash')
            ->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(transaction_reference, ' ', ''), '-', ''), '.', '')) = ?", [$normalized])
            ->whereHas('sale', fn ($q) => $q->where('business_id', $businessId)->where('payment_status', '!=', 'cancelled'))
            ->with(['sale:id,reference_no', 'user:id,name'])
            ->latest('id')
            ->first();
    }

    public static function duplicateReferenceMessage(SalePayment $existing, string $reference): string
    {
        return 'Reference '.$reference.' was already used on order '
            .($existing->sale?->reference_no ?? '#'.$existing->sale_id)
            .' ('.money($existing->amount).', by '.($existing->user?->name ?? 'staff')
            .' on '.$existing->created_at?->format('M d, h:i A').'). Check the SMS and enter the correct reference.';
    }

    /**
     * @param  iterable<int, array{reference: ?string, method: ?string}>  $lines
     */
    public static function assertReferencesUnique(int $businessId, iterable $lines): void
    {
        $seen = [];

        foreach ($lines as $line) {
            if (($line['method'] ?? null) === 'cash') {
                continue;
            }

            $reference = trim((string) ($line['reference'] ?? ''));
            $normalized = self::normalizeReference($reference);

            if (strlen($normalized) < self::MIN_REFERENCE_LENGTH) {
                continue;
            }

            if (isset($seen[$normalized])) {
                throw ValidationException::withMessages([
                    'transaction_reference' => 'Reference '.$reference.' is entered twice in this payment.',
                ]);
            }
            $seen[$normalized] = true;

            if ($existing = self::findDuplicateReference($businessId, $reference)) {
                throw ValidationException::withMessages([
                    'transaction_reference' => self::duplicateReferenceMessage($existing, $reference),
                ]);
            }
        }
    }

    private static function lockKey(int $saleId): string
    {
        return 'sale_payment_lock:'.$saleId;
    }

    private static function userKey(int $userId): string
    {
        return 'sale_payment_lock_user:'.$userId;
    }

    public static function saleBeingCollectedBy(int $userId): ?int
    {
        $saleId = (int) Cache::get(self::userKey($userId), 0);

        if (! $saleId) {
            return null;
        }

        $holder = self::lockHolder($saleId);

        return $holder && (int) $holder['user_id'] === $userId ? $saleId : null;
    }

    /**
     * @return array{user_id: int, name: string, expires_at: string}|null
     */
    public static function lockHolder(int $saleId): ?array
    {
        $lock = Cache::get(self::lockKey($saleId));

        if (! is_array($lock) || now()->greaterThan($lock['expires_at'] ?? now()->subSecond())) {
            return null;
        }

        return $lock;
    }

    /**
     * @param  array<int>  $saleIds
     * @return array<int, array{user_id: int, name: string, expires_at: string}>
     */
    public static function lockHolders(array $saleIds): array
    {
        $holders = [];

        foreach (array_unique($saleIds) as $saleId) {
            if ($holder = self::lockHolder((int) $saleId)) {
                $holders[(int) $saleId] = $holder;
            }
        }

        return $holders;
    }

    /**
     * Take or renew the lock. Returns the other holder when someone else already has it.
     *
     * @return array{user_id: int, name: string, expires_at: string}|null
     */
    public static function acquire(Sale $sale, User $user): ?array
    {
        $holder = self::lockHolder($sale->id);

        if ($holder && (int) $holder['user_id'] !== (int) $user->id) {
            return $holder;
        }

        $payload = [
            'user_id' => (int) $user->id,
            'name' => (string) $user->name,
            'expires_at' => now()->addSeconds(self::LOCK_SECONDS)->toIso8601String(),
        ];

        if ($holder) {
            Cache::put(self::lockKey($sale->id), $payload, self::LOCK_SECONDS);
        } elseif (! Cache::add(self::lockKey($sale->id), $payload, self::LOCK_SECONDS)) {
            $winner = self::lockHolder($sale->id);
            if ($winner && (int) $winner['user_id'] !== (int) $user->id) {
                return $winner;
            }
        }

        Cache::put(self::userKey($user->id), $sale->id, self::LOCK_SECONDS);

        return null;
    }

    public static function release(Sale $sale, User $user): void
    {
        $holder = self::lockHolder($sale->id);

        if (! $holder || (int) $holder['user_id'] === (int) $user->id) {
            Cache::forget(self::lockKey($sale->id));
        }

        if ((int) Cache::get(self::userKey($user->id), 0) === (int) $sale->id) {
            Cache::forget(self::userKey($user->id));
        }
    }

    /**
     * Message when another user is busy collecting on this order, otherwise null.
     */
    public static function lockedByOtherMessage(Sale $sale, User $user): ?string
    {
        $holder = self::lockHolder($sale->id);

        if (! $holder || (int) $holder['user_id'] === (int) $user->id) {
            return null;
        }

        return $holder['name'].' is already collecting payment for order '.$sale->reference_no.'. Wait a moment and refresh.';
    }
}
