<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlatformBillingInvoice;
use App\Services\PlanFeatureService;
use App\Services\PlatformBillingService;
use App\Services\PlatformInvoiceDocumentService;
use Illuminate\Http\Request;
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

    public function invoice(Request $request, PlatformBillingInvoice $invoice, PlatformInvoiceDocumentService $documents)
    {
        $user = Auth::user();

        if (! $user->business_id || (int) $invoice->business_id !== (int) $user->business_id) {
            abort(404);
        }

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($documents->renderPdf($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$documents->filename($invoice).'"',
        ]);
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
