<?php

namespace App\Http\Controllers;

use App\Services\ActiveBusinessService;
use Illuminate\Http\Request;

class BusinessSwitchController extends Controller
{
    public function switch(Request $request, ActiveBusinessService $businessService)
    {
        if (auth()->user()->role !== 'owner') {
            abort(403);
        }

        $request->validate([
            'business_id' => 'required|integer',
        ]);

        $businessService->setActiveBusiness((int) $request->business_id);

        return redirect()->to($request->headers->get('referer', url('/home')))
            ->with('success', 'Switched to '.$businessService->activeBusinessLabel().'.');
    }
}
