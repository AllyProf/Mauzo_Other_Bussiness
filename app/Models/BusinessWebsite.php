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
        if (is_string($path) && $path !== '' && Storage::disk('public')->exists($path)) {
            // Prefer asset()/storage path so local & production hosts stay correct.
            return asset('storage/'.ltrim($path, '/'));
        }

        return asset('gp-assets/img/hero-bg.jpg');
    }
}
