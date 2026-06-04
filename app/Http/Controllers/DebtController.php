<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DebtController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAny(['manage_debts', 'process_sales', 'collect_payments']);

        $businessId = Auth::user()->business_id;
        $business = Auth::user()->business;
        $today = now()->toDateString();

        $branchFilterId = $this->debtBranchFilterId();
        $viewingAllBranches = $this->actsAsBusinessWideViewer() && ! $branchFilterId;
        $activeBranchName = $branchFilterId
            ? (active_branch()?->name ?? Branch::find($branchFilterId)?->name ?? 'Branch')
            : null;

        $templates = config('category_templates', []);
        if ($branchFilterId) {
            $businessTypes = collect($business->importedTypesForBranch($branchFilterId))
                ->map(function ($type) use ($templates) {
                    $key = (string) ($type['key'] ?? '');

                    return [
                        'key' => $key,
                        'label' => (string) ($type['label'] ?? $key),
                        'icon' => $templates[$key]['icon'] ?? (str_starts_with($key, 'custom:') ? 'fa-pencil' : 'fa-store'),
                    ];
                })
                ->values()
                ->all();
        } else {
            $businessTypes = $business->posBusinessTypesMeta();
        }
        $multiBusiness = count($businessTypes) > 1;
        $activeBusinessType = $request->input('business_type', 'all');

        $baseQuery = $this->debtRelatedSalesQuery($businessId)
            ->whereNotIn('payment_status', ['paid', 'cancelled'])
            ->whereColumn('total_amount', '>', 'amount_paid');

        if ($activeBusinessType && $activeBusinessType !== 'all') {
            $baseQuery->whereHas('items.item.category', function ($query) use ($activeBusinessType) {
                $query->where('source_business_type_key', $activeBusinessType);
            });
        }

        $allOutstanding = (clone $baseQuery)->with('items.item.category')->get();

        $stats = [
            'total_outstanding' => $allOutstanding->sum(fn ($sale) => (float) $sale->total_amount - (float) $sale->amount_paid),
            'open_accounts' => $allOutstanding->count(),
            'overdue_count' => $allOutstanding->filter(fn ($sale) => $sale->due_date && $sale->due_date < $today)->count(),
            'customers' => $allOutstanding->pluck('customer_name')->filter()->unique()->count(),
        ];

        $query = (clone $baseQuery)->with(['user', 'items.item.category']);

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
            'activeBranchName',
            'branchFilterId',
            'viewingAllBranches',
            'businessTypes',
            'multiBusiness',
            'activeBusinessType',
        ));
    }

    public function history()
    {
        $this->authorizeAny(['manage_debts', 'process_sales', 'collect_payments']);

        $business = Auth::user()->business;
        $businessId = $business->id;
        $scopedToSelf = ! $this->actsAsBusinessWideViewer();
        $businessTypes = $business->posBusinessTypesMeta();
        $multiBusiness = count($businessTypes) > 1;

        $debtSalesQuery = $this->debtRelatedSalesQuery($businessId);
        $allDebtSales = (clone $debtSalesQuery)->with('items.item.category')->get();

        $stats = [
            'total_collected' => SalePayment::whereHas('sale', fn ($q) => $this->applyDebtSaleScope($q, $businessId))->sum('amount'),
            'payments_count' => SalePayment::whereHas('sale', fn ($q) => $this->applyDebtSaleScope($q, $businessId))->count(),
            'settled_accounts' => $allDebtSales->where('payment_status', 'paid')->count(),
            'open_balance' => $allDebtSales
                ->filter(fn (Sale $sale) => ! in_array($sale->payment_status, ['paid', 'cancelled'], true))
                ->sum(fn (Sale $sale) => max(0, (float) $sale->total_amount - (float) $sale->amount_paid)),
        ];

        $payments = SalePayment::query()
            ->whereHas('sale', fn ($q) => $this->applyDebtSaleScope($q, $businessId))
            ->with(['sale.user', 'sale.items.item.category', 'user'])
            ->latest()
            ->get();

        $settledAccounts = (clone $debtSalesQuery)
            ->where('payment_status', 'paid')
            ->with(['user', 'payments', 'items.item.category'])
            ->latest('updated_at')
            ->get();

        $paymentMethodLabels = collect($business->enabledPaymentMethods())
            ->pluck('label', 'key')
            ->all();

        return view('debts.history', compact(
            'payments',
            'stats',
            'settledAccounts',
            'paymentMethodLabels',
            'scopedToSelf',
            'businessTypes',
            'multiBusiness',
        ));
    }

    private function debtRelatedSalesQuery(int $businessId)
    {
        return $this->applyDebtSaleScope(
            Sale::query()->where('business_id', $businessId),
            $businessId
        );
    }

    private function applyDebtSaleScope($query, int $businessId)
    {
        $query->where('business_id', $businessId)
            ->whereNotIn('payment_status', ['cancelled'])
            ->where(function ($inner) {
                $inner->whereNotNull('customer_name')
                    ->orWhereNotNull('customer_id')
                    ->orWhereIn('payment_status', ['debt', 'partial']);
            });

        if ($this->actsAsBusinessWideViewer()) {
            if ($branchFilterId = $this->debtBranchFilterId()) {
                $query->whereHas('items.item.category', function ($categoryQuery) use ($branchFilterId) {
                    $categoryQuery->where('branch_id', $branchFilterId);
                });
            }

            return $query;
        }

        return $query->where('user_id', Auth::id());
    }

    private function debtBranchFilterId(): ?int
    {
        if (! $this->actsAsBusinessWideViewer() && Auth::user()->branch_id) {
            return (int) Auth::user()->branch_id;
        }

        return active_branch_id();
    }
}
