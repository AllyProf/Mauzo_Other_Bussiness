<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $entries = collect(config('seo.sitemap', []))
            ->map(function (array $row): ?array {
                $name = $row['route'] ?? null;
                if (! is_string($name) || ! Route::has($name)) {
                    return null;
                }

                return [
                    'loc' => route($name),
                    'changefreq' => $row['changefreq'] ?? 'monthly',
                    'priority' => $row['priority'] ?? '0.5',
                    'lastmod' => now()->toAtomString(),
                ];
            })
            ->filter()
            ->values()
            ->all();

        return response()
            ->view('seo.sitemap', ['entries' => $entries])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $disallow = config('seo.robots_disallow', []);
        $sitemap = url('/sitemap.xml');

        return response()
            ->view('seo.robots', [
                'disallow' => $disallow,
                'sitemap' => $sitemap,
            ])
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
