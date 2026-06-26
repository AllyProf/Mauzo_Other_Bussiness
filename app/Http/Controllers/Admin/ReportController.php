<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\EnsuresPlatformAdmin;
use App\Services\PlatformReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use EnsuresPlatformAdmin;

    public function index(Request $request, PlatformReportService $reports)
    {
        $this->ensurePlatformAdmin('reports');

        $months = (int) $request->input('months', 6);
        $data = $reports->dashboard($months);

        return view('admin.reports.index', compact('data', 'months'));
    }

    public function industryInsights()
    {
        $this->ensurePlatformAdmin('reports');

        $businesses = \App\Models\Business::with('plan')->get();
        $config = config('category_templates', []);

        $insights = collect($config)->map(function ($template, $key) use ($businesses) {
            $matching = $businesses->filter(function ($b) use ($key) {
                return collect($b->category_business_types ?? [])->contains(fn ($t) => ($t['key'] ?? '') === $key);
            });

            $businessIds = $matching->pluck('id')->toArray();

            $salesCount = $businessIds
                ? \App\Models\Sale::whereIn('business_id', $businessIds)->where('payment_status', '!=', 'cancelled')->count()
                : 0;
            $totalRevenue = $businessIds
                ? (int) \App\Models\Sale::whereIn('business_id', $businessIds)->where('payment_status', '!=', 'cancelled')->sum('total_amount')
                : 0;
            $avgTicket = $salesCount > 0 ? round($totalRevenue / $salesCount) : 0;

            return [
                'key'            => $key,
                'label'          => $template['label'] ?? ucfirst($key),
                'icon'           => $template['icon'] ?? 'fa-store',
                'business_count' => $matching->count(),
                'sales_count'    => $salesCount,
                'total_revenue'  => $totalRevenue,
                'avg_ticket'     => $avgTicket,
            ];
        })->filter(fn ($row) => $row['business_count'] > 0)->sortByDesc('total_revenue')->values();

        $totalBusinesses = $businesses->count();

        return view('admin.reports.industry-insights', compact('insights', 'totalBusinesses'));
    }
}
