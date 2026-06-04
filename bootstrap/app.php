<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'check.subscription' => \App\Http\Middleware\CheckSubscription::class,
            'check.user.active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'check.plan.feature' => \App\Http\Middleware\CheckPlanFeature::class,
            'check.business.retail' => \App\Http\Middleware\CheckBusinessOperationMode::class.':retail',
            'check.business.services' => \App\Http\Middleware\CheckBusinessOperationMode::class.':services',
            'check.platform.admin' => \App\Http\Middleware\CheckPlatformAdmin::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\CheckMaintenanceMode::class,
            \App\Http\Middleware\LogBusinessActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
