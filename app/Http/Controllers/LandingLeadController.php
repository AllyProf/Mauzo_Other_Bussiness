<?php

namespace App\Http\Controllers;

use App\Models\PlatformLead;
use App\Services\PlatformSettingsService;
use App\Services\PlatformSmsService;
use Illuminate\Http\Request;

class LandingLeadController extends Controller
{
    public function store(Request $request, PlatformSettingsService $settings, PlatformSmsService $platformSms)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'company' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:2000',
        ]);

        if (blank($validated['email']) && blank($validated['phone'])) {
            return back()->withErrors(['email' => 'Please provide an email or phone number.'])->withInput();
        }

        PlatformLead::create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'company' => $validated['company'] ?? null,
            'message' => $validated['message'] ?? null,
            'source' => 'landing_demo',
            'status' => 'new',
            'ip_address' => $request->ip(),
        ]);

        $notifyEmail = trim((string) $settings->get('admin_notification_email', ''));

        if ($notifyEmail) {
            try {
                app(\App\Services\PlatformMailService::class)->notifyAdminDemoLead($validated);
            } catch (\Throwable) {
                // Non-blocking
            }
        }

        try {
            $platformSms->notifyAdminDemoLead($validated);
        } catch (\Throwable) {
            // Non-blocking
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Thank you! We will contact you shortly.']);
        }

        return back()->with('lead_success', 'Thank you! We will contact you shortly about your demo request.');
    }
}
