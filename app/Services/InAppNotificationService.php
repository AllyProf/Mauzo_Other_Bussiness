<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Business;
use App\Models\DayClosing;
use App\Models\Device;
use App\Models\Item;
use App\Models\NotificationPreference;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class InAppNotificationService
{
    public const CATEGORY_SALES = 'sales';

    public const CATEGORY_STOCK = 'stock';

    public const CATEGORY_DAY_CLOSING = 'day_closing';

    public const CATEGORY_TARGETS = 'targets';

    public const CATEGORY_CUSTOMERS = 'customers';

    public const CATEGORY_SYSTEM = 'system';

    /**
     * Map notification type prefix → preference category.
     *
     * @var array<string, string>
     */
    private const TYPE_CATEGORY = [
        'auth' => self::CATEGORY_SYSTEM,
        'sales' => self::CATEGORY_SALES,
        'stock' => self::CATEGORY_STOCK,
        'day_closing' => self::CATEGORY_DAY_CLOSING,
        'targets' => self::CATEGORY_TARGETS,
        'customers' => self::CATEGORY_CUSTOMERS,
        'reports' => self::CATEGORY_SYSTEM,
        'staff' => self::CATEGORY_SYSTEM,
        'branch' => self::CATEGORY_SYSTEM,
        'system' => self::CATEGORY_SYSTEM,
    ];

    /**
     * Create an in-app notification for one user (respects preferences).
     *
     * @return AppNotification|null Null when skipped by preferences / invalid user
     */
    public function createNotification(
        int $userId,
        int $businessId,
        string $type,
        string $title,
        string $body,
        ?string $payload = null,
        ?int $branchId = null
    ): ?AppNotification {
        if (! $this->userAllowsType($userId, $type)) {
            return null;
        }

        try {
            return AppNotification::create([
                'user_id' => $userId,
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'payload' => $payload,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to create in-app notification', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Notify multiple users (deduped).
     *
     * @param  iterable<int|User>  $users
     * @return list<AppNotification>
     */
    public function notifyUsers(
        iterable $users,
        int $businessId,
        string $type,
        string $title,
        string $body,
        ?string $payload = null,
        ?int $branchId = null
    ): array {
        $created = [];
        $seen = [];

        foreach ($users as $user) {
            $userId = $user instanceof User ? (int) $user->id : (int) $user;
            if ($userId <= 0 || isset($seen[$userId])) {
                continue;
            }
            $seen[$userId] = true;

            $row = $this->createNotification(
                $userId,
                $businessId,
                $type,
                $title,
                $body,
                $payload,
                $branchId
            );

            if ($row) {
                $created[] = $row;
            }
        }

        return $created;
    }

    // ── Device registry ──────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function registerDevice(User $user, Business $business, array $payload): array
    {
        $validated = validator($payload, [
            'token' => 'required|string|max:191',
            'platform' => 'nullable|string|max:32',
            'device_name' => 'nullable|string|max:255',
            'branch_id' => 'nullable|integer',
            'user_id' => 'nullable',
            'business_id' => 'nullable',
        ])->validate();

        $token = trim($validated['token']);
        $branchId = isset($validated['branch_id']) && (int) $validated['branch_id'] > 0
            ? (int) $validated['branch_id']
            : ($user->branch_id ? (int) $user->branch_id : null);

        $device = Device::query()
            ->where('user_id', $user->id)
            ->where('token', $token)
            ->first();

        if (! $device) {
            $device = Device::query()
                ->where('business_id', $business->id)
                ->where('token', $token)
                ->first();
        }

        $created = false;

        if ($device) {
            $device->fill([
                'user_id' => $user->id,
                'business_id' => $business->id,
                'branch_id' => $branchId,
                'platform' => $validated['platform'] ?? $device->platform,
                'device_name' => $validated['device_name'] ?? $device->device_name,
                'last_seen_at' => now(),
            ])->save();
        } else {
            $device = Device::create([
                'user_id' => $user->id,
                'business_id' => $business->id,
                'branch_id' => $branchId,
                'token' => $token,
                'platform' => $validated['platform'] ?? null,
                'device_name' => $validated['device_name'] ?? null,
                'last_seen_at' => now(),
            ]);
            $created = true;
        }

        return [
            'id' => $device->id,
            'token' => $device->token,
            'platform' => $device->platform,
            'device_name' => $device->device_name,
            'branch_id' => $device->branch_id,
            'updated_at' => $device->updated_at?->toIso8601String(),
            'created' => $created,
        ];
    }

    public function unregisterDevice(User $user, string $token): bool
    {
        $deleted = Device::query()
            ->where('user_id', $user->id)
            ->where('token', $token)
            ->delete();

        return $deleted > 0;
    }

    // ── Inbox ────────────────────────────────────────────────────────

    /**
     * @return array{notifications: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function listForUser(User $user, int $businessId, array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(100, max(1, (int) ($filters['limit'] ?? $filters['per_page'] ?? 30)));
        $unreadOnly = filter_var($filters['unread_only'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = AppNotification::query()
            ->where('user_id', $user->id)
            ->where('business_id', $businessId)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $paginator = $query->paginate($limit, ['*'], 'page', $page);

        return [
            'notifications' => collect($paginator->items())
                ->map(fn (AppNotification $n) => $this->formatNotification($n))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'unread_count' => AppNotification::query()
                    ->where('user_id', $user->id)
                    ->where('business_id', $businessId)
                    ->whereNull('read_at')
                    ->count(),
            ],
        ];
    }

    public function markRead(User $user, int $businessId, int $notificationId): ?array
    {
        $notification = AppNotification::query()
            ->where('id', $notificationId)
            ->where('user_id', $user->id)
            ->where('business_id', $businessId)
            ->first();

        if (! $notification) {
            return null;
        }

        if (! $notification->read_at) {
            $notification->read_at = now();
            $notification->save();
        }

        return $this->formatNotification($notification->fresh());
    }

    /**
     * @return array{marked: int}
     */
    public function markAllRead(User $user, int $businessId): array
    {
        $marked = AppNotification::query()
            ->where('user_id', $user->id)
            ->where('business_id', $businessId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ['marked' => $marked];
    }

    /**
     * @return array{sales: bool, stock: bool, day_closing: bool, targets: bool, customers: bool, system: bool}
     */
    public function getPreferences(User $user): array
    {
        $prefs = NotificationPreference::query()->where('user_id', $user->id)->first();

        return $prefs ? $prefs->toFlags() : NotificationPreference::defaults();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{sales: bool, stock: bool, day_closing: bool, targets: bool, customers: bool, system: bool}
     */
    public function updatePreferences(User $user, array $payload): array
    {
        $validated = validator($payload, [
            'sales' => 'sometimes|boolean',
            'stock' => 'sometimes|boolean',
            'day_closing' => 'sometimes|boolean',
            'targets' => 'sometimes|boolean',
            'customers' => 'sometimes|boolean',
            'system' => 'sometimes|boolean',
        ])->validate();

        $defaults = NotificationPreference::defaults();
        $prefs = NotificationPreference::firstOrNew(['user_id' => $user->id]);

        foreach ($defaults as $key => $default) {
            if (array_key_exists($key, $validated)) {
                $prefs->{$key} = filter_var($validated[$key], FILTER_VALIDATE_BOOLEAN);
            } elseif (! $prefs->exists) {
                $prefs->{$key} = $default;
            }
        }

        $prefs->user_id = $user->id;
        $prefs->save();

        return $prefs->toFlags();
    }

    // ── Domain event helpers (MVP) ───────────────────────────────────

    public function notifyHandoverSubmitted(DayClosing $closing): void
    {
        $closing->loadMissing(['business', 'user', 'shift']);
        $business = $closing->business;
        if (! $business) {
            return;
        }

        $submitter = $closing->user;
        $dateLabel = $closing->closing_date?->format('d M Y') ?? '';
        $title = 'Handover submitted';
        $body = ($submitter?->name ?? 'Staff').' submitted day closing for '.$dateLabel
            .' (net '.number_format((float) $closing->net_amount, 0).' TZS).';

        $recipients = $this->managersAndOwner($business, $submitter?->id);

        $this->notifyUsers(
            $recipients,
            (int) $business->id,
            'day_closing.handover_submitted',
            $title,
            $body,
            'day_closing:submitted',
            $submitter?->branch_id ? (int) $submitter->branch_id : null
        );
    }

    public function notifyHandoverVerified(DayClosing $closing, bool $rejected = false): void
    {
        $closing->loadMissing(['business', 'user']);
        $cashier = $closing->user;
        $business = $closing->business;
        if (! $cashier || ! $business) {
            return;
        }

        $dateLabel = $closing->closing_date?->format('d M Y') ?? '';

        if ($rejected) {
            $type = 'day_closing.handover_rejected';
            $title = 'Handover disputed';
            $body = 'Your handover for '.$dateLabel.' was disputed'
                .($closing->dispute_reason ? ': '.$closing->dispute_reason : '.');
            $payload = 'day_closing:rejected';
        } else {
            $type = 'day_closing.handover_verified';
            $title = 'Handover verified';
            $body = 'Your handover for '.$dateLabel.' was verified.';
            $payload = 'day_closing:verified';

            if ((float) ($closing->money_short ?? 0) > 0) {
                $this->createNotification(
                    (int) $cashier->id,
                    (int) $business->id,
                    'day_closing.cash_variance',
                    'Cash variance',
                    'Handover short by '.number_format((float) $closing->money_short, 0).' TZS for '.$dateLabel.'.',
                    'day_closing:variance',
                    $cashier->branch_id ? (int) $cashier->branch_id : null
                );
            }
        }

        $this->createNotification(
            (int) $cashier->id,
            (int) $business->id,
            $type,
            $title,
            $body,
            $payload,
            $cashier->branch_id ? (int) $cashier->branch_id : null
        );
    }

    public function notifyPaymentReceived(Sale $sale, float $amountPaid): void
    {
        if ($amountPaid <= 0) {
            return;
        }

        $sale->loadMissing(['business', 'user']);
        $business = $sale->business;
        if (! $business) {
            return;
        }

        $title = 'Payment received';
        $body = 'Payment of '.number_format($amountPaid, 0).' TZS on sale #'.$sale->id
            .($sale->customer_name ? ' ('.$sale->customer_name.')' : '').'.';

        $recipients = $this->managersAndOwner($business, null);

        // Also notify the cashier who took the payment (if different)
        if ($sale->user_id) {
            $recipients->push($sale->user);
        }

        $this->notifyUsers(
            $recipients,
            (int) $business->id,
            'sales.payment_received',
            $title,
            $body,
            'sales:payment',
            $sale->user?->branch_id ? (int) $sale->user->branch_id : null
        );
    }

    /**
     * After stock deduction — notify owners/managers for low / out of stock items.
     */
    public function notifyStockAfterSale(Sale $sale): void
    {
        $sale->loadMissing(['business', 'items.item.packagings.packagingType', 'items.item.receivingPackaging', 'user']);
        $business = $sale->business;
        if (! $business) {
            return;
        }

        $threshold = (int) ($business->automationSettings()['low_stock_threshold'] ?? 5);
        $recipients = $this->managersAndOwner($business);
        $branchId = $sale->user?->branch_id ? (int) $sale->user->branch_id : null;
        $seenItems = [];

        foreach ($sale->items as $saleItem) {
            $item = $saleItem->item;
            if (! $item || isset($seenItems[$item->id])) {
                continue;
            }
            $seenItems[$item->id] = true;

            $qty = (float) $item->current_stock;
            $stockLabel = $this->stockLabel($item, $qty);

            if ($qty <= 0) {
                $this->notifyUsers(
                    $recipients,
                    (int) $business->id,
                    'stock.out',
                    'Out of stock',
                    $item->name.' is out of stock.',
                    'stock:out',
                    $branchId
                );
            } elseif ($qty <= $threshold) {
                $this->notifyUsers(
                    $recipients,
                    (int) $business->id,
                    'stock.low',
                    'Low stock',
                    $item->name.' is low ('.$stockLabel.').',
                    'stock:low',
                    $branchId
                );
            }
        }
    }

    // ── Internals ────────────────────────────────────────────────────

    public function categoryForType(string $type): string
    {
        $prefix = explode('.', $type, 2)[0] ?? $type;

        return self::TYPE_CATEGORY[$prefix] ?? self::CATEGORY_SYSTEM;
    }

    public function userAllowsType(int $userId, string $type): bool
    {
        $category = $this->categoryForType($type);
        $prefs = NotificationPreference::query()->where('user_id', $userId)->first();
        $flags = $prefs ? $prefs->toFlags() : NotificationPreference::defaults();

        return (bool) ($flags[$category] ?? true);
    }

    /**
     * @return Collection<int, User>
     */
    public function managersAndOwner(Business $business, ?int $excludeUserId = null): Collection
    {
        $users = collect();

        $owner = $business->resolveOwner();
        if ($owner) {
            $users->push($owner);
        }

        foreach ($business->resolveManagers() as $manager) {
            $users->push($manager);
        }

        return $users
            ->filter(fn (User $u) => $u->is_active !== false)
            ->filter(fn (User $u) => ! $excludeUserId || (int) $u->id !== (int) $excludeUserId)
            ->unique('id')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatNotification(AppNotification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'body' => $n->body,
            'payload' => $n->payload,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->utc()->format('Y-m-d\TH:i:s\Z')
                ?? $n->created_at?->toIso8601String(),
            'branch_id' => $n->branch_id,
            'business_id' => $n->business_id,
        ];
    }

    private function stockLabel(Item $item, float $pieces): string
    {
        try {
            $formatted = app(ItemStockDisplayService::class)->format($item, $pieces);

            return $formatted['stock_display'] ?? ($pieces.' pcs');
        } catch (\Throwable) {
            return $pieces.' pcs';
        }
    }
}
