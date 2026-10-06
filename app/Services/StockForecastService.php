<?php

namespace App\Services;

use App\Models\Item;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Simple on-device-style demand forecast: selling speed per item from recent sales,
 * adjusted for weekday pattern, payday (month end) and Tanzanian public holidays.
 */
class StockForecastService
{
    private const HISTORY_DAYS = 28;

    private const RECENT_DAYS = 7;

    private const COVER_DAYS = 10;

    private const HORIZON_DAYS = 60;

    /** month-day => [en, sw] */
    private const HOLIDAYS = [
        '01-01' => ['New Year', 'Mwaka Mpya'],
        '01-12' => ['Zanzibar Revolution Day', 'Mapinduzi ya Zanzibar'],
        '04-07' => ['Karume Day', 'Siku ya Karume'],
        '04-26' => ['Union Day', 'Siku ya Muungano'],
        '05-01' => ['Workers Day', 'Mei Mosi'],
        '07-07' => ['Saba Saba', 'Saba Saba'],
        '08-08' => ['Nane Nane', 'Nane Nane'],
        '10-14' => ['Nyerere Day', 'Siku ya Nyerere'],
        '12-09' => ['Independence Day', 'Siku ya Uhuru'],
        '12-25' => ['Christmas', 'Krismasi'],
        '12-26' => ['Boxing Day', 'Siku ya Kupeana Zawadi'],
    ];

    public function __construct(private ItemStockDisplayService $stockDisplay)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function forecast(int $businessId, ?int $branchId): array
    {
        $today = CarbonImmutable::today();
        $from = $today->subDays(self::HISTORY_DAYS);

        $items = Item::query()
            ->where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('branch_id', $branchId)))
            ->with(['packagings.packagingType', 'receivingPackaging'])
            ->get()
            ->keyBy('id');

        if ($items->isEmpty()) {
            return $this->emptyPayload($today);
        }

        $lines = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.business_id', $businessId)
            ->where('sales.payment_status', '!=', 'cancelled')
            ->whereNotNull('sale_items.item_id')
            ->whereIn('sale_items.item_id', $items->keys())
            ->where('sales.sale_date', '>=', $from->toDateString())
            ->selectRaw('sale_items.item_id, sale_items.item_packaging_id, DATE(sales.sale_date) as day, SUM(sale_items.quantity) as qty')
            ->groupBy('sale_items.item_id', 'sale_items.item_packaging_id', DB::raw('DATE(sales.sale_date)'))
            ->get();

        $firstSaleDay = DB::table('sales')
            ->where('business_id', $businessId)
            ->where('payment_status', '!=', 'cancelled')
            ->min('sale_date');
        $historyDays = self::HISTORY_DAYS;
        if ($firstSaleDay) {
            $age = Carbon::parse($firstSaleDay)->startOfDay()->diffInDays($today) + 1;
            $historyDays = (int) max(self::RECENT_DAYS, min(self::HISTORY_DAYS, $age));
        }

        // item_id => [day => pieces]
        $daily = [];
        $packFactor = [];
        foreach ($lines as $line) {
            $item = $items->get($line->item_id);
            if (! $item) {
                continue;
            }
            $key = $line->item_id.':'.($line->item_packaging_id ?? 0);
            if (! isset($packFactor[$key])) {
                $packaging = $line->item_packaging_id
                    ? $item->packagings->firstWhere('id', (int) $line->item_packaging_id)
                    : null;
                $packFactor[$key] = $item->effectiveQuantityPerUnit($packaging);
            }
            $pieces = (float) $line->qty * $packFactor[$key];
            $daily[$line->item_id][$line->day] = ($daily[$line->item_id][$line->day] ?? 0) + $pieces;
        }

        [$weekdayFactors, $paydayFactor] = $this->patternFactors($daily, $today, $historyDays);
        $upcoming = $this->upcomingEvents($today, 7);

        $rows = [];
        foreach ($items as $item) {
            $rows[] = $this->itemForecast(
                $item,
                $daily[$item->id] ?? [],
                $today,
                $historyDays,
                $weekdayFactors,
                $paydayFactor,
            );
        }

        $rows = collect($rows)
            ->filter(fn ($r) => $r['status'] !== 'idle')
            ->sortBy(fn ($r) => [
                ['out' => 0, 'critical' => 1, 'low' => 2, 'ok' => 3, 'slow' => 4][$r['status']] ?? 5,
                $r['days_left'] ?? 9999,
            ])
            ->values();

        $bestDow = collect($weekdayFactors)->sortDesc()->keys()->first();

        return [
            'generated_at' => now()->toIso8601String(),
            'history_days' => $historyDays,
            'summary' => [
                'out' => $rows->where('status', 'out')->count(),
                'critical' => $rows->where('status', 'critical')->count(),
                'low' => $rows->where('status', 'low')->count(),
                'ok' => $rows->where('status', 'ok')->count(),
                'slow' => $rows->where('status', 'slow')->count(),
                'reorder' => $rows->where('reorder_packs', '>', 0)->count(),
            ],
            'insights' => [
                'payday_boost' => round($paydayFactor, 2),
                'best_weekday' => $bestDow !== null && ($weekdayFactors[$bestDow] ?? 1) > 1.05 ? (int) $bestDow : null,
                'best_weekday_boost' => $bestDow !== null ? round($weekdayFactors[$bestDow], 2) : null,
                'upcoming' => $upcoming,
            ],
            'items' => $rows->all(),
        ];
    }

    /**
     * @param  array<string, float>  $days
     * @param  array<int, float>  $weekdayFactors
     * @return array<string, mixed>
     */
    private function itemForecast(
        Item $item,
        array $days,
        CarbonImmutable $today,
        int $historyDays,
        array $weekdayFactors,
        float $paydayFactor,
    ): array {
        $stock = max(0, (float) $item->current_stock);
        $total = array_sum($days);
        $recentFrom = $today->subDays(self::RECENT_DAYS - 1)->toDateString();
        $recent = 0.0;
        foreach ($days as $day => $pieces) {
            if ($day >= $recentFrom) {
                $recent += $pieces;
            }
        }

        $rateLong = $total / $historyDays;
        $rateRecent = $recent / self::RECENT_DAYS;
        $rate = $total > 0 ? (0.6 * $rateRecent + 0.4 * $rateLong) : 0.0;

        $trend = 'steady';
        if ($rateLong > 0) {
            $ratio = $rateRecent / $rateLong;
            $trend = $ratio >= 1.25 ? 'up' : ($ratio <= 0.75 ? 'down' : 'steady');
        }

        $daysLeft = null;
        $runOutDate = null;
        $coverDemand = 0.0;
        if ($rate > 0) {
            $remaining = $stock;
            for ($i = 0; $i < self::HORIZON_DAYS; $i++) {
                $date = $today->addDays($i);
                $demand = $rate * $this->dayMultiplier($date, $weekdayFactors, $paydayFactor);
                if ($i < self::COVER_DAYS) {
                    $coverDemand += $demand;
                }
                if ($daysLeft === null) {
                    $remaining -= $demand;
                    if ($remaining <= 0) {
                        $daysLeft = $i;
                        $runOutDate = $date->toDateString();
                    }
                }
            }
        }

        $packSize = max(1, (int) ($item->units_per_receiving_pack ?: 1));
        $packName = $item->receivingPackaging?->name ?: $item->baseStockUnitName();
        $need = max(0, $coverDemand - $stock);
        $reorderPacks = $need > 0 ? (int) ceil($need / $packSize) : 0;

        $status = match (true) {
            $rate <= 0 && $stock > 0 => 'slow',
            $rate <= 0 => 'idle',
            $stock <= 0 => 'out',
            $daysLeft !== null && $daysLeft <= 3 => 'critical',
            $daysLeft !== null && $daysLeft <= 7 => 'low',
            default => 'ok',
        };

        return [
            'item_id' => $item->id,
            'name' => $item->name,
            'stock' => round($stock, 2),
            'stock_display' => $this->stockDisplay->format($item, $stock)['stock_display'],
            'unit' => $item->baseStockUnitName(),
            'daily_rate' => round($rate, 2),
            'week_demand' => round($rate * 7, 1),
            'sold_period' => round($total, 2),
            'trend' => $trend,
            'days_left' => $status === 'out' ? 0 : $daysLeft,
            'run_out_date' => $status === 'out' ? $today->toDateString() : $runOutDate,
            'reorder_packs' => $reorderPacks,
            'reorder_pieces' => $reorderPacks * $packSize,
            'pack_name' => $packName,
            'pack_size' => $packSize,
            'status' => $status,
        ];
    }

    /**
     * @param  array<int, float>  $weekdayFactors
     */
    private function dayMultiplier(CarbonImmutable $date, array $weekdayFactors, float $paydayFactor): float
    {
        $m = $weekdayFactors[$date->dayOfWeek] ?? 1.0;
        if ($this->isPayday($date)) {
            $m *= $paydayFactor;
        }
        if (isset(self::HOLIDAYS[$date->format('m-d')])) {
            $m *= 1.2;
        }

        return $m;
    }

    private function isPayday(CarbonImmutable $date): bool
    {
        return $date->day >= 25 || $date->day <= 3;
    }

    /**
     * Business-wide weekday and payday multipliers learned from the history window.
     *
     * @param  array<int, array<string, float>>  $daily
     * @return array{0: array<int, float>, 1: float}
     */
    private function patternFactors(array $daily, CarbonImmutable $today, int $historyDays): array
    {
        $totals = [];
        foreach ($daily as $days) {
            foreach ($days as $day => $pieces) {
                $totals[$day] = ($totals[$day] ?? 0) + $pieces;
            }
        }

        $weekdayFactors = array_fill(0, 7, 1.0);
        $paydayFactor = 1.0;
        if (count($totals) < 7) {
            return [$weekdayFactors, $paydayFactor];
        }

        $start = $today->subDays($historyDays - 1);
        $byDow = array_fill(0, 7, []);
        $payday = [];
        $normal = [];
        for ($d = $start; $d->lte($today); $d = $d->addDay()) {
            $value = $totals[$d->toDateString()] ?? 0.0;
            $byDow[$d->dayOfWeek][] = $value;
            if ($this->isPayday($d)) {
                $payday[] = $value;
            } else {
                $normal[] = $value;
            }
        }

        $all = array_merge(...array_values($byDow));
        $mean = count($all) ? array_sum($all) / count($all) : 0;
        if ($mean > 0) {
            foreach ($byDow as $dow => $values) {
                if (count($values) === 0) {
                    continue;
                }
                $avg = array_sum($values) / count($values);
                $weekdayFactors[$dow] = $this->clamp($avg / $mean, 0.6, 1.6);
            }
        }

        if (count($payday) >= 3 && count($normal) >= 3) {
            $normalAvg = array_sum($normal) / count($normal);
            $paydayAvg = array_sum($payday) / count($payday);
            if ($normalAvg > 0) {
                $paydayFactor = $this->clamp($paydayAvg / $normalAvg, 1.0, 1.5);
            }
        }

        return [$weekdayFactors, $paydayFactor];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function upcomingEvents(CarbonImmutable $today, int $days): array
    {
        $events = [];
        $paydayAdded = false;
        for ($i = 0; $i < $days; $i++) {
            $date = $today->addDays($i);
            if (isset(self::HOLIDAYS[$date->format('m-d')])) {
                [$en, $sw] = self::HOLIDAYS[$date->format('m-d')];
                $events[] = ['type' => 'holiday', 'date' => $date->toDateString(), 'in_days' => $i, 'name' => $en, 'name_sw' => $sw];
            }
            if (! $paydayAdded && ($date->day === 25 || ($i === 0 && $this->isPayday($date)))) {
                $events[] = ['type' => 'payday', 'date' => $date->toDateString(), 'in_days' => $i, 'name' => 'Month-end payday', 'name_sw' => 'Mwisho wa mwezi (mishahara)'];
                $paydayAdded = true;
            }
        }

        return $events;
    }

    private function clamp(float $v, float $min, float $max): float
    {
        return max($min, min($max, $v));
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(CarbonImmutable $today): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'history_days' => 0,
            'summary' => ['out' => 0, 'critical' => 0, 'low' => 0, 'ok' => 0, 'slow' => 0, 'reorder' => 0],
            'insights' => ['payday_boost' => 1.0, 'best_weekday' => null, 'best_weekday_boost' => null, 'upcoming' => $this->upcomingEvents($today, 7)],
            'items' => [],
        ];
    }
}
