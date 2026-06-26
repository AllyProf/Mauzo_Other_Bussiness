<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;

class TenantHelpController extends Controller
{
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
