<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\PlatformSettingsService;
use App\Services\RegistrationFunnelService;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function index(Request $request, PlatformSettingsService $platformSettings, RegistrationFunnelService $funnel)
    {
        $funnel->track($request, 'landing_view');

        return view('landing.index', [
            'platformSettings' => $platformSettings->all(),
            'plans' => Plan::query()->orderBy('price')->get(),
            'registrationOpen' => $platformSettings->isRegistrationOpen(),
        ]);
    }
}
