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
            'siteConfig' => $website->siteConfig(),
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

    public function updateSections(Request $request)
    {
        $this->authorizeOwner();

        $business = Auth::user()->business;
        $website = $this->websites->forBusiness($business);

        $this->websites->updateSections($website, $request->all());

        return back()->with('success', 'Section visibility saved.');
    }

    public function updatePaymentQr(Request $request)
    {
        $this->authorizeOwner();

        $business = Auth::user()->business;
        $website = $this->websites->forBusiness($business);

        $request->validate([
            'merchant_name' => 'nullable|string|max:120',
            'merchant_id' => 'nullable|string|max:40',
            'payment_title' => 'nullable|string|max:80',
            'payment_footer_text' => 'nullable|string|max:160',
        ]);

        $this->websites->updatePaymentStandee($website, $request->all());

        return back()->with('success', 'Shop website QR standee saved.');
    }

    public function printPaymentQr()
    {
        $this->authorizeOwner();

        $business = Auth::user()->business;
        $website = $this->websites->forBusiness($business);
        $payment = $website->siteConfig()['payment'];

        // QR always encodes the public website URL (not a payment till).
        $qrUrl = $website->qrCodeUrl(480);

        $merchantName = trim((string) ($payment['merchant_name'] ?? ''));
        if ($merchantName === '') {
            $merchantName = strtoupper((string) $business->name);
        }

        return view('business-website.print-payment-qr', [
            'business' => $business,
            'website' => $website,
            'payment' => $payment,
            'qrUrl' => $qrUrl,
            'siteUrl' => $website->publicUrl(),
            'merchantName' => $merchantName,
            'merchantId' => trim((string) ($payment['merchant_id'] ?? '')),
            'title' => $this->standeeTitle($payment),
            'footerText' => $this->standeeFooter($payment),
        ]);
    }

    private function standeeTitle(array $payment): string
    {
        $title = trim((string) ($payment['title'] ?? ''));
        if ($title === '' || str_contains(strtolower($title), 'lipa')) {
            return 'Visit our website';
        }

        return $title;
    }

    private function standeeFooter(array $payment): string
    {
        $footer = trim((string) ($payment['footer_text'] ?? ''));
        if ($footer === '' || str_contains(strtolower($footer), 'lipa') || str_contains(strtolower($footer), 'benki') || str_contains(strtolower($footer), 'products')) {
            return 'Scan to open';
        }

        return $footer;
    }

    public function editPage(Request $request, string $page)
    {
        $this->authorizeOwner();

        if (! in_array($page, \App\Models\BusinessWebsite::PAGE_KEYS, true)) {
            abort(404);
        }

        $section = $this->resolveEditSection($page, $request->query('section'));

        $business = Auth::user()->business;
        $website = $this->websites->forBusiness($business);
        $content = $website->page($page);
        if ($content === []) {
            $defaults = $this->websites->defaultPages($business);
            $content = $defaults[$page] ?? [];
        }

        $catalogCategories = collect();
        $uncategorizedItems = collect();
        if ($page === 'products') {
            $catalogCategories = \App\Models\Category::query()
                ->where('business_id', $business->id)
                ->with(['items' => fn ($q) => $q->orderBy('name')])
                ->orderBy('name')
                ->get();
            $uncategorizedItems = \App\Models\Item::query()
                ->where('business_id', $business->id)
                ->whereNull('category_id')
                ->orderBy('name')
                ->get();
        }

        return view('business-website.edit-page', [
            'business' => $business,
            'website' => $website,
            'page' => $page,
            'section' => $section,
            'pageLabel' => $this->sectionLabel($page, $section),
            'content' => $content,
            'catalogCategories' => $catalogCategories,
            'uncategorizedItems' => $uncategorizedItems,
        ]);
    }

    public function updatePage(Request $request, string $page)
    {
        $this->authorizeOwner();

        if (! in_array($page, \App\Models\BusinessWebsite::PAGE_KEYS, true)) {
            abort(404);
        }

        $section = $this->resolveEditSection($page, $request->input('section'));

        $business = Auth::user()->business;
        $website = $this->websites->forBusiness($business);

        if ($page === 'home' && in_array($section, ['hero', 'home', null], true)) {
            $request->validate([
                // Accept large phone/HDR photos; we compress them on save.
                'hero_image' => 'nullable|mimes:jpg,jpeg,png,webp|max:20480',
            ]);
            if ($request->hasFile('hero_image') && ! $request->file('hero_image')->isValid()) {
                $err = $request->file('hero_image')->getErrorMessage() ?: 'Hero image upload failed.';

                return back()
                    ->withErrors(['hero_image' => $err.' Try a JPG under 20MB.'])
                    ->withInput();
            }
        }

        if ($page === 'home' && in_array($section, ['gallery', 'home', null], true)) {
            $request->validate([
                'gallery_images' => 'nullable|array|max:8',
                'gallery_images.*' => 'image|max:4096',
            ]);
        }

        if ($page === 'home' && in_array($section, ['promo', 'home', null], true)) {
            $request->validate([
                'promo_title' => 'nullable|string|max:120',
                'promo_items' => 'nullable|string|max:2000',
            ]);
        }

        if ($page === 'contact') {
            $request->validate([
                'open_time' => ['nullable', 'regex:/^([01]?\d|2[0-3]):([0-5]\d)$/'],
                'close_time' => ['nullable', 'regex:/^([01]?\d|2[0-3]):([0-5]\d)$/'],
            ]);
        }

        if ($page === 'products') {
            $request->validate([
                'hidden_category_ids' => 'nullable|array',
                'hidden_category_ids.*' => 'integer',
                'hidden_item_ids' => 'nullable|array',
                'hidden_item_ids.*' => 'integer',
            ]);
        }

        $galleryImages = [];
        if ($page === 'home' && in_array($section, ['gallery', 'home', null], true)) {
            $uploaded = $request->file('gallery_images', []);
            if (! is_array($uploaded)) {
                $uploaded = $uploaded ? [$uploaded] : [];
            }
            $galleryImages = array_values(array_filter($uploaded));
        }

        $heroFile = $request->file('hero_image');

        try {
            $website = $this->websites->updatePage(
                $website,
                $page,
                $request->all(),
                $heroFile,
                $galleryImages,
                $section
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withErrors(['hero_image' => 'Could not save the image. '.$e->getMessage()])
                ->withInput();
        }

        $message = $this->sectionLabel($page, $section).' saved.';
        if ($page === 'home' && $heroFile && $website->uploadedMediaUrl($website->page('home')['hero_image_path'] ?? null)) {
            $message .= ' Hero image updated.';
        } elseif ($page === 'home' && $heroFile) {
            return back()
                ->withErrors(['hero_image' => 'Upload finished but the image file is not readable yet. Check public_html/uploads/business-website permissions.'])
                ->withInput();
        }

        return redirect()
            ->route('business-website.edit-page', array_filter(['page' => $page, 'section' => $section]))
            ->with('success', $message);
    }

    private function resolveEditSection(string $page, mixed $section): ?string
    {
        $section = is_string($section) ? strtolower(trim($section)) : null;
        if ($section === '' || $section === null) {
            return $page === 'home' ? 'hero' : null;
        }

        $allowed = match ($page) {
            'home' => ['hero', 'promo', 'gallery', 'home'],
            'contact' => ['hours', 'contact'],
            default => [],
        };

        if ($allowed === [] || ! in_array($section, $allowed, true)) {
            return $page === 'home' ? 'hero' : null;
        }

        return $section === 'contact' ? null : $section;
    }

    private function sectionLabel(string $page, ?string $section): string
    {
        return match ($section) {
            'hero' => 'Hero',
            'promo' => 'Promo / specials',
            'gallery' => 'Shop photo gallery',
            'hours' => 'Open now badge / hours',
            default => $this->pageLabel($page),
        };
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
            'home' => 'Hero / Home',
            'products' => 'Products',
            'about' => 'About',
            'contact' => 'Contact',
            'order' => 'Order',
            default => ucfirst($page),
        };
    }
}
