<?php

namespace App\Http\Controllers;

use App\Services\BusinessReportService;
use App\Services\CashierPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function __construct(private BusinessReportService $reports)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeAny(['view_reports', 'verify_day_closing', 'finalize_reports']);

        $options = business_report_menu_options();
        if ($options === []) {
            abort(403);
        }

        // Prefer Daily Report as the hub landing page when available.
        $preferred = collect($options)->firstWhere('key', 'daily-report') ?? $options[0];
        $target = $preferred['url'];
        $query = $request->query();
        if ($query !== []) {
            $target .= (str_contains($target, '?') ? '&' : '?').http_build_query($query);
        }

        return redirect()->to($target);
    }

    public function paymentChannels(Request $request)
    {
        $this->authorizeAny(['view_reports']);
        $reportDate = $request->filled('report_date')
            ? \Carbon\Carbon::parse($request->report_date)->toDateString()
            : ($request->filled('end_date')
                ? \Carbon\Carbon::parse($request->end_date)->toDateString()
                : now()->toDateString());

        $filter = $this->branchBusinessFilterContext($request);
        $business = $filter['business'];
        $activeBusinessType = $this->reports->resolveBusinessTypeFilter($request, $business, $filter['businessTypes']);
        $snapshot = $this->reports->dailySnapshotReport($business, $reportDate, $activeBusinessType);
        $sources = collect($snapshot['sources'] ?? [])
            ->map(fn (array $row) => [
                'method' => $row['method'],
                'label' => $row['label'],
                'day_amount' => (float) ($row['day_amount'] ?? 0),
                'count' => (int) ($row['day_orders'] ?? 0),
            ])
            ->sortByDesc('day_amount')
            ->values()
            ->all();

        return view('reports.payment-channels', [
            'title' => 'Payment channels',
            'sources' => $sources,
            'reportDate' => $reportDate,
            'business' => $business,
            'businessTypes' => $filter['businessTypes'],
            'multiBusiness' => $filter['multiBusiness'],
            'activeBusinessType' => $activeBusinessType,
            'dateRange' => [
                'from' => $reportDate,
                'to' => $reportDate,
            ],
        ] + $filter);
    }

    public function dailyReport(Request $request)
    {
        $this->authorizeAny(['view_reports']);
        $reportDate = $request->filled('report_date')
            ? \Carbon\Carbon::parse($request->report_date)->toDateString()
            : ($request->filled('end_date')
                ? \Carbon\Carbon::parse($request->end_date)->toDateString()
                : now()->toDateString());

        $filter = $this->branchBusinessFilterContext($request);
        $business = $filter['business'];
        $activeBusinessType = $this->reports->resolveBusinessTypeFilter($request, $business, $filter['businessTypes']);
        $data = $this->reports->dailySnapshotReport($business, $reportDate, $activeBusinessType);

        return view('reports.daily-report', [
            'title' => __('reports.daily.title'),
            'data' => $data,
            'business' => $business,
            'businessTypes' => $filter['businessTypes'],
            'multiBusiness' => $filter['multiBusiness'],
            'activeBusinessType' => $activeBusinessType,
            'businessTypeNote' => null,
            'reportDate' => $reportDate,
            'dateRange' => [
                'from' => $data['period_from'],
                'to' => $reportDate,
            ],
        ] + $filter);
    }

    public function circulationProfit(Request $request)
    {
        return $this->renderReport($request, 'circulation-profit', 'Circulation vs Profit', function ($business, $from, $to, $businessTypeKey) use ($request) {
            $data = $this->reports->circulationProfitReport($business, $from, $to, $businessTypeKey);
            $data['tableRows'] = $this->paginateReportRows($request, collect($data['rows']), 15);

            return $data;
        });
    }

    public function dailySales(Request $request)
    {
        return $this->renderReport($request, 'daily-sales', 'Daily Sales', function ($business, $from, $to, $businessTypeKey) use ($request) {
            $data = $this->reports->dailySalesReport($business, $from, $to, $businessTypeKey);
            $data['tableRows'] = $this->paginateReportRows($request, collect($data['rows']), 15);

            return $data;
        });
    }

    public function expenses(Request $request)
    {
        return $this->renderReport($request, 'expenses', 'Expense Report', function ($business, $from, $to, $businessTypeKey) use ($request) {
            $data = $this->reports->expensesReport($business, $from, $to, $businessTypeKey);
            $data['tableRows'] = $this->paginateReportRows(
                $request,
                collect($data['rows'])->filter(fn ($row) => $row['total'] > 0)->values(),
                15
            );

            return $data;
        });
    }

    public function profit(Request $request)
    {
        return $this->renderReport($request, 'profit', 'Profit Report', function ($business, $from, $to, $businessTypeKey) use ($request) {
            $data = $this->reports->profitReport($business, $from, $to, $businessTypeKey);
            $data['tableRows'] = $this->paginateReportRows($request, collect($data['rows']), 31);

            return $data;
        });
    }

    public function salesAnalytics(Request $request)
    {
        return $this->renderReport($request, 'sales-analytics', 'Sales Analytics', function ($business, $from, $to, $businessTypeKey) use ($request) {
            $data = $this->reports->salesAnalyticsReport($business, $from, $to, $businessTypeKey);
            $data['staffRows'] = $this->paginateReportRows($request, collect($data['by_staff']), 15);

            return $data;
        });
    }

    public function products(Request $request)
    {
        return $this->renderReport($request, 'products', 'Product Report', function ($business, $from, $to, $businessTypeKey) use ($request) {
            $data = $this->reports->productsReport($business, $from, $to, $businessTypeKey);
            $data['productRows'] = $this->paginateReportRows($request, collect($data['products']), 15);

            return $data;
        });
    }

    public function debts(Request $request)
    {
        return $this->renderReport($request, 'debts', 'Debt Report', function ($business, $from, $to, $businessTypeKey) use ($request) {
            $data = $this->reports->debtsReport($business, $from, $to, $businessTypeKey);
            $data['debtorRows'] = $this->paginateReportRows($request, collect($data['customer_summaries']), 15);

            return $data;
        });
    }

    public function cashiers(Request $request)
    {
        return $this->renderReport($request, 'cashiers', 'Cashier Performance', function ($business, $from, $to) use ($request) {
            return app(CashierPerformanceService::class)->report(
                $business,
                $from,
                $to,
                $this->resolveBranchFilterId(),
                $request->boolean('cashiers_only')
            );
        });
    }

    private function renderReport(Request $request, string $view, string $title, callable $builder)
    {
        $this->authorizeAny(['view_reports']);
        $range = $this->reports->parseDateRange($request);
        $filter = $this->branchBusinessFilterContext($request);
        $business = $filter['business'];
        $activeBusinessType = $this->reports->resolveBusinessTypeFilter($request, $business, $filter['businessTypes']);
        $data = $builder($business, $range['from'], $range['to'], $activeBusinessType);

        return view('reports.'.$view, [
            'title' => $title,
            'data' => $data,
            'business' => $business,
            'businessTypes' => $filter['businessTypes'],
            'multiBusiness' => $filter['multiBusiness'],
            'activeBusinessType' => $activeBusinessType,
            'businessTypeNote' => $data['business_type_note'] ?? null,
            'dateRange' => $range,
        ] + $filter);
    }

    private function paginateReportRows(Request $request, Collection $rows, int $perPage = 15): LengthAwarePaginator
    {
        $page = $request->integer('page', 1);

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }
}
