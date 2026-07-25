<?php

namespace App\Services;

use App\Mail\SalesReportMail;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessOwnerExpense;
use App\Models\DayClosing;
use App\Models\Item;
use App\Models\OwnerDailyReport;
use App\Models\Receiving;
use App\Models\ReceivingItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\ShiftStockCheck;
use App\Models\User;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BusinessSalesReportEmailService
{
    private const MAX_RETRY_ATTEMPTS = 5;

    /**
     * @return array{daily: int, weekly: int, monthly: int, skipped: int, failed: int, retried: int}
     */
    public function sendDueScheduledReports(): array
    {
        $counts = [
            'daily' => 0,
            'weekly' => 0,
            'monthly' => 0,
            'skipped' => 0,
            'failed' => 0,
            'retried' => 0,
        ];

        Business::query()
            ->where('is_active', true)
            ->where('pending_approval', false)
            ->with(['plan', 'ownerUser'])
            ->orderBy('id')
            ->chunkById(50, function ($businesses) use (&$counts) {
                foreach ($businesses as $business) {
                    $settings = $business->automationSettings();

                    if (! $this->reportsEnabled($settings)) {
                        $counts['skipped']++;
                        continue;
                    }

                    $recipients = $this->recipientEmails($settings, $business);
                    if ($recipients === []) {
                        $counts['skipped']++;
                        continue;
                    }

                    $retried = $this->processRetryQueue($business, $recipients);
                    $counts['retried'] += $retried;

                    if (! $this->isPastSendTime($settings)) {
                        $counts['skipped']++;
                        continue;
                    }

                    if ((bool) ($settings['email_sales_report_daily'] ?? false)
                        && $this->shouldSendPeriod($settings, 'email_sales_report_daily_last_sent')) {
                        $result = $this->sendPeriodReport($business, 'daily', $recipients);
                        if ($result === 'sent') {
                            $counts['daily']++;
                        } elseif ($result === 'skipped') {
                            $counts['skipped']++;
                        } else {
                            $counts['failed']++;
                        }
                    }

                    if ((bool) ($settings['email_sales_report_weekly'] ?? false)
                        && $this->shouldSendWeekly($settings)) {
                        $result = $this->sendPeriodReport($business, 'weekly', $recipients);
                        if ($result === 'sent') {
                            $counts['weekly']++;
                        } elseif ($result === 'skipped') {
                            $counts['skipped']++;
                        } else {
                            $counts['failed']++;
                        }
                    }

                    if ((bool) ($settings['email_sales_report_monthly'] ?? false)
                        && $this->shouldSendMonthly($settings)) {
                        $result = $this->sendPeriodReport($business, 'monthly', $recipients);
                        if ($result === 'sent') {
                            $counts['monthly']++;
                        } elseif ($result === 'skipped') {
                            $counts['skipped']++;
                        } else {
                            $counts['failed']++;
                        }
                    }
                }
            });

        return $counts;
    }

    public function sendOnShiftClose(Business $business, User $submitter, DayClosing $closing): void
    {
        $settings = $business->automationSettings();

        if (! (bool) ($settings['email_sales_report_on_shift_close'] ?? false)) {
            return;
        }

        if (! $this->reportsEnabled($settings)) {
            return;
        }

        // Digest mode: skip per-shift emails when daily all-branch digest is enabled.
        if ((bool) ($settings['email_sales_report_manager_digest'] ?? true)
            && (bool) ($settings['email_sales_report_daily'] ?? false)) {
            return;
        }

        $recipients = $this->recipientEmails($settings, $business);
        if ($recipients === []) {
            return;
        }

        $report = $this->buildShiftReport($business, $submitter, $closing);

        if ((bool) ($settings['email_sales_report_skip_empty'] ?? true)
            && (int) ($report['stats']['orders'] ?? 0) === 0
            && (float) ($report['stats']['gross'] ?? 0) <= 0) {
            return;
        }

        $pdf = $this->renderPdf($report);
        $filename = 'sales-shift-'.$closing->closing_date->format('Ymd').'-'.$closing->id;

        $subject = $business->name.' — Shift Sales Summary — '.$closing->closing_date->format('d M Y');
        $body = sprintf(
            "Shift closed by %s on %s.\n\nOrders: %s\nGross sales: TZS %s\nCollected: TZS %s\nNet handover: TZS %s\n\nSee the attached PDF for the full sales list.",
            $submitter->name,
            $closing->closing_date->format('d M Y'),
            number_format($report['stats']['orders']),
            number_format($report['stats']['gross'], 0),
            number_format($report['stats']['collected'], 0),
            number_format($report['stats']['net_handover'] ?? 0, 0),
        );

        $this->dispatchEmails($business, $recipients, $subject, $body, $pdf, $filename);
    }

    /**
     * @param  list<string>  $recipients
     * @return 'sent'|'skipped'|'failed'
     */
    public function sendPeriodReport(Business $business, string $period, array $recipients, bool $fromRetry = false): string
    {
        [$from, $to, $periodLabel, $lastSentKey] = $this->periodWindow($period);

        $report = $this->buildPeriodReport($business, $from, $to, $periodLabel, ucfirst($period).' Sales Summary');

        $settings = $business->automationSettings();
        $skipEmpty = (bool) ($settings['email_sales_report_skip_empty'] ?? true);

        if ($skipEmpty
            && (int) ($report['stats']['orders'] ?? 0) === 0
            && (float) ($report['stats']['gross'] ?? 0) <= 0) {
            if (! $fromRetry) {
                $this->markSent($business, $lastSentKey, now()->toDateString());
            }
            $this->removeRetryQueueItem($business, $period, $from, $to);

            return 'skipped';
        }

        $pdf = $this->renderPdf($report);
        $filename = 'sales-'.$period.'-'.str_replace('-', '', $to);

        $subject = $business->name.' — '.$periodLabel;
        if ((bool) ($settings['email_sales_report_manager_digest'] ?? true)
            && ($report['branchRows'] ?? collect())->count() > 1) {
            $subject .= ' (All branches)';
        }

        $body = sprintf(
            "%s\n\nOrders: %s\nGross sales: TZS %s\nCollected: TZS %s\nOutstanding: TZS %s\n\nSee the attached PDF for the full sales list.",
            $periodLabel,
            number_format($report['stats']['orders']),
            number_format($report['stats']['gross'], 0),
            number_format($report['stats']['collected'], 0),
            number_format($report['stats']['outstanding'], 0),
        );

        $sent = $this->dispatchEmails($business, $recipients, $subject, $body, $pdf, $filename);

        if ($sent) {
            if (! $fromRetry) {
                $this->markSent($business, $lastSentKey, now()->toDateString());
            }
            $this->removeRetryQueueItem($business, $period, $from, $to);

            return 'sent';
        }

        $this->queueFailedSend($business, $period, $from, $to, $periodLabel);

        return 'failed';
    }

    /**
     * @return list<string>
     */
    public function parseRecipientEmails(?string $raw): array
    {
        if (! filled($raw)) {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $raw) ?: [];

        return collect($parts)
            ->map(fn ($email) => strtolower(trim($email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    public function recipientEmails(array $settings, Business $business): array
    {
        $configured = $this->parseRecipientEmails((string) ($settings['email_sales_report_recipients'] ?? ''));

        if ($configured !== []) {
            return $configured;
        }

        $fallback = [];
        $owner = $business->resolveOwner();
        if (filled($owner?->email)) {
            $fallback[] = strtolower($owner->email);
        } elseif (filled($business->email)) {
            $fallback[] = strtolower($business->email);
        }

        return array_values(array_unique($fallback));
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function isPastSendTime(array $settings): bool
    {
        $sendTime = (string) ($settings['email_sales_report_send_time'] ?? '18:00');

        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $sendTime, $matches)) {
            return true;
        }

        $scheduled = now()->copy()->setTime((int) $matches[1], (int) $matches[2], 0);

        return now()->greaterThanOrEqualTo($scheduled);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function reportsEnabled(array $settings): bool
    {
        return (bool) ($settings['email_sales_report_enabled'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function shouldSendPeriod(array $settings, string $lastSentKey): bool
    {
        return (string) ($settings[$lastSentKey] ?? '') !== now()->toDateString();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function shouldSendWeekly(array $settings): bool
    {
        $day = (int) ($settings['email_sales_report_weekly_day'] ?? 1);

        if ((int) now()->dayOfWeek !== $day) {
            return false;
        }

        return $this->shouldSendPeriod($settings, 'email_sales_report_weekly_last_sent');
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function shouldSendMonthly(array $settings): bool
    {
        $day = max(1, min(28, (int) ($settings['email_sales_report_monthly_day'] ?? 1)));

        if ((int) now()->day !== $day) {
            return false;
        }

        return $this->shouldSendPeriod($settings, 'email_sales_report_monthly_last_sent');
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function periodWindow(string $period): array
    {
        return match ($period) {
            'weekly' => [
                now()->subDay()->startOfDay()->subDays(6)->toDateString(),
                now()->subDay()->toDateString(),
                'Weekly Report '.now()->subDay()->subDays(6)->format('d M').' – '.now()->subDay()->format('d M Y'),
                'email_sales_report_weekly_last_sent',
            ],
            'monthly' => [
                now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
                'Monthly Report '.now()->subMonthNoOverflow()->format('M Y'),
                'email_sales_report_monthly_last_sent',
            ],
            default => [
                now()->subDay()->toDateString(),
                now()->subDay()->toDateString(),
                'Daily Report '.now()->subDay()->format('d M Y'),
                'email_sales_report_daily_last_sent',
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildShiftReport(Business $business, User $submitter, DayClosing $closing): array
    {
        $closing->loadMissing(['shift', 'user']);

        $salesQuery = Sale::query()
            ->where('business_id', $business->id)
            ->where('payment_status', '!=', 'cancelled')
            ->with(['user.branch', 'customer']);

        if ($closing->shift_id) {
            $salesQuery->where('shift_id', $closing->shift_id);
        } else {
            $salesQuery->whereDate('sale_date', $closing->closing_date->toDateString());
        }

        $sales = $salesQuery->orderByDesc('total_amount')->orderBy('id')->get();
        $finance = app(OwnerDailyReportService::class)->buildShiftHandoverReviewData($closing);
        $stats = $this->baseStatsFromSales($sales);
        $stats['expenses'] = (float) ($closing->total_expenses ?? 0);
        $stats['profit'] = (float) ($finance['net_profit'] ?? $finance['gross_profit'] ?? 0);
        $stats['opening_circulation'] = (float) ($finance['opening_circulation'] ?? 0);
        $stats['circulation_returned'] = (float) ($finance['circulation_refill'] ?? $closing->net_amount ?? 0);
        $stats['closing_circulation'] = (float) ($finance['closing_circulation'] ?? $stats['circulation_returned']);
        $stats['net_handover'] = (float) ($closing->net_amount ?? 0);

        $date = $closing->closing_date->toDateString();
        $extras = $this->buildReportExtras($business, $sales, $date, $date, 'daily');

        $branchRows = $this->branchBreakdown($sales);
        $comments = $this->buildComments($stats, $branchRows, 'shift', $extras);

        return [
            'business' => $business,
            'reportTitle' => 'Sales Summary Report',
            'periodLabel' => 'Shift Closing — '.$closing->closing_date->format('d M Y'),
            'generatedBy' => $submitter->name,
            'generatedAt' => now(),
            'refCode' => 'SLS-'.$closing->id.'-'.$closing->closing_date->format('Ymd'),
            'submitterName' => $submitter->name,
            'shiftLabel' => $closing->shift_id ? 'Shift #'.$closing->shift_id : 'Owner direct close',
            'salesRows' => $this->mapSalesRows($sales),
            'branchRows' => $branchRows,
            'stats' => $stats,
            'comments' => $comments,
            ...$extras,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPeriodReport(
        Business $business,
        string $from,
        string $to,
        string $periodLabel,
        string $reportTitle,
    ): array {
        $sales = Sale::query()
            ->where('business_id', $business->id)
            ->whereBetween('sale_date', [$from, $to])
            ->where('payment_status', '!=', 'cancelled')
            ->with(['user.branch', 'customer'])
            ->orderByDesc('total_amount')
            ->orderBy('id')
            ->get();

        $stats = $this->baseStatsFromSales($sales);
        $stats['outstanding'] = (float) Sale::query()
            ->where('business_id', $business->id)
            ->whereNotIn('payment_status', ['paid', 'cancelled'])
            ->whereColumn('total_amount', '>', 'amount_paid')
            ->get(['total_amount', 'amount_paid'])
            ->sum(fn (Sale $s) => max(0, (float) $s->total_amount - (float) $s->amount_paid));

        $dailyReports = OwnerDailyReport::query()
            ->where('business_id', $business->id)
            ->whereBetween('report_date', [$from, $to])
            ->get();

        if ($dailyReports->isNotEmpty()) {
            $stats['profit'] = (float) $dailyReports->sum('net_profit');
            $first = $dailyReports->sortBy('report_date')->first();
            $last = $dailyReports->sortByDesc('report_date')->first();
            $stats['opening_circulation'] = (float) ($first->opening_circulation ?? 0);
            $stats['closing_circulation'] = (float) ($last->closing_circulation ?? $business->circulation_balance);
            $stats['circulation_returned'] = max(0, $stats['closing_circulation'] - $stats['opening_circulation']);
        } else {
            $profit = $this->estimateProfitFromSales($sales);
            $stats['profit'] = $profit;
            $stats['opening_circulation'] = (float) $business->circulation_balance;
            $stats['circulation_returned'] = (float) $stats['collected'];
            $stats['closing_circulation'] = (float) $business->circulation_balance;
        }

        $periodType = $from === $to ? 'daily' : (Carbon::parse($from)->diffInDays(Carbon::parse($to)) >= 27 ? 'monthly' : 'weekly');
        $extras = $this->buildReportExtras($business, $sales, $from, $to, $periodType);

        $staffExpenses = (float) ($extras['expenses']['staff_total'] ?? 0);
        $ownerExpenses = (float) ($extras['expenses']['owner_total'] ?? 0);
        $stats['expenses'] = $staffExpenses + $ownerExpenses;

        $branchRows = $this->branchBreakdown($sales);
        $comments = $this->buildComments($stats, $branchRows, 'period', $extras);

        return [
            'business' => $business,
            'reportTitle' => $reportTitle,
            'periodLabel' => $periodLabel,
            'generatedBy' => 'Mauzo Link',
            'generatedAt' => now(),
            'refCode' => 'SR-'.strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $business->name) ?: 'BIZ', 0, 3)).'-'.str_replace('-', '', $to),
            'submitterName' => null,
            'shiftLabel' => null,
            'salesRows' => $this->mapSalesRows($sales),
            'branchRows' => $branchRows,
            'stats' => $stats,
            'comments' => $comments,
            ...$extras,
        ];
    }

    /**
     * @return array{
     *   paymentBreakdown: Collection,
     *   topProducts: Collection,
     *   expenses: array{staff_total: float, owner_total: float, total: float, staff_rows: Collection, owner_rows: Collection},
     *   comparison: array{label: string, previous_gross: float, previous_orders: int, previous_collected: float, gross_change_pct: float|null, orders_change_pct: float|null, collected_change_pct: float|null},
     *   lowStock: Collection,
     *   shortages: Collection,
     *   receivedItems: array{receipts: int, lines: int, total_amount: float, rows: Collection},
     *   stockValue: array{item_count: int, pieces: float, selling_value: float, cost_value: float}
     * }
     */
    private function buildReportExtras(
        Business $business,
        Collection $sales,
        string $from,
        string $to,
        string $periodType,
    ): array {
        return [
            'paymentBreakdown' => $this->paymentBreakdown($business, $sales),
            'topProducts' => $this->topProducts($sales),
            'expenses' => $this->expensesForPeriod($business, $from, $to),
            'comparison' => $this->comparePreviousPeriod($business, $from, $to, $periodType, $sales),
            'lowStock' => $this->lowStockItems($business),
            'shortages' => $this->pendingShortages($business),
            'receivedItems' => $this->receivedItemsForPeriod($business, $from, $to),
            'stockValue' => $this->overallStockValue($business),
        ];
    }

    /**
     * @return Collection<int, array{key: string, label: string, amount: float}>
     */
    private function paymentBreakdown(Business $business, Collection $sales): Collection
    {
        $saleIds = $sales->pluck('id')->filter()->values();
        if ($saleIds->isEmpty()) {
            return collect();
        }

        $payments = SalePayment::query()
            ->whereIn('sale_id', $saleIds)
            ->get();

        $total = max(0.01, (float) $payments->sum('amount'));

        return $payments->groupBy(fn (SalePayment $p) => (string) ($p->payment_method ?: 'other'))
            ->map(function (Collection $group, $method) use ($business, $total) {
                $amount = (float) $group->sum('amount');

                return [
                    'key' => (string) $method,
                    'label' => $business->paymentMethodLabel((string) $method) ?: ucfirst((string) $method),
                    'amount' => $amount,
                    'pct' => (int) round(($amount / $total) * 100),
                ];
            })
            ->sortByDesc('amount')
            ->values();
    }

    /**
     * @return Collection<int, array{name: string, qty: float, revenue: float}>
     */
    private function topProducts(Collection $sales, int $limit = 3): Collection
    {
        $saleIds = $sales->pluck('id')->filter()->values();
        if ($saleIds->isEmpty()) {
            return collect();
        }

        $lines = SaleItem::query()
            ->whereIn('sale_id', $saleIds)
            ->with(['item', 'service'])
            ->get();

        return $lines->groupBy(function (SaleItem $line) {
            if ($line->service_id) {
                return 'svc:'.$line->service_id;
            }

            return 'item:'.($line->item_id ?: 'x');
        })->map(function (Collection $group) {
            $first = $group->first();
            $name = $first->service_id
                ? ($first->line_description ?: $first->service?->name ?: 'Service')
                : ($first->item?->name ?? $first->line_description ?: 'Item');

            return [
                'name' => $name,
                'qty' => (float) $group->sum('quantity'),
                'revenue' => (float) $group->sum('subtotal'),
            ];
        })->sortByDesc('revenue')->take($limit)->values();
    }

    /**
     * @return array{staff_total: float, owner_total: float, total: float, staff_rows: Collection, owner_rows: Collection}
     */
    private function expensesForPeriod(Business $business, string $from, string $to): array
    {
        $closings = DayClosing::query()
            ->where('business_id', $business->id)
            ->whereBetween('closing_date', [$from, $to])
            ->with(['expenses', 'user'])
            ->get();

        $staffRows = $closings->flatMap(function (DayClosing $closing) {
            if ($closing->expenses->isNotEmpty()) {
                return $closing->expenses->map(fn ($e) => [
                    'date' => $closing->closing_date?->format('d M Y') ?? '—',
                    'by' => $closing->user?->name ?? 'Staff',
                    'description' => $e->description ?: 'Expense',
                    'amount' => (float) $e->amount,
                    'type' => 'Staff',
                ]);
            }

            $amount = (float) ($closing->total_expenses ?? 0);
            if ($amount <= 0) {
                return [];
            }

            return [[
                'date' => $closing->closing_date?->format('d M Y') ?? '—',
                'by' => $closing->user?->name ?? 'Staff',
                'description' => 'Shift expenses',
                'amount' => $amount,
                'type' => 'Staff',
            ]];
        })->values();

        $ownerExpenses = BusinessOwnerExpense::query()
            ->where('business_id', $business->id)
            ->whereBetween('expense_date', [$from, $to])
            ->orderByDesc('expense_date')
            ->limit(20)
            ->get();

        $ownerRows = $ownerExpenses->map(fn (BusinessOwnerExpense $e) => [
            'date' => $e->expense_date?->format('d M Y') ?? '—',
            'by' => 'Owner',
            'description' => $e->description ?: ($e->categoryLabel()),
            'amount' => (float) $e->amount,
            'type' => 'Owner',
        ])->values();

        $staffTotal = (float) $closings->sum(fn (DayClosing $c) => (float) ($c->total_expenses ?? 0));
        $ownerTotal = (float) $ownerExpenses->sum('amount');

        return [
            'staff_total' => $staffTotal,
            'owner_total' => $ownerTotal,
            'total' => $staffTotal + $ownerTotal,
            'staff_rows' => $staffRows->take(12),
            'owner_rows' => $ownerRows->take(12),
        ];
    }

    /**
     * @return array{label: string, previous_gross: float, previous_orders: int, previous_collected: float, gross_change_pct: float|null, orders_change_pct: float|null, collected_change_pct: float|null}
     */
    private function comparePreviousPeriod(
        Business $business,
        string $from,
        string $to,
        string $periodType,
        Collection $currentSales,
    ): array {
        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->startOfDay();
        $days = max(1, $fromDate->diffInDays($toDate) + 1);

        if ($periodType === 'monthly') {
            $prevFrom = $fromDate->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
            $prevTo = $fromDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
            $label = 'vs previous month';
        } else {
            $prevTo = $fromDate->copy()->subDay()->toDateString();
            $prevFrom = $fromDate->copy()->subDays($days)->toDateString();
            $label = $days === 1 ? 'vs previous day' : 'vs previous '.$days.' days';
        }

        $prevSales = Sale::query()
            ->where('business_id', $business->id)
            ->whereBetween('sale_date', [$prevFrom, $prevTo])
            ->where('payment_status', '!=', 'cancelled')
            ->get(['total_amount', 'amount_paid']);

        $prevGross = (float) $prevSales->sum('total_amount');
        $prevOrders = $prevSales->count();
        $prevCollected = (float) $prevSales->sum('amount_paid');

        $gross = (float) $currentSales->sum('total_amount');
        $orders = $currentSales->count();
        $collected = (float) $currentSales->sum('amount_paid');

        return [
            'label' => $label,
            'previous_from' => $prevFrom,
            'previous_to' => $prevTo,
            'previous_gross' => $prevGross,
            'previous_orders' => $prevOrders,
            'previous_collected' => $prevCollected,
            'gross_change_pct' => $this->pctChange($gross, $prevGross),
            'orders_change_pct' => $this->pctChange((float) $orders, (float) $prevOrders),
            'collected_change_pct' => $this->pctChange($collected, $prevCollected),
        ];
    }

    private function pctChange(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @return Collection<int, array{name: string, stock: float}>
     */
    private function lowStockItems(Business $business, int $limit = 8): Collection
    {
        $threshold = (int) ($business->automationSettings()['low_stock_threshold'] ?? 5);

        return Item::query()
            ->where('business_id', $business->id)
            ->where('current_stock', '>', 0)
            ->where('current_stock', '<=', $threshold)
            ->orderBy('current_stock')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'current_stock'])
            ->map(fn (Item $item) => [
                'name' => $item->name,
                'stock' => (float) $item->current_stock,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{item: string, qty: float, staff: string}>
     */
    private function pendingShortages(Business $business, int $limit = 8): Collection
    {
        return ShiftStockCheck::query()
            ->whereHas('shift', fn ($q) => $q->where('business_id', $business->id))
            ->shortages()
            ->pendingVerification()
            ->with(['item', 'recorder'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (ShiftStockCheck $row) => [
                'item' => $row->item?->name ?? 'Item',
                'qty' => abs((float) $row->variance),
                'staff' => $row->recorder?->name ?? '—',
            ])
            ->values();
    }

    /**
     * @return array{receipts: int, lines: int, total_amount: float, rows: Collection<int, array{date: string, reference: string, supplier: string, item: string, qty_label: string, amount: float}>}
     */
    private function receivedItemsForPeriod(Business $business, string $from, string $to, int $limit = 20): array
    {
        $receivings = Receiving::query()
            ->where('business_id', $business->id)
            ->whereBetween('received_date', [$from, $to])
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'cancelled');
            })
            ->with(['items.item.receivingPackaging', 'supplier'])
            ->orderByDesc('received_date')
            ->orderByDesc('id')
            ->get();

        $rows = collect();
        foreach ($receivings as $receiving) {
            foreach ($receiving->items as $line) {
                /** @var ReceivingItem $line */
                $amount = max(0, ((float) $line->cost_price * (float) $line->quantity) - (float) ($line->discount_amount ?? 0));
                $rows->push([
                    'date' => Carbon::parse($receiving->received_date)->format('d M Y'),
                    'reference' => (string) ($receiving->reference_no ?: '#'.$receiving->id),
                    'supplier' => $receiving->supplier?->name ?? '—',
                    'item' => $line->item?->name ?? 'Item',
                    'qty_label' => $line->receivedQuantityLabel($line->item),
                    'amount' => $amount,
                ]);
            }
        }

        return [
            'receipts' => $receivings->count(),
            'lines' => $rows->count(),
            'total_amount' => (float) $receivings->sum('total_amount'),
            'rows' => $rows->take($limit)->values(),
        ];
    }

    /**
     * @return array{item_count: int, pieces: float, selling_value: float, cost_value: float}
     */
    private function overallStockValue(Business $business): array
    {
        $items = Item::query()
            ->where('business_id', $business->id)
            ->where('current_stock', '>', 0)
            ->with(['packagings'])
            ->get(['id', 'current_stock']);

        $sellingValue = 0.0;
        $costValue = 0.0;
        $piecesTotal = 0.0;

        foreach ($items as $item) {
            $pieces = (float) $item->current_stock;
            $piecesTotal += $pieces;
            $pkg = $item->packagings->sortBy('quantity_per_unit')->first()
                ?? $item->packagings->first();
            $qpu = max(1, (int) ($pkg?->quantity_per_unit ?? 1));
            $sellingValue += $pieces * ((float) ($pkg?->selling_price ?? 0) / $qpu);
            $costValue += $pieces * ((float) ($pkg?->cost_price ?? 0) / $qpu);
        }

        return [
            'item_count' => $items->count(),
            'pieces' => $piecesTotal,
            'selling_value' => round($sellingValue, 2),
            'cost_value' => round($costValue, 2),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function baseStatsFromSales(Collection $sales): array
    {
        $gross = (float) $sales->sum('total_amount');
        $collected = (float) $sales->sum('amount_paid');

        return [
            'orders' => $sales->count(),
            'gross' => $gross,
            'collected' => $collected,
            'outstanding' => (float) $sales->sum(fn (Sale $s) => max(0, (float) $s->total_amount - (float) $s->amount_paid)),
            'profit' => 0,
            'expenses' => 0,
            'opening_circulation' => 0,
            'circulation_returned' => 0,
            'closing_circulation' => 0,
        ];
    }

    private function estimateProfitFromSales(Collection $sales): float
    {
        $sales->loadMissing('items');
        $cogs = 0.0;
        foreach ($sales as $sale) {
            foreach ($sale->items as $line) {
                $cogs += (float) ($line->cost_price ?? 0) * (float) ($line->quantity ?? 0);
            }
        }

        return max(0, (float) $sales->sum('total_amount') - $cogs);
    }

    /**
     * @return Collection<int, array{name: string, orders: int, gross: float, collected: float}>
     */
    private function branchBreakdown(Collection $sales): Collection
    {
        $branchNames = Branch::query()
            ->whereIn('id', $sales->pluck('user.branch_id')->filter()->unique()->values())
            ->pluck('name', 'id');

        return $sales->groupBy(fn (Sale $sale) => (int) ($sale->user?->branch_id ?? 0))
            ->map(function (Collection $group, $branchId) use ($branchNames) {
                $branchId = (int) $branchId;

                return [
                    'name' => $branchId > 0
                        ? (string) ($branchNames[$branchId] ?? ('Branch #'.$branchId))
                        : 'Unassigned / HQ',
                    'orders' => $group->count(),
                    'gross' => (float) $group->sum('total_amount'),
                    'collected' => (float) $group->sum('amount_paid'),
                ];
            })
            ->sortByDesc('gross')
            ->values();
    }

    /**
     * @param  array<string, float|int>  $stats
     * @param  Collection<int, array{name: string, orders: int, gross: float, collected: float}>  $branches
     * @param  array<string, mixed>  $extras
     * @return list<string>
     */
    private function buildComments(array $stats, Collection $branches, string $context, array $extras = []): array
    {
        $comments = [];
        $gross = (float) ($stats['gross'] ?? 0);
        $collected = (float) ($stats['collected'] ?? 0);
        $outstanding = (float) ($stats['outstanding'] ?? 0);
        $profit = (float) ($stats['profit'] ?? 0);
        $orders = (int) ($stats['orders'] ?? 0);

        if ($orders === 0) {
            return ['No sales recorded for this period.'];
        }

        $collectPct = $gross > 0 ? round(($collected / $gross) * 100) : 0;
        $comments[] = "Collection rate is {$collectPct}% (TZS ".number_format($collected, 0).' collected from TZS '.number_format($gross, 0).' sales).';

        if ($profit > 0 && $gross > 0) {
            $margin = round(($profit / $gross) * 100);
            $comments[] = 'Estimated profit TZS '.number_format($profit, 0)." (~{$margin}% margin).";
        } elseif ($profit <= 0) {
            $comments[] = 'Profit is low or not yet finalized on the Master Sheet for this period.';
        }

        $comparison = $extras['comparison'] ?? null;
        if (is_array($comparison) && ($comparison['gross_change_pct'] ?? null) !== null) {
            $dir = $comparison['gross_change_pct'] >= 0 ? 'up' : 'down';
            $comments[] = 'Sales are '.$dir.' '.abs((float) $comparison['gross_change_pct']).'% '.$comparison['label']
                .' (previous TZS '.number_format((float) $comparison['previous_gross'], 0).').';
        }

        $expensesTotal = (float) (($extras['expenses']['total'] ?? null) ?? ($stats['expenses'] ?? 0));
        if ($expensesTotal > 0) {
            $comments[] = 'Total expenses TZS '.number_format($expensesTotal, 0)
                .' (staff '.number_format((float) ($extras['expenses']['staff_total'] ?? 0), 0)
                .', owner '.number_format((float) ($extras['expenses']['owner_total'] ?? 0), 0).').';
        }

        if ($outstanding > 0) {
            $comments[] = 'Outstanding customer debt stands at TZS '.number_format($outstanding, 0).'. Follow up on unpaid invoices.';
        } else {
            $comments[] = 'No outstanding debt in the current open balances — collections look clean.';
        }

        $returned = (float) ($stats['circulation_returned'] ?? 0);
        if ($returned > 0) {
            $comments[] = 'Circulation returned / refilled: TZS '.number_format($returned, 0)
                .' (opening TZS '.number_format((float) ($stats['opening_circulation'] ?? 0), 0)
                .', closing TZS '.number_format((float) ($stats['closing_circulation'] ?? 0), 0).').';
        }

        if ($branches->count() > 1) {
            $top = $branches->first();
            $share = $gross > 0 ? round((((float) $top['gross']) / $gross) * 100) : 0;
            $comments[] = $top['name']." led sales with {$share}% of total (TZS ".number_format((float) $top['gross'], 0).').';
            $bottom = $branches->last();
            if ($bottom && $bottom['name'] !== $top['name']) {
                $comments[] = $bottom['name'].' was the lowest contributor (TZS '.number_format((float) $bottom['gross'], 0).').';
            }
        } elseif ($context === 'shift') {
            $comments[] = 'This summary covers the closed shift only.';
        }

        $lowStock = $extras['lowStock'] ?? collect();
        $shortages = $extras['shortages'] ?? collect();
        if ($lowStock->isNotEmpty() || $shortages->isNotEmpty()) {
            $comments[] = 'Stock attention: '.$lowStock->count().' low-stock item(s), '.$shortages->count().' pending shortage(s).';
        }

        $avg = $orders > 0 ? $gross / $orders : 0;
        $comments[] = 'Average order value: TZS '.number_format($avg, 0).' across '.$orders.' order(s).';

        return $comments;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function mapSalesRows(Collection $sales): Collection
    {
        return $sales->map(function (Sale $sale) {
            return [
                'reference' => $sale->reference_no ?? ('#'.$sale->id),
                'date' => Carbon::parse($sale->sale_date)->format('d M Y'),
                'customer' => $sale->customer?->name
                    ?? $sale->customer_name
                    ?? 'Walk-in',
                'cashier' => $sale->user?->name ?? '—',
                'gross' => (float) $sale->total_amount,
                'paid' => (float) $sale->amount_paid,
                'status' => ucfirst((string) $sale->payment_status),
            ];
        })->values();
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function renderPdf(array $report): string
    {
        $html = view('reports.sales-summary-export-pdf', $report)->render();

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @param  list<string>  $recipients
     */
    private function dispatchEmails(
        Business $business,
        array $recipients,
        string $subject,
        string $body,
        string $pdf,
        string $filename,
    ): bool {
        $sentAny = false;

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new SalesReportMail(
                    $business,
                    $subject,
                    $body,
                    $email,
                    $pdf,
                    $filename,
                ));
                $sentAny = true;
            } catch (\Throwable $e) {
                Log::warning('Sales report email failed', [
                    'business_id' => $business->id,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sentAny;
    }

    private function markSent(Business $business, string $key, string $date): void
    {
        $business->refresh();
        $business->update([
            'automation_settings' => array_merge(
                $business->automation_settings ?? [],
                [$key => $date]
            ),
        ]);
    }

    /**
     * @param  list<string>  $recipients
     */
    private function processRetryQueue(Business $business, array $recipients): int
    {
        $business->refresh();
        $settings = $business->automation_settings ?? [];
        $queue = $settings['email_sales_report_retry_queue'] ?? [];

        if (! is_array($queue) || $queue === []) {
            return 0;
        }

        $retried = 0;
        $remaining = [];

        foreach ($queue as $item) {
            if (! is_array($item)) {
                continue;
            }

            $attempts = (int) ($item['attempts'] ?? 0);
            $nextAt = (string) ($item['next_retry_at'] ?? '');
            if ($nextAt !== '' && now()->lt(Carbon::parse($nextAt))) {
                $remaining[] = $item;
                continue;
            }

            if ($attempts >= self::MAX_RETRY_ATTEMPTS) {
                Log::warning('Sales report email retry abandoned', [
                    'business_id' => $business->id,
                    'period' => $item['period'] ?? null,
                    'from' => $item['from'] ?? null,
                    'to' => $item['to'] ?? null,
                ]);
                continue;
            }

            $period = (string) ($item['period'] ?? 'daily');
            $from = (string) ($item['from'] ?? '');
            $to = (string) ($item['to'] ?? '');
            $periodLabel = (string) ($item['period_label'] ?? ('Retry '.$period));

            if ($from === '' || $to === '') {
                continue;
            }

            $report = $this->buildPeriodReport($business, $from, $to, $periodLabel, ucfirst($period).' Sales Summary');
            $pdf = $this->renderPdf($report);
            $filename = 'sales-'.$period.'-retry-'.str_replace('-', '', $to);
            $subject = $business->name.' — '.$periodLabel.' (retry)';
            $body = sprintf(
                "%s\n\nOrders: %s\nGross sales: TZS %s\nCollected: TZS %s\n\nSee the attached PDF.",
                $periodLabel,
                number_format($report['stats']['orders']),
                number_format($report['stats']['gross'], 0),
                number_format($report['stats']['collected'], 0),
            );

            $ok = $this->dispatchEmails($business, $recipients, $subject, $body, $pdf, $filename);
            $retried++;

            if ($ok) {
                continue;
            }

            $attempts++;
            $remaining[] = [
                'period' => $period,
                'from' => $from,
                'to' => $to,
                'period_label' => $periodLabel,
                'attempts' => $attempts,
                'next_retry_at' => now()->addMinutes(min(120, 15 * $attempts))->toDateTimeString(),
                'last_error_at' => now()->toDateTimeString(),
            ];
        }

        $business->refresh();
        $business->update([
            'automation_settings' => array_merge(
                $business->automation_settings ?? [],
                ['email_sales_report_retry_queue' => array_values($remaining)]
            ),
        ]);

        return $retried;
    }

    private function queueFailedSend(
        Business $business,
        string $period,
        string $from,
        string $to,
        string $periodLabel,
    ): void {
        $business->refresh();
        $settings = $business->automation_settings ?? [];
        $queue = is_array($settings['email_sales_report_retry_queue'] ?? null)
            ? $settings['email_sales_report_retry_queue']
            : [];

        $found = false;
        foreach ($queue as &$item) {
            if (($item['period'] ?? null) === $period
                && ($item['from'] ?? null) === $from
                && ($item['to'] ?? null) === $to) {
                $item['attempts'] = (int) ($item['attempts'] ?? 0) + 1;
                $item['next_retry_at'] = now()->addMinutes(15)->toDateTimeString();
                $item['last_error_at'] = now()->toDateTimeString();
                $found = true;
                break;
            }
        }
        unset($item);

        if (! $found) {
            $queue[] = [
                'period' => $period,
                'from' => $from,
                'to' => $to,
                'period_label' => $periodLabel,
                'attempts' => 1,
                'next_retry_at' => now()->addMinutes(15)->toDateTimeString(),
                'last_error_at' => now()->toDateTimeString(),
            ];
        }

        $business->update([
            'automation_settings' => array_merge($settings, [
                'email_sales_report_retry_queue' => array_values($queue),
            ]),
        ]);
    }

    private function removeRetryQueueItem(Business $business, string $period, string $from, string $to): void
    {
        $business->refresh();
        $settings = $business->automation_settings ?? [];
        $queue = is_array($settings['email_sales_report_retry_queue'] ?? null)
            ? $settings['email_sales_report_retry_queue']
            : [];

        if ($queue === []) {
            return;
        }

        $filtered = array_values(array_filter($queue, function ($item) use ($period, $from, $to) {
            return ! (
                ($item['period'] ?? null) === $period
                && ($item['from'] ?? null) === $from
                && ($item['to'] ?? null) === $to
            );
        }));

        if (count($filtered) === count($queue)) {
            return;
        }

        $business->update([
            'automation_settings' => array_merge($settings, [
                'email_sales_report_retry_queue' => $filtered,
            ]),
        ]);
    }
}
