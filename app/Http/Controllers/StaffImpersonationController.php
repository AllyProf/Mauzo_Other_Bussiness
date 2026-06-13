<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class StaffImpersonationController extends Controller
{
    public function start(User $employee)
    {
        if (Auth::user()->role !== 'owner') {
            abort(403, 'Only the business owner can view the system as staff.');
        }

        if (session()->has('impersonate_staff_original_user')) {
            return redirect()->back()->with('error', 'You are already viewing as a staff member. Switch back first.');
        }

        if (! $this->canImpersonateEmployee($employee)) {
            return redirect()->back()->with('error', 'You cannot view the system as this user.');
        }

        session(['impersonate_staff_original_user' => Auth::id()]);

        AuditLog::log(
            'IMPERSONATE_STAFF_START',
            'Owner started viewing as staff: '.$employee->name.' ('.$employee->email.')',
            $employee->business_id
        );

        Auth::login($employee);

        if ($employee->branch_id) {
            active_branch_service()->setActiveBranch((int) $employee->branch_id);
        }

        return redirect('/home')->with('success', "You are now viewing as {$employee->name}.");
    }

    private function canImpersonateEmployee(User $employee): bool
    {
        if ($employee->id === Auth::id()) {
            return false;
        }

        if (! $employee->isActiveAccount()) {
            return false;
        }

        if ($employee->role !== 'staff') {
            return false;
        }

        if ((int) $employee->business_id !== current_business_id()) {
            return false;
        }

        return true;
    }
}
