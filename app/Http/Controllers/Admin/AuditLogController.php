<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\EnsuresPlatformAdmin;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogController extends Controller
{
    use EnsuresPlatformAdmin;

    public function index(Request $request)
    {
        $this->ensurePlatformAdmin('audit-logs');

        $filters = $this->filtersFromRequest($request);
        $logs = $this->logQuery($filters)->paginate(50)->withQueryString();

        $businesses = Business::query()->orderBy('name')->get(['id', 'name']);

        $users = User::query()
            ->when($request->filled('business_id'), fn ($q) => $q->where('business_id', $request->business_id))
            ->whereNotNull('business_id')
            ->orderBy('name')
            ->get(['id', 'name', 'business_id']);

        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('admin.audit_logs.index', compact('logs', 'businesses', 'users', 'actions', 'filters'));
    }

    public function feed(Request $request)
    {
        $this->ensurePlatformAdmin('audit-logs');

        $filters = $this->filtersFromRequest($request);
        $logs = $this->logQuery($filters)->limit(50)->get();

        return response()->json([
            'html' => view('admin.audit_logs.partials.rows', compact('logs'))->render(),
            'latest_id' => $logs->first()?->id,
            'count' => $logs->count(),
            'updated_at' => now()->format('H:i:s'),
        ]);
    }

    public function show(AuditLog $auditLog)
    {
        $this->ensurePlatformAdmin('audit-logs');

        $auditLog->load(['user', 'business']);
        $location = $auditLog->ipLocation();

        return response()->json([
            'id' => $auditLog->id,
            'created_at' => $auditLog->created_at?->format('d M Y, H:i:s'),
            'action' => $auditLog->action,
            'action_label' => $auditLog->actionLabel(),
            'badge_class' => $auditLog->badgeClass(),
            'description' => $auditLog->description,
            'business' => $auditLog->business?->name ?? 'Platform',
            'user' => $auditLog->user?->name ?? 'System',
            'user_role' => $auditLog->user?->displayRoleName() ?? '—',
            'ip_address' => $auditLog->ip_address ?? '—',
            'ip_location' => $location,
            'user_agent' => $auditLog->user_agent ?? '—',
        ]);
    }

    public function export(Request $request)
    {
        $this->ensurePlatformAdmin('audit-logs');

        $filters = $this->filtersFromRequest($request);
        $filename = 'audit-logs-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Date', 'Action', 'User', 'Business', 'Details', 'IP', 'Location', 'User Agent']);

            $this->logQuery($filters)->chunk(500, function ($logs) use ($handle) {
                $geo = app(\App\Services\IpGeolocationService::class);
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->created_at?->format('Y-m-d H:i:s'),
                        $log->action,
                        $log->user?->name,
                        $log->business?->name,
                        $log->description,
                        $log->ip_address,
                        $geo->formatLabel($log->ip_address),
                        $log->user_agent,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function filtersFromRequest(Request $request): array
    {
        return $request->only(['business_id', 'user_id', 'action', 'search', 'date_from', 'date_to', 'type']);
    }

    private function logQuery(array $filters)
    {
        return AuditLog::query()
            ->with(['user', 'business'])
            ->filtered($filters)
            ->latest();
    }
}
