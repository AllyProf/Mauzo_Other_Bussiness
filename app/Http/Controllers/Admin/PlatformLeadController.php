<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\EnsuresPlatformAdmin;
use App\Models\PlatformLead;
use Illuminate\Http\Request;

class PlatformLeadController extends Controller
{
    use EnsuresPlatformAdmin;

    public function index(Request $request)
    {
        $this->ensurePlatformAdmin('leads');

        $leads = PlatformLead::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim($request->search);
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.leads.index', compact('leads'));
    }

    public function update(Request $request, PlatformLead $lead)
    {
        $this->ensurePlatformAdmin('leads');

        $validated = $request->validate([
            'status' => 'required|in:new,contacted,converted,closed',
        ]);

        $lead->update(['status' => $validated['status']]);

        return back()->with('success', 'Lead updated.');
    }
}
