<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessWebsite;
use App\Models\Item;
use Illuminate\Support\Collection;
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
        $slug = Str::slug($slug);
        if ($slug === '') {
            return null;
        }

        return BusinessWebsite::query()
            ->where('slug', $slug)
            ->where('is_published', true)
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
    public function updatePage(BusinessWebsite $website, string $pageKey, array $input): BusinessWebsite
    {
        if (! in_array($pageKey, BusinessWebsite::PAGE_KEYS, true)) {
            throw ValidationException::withMessages(['page' => 'Unknown page.']);
        }

        $pages = $website->pages ?? [];
        $pages[$pageKey] = match ($pageKey) {
            'home' => [
                'hero_title' => trim((string) ($input['hero_title'] ?? '')),
                'hero_subtitle' => trim((string) ($input['hero_subtitle'] ?? '')),
                'hero_button_label' => trim((string) ($input['hero_button_label'] ?? '')),
                'hero_button_url' => trim((string) ($input['hero_button_url'] ?? '')),
                'intro_title' => trim((string) ($input['intro_title'] ?? '')),
                'intro_body' => trim((string) ($input['intro_body'] ?? '')),
            ],
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
            'contact' => [
                'title' => trim((string) ($input['title'] ?? '')),
                'body' => trim((string) ($input['body'] ?? '')),
                'hours' => trim((string) ($input['hours'] ?? '')),
                'show_contact_form' => filter_var($input['show_contact_form'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
            'order' => [
                'title' => trim((string) ($input['title'] ?? '')),
                'body' => trim((string) ($input['body'] ?? '')),
                'steps' => trim((string) ($input['steps'] ?? '')),
                'whatsapp_message' => trim((string) ($input['whatsapp_message'] ?? '')),
            ],
            'products' => [
                'title' => trim((string) ($input['title'] ?? '')),
                'body' => trim((string) ($input['body'] ?? '')),
                'show_prices' => filter_var($input['show_prices'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'show_out_of_stock' => filter_var($input['show_out_of_stock'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ],
            default => [],
        };

        $website->update(['pages' => $pages]);

        return $website->fresh();
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
                'hero_subtitle' => 'Quality products and service you can trust.',
                'hero_button_label' => 'Order now',
                'hero_button_url' => '/order',
                'intro_title' => 'Products we offer',
                'intro_body' => '',
            ],
            'products' => [
                'title' => 'Products',
                'body' => 'Browse our current range. Prices and availability match what we sell in store.',
                'show_prices' => true,
                'show_out_of_stock' => false,
            ],
            'about' => [
                'title' => 'About '.$name,
                'body' => $name.' serves customers with care and transparency. This page tells your story — edit it in Mauzo Link under Business Website.',
                'highlight_1_title' => 'Our mission',
                'highlight_1_body' => 'Deliver value every day.',
                'highlight_2_title' => 'Our team',
                'highlight_2_body' => 'Friendly staff ready to help.',
                'highlight_3_title' => 'Our location',
                'highlight_3_body' => $business->address ?: 'Add your address in business settings.',
            ],
            'contact' => [
                'title' => 'Contact us',
                'body' => 'Reach us by phone, WhatsApp, or the form below.',
                'hours' => 'Mon – Sat: 8:00 – 20:00',
                'show_contact_form' => true,
            ],
            'order' => [
                'title' => 'Place an order',
                'body' => 'Tell us what you need. We confirm availability and delivery or pickup.',
                'steps' => "1. Send your list on WhatsApp or call us.\n2. We confirm price and stock.\n3. Pay and collect or receive delivery.",
                'whatsapp_message' => 'Hello '.$name.', I would like to order: ',
            ],
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

        $items = Item::query()
            ->where('business_id', $business->id)
            ->with(['category', 'packagings.packagingType'])
            ->orderBy('name')
            ->get();

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
