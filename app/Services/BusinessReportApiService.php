<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessOwnerExpense;
use App\Models\DayClosingExpense;
use App\Models\Sale;
use App\Models\User;
use App\Services\Api\ApiTenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class BusinessReportApiService
{
    public function __construct(private BusinessReportService $reports)
    {
    }

    /**
     * @return list<array{key: string, label: string, path: string}>
     */
    public function availableReports(): array
    {
        return [
            ['key' => 'circulation-profit', 'label' => 'Circulation vs Profit', 'path' => '/reports/circulation-profit'],
            ['key' => 'daily-sales', 'label' => 'Daily Sales', 'path' => '/reports/daily-sales'],
            ['key' => 'expenses', 'label' => 'Expense Report', 'path' => '/reports/expenses'],
            ['key' => 'profit', 'label' => 'Profit Report', 'path' => '/reports/profit'],
            ['key' => 'sales-analytics', 'label' => 'Sales Analytics', 'path' => '/reports/sales-analytics'],
            ['key' => 'products', 'label' => 'Product Report', 'path' => '/reports/products'],
            ['key' => 'debts', 'label' => 'Debt Report', 'path' => '/reports/debts'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(
        User $user,
        Business $business,
        ?int $branchFilterId,
        Request $request,
        string $reportKey
    ): array {
        return $this->withWebContext($user, (int) $business->id, $branchFilterId, function () use ($user, $business, $branchFilterId, $request, $reportKey) {
            $range = $this->reports->parseDateRange($request);
            $filter = $this->filterMeta($user, $business, $branchFilterId, $request);
            $businessTypeKey = $this->reports->resolveBusinessTypeFilter($request, $business, $filter['business_types']);

            $raw = match ($reportKey) {
                'circulation-profit' => $this->reports->circulationProfitReport($business, $range['from'], $range['to'], $businessTypeKey),
                'daily-sales' => $this->reports->dailySalesReport($business, $range['from'], $range['to'], $businessTypeKey),
                'expenses' => $this->formatExpensesReport(
                    $this->reports->expensesReport($business, $range['from'], $range['to'], $businessTypeKey)
                ),
                'profit' => $this->reports->profitReport($business, $range['from'], $range['to'], $businessTypeKey),
                'sales-analytics' => $this->normalizeCollections(
                    $this->reports->salesAnalyticsReport($business, $range['from'], $range['to'], $businessTypeKey)
                ),
                'products' => $this->normalizeCollections(
                    $this->reports->productsReport($business, $range['from'], $range['to'], $businessTypeKey)
                ),
                'debts' => $this->formatDebtsReport(
                    $this->reports->debtsReport($business, $range['from'], $range['to'], $businessTypeKey)
                ),
                default => throw new \InvalidArgumentException("Unknown report: {$reportKey}"),
            };

            $raw = $this->normalizeCollections($raw);

            return [
                'report' => $reportKey,
                'title' => collect($this->availableReports())->firstWhere('key', $reportKey)['label'] ?? $reportKey,
                'date_range' => [
                    'start_date' => $range['from'],
                    'end_date' => $range['to'],
                    'start_date_label' => $range['from_c']->format('d M, Y'),
                    'end_date_label' => $range['to_c']->format('d M, Y'),
                    'days' => $range['from_c']->diffInDays($range['to_c']) + 1,
                ],
                'filters' => $filter + [
                    'active_business_type' => $businessTypeKey,
                    'active_business_label' => $businessTypeKey
                        ? (collect($filter['business_types'])->firstWhere('key', $businessTypeKey)['label']
                            ?? $business->businessTypeLabel($businessTypeKey))
                        : null,
                ],
                'data' => $raw,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function indexMeta(User $user, Business $business, ?int $branchFilterId, Request $request): array
    {
        $range = $this->reports->parseDateRange($request);
        $filter = $this->filterMeta($user, $business, $branchFilterId, $request);

        return [
            'reports' => $this->availableReports(),
            'date_range' => [
                'start_date' => $range['from'],
                'end_date' => $range['to'],
                'default_days' => 7,
                'min_days' => 5,
                'max_days' => 62,
            ],
            'filters' => $filter,
        ];
    }

    public function branchFilterId(User $user, ApiTenantContext $ctx, ?Request $request = null): ?int
    {
        if (! $user->seesBusinessWideData() && $user->branch_id) {
            return (int) $user->branch_id;
        }

        if ($request?->filled('branch_id')) {
            $requested = (int) $request->input('branch_id');
            if ($requested > 0 && $ctx->ownerBranches()->contains('id', $requested)) {
                return $requested;
            }
        }

        return $ctx->branchId();
    }

    /**
     * @return array<string, mixed>
     */
    private function filterMeta(User $user, Business $business, ?int $branchFilterId, Request $request): array
    {
        $templates = config('category_templates', []);

        if ($branchFilterId) {
            $businessTypes = collect($business->branchPosBusinessTypesMeta($branchFilterId))
                ->map(function ($type) use ($templates) {
                    $key = (string) ($type['key'] ?? '');

                    return [
                        'key' => $key,
                        'label' => (string) ($type['label'] ?? $key),
                        'icon' => $type['icon'] ?? ($templates[$key]['icon'] ?? (str_starts_with($key, 'custom:') ? 'fa-pencil' : 'fa-store')),
                    ];
                })
                ->values()
                ->all();
        } else {
            $businessTypes = $business->posBusinessTypesMeta();
        }

        return [
            'branch_id' => $branchFilterId,
            'branch_name' => $branchFilterId
                ? (Branch::find($branchFilterId)?->name ?? $user->branch?->name ?? 'Branch')
                : null,
            'viewing_all_branches' => $user->seesBusinessWideData() && ! $branchFilterId,
            'business_types' => $businessTypes,
            'multi_business' => count($businessTypes) > 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function formatExpensesReport(array $data): array
    {
        $data['recent_staff'] = collect($data['recent_staff'] ?? [])->map(function ($expense) {
            /** @var DayClosingExpense $expense */
            return [
                'id' => $expense->id,
                'description' => $expense->description,
                'amount' => (float) $expense->amount,
                'date' => $expense->dayClosing?->closing_date?->toDateString(),
                'date_label' => $expense->dayClosing?->closing_date?->format('d M, Y'),
            ];
        })->values()->all();

        $data['recent_owner'] = collect($data['recent_owner'] ?? [])->map(function ($expense) {
            /** @var BusinessOwnerExpense $expense */
            return [
                'id' => $expense->id,
                'description' => $expense->description,
                'amount' => (float) $expense->amount,
                'category' => $expense->category,
                'category_label' => $expense->categoryLabel(),
                'fund_source' => $expense->fund_source,
                'fund_source_label' => $expense->fundSourceLabel(),
                'date' => $expense->expense_date?->toDateString(),
                'date_label' => $expense->expense_date?->format('d M, Y'),
            ];
        })->values()->all();

        $data['rows'] = collect($data['rows'] ?? [])
            ->filter(fn ($row) => ($row['total'] ?? 0) > 0)
            ->values()
            ->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function formatDebtsReport(array $data): array
    {
        $data['recent_debts'] = collect($data['recent_debts'] ?? [])->map(function ($sale) {
            /** @var Sale $sale */
            return [
                'id' => $sale->id,
                'reference_no' => $sale->reference_no,
                'sale_date' => $sale->sale_date instanceof \Carbon\Carbon
                    ? $sale->sale_date->toDateString()
                    : (string) $sale->sale_date,
                'customer_name' => $sale->customer_name ?: ($sale->customer?->name ?? 'Walk-in'),
                'customer_phone' => $sale->customer_phone ?: $sale->customer?->phone,
                'total_amount' => (float) $sale->total_amount,
                'amount_paid' => (float) $sale->amount_paid,
                'balance_due' => max(0, (float) $sale->total_amount - (float) $sale->amount_paid),
                'payment_status' => $sale->payment_status,
                'due_date' => $sale->due_date?->toDateString(),
            ];
        })->values()->all();

        if (isset($data['aging']) && $data['aging'] instanceof Collection) {
            $data['aging'] = $data['aging']->all();
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeCollections(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value instanceof Collection) {
                $data[$key] = $value->values()->all();
            }
        }

        return $data;
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function withWebContext(User $user, int $businessId, ?int $branchFilterId, callable $callback)
    {
        if ($user->role === 'owner') {
            app(ActiveBusinessService::class)->setActiveBusiness($businessId);
            if ($user->seesBusinessWideData()) {
                app(ActiveBranchService::class)->setActiveBranch($branchFilterId);
            }
        }

        return $callback();
    }
}
