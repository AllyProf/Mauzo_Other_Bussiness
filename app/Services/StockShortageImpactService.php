<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ShiftStockCheck;
use App\Models\User;
use Illuminate\Support\Collection;

class StockShortageImpactService
{
    public function __construct(private ItemPackagingNormalizer $normalizer) {}

    public function enrich(ShiftStockCheck $check): ShiftStockCheck
    {
        $check->setAttribute('financial_impact', $this->forCheck($check));

        return $check;
    }

    /**
     * @return Collection<int, ShiftStockCheck>
     */
    public function staffShortagesForUser(User $user, int $limit = 15): Collection
    {
        if (! $user->business_id) {
            return collect();
        }

        return ShiftStockCheck::query()
            ->whereHas('shift', fn ($q) => $q
                ->where('business_id', $user->business_id)
                ->where('user_id', $user->id))
            ->shortages()
            ->with(['item.packagings.packagingType', 'item.category', 'shift'])
            ->latest('recorded_at')
            ->limit($limit)
            ->get()
            ->map(fn (ShiftStockCheck $check) => $this->enrich($check));
    }

    /**
     * @param  Collection<int, ShiftStockCheck>  $shortages
     * @return array{total: int, pending: int, will_be_paid: int, waived: int, amount_due: float}
     */
    public function staffShortageStats(Collection $shortages): array
    {
        $willBePaid = $shortages->filter(fn (ShiftStockCheck $check) => $check->isWillBePaid());

        return [
            'total' => $shortages->count(),
            'pending' => $shortages->filter(fn (ShiftStockCheck $check) => ! $check->isVerified())->count(),
            'will_be_paid' => $willBePaid->count(),
            'waived' => $shortages->filter(fn (ShiftStockCheck $check) => $check->isWaived())->count(),
            'amount_due' => round($willBePaid->sum(fn (ShiftStockCheck $check) => (float) ($check->financial_impact['cost_value'] ?? 0)), 2),
        ];
    }

    /**
     * @param  Collection<int, ShiftStockCheck>  $checks
     * @return array{cost_value: float, revenue_value: float, profit_value: float, shortage_qty: float}
     */
    public function summarize(Collection $checks): array
    {
        $totals = [
            'cost_value' => 0.0,
            'revenue_value' => 0.0,
            'profit_value' => 0.0,
            'shortage_qty' => 0.0,
        ];

        foreach ($checks as $check) {
            $impact = $this->forCheck($check);
            $totals['cost_value'] += $impact['cost_value'];
            $totals['revenue_value'] += $impact['revenue_value'];
            $totals['profit_value'] += $impact['profit_value'];
            $totals['shortage_qty'] += $impact['shortage_qty'];
        }

        return $totals;
    }

    /**
     * @return array{
     *     shortage_qty: float,
     *     unit_cost: float,
     *     unit_sell: float,
     *     cost_value: float,
     *     revenue_value: float,
     *     profit_value: float
     * }
     */
    public function forCheck(ShiftStockCheck $check): array
    {
        $qty = $check->shortageAmount();

        if (! $check->item || $qty <= 0) {
            return $this->emptyImpact($qty);
        }

        return $this->forItem($check->item, $qty);
    }

    /**
     * @return array{
     *     shortage_qty: float,
     *     unit_cost: float,
     *     unit_sell: float,
     *     cost_value: float,
     *     revenue_value: float,
     *     profit_value: float
     * }
     */
    public function forItem(Item $item, float $shortageQty): array
    {
        if ($shortageQty <= 0) {
            return $this->emptyImpact(0);
        }

        $item->loadMissing(['packagings.packagingType']);
        $pricing = $this->perPiecePricing($item);

        $costValue = round($shortageQty * $pricing['unit_cost'], 2);
        $revenueValue = round($shortageQty * $pricing['unit_sell'], 2);

        return [
            'shortage_qty' => $shortageQty,
            'unit_cost' => $pricing['unit_cost'],
            'unit_sell' => $pricing['unit_sell'],
            'cost_value' => $costValue,
            'revenue_value' => $revenueValue,
            'profit_value' => round($revenueValue - $costValue, 2),
        ];
    }

    /**
     * @return array{unit_cost: float, unit_sell: float}
     */
    private function perPiecePricing(Item $item): array
    {
        $packagingModels = $item->packagings->sortBy('quantity_per_unit')->values();
        $normalized = $this->normalizer->normalizeItemPackagings($item, $packagingModels);

        $row = $normalized->first(fn ($r) => (int) $r['quantity_per_unit'] === 1)
            ?? $normalized->sortBy('quantity_per_unit')->first();

        if (! $row) {
            return ['unit_cost' => 0.0, 'unit_sell' => 0.0];
        }

        $qpu = max(1, (int) $row['quantity_per_unit']);
        $packaging = $row['packaging'];

        return [
            'unit_cost' => round((float) ($packaging->cost_price ?? 0) / $qpu, 2),
            'unit_sell' => round((float) ($packaging->selling_price ?? 0) / $qpu, 2),
        ];
    }

    /**
     * @return array{
     *     shortage_qty: float,
     *     unit_cost: float,
     *     unit_sell: float,
     *     cost_value: float,
     *     revenue_value: float,
     *     profit_value: float
     * }
     */
    private function emptyImpact(float $qty): array
    {
        return [
            'shortage_qty' => $qty,
            'unit_cost' => 0.0,
            'unit_sell' => 0.0,
            'cost_value' => 0.0,
            'revenue_value' => 0.0,
            'profit_value' => 0.0,
        ];
    }
}
