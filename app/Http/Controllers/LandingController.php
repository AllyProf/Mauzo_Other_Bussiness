<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Support\LandingSeo;
use App\Services\PlatformSettingsService;
use App\Services\RegistrationFunnelService;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function index(Request $request, PlatformSettingsService $platformSettings, RegistrationFunnelService $funnel)
    {
        $funnel->track($request, 'landing_view');

        $settings = $platformSettings->all();

        $platformName = $settings['platform_name'] ?? config('app.name');
        $supportEmail = $settings['support_email'] ?? '';
        $supportPhone = $settings['support_phone'] ?? '';

        return view('landing.index', [
            'platformSettings' => $settings,
            'platformName' => $platformName,
            'supportEmail' => $supportEmail,
            'supportPhone' => $supportPhone,
            'landingSeo' => LandingSeo::build($platformName, $supportEmail, $supportPhone),
            'plans' => Plan::query()->orderBy('price')->get(),
            'registrationOpen' => $platformSettings->isRegistrationOpen(),
            'trialDays' => (int) ($settings['default_trial_days'] ?? 30),
        ]);
    }
}
