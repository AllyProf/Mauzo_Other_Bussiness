<?php

namespace App\Services;

use App\Models\Business;
use App\Models\DayClosing;
use App\Models\SalePayment;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Owner report: how each collector (payment cashiers first, then sales officers who collect) performed.
 */
class CashierPerformanceService
{
    /**
     * @return array<string, mixed>
     */
    public function report(Business $business, string $from, string $to, ?int $branchId = null, bool $cashiersOnly = false): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $payments = SalePayment::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereHas('sale', fn ($q) => $q->where('business_id', $business->id)->where('payment_status', '!=', 'cancelled'))
            ->with(['sale:id,user_id,created_at,reference_no', 'user:id,name,role,branch_id,business_id'])
            ->get();

        if ($branchId) {
            $payments = $payments->filter(fn (SalePayment $p) => (int) ($p->user?->branch_id) === $branchId)->values();
        }

        $rows = $payments
            ->filter(fn (SalePayment $p) => $p->user)
            ->groupBy('user_id')
            ->map(fn (Collection $rows) => $this->collectorRow($rows->first()->user, $rows, $business->id, $start, $end))
            ->when($cashiersOnly, fn ($c) => $c->filter(fn ($row) => $row['is_cashier']))
            ->sortBy([['is_cashier', 'desc'], ['total', 'desc']])
            ->values();

        $cashierRows = $rows->where('is_cashier', true);
        $waits = $cashierRows->pluck('avg_wait_minutes')->filter(fn ($v) => $v !== null);

        return [
            'period' => ['from' => $start->toDateString(), 'to' => $end->toDateString()],
            'summary' => [
                'collectors' => $rows->count(),
                'cashiers' => $cashierRows->count(),
                'total_collected' => (float) $rows->sum('total'),
                'cashier_collected' => (float) $cashierRows->sum('total'),
                'cashier_share_percent' => $rows->sum('total') > 0
                    ? round($cashierRows->sum('total') / $rows->sum('total') * 100, 1)
                    : 0.0,
                'payments_count' => (int) $rows->sum('payments_count'),
                'avg_wait_minutes' => $waits->isNotEmpty() ? round($waits->avg(), 1) : null,
                'total_short' => (float) $rows->sum('money_short'),
                'total_over' => (float) $rows->sum('money_over'),
            ],
            'rows' => $rows->all(),
        ];
    }

    /**
     * @param  Collection<int, SalePayment>  $payments
     * @return array<string, mixed>
     */
    private function collectorRow(User $user, Collection $payments, int $businessId, Carbon $start, Carbon $end): array
    {
        $counterPayments = $payments->filter(fn (SalePayment $p) => $p->sale && (int) $p->sale->user_id !== (int) $user->id);

        $firstCounterPayments = $counterPayments->groupBy('sale_id')->map(fn ($rows) => $rows->sortBy('id')->first());
        $waits = $firstCounterPayments
            ->map(fn (SalePayment $p) => $p->sale?->created_at ? max(0, $p->sale->created_at->diffInMinutes($p->created_at)) : null)
            ->filter(fn ($v) => $v !== null);

        $cash = (float) $payments->where('payment_method', 'cash')->sum('amount');

        $shiftIds = Shift::query()
            ->where('business_id', $businessId)
            ->where('user_id', $user->id)
            ->whereBetween('opened_at', [$start, $end])
            ->pluck('id');

        $closings = DayClosing::query()
            ->where('business_id', $businessId)
            ->where('user_id', $user->id)
            ->whereBetween('closing_date', [$start->toDateString(), $end->toDateString()])
            ->get(['id', 'expected_handover', 'actual_received', 'money_short', 'status']);

        $over = $closings->sum(fn (DayClosing $c) => max(0, (float) $c->actual_received - (float) $c->expected_handover));

        $byMethod = $payments
            ->groupBy(fn (SalePayment $p) => $p->payment_method === 'cash' ? 'Cash' : ($p->payment_provider ?: ucfirst(str_replace('_', ' ', $p->payment_method))))
            ->map(fn ($rows, $label) => ['label' => $label, 'amount' => (float) $rows->sum('amount'), 'count' => $rows->count()])
            ->sortByDesc('amount')
            ->values()
            ->all();

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'is_cashier' => $user->isPaymentCashier(),
            'role_label' => $user->isPaymentCashier() ? 'Cashier' : 'Sales officer',
            'branch' => $user->branch?->name,
            'payments_count' => $payments->count(),
            'orders_count' => $payments->pluck('sale_id')->unique()->count(),
            'counter_orders' => $firstCounterPayments->count(),
            'total' => (float) $payments->sum('amount'),
            'cash' => $cash,
            'non_cash' => (float) $payments->sum('amount') - $cash,
            'avg_payment' => $payments->count() ? round((float) $payments->sum('amount') / $payments->count(), 2) : 0.0,
            'avg_wait_minutes' => $waits->isNotEmpty() ? round($waits->avg(), 1) : null,
            'max_wait_minutes' => $waits->isNotEmpty() ? (int) $waits->max() : null,
            'shifts' => $shiftIds->count(),
            'handovers' => $closings->count(),
            'money_short' => (float) $closings->sum('money_short'),
            'money_over' => (float) $over,
            'last_payment_at' => $payments->max('created_at')?->toIso8601String(),
            'by_method' => $byMethod,
        ];
    }
}
