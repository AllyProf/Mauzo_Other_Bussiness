<?php

namespace App\Services;

use App\Models\Business;
use App\Models\PlatformBillingInvoice;
use App\Models\PlatformLead;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    public function __construct(
        private PlatformAdminService $platformAdmin,
        private BusinessHealthService $health,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function metrics(): array
    {
        $expiringSoon = Business::query()
            ->where('is_active', true)
            ->where('pending_approval', false)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now()->addDays(7))
            ->whereDate('expiry_date', '>=', now())
            ->count();

        $outstanding = PlatformBillingInvoice::query()
            ->whereIn('status', [PlatformBillingInvoice::STATUS_PENDING, PlatformBillingInvoice::STATUS_NOTIFIED])
            ->sum('amount');

        return [
            'total_businesses' => Business::count(),
            'active_businesses' => Business::where('is_active', true)->count(),
            'pending_registrations' => Business::where('pending_approval', true)->count(),
            'pending_businesses' => Business::with('plan')->where('pending_approval', true)->latest()->limit(8)->get(),
            'expiring_this_week' => $expiringSoon,
            'open_tickets' => Ticket::where('status', 'open')->count(),
            'unread_tickets' => $this->platformAdmin->unreadTicketsCount(),
            'outstanding_amount' => (float) $outstanding,
            'new_leads' => PlatformLead::where('status', 'new')->count(),
            'recent_businesses' => Business::with('plan')->latest()->limit(8)->get(),
            'expiring_businesses' => Business::with('plan')
                ->where('is_active', true)
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<=', now()->addDays(7))
                ->orderBy('expiry_date')
                ->limit(8)
                ->get(),
            'at_risk' => $this->health->allBusinessSnapshots()
                ->filter(fn ($row) => in_array($row['health']['class'], ['danger', 'warning'], true))
                ->take(6)
                ->values(),
            'registrations_chart' => $this->registrationTrend(),
        ];
    }

    /**
     * @return list<array{month: string, count: int}>
     */
    private function registrationTrend(): array
    {
        $rows = Business::query()
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"), DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $rows->map(fn ($row) => [
            'month' => Carbon::createFromFormat('Y-m', $row->month)->format('M Y'),
            'count' => (int) $row->total,
        ])->all();
    }
}
