<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ServiceSaleController extends Controller
{
    public function create()
    {
        $this->authorizeAny(['process_sales']);

        $openShift = Shift::openForUser(Auth::id(), Auth::user()->business_id);
        if (Auth::user()->requiresOpenShift() && ! $openShift) {
            return redirect()->route('shifts.create')
                ->with('warning', 'Open your shift before selling services.');
        }

        if ($redirect = $this->redirectIfShiftOverdue($openShift)) {
            return $redirect;
        }

        $business = Auth::user()->business;
        $branchFilterId = $this->resolvePosBranchFilterId();
        $templates = config('service_templates', []);

        if ($branchFilterId) {
            $businessTypes = $business->branchServicePosTypesMeta($branchFilterId);
        } else {
            $businessTypes = $business->servicePosTypesMeta();
        }

        if (empty($businessTypes)) {
            return redirect()->route('services.index')
                ->with('warning', 'Import a service template and configure your services before using Service POS.');
        }

        $categoriesQuery = ServiceCategory::query()
            ->where('business_id', $business->id)
            ->whereHas('services', fn ($q) => $q->where('is_active', true));

        if ($branchFilterId) {
            $categoriesQuery->where('branch_id', $branchFilterId);
        }

        $categories = $categoriesQuery->orderBy('name')->get();

        $servicesByCategory = ServiceCategory::query()
            ->where('business_id', $business->id)
            ->when($branchFilterId, fn ($q) => $q->where('branch_id', $branchFilterId))
            ->with(['activeServices'])
            ->get()
            ->mapWithKeys(function ($cat) {
                $typeKey = $cat->source_service_type_key ?: 'other';
                $services = $cat->activeServices->map(fn (Service $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'unit_label' => $s->unit_label,
                    'price' => (float) $s->price,
                    'businessTypeKey' => $typeKey,
                ])->values();

                return [$cat->id => $services];
            });

        $multiBusiness = count($businessTypes) > 1;
        $activeBranchName = $branchFilterId
            ? (active_branch()?->name ?? \App\Models\Branch::find($branchFilterId)?->name)
            : null;

        return view('services.pos', compact(
            'categories',
            'servicesByCategory',
            'openShift',
            'businessTypes',
            'multiBusiness',
            'activeBranchName',
            'branchFilterId',
        ));
    }

    public function store(Request $request)
    {
        $this->authorizeAny(['process_sales']);

        $openShift = Shift::openForUser(Auth::id(), Auth::user()->business_id);
        if (Auth::user()->requiresOpenShift() && ! $openShift) {
            return redirect()->route('shifts.create')->with('error', 'Your shift is not open.');
        }

        if ($redirect = $this->redirectIfShiftOverdue($openShift)) {
            return $redirect;
        }

        $request->validate([
            'sale_date' => 'required|date',
            'lines' => 'required|array|min:1',
            'lines.*.service_id' => 'required|exists:services,id',
            'lines.*.qty' => 'required|integer|min:1',
            'lines.*.price' => 'required|numeric|min:0',
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('business_id', Auth::user()->business_id)],
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);

        $activeLines = array_filter($request->lines, fn ($l) => ($l['qty'] ?? 0) > 0);
        if (empty($activeLines)) {
            return redirect()->back()->with('error', 'Add at least one service.')->withInput();
        }

        DB::beginTransaction();

        try {
            $ref = 'SRV-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));
            $total = 0;

            foreach ($activeLines as $line) {
                $total += (float) $line['qty'] * (float) $line['price'];
            }

            $customerFields = $this->resolveCustomerFields($request);

            $sale = Sale::create([
                'business_id' => Auth::user()->business_id,
                'user_id' => Auth::id(),
                'shift_id' => $openShift?->id,
                'reference_no' => $ref,
                'sale_source' => 'service_pos',
                'stock_deducted' => true,
                'consumables_deducted' => false,
                'sale_date' => $request->sale_date,
                'total_amount' => $total,
                'amount_paid' => 0,
                'payment_status' => 'pending',
                'customer_id' => $customerFields['customer_id'],
                'customer_name' => $customerFields['customer_name'],
                'customer_phone' => $customerFields['customer_phone'],
                'notes' => $request->notes,
            ]);

            foreach ($activeLines as $line) {
                $service = Service::find($line['service_id']);
                if (! $service || $service->business_id !== Auth::user()->business_id) {
                    throw new \InvalidArgumentException('Invalid service selected.');
                }

                $qty = (float) $line['qty'];
                $price = (float) $line['price'];
                $subtotal = $qty * $price;

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'item_id' => null,
                    'service_id' => $service->id,
                    'line_description' => $service->name.' ('.$service->unit_label.')',
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'list_unit_price' => $price,
                    'cost_price' => 0,
                    'subtotal' => $subtotal,
                ]);
            }

            DB::commit();
            $openShift?->refreshTotals();
            app(\App\Services\ServiceConsumableService::class)->deductForSale($sale->fresh());

            return redirect()->route('sales.index')
                ->with('success', "Service order {$ref} placed. Collect payment from Sales.");
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    private function resolvePosBranchFilterId(): ?int
    {
        if (! $this->actsAsBusinessWideViewer() && Auth::user()->branch_id) {
            return (int) Auth::user()->branch_id;
        }

        if ($branchId = active_branch_id()) {
            return $branchId;
        }

        return null;
    }
}
