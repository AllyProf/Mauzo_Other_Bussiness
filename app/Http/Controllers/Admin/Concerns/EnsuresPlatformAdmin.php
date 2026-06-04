<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Services\PlatformAdminService;
use Illuminate\Support\Facades\Auth;

trait EnsuresPlatformAdmin
{
    protected function ensurePlatformAdmin(?string $permission = null): void
    {
        $user = Auth::user();
        $service = app(PlatformAdminService::class);

        if (! $service->isPlatformAdmin($user)) {
            abort(403);
        }

        if ($permission && ! $service->canAccess($user, $permission)) {
            abort(403);
        }
    }
}
