<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerCommunicationCampaign;
use App\Models\CustomerSmsLog;
use App\Services\BusinessSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CustomerCommunicationController extends Controller
{
    public function index(Request $request, BusinessSmsService $smsService)
    {
        $this->authorizeAny(['manage_customer_communications', 'manage_customers']);

        $business = Auth::user()->business;

        if (! $business || ! $smsService->canUseCommunication($business)) {
            return redirect()
                ->route('subscription.upgrade')
                ->with('warning', 'Customer communication is not available on your current plan.');
        }

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
            ->limit(50)
            ->get();

        $scheduledCampaigns = CustomerCommunicationCampaign::query()
            ->where('business_id', $business->id)
            ->where('status', 'scheduled')
            ->with('user')
            ->orderBy('scheduled_at')
            ->get();

        $quota = $smsService->quotaSummary($business);

        return view('customer-communications.index', compact(
            'customers',
            'logs',
            'scheduledCampaigns',
            'quota',
            'business'
        ));
    }

    public function send(Request $request, BusinessSmsService $smsService)
    {
        $this->authorizeAny(['manage_customer_communications', 'manage_customers']);

        $business = Auth::user()->business;

        if (! $business || ! $smsService->canUseCommunication($business)) {
            return back()->with('error', 'Customer communication is not available on your current plan.');
        }

        $validated = $request->validate([
            'purpose' => 'required|in:general,new_product,promotion,debt_reminder',
            'message' => 'required|string|min:5|max:480',
            'subject' => [
                Rule::requiredIf(fn () => in_array('email', $request->input('channels', []), true)),
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
        ]);

        $channels = array_values(array_unique($validated['channels']));

        foreach ($channels as $channel) {
            if (! $smsService->allowsChannel($business, $channel)) {
                return back()->with('error', ucfirst($channel).' is disabled on your plan.');
            }

            if ($smsService->remainingQuota($business, $channel) === 0) {
                return back()->with('error', __('communications.quota_reached', ['channel' => strtoupper($channel)]));
            }
        }

        $customers = Customer::query()
            ->where('business_id', $business->id)
            ->whereIn('id', $validated['customer_ids'])
            ->where('is_active', true)
            ->get();

        if ($customers->isEmpty()) {
            return back()->with('error', 'No valid customers were selected.');
        }

        $message = trim($validated['message']);
        if ($validated['purpose'] === 'new_product' && ! str_contains(strtolower($message), 'new')) {
            $message = 'New arrival: '.$message;
        }

        $subject = isset($validated['subject']) ? trim($validated['subject']) : null;

        if ($validated['send_mode'] === 'scheduled') {
            $campaign = $smsService->scheduleCampaign(
                $business,
                Auth::user(),
                $customers->pluck('id')->all(),
                $message,
                $validated['purpose'],
                $channels,
                \Carbon\Carbon::parse($validated['scheduled_at']),
                $subject
            );

            return back()->with(
                'success',
                'Message scheduled for '.$campaign->scheduled_at->format('M d, Y H:i').' via '.$campaign->channelsLabel().'.'
            );
        }

        $result = $smsService->sendMultiChannel(
            $business,
            Auth::user(),
            $customers,
            $message,
            $validated['purpose'],
            $channels,
            $subject
        );

        if ($result['sent'] === 0) {
            return back()->with('error', $result['errors'][0] ?? 'No messages were sent.');
        }

        $summary = $result['sent'].' message(s) sent';
        if ($result['failed'] > 0) {
            $summary .= ', '.$result['failed'].' failed';
        }
        if ($result['skipped'] > 0) {
            $summary .= ', '.$result['skipped'].' skipped (missing contact for selected channel)';
        }

        return back()->with('success', $summary.'.');
    }

    public function cancelCampaign(CustomerCommunicationCampaign $campaign, BusinessSmsService $smsService)
    {
        $this->authorizeAny(['manage_customer_communications', 'manage_customers']);

        $business = Auth::user()->business;

        if (! $business || $campaign->business_id !== $business->id) {
            abort(404);
        }

        if ($campaign->status !== 'scheduled') {
            return back()->with('error', 'Only scheduled campaigns can be cancelled.');
        }

        $campaign->update(['status' => 'cancelled']);

        return back()->with('success', 'Scheduled message cancelled.');
    }
}

