<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\EnsuresPlatformAdmin;
use App\Services\BusinessHealthService;

class RegionalReportController extends Controller
{
    use EnsuresPlatformAdmin;

    public function index(BusinessHealthService $health)
    {
        $this->ensurePlatformAdmin('regional');

        return view('admin.regional.index', [
            'data' => $health->regionalSummary(),
        ]);
    }
}
