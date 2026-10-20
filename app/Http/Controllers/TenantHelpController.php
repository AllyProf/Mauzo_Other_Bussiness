<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;

class TenantHelpController extends Controller
{
    public function gettingStarted()
    {
        $steps = collect(config('usage_guide.steps', []))->map(function (array $def, int $index) {
            $key = $def['key'] ?? '';
            $copy = __('help.getting_started.steps.'.$key);
            if (! is_array($copy)) {
                $copy = ['title' => $key, 'menu' => '', 'bullets' => []];
            }

            $canOpen = true;
            if (! empty($def['retail']) && ! business_retail_enabled()) {
                $canOpen = false;
            }
            if (! empty($def['permission']) && ! auth()->user()?->can($def['permission'])) {
                $canOpen = false;
            }
            if (! empty($def['any_report']) && ! user_can_any_business_report()) {
                $canOpen = false;
            }

            $routeName = $def['route'] ?? null;
            $url = ($canOpen && $routeName && \Illuminate\Support\Facades\Route::has($routeName))
                ? route($routeName)
                : null;

            return array_merge($def, [
                'number' => $index + 1,
                'title' => $copy['title'] ?? $key,
                'menu' => $copy['menu'] ?? '',
                'bullets' => $copy['bullets'] ?? [],
                'url' => $url,
                'can_open' => $canOpen && $url !== null,
                'retail_skip' => ! empty($def['retail']) && ! business_retail_enabled(),
                'diagram' => $def['diagram'] ?? null,
            ]);
        });

        return view('help.getting-started', compact('steps'));
    }

    public function index()
    {
        $articles = HelpArticle::published()->orderBy('category')->orderBy('title')->get();
        $grouped = $articles->groupBy('category');
        return view('help.index', compact('grouped'));
    }

    public function show(string $slug)
    {
        $article = HelpArticle::where('slug', $slug)->where('is_published', true)->firstOrFail();
        $related = HelpArticle::published()
            ->where('category', $article->category)
            ->where('id', '!=', $article->id)
            ->limit(5)
            ->get();
        return view('help.show', compact('article', 'related'));
    }
}
