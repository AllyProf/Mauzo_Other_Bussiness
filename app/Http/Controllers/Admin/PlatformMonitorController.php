<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\EnsuresPlatformAdmin;
use App\Models\AuditLog;
use App\Services\BusinessHealthService;
use App\Services\PlatformSettingsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PlatformMonitorController extends Controller
{
    use EnsuresPlatformAdmin;

    public function index(Request $request, BusinessHealthService $health)
    {
        $this->ensurePlatformAdmin('monitor');

        $tab = $request->get('tab', 'usage');

        if ($tab === 'activity_share') {
            $date = $request->get('date', now()->format('Y-m-d'));

            $rawLogs = AuditLog::query()
                ->whereDate('created_at', $date)
                ->selectRaw('business_id, count(*) as count')
                ->groupBy('business_id')
                ->with('business')
                ->get();

            $totalActions = $rawLogs->sum('count');

            $leaderboard = $rawLogs->map(function ($row) use ($totalActions) {
                return [
                    'business'      => $row->business,
                    'business_name' => $row->business ? $row->business->name : 'System / Platform Admins',
                    'count'         => $row->count,
                    'percent'       => $totalActions > 0 ? round(($row->count / $totalActions) * 100, 1) : 0,
                    'business_id'   => $row->business_id,
                ];
            })->sortByDesc('count')->values();

            return view('admin.monitor.index', compact('tab', 'date', 'totalActions', 'leaderboard'));
        }

        if ($tab === 'server_health') {
            $settings = app(PlatformSettingsService::class);

            // Cron health
            $lastCronRaw = $settings->get('scheduler_last_run_at');
            $lastCronAt  = $lastCronRaw ? Carbon::parse($lastCronRaw) : null;
            $cronStatus  = $lastCronAt && $lastCronAt->diffInMinutes(now()) <= 2 ? 'ok' : ($lastCronAt ? 'stale' : 'unknown');

            // Queue jobs
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs  = 0;
            try {
                $failedJobs = DB::table('failed_jobs')->count();
            } catch (\Throwable) {}

            // DB stats
            $driver = config('database.default', 'mysql');
            $dbName = config("database.connections.{$driver}.database", 'N/A');
            $dbSize = 0;
            try {
                $row = DB::select("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = ?", [$dbName]);
                $dbSize = $row[0]->size_mb ?? 0;
            } catch (\Throwable) {}

            // Cache driver
            $cacheDriver = config('cache.default', 'file');

            // Laravel version & PHP
            $laravelVersion = app()->version();
            $phpVersion = PHP_VERSION;

            // Storage usage (public disk)
            $storagePath = storage_path('app/public');
            $storageMb = 0;
            if (is_dir($storagePath)) {
                try {
                    $size = 0;
                    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($storagePath, \FilesystemIterator::SKIP_DOTS)) as $f) {
                        $size += $f->getSize();
                    }
                    $storageMb = round($size / 1024 / 1024, 2);
                } catch (\Throwable) {}
            }

            return view('admin.monitor.index', compact(
                'tab', 'lastCronAt', 'cronStatus',
                'pendingJobs', 'failedJobs', 'dbSize', 'dbName',
                'cacheDriver', 'laravelVersion', 'phpVersion', 'storageMb', 'driver'
            ));
        }

        $snapshots   = $health->allBusinessSnapshots();
        $smsRows     = $snapshots->sortByDesc(fn ($row) => $row['sms']['percent'])->values();
        $storageRows = $snapshots->sortByDesc(fn ($row) => $row['storage']['percent'])->values();

        return view('admin.monitor.index', compact('tab', 'snapshots', 'smsRows', 'storageRows'));
    }
}
