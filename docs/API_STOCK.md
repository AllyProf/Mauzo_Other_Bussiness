# Stock on Hand API (Mobile)

REST endpoints for the **Stock on Hand** screen — same behavior as web `/items/stock`.

---

## What this feature does

Shows **only items that currently have stock** (quantity > 0, with a category). Unlike `GET /items` (full inventory catalog), this is the operational stock view:

- Stock display (pieces + packaging breakdown)
- Selling prices per packaging
- Low-stock warnings (from business automation threshold)
- Expected revenue & profit (owners only)
- Per-item movement history (receivings, sales, losses, adjustments)

### Typical mobile flow (matches `/items/stock`)

1. `GET /items/stock` — load stock list + stats + filters  
2. Filter client-side or via query params (`q`, `low_stock`, category, business type)  
3. Tap item → `GET /items/{id}/history` — movement timeline  
4. Optional: `POST /receivings` to add more stock

---

| Environment | Base URL |
|-------------|----------|
| Production | `https://www.mauzolink.co.tz/api/v1` |
| Local | `http://192.168.100.106:5000/api/v1` |

**Permission:** `view_stock_history` **or** `view_inventory`

---

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/items/stock` | Stock on hand list |
| `GET` | `/items/{id}/history` | Stock movement history for one item |

---

## 1. Stock list

`GET /items/stock`

### Query parameters

| Param | Notes |
|-------|-------|
| `q` | Search name, SKU, brand, category |
| `low_stock` | `1` or `true` — only items at/below threshold |
| `business_type_key` | Filter by department (`grocery`, `liquor`, etc.) |
| `category_slug` | Filter by category slug from `meta.category_filters` |
| `category_id` | Filter by category ID |

### Response

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 154,
        "name": "Absolut Vodka 750ml",
        "sku": "",
        "brand": "Absolut",
        "category": "Spirits",
        "category_slug": "spirits",
        "business_type_key": "liquor",
        "unit": "Bottle",
        "stock_pieces": 36,
        "stock_display": "3 Cartons (36 pcs)",
        "packaging_breakdown": [
          { "name": "Carton", "quantity_per_unit": 12, "formatted_count": "3" }
        ],
        "selling_price": 25000,
        "packaging_prices": [
          { "name": "Bottle", "quantity_per_unit": 1, "selling_price": 25000 },
          { "name": "Carton", "quantity_per_unit": 12, "selling_price": 280000 }
        ],
        "has_multi_packaging": true,
        "is_low_stock": false,
        "status": "in_stock",
        "expected_revenue": 900000,
        "expected_profit": 180000,
        "margin_percent": 20
      }
    ],
    "stats": {
      "total_items": 215,
      "low_stock": 14
    },
    "totals": {
      "expected_revenue": 12500000,
      "expected_profit": 3200000,
      "cost_holding_value": 9300000
    },
    "meta": {
      "branch_filter_id": 2,
      "active_branch_name": "Main",
      "viewing_all_branches": false,
      "business_types": [{ "key": "liquor", "label": "Liquor" }],
      "multi_business": true,
      "category_filters": [
        { "name": "Spirits", "slug": "spirits", "business_type_key": "liquor" }
      ],
      "low_stock_threshold": 5,
      "can_view_value": true,
      "items_count": 215
    }
  }
}
```

### Notes

- **Only in-stock items** with a category (matches web).
- **`totals` and per-item `expected_revenue` / `expected_profit`** are included only when `meta.can_view_value` is `true` (owners / business-wide viewers). Staff without that permission get stock quantities and prices but not valuation totals.
- **Low stock:** `stock_pieces <= low_stock_threshold` → `is_low_stock: true`, `status: "low_stock"`.
- Use `meta.category_filters` and `meta.business_types` to build the same filter pills as web.

---

## 2. Item history

`GET /items/{id}/history`

Movement timeline: stock-in (receivings), sales, stock losses, adjustments.

### Response

```json
{
  "success": true,
  "data": {
    "item": {
      "id": 154,
      "name": "Absolut Vodka 750ml",
      "current_stock": 36,
      "stock_display": "3 Cartons (36 pcs)",
      "unit": "Bottle"
    },
    "movements": [
      {
        "date": "2026-07-28",
        "time": "10:15 AM",
        "type": "stock_in",
        "type_label": "Stock In",
        "reference": "RCV-20260728-DE7A",
        "receiving_id": 183,
        "quantity": 12,
        "quantity_label": "+12",
        "direction": "in",
        "by": "John",
        "party": "ABC Supplier",
        "party_label": "Supplier",
        "details": "Received 1 Carton (12 pcs)",
        "status": "Completed",
        "counts_toward_totals": true
      },
      {
        "type": "sale",
        "sale_id": 501,
        "direction": "out",
        "quantity_label": "-2",
        "reference": "INV-20260727-0042"
      }
    ],
    "stats": {
      "total_received": 48,
      "total_sold": 12,
      "total_lost": 0,
      "current_stock": 36
    }
  }
}
```

### Movement types

| `type` | `direction` | Link field |
|--------|-------------|------------|
| `stock_in` | `in` | `receiving_id` → `GET /receivings/{id}` |
| `sale` | `out` | `sale_id` → `GET /sales/{id}` |
| `stock_loss` | `out` | `stock_loss_id` (web only for now) |
| `stock_adjustment` | `in` or `out` | `stock_adjustment_id` (web only for now) |

Cancelled records are included but `counts_toward_totals: false`.

---

## Difference: `/items` vs `/items/stock`

| | `GET /items` | `GET /items/stock` |
|--|--------------|-------------------|
| Purpose | Full inventory catalog (CRUD) | Stock on hand dashboard |
| Includes zero stock | Yes | No |
| Uncategorized items | Yes | No |
| Valuation / profit | No | Yes (owners) |
| Low-stock flag | No | Yes |
| History | No | Via `/items/{id}/history` |

---

## Related

- Add stock: [`API_RECEIVINGS.md`](API_RECEIVINGS.md)  
- Register products: [`API_ITEMS.md`](API_ITEMS.md)  
- Mobile overview: [`API_MOBILE.md`](API_MOBILE.md)
