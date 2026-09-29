<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\EnsuresPlatformAdmin;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\PlatformBillingInvoice;
use App\Services\PlatformBillingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentReportController extends Controller
{
    use EnsuresPlatformAdmin;

    public function index(Request $request, PlatformBillingService $billing)
    {
        $this->ensurePlatformAdmin('payments');

        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->month)->startOfMonth()
            : null;

        $query = PlatformBillingInvoice::query()
            ->with(['business.plan', 'plan', 'markedPaidByUser'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('business_id'), fn ($q) => $q->where('business_id', $request->business_id))
            ->when($month, fn ($q) => $q->whereDate('billing_month', $month->toDateString()))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim($request->search);
                $q->where(function ($inner) use ($search) {
                    $inner->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('business', fn ($business) => $business->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('billing_month')
            ->orderByDesc('id');

        $invoices = $query->paginate(25)->withQueryString();
        $summary = $billing->paymentReportSummary($month, $request->only(['status', 'business_id', 'search']));

        $businesses = Business::query()
            ->where('is_active', true)
            ->whereNotNull('plan_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        $invoiceBusinesses = Business::query()
            ->where('pending_approval', false)
            ->with('plan')
            ->orderBy('name')
            ->get();

        return view('admin.payments.index', compact('invoices', 'summary', 'businesses', 'month', 'invoiceBusinesses'));
    }

    public function storeManual(Request $request, PlatformBillingService $billing)
    {
        $this->ensurePlatformAdmin('payments');

        $data = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'billing_month' => 'required|date_format:Y-m',
            'quantity' => 'required|integer|min:1|max:36',
            'unit_price' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
            'send_now' => 'nullable|boolean',
        ]);

        $business = Business::findOrFail($data['business_id']);
        $invoice = $billing->createManualInvoice($business, [
            'billing_month' => Carbon::createFromFormat('Y-m', $data['billing_month'])->startOfMonth(),
            'quantity' => (int) $data['quantity'],
            'unit_price' => (float) $data['unit_price'],
            'description' => $data['description'] ?? null,
        ]);

        AuditLog::log('CREATE_MANUAL_BILLING_INVOICE', "Created manual invoice {$invoice->invoice_number} for {$business->name} (TZS ".number_format((float) $invoice->amount, 0).')');

        if (! $request->boolean('send_now')) {
            return back()->with('success', "Invoice {$invoice->invoice_number} created for {$business->name}.");
        }

        if ($billing->sendInvoiceEmail($invoice)) {
            return back()->with('success', "Invoice {$invoice->invoice_number} created and emailed to {$business->email}.");
        }

        return back()->with('error', "Invoice {$invoice->invoice_number} was created but the email could not be sent. Check the business email and SMTP settings, then use Resend.");
    }

    public function markPaid(Request $request, PlatformBillingInvoice $invoice, PlatformBillingService $billing)
    {
        $this->ensurePlatformAdmin('payments');

        if ($invoice->status === PlatformBillingInvoice::STATUS_PAID) {
            return back()->with('error', 'This invoice is already marked as paid.');
        }

        $validated = $request->validate([
            'payment_reference' => 'nullable|string|max:120',
            'payment_notes' => 'nullable|string|max:1000',
            'extend_subscription' => 'nullable|boolean',
            'months_paid' => 'nullable|integer|min:1|max:36',
            'paid_until' => 'nullable|date',
        ]);
        $validated['extend_subscription'] = $request->boolean('extend_subscription');

        $billing->markInvoicePaid($invoice, $validated, Auth::user());

        $invoice->loadMissing('business');

        AuditLog::log(
            'MARK_INVOICE_PAID',
            "Marked invoice {$invoice->invoice_number} as paid for {$invoice->business->name} (TZS ".number_format((float) $invoice->amount, 0).')'
        );

        return back()->with('success', "Payment recorded for {$invoice->business->name}.");
    }

    public function updateExpiry(Request $request, PlatformBillingInvoice $invoice)
    {
        $this->ensurePlatformAdmin('payments');

        $validated = $request->validate([
            'paid_until' => 'required|date',
        ]);

        $business = $invoice->business;
        if (! $business) {
            return back()->with('error', 'This invoice has no business attached.');
        }

        $previous = $business->expiry_date?->format('Y-m-d') ?? 'not set';
        $newExpiry = \Carbon\Carbon::parse($validated['paid_until'])->startOfDay();

        $business->forceFill([
            'expiry_date' => $newExpiry->toDateString(),
            'expiry_reminder_sent_at' => null,
        ])->save();

        AuditLog::log(
            'UPDATE_BUSINESS_EXPIRY',
            "Changed paid-until date for {$business->name} from {$previous} to {$newExpiry->toDateString()} (invoice {$invoice->invoice_number})",
            $business->id
        );

        return back()->with('success', "Paid-until date for {$business->name} set to {$newExpiry->format('d M Y')}.");
    }

    public function generateInvoices(Request $request, PlatformBillingService $billing)
    {
        $this->ensurePlatformAdmin('payments');

        $validated = $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $month = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
        $generated = 0;

        Business::query()
            ->where('is_active', true)
            ->whereNotNull('plan_id')
            ->where('pending_approval', false)
            ->with('plan')
            ->orderBy('id')
            ->each(function (Business $business) use ($billing, $month, &$generated) {
                $invoice = $billing->issueMonthlyInvoice($business, $month);
                if ($invoice && $invoice->wasRecentlyCreated) {
                    $generated++;
                }
            });

        AuditLog::log('GENERATE_BILLING_INVOICES', "Generated {$generated} invoice(s) for ".$month->format('F Y'));

        return back()->with('success', "{$generated} invoice(s) generated for ".$month->format('F Y').'.');
    }

    public function downloadPdf(PlatformBillingInvoice $invoice)
    {
        $this->ensurePlatformAdmin('payments');

        $invoice->loadMissing(['business.plan', 'plan']);

        $documents = app(\App\Services\PlatformInvoiceDocumentService::class);

        return response($documents->renderPdf($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$documents->filename($invoice).'"',
        ]);
    }

    public function resendInvoice(PlatformBillingInvoice $invoice, PlatformBillingService $billing)
    {
        $this->ensurePlatformAdmin('payments');

        if ($billing->sendInvoiceEmail($invoice)) {
            AuditLog::log('RESEND_BILLING_INVOICE', "Resent invoice {$invoice->invoice_number}");

            return back()->with('success', 'Invoice email sent successfully.');
        }

        return back()->with('error', 'Could not send invoice email. Check SMTP settings and business email.');
    }
}
