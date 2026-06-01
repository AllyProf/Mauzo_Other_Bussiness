<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DebtController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAny(['manage_debts', 'process_sales', 'collect_payments']);

        $businessId = Auth::user()->business_id;
        $today = now()->toDateString();

        $baseQuery = $this->scopeToCurrentStaff(
            Sale::where('business_id', $businessId)
                ->whereNotIn('payment_status', ['paid', 'cancelled'])
                ->whereColumn('total_amount', '>', 'amount_paid')
        );

        $allOutstanding = (clone $baseQuery)->get();

        $stats = [
            'total_outstanding' => $allOutstanding->sum(fn ($sale) => (float) $sale->total_amount - (float) $sale->amount_paid),
            'open_accounts' => $allOutstanding->count(),
            'overdue_count' => $allOutstanding->filter(fn ($sale) => $sale->due_date && $sale->due_date < $today)->count(),
            'customers' => $allOutstanding->pluck('customer_name')->filter()->unique()->count(),
        ];

        $query = (clone $baseQuery)->with(['user', 'items.item']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        if ($request->status === 'debt') {
            $query->where('payment_status', 'debt');
        } elseif ($request->status === 'partial') {
            $query->where('payment_status', 'partial');
        } elseif ($request->status === 'pending') {
            $query->where('payment_status', 'pending');
        }

        if ($request->filter === 'overdue') {
            $query->whereNotNull('due_date')->whereDate('due_date', '<', $today);
        }

        $debts = $query->latest()->paginate(15)->withQueryString();

        $customerSummaries = $allOutstanding
            ->groupBy(fn ($sale) => $sale->customer_name ?: 'Walk-in / Unnamed')
            ->map(function ($sales, $name) {
                return [
                    'name' => $name,
                    'phone' => $sales->first()->customer_phone,
                    'orders' => $sales->count(),
                    'balance' => $sales->sum(fn ($sale) => (float) $sale->total_amount - (float) $sale->amount_paid),
                ];
            })
            ->sortByDesc('balance')
            ->take(10)
            ->values();

        $scopedToSelf = ! $this->actsAsBusinessWideViewer();

        $customers = \App\Models\Customer::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        $paymentMethods = Auth::user()->business->enabledPaymentMethods();

        return view('debts.index', compact(
            'debts',
            'stats',
            'customerSummaries',
            'today',
            'scopedToSelf',
            'customers',
            'paymentMethods',
        ));
    }
}
