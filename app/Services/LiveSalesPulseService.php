<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LiveSalesPulseService
{

    /**
     * @return array{shift: ?Shift, mode: string, date: string, scope_label: string}
     */
    public function resolveContext(User $user, Business $business, bool $businessWide, ?int $branchFilterId = null): array
    {
        $date = now()->toDateString();

        if ($user->requiresOpenShift()) {
            $shift = Shift::openForUser($user->id, $business->id);

            return [
                'shift' => $shift,
                'mode' => $shift ? 'shift' : 'none',
                'date' => $date,
                'scope_label' => $shift
                    ? 'Your shift #'.$shift->id
                    : 'Open a shift to see live data',
            ];
        }

        if ($businessWide) {
            $shift = $this->openShiftForBusiness($business->id, $branchFilterId);

            return [
                'shift' => $shift,
                'mode' => $shift ? 'shift' : 'day',
                'date' => $date,
                'scope_label' => $shift
                    ? 'Business · Shift #'.$shift->id.' ('.$shift->user?->name.')'
                    : 'Business · Today',
            ];
        }

        $shift = Shift::openForUser($user->id, $business->id);

        return [
            'shift' => $shift,
            'mode' => $shift ? 'shift' : 'day',
            'date' => $date,
            'scope_label' => $shift ? 'Your shift #'.$shift->id : 'Your sales today',
        ];
    }

    /**
     * @param  array{branch_filter_id: ?int, business_type_key: ?string}  $filters
     */
    public function snapshot(
        User $user,
        Business $business,
        bool $businessWide,
        bool $scopeToStaffOnly,
        array $filters = [],
    ): array {
        $context = $this->resolveContext($user, $business, $businessWide, $filters['branch_filter_id'] ?? null);
        $shift = $context['shift'];
        $date = $context['date'];
        $branchFilterId = $filters['branch_filter_id'] ?? null;
        $businessTypeKey = $filters['business_type_key'] ?? null;

        $salesQuery = $this->baseSalesQuery(
            $business->id,
            $date,
            $shift?->id,
            $scopeToStaffOnly,
            $businessWide,
            $user,
            $branchFilterId,
            $businessTypeKey,
        );

        $activeQuery = (clone $salesQuery)->where('payment_status', '!=', 'cancelled');
        $sales = (clone $activeQuery)
            ->with(['user', 'items.item.category', 'items.item.packagings', 'items.service.category'])
            ->get();

        $grossProfit = $this->grossProfitFromSales($sales);
        $totalRevenue = (float) $sales->sum('total_amount');
        $collected = (float) $sales->sum('amount_paid');

        $cashRevenue = $this->sumByPaymentMethods($sales, ['cash']);
        $digitalRevenue = max(0, $collected - $cashRevenue);

        $circulation = max(0, $collected - $grossProfit);

        $totalOrders = $sales->count();
        $activeOrders = $sales->whereNotIn('payment_status', ['paid', 'cancelled'])->count();
        $servedOrders = $sales->where('payment_status', 'paid')->count();

        $hourlyData = $this->hourlyVelocity($salesQuery, $shift, $date);
        $categoryMix = $this->categoryMix($sales);
        $liveFeed = $this->liveFeed($salesQuery);
        $staffPulse = $this->staffPulse($sales);
        $topProducts = $this->topLines($sales, 'product', 5);
        $topServices = $this->topLines($sales, 'service', 5);

        return [
            'context' => $context,
            'active_shift' => $shift,
            'total_revenue' => $totalRevenue,
            'shift_profit' => $grossProfit,
            'today_cash' => $cashRevenue,
            'today_digital' => $digitalRevenue,
            'money_in_circulation' => $circulation,
            'total_orders' => $totalOrders,
            'active_orders' => $activeOrders,
            'served_orders' => $servedOrders,
            'hourly_data' => $hourlyData,
            'category_mix' => $categoryMix,
            'live_feed' => $liveFeed,
            'staff_pulse' => $staffPulse,
            'top_products' => $topProducts,
            'top_services' => $topServices,
            'margin_percent' => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 1) : 0,
            'filter_note' => $this->filterNote(
                $businessTypeKey,
                $filters['business_type_label'] ?? null,
            ),
        ];
    }

    public function jsonPayload(array $snapshot): array
    {
        return [
            'revenue' => [
                'total' => number_format($snapshot['total_revenue'], 0),
                'cash' => number_format($snapshot['today_cash'], 0),
                'digital' => number_format($snapshot['today_digital'], 0),
                'profit' => number_format($snapshot['shift_profit'], 0),
                'circulation' => number_format($snapshot['money_in_circulation'], 0),
            ],
            'pulse' => [
                'total_orders' => $snapshot['total_orders'],
                'active_orders' => $snapshot['active_orders'],
                'served_orders' => $snapshot['served_orders'],
            ],
            'hourly_data' => array_values($snapshot['hourly_data']),
            'category_mix' => [
                'products' => round($snapshot['category_mix']['products'], 2),
                'services' => round($snapshot['category_mix']['services'], 2),
            ],
            'live_feed' => view('live-sales.partials.feed_items', [
                'liveFeed' => $snapshot['live_feed'],
            ])->render(),
            'staff_pulse' => view('live-sales.partials.staff_items', [
                'staffPulse' => $snapshot['staff_pulse'],
            ])->render(),
            'margin_percent' => $snapshot['margin_percent'],
            'scope_label' => $snapshot['context']['scope_label'],
            'filter_note' => $snapshot['filter_note'] ?? '',
        ];
    }

    private function openShiftForBusiness(int $businessId, ?int $branchFilterId): ?Shift
    {
        $query = Shift::query()
            ->where('business_id', $businessId)
            ->where('status', 'open')
            ->with('user');

        $this->scopeShiftUsers($query, $branchFilterId);

        return $query->latest('opened_at')->first();
    }

    private function baseSalesQuery(
        int $businessId,
        string $date,
        ?int $shiftId,
        bool $scopeToStaffOnly,
        bool $businessWide,
        User $user,
        ?int $branchFilterId,
        ?string $businessTypeKey,
    ): Builder {
        $query = Sale::query()
            ->where('business_id', $businessId)
            ->whereDate('sale_date', $date);

        if ($shiftId) {
            $query->where('shift_id', $shiftId);
        }

        if ($scopeToStaffOnly) {
            $query->where('user_id', $user->id);
            if ($branchFilterId) {
                $this->applyBranchFilter($query, $branchFilterId);
            } elseif ($user->branch_id) {
                $this->applyBranchFilter($query, (int) $user->branch_id);
            }
        } elseif ($businessWide) {
            if ($branchFilterId) {
                $this->applyBranchFilter($query, $branchFilterId);
            }
        } else {
            $query->where('user_id', $user->id);
        }

        if ($businessTypeKey) {
            $this->applyBusinessTypeFilter($query, $businessTypeKey);
        }

        return $query;
    }

    private function applyBranchFilter(Builder $query, int $branchId): void
    {
        $query->where(function (Builder $scoped) use ($branchId) {
            $scoped->whereHas('items.item.category', function (Builder $categoryQuery) use ($branchId) {
                $categoryQuery->where('branch_id', $branchId);
            })->orWhereHas('items.service', function (Builder $serviceQuery) use ($branchId) {
                $serviceQuery->where('branch_id', $branchId);
            });
        });
    }

    private function applyBusinessTypeFilter(Builder $query, string $businessTypeKey): void
    {
        $query->where(function (Builder $scoped) use ($businessTypeKey) {
            $scoped->whereHas('items.item.category', function (Builder $categoryQuery) use ($businessTypeKey) {
                $categoryQuery->where('source_business_type_key', $businessTypeKey);
            })->orWhereHas('items.service.category', function (Builder $categoryQuery) use ($businessTypeKey) {
                $categoryQuery->where('source_service_type_key', $businessTypeKey);
            });
        });
    }

    private function scopeShiftUsers(Builder $query, ?int $branchFilterId): void
    {
        $branchId = $branchFilterId ?? active_branch_id();

        if (! $branchId) {
            return;
        }

        $query->whereHas('user', function (Builder $userQuery) use ($branchId) {
            $userQuery->where(function (Builder $scoped) use ($branchId) {
                $scoped->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            });
        });
    }

    private function grossProfitFromSales(Collection $sales): float
    {
        $costOfGoods = 0.0;

        foreach ($sales as $sale) {
            foreach ($sale->items as $line) {
                $unitCost = (float) ($line->cost_price ?? optional($line->item?->packagings?->first())->cost_price ?? 0);
                $costOfGoods += $unitCost * (float) $line->quantity;
            }
        }

        return (float) $sales->sum('total_amount') - $costOfGoods;
    }

    public function filterNote(?string $businessTypeKey, ?string $businessTypeLabel): string
    {
        if ($businessTypeKey && $businessTypeLabel) {
            return 'Business type: '.$businessTypeLabel;
        }

        return '';
    }

    /**
     * @return array<int, int>
     */
    private function hourlyVelocity(Builder $baseQuery, ?Shift $shift, string $date): array
    {
        $hours = array_fill(0, 24, 0);

        $rows = (clone $baseQuery)
            ->where('payment_status', '!=', 'cancelled')
            ->selectRaw('HOUR(created_at) as hour_bucket, COUNT(*) as total')
            ->groupBy('hour_bucket')
            ->pluck('total', 'hour_bucket');

        foreach ($rows as $hour => $count) {
            $hours[(int) $hour] = (int) $count;
        }

        if ($shift) {
            $startHour = $shift->opened_at?->hour ?? 0;
            for ($h = 0; $h < $startHour; $h++) {
                $hours[$h] = 0;
            }
        }

        return $hours;
    }

    /**
     * @return array{products: float, services: float}
     */
    private function categoryMix(Collection $sales): array
    {
        $products = 0.0;
        $services = 0.0;

        foreach ($sales as $sale) {
            foreach ($sale->items as $line) {
                $amount = (float) ($line->subtotal ?? ($line->unit_price * $line->quantity));
                if ($line->service_id) {
                    $services += $amount;
                } else {
                    $products += $amount;
                }
            }
        }

        return [
            'products' => $products,
            'services' => $services,
        ];
    }

    /**
     * @return Collection<int, Sale>
     */
    private function liveFeed(Builder $baseQuery): Collection
    {
        return (clone $baseQuery)
            ->where('payment_status', '!=', 'cancelled')
            ->with(['user', 'items.item', 'items.service'])
            ->latest()
            ->limit(30)
            ->get();
    }

    /**
     * @return Collection<int, object{user_id: int, name: string, orders: int, revenue: float}>
     */
    private function staffPulse(Collection $sales): Collection
    {
        return $sales
            ->groupBy('user_id')
            ->map(function (Collection $group, $userId) {
                $user = $group->first()->user;

                return (object) [
                    'user_id' => (int) $userId,
                    'name' => $user?->name ?? 'Staff',
                    'orders' => $group->count(),
                    'revenue' => (float) $group->sum('total_amount'),
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->take(8);
    }

    /**
     * @return Collection<int, object{name: string, total_qty: float, revenue: float}>
     */
    private function topLines(Collection $sales, string $type, int $limit): Collection
    {
        $lines = collect();

        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $isService = (bool) $item->service_id;
                if ($type === 'service' && ! $isService) {
                    continue;
                }
                if ($type === 'product' && $isService) {
                    continue;
                }

                $name = $isService
                    ? ($item->line_description ?: $item->service?->name ?: 'Service')
                    : ($item->item?->name ?? 'Item');

                $key = $name;
                $existing = $lines->get($key, ['name' => $name, 'total_qty' => 0.0, 'revenue' => 0.0]);
                $existing['total_qty'] += (float) $item->quantity;
                $existing['revenue'] += (float) ($item->subtotal ?? ($item->unit_price * $item->quantity));
                $lines->put($key, $existing);
            }
        }

        return $lines
            ->sortByDesc('revenue')
            ->take($limit)
            ->map(fn (array $row) => (object) $row)
            ->values();
    }

    /**
     * @param  list<string>  $methods
     */
    private function sumByPaymentMethods(Collection $sales, array $methods): float
    {
        return (float) $sales
            ->filter(fn (Sale $sale) => in_array($sale->payment_method, $methods, true))
            ->sum('amount_paid');
    }
}
