<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\EnsuresPlatformAdmin;
use App\Services\RegistrationFunnelService;
use Illuminate\Http\Request;

class RegistrationFunnelController extends Controller
{
    use EnsuresPlatformAdmin;

    public function index(Request $request, RegistrationFunnelService $funnel)
    {
        $this->ensurePlatformAdmin('funnel');

        $days = (int) $request->get('days', 30);

        return view('admin.funnel.index', [
            'summary' => $funnel->summary($days),
            'days' => $days,
        ]);
    }
}
