<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\PlanFeatureService;
use App\Services\PlatformBillingService;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    public function expired(PlatformBillingService $billing)
    {
        $business = Auth::user()->business?->load('plan');

        if (! $business) {
            abort(403);
        }

        $overview = $billing->subscriptionOverview($business);

        return view('errors.subscription-expired', compact('business', 'overview'));
    }

    public function upgrade(PlatformBillingService $billing, PlanFeatureService $planFeatures)
    {
        $user = Auth::user();

        if (! $user || $user->role === 'super_admin') {
            return redirect()->route('admin.plans.index');
        }

        $business = $user->business?->load('plan');

        if (! $business) {
            abort(403);
        }

        $overview = $billing->subscriptionOverview($business);
        $plans = Plan::orderBy('price')->get();
        $disabledFeatures = $planFeatures->disabledFeaturesForBusiness($business);
        $featureGroups = $planFeatures->groups();

        return view('subscription.upgrade', compact(
            'business',
            'overview',
            'plans',
            'disabledFeatures',
            'featureGroups',
            'planFeatures',
        ));
    }
}
