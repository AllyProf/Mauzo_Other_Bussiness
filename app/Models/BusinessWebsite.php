<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BusinessWebsite extends Model
{
    public const PAGE_KEYS = ['home', 'products', 'about', 'contact', 'order'];

    protected $fillable = [
        'business_id',
        'slug',
        'is_published',
        'tagline',
        'primary_color',
        'social_links',
        'pages',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'social_links' => 'array',
        'pages' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function page(string $key): array
    {
        $pages = $this->pages ?? [];

        return is_array($pages[$key] ?? null) ? $pages[$key] : [];
    }

    /**
     * One-page site: section links are home URL + hash anchors.
     */
    public function publicUrl(?string $page = null): string
    {
        $base = url('/site/'.$this->slug);
        if (! $page || $page === 'home') {
            return $base;
        }

        if (in_array($page, self::PAGE_KEYS, true)) {
            return $base.'#'.$page;
        }

        return $base;
    }

    public function heroImageUrl(): string
    {
        $path = $this->page('home')['hero_image_path'] ?? null;
        $uploaded = is_string($path) ? $this->uploadedMediaUrl($path) : null;
        if ($uploaded) {
            return $uploaded;
        }

        foreach ([
            'gp-assets/img/business-site/default-hero.jpg',
            'gp-assets/img/default-hero.jpg',
            'gp-assets/img/hero-bg.jpg',
        ] as $fallback) {
            if (is_file(public_path($fallback))) {
                return asset($fallback).'?v='.@filemtime(public_path($fallback));
            }
        }

        return asset('gp-assets/img/hero-bg.jpg');
    }

    /**
     * @return list<string>
     */
    public function galleryImageUrls(): array
    {
        $paths = $this->page('home')['gallery_paths'] ?? [];
        if (! is_array($paths)) {
            return [];
        }

        $urls = [];
        foreach ($paths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }
            $url = $this->uploadedMediaUrl($path);
            if ($url) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * Public URL for an uploaded business-website file.
     * Prefers files written into public_html/uploads (cPanel), then /site-media/...
     * for legacy storage/app/public files.
     */
    public function uploadedMediaUrl(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        if (Storage::disk('website_media')->exists($path)) {
            $version = @filemtime(Storage::disk('website_media')->path($path)) ?: time();

            return Storage::disk('website_media')->url($path).'?v='.$version;
        }

        if (Storage::disk('public')->exists($path)) {
            $version = @filemtime(Storage::disk('public')->path($path)) ?: time();

            return url('/site-media/'.$path).'?v='.$version;
        }

        return null;
    }

    /**
     * null = unknown (times not set); true/false = open/closed now (Africa/Dar_es_Salaam).
     */
    public function isOpenNow(): ?bool
    {
        $contact = $this->page('contact');
        $open = $contact['open_time'] ?? null;
        $close = $contact['close_time'] ?? null;
        if (! is_string($open) || ! is_string($close) || $open === '' || $close === '') {
            return null;
        }

        try {
            $now = now('Africa/Dar_es_Salaam');
            $openAt = $now->copy()->setTimeFromTimeString($open);
            $closeAt = $now->copy()->setTimeFromTimeString($close);
        } catch (\Throwable) {
            return null;
        }

        if ($closeAt->equalTo($openAt)) {
            return true;
        }

        // Overnight window (e.g. 18:00 – 02:00).
        if ($closeAt->lessThanOrEqualTo($openAt)) {
            return $now->greaterThanOrEqualTo($openAt) || $now->lessThanOrEqualTo($closeAt);
        }

        return $now->greaterThanOrEqualTo($openAt) && $now->lessThanOrEqualTo($closeAt);
    }

    public function qrCodeUrl(int $size = 200): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size='.$size.'x'.$size.'&data='.rawurlencode($this->publicUrl());
    }

    /**
     * @return array{show: bool, embed: string, open: string, query: string}
     */
    public function contactMap(): array
    {
        $contact = $this->page('contact');
        $show = filter_var($contact['show_map'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $customEmbed = trim((string) ($contact['map_embed_url'] ?? ''));
        $query = trim((string) ($contact['map_query'] ?? ''));
        if ($query === '') {
            $query = trim((string) ($this->business?->address ?? ''));
        }
        if ($query === '') {
            $query = trim((string) ($this->business?->name ?? 'Tanzania'));
        }

        $openUrl = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($query);

        return [
            'show' => $show,
            'embed' => $customEmbed !== ''
                ? $customEmbed
                : 'https://maps.google.com/maps?q='.rawurlencode($query).'&z=15&output=embed',
            'open' => $openUrl,
            'query' => $query,
        ];
    }

    /**
     * @return array{sections: array<string, bool>, payment: array<string, mixed>}
     */
    public function siteConfig(): array
    {
        $site = $this->page('site');
        $defaults = self::defaultSiteConfig($this->business?->name);

        return [
            'sections' => array_merge($defaults['sections'], is_array($site['sections'] ?? null) ? $site['sections'] : []),
            'payment' => array_merge($defaults['payment'], is_array($site['payment'] ?? null) ? $site['payment'] : []),
        ];
    }

    public function sectionEnabled(string $key): bool
    {
        $sections = $this->siteConfig()['sections'];

        return (bool) ($sections[$key] ?? true);
    }

    /**
     * @return array{sections: array<string, bool>, payment: array<string, mixed>}
     */
    public static function defaultSiteConfig(?string $businessName = null): array
    {
        return [
            'sections' => [
                'promo' => false,
                'products' => true,
                'order' => true,
                'gallery' => false,
                'contact' => true,
                'share' => true,
                'open_badge' => true,
                'whatsapp_float' => true,
            ],
            'payment' => [
                'merchant_name' => strtoupper(trim((string) $businessName)),
                'merchant_id' => '',
                'qr_image_path' => null,
                'title' => 'Visit our website',
                'footer_text' => 'Scan to open',
            ],
        ];
    }

    public function paymentQrImageUrl(): ?string
    {
        $path = $this->siteConfig()['payment']['qr_image_path'] ?? null;

        return $this->uploadedMediaUrl(is_string($path) ? $path : null);
    }
}
