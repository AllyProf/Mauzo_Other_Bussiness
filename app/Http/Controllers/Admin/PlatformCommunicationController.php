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
            'recipient_group' => 'required|in:all,active,suspended,expired,selected',
            'selected_businesses' => 'required_if:recipient_group,selected|array',
            'selected_businesses.*' => 'exists:businesses,id',
            'message' => 'required|string|max:1000',
            'send_type' => 'required|in:immediate,scheduled',
            'scheduled_at' => 'required_if:send_type,scheduled|nullable|date|after:now',
        ]);

        $group = $request->recipient_group;
        $query = Business::query();

        if ($group === 'active') {
            $query->where('is_active', true)->where(function($q) {
                $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', now());
            });
        } elseif ($group === 'suspended') {
            $query->where('is_active', false);
        } elseif ($group === 'expired') {
            $query->where('is_active', true)->whereNotNull('expiry_date')->where('expiry_date', '<', now());
        } elseif ($group === 'selected') {
            $query->whereIn('id', $request->selected_businesses);
        }

        $targets = $query->get();
        if ($targets->isEmpty()) {
            return redirect()->back()->with('error', 'No matching businesses found to send SMS.');
        }

        $scheduledAt = null;
        if ($request->send_type === 'scheduled' && $request->scheduled_at) {
            $scheduledAt = \Carbon\Carbon::parse($request->scheduled_at, 'Africa/Dar_es_Salaam')->timezone('UTC')->toDateTimeString();
        }

        $sentCount = 0;
        foreach ($targets as $business) {
            $phone = trim($business->phone ?? '');
            if ($phone === '') {
                continue;
            }

            // Interpolate name in broadcast if they want to use {business_name} or {contact_person}
            $interpolatedMessage = str_replace(
                ['{business_name}', '{contact_person}'],
                [$business->name, $business->contact_person ?? ''],
                $request->message
            );

            $success = $this->smsService->sendCustomSms(
                $phone,
                $interpolatedMessage,
                $business->id,
                $business->owner_user_id,
                $business->name,
                $scheduledAt
            );

            if ($success) {
                $sentCount++;
            }
        }

        $actionType = $scheduledAt ? 'SMS_BROADCAST_SCHEDULED' : 'SMS_BROADCAST';
        $logMessage = $scheduledAt 
            ? "Scheduled SMS broadcast to {$sentCount} business(es) for {$scheduledAt}. Group: {$group}"
            : "Sent SMS broadcast to {$sentCount} business(es). Group: {$group}";

        AuditLog::log($actionType, $logMessage);

        $successMessage = $scheduledAt
            ? "SMS Broadcast successfully scheduled for {$sentCount} business(es) at {$scheduledAt}."
            : "SMS Broadcast completed. Successfully sent to {$sentCount} business(es).";

        return redirect()->back()->with('success', $successMessage);
    }

    public function updateTemplates(Request $request)
    {
        $this->ensurePlatformAdmin('settings');

        $data = $request->validate([
            'sms_template_registration_verification' => 'required|string',
            'sms_template_registration_pending' => 'required|string',
            'sms_template_registration_approved' => 'required|string',
            'sms_template_registration_rejected' => 'required|string',
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
