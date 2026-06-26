<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HelpArticle extends Model
{
    protected $fillable = ['title', 'slug', 'category', 'content', 'is_published'];

    protected $casts = ['is_published' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (HelpArticle $article) {
            if (empty($article->slug)) {
                $base = Str::slug($article->title);
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->where('id', '!=', $article->id ?? 0)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $article->slug = $slug;
            }
        });
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
