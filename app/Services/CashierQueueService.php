<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Payment queue for counter cashiers: unpaid orders placed by sales officers in the cashier's branch.
 */
class CashierQueueService
{
    public const QUEUE_STATUSES = ['pending', 'partial'];

    public static function scopeBranch($query, User $user)
    {
        return self::scopeBranchId($query, $user->branch_id ? (int) $user->branch_id : null);
    }

    public static function scopeBranchId($query, ?int $branchId)
    {
        if (! $branchId) {
            return $query;
        }

        return $query->where(function ($scoped) use ($branchId) {
            $scoped->whereHas('user', fn ($u) => $u->where('branch_id', $branchId))
                ->orWhereHas('items.item.category', fn ($c) => $c->where('branch_id', $branchId))
                ->orWhereHas('items.service', fn ($s) => $s->where('branch_id', $branchId));
        });
    }

    public static function tab(Request $request): string
    {
        return $request->get('tab') === 'paid' ? 'paid' : 'unpaid';
    }

    public function queueQuery(User $user, int $businessId, Request $request, ?string $tab = null): Builder
    {
        $tab = $tab ?? self::tab($request);
        $query = Sale::query()->where('business_id', $businessId);

        if ($tab === 'paid') {
            $paidOn = $request->get('date') ?: now()->toDateString();
            $query->where('payment_status', 'paid')
                ->whereHas('payments', fn ($p) => $p->whereDate('created_at', $paidOn));
        } else {
            $status = $request->get('status');
            $statuses = in_array($status, self::QUEUE_STATUSES, true) ? [$status] : self::QUEUE_STATUSES;
            $query->whereIn('payment_status', $statuses)
                ->whereColumn('total_amount', '>', 'amount_paid');

            if ($request->filled('date')) {
                $query->whereDate('sale_date', $request->get('date'));
            }
        }

        if (! $user->seesBusinessWideData()) {
            self::scopeBranch($query, $user);
        } else {
            self::scopeBranchId($query, self::viewerBranchId($request));
        }

        $search = trim((string) $request->get('q', $request->get('search', '')));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('officer_id')) {
            $query->where('user_id', (int) $request->get('officer_id'));
        }

        return $query;
    }

    /**
     * Branch an owner/manager is looking at: explicit ?branch_id=, otherwise the active branch switcher.
     */
    public static function viewerBranchId(Request $request): ?int
    {
        $branchId = (int) $request->get('branch_id', 0);

        return $branchId > 0 ? $branchId : active_branch_id();
    }

    public function latestOrderId(User $user, int $businessId, Request $request): int
    {
        $base = Request::create('/', 'GET', array_filter([
            'branch_id' => $request->get('branch_id'),
        ]));

        return (int) $this->queueQuery($user, $businessId, $base, 'unpaid')->max('sales.id');
    }

    /**
     * Payment cashiers with an open shift: what they collected and which order they are on right now.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cashiersOnDuty(int $businessId, ?int $branchId = null): array
    {
        $shifts = Shift::query()
            ->where('business_id', $businessId)
            ->where('status', 'open')
            ->with('user')
            ->get()
            ->filter(fn (Shift $shift) => $shift->user
                && $shift->user->isPaymentCashier()
                && (! $branchId || (int) $shift->user->branch_id === $branchId));

        return $shifts->map(function (Shift $shift) use ($businessId) {
            $payments = SalePayment::query()
                ->where('user_id', $shift->user_id)
                ->where('created_at', '>=', $shift->opened_at)
                ->whereHas('sale', fn ($q) => $q->where('business_id', $businessId)->where('payment_status', '!=', 'cancelled'));

            $lastPaymentAt = (clone $payments)->max('created_at');
            $collectingSaleId = CashierPaymentGuard::saleBeingCollectedBy((int) $shift->user_id);
            $collectingRef = $collectingSaleId ? Sale::whereKey($collectingSaleId)->value('reference_no') : null;

            return [
                'user_id' => $shift->user_id,
                'name' => $shift->user->name,
                'branch' => $shift->user->branch?->name,
                'shift_id' => $shift->id,
                'opened_at' => $shift->opened_at?->toIso8601String(),
                'opened_label' => $shift->opened_at?->format('h:i A'),
                'total' => (float) (clone $payments)->sum('amount'),
                'payments_count' => (clone $payments)->count(),
                'last_payment_at' => $lastPaymentAt ? \Carbon\Carbon::parse($lastPaymentAt)->toIso8601String() : null,
                'last_payment_label' => $lastPaymentAt ? \Carbon\Carbon::parse($lastPaymentAt)->diffForHumans() : null,
                'collecting_now' => $collectingSaleId
                    ? ['sale_id' => $collectingSaleId, 'reference_no' => $collectingRef]
                    : null,
            ];
        })->sortByDesc('total')->values()->all();
    }

    /**
     * Ready-to-send receipt for the customer (print link, WhatsApp and SMS text).
     *
     * @return array<string, mixed>
     */
    public function receiptShare(Sale $sale): array
    {
        $sale->loadMissing(['business', 'items.item', 'items.service', 'payments']);
        $balance = max(0, (float) $sale->total_amount - (float) $sale->amount_paid);
        $lastPayment = $sale->payments->sortBy('id')->last();

        $lines = [
            $sale->business?->name ?? 'Receipt',
            'Receipt: '.$sale->reference_no.' · '.now()->format('d M Y H:i'),
        ];
        foreach ($sale->items as $si) {
            $name = $si->service_id ? ($si->line_description ?: $si->service?->name ?? 'Service') : ($si->item->name ?? 'Item');
            $lines[] = '- '.$name.' x'.rtrim(rtrim(number_format((float) $si->quantity, 2), '0'), '.').' = '.money($si->subtotal);
        }
        $lines[] = 'Total: '.money($sale->total_amount);
        $lines[] = 'Paid: '.money($sale->amount_paid)
            .($lastPayment ? ' ('.($lastPayment->payment_provider ?: ucfirst(str_replace('_', ' ', $lastPayment->payment_method))).')' : '');
        if ($balance > 0) {
            $lines[] = 'Balance: '.money($balance).($sale->due_date ? ' due '.\Carbon\Carbon::parse($sale->due_date)->format('d M Y') : '');
        }
        $lines[] = 'Thank you!';

        $text = implode("\n", $lines);
        $phone = \App\Models\Customer::normalizePhone($sale->customer_phone);
        $digits = $phone ? ltrim($phone, '+') : null;

        return [
            'sale_id' => $sale->id,
            'reference_no' => $sale->reference_no,
            'customer_name' => $sale->customer_name,
            'customer_phone' => $phone,
            'payment_status' => $sale->payment_status,
            'total_amount' => (float) $sale->total_amount,
            'amount_paid' => (float) $sale->amount_paid,
            'balance' => $balance,
            'text' => $text,
            'print_url' => route('sales.show', ['sale' => $sale->id, 'print' => 1]),
            'whatsapp_url' => 'https://wa.me/'.($digits ?? '').'?text='.rawurlencode($text),
            'sms_url' => $phone ? 'sms:'.$phone.'?body='.rawurlencode($text) : null,
        ];
    }

    public function stats(Builder $query): array
    {
        return [
            'orders' => (clone $query)->count(),
            'pending' => (clone $query)->where('payment_status', 'pending')->count(),
            'partial' => (clone $query)->where('payment_status', 'partial')->count(),
            'total_due' => (float) (clone $query)->sum(DB::raw('total_amount - amount_paid')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function saleRow(Sale $sale, ?array $lock = null): array
    {
        $balance = max(0, (float) $sale->total_amount - (float) $sale->amount_paid);

        return [
            'id' => $sale->id,
            'reference_no' => $sale->reference_no,
            'sale_source' => $sale->sale_source,
            'is_service' => in_array($sale->sale_source, ['service_pos', 'service_invoice'], true),
            'sale_date' => $sale->sale_date ? \Carbon\Carbon::parse($sale->sale_date)->toDateString() : null,
            'created_at' => $sale->created_at?->toIso8601String(),
            'time' => $sale->created_at?->format('h:i A'),
            'waiting_minutes' => $sale->created_at ? (int) $sale->created_at->diffInMinutes(now()) : null,
            'officer' => ['id' => $sale->user?->id, 'name' => $sale->user?->name],
            'customer' => [
                'id' => $sale->customer_id,
                'name' => $sale->customer_name,
                'phone' => $sale->customer_phone,
            ],
            'payment_status' => $sale->payment_status,
            'total_amount' => (float) $sale->total_amount,
            'amount_paid' => (float) $sale->amount_paid,
            'balance' => $balance,
            'due_date' => $sale->due_date ? \Carbon\Carbon::parse($sale->due_date)->toDateString() : null,
            'locked_by' => $lock ? ['user_id' => $lock['user_id'], 'name' => $lock['name']] : null,
            'payments' => $sale->relationLoaded('payments')
                ? $sale->payments->map(fn (SalePayment $p) => [
                    'amount' => (float) $p->amount,
                    'method' => $p->payment_method,
                    'provider' => $p->payment_provider,
                    'reference' => $p->transaction_reference,
                    'collected_by' => $p->user?->name,
                    'collected_at' => $p->created_at?->toIso8601String(),
                ])->values()->all()
                : [],
            'items_count' => $sale->items->count(),
            'items' => $sale->items->map(fn (SaleItem $si) => [
                'id' => $si->id,
                'name' => $si->service_id
                    ? ($si->line_description ?: $si->service?->name ?? 'Service')
                    : ($si->item->name ?? 'Item'),
                'qty' => (float) $si->quantity,
                'unit_price' => (float) ($si->list_unit_price ?? $si->unit_price),
                'subtotal' => (float) $si->subtotal,
            ])->values()->all(),
        ];
    }

    /**
     * What the cashier has collected during his open shift.
     *
     * @return array<string, mixed>
     */
    public function shiftCollections(User $user, int $businessId, int $recentLimit = 10): array
    {
        $shift = Shift::openForUser($user->id, $businessId);

        if (! $shift) {
            return [
                'shift' => null,
                'total' => 0.0,
                'payments_count' => 0,
                'orders_count' => 0,
                'by_method' => [],
                'recent' => [],
            ];
        }

        $payments = SalePayment::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $shift->opened_at)
            ->whereHas('sale', fn ($q) => $q->where('business_id', $businessId)->where('payment_status', '!=', 'cancelled'))
            ->with(['sale.user:id,name'])
            ->latest()
            ->get();

        $byMethod = $payments
            ->groupBy(fn (SalePayment $p) => $p->payment_method === 'cash'
                ? 'cash'
                : ($p->payment_provider ? $p->payment_method.':'.$p->payment_provider : $p->payment_method))
            ->map(fn ($rows, $key) => [
                'key' => $key,
                'method' => $rows->first()->payment_method,
                'label' => $rows->first()->payment_method === 'cash'
                    ? 'Physical Cash'
                    : ($rows->first()->payment_provider ?: ucfirst(str_replace('_', ' ', $rows->first()->payment_method))),
                'amount' => (float) $rows->sum('amount'),
                'count' => $rows->count(),
            ])
            ->values()
            ->all();

        return [
            'shift' => [
                'id' => $shift->id,
                'opened_at' => $shift->opened_at?->toIso8601String(),
            ],
            'total' => (float) $payments->sum('amount'),
            'payments_count' => $payments->count(),
            'orders_count' => $payments->pluck('sale_id')->unique()->count(),
            'by_method' => $byMethod,
            'recent' => $payments->take($recentLimit)->map(fn (SalePayment $p) => [
                'id' => $p->id,
                'sale_id' => $p->sale_id,
                'reference_no' => $p->sale?->reference_no,
                'officer' => $p->sale?->user?->name,
                'customer' => $p->sale?->customer_name,
                'amount' => (float) $p->amount,
                'method' => $p->payment_method,
                'provider' => $p->payment_provider,
                'reference' => $p->transaction_reference,
                'time' => $p->created_at?->format('h:i A'),
            ])->values()->all(),
        ];
    }
}
