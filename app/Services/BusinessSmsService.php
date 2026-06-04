<?php

namespace App\Services;

use App\Mail\CustomerCommunicationMail;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerCommunicationCampaign;
use App\Models\CustomerSmsLog;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class BusinessSmsService
{
    public function __construct(private SmsService $smsService) {}

    public function allowsChannel(Business $business, string $channel = 'sms'): bool
    {
        $business->loadMissing('plan');

        if (! $business->plan) {
            return true;
        }

        return in_array($channel, ['email', 'email_sms'], true)
            ? $business->plan->allowsEmailSms()
            : $business->plan->allowsSmsSending();
    }

    public function canUseCommunication(Business $business): bool
    {
        return $business->hasPlanFeature('customer_communication')
            && ($this->allowsChannel($business, 'sms') || $this->allowsChannel($business, 'email'));
    }

    public function monthlyUsage(Business $business, string $channel = 'sms'): int
    {
        $channels = $channel === 'email'
            ? ['email', 'email_sms']
            : [$channel];

        return CustomerSmsLog::query()
            ->where('business_id', $business->id)
            ->whereIn('channel', $channels)
            ->where('status', 'sent')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    }

    public function quotaLimit(Business $business, string $channel = 'sms'): ?int
    {
        $business->loadMissing('plan');

        if (! $business->plan || ! $this->allowsChannel($business, $channel)) {
            return 0;
        }

        $max = in_array($channel, ['email', 'email_sms'], true)
            ? (int) $business->plan->max_email_sms
            : (int) $business->plan->max_sms;

        return $max === 0 ? null : $max;
    }

    public function remainingQuota(Business $business, string $channel = 'sms'): ?int
    {
        $limit = $this->quotaLimit($business, $channel);

        if ($limit === 0) {
            return 0;
        }

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->monthlyUsage($business, $channel));
    }

    public function quotaSummary(Business $business): array
    {
        return [
            'sms' => [
                'enabled' => $this->allowsChannel($business, 'sms'),
                'used' => $this->monthlyUsage($business, 'sms'),
                'limit' => $this->quotaLimit($business, 'sms'),
                'remaining' => $this->remainingQuota($business, 'sms'),
            ],
            'email' => [
                'enabled' => $this->allowsChannel($business, 'email'),
                'used' => $this->monthlyUsage($business, 'email'),
                'limit' => $this->quotaLimit($business, 'email'),
                'remaining' => $this->remainingQuota($business, 'email'),
            ],
        ];
    }

    /**
     * @param  array<int, string>  $channels
     * @return array{sent: int, failed: int, skipped: int, errors: array<int, string>}
     */
    public function sendMultiChannel(
        Business $business,
        User $sender,
        Collection $customers,
        string $message,
        string $purpose,
        array $channels,
        ?string $subject = null,
        ?CustomerCommunicationCampaign $campaign = null
    ): array {
        $channels = array_values(array_unique(array_intersect($channels, ['sms', 'email'])));

        if ($channels === []) {
            return [
                'sent' => 0,
                'failed' => 0,
                'skipped' => $customers->count(),
                'errors' => ['No valid delivery channels were selected.'],
            ];
        }

        if (! $business->hasPlanFeature('customer_communication')) {
            return [
                'sent' => 0,
                'failed' => 0,
                'skipped' => $customers->count(),
                'errors' => ['Customer communication is not included in your plan.'],
            ];
        }

        foreach ($channels as $channel) {
            if (! $this->allowsChannel($business, $channel)) {
                return [
                    'sent' => 0,
                    'failed' => 0,
                    'skipped' => $customers->count(),
                    'errors' => [ucfirst($channel).' is disabled on your subscription plan.'],
                ];
            }
        }

        if (in_array('email', $channels, true) && blank($subject)) {
            return [
                'sent' => 0,
                'failed' => 0,
                'skipped' => $customers->count(),
                'errors' => ['An email subject is required when sending by email.'],
            ];
        }

        $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($customers as $customer) {
            foreach ($channels as $channel) {
                if ($channel === 'sms') {
                    if (blank($customer->phone)) {
                        $result['skipped']++;

                        continue;
                    }

                    $remaining = $this->remainingQuota($business, 'sms');
                    if ($remaining === 0) {
                        $result['errors'][] = 'Monthly SMS quota reached.';
                        break 2;
                    }

                    $sendResult = $this->sendSmsToCustomer($business, $sender, $customer, $message, $purpose, $campaign);
                } else {
                    if (blank($customer->email)) {
                        $result['skipped']++;

                        continue;
                    }

                    $remaining = $this->remainingQuota($business, 'email');
                    if ($remaining === 0) {
                        $result['errors'][] = 'Monthly email quota reached.';
                        break 2;
                    }

                    $sendResult = $this->sendEmailToCustomer($business, $sender, $customer, $message, $purpose, $subject, $campaign);
                }

                if ($sendResult['success']) {
                    $result['sent']++;
                } else {
                    $result['failed']++;
                    $result['errors'][] = ($customer->name ?? 'Customer').' ('.$channel.'): '.($sendResult['error'] ?? 'Failed to send.');
                }
            }
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $channels
     * @param  array<int, int>  $customerIds
     */
    public function scheduleCampaign(
        Business $business,
        User $sender,
        array $customerIds,
        string $message,
        string $purpose,
        array $channels,
        \DateTimeInterface $scheduledAt,
        ?string $subject = null
    ): CustomerCommunicationCampaign {
        return CustomerCommunicationCampaign::create([
            'business_id' => $business->id,
            'user_id' => $sender->id,
            'customer_ids' => array_values(array_unique($customerIds)),
            'channels' => array_values(array_unique(array_intersect($channels, ['sms', 'email']))),
            'purpose' => $purpose,
            'subject' => $subject,
            'message' => $message,
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled',
        ]);
    }

    /**
     * @return array{sent: int, failed: int, skipped: int, errors: array<int, string>}
     */
    public function processCampaign(CustomerCommunicationCampaign $campaign): array
    {
        $campaign->loadMissing(['business.plan', 'user']);

        $customers = Customer::query()
            ->where('business_id', $campaign->business_id)
            ->whereIn('id', $campaign->customer_ids ?? [])
            ->where('is_active', true)
            ->get();

        if ($customers->isEmpty()) {
            return [
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'errors' => ['No valid customers found for this campaign.'],
            ];
        }

        return $this->sendMultiChannel(
            $campaign->business,
            $campaign->user ?? new User,
            $customers,
            $campaign->message,
            $campaign->purpose,
            $campaign->channels ?? ['sms'],
            $campaign->subject,
            $campaign
        );
    }

    /**
     * @return array{sent: int, failed: int, skipped: int, errors: array<int, string>}
     */
    public function sendBulk(
        Business $business,
        User $sender,
        Collection $customers,
        string $message,
        string $purpose = 'general',
        string $channel = 'sms'
    ): array {
        return $this->sendMultiChannel($business, $sender, $customers, $message, $purpose, [$channel]);
    }

    /**
     * @return array{success: bool, error?: string, log?: CustomerSmsLog}
     */
    public function sendToCustomer(
        Business $business,
        User $sender,
        Customer $customer,
        string $message,
        string $purpose = 'general',
        string $channel = 'sms'
    ): array {
        if ($channel === 'email') {
            return $this->sendEmailToCustomer($business, $sender, $customer, $message, $purpose, $message);
        }

        return $this->sendSmsToCustomer($business, $sender, $customer, $message, $purpose);
    }

    /**
     * @return array{success: bool, error?: string, log?: CustomerSmsLog}
     */
    public function sendSmsToCustomer(
        Business $business,
        User $sender,
        Customer $customer,
        string $message,
        string $purpose = 'general',
        ?CustomerCommunicationCampaign $campaign = null
    ): array {
        if (blank($customer->phone)) {
            return ['success' => false, 'error' => 'Customer has no phone number.'];
        }

        if ($this->remainingQuota($business, 'sms') === 0) {
            return ['success' => false, 'error' => 'Monthly SMS quota reached.'];
        }

        $log = CustomerSmsLog::create([
            'business_id' => $business->id,
            'user_id' => $sender->id,
            'customer_id' => $customer->id,
            'campaign_id' => $campaign?->id,
            'phone' => $customer->phone,
            'recipient_name' => $customer->name,
            'message' => $message,
            'channel' => 'sms',
            'purpose' => $purpose,
            'status' => 'pending',
        ]);

        $phone = $this->smsService->formatPhoneNumber($customer->phone);
        $response = $this->smsService->sendSms($phone, $message);

        $log->update([
            'status' => ($response['success'] ?? false) ? 'sent' : 'failed',
            'provider_response' => is_string($response['response'] ?? null)
                ? $response['response']
                : json_encode($response),
        ]);

        if (! ($response['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $response['error'] ?? 'SMS gateway rejected the message.',
                'log' => $log,
            ];
        }

        return ['success' => true, 'log' => $log];
    }

    /**
     * @return array{success: bool, error?: string, log?: CustomerSmsLog}
     */
    public function sendSmsToStaff(
        Business $business,
        User $sender,
        User $staff,
        string $message,
        string $purpose = 'staff_general',
    ): array {
        if (blank($staff->phone)) {
            return ['success' => false, 'error' => 'Staff member has no phone number.'];
        }

        if (! $this->allowsChannel($business, 'sms')) {
            return ['success' => false, 'error' => 'SMS is disabled on your subscription plan.'];
        }

        if ($this->remainingQuota($business, 'sms') === 0) {
            return ['success' => false, 'error' => 'Monthly SMS quota reached.'];
        }

        $log = CustomerSmsLog::create([
            'business_id' => $business->id,
            'user_id' => $sender->id,
            'customer_id' => null,
            'phone' => $staff->phone,
            'recipient_name' => $staff->name,
            'message' => $message,
            'channel' => 'sms',
            'purpose' => $purpose,
            'status' => 'pending',
        ]);

        $phone = $this->smsService->formatPhoneNumber($staff->phone);
        $response = $this->smsService->sendSms($phone, $message);

        $log->update([
            'status' => ($response['success'] ?? false) ? 'sent' : 'failed',
            'provider_response' => is_string($response['response'] ?? null)
                ? $response['response']
                : json_encode($response),
        ]);

        if (! ($response['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $response['error'] ?? 'SMS gateway rejected the message.',
                'log' => $log,
            ];
        }

        return ['success' => true, 'log' => $log];
    }

    /**
     * @return array{success: bool, error?: string, log?: CustomerSmsLog}
     */
    public function sendDebtorReminderSms(
        Business $business,
        User $sender,
        Sale $sale,
        string $message,
        string $purpose = 'debt_reminder',
    ): array {
        $phone = trim((string) ($sale->customer_phone ?: $sale->customer?->phone));

        if ($phone === '') {
            return ['success' => false, 'error' => 'Debtor has no phone number.'];
        }

        if (! $this->allowsChannel($business, 'sms')) {
            return ['success' => false, 'error' => 'SMS is disabled on your subscription plan.'];
        }

        if ($this->remainingQuota($business, 'sms') === 0) {
            return ['success' => false, 'error' => 'Monthly SMS quota reached.'];
        }

        $log = CustomerSmsLog::create([
            'business_id' => $business->id,
            'user_id' => $sender->id ?: null,
            'customer_id' => $sale->customer_id,
            'phone' => $phone,
            'recipient_name' => $sale->customer_name ?: $sale->customer?->name,
            'message' => $message,
            'channel' => 'sms',
            'purpose' => $purpose,
            'status' => 'pending',
        ]);

        $formattedPhone = $this->smsService->formatPhoneNumber($phone);
        $response = $this->smsService->sendSms($formattedPhone, $message);

        $log->update([
            'status' => ($response['success'] ?? false) ? 'sent' : 'failed',
            'provider_response' => is_string($response['response'] ?? null)
                ? $response['response']
                : json_encode($response),
        ]);

        if (! ($response['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $response['error'] ?? 'SMS gateway rejected the message.',
                'log' => $log,
            ];
        }

        return ['success' => true, 'log' => $log];
    }

    /**
     * @return array{success: bool, error?: string, log?: CustomerSmsLog}
     */
    public function sendInternalSms(
        Business $business,
        User $sender,
        string $phone,
        string $message,
        string $purpose = 'internal',
        ?string $recipientName = null,
        ?int $recipientUserId = null,
    ): array {
        if (blank($phone)) {
            return ['success' => false, 'error' => 'No phone number provided.'];
        }

        if (! $this->allowsChannel($business, 'sms')) {
            return ['success' => false, 'error' => 'SMS is disabled on your subscription plan.'];
        }

        if ($this->remainingQuota($business, 'sms') === 0) {
            return ['success' => false, 'error' => 'Monthly SMS quota reached.'];
        }

        $log = CustomerSmsLog::create([
            'business_id' => $business->id,
            'user_id' => $sender->id,
            'customer_id' => null,
            'phone' => $phone,
            'recipient_name' => $recipientName,
            'message' => $message,
            'channel' => 'sms',
            'purpose' => $purpose,
            'status' => 'pending',
        ]);

        $formattedPhone = $this->smsService->formatPhoneNumber($phone);
        $response = $this->smsService->sendSms($formattedPhone, $message);

        $log->update([
            'status' => ($response['success'] ?? false) ? 'sent' : 'failed',
            'provider_response' => is_string($response['response'] ?? null)
                ? $response['response']
                : json_encode($response),
        ]);

        if (! ($response['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $response['error'] ?? 'SMS gateway rejected the message.',
                'log' => $log,
            ];
        }

        return ['success' => true, 'log' => $log];
    }

    /**
     * @return array{success: bool, error?: string, log?: CustomerSmsLog}
     */
    public function sendEmailToCustomer(
        Business $business,
        User $sender,
        Customer $customer,
        string $message,
        string $purpose = 'general',
        ?string $subject = null,
        ?CustomerCommunicationCampaign $campaign = null
    ): array {
        if (blank($customer->email)) {
            return ['success' => false, 'error' => 'Customer has no email address.'];
        }

        if ($this->remainingQuota($business, 'email') === 0) {
            return ['success' => false, 'error' => 'Monthly email quota reached.'];
        }

        $subjectLine = trim((string) $subject) ?: $business->name.' — Message';

        $log = CustomerSmsLog::create([
            'business_id' => $business->id,
            'user_id' => $sender->id,
            'customer_id' => $customer->id,
            'campaign_id' => $campaign?->id,
            'phone' => $customer->phone,
            'recipient_email' => $customer->email,
            'recipient_name' => $customer->name,
            'message' => $message,
            'channel' => 'email',
            'purpose' => $purpose,
            'status' => 'pending',
        ]);

        try {
            Mail::to($customer->email)->send(new CustomerCommunicationMail(
                $business,
                $customer,
                $subjectLine,
                $message,
                $purpose
            ));

            $log->update(['status' => 'sent']);

            return ['success' => true, 'log' => $log];
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'provider_response' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'log' => $log,
            ];
        }
    }
}

