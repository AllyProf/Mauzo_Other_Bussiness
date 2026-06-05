<?php

namespace App\Http\Controllers;

use App\Services\SystemTourService;
use Illuminate\Http\Request;

class SystemTourController extends Controller
{
    public function complete(Request $request, SystemTourService $tour)
    {
        $tour->markCompleted($request->user());

        return response()->json(['ok' => true]);
    }

    public function skip(Request $request, SystemTourService $tour)
    {
        $tour->markSkipped($request->user());

        return response()->json(['ok' => true]);
    }

    public function replay(SystemTourService $tour)
    {
        $user = auth()->user();

        if (! $tour->resolveTourKey($user)) {
            return redirect()->back()->with('error', 'System guide is not available for your account.');
        }

        $tour->queueReplay($user);

        return redirect()->to($user->defaultLandingUrl())
            ->with('info', 'System guide will start shortly on your dashboard.');
    }
}
