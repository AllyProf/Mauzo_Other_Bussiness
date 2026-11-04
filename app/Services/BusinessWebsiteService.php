<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessWebsite;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BusinessWebsiteService
{
    public function forBusiness(Business $business): BusinessWebsite
    {
        $existing = BusinessWebsite::where('business_id', $business->id)->first();
        if ($existing) {
            return $existing;
        }

        return BusinessWebsite::create([
            'business_id' => $business->id,
            'slug' => $this->uniqueSlug($business->name),
            'is_published' => false,
            'tagline' => null,
            'primary_color' => '#940000',
            'social_links' => $this->defaultSocialLinks(),
            'pages' => $this->defaultPages($business),
        ]);
    }

    public function findPublishedBySlug(string $slug): ?BusinessWebsite
    {
        $website = $this->findBySlug($slug);

        return $website && $website->is_published ? $website : null;
    }

    public function findBySlug(string $slug): ?BusinessWebsite
    {
        $slug = Str::slug($slug);
        if ($slug === '') {
            return null;
        }

        return BusinessWebsite::query()
            ->where('slug', $slug)
            ->with('business')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateSettings(BusinessWebsite $website, array $input): BusinessWebsite
    {
        $slug = Str::slug($input['slug'] ?? $website->slug);
        if ($slug === '') {
            throw ValidationException::withMessages(['slug' => 'Choose a valid web address slug.']);
        }

        if (BusinessWebsite::where('slug', $slug)->where('id', '!=', $website->id)->exists()) {
            throw ValidationException::withMessages(['slug' => 'This web address is already taken.']);
        }

        $website->update([
            'slug' => $slug,
            'is_published' => filter_var($input['is_published'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'tagline' => $input['tagline'] ?? null,
            'primary_color' => $this->normalizeColor($input['primary_color'] ?? $website->primary_color),
            'social_links' => [
                'whatsapp' => trim((string) ($input['whatsapp'] ?? '')),
                'facebook' => trim((string) ($input['facebook'] ?? '')),
                'instagram' => trim((string) ($input['instagram'] ?? '')),
            ],
        ]);

        return $website->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateSections(BusinessWebsite $website, array $input): BusinessWebsite
    {
        $pages = $website->pages ?? [];
        $site = is_array($pages['site'] ?? null) ? $pages['site'] : [];
        $defaults = BusinessWebsite::defaultSiteConfig($website->business?->name);
        $sections = [];
        foreach (array_keys($defaults['sections']) as $key) {
            $sections[$key] = filter_var($input['section_'.$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
        $site['sections'] = $sections;
        $site['payment'] = array_merge($defaults['payment'], is_array($site['payment'] ?? null) ? $site['payment'] : []);
        $pages['site'] = $site;
        $website->update(['pages' => $pages]);

        return $website->fresh();
    }

    /**
     * Standee text labels only — QR always encodes the public website URL.
     *
     * @param  array<string, mixed>  $input
     */
    public function updatePaymentStandee(BusinessWebsite $website, array $input): BusinessWebsite
    {
        $pages = $website->pages ?? [];
        $site = is_array($pages['site'] ?? null) ? $pages['site'] : [];
        $defaults = BusinessWebsite::defaultSiteConfig($website->business?->name);
        $payment = array_merge($defaults['payment'], is_array($site['payment'] ?? null) ? $site['payment'] : []);

        $payment['merchant_name'] = strtoupper(trim((string) ($input['merchant_name'] ?? '')));
        $payment['merchant_id'] = trim((string) ($input['merchant_id'] ?? ''));
        $payment['title'] = trim((string) ($input['payment_title'] ?? $defaults['payment']['title']));
        $payment['footer_text'] = trim((string) ($input['payment_footer_text'] ?? $defaults['payment']['footer_text']));
        // Keep any legacy uploaded path but do not require/use it for the standee QR.
        $payment['qr_image_path'] = is_string($payment['qr_image_path'] ?? null) ? $payment['qr_image_path'] : null;

        if ($payment['title'] === '') {
            $payment['title'] = $defaults['payment']['title'];
        }
        if ($payment['footer_text'] === '') {
            $payment['footer_text'] = $defaults['payment']['footer_text'];
        }

        $site['payment'] = $payment;
        $site['sections'] = array_merge($defaults['sections'], is_array($site['sections'] ?? null) ? $site['sections'] : []);
        $pages['site'] = $site;
        $website->update(['pages' => $pages]);

        return $website->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<UploadedFile>  $galleryImages
     */
    public function updatePage(
        BusinessWebsite $website,
        string $pageKey,
        array $input,
        ?UploadedFile $heroImage = null,
        array $galleryImages = [],
        ?string $section = null
    ): BusinessWebsite {
        if (! in_array($pageKey, BusinessWebsite::PAGE_KEYS, true)) {
            throw ValidationException::withMessages(['page' => 'Unknown page.']);
        }

        $pages = $website->pages ?? [];
        $pages[$pageKey] = match ($pageKey) {
            'home' => $this->buildHomePageContent($website, $input, $heroImage, $galleryImages, $section),
            'about' => [
                'title' => trim((string) ($input['title'] ?? '')),
                'body' => trim((string) ($input['body'] ?? '')),
                'highlight_1_title' => trim((string) ($input['highlight_1_title'] ?? '')),
                'highlight_1_body' => trim((string) ($input['highlight_1_body'] ?? '')),
                'highlight_2_title' => trim((string) ($input['highlight_2_title'] ?? '')),
                'highlight_2_body' => trim((string) ($input['highlight_2_body'] ?? '')),
                'highlight_3_title' => trim((string) ($input['highlight_3_title'] ?? '')),
                'highlight_3_body' => trim((string) ($input['highlight_3_body'] ?? '')),
            ],
            'contact' => $this->buildContactPageContent($website, $input, $section),
            'order' => [
                'title' => trim((string) ($input['title'] ?? '')),
                'body' => trim((string) ($input['body'] ?? '')),
                'steps' => trim((string) ($input['steps'] ?? '')),
                'whatsapp_message' => trim((string) ($input['whatsapp_message'] ?? '')),
                'delivery_notes' => trim((string) ($input['delivery_notes'] ?? '')),
                'payment_notes' => trim((string) ($input['payment_notes'] ?? '')),
            ],
            'products' => $this->buildProductsPageContent($website, $input),
            default => [],
        };

        $website->update(['pages' => $pages]);

        return $website->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function buildProductsPageContent(BusinessWebsite $website, array $input): array
    {
        $businessId = (int) $website->business_id;

        $hiddenCategoryIds = collect($input['hidden_category_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($hiddenCategoryIds !== []) {
            $hiddenCategoryIds = Category::query()
                ->where('business_id', $businessId)
                ->whereIn('id', $hiddenCategoryIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        $hiddenItemIds = collect($input['hidden_item_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($hiddenItemIds !== []) {
            $hiddenItemIds = Item::query()
                ->where('business_id', $businessId)
                ->whereIn('id', $hiddenItemIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return [
            'title' => trim((string) ($input['title'] ?? '')),
            'body' => trim((string) ($input['body'] ?? '')),
            'show_prices' => filter_var($input['show_prices'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'show_out_of_stock' => filter_var($input['show_out_of_stock'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'hidden_category_ids' => $hiddenCategoryIds,
            'hidden_item_ids' => $hiddenItemIds,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function buildContactPageContent(BusinessWebsite $website, array $input, ?string $section = null): array
    {
        $existing = $website->page('contact');
        $base = [
            'title' => (string) ($existing['title'] ?? 'Contact'),
            'body' => (string) ($existing['body'] ?? ''),
            'hours' => (string) ($existing['hours'] ?? ''),
            'open_time' => $existing['open_time'] ?? '08:00',
            'close_time' => $existing['close_time'] ?? '20:00',
            'show_contact_form' => filter_var($existing['show_contact_form'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'show_map' => filter_var($existing['show_map'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'map_query' => (string) ($existing['map_query'] ?? ''),
            'map_embed_url' => (string) ($existing['map_embed_url'] ?? ''),
        ];

        if ($section === 'hours') {
            return array_merge($base, [
                'hours' => trim((string) ($input['hours'] ?? '')),
                'open_time' => $this->normalizeTime($input['open_time'] ?? ''),
                'close_time' => $this->normalizeTime($input['close_time'] ?? ''),
            ]);
        }

        return [
            'title' => trim((string) ($input['title'] ?? '')),
            'body' => trim((string) ($input['body'] ?? '')),
            'hours' => trim((string) ($input['hours'] ?? $base['hours'])),
            'open_time' => $this->normalizeTime($input['open_time'] ?? $base['open_time']),
            'close_time' => $this->normalizeTime($input['close_time'] ?? $base['close_time']),
            'show_contact_form' => filter_var($input['show_contact_form'] ?? $base['show_contact_form'], FILTER_VALIDATE_BOOLEAN),
            'show_map' => filter_var($input['show_map'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'map_query' => trim((string) ($input['map_query'] ?? '')),
            'map_embed_url' => $this->normalizeMapEmbedUrl((string) ($input['map_embed_url'] ?? '')),
        ];
    }

    private function normalizeMapEmbedUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Allow pasting a full iframe snippet and extract the src.
        if (preg_match('/src=["\']([^"\']+)["\']/i', $value, $m)) {
            $value = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        }

        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            return '';
        }

        $host = strtolower((string) parse_url($value, PHP_URL_HOST));
        $allowed = ['google.com', 'www.google.com', 'maps.google.com', 'maps.google.co.tz', 'www.google.co.tz'];
        $ok = false;
        foreach ($allowed as $allowedHost) {
            if ($host === $allowedHost || str_ends_with($host, '.'.$allowedHost)) {
                $ok = true;
                break;
            }
        }

        return $ok ? $value : '';
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<UploadedFile>  $galleryImages
     * @return array<string, mixed>
     */
    private function buildHomePageContent(
        BusinessWebsite $website,
        array $input,
        ?UploadedFile $heroImage,
        array $galleryImages = [],
        ?string $section = null
    ): array {
        $existingHome = $website->page('home');
        $existingPath = $existingHome['hero_image_path'] ?? null;
        $heroPath = is_string($existingPath) ? $existingPath : null;

        $galleryPaths = collect($existingHome['gallery_paths'] ?? [])
            ->filter(fn ($p) => is_string($p) && $p !== '')
            ->values()
            ->all();

        $promoItems = collect($existingHome['promo_items'] ?? [])
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->take(5)
            ->values()
            ->all();

        $base = [
            'hero_title' => (string) ($existingHome['hero_title'] ?? ''),
            'hero_subtitle' => (string) ($existingHome['hero_subtitle'] ?? ''),
            'hero_button_label' => (string) ($existingHome['hero_button_label'] ?? ''),
            'hero_button_url' => (string) ($existingHome['hero_button_url'] ?? ''),
            'hero_image_path' => $heroPath,
            'intro_title' => (string) ($existingHome['intro_title'] ?? ''),
            'intro_body' => (string) ($existingHome['intro_body'] ?? ''),
            'promo_title' => (string) ($existingHome['promo_title'] ?? ''),
            'promo_items' => $promoItems,
            'gallery_paths' => $galleryPaths,
        ];

        $updateHero = $section === null || $section === 'hero' || $section === 'home';
        $updatePromo = $section === null || $section === 'promo' || $section === 'home';
        $updateGallery = $section === null || $section === 'gallery' || $section === 'home';

        if ($updateHero) {
            if (filter_var($input['remove_hero_image'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                if ($heroPath) {
                    Storage::disk('public')->delete($heroPath);
                }
                $heroPath = null;
            }

            if ($heroImage) {
                if ($heroPath) {
                    Storage::disk('public')->delete($heroPath);
                }
                $heroPath = $heroImage->store('business-website-heroes', 'public');
            }

            $base['hero_title'] = trim((string) ($input['hero_title'] ?? ''));
            $base['hero_subtitle'] = trim((string) ($input['hero_subtitle'] ?? ''));
            $base['hero_button_label'] = trim((string) ($input['hero_button_label'] ?? ''));
            $base['hero_button_url'] = trim((string) ($input['hero_button_url'] ?? ''));
            $base['hero_image_path'] = $heroPath;
            $base['intro_title'] = trim((string) ($input['intro_title'] ?? ''));
            $base['intro_body'] = trim((string) ($input['intro_body'] ?? ''));
        }

        if ($updatePromo) {
            $base['promo_title'] = trim((string) ($input['promo_title'] ?? ''));
            $base['promo_items'] = collect(preg_split('/\r\n|\r|\n/', (string) ($input['promo_items'] ?? '')))
                ->map(fn ($line) => trim($line))
                ->filter()
                ->take(5)
                ->values()
                ->all();
        }

        if ($updateGallery) {
            $removeGallery = collect($input['remove_gallery'] ?? [])
                ->map(fn ($p) => (string) $p)
                ->filter()
                ->all();

            if ($removeGallery !== []) {
                $kept = [];
                foreach ($galleryPaths as $path) {
                    if (in_array($path, $removeGallery, true)) {
                        Storage::disk('public')->delete($path);
                    } else {
                        $kept[] = $path;
                    }
                }
                $galleryPaths = $kept;
            }

            foreach ($galleryImages as $file) {
                if ($file instanceof UploadedFile && count($galleryPaths) < 8) {
                    $galleryPaths[] = $file->store('business-website-gallery', 'public');
                }
            }

            $base['gallery_paths'] = $galleryPaths;
        }

        return $base;
    }

    private function normalizeTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultPages(Business $business): array
    {
        $name = $business->name ?: 'Our business';

        return [
            'home' => [
                'hero_title' => 'Welcome to '.$name,
                'hero_subtitle' => 'Quality products. Fair prices.',
                'hero_button_label' => 'Order now',
                'hero_button_url' => '/order',
                'intro_title' => 'Products',
                'intro_body' => 'In stock with prices.',
                'promo_title' => '',
                'promo_items' => [],
                'gallery_paths' => [],
            ],
            'products' => [
                'title' => 'Products',
                'body' => 'In stock with prices.',
                'show_prices' => true,
                'show_out_of_stock' => false,
                'hidden_category_ids' => [],
                'hidden_item_ids' => [],
            ],
            'about' => [
                'title' => '',
                'body' => '',
                'highlight_1_title' => '',
                'highlight_1_body' => '',
                'highlight_2_title' => '',
                'highlight_2_body' => '',
                'highlight_3_title' => '',
                'highlight_3_body' => '',
            ],
            'contact' => [
                'title' => 'Contact',
                'body' => 'Call or WhatsApp us.',
                'hours' => 'Mon – Sat: 8:00 – 20:00',
                'open_time' => '08:00',
                'close_time' => '20:00',
                'show_contact_form' => false,
                'show_map' => true,
                'map_query' => '',
                'map_embed_url' => '',
            ],
            'order' => [
                'title' => 'Place an order',
                'body' => 'Order by WhatsApp or phone.',
                'steps' => "Send your list on WhatsApp or call us.\nWe confirm price and stock.\nPay and collect or receive delivery.",
                'whatsapp_message' => 'Hello '.$name.', I would like to order: ',
                'delivery_notes' => '',
                'payment_notes' => '',
            ],
            'site' => BusinessWebsite::defaultSiteConfig($name),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function defaultSocialLinks(): array
    {
        return [
            'whatsapp' => '',
            'facebook' => '',
            'instagram' => '',
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'shop';
        $slug = $base;
        $i = 1;
        while (BusinessWebsite::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function normalizeColor(string $color): string
    {
        $color = trim($color);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return $color;
        }

        return '#940000';
    }

    /**
     * Sellable items grouped by category for the public products page.
     *
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public function productCatalog(Business $business, array $pageSettings): Collection
    {
        $showPrices = filter_var($pageSettings['show_prices'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $showOutOfStock = filter_var($pageSettings['show_out_of_stock'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hiddenCategoryIds = collect($pageSettings['hidden_category_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->all();
        $hiddenItemIds = collect($pageSettings['hidden_item_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->all();

        $itemsQuery = Item::query()
            ->where('business_id', $business->id)
            ->with(['category', 'packagings.packagingType'])
            ->orderBy('name');

        if ($hiddenCategoryIds !== []) {
            $itemsQuery->where(function ($q) use ($hiddenCategoryIds) {
                $q->whereNull('category_id')
                    ->orWhereNotIn('category_id', $hiddenCategoryIds);
            });
        }

        if ($hiddenItemIds !== []) {
            $itemsQuery->whereNotIn('id', $hiddenItemIds);
        }

        $items = $itemsQuery->get();

        $latestReceivingPrices = app(ItemSellingPriceResolver::class)
            ->latestReceivingPricesByItemId((int) $business->id);
        $normalizer = app(ItemPackagingNormalizer::class);
        $grouped = collect();

        foreach ($items as $item) {
            if (! $showOutOfStock && (float) ($item->current_stock ?? 0) <= 0) {
                continue;
            }

            $normalized = $normalizer->normalizeItemPackagings($item, $item->packagings);
            $packagingRows = $normalized->map(function ($row) use ($showPrices) {
                $packaging = $row['packaging'];
                $price = (float) $packaging->selling_price;

                return [
                    'label' => $packaging->packagingType?->name ?? 'Unit',
                    'selling_price' => $price,
                    'price_label' => $showPrices && $price > 0 ? money($price) : null,
                ];
            })->values();

            if ($showPrices) {
                $pricedRows = $packagingRows->filter(fn ($row) => $row['selling_price'] > 0)->values();
                $packagingRows = $pricedRows->isNotEmpty() ? $pricedRows : collect();
            }

            if ($packagingRows->isEmpty()) {
                $fallback = $latestReceivingPrices[$item->id] ?? null;
                $fallbackPrice = (float) ($fallback['selling_price'] ?? 0);
                $packagingRows = collect([[
                    'label' => $item->baseStockUnitName(),
                    'selling_price' => $fallbackPrice,
                    'price_label' => $showPrices && $fallbackPrice > 0 ? money($fallbackPrice) : null,
                ]]);
            }

            $categoryName = $item->category?->name ?? 'Other';
            if (! $grouped->has($categoryName)) {
                $grouped[$categoryName] = collect();
            }

            $grouped[$categoryName]->push([
                'name' => $item->name,
                'brand' => $item->brand,
                'stock' => (float) ($item->current_stock ?? 0),
                'in_stock' => (float) ($item->current_stock ?? 0) > 0,
                'packaging_rows' => $packagingRows->all(),
            ]);
        }

        return $grouped->sortKeys();
    }

}
