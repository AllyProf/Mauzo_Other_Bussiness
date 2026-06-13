<?php

namespace App\Services;

use App\Models\Business;
use App\Models\SalePayment;

class BusinessTypeBreakdownService
{
    public function buildFromSales($daySales, array $businessTypes, ?Business $business, array $debtByType = [], array $debtProfitByType = []): array
    {
        $labels = collect($businessTypes)->mapWithKeys(fn ($type) => [
            ($type['key'] ?? 'other') => $type['label'] ?? 'Other',
        ]);

        $deductFrom = $business?->expense_deduct_from ?? 'circulation';
        $activeSales = $daySales->where('payment_status', '!=', 'cancelled');
        $rows = [];

        foreach ($activeSales as $sale) {
            $saleTotal = (float) $sale->total_amount;
            $salePaid = (float) $sale->amount_paid;

            foreach ($sale->items as $line) {
                $typeKey = $line->item?->category?->source_business_type_key ?: 'other';
                $lineTotal = (float) ($line->subtotal ?? 0);

                if ($lineTotal <= 0 && $saleTotal > 0) {
                    $lineTotal = $saleTotal / max(1, $sale->items->count());
                }

                $share = $saleTotal > 0 ? ($lineTotal / $saleTotal) : 0;
                $unitCost = (float) ($line->cost_price ?? optional($line->item?->packagings?->first())->cost_price ?? 0);
                $lineCost = $unitCost * (float) $line->quantity;

                if (! isset($rows[$typeKey])) {
                    $rows[$typeKey] = [
                        'key' => $typeKey,
                        'label' => $labels->get($typeKey, ucwords(str_replace([':', '_', '-'], ' ', $typeKey))),
                        'orders' => [],
                        'gross_sales' => 0.0,
                        'collected' => 0.0,
                        'cost_of_goods' => 0.0,
                        'credit' => 0.0,
                        'debt_collected' => 0.0,
                        'gross_profit' => 0.0,
                        'profit_generated' => 0.0,
                        'circulation_generated' => 0.0,
                    ];
                }

                $rows[$typeKey]['orders'][$sale->id] = true;
                $rows[$typeKey]['gross_sales'] += $lineTotal;
                $rows[$typeKey]['collected'] += $salePaid * $share;
                $rows[$typeKey]['cost_of_goods'] += $lineCost;
            }
        }

        foreach ($debtByType as $typeKey => $debtAmount) {
            if ((float) $debtAmount <= 0 || isset($rows[$typeKey])) {
                continue;
            }

            $debtProfit = (float) ($debtProfitByType[$typeKey] ?? 0);
            $rows[$typeKey] = [
                'key' => $typeKey,
                'label' => $labels->get($typeKey, ucwords(str_replace([':', '_', '-'], ' ', $typeKey))),
                'orders' => 0,
                'gross_sales' => 0.0,
                'collected' => 0.0,
                'cost_of_goods' => 0.0,
                'credit' => 0.0,
                'debt_collected' => (float) $debtAmount,
                'gross_profit' => $debtProfit,
                'profit_generated' => 0.0,
                'circulation_generated' => 0.0,
            ];
        }

        return collect($rows)
            ->map(function ($row) use ($deductFrom, $debtByType, $debtProfitByType) {
                $row['orders'] = is_array($row['orders'] ?? null) ? count($row['orders']) : (int) ($row['orders'] ?? 0);
                $row['credit'] = max(0, $row['gross_sales'] - $row['collected']);
                $debtProfitShare = (float) ($debtProfitByType[$row['key']] ?? 0);
                if (($row['gross_profit'] ?? 0) <= 0) {
                    $row['gross_profit'] = max(0, $row['gross_sales'] - $row['cost_of_goods']);
                }
                $row['gross_profit'] = (float) $row['gross_profit'] + $debtProfitShare;
                $row['debt_collected'] = (float) ($debtByType[$row['key']] ?? $row['debt_collected'] ?? 0);
                $collectedTotal = (float) $row['collected'] + (float) $row['debt_collected'];

                if ($deductFrom === 'circulation') {
                    $row['profit_generated'] = min($collectedTotal, (float) $row['gross_profit']);
                    $row['circulation_generated'] = max(0, $collectedTotal - (float) $row['gross_profit']);
                } else {
                    $row['profit_generated'] = (float) $row['gross_profit'];
                    $row['circulation_generated'] = $collectedTotal;
                }

                return $row;
            })
            ->sortByDesc('gross_sales')
            ->values()
            ->all();
    }

    public function summarize(array $breakdown): array
    {
        return [
            'orders' => collect($breakdown)->sum('orders'),
            'gross_sales' => (float) collect($breakdown)->sum('gross_sales'),
            'collected' => (float) collect($breakdown)->sum('collected'),
            'credit' => (float) collect($breakdown)->sum('credit'),
            'debt_collected' => (float) collect($breakdown)->sum('debt_collected'),
            'gross_profit' => (float) collect($breakdown)->sum('gross_profit'),
            'profit_generated' => (float) collect($breakdown)->sum('profit_generated'),
            'circulation_generated' => (float) collect($breakdown)->sum('circulation_generated'),
        ];
    }

    public function allocateDebtFromPayments($payments): array
    {
        $byType = [];

        foreach ($payments as $payment) {
            if (! $payment instanceof SalePayment) {
                continue;
            }

            $sale = $payment->sale;
            if (! $sale) {
                continue;
            }

            $sale->loadMissing(['items.item.category']);
            $amount = (float) $payment->amount;
            $typeWeights = [];
            $totalWeight = 0.0;

            foreach ($sale->items as $line) {
                $typeKey = $line->item?->category?->source_business_type_key ?: 'other';
                $weight = (float) ($line->subtotal ?? 0);
                if ($weight <= 0) {
                    $weight = 1.0;
                }
                $typeWeights[$typeKey] = ($typeWeights[$typeKey] ?? 0) + $weight;
                $totalWeight += $weight;
            }

            if ($totalWeight <= 0) {
                $byType['other'] = ($byType['other'] ?? 0) + $amount;

                continue;
            }

            foreach ($typeWeights as $typeKey => $weight) {
                $byType[$typeKey] = ($byType[$typeKey] ?? 0) + ($amount * ($weight / $totalWeight));
            }
        }

        return $byType;
    }

    public function allocateDebtProfitFromPayments($payments): array
    {
        $byType = [];

        foreach ($payments as $payment) {
            if (! $payment instanceof SalePayment) {
                continue;
            }

            $sale = $payment->sale;
            if (! $sale) {
                continue;
            }

            $sale->loadMissing(['items.item.packagings']);
            $saleTotal = (float) $sale->total_amount;
            $paymentAmount = (float) $payment->amount;

            if ($saleTotal <= 0 || $paymentAmount <= 0) {
                continue;
            }

            $costOfGoods = 0.0;
            foreach ($sale->items as $line) {
                $unitCost = (float) ($line->cost_price ?? optional($line->item?->packagings?->first())->cost_price ?? 0);
                $costOfGoods += $unitCost * (float) $line->quantity;
            }

            $saleProfit = max(0, $saleTotal - $costOfGoods);
            $ratio = min(1, $paymentAmount / $saleTotal);
            $paymentProfit = $saleProfit * $ratio;

            $typeWeights = [];
            $totalWeight = 0.0;

            foreach ($sale->items as $line) {
                $typeKey = $line->item?->category?->source_business_type_key ?: 'other';
                $weight = (float) ($line->subtotal ?? 0);
                if ($weight <= 0) {
                    $weight = 1.0;
                }
                $typeWeights[$typeKey] = ($typeWeights[$typeKey] ?? 0) + $weight;
                $totalWeight += $weight;
            }

            if ($totalWeight <= 0) {
                $byType['other'] = ($byType['other'] ?? 0) + $paymentProfit;

                continue;
            }

            foreach ($typeWeights as $typeKey => $weight) {
                $byType[$typeKey] = ($byType[$typeKey] ?? 0) + ($paymentProfit * ($weight / $totalWeight));
            }
        }

        return $byType;
    }
}
