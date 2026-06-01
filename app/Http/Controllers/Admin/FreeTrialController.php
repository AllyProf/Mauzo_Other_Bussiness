<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Plan;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Carbon\Carbon;

class FreeTrialController extends Controller
{
    public function index()
    {
        // Businesses on a free plan (price = 0) or expiring within 14 days
        $trialBusinesses = Business::with(['plan', 'users'])
            ->whereHas('plan', function ($q) {
                $q->where('price', 0);
            })
            ->orWhere(function ($q) {
                $q->whereNotNull('expiry_date')
                  ->whereDate('expiry_date', '>=', Carbon::now())
                  ->whereDate('expiry_date', '<=', Carbon::now()->addDays(14));
            })
            ->with('plan')
            ->get();

        $paidPlans = Plan::where('price', '>', 0)->get();

        return view('admin.free_trials.index', compact('trialBusinesses', 'paidPlans'));
    }

    public function extendTrial(Request $request, Business $business)
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:365',
        ]);

        $currentExpiry = $business->expiry_date ? Carbon::parse($business->expiry_date) : Carbon::now();
        $newExpiry = $currentExpiry->addDays($request->days);

        $business->update(['expiry_date' => $newExpiry->toDateString()]);

        AuditLog::log('EXTEND_TRIAL', "Extended trial for {$business->name} by {$request->days} day(s). New expiry: {$newExpiry->format('M d, Y')}");

        return redirect()->back()->with('success', "Trial extended by {$request->days} day(s) for {$business->name}.");
    }

    public function convertToPaid(Request $request, Business $business)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'expiry_date' => 'required|date|after:today',
        ]);

        $plan = Plan::find($request->plan_id);
        $business->update([
            'plan_id' => $request->plan_id,
            'expiry_date' => $request->expiry_date,
            'is_active' => true,
        ]);

        AuditLog::log('CONVERT_TO_PAID', "Converted {$business->name} to paid plan: {$plan->name}. Expiry: {$request->expiry_date}");

        return redirect()->back()->with('success', "{$business->name} has been converted to the {$plan->name} plan.");
    }
}
