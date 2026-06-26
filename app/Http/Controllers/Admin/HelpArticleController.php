<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\EnsuresPlatformAdmin;
use App\Models\AuditLog;
use App\Models\HelpArticle;
use Illuminate\Http\Request;

class HelpArticleController extends Controller
{
    use EnsuresPlatformAdmin;

    public function index()
    {
        $this->ensurePlatformAdmin('settings');
        $articles = HelpArticle::orderBy('category')->orderBy('title')->get();
        $categories = $articles->pluck('category')->unique()->sort()->values();
        return view('admin.help-articles.index', compact('articles', 'categories'));
    }

    public function create()
    {
        $this->ensurePlatformAdmin('settings');
        $existingCategories = HelpArticle::select('category')->distinct()->pluck('category');
        return view('admin.help-articles.create', compact('existingCategories'));
    }

    public function store(Request $request)
    {
        $this->ensurePlatformAdmin('settings');
        $data = $request->validate([
            'title'    => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'content'  => 'required|string',
            'is_published' => 'boolean',
        ]);
        $data['is_published'] = $request->boolean('is_published');
        $article = HelpArticle::create($data);
        AuditLog::log('CREATE_HELP_ARTICLE', "Created help article: {$article->title}");
        return redirect()->route('admin.help-articles.index')->with('success', 'Article created.');
    }

    public function edit(HelpArticle $helpArticle)
    {
        $this->ensurePlatformAdmin('settings');
        $existingCategories = HelpArticle::select('category')->distinct()->pluck('category');
        return view('admin.help-articles.edit', ['article' => $helpArticle, 'existingCategories' => $existingCategories]);
    }

    public function update(Request $request, HelpArticle $helpArticle)
    {
        $this->ensurePlatformAdmin('settings');
        $data = $request->validate([
            'title'    => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'content'  => 'required|string',
            'is_published' => 'boolean',
        ]);
        $data['is_published'] = $request->boolean('is_published');
        $helpArticle->update($data);
        AuditLog::log('UPDATE_HELP_ARTICLE', "Updated help article: {$helpArticle->title}");
        return redirect()->route('admin.help-articles.index')->with('success', 'Article updated.');
    }

    public function destroy(HelpArticle $helpArticle)
    {
        $this->ensurePlatformAdmin('settings');
        $title = $helpArticle->title;
        $helpArticle->delete();
        AuditLog::log('DELETE_HELP_ARTICLE', "Deleted help article: {$title}");
        return redirect()->route('admin.help-articles.index')->with('success', 'Article deleted.');
    }

    public function togglePublish(HelpArticle $helpArticle)
    {
        $this->ensurePlatformAdmin('settings');
        $helpArticle->update(['is_published' => ! $helpArticle->is_published]);
        $status = $helpArticle->is_published ? 'published' : 'unpublished';
        return redirect()->back()->with('success', "Article {$status}.");
    }
}
