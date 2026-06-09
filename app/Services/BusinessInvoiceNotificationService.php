<?php

namespace App\Services;

use App\Mail\CustomerInvoiceMail;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerSmsLog;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BusinessInvoiceNotificationService
{
    public function __construct(
        private BusinessSmsService $businessSms,
        private InvoiceDocumentService $invoiceDocuments,
        private PlatformSettingsService $platformSettings,
    ) {}

    /**
     * @return array{sms_sent: bool, email_sent: bool, sms_error: ?string, email_error: ?string}
     */
    public function notifyCreated(Sale $sale, User $sender, ?string $overrideEmail = null): array
    {
        $sale->loadMissing(['items.item', 'customer', 'business']);
        $business = $sale->business ?? Business::find($sale->business_id);

        if (! $business) {
            return [
                'sms_sent' => false,
                'email_sent' => false,
                'sms_error' => 'Business not found.',
                'email_error' => 'Business not found.',
            ];
        }

        $settings = $business->automationSettings();
        $vars = $this->templateVars($sale, $business);

        $smsSent = false;
        $emailSent = false;
        $smsError = null;
        $emailError = null;

        if ($settings['sms_invoice_created_enabled'] ?? true) {
            $smsResult = $this->sendSms($business, $sender, $sale, $vars);
            $smsSent = $smsResult['success'] ?? false;
            $smsError = $smsResult['error'] ?? null;
        }

        if ($settings['email_invoice_created_enabled'] ?? true) {
            $emailResult = $this->sendEmail($business, $sender, $sale, $vars, $overrideEmail);
            $emailSent = $emailResult['success'] ?? false;
            $emailError = $emailResult['error'] ?? null;
        }

        return [
            'sms_sent' => $smsSent,
            'email_sent' => $emailSent,
            'sms_error' => $smsError,
            'email_error' => $emailError,
        ];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    private function sendSms(Business $business, User $sender, Sale $sale, array $vars): array
    {
        $phone = trim((string) ($sale->customer_phone ?: $sale->customer?->phone));

        if ($phone === '') {
            return ['success' => false, 'error' => 'Customer has no phone number.'];
        }

        if (! $this->businessSms->allowsChannel($business, 'sms')) {
            return ['success' => false, 'error' => 'SMS is not enabled on your plan.'];
        }

        $message = $this->renderTemplate(
            $business,
            'sms_invoice_created_template',
            '{business}: Dear {customer}, invoice {reference} for TZS {amount} dated {date} has been created. The invoice has been emailed if we have your email address.',
            $vars,
        );

        return $this->businessSms->sendDebtorReminderSms(
            $business,
            $sender,
            $sale,
            $message,
            'invoice_created',
        );
    }

    /**
     * @return array{success: bool, error?: string}
     */
    private function sendEmail(Business $business, User $sender, Sale $sale, array $vars, ?string $overrideEmail = null): array
    {
        $email = $this->resolveCustomerEmail($sale, $overrideEmail);

        if (! filled($email)) {
            return ['success' => false, 'error' => 'Customer has no email address.'];
        }

        if (! $this->businessSms->allowsChannel($business, 'email')) {
            return ['success' => false, 'error' => 'Email is not enabled on your plan.'];
        }

        if ($this->businessSms->remainingQuota($business, 'email') === 0) {
            return ['success' => false, 'error' => 'Monthly email quota reached.'];
        }

        if (! $this->platformSettings->isMailConfigured()) {
            return ['success' => false, 'error' => 'Email is not configured on the server.'];
        }

        $customer = $this->customerModel($sale, $business, $email);
        $subject = $this->renderTemplate(
            $business,
            'email_invoice_created_subject',
            '{business} — Invoice {reference}',
            $vars,
        );
        $body = $this->renderTemplate(
            $business,
            'email_invoice_created_body',
            'Your invoice {reference} is attached. Open the PDF for the full invoice details.',
            $vars,
        );
        $attachmentPdf = $this->invoiceDocuments->renderPdf($sale, $business);

        $log = CustomerSmsLog::create([
            'business_id' => $business->id,
            'user_id' => $sender->id,
            'customer_id' => $sale->customer_id,
            'phone' => $customer->phone,
            'recipient_email' => $email,
            'recipient_name' => $customer->name,
            'message' => $body,
            'channel' => 'email',
            'purpose' => 'invoice_created',
            'status' => 'pending',
        ]);

        try {
            Mail::to($email)->send(new CustomerInvoiceMail(
                $business,
                $customer,
                $sale,
                $subject,
                $body,
                $attachmentPdf,
            ));

            $log->update(['status' => 'sent']);

            return ['success' => true];
        } catch (\Throwable $exception) {
            Log::warning('Invoice customer email failed', [
                'sale_id' => $sale->id,
                'error' => $exception->getMessage(),
            ]);

            $log->update([
                'status' => 'failed',
                'provider_response' => $exception->getMessage(),
            ]);

            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    private function resolveCustomerEmail(Sale $sale, ?string $overrideEmail = null): ?string
    {
        if (filled($overrideEmail) && filter_var($overrideEmail, FILTER_VALIDATE_EMAIL)) {
            return $overrideEmail;
        }

        $sale->loadMissing('customer');
        $email = $sale->customer?->email;

        if (filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return null;
    }

    private function customerModel(Sale $sale, Business $business, string $email): Customer
    {
        if ($sale->customer) {
            return $sale->customer;
        }

        return new Customer([
            'business_id' => $business->id,
            'name' => $sale->customer_name ?: 'Customer',
            'email' => $email,
            'phone' => $sale->customer_phone,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function templateVars(Sale $sale, Business $business): array
    {
        $balance = max(0, (float) $sale->total_amount - (float) $sale->amount_paid);

        return [
            'business' => $business->name,
            'customer' => $sale->customer_name ?: $sale->customer?->name ?: 'Customer',
            'reference' => (string) $sale->reference_no,
            'amount' => number_format((float) $sale->total_amount, 0),
            'balance' => number_format($balance, 0),
            'date' => \Carbon\Carbon::parse($sale->sale_date)->format('d M Y'),
        ];
    }

    private function renderTemplate(Business $business, string $key, string $default, array $vars): string
    {
        $template = (string) ($business->automationSettings()[$key] ?? $default);

        foreach ($vars as $placeholder => $value) {
            $template = str_replace('{'.$placeholder.'}', $value, $template);
        }

        return trim($template);
    }
}
