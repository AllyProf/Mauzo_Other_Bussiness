<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function publicUrl(?string $page = null): string
    {
        $base = url('/site/'.$this->slug);
        if ($page && $page !== 'home' && in_array($page, self::PAGE_KEYS, true)) {
            return $base.'/'.$page;
        }

        return $base;
    }
}
