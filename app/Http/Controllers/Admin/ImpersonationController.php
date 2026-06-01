<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Business;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function impersonate(Business $business)
    {
        $owner = User::where('business_id', $business->id)->where('role', 'owner')->first();

        if (!$owner) {
            return redirect()->back()->with('error', 'No owner account found for this business.');
        }

        // Store the original Admin ID in session so we can switch back
        session(['impersonate_original_user' => Auth::id()]);
        
        AuditLog::log('IMPERSONATE_START', "Admin started impersonating business: {$business->name} (Owner: {$owner->email})");
        Auth::login($owner);

        return redirect('/home')->with('success', "You are now logged in as {$business->name}");
    }

    public function stopImpersonating()
    {
        $adminId = session('impersonate_original_user');
        
        if ($adminId) {
            $businessName = Auth::user()->business ? Auth::user()->business->name : 'Unknown';
            $admin = User::find($adminId);
            Auth::login($admin);
            session()->forget('impersonate_original_user');

            AuditLog::log('IMPERSONATE_STOP', "Admin stopped impersonating business: {$businessName}");
            
            return redirect()->route('admin.businesses.index')->with('success', 'Switched back to Software Owner account.');
        }

        return redirect('/home');
    }
}
