<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\EnsuresPlatformAdmin;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\PlatformSmsLog;
use App\Services\PlatformSettingsService;
use App\Services\PlatformSmsService;
use Illuminate\Http\Request;

class PlatformCommunicationController extends Controller
{
    use EnsuresPlatformAdmin;

    public function __construct(
        private PlatformSettingsService $settings,
        private PlatformSmsService $smsService
    ) {}

    public function index(\Illuminate\Http\Request $request)
    {
        $this->ensurePlatformAdmin('settings');

        $settings   = $this->settings->all();
        $businesses = Business::orderBy('name')->get();

        $statusFilter = $request->get('status', 'all');

        $logsQuery = PlatformSmsLog::with('business')->latest();
        if ($statusFilter !== 'all') {
            $logsQuery->where('status', $statusFilter);
        }
        $smsLogs = $logsQuery->paginate(50)->withQueryString();

        // Counts for badge display
        $counts = [
            'all'       => PlatformSmsLog::count(),
            'scheduled' => PlatformSmsLog::where('status', 'scheduled')->count(),
            'sent'      => PlatformSmsLog::where('status', 'sent')->count(),
            'failed'    => PlatformSmsLog::where('status', 'failed')->count(),
        ];

        return view('admin.communication.index', compact('settings', 'businesses', 'smsLogs', 'statusFilter', 'counts'));
    }

    public function sendBroadcast(Request $request)
    {
        $this->ensurePlatformAdmin('settings');

        $request->validate([
            'recipient_group' => 'required|in:all,active,suspended,expired,selected,platform_staff,business_staff',
            'selected_businesses' => 'required_if:recipient_group,selected|array',
            'selected_businesses.*' => 'exists:businesses,id',
            'message' => 'required|string|max:1000',
            'send_type' => 'required|in:immediate,scheduled',
            'scheduled_at' => 'required_if:send_type,scheduled|nullable|date|after:now',
        ]);

        $group = $request->recipient_group;
        $recipients = $this->resolveBroadcastRecipients($request);

        if ($recipients->isEmpty()) {
            return redirect()->back()->with('error', 'No matching recipients with phone numbers found.');
        }

        $scheduledAt = null;
        if ($request->send_type === 'scheduled' && $request->scheduled_at) {
            $scheduledAt = \Carbon\Carbon::parse($request->scheduled_at, 'Africa/Dar_es_Salaam')->timezone('UTC')->toDateTimeString();
        }

        $sentCount = 0;
        foreach ($recipients as $recipient) {
            $interpolatedMessage = str_replace(
                ['{business_name}', '{contact_person}', '{staff_name}'],
                [
                    $recipient['business_name'] ?? '',
                    $recipient['contact_person'] ?? '',
                    $recipient['staff_name'] ?? '',
                ],
                $request->message
            );

            $success = $this->smsService->sendCustomSms(
                $recipient['phone'],
                $interpolatedMessage,
                $recipient['business_id'] ?? null,
                $recipient['user_id'] ?? null,
                $recipient['recipient_name'] ?? null,
                $scheduledAt
            );

            if ($success) {
                $sentCount++;
            }
        }

        $audienceLabel = match ($group) {
            'platform_staff' => 'platform staff',
            'business_staff' => 'business staff',
            default => 'business(es)',
        };

        $actionType = $scheduledAt ? 'SMS_BROADCAST_SCHEDULED' : 'SMS_BROADCAST';
        $logMessage = $scheduledAt
            ? "Scheduled SMS broadcast to {$sentCount} {$audienceLabel} for {$scheduledAt}. Group: {$group}"
            : "Sent SMS broadcast to {$sentCount} {$audienceLabel}. Group: {$group}";

        AuditLog::log($actionType, $logMessage);

        $successMessage = $scheduledAt
            ? "SMS Broadcast successfully scheduled for {$sentCount} {$audienceLabel} at {$scheduledAt}."
            : "SMS Broadcast completed. Successfully sent to {$sentCount} {$audienceLabel}.";

        return redirect()->back()->with('success', $successMessage);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{phone: string, recipient_name: ?string, business_id: ?int, user_id: ?int, business_name: string, contact_person: string, staff_name: string}>
     */
    private function resolveBroadcastRecipients(Request $request): \Illuminate\Support\Collection
    {
        $group = $request->recipient_group;

        if ($group === 'platform_staff') {
            return \App\Models\User::query()
                ->where('role', 'platform_staff')
                ->where('is_active', true)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->orderBy('name')
                ->get()
                ->map(fn (\App\Models\User $user) => [
                    'phone' => trim((string) $user->phone),
                    'recipient_name' => $user->name,
                    'business_id' => null,
                    'user_id' => $user->id,
                    'business_name' => '',
                    'contact_person' => $user->name,
                    'staff_name' => $user->name,
                ])
                ->filter(fn ($row) => $row['phone'] !== '')
                ->values();
        }

        if ($group === 'business_staff') {
            $query = \App\Models\User::query()
                ->with('business')
                ->whereNotNull('business_id')
                ->where('is_active', true)
                ->whereNotIn('role', ['super_admin', 'platform_staff', 'owner'])
                ->whereNotNull('phone')
                ->where('phone', '!=', '');

            if ($request->filled('selected_businesses')) {
                $query->whereIn('business_id', $request->selected_businesses);
            }

            return $query->orderBy('name')->get()
                ->map(fn (\App\Models\User $user) => [
                    'phone' => trim((string) $user->phone),
                    'recipient_name' => $user->name,
                    'business_id' => (int) $user->business_id,
                    'user_id' => $user->id,
                    'business_name' => $user->business?->name ?? '',
                    'contact_person' => $user->name,
                    'staff_name' => $user->name,
                ])
                ->filter(fn ($row) => $row['phone'] !== '')
                ->values();
        }

        $query = Business::query();

        if ($group === 'active') {
            $query->where('is_active', true)->where(function ($q) {
                $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', now());
            });
        } elseif ($group === 'suspended') {
            $query->where('is_active', false);
        } elseif ($group === 'expired') {
            $query->where('is_active', true)->whereNotNull('expiry_date')->where('expiry_date', '<', now());
        } elseif ($group === 'selected') {
            $query->whereIn('id', $request->selected_businesses ?? []);
        }

        return $query->get()
            ->map(fn (Business $business) => [
                'phone' => trim((string) ($business->phone ?? '')),
                'recipient_name' => $business->name,
                'business_id' => $business->id,
                'user_id' => $business->owner_user_id,
                'business_name' => $business->name,
                'contact_person' => $business->contact_person ?? '',
                'staff_name' => $business->contact_person ?? '',
            ])
            ->filter(fn ($row) => $row['phone'] !== '')
            ->values();
    }

    public function updateTemplates(Request $request)
    {
        $this->ensurePlatformAdmin('settings');

        $data = $request->validate([
            'sms_template_registration_verification' => 'required|string',
            'sms_template_registration_pending' => 'required|string',
            'sms_template_registration_approved' => 'required|string',
            'sms_template_registration_rejected' => 'required|string',
            'sms_template_registration_submitted_admin' => 'required|string',
            'sms_template_business_registered' => 'required|string',
            'sms_template_business_linked' => 'required|string',
            'sms_template_password_reset' => 'required|string',
            'sms_template_account_suspended' => 'required|string',
            'sms_template_account_reactivated' => 'required|string',
        ]);

        $this->settings->update($data);

        AuditLog::log('UPDATE_SMS_TEMPLATES', 'Updated automated SMS templates settings');

        return redirect()->back()->with('success', 'Automated SMS templates saved successfully.');
    }

    public function cancelScheduled($id)
    {
        $this->ensurePlatformAdmin('settings');

        $log = PlatformSmsLog::findOrFail($id);

        if ($log->status !== 'scheduled') {
            return redirect()->back()->with('error', 'This SMS is not in a scheduled state.');
        }

        $log->update([
            'status' => 'failed',
            'provider_response' => 'Cancelled by Administrator',
        ]);

        AuditLog::log('SMS_CANCELLED', "Cancelled scheduled SMS to {$log->phone} ({$log->recipient_name})");

        return redirect()->back()->with('success', 'Scheduled SMS cancelled successfully.');
    }

    public function cancelAllScheduled()
    {
        $this->ensurePlatformAdmin('settings');

        $cancelledCount = PlatformSmsLog::where('status', 'scheduled')->update([
            'status' => 'failed',
            'provider_response' => 'Cancelled by Administrator',
        ]);

        if ($cancelledCount > 0) {
            AuditLog::log('SMS_ALL_CANCELLED', "Cancelled {$cancelledCount} scheduled SMS message(s)");
            return redirect()->back()->with('success', "Cancelled {$cancelledCount} scheduled SMS message(s) successfully.");
        }

        return redirect()->back()->with('info', 'No scheduled SMS messages found to cancel.');
    }
}
