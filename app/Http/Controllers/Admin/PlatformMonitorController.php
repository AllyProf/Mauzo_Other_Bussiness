<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\EnsuresPlatformAdmin;
use App\Services\BusinessHealthService;
use Illuminate\Http\Request;

class PlatformMonitorController extends Controller
{
    use EnsuresPlatformAdmin;

    public function index(Request $request, BusinessHealthService $health)
    {
        $this->ensurePlatformAdmin('monitor');

        $tab = $request->get('tab', 'usage');
        $snapshots = $health->allBusinessSnapshots();

        $smsRows = $snapshots->sortByDesc(fn ($row) => $row['sms']['percent'])->values();
        $storageRows = $snapshots->sortByDesc(fn ($row) => $row['storage']['percent'])->values();

        return view('admin.monitor.index', compact('tab', 'snapshots', 'smsRows', 'storageRows'));
    }
}
