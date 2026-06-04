<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\EnsuresPlatformAdmin;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminSessionController extends Controller
{
    use EnsuresPlatformAdmin;

    public function index()
    {
        $this->ensurePlatformAdmin('sessions');

        $sessions = DB::table('sessions')
            ->join('users', 'sessions.user_id', '=', 'users.id')
            ->whereIn('users.role', ['super_admin', 'platform_staff'])
            ->select([
                'sessions.id',
                'sessions.user_id',
                'sessions.ip_address',
                'sessions.user_agent',
                'sessions.last_activity',
                'users.name',
                'users.email',
            ])
            ->orderByDesc('sessions.last_activity')
            ->get()
            ->map(function ($session) {
                $session->last_activity_at = \Carbon\Carbon::createFromTimestamp($session->last_activity);
                $session->is_current = $session->id === session()->getId();

                return $session;
            });

        return view('admin.sessions.index', compact('sessions'));
    }

    public function destroy(string $sessionId)
    {
        $this->ensurePlatformAdmin('sessions');

        if ($sessionId === session()->getId()) {
            return back()->with('error', 'You cannot terminate your current session here. Use logout instead.');
        }

        $deleted = DB::table('sessions')->where('id', $sessionId)->delete();

        if ($deleted) {
            AuditLog::log('TERMINATE_ADMIN_SESSION', 'Terminated admin session '.$sessionId);
        }

        return back()->with('success', 'Session terminated.');
    }
}
