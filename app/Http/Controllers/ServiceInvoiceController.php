<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ServiceInvoiceController extends Controller
{
    public function index()
    {
        $this->authorizeAny(['view_invoices', 'view_sales_history', 'process_sales']);

        $businessId = Auth::user()->business_id;
        $salesQuery = Sale::where('business_id', $businessId)
            ->where('payment_status', '!=', 'cancelled')
            ->where('sale_source', 'service_invoice');

        if (! $this->actsAsBusinessWideViewer()) {
            $salesQuery->where('user_id', Auth::id());
        }

        $stats = [
            'total' => (clone $salesQuery)->count(),
            'unpaid' => (clone $salesQuery)->whereIn('payment_status', ['pending', 'partial', 'debt'])->count(),
            'total_amount' => (float) (clone $salesQuery)->sum('total_amount'),
        ];

        $sales = (clone $salesQuery)
            ->with(['user', 'customer', 'items.service'])
            ->latest()
            ->paginate(20);

        return view('service-invoices.index', compact('sales', 'stats'));
    }

    public function create()
    {
        $this->authorizeAny(['create_invoices', 'process_sales']);

        $openShift = Shift::openForUser(Auth::id(), Auth::user()->business_id);
        if (Auth::user()->requiresOpenShift() && ! $openShift) {
            return redirect()->route('shifts.create')
                ->with('warning', 'Open your shift before creating service invoices.');
        }

        if ($redirect = $this->redirectIfShiftOverdue($openShift)) {
            return $redirect;
        }

        $business = Auth::user()->business;
        $branchFilterId = $this->resolvePosBranchFilterId();

        if ($branchFilterId) {
            $businessTypes = $business->branchServicePosTypesMeta($branchFilterId);
        } else {
            $businessTypes = $business->servicePosTypesMeta();
        }

        if (empty($businessTypes)) {
            return redirect()->route('services.register')
                ->with('warning', 'Import service templates before creating service invoices.');
        }

        $categories = ServiceCategory::query()
            ->where('business_id', $business->id)
            ->when($branchFilterId, fn ($q) => $q->where('branch_id', $branchFilterId))
            ->whereHas('services', fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        $services = Service::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->when($branchFilterId, fn ($q) => $q->where('branch_id', $branchFilterId))
            ->with('category')
            ->orderBy('name')
            ->get();

        $customers = Customer::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email']);

        return view('service-invoices.create', compact(
            'categories',
            'services',
            'customers',
            'businessTypes',
            'openShift',
            'branchFilterId',
        ));
    }

    public function store(Request $request)
    {
        $this->authorizeAny(['create_invoices', 'process_sales']);

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
            'customer_email' => 'nullable|email|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $activeLines = array_values(array_filter($request->lines, fn ($l) => ($l['qty'] ?? 0) > 0));
        if (empty($activeLines)) {
            return redirect()->back()->with('error', 'Add at least one service line.')->withInput();
        }

        DB::beginTransaction();

        try {
            $total = 0;
            foreach ($activeLines as $line) {
                $total += (float) $line['qty'] * (float) $line['price'];
            }

            $ref = 'SINV-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));
            $customerFields = $this->resolveCustomerFields($request);

            $sale = Sale::create([
                'business_id' => Auth::user()->business_id,
                'user_id' => Auth::id(),
                'shift_id' => $openShift?->id,
                'reference_no' => $ref,
                'sale_source' => 'service_invoice',
                'stock_deducted' => false,
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

                $qty = (int) $line['qty'];
                $price = (float) $line['price'];

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'item_id' => null,
                    'service_id' => $service->id,
                    'line_description' => $service->name.' ('.$service->unit_label.')',
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'list_unit_price' => $price,
                    'cost_price' => 0,
                    'subtotal' => $qty * $price,
                ]);
            }

            DB::commit();
            $openShift?->refreshTotals();

            $sale->load(['items.service', 'user', 'customer', 'business']);
            $notification = app(\App\Services\BusinessInvoiceNotificationService::class)
                ->notifyCreated($sale, Auth::user(), $request->input('customer_email'));

            $successMessage = "Service invoice {$ref} created.";
            if ($notification['sms_sent']) {
                $successMessage .= ' SMS sent.';
            } elseif ($notification['sms_error'] && filled($sale->customer_phone ?: $sale->customer?->phone)) {
                $successMessage .= ' SMS failed: '.$notification['sms_error'];
            }
            if ($notification['email_sent']) {
                $successMessage .= ' PDF emailed.';
            } elseif ($notification['email_error'] && (filled($sale->customer?->email) || filled($request->input('customer_email')))) {
                $successMessage .= ' Email failed: '.$notification['email_error'];
            }

            return redirect()
                ->route('service-invoices.show', $sale)
                ->with('success', $successMessage);
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function show(Sale $serviceInvoice)
    {
        $this->authorizeAny(['view_invoices', 'view_sales_history', 'process_sales']);

        if ($serviceInvoice->business_id != Auth::user()->business_id
            || ($serviceInvoice->sale_source ?? '') !== 'service_invoice') {
            abort(403);
        }

        $serviceInvoice->load(['items.service', 'items.item', 'user', 'payments.user', 'customer']);
        $sale = $serviceInvoice;

        return view('invoices.show', [
            'sale' => $sale,
            'isServiceInvoice' => true,
            'backRoute' => route('service-invoices.index'),
            'backLabel' => 'Service Invoices',
        ]);
    }

    private function resolvePosBranchFilterId(): ?int
    {
        if (! $this->actsAsBusinessWideViewer() && Auth::user()->branch_id) {
            return (int) Auth::user()->branch_id;
        }

        return active_branch_id();
    }
}
