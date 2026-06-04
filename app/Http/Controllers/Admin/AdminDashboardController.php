<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\EnsuresPlatformAdmin;
use App\Services\AdminDashboardService;

class AdminDashboardController extends Controller
{
    use EnsuresPlatformAdmin;

    public function index(AdminDashboardService $dashboard)
    {
        $this->ensurePlatformAdmin('dashboard');

        return view('admin.dashboard.index', [
            'metrics' => $dashboard->metrics(),
        ]);
    }
}
