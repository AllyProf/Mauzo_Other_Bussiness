<?php

namespace App\Http\Controllers;

use App\Services\BusinessWebsiteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class BusinessWebsiteController extends Controller
{
    public function __construct(private BusinessWebsiteService $websites)
    {
    }

    public function index()
    {
        $this->authorizeOwner();

        $business = Auth::user()->business;
        $website = $this->websites->forBusiness($business);

        return view('business-website.index', [
            'business' => $business,
            'website' => $website,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $this->authorizeOwner();

        $business = Auth::user()->business;
        $website = $this->websites->forBusiness($business);

        $request->validate([
            'slug' => 'required|string|max:80|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            'tagline' => 'nullable|string|max:255',
            'primary_color' => 'nullable|string|max:20',
            'is_published' => 'nullable|boolean',
            'whatsapp' => 'nullable|string|max:50',
            'facebook' => 'nullable|url|max:255',
            'instagram' => 'nullable|url|max:255',
        ]);

        try {
            $this->websites->updateSettings($website, $request->all());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'Website settings saved.');
    }

    public function editPage(string $page)
    {
        $this->authorizeOwner();

        if (! in_array($page, \App\Models\BusinessWebsite::PAGE_KEYS, true)) {
            abort(404);
        }

        $business = Auth::user()->business;
        $website = $this->websites->forBusiness($business);
        $content = $website->page($page);
        if ($content === []) {
            $defaults = $this->websites->defaultPages($business);
            $content = $defaults[$page] ?? [];
        }

        return view('business-website.edit-page', [
            'business' => $business,
            'website' => $website,
            'page' => $page,
            'pageLabel' => $this->pageLabel($page),
            'content' => $content,
        ]);
    }

    public function updatePage(Request $request, string $page)
    {
        $this->authorizeOwner();

        if (! in_array($page, \App\Models\BusinessWebsite::PAGE_KEYS, true)) {
            abort(404);
        }

        $business = Auth::user()->business;
        $website = $this->websites->forBusiness($business);

        try {
            $this->websites->updatePage($website, $page, $request->all());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('business-website.edit-page', $page)
            ->with('success', $this->pageLabel($page).' page saved.');
    }

    private function authorizeOwner(): void
    {
        if (Auth::user()->role !== 'owner') {
            abort(403, 'Only the business owner can manage the public website.');
        }
    }

    private function pageLabel(string $page): string
    {
        return match ($page) {
            'home' => 'Home',
            'products' => 'Products',
            'about' => 'About',
            'contact' => 'Contact',
            'order' => 'Order',
            default => ucfirst($page),
        };
    }
}
