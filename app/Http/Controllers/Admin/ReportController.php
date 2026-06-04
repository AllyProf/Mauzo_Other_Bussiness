<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\EnsuresPlatformAdmin;
use App\Services\PlatformReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use EnsuresPlatformAdmin;

    public function index(Request $request, PlatformReportService $reports)
    {
        $this->ensurePlatformAdmin('reports');

        $months = (int) $request->input('months', 6);
        $data = $reports->dashboard($months);

        return view('admin.reports.index', compact('data', 'months'));
    }
}
