<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\EnsuresPlatformAdmin;
use App\Models\Business;
use App\Services\OnboardingService;

class BusinessOnboardingController extends Controller
{
    use EnsuresPlatformAdmin;

    public function show(Business $business, OnboardingService $onboarding)
    {
        $this->ensurePlatformAdmin('onboarding');

        $onboarding->syncDetectedSteps($business);

        return view('admin.onboarding.show', [
            'business' => $business->load('plan', 'ownerUser'),
            'checklist' => $onboarding->checklist($business),
            'progress' => $onboarding->progressPercent($business),
        ]);
    }
}
