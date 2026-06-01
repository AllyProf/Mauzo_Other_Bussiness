<?php

namespace App\Http\Controllers;

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
}
