<?php

namespace App\Services;

use App\Models\DayClosing;
use App\Models\MoneyShortSettlement;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoneyShortSettlementService
{
    public function __construct(private OwnerDailyReportService $reportService)
    {
    }

    public function computeShortSplit(DayClosing $closing): array
    {
        $closing->loadMissing('business');
        $business = $closing->business;
        $date = $closing->closing_date->toDateString();
        $profit = $this->reportService->calculateProfit($business->id, $date, $closing->shift_id, $closing);
        $grossProfit = (float) $profit['gross_profit'];
        $expected = $closing->expectedHandoverAmount();
        $actual = $closing->resolvedHandoverAmount();
        $deductFrom = $business->expense_deduct_from ?? 'circulation';

        if ($deductFrom === 'profit') {
            $profitShort = max(0, (float) $closing->money_short);
            $circulationShort = 0.0;
            $profitFromHandover = max(0, $grossProfit - (float) $closing->total_expenses - $profitShort);
            $circulationFromHandover = max(0, $actual - $profitFromHandover);
        } else {
            $expectedCirculation = max(0, $expected - $grossProfit);
            $circulationFromHandover = max(0, $actual - $grossProfit);
            $profitFromHandover = min($actual, $grossProfit);
            $profitShort = max(0, $grossProfit - $actual);
            $circulationShort = max(0, $expectedCirculation - $circulationFromHandover);
        }

        return [
            'gross_profit' => $grossProfit,
            'expected_handover' => $expected,
            'actual_received' => $actual,
            'profit_short' => round($profitShort, 2),
            'circulation_short' => round($circulationShort, 2),
            'profit_from_handover' => round($profitFromHandover, 2),
            'circulation_from_handover' => round($circulationFromHandover, 2),
        ];
    }

    public function closingSales(DayClosing $closing)
    {
        $query = Sale::where('business_id', $closing->business_id)
            ->whereDate('sale_date', $closing->closing_date)
            ->where('payment_status', '!=', 'cancelled')
            ->with(['items.item.category']);

        if ($closing->shift_id) {
            return $query->where('shift_id', $closing->shift_id)->get();
        }

        return $query->where('user_id', $closing->user_id)->get();
    }

    public function closingBusinessTypeKeys(DayClosing $closing): array
    {
        return $this->closingSales($closing)
            ->flatMap(fn ($sale) => $sale->businessTypeKeys())
            ->unique()
            ->values()
            ->all();
    }

    public function businessTypeShareRatio(DayClosing $closing, string $businessTypeKey): float
    {
        $sales = $this->closingSales($closing);
        if ($sales->isEmpty()) {
            return 0.0;
        }

        $closing->loadMissing('business');
        $business = $closing->business;
        $branchId = active_branch_id();
        $businessTypes = $branchId
            ? $business->branchPosBusinessTypesMeta($branchId)
            : $business->posBusinessTypesMeta();

        $breakdown = app(BusinessTypeBreakdownService::class)->buildFromSales(
            $sales,
            $businessTypes,
            $business,
            []
        );

        $totalCollected = (float) collect($breakdown)->sum('collected');
        if ($totalCollected <= 0) {
            $totalCollected = (float) collect($breakdown)->sum('gross_sales');
        }

        if ($totalCollected <= 0) {
            return 0.0;
        }

        $typeRow = collect($breakdown)->firstWhere('key', $businessTypeKey);
        $typeAmount = (float) ($typeRow['collected'] ?? 0);
        if ($typeAmount <= 0) {
            $typeAmount = (float) ($typeRow['gross_sales'] ?? 0);
        }

        return min(1, max(0, $typeAmount / $totalCollected));
    }

    public function allocateShortToBusinessType(DayClosing $closing, string $businessTypeKey): array
    {
        $ratio = $this->businessTypeShareRatio($closing, $businessTypeKey);
        $originalShort = (float) $closing->money_short;
        $allocatedShort = round($originalShort * $ratio, 2);
        $totalSettled = (float) $closing->settlements()->active()->sum('amount');
        $share = $originalShort > 0 ? ($allocatedShort / $originalShort) : 0.0;
        $allocatedSettled = round($totalSettled * $share, 2);

        return [
            'ratio' => $ratio,
            'allocated_short' => $allocatedShort,
            'allocated_settled' => $allocatedSettled,
            'allocated_balance' => max(0, round($allocatedShort - $allocatedSettled, 2)),
        ];
    }

    public function scopeClosingsForBusinessType($query, int $businessId, string $businessTypeKey)
    {
        return $query->where(function ($outer) use ($businessId, $businessTypeKey) {
            $outer->where(function ($shiftQuery) use ($businessId, $businessTypeKey) {
                $shiftQuery->whereNotNull('shift_id')->whereExists(function ($sub) use ($businessId, $businessTypeKey) {
                    $sub->from('sales')
                        ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
                        ->join('items', 'items.id', '=', 'sale_items.item_id')
                        ->join('categories', 'categories.id', '=', 'items.category_id')
                        ->whereColumn('sales.shift_id', 'day_closings.shift_id')
                        ->where('sales.business_id', $businessId)
                        ->where('sales.payment_status', '!=', 'cancelled')
                        ->where('categories.source_business_type_key', $businessTypeKey)
                        ->whereRaw('DATE(sales.sale_date) = DATE(day_closings.closing_date)')
                        ->selectRaw('1');
                });
            })->orWhere(function ($ownerQuery) use ($businessId, $businessTypeKey) {
                $ownerQuery->whereNull('shift_id')->whereExists(function ($sub) use ($businessId, $businessTypeKey) {
                    $sub->from('sales')
                        ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
                        ->join('items', 'items.id', '=', 'sale_items.item_id')
                        ->join('categories', 'categories.id', '=', 'items.category_id')
                        ->whereColumn('sales.user_id', 'day_closings.user_id')
                        ->where('sales.business_id', $businessId)
                        ->where('sales.payment_status', '!=', 'cancelled')
                        ->where('categories.source_business_type_key', $businessTypeKey)
                        ->whereRaw('DATE(sales.sale_date) = DATE(day_closings.closing_date)')
                        ->selectRaw('1');
                });
            });
        });
    }

    public function recoveryAllocation(MoneyShortSettlement $settlement): array
    {
        $closing = $settlement->dayClosing;
        if (! $closing) {
            return ['profit' => 0.0, 'circulation' => (float) $settlement->amount];
        }

        $split = $this->computeShortSplit($closing);
        $priorSettled = (float) $closing->settlements()
            ->active()
            ->where('settlement_type', MoneyShortSettlement::TYPE_CASH_PAYMENT)
            ->where('id', '<', $settlement->id)
            ->sum('amount');

        $totalIncludingThis = $priorSettled + (float) $settlement->amount;
        $profitTarget = $split['profit_short'];
        $profitAllocatedTotal = min($totalIncludingThis, $profitTarget);
        $profitAllocatedPrior = min($priorSettled, $profitTarget);
        $toProfit = $profitAllocatedTotal - $profitAllocatedPrior;
        $toCirculation = (float) $settlement->amount - $toProfit;

        return [
            'profit' => round(max(0, $toProfit), 2),
            'circulation' => round(max(0, $toCirculation), 2),
        ];
    }

    public function recoveryTotalsForDate(int $businessId, string $date): array
    {
        $settlements = MoneyShortSettlement::where('business_id', $businessId)
            ->whereDate('settlement_date', $date)
            ->where('settlement_type', MoneyShortSettlement::TYPE_CASH_PAYMENT)
            ->active()
            ->with('dayClosing')
            ->orderBy('id')
            ->get();

        $totals = [
            'total' => 0.0,
            'profit' => 0.0,
            'circulation' => 0.0,
        ];

        foreach ($settlements as $settlement) {
            $allocation = $this->recoveryAllocation($settlement);
            $totals['total'] += (float) $settlement->amount;
            $totals['profit'] += $allocation['profit'];
            $totals['circulation'] += $allocation['circulation'];
        }

        return $totals;
    }

    public function shortBalance(DayClosing $closing): float
    {
        $settled = (float) $closing->settlements()->active()->sum('amount');

        return max(0, round((float) $closing->money_short - $settled, 2));
    }

    public function settlementStatus(DayClosing $closing): string
    {
        if ((float) $closing->money_short <= 0) {
            return 'none';
        }

        $balance = $this->shortBalance($closing);
        if ($balance <= 0) {
            $lastType = $closing->settlements()->active()->latest('id')->value('settlement_type');

            return $lastType === MoneyShortSettlement::TYPE_SALARY_DEDUCTION
                ? 'salary_deduction'
                : 'paid';
        }

        return $closing->settlements()->exists() ? 'partial' : 'pending';
    }

    public function recordCashPayment(DayClosing $closing, User $owner, array $data): MoneyShortSettlement
    {
        return DB::transaction(function () use ($closing, $owner, $data) {
            $amount = round((float) $data['amount'], 2);
            $balance = $this->shortBalance($closing);

            if ($amount <= 0 || $amount > $balance + 0.009) {
                throw ValidationException::withMessages([
                    'amount' => 'Enter an amount up to the outstanding balance of '.number_format($balance, 0).'.',
                ]);
            }

            $settlement = MoneyShortSettlement::create([
                'business_id' => $closing->business_id,
                'day_closing_id' => $closing->id,
                'user_id' => $closing->user_id,
                'settlement_type' => MoneyShortSettlement::TYPE_CASH_PAYMENT,
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'payment_provider' => $data['payment_provider'] ?? null,
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'settlement_date' => $data['settlement_date'],
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $owner->id,
            ]);

            $this->reportService->syncReport(
                $closing->business,
                $settlement->settlement_date->toDateString()
            );

            return $settlement;
        });
    }

    public function recordSalaryDeduction(DayClosing $closing, User $owner, array $data): MoneyShortSettlement
    {
        return DB::transaction(function () use ($closing, $owner, $data) {
            $balance = $this->shortBalance($closing);
            $amount = isset($data['amount']) && $data['amount'] !== ''
                ? round((float) $data['amount'], 2)
                : $balance;

            if ($amount <= 0 || $amount > $balance + 0.009) {
                throw ValidationException::withMessages([
                    'amount' => 'Enter an amount up to the outstanding balance of '.number_format($balance, 0).'.',
                ]);
            }

            return MoneyShortSettlement::create([
                'business_id' => $closing->business_id,
                'day_closing_id' => $closing->id,
                'user_id' => $closing->user_id,
                'settlement_type' => MoneyShortSettlement::TYPE_SALARY_DEDUCTION,
                'amount' => $amount,
                'settlement_date' => $data['settlement_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $owner->id,
            ]);
        });
    }

    public function undoSettlement(MoneyShortSettlement $settlement, User $owner): array
    {
        if ($settlement->isVoided()) {
            throw ValidationException::withMessages([
                'settlement' => 'This settlement has already been undone.',
            ]);
        }

        return DB::transaction(function () use ($settlement, $owner) {
            $closing = $settlement->dayClosing()->with('business')->firstOrFail();
            $settlementDate = $settlement->settlement_date->toDateString();
            $wasCashPayment = $settlement->isCashPayment();
            $amount = (float) $settlement->amount;
            $typeLabel = $settlement->typeLabel();

            $settlement->update([
                'voided_at' => now(),
                'voided_by' => $owner->id,
            ]);

            if ($wasCashPayment) {
                $this->reportService->syncReport($closing->business, $settlementDate);
            }

            return [
                'amount' => $amount,
                'type_label' => $typeLabel,
                'settlement_date' => $settlementDate,
                'was_cash_payment' => $wasCashPayment,
                'new_balance' => $this->shortBalance($closing->fresh()),
            ];
        });
    }

    public function cashRecoveriesForDate(int $businessId, string $date): float
    {
        return (float) MoneyShortSettlement::where('business_id', $businessId)
            ->whereDate('settlement_date', $date)
            ->where('settlement_type', MoneyShortSettlement::TYPE_CASH_PAYMENT)
            ->active()
            ->sum('amount');
    }

    public function cashRecoveryBreakdownForDate(int $businessId, string $date): array
    {
        $settlements = MoneyShortSettlement::where('business_id', $businessId)
            ->whereDate('settlement_date', $date)
            ->where('settlement_type', MoneyShortSettlement::TYPE_CASH_PAYMENT)
            ->active()
            ->get();

        $breakdown = [];

        foreach ($settlements as $settlement) {
            $key = $this->platformKey($settlement);
            if (! isset($breakdown[$key])) {
                $breakdown[$key] = 0;
            }
            $breakdown[$key] += (float) $settlement->amount;
        }

        return $breakdown;
    }

    private function platformKey(MoneyShortSettlement $settlement): string
    {
        if ($settlement->payment_method === 'cash') {
            return 'cash';
        }

        $provider = strtolower(trim($settlement->payment_provider ?? ''));
        if ($provider !== '') {
            return str_replace([' ', '-', '.', '/'], '_', $provider);
        }

        return $settlement->payment_method === 'bank' ? 'bank_other' : 'mobile_other';
    }
}
