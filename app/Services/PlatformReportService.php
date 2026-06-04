<?php

namespace App\Services;

use App\Models\Business;
use App\Models\PlatformBillingInvoice;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlatformReportService
{
    public function dashboard(int $months = 6): array
    {
        $months = max(3, min(12, $months));
        $start = now()->copy()->subMonths($months - 1)->startOfMonth();

        return [
            'months' => $months,
            'summary' => $this->summary(),
            'monthlyRevenue' => $this->monthlyRevenue($start, $months),
            'businessStatus' => $this->businessStatusBreakdown(),
            'registrationsTrend' => $this->registrationsTrend($start, $months),
            'invoiceStatus' => $this->invoiceStatusBreakdown(),
            'topBusinesses' => $this->topPayingBusinesses(8),
            'planDistribution' => $this->planDistribution(),
            'supportTickets' => $this->supportTicketBreakdown(),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function summary(): array
    {
        $paidTotal = (float) PlatformBillingInvoice::query()
            ->where('status', PlatformBillingInvoice::STATUS_PAID)
            ->sum('amount');

        $outstanding = (float) PlatformBillingInvoice::query()
            ->whereIn('status', [PlatformBillingInvoice::STATUS_PENDING, PlatformBillingInvoice::STATUS_NOTIFIED])
            ->sum('amount');

        $thisMonthPaid = (float) PlatformBillingInvoice::query()
            ->where('status', PlatformBillingInvoice::STATUS_PAID)
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        return [
            'total_businesses' => Business::count(),
            'active_businesses' => Business::where('is_active', true)->where('pending_approval', false)->count(),
            'pending_registrations' => Business::where('pending_approval', true)->count(),
            'total_collected' => $paidTotal,
            'outstanding' => $outstanding,
            'collected_this_month' => $thisMonthPaid,
            'open_tickets' => Ticket::where('status', 'open')->count(),
        ];
    }

    /**
     * @return array<int, array{label: string, month: string, invoiced: float, paid: float, outstanding: float}>
     */
    private function monthlyRevenue(Carbon $start, int $months): array
    {
        $rows = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $monthKey = $month->format('Y-m');

            $invoiced = (float) PlatformBillingInvoice::query()
                ->whereDate('billing_month', $month->copy()->startOfMonth()->toDateString())
                ->sum('amount');

            $paid = (float) PlatformBillingInvoice::query()
                ->where('status', PlatformBillingInvoice::STATUS_PAID)
                ->whereDate('billing_month', $month->copy()->startOfMonth()->toDateString())
                ->sum('amount');

            $outstanding = (float) PlatformBillingInvoice::query()
                ->whereIn('status', [PlatformBillingInvoice::STATUS_PENDING, PlatformBillingInvoice::STATUS_NOTIFIED])
                ->whereDate('billing_month', $month->copy()->startOfMonth()->toDateString())
                ->sum('amount');

            $rows[] = [
                'label' => $month->format('M Y'),
                'month' => $monthKey,
                'invoiced' => $invoiced,
                'paid' => $paid,
                'outstanding' => $outstanding,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function businessStatusBreakdown(): array
    {
        $now = now()->toDateString();

        $active = Business::query()
            ->where('is_active', true)
            ->where('pending_approval', false)
            ->where(function ($query) use ($now) {
                $query->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', $now);
            })
            ->count();

        $expired = Business::query()
            ->where('pending_approval', false)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', $now)
            ->count();

        $suspended = Business::query()
            ->where('is_active', false)
            ->where('pending_approval', false)
            ->count();

        $pending = Business::where('pending_approval', true)->count();

        return [
            ['label' => 'Active', 'count' => $active],
            ['label' => 'Expired', 'count' => $expired],
            ['label' => 'Suspended', 'count' => $suspended],
            ['label' => 'Pending Approval', 'count' => $pending],
        ];
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function registrationsTrend(Carbon $start, int $months): array
    {
        $rows = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);

            $count = Business::query()
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();

            $rows[] = [
                'label' => $month->format('M Y'),
                'count' => $count,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{label: string, count: int, amount: float}>
     */
    private function invoiceStatusBreakdown(): array
    {
        $statuses = [
            PlatformBillingInvoice::STATUS_PAID => 'Paid',
            PlatformBillingInvoice::STATUS_NOTIFIED => 'Invoice Sent',
            PlatformBillingInvoice::STATUS_PENDING => 'Pending',
        ];

        $rows = [];

        foreach ($statuses as $status => $label) {
            $query = PlatformBillingInvoice::query()->where('status', $status);
            $rows[] = [
                'label' => $label,
                'count' => (int) $query->count(),
                'amount' => (float) (clone $query)->sum('amount'),
            ];
        }

        return $rows;
    }

    /**
     * @return Collection<int, object{business_name: string, total_paid: float, invoice_count: int}>
     */
    private function topPayingBusinesses(int $limit = 8): Collection
    {
        return collect(
            PlatformBillingInvoice::query()
                ->join('businesses', 'platform_billing_invoices.business_id', '=', 'businesses.id')
                ->select(
                    'businesses.name as business_name',
                    DB::raw('SUM(platform_billing_invoices.amount) as total_paid'),
                    DB::raw('COUNT(*) as invoice_count')
                )
                ->where('platform_billing_invoices.status', PlatformBillingInvoice::STATUS_PAID)
                ->groupBy('businesses.id', 'businesses.name')
                ->orderByDesc('total_paid')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => [
                    'business_name' => $row->business_name,
                    'total_paid' => (float) $row->total_paid,
                    'invoice_count' => (int) $row->invoice_count,
                ])
                ->all()
        );
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function planDistribution(): array
    {
        $rows = Business::query()
            ->join('plans', 'businesses.plan_id', '=', 'plans.id')
            ->select('plans.name as label', DB::raw('COUNT(*) as count'))
            ->groupBy('plans.id', 'plans.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'count' => (int) $row->count,
            ])
            ->all();

        $withoutPlan = Business::whereNull('plan_id')->count();
        if ($withoutPlan > 0) {
            $rows[] = ['label' => 'No Plan', 'count' => $withoutPlan];
        }

        return $rows;
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function supportTicketBreakdown(): array
    {
        $statuses = ['open', 'pending', 'resolved', 'closed'];

        return collect($statuses)->map(function (string $status) {
            return [
                'label' => ucfirst($status),
                'count' => Ticket::where('status', $status)->count(),
            ];
        })->all();
    }
}
