<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = [
        'business_id', 
        'category_id', 
        'receiving_packaging_id',
        'units_per_receiving_pack',
        'name', 
        'sku', 
        'brand', 
        'description',
        'current_stock'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function receivingPackaging()
    {
        return $this->belongsTo(Packaging::class, 'receiving_packaging_id');
    }

    public function packagings()
    {
        return $this->hasMany(ItemPackaging::class)->orderBy('quantity_per_unit');
    }

    public function baseStockUnitName(): string
    {
        $smallest = $this->packagings->sortBy('quantity_per_unit')->first();

        return $smallest?->packagingType?->name ?? 'Unit';
    }

    public function effectiveQuantityPerUnit(?ItemPackaging $packaging): int
    {
        if (! $packaging) {
            return 1;
        }

        $this->loadMissing('packagings.packagingType');
        $normalizer = app(\App\Services\ItemPackagingNormalizer::class);
        $normalized = $normalizer->normalizeItemPackagings($this, $this->packagings);
        $row = $normalized->first(fn ($r) => $r['packaging']->id === $packaging->id);

        return max(1, (int) ($row['quantity_per_unit'] ?? $packaging->quantity_per_unit));
    }

    public function stockUnitsForPackaging(int $qty, ?ItemPackaging $packaging): float
    {
        return (float) $qty * $this->effectiveQuantityPerUnit($packaging);
    }

    public function stockDisplay(?float $pieces = null): string
    {
        return app(\App\Services\ItemStockDisplayService::class)->format($this, $pieces)['stock_display'];
    }
}
