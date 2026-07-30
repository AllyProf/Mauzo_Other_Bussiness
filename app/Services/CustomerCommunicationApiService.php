<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerCommunicationCampaign;
use App\Models\CustomerSmsLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerCommunicationApiService
{
    public function __construct(private BusinessSmsService $smsService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function index(Business $business, int $page = 1): array
    {
        $this->assertFeatureAvailable($business);

        $customers = Customer::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where(function ($phoneQuery) {
                    $phoneQuery->whereNotNull('phone')->where('phone', '!=', '');
                })->orWhere(function ($emailQuery) {
                    $emailQuery->whereNotNull('email')->where('email', '!=', '');
                });
            })
            ->orderBy('name')
            ->get();

        $logs = CustomerSmsLog::query()
            ->where('business_id', $business->id)
            ->with(['customer', 'user', 'campaign'])
            ->latest()
            ->paginate(20, ['*'], 'page', max(1, $page));

        $scheduledCampaigns = CustomerCommunicationCampaign::query()
            ->where('business_id', $business->id)
            ->where('status', 'scheduled')
            ->with('user')
            ->orderBy('scheduled_at')
            ->get();

        $quota = $this->smsService->quotaSummary($business);

        return [
            'available' => true,
            'quota' => $this->formatQuota($quota),
            'purposes' => $this->purposeOptions(),
            'channels' => $this->channelOptions($quota),
            'customers' => $customers->map(fn (Customer $c) => $this->formatCustomer($c))->values()->all(),
            'scheduled_campaigns' => $scheduledCampaigns->map(fn ($c) => $this->formatCampaign($c))->values()->all(),
            'logs' => collect($logs->items())->map(fn ($log) => $this->formatLog($log))->values()->all(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function send(Business $business, User $sender, array $payload): array
    {
        $this->assertFeatureAvailable($business);

        $validated = validator($payload, [
            'purpose' => 'required|in:general,new_product,promotion,debt_reminder',
            'message' => 'required|string|min:5|max:480',
            'subject' => [
                Rule::requiredIf(fn () => in_array('email', $payload['channels'] ?? [], true)),
                'nullable',
                'string',
                'max:255',
            ],
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'integer|exists:customers,id',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:sms,email',
            'send_mode' => 'required|in:now,scheduled',
            'scheduled_at' => 'required_if:send_mode,scheduled|nullable|date|after:now',
        ])->validate();

        $channels = array_values(array_unique($validated['channels']));

        foreach ($channels as $channel) {
            if (! $this->smsService->allowsChannel($business, $channel)) {
                throw ValidationException::withMessages([
                    'channels' => [ucfirst($channel).' is disabled on your plan.'],
                ]);
            }

            if ($this->smsService->remainingQuota($business, $channel) === 0) {
                throw ValidationException::withMessages([
                    'channels' => [__('communications.quota_reached', ['channel' => strtoupper($channel)])],
                ]);
            }
        }

        $customers = Customer::query()
            ->where('business_id', $business->id)
            ->whereIn('id', $validated['customer_ids'])
            ->where('is_active', true)
            ->get();

        if ($customers->isEmpty()) {
            throw ValidationException::withMessages([
                'customer_ids' => ['No valid customers were selected.'],
            ]);
        }

        $message = trim($validated['message']);
        if ($validated['purpose'] === 'new_product' && ! str_contains(strtolower($message), 'new')) {
            $message = 'New arrival: '.$message;
        }

        $subject = isset($validated['subject']) ? trim($validated['subject']) : null;

        if ($validated['send_mode'] === 'scheduled') {
            $campaign = $this->smsService->scheduleCampaign(
                $business,
                $sender,
                $customers->pluck('id')->all(),
                $message,
                $validated['purpose'],
                $channels,
                Carbon::parse($validated['scheduled_at']),
                $subject
            );
            $campaign->loadMissing('user');

            return [
                'mode' => 'scheduled',
                'campaign' => $this->formatCampaign($campaign),
            ];
        }

        $result = $this->smsService->sendMultiChannel(
            $business,
            $sender,
            $customers,
            $message,
            $validated['purpose'],
            $channels,
            $subject
        );

        if ($result['sent'] === 0) {
            throw ValidationException::withMessages([
                'message' => [$result['errors'][0] ?? 'No messages were sent.'],
            ]);
        }

        return [
            'mode' => 'now',
            'sent' => $result['sent'],
            'failed' => $result['failed'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelCampaign(Business $business, CustomerCommunicationCampaign $campaign): array
    {
        $this->assertFeatureAvailable($business);

        if ($campaign->business_id !== $business->id) {
            abort(404);
        }

        if ($campaign->status !== 'scheduled') {
            throw ValidationException::withMessages([
                'campaign' => ['Only scheduled campaigns can be cancelled.'],
            ]);
        }

        $campaign->update(['status' => 'cancelled']);
        $campaign->loadMissing('user');

        return [
            'campaign' => $this->formatCampaign($campaign->fresh()),
        ];
    }

    private function assertFeatureAvailable(Business $business): void
    {
        if (! $this->smsService->canUseCommunication($business)) {
            throw ValidationException::withMessages([
                'plan' => ['Customer communication is not available on your current plan.'],
            ]);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $quota
     * @return array<string, mixed>
     */
    private function formatQuota(array $quota): array
    {
        return [
            'sms' => [
                'enabled' => (bool) ($quota['sms']['enabled'] ?? false),
                'used' => (int) ($quota['sms']['used'] ?? 0),
                'limit' => $quota['sms']['limit'] ?? null,
                'remaining' => $quota['sms']['remaining'] ?? null,
                'quota_exhausted' => ($quota['sms']['enabled'] ?? false) && ($quota['sms']['remaining'] ?? null) === 0,
            ],
            'email' => [
                'enabled' => (bool) ($quota['email']['enabled'] ?? false),
                'used' => (int) ($quota['email']['used'] ?? 0),
                'limit' => $quota['email']['limit'] ?? null,
                'remaining' => $quota['email']['remaining'] ?? null,
                'quota_exhausted' => ($quota['email']['enabled'] ?? false) && ($quota['email']['remaining'] ?? null) === 0,
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function purposeOptions(): array
    {
        return [
            ['key' => 'new_product', 'label' => __('communications.purposes.new_product')],
            ['key' => 'promotion', 'label' => __('communications.purposes.promotion')],
            ['key' => 'debt_reminder', 'label' => __('communications.purposes.debt_reminder')],
            ['key' => 'general', 'label' => __('communications.purposes.general')],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $quota
     * @return array<int, array<string, mixed>>
     */
    private function channelOptions(array $quota): array
    {
        $channels = [];

        if ($quota['sms']['enabled'] ?? false) {
            $channels[] = [
                'key' => 'sms',
                'label' => __('communications.sms'),
                'quota_exhausted' => ($quota['sms']['remaining'] ?? null) === 0,
            ];
        }

        if ($quota['email']['enabled'] ?? false) {
            $channels[] = [
                'key' => 'email',
                'label' => __('communications.email'),
                'quota_exhausted' => ($quota['email']['remaining'] ?? null) === 0,
            ];
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'phone_display' => $customer->displayPhone(),
            'email' => $customer->email,
            'has_phone' => filled($customer->phone),
            'has_email' => filled($customer->email),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCampaign(CustomerCommunicationCampaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'purpose' => $campaign->purpose,
            'purpose_label' => $campaign->purposeLabel(),
            'channels' => $campaign->channels ?? [],
            'channels_label' => $campaign->channelsLabel(),
            'subject' => $campaign->subject,
            'message' => $campaign->message,
            'customer_ids' => $campaign->customer_ids ?? [],
            'recipient_count' => count($campaign->customer_ids ?? []),
            'status' => $campaign->status,
            'status_label' => $campaign->statusLabel(),
            'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
            'scheduled_at_label' => $campaign->scheduled_at?->format('M d, Y H:i'),
            'sent_at' => $campaign->sent_at?->toIso8601String(),
            'created_by' => $campaign->user ? [
                'id' => $campaign->user->id,
                'name' => $campaign->user->name,
            ] : null,
            'result_summary' => $campaign->result_summary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatLog(CustomerSmsLog $log): array
    {
        return [
            'id' => $log->id,
            'channel' => $log->channel,
            'channel_label' => $log->channelLabel(),
            'purpose' => $log->purpose,
            'purpose_label' => $log->purposeLabel(),
            'status' => $log->status,
            'status_label' => $log->statusLabel(),
            'message' => $log->message,
            'recipient_name' => $log->recipient_name,
            'recipient_contact' => $log->recipientContact(),
            'phone' => $log->phone,
            'recipient_email' => $log->recipient_email,
            'customer' => $log->customer ? [
                'id' => $log->customer->id,
                'name' => $log->customer->name,
            ] : null,
            'sent_by' => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
            ] : null,
            'campaign_id' => $log->campaign_id,
            'created_at' => $log->created_at?->toIso8601String(),
            'created_at_label' => $log->created_at?->format('M d, Y H:i'),
        ];
    }
}
