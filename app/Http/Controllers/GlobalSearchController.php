<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Item;
use App\Models\PlatformBillingInvoice;
use App\Models\Sale;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GlobalSearchController extends Controller
{
    private const LIMIT = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['groups' => []]);
        }

        $user = Auth::user();
        $like = '%'.addcslashes($term, '%_\\').'%';

        $groups = $user->isPlatformAdmin()
            ? $this->platformGroups($like)
            : $this->businessGroups($like);

        return response()->json(['groups' => array_values(array_filter($groups, fn ($g) => count($g['items'])))]);
    }

    private function platformGroups(string $like): array
    {
        $businesses = Business::query()
            ->where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhere('contact_person', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'contact_person', 'phone', 'is_active']);

        $invoices = PlatformBillingInvoice::query()
            ->with('business:id,name')
            ->where(fn ($q) => $q->where('invoice_number', 'like', $like)
                ->orWhereHas('business', fn ($b) => $b->where('name', 'like', $like)))
            ->latest('id')
            ->limit(self::LIMIT)
            ->get();

        return [
            [
                'label' => 'Businesses',
                'items' => $businesses->map(fn ($b) => [
                    'title' => $b->name,
                    'subtitle' => trim(($b->contact_person ?? '').' '.($b->phone ?? '')).($b->is_active ? '' : ' · inactive'),
                    'icon' => 'fa-building',
                    'url' => route('admin.businesses.edit', $b),
                ])->all(),
            ],
            [
                'label' => 'Subscription Invoices',
                'items' => $invoices->map(fn ($i) => [
                    'title' => $i->invoice_number,
                    'subtitle' => ($i->business->name ?? '—').' · TZS '.number_format((float) $i->amount, 0).' · '.$i->statusLabel(),
                    'icon' => 'fa-file-text-o',
                    'url' => route('admin.payments.index', ['search' => $i->invoice_number]),
                ])->all(),
            ],
        ];
    }

    private function businessGroups(string $like): array
    {
        $user = Auth::user();
        $businessId = $this->currentBusinessId() ?: $user->business_id;

        if (! $businessId) {
            return [];
        }

        $groups = [];

        if ($user->can('view_inventory') && business_retail_enabled()) {
            $groups[] = [
                'label' => 'Items',
                'items' => Item::query()
                    ->where('business_id', $businessId)
                    ->where(fn ($q) => $q->where('name', 'like', $like)
                        ->orWhere('sku', 'like', $like)
                        ->orWhere('brand', 'like', $like))
                    ->orderBy('name')
                    ->limit(self::LIMIT)
                    ->get(['id', 'name', 'sku', 'brand', 'current_stock'])
                    ->map(fn ($item) => [
                        'title' => $item->name,
                        'subtitle' => trim(implode(' · ', array_filter([$item->sku, $item->brand, 'Stock: '.(float) $item->current_stock]))),
                        'icon' => 'fa-barcode',
                        'url' => route('items.show', $item),
                    ])->all(),
            ];
        }

        if ($user->can('manage_customers') && plan_feature('customers')) {
            $groups[] = [
                'label' => 'Customers',
                'items' => Customer::query()
                    ->where('business_id', $businessId)
                    ->where(fn ($q) => $q->where('name', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('email', 'like', $like))
                    ->orderBy('name')
                    ->limit(self::LIMIT)
                    ->get(['id', 'name', 'phone', 'email'])
                    ->map(fn ($c) => [
                        'title' => $c->name,
                        'subtitle' => $c->phone ?: $c->email,
                        'icon' => 'fa-address-book',
                        'url' => route('customers.show', $c),
                    ])->all(),
            ];
        }

        if ($user->can('manage_suppliers') && $user->can('view_inventory') && business_retail_enabled()) {
            $groups[] = [
                'label' => 'Suppliers',
                'items' => Supplier::query()
                    ->where('business_id', $businessId)
                    ->where(fn ($q) => $q->where('name', 'like', $like)
                        ->orWhere('contact_person', 'like', $like)
                        ->orWhere('phone', 'like', $like))
                    ->orderBy('name')
                    ->limit(self::LIMIT)
                    ->get(['id', 'name', 'contact_person', 'phone'])
                    ->map(fn ($s) => [
                        'title' => $s->name,
                        'subtitle' => trim(($s->contact_person ?? '').' '.($s->phone ?? '')),
                        'icon' => 'fa-truck',
                        'url' => route('suppliers.show', $s),
                    ])->all(),
            ];
        }

        if ($user->can('view_invoices') || $user->can('view_sales_history') || $user->can('process_sales')) {
            $sales = Sale::query()
                ->where('business_id', $businessId)
                ->where('payment_status', '!=', 'cancelled')
                ->where(fn ($q) => $q->where('reference_no', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('customer_phone', 'like', $like)
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)))
                ->when(! $this->actsAsBusinessWideViewer(), fn ($q) => $q->where('user_id', $user->id))
                ->with('customer:id,name')
                ->latest('id')
                ->limit(8)
                ->get(['id', 'reference_no', 'sale_source', 'customer_id', 'customer_name', 'total_amount', 'payment_status', 'created_at']);

            $invoiceItems = [];
            $saleItems = [];
            foreach ($sales as $sale) {
                $isInvoice = $sale->sale_source === 'invoice';
                $row = [
                    'title' => $sale->reference_no ?: '#'.$sale->id,
                    'subtitle' => implode(' · ', array_filter([
                        $sale->customer->name ?? $sale->customer_name,
                        'TZS '.number_format((float) $sale->total_amount, 0),
                        ucfirst((string) $sale->payment_status),
                        $sale->created_at?->format('d M Y'),
                    ])),
                    'icon' => $isInvoice ? 'fa-file-text-o' : 'fa-shopping-cart',
                    'url' => $isInvoice ? route('invoices.show', $sale) : route('sales.show', $sale),
                ];
                if ($isInvoice) {
                    $invoiceItems[] = $row;
                } else {
                    $saleItems[] = $row;
                }
            }

            if (plan_feature('invoices')) {
                $groups[] = ['label' => 'Invoices', 'items' => array_slice($invoiceItems, 0, self::LIMIT)];
            }
            $groups[] = ['label' => 'Sales', 'items' => array_slice($saleItems, 0, self::LIMIT)];
        }

        return $groups;
    }
}
