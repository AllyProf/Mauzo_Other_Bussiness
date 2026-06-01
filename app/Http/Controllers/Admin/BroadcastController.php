<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use Illuminate\Http\Request;

class BroadcastController extends Controller
{
    public function index()
    {
        $broadcasts = Broadcast::latest()->get();
        return view('admin.broadcasts.index', compact('broadcasts'));
    }

    public function store(Request $request)
    {
        $request->validate(['message' => 'required|string']);
        
        // Deactivate old broadcasts
        Broadcast::where('is_active', true)->update(['is_active' => false]);
        
        Broadcast::create(['message' => $request->message, 'is_active' => true]);

        return redirect()->back()->with('success', 'Broadcast message sent to all businesses.');
    }

    public function destroy(Broadcast $broadcast)
    {
        $broadcast->delete();
        return redirect()->back()->with('success', 'Broadcast deleted.');
    }
}
