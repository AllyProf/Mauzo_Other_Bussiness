<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Facades\DB;

class ItemSellingPriceResolver
{
    /**
     * Latest receiving line selling price per item.
     *
     * @return array<int, array{selling_price: float, selling_prices_snapshot: ?array}>
     */
    public function latestReceivingPricesByItemId(int $businessId): array
    {
        $latestIds = DB::table('receiving_items as ri')
            ->join('receivings as r', 'r.id', '=', 'ri.receiving_id')
            ->where('r.business_id', $businessId)
            ->where('ri.selling_price', '>', 0)
            ->selectRaw('MAX(ri.id) as id')
            ->groupBy('ri.item_id');

        $rows = DB::table('receiving_items as ri')
            ->joinSub($latestIds, 'latest', fn ($join) => $join->on('latest.id', '=', 'ri.id'))
            ->get(['ri.item_id', 'ri.selling_price', 'ri.selling_prices_snapshot']);

        $out = [];
        foreach ($rows as $row) {
            $snapshot = $row->selling_prices_snapshot ? json_decode($row->selling_prices_snapshot, true) : null;
            $out[(int) $row->item_id] = [
                'selling_price' => (float) $row->selling_price,
                'selling_prices_snapshot' => is_array($snapshot) ? $snapshot : null,
            ];
        }

        return $out;
    }

    public function receivingFallbackPrice(Item $item, ?array $cache = null): float
    {
        $cache ??= $this->latestReceivingPricesByItemId((int) $item->business_id);

        return (float) ($cache[$item->id]['selling_price'] ?? 0);
    }

    public function resolvePackagingPrice(Item $item, float $storedPrice, ?array $receivingCache = null): float
    {
        if ($storedPrice > 0) {
            return $storedPrice;
        }

        return $this->receivingFallbackPrice($item, $receivingCache);
    }
}
