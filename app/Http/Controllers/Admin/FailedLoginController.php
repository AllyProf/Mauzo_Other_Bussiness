<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\EnsuresPlatformAdmin;
use App\Models\AuditLog;
use App\Models\BlockedIp;
use App\Models\FailedLoginAttempt;
use App\Models\User;
use App\Services\LoginSecurityService;
use Illuminate\Http\Request;

class FailedLoginController extends Controller
{
    use EnsuresPlatformAdmin;

    public function index(Request $request)
    {
        $this->ensurePlatformAdmin('security');

        $attempts = FailedLoginAttempt::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim($request->search);
                $q->where(function ($inner) use ($search) {
                    $inner->where('login_identifier', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('attempted_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('attempted_at', '<=', $request->date_to))
            ->orderByDesc('attempted_at')
            ->paginate(50)
            ->withQueryString();

        $lockedUsers = User::query()
            ->with('business:id,name')
            ->where('locked_until', '>', now())
            ->orderByDesc('locked_until')
            ->get();

        $blockedIps = BlockedIp::where('blocked_until', '>', now())
            ->orderByDesc('blocked_until')
            ->get();

        return view('admin.security.failed-logins', compact('attempts', 'lockedUsers', 'blockedIps'));
    }

    public function unlock(User $user)
    {
        $this->ensurePlatformAdmin('security');

        $user->clearLoginLock();
        AuditLog::log('ACCOUNT_UNLOCKED', "Unlocked login for {$user->email}", $user->business_id);

        return back()->with('success', "{$user->name} ({$user->email}) has been unlocked and can sign in again.");
    }

    public function unblockIp(BlockedIp $blockedIp, LoginSecurityService $security)
    {
        $this->ensurePlatformAdmin('security');

        $security->unblockIp($blockedIp);
        AuditLog::log('IP_UNBLOCKED', "Unblocked IP {$blockedIp->ip_address}");

        return back()->with('success', "IP {$blockedIp->ip_address} has been unblocked.");
    }
}
