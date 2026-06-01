<?php

namespace App\Http\Controllers;

use App\Services\BusinessReportService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function __construct(private BusinessReportService $reports)
    {
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
            $data['tableRows'] = $this->paginateReportRows($request, collect($data['rows']), 15);

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

    private function renderReport(Request $request, string $view, string $title, callable $builder)
    {
        $this->authorizeAny(['view_reports']);
        $range = $this->reports->parseDateRange($request);
        $business = Auth::user()->business;
        $businessTypes = $business->posBusinessTypesMeta();
        $multiBusiness = count($businessTypes) > 1;
        $activeBusinessType = $this->reports->resolveBusinessTypeFilter($request, $business);
        $data = $builder($business, $range['from'], $range['to'], $activeBusinessType);

        return view('reports.'.$view, [
            'title' => $title,
            'data' => $data,
            'business' => $business,
            'businessTypes' => $businessTypes,
            'multiBusiness' => $multiBusiness,
            'activeBusinessType' => $activeBusinessType,
            'businessTypeNote' => $data['business_type_note'] ?? null,
        ]);
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
