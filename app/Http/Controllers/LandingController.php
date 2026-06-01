<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\PlatformSettingsService;

class LandingController extends Controller
{
    public function index(PlatformSettingsService $platformSettings)
    {
        return view('landing.index', [
            'platformSettings' => $platformSettings->all(),
            'plans' => Plan::query()->orderBy('price')->get(),
            'registrationOpen' => $platformSettings->isRegistrationOpen(),
        ]);
    }
}
