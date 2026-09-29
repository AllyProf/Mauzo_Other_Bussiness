<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Broadcast extends Model
{
    public const SPEEDS = [
        'very_slow' => ['label' => 'Very slow', 'px_per_second' => 30],
        'slow' => ['label' => 'Slow', 'px_per_second' => 50],
        'normal' => ['label' => 'Normal', 'px_per_second' => 75],
        'fast' => ['label' => 'Fast', 'px_per_second' => 110],
    ];

    public const FONTS = [
        'century_gothic' => ['label' => 'Century Gothic', 'css' => "'Century Gothic', 'Segoe UI', sans-serif"],
        'arial' => ['label' => 'Arial', 'css' => 'Arial, Helvetica, sans-serif'],
        'verdana' => ['label' => 'Verdana', 'css' => 'Verdana, Geneva, sans-serif'],
        'tahoma' => ['label' => 'Tahoma', 'css' => 'Tahoma, Geneva, sans-serif'],
        'trebuchet' => ['label' => 'Trebuchet MS', 'css' => "'Trebuchet MS', Helvetica, sans-serif"],
        'georgia' => ['label' => 'Georgia', 'css' => 'Georgia, serif'],
        'times' => ['label' => 'Times New Roman', 'css' => "'Times New Roman', Times, serif"],
        'courier' => ['label' => 'Courier New', 'css' => "'Courier New', Courier, monospace"],
    ];

    public const FONT_SIZES = [12, 13, 14, 15, 16, 18, 20, 22, 24];

    protected $fillable = [
        'message',
        'is_active',
        'scroll_speed',
        'text_color',
        'font_family',
        'font_size',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'font_size' => 'integer',
    ];

    public function pixelsPerSecond(): int
    {
        return self::SPEEDS[$this->scroll_speed]['px_per_second'] ?? self::SPEEDS['slow']['px_per_second'];
    }

    public function speedLabel(): string
    {
        return self::SPEEDS[$this->scroll_speed]['label'] ?? self::SPEEDS['slow']['label'];
    }

    public function fontCss(): string
    {
        return self::FONTS[$this->font_family]['css'] ?? self::FONTS['century_gothic']['css'];
    }

    public function fontLabel(): string
    {
        return self::FONTS[$this->font_family]['label'] ?? self::FONTS['century_gothic']['label'];
    }

    public function textColor(): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $this->text_color) ? $this->text_color : '#ffffff';
    }

    public function fontSizePx(): int
    {
        $size = (int) ($this->font_size ?: 14);

        return max(12, min(24, $size));
    }
}
