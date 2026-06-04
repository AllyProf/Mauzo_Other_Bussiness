<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\PlatformSettingsService;
use Illuminate\Console\Command;

class PurgeAuditLogs extends Command
{
    protected $signature = 'platform:purge-audit-logs';

    protected $description = 'Delete audit logs older than the configured retention period';

    public function handle(PlatformSettingsService $settings): int
    {
        $days = max(30, (int) $settings->get('audit_log_retention_days', 365));
        $cutoff = now()->subDays($days);

        $deleted = AuditLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Deleted {$deleted} audit log(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
