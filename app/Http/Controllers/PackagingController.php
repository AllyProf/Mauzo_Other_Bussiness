<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Packaging;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PackagingController extends Controller
{
    public function index()
    {
        $this->authorizeAny(['manage_packaging', 'view_inventory']);

        $business = Business::findOrFail(Auth::user()->business_id);
        $packagings = Packaging::where('business_id', $business->id)->orderBy('name')->get();
        $importedTypes = $business->categoryBusinessTypesList();
        $businessTemplates = config('category_templates', []);
        $packagingTemplates = config('packaging_templates', []);

        $unitImportOptions = collect($importedTypes)->map(function (array $type) use ($businessTemplates, $packagingTemplates) {
            $key = $type['key'] ?? '';
            $template = $businessTemplates[$key] ?? null;
            $units = $packagingTemplates[$key] ?? $packagingTemplates['_default'] ?? [];

            return [
                'key' => $key,
                'label' => $type['label'] ?? $template['label'] ?? 'Business',
                'icon' => $template['icon'] ?? 'fa-store',
                'units' => $units,
                'unit_count' => count($units),
                'units_preview' => implode(', ', $units),
                'is_custom' => str_starts_with($key, 'custom:'),
            ];
        })->values();

        return view('registration.packagings.index', compact(
            'packagings',
            'importedTypes',
            'unitImportOptions'
        ));
    }

    public function store(Request $request)
    {
        $this->authorizeAny(['manage_packaging', 'add_items']);
        $request->validate(['name' => 'required|string|max:255']);

        Packaging::create([
            'business_id' => Auth::user()->business_id,
            'name' => $request->name,
        ]);

        return redirect()->back()->with('success', 'Packaging unit added successfully.');
    }

    public function update(Request $request, Packaging $packaging)
    {
        $this->authorizeAny(['manage_packaging', 'edit_items']);
        if ($packaging->business_id != Auth::user()->business_id) {
            abort(403);
        }
        $request->validate(['name' => 'required|string|max:255']);
        $packaging->update(['name' => $request->name]);

        return redirect()->back()->with('success', 'Packaging unit updated.');
    }

    public function destroy(Packaging $packaging)
    {
        $this->authorizeAny(['manage_packaging', 'delete_items']);
        if ($packaging->business_id != Auth::user()->business_id) {
            abort(403);
        }
        $packaging->delete();

        return redirect()->back()->with('success', 'Packaging unit deleted.');
    }

    public function importTemplates(Request $request)
    {
        $this->authorizeAny(['manage_packaging', 'add_items']);

        $business = Business::findOrFail(Auth::user()->business_id);
        $importedTypes = $business->categoryBusinessTypesList();

        if (empty($importedTypes)) {
            return redirect()->back()->with(
                'error',
                'Please import a business type on the Categories page first.'
            );
        }

        $allowedKeys = collect($importedTypes)->pluck('key')->filter()->all();
        $businessTypeKey = $request->input('business_type_key');

        if ($businessTypeKey === 'all') {
            $keysToImport = $allowedKeys;
        } elseif (in_array($businessTypeKey, $allowedKeys, true)) {
            $keysToImport = [$businessTypeKey];
        } else {
            return redirect()->back()->with('error', 'Please select a valid business type.');
        }

        $units = [];
        foreach ($keysToImport as $key) {
            $units = array_merge($units, $this->unitsForBusinessType($key));
        }
        $units = array_values(array_unique($units));

        foreach ($units as $unit) {
            Packaging::firstOrCreate([
                'business_id' => $business->id,
                'name' => $unit,
            ]);
        }

        $label = $businessTypeKey === 'all'
            ? 'all your business types'
            : (collect($importedTypes)->firstWhere('key', $businessTypeKey)['label'] ?? 'business type');

        return redirect()->back()->with('success', "Packaging units for {$label} imported successfully.");
    }

    public function clearAll()
    {
        $this->authorizeAny(['manage_packaging', 'delete_items']);
        Packaging::where('business_id', Auth::user()->business_id)->delete();

        return redirect()->back()->with('success', 'All packaging units have been cleared.');
    }

    private function unitsForBusinessType(string $key): array
    {
        $packagingTemplates = config('packaging_templates', []);

        return $packagingTemplates[$key] ?? $packagingTemplates['_default'] ?? [];
    }
}
