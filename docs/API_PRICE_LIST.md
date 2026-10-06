# Price List API (Mobile)

Same as web **`/price-list`**.

**Permission:** `view_price_list`, `view_inventory` or `process_sales`  
**Auth:** `Authorization: Bearer {token}` · `Accept: application/json`

---

## What this feature does

A read-only list of **selling prices** for every product, grouped by category, with one price per **sale unit** (e.g. Piece, Nusu, Carton). Used to quote customers or print/share the price list.

Staff see their branch; owners see the branch from `/auth/switch-branch` or `?branch_id=` (`0` = all).

---

## Endpoint

`GET /price-list?category_id=10&q=baltika&show_unpriced=0`

| Query | Description |
|-------|-------------|
| `category_id` | Only this category (omit = all) |
| `q` | Search name, brand or SKU |
| `show_unpriced` | `1` = also show units/items with **no price** (price `0`). Default hides them |
| `branch_id` | Owner only |

```json
{
  "success": true,
  "data": {
    "business": { "name": "SINDATO", "currency": "TZS" },
    "branch_id": 14,
    "branch_name": "Main",
    "categories": [{ "id": 10, "name": "Beers" }, { "id": 11, "name": "Juice" }],
    "selected_category_id": null,
    "show_unpriced": false,
    "search": "",
    "total_items": 242,
    "priced_packaging_count": 610,
    "groups": [
      {
        "category_id": 10,
        "category": "Beers",
        "items": [
          {
            "id": 285,
            "name": "baltika",
            "brand": null,
            "sku": "SP-7E25A804",
            "prices": [
              { "packaging_id": 707, "label": "Robo", "quantity_per_unit": 6, "selling_price": 21500 },
              { "packaging_id": 706, "label": "Nusu", "quantity_per_unit": 12, "selling_price": 42500 },
              { "packaging_id": 705, "label": "Carton", "quantity_per_unit": 24, "selling_price": 85000 }
            ]
          }
        ]
      }
    ],
    "generated_at": "2026-10-02T11:45:00+03:00"
  }
}
```

**Screen (matches web):** category dropdown + search + "show unpriced" toggle → sections per category → each item with its unit prices (`label` + `quantity_per_unit` pcs + price). Groups are sorted A–Z; items A–Z inside each group.

To change prices use the items API (`PUT /items/{id}`) — see [`API_ITEMS.md`](API_ITEMS.md).
