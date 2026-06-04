<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class BusinessAuditLogController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('view_audit_logs');

        $user = Auth::user();
        $filters = $request->only(['user_id', 'action', 'search', 'date_from', 'date_to', 'type']);

        $logs = AuditLog::query()
            ->with(['user'])
            ->filtered($filters, (int) $user->business_id)
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $staff = User::query()
            ->where('business_id', $user->business_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $actions = AuditLog::query()
            ->where('business_id', $user->business_id)
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('audit-logs.index', compact('logs', 'staff', 'actions', 'filters'));
    }
}
