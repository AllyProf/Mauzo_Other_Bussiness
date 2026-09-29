<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BroadcastController extends Controller
{
    public function index(Request $request)
    {
        $broadcasts = Broadcast::latest()->get();
        $editing = $request->filled('edit') ? Broadcast::find($request->integer('edit')) : null;

        return view('admin.broadcasts.index', compact('broadcasts', 'editing'));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        Broadcast::where('is_active', true)->update(['is_active' => false]);

        Broadcast::create($validated + ['is_active' => true]);

        return redirect()->route('admin.broadcasts.index')->with('success', 'Broadcast message sent to all businesses.');
    }

    public function update(Request $request, Broadcast $broadcast)
    {
        $broadcast->update($this->validatePayload($request));

        return redirect()->route('admin.broadcasts.index')->with('success', 'Broadcast updated.');
    }

    public function activate(Broadcast $broadcast)
    {
        Broadcast::where('is_active', true)->where('id', '!=', $broadcast->id)->update(['is_active' => false]);
        $broadcast->update(['is_active' => ! $broadcast->is_active]);

        return redirect()->route('admin.broadcasts.index')->with(
            'success',
            $broadcast->is_active ? 'Broadcast is now showing to all businesses.' : 'Broadcast hidden.'
        );
    }

    public function destroy(Broadcast $broadcast)
    {
        $broadcast->delete();

        return redirect()->route('admin.broadcasts.index')->with('success', 'Broadcast deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'message' => 'required|string|max:1000',
            'scroll_speed' => ['required', Rule::in(array_keys(Broadcast::SPEEDS))],
            'text_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'font_family' => ['required', Rule::in(array_keys(Broadcast::FONTS))],
            'font_size' => ['required', 'integer', Rule::in(Broadcast::FONT_SIZES)],
        ]);
    }
}
