# Receivings API (Mobile)

REST endpoints for stock-in (receiving) — same behavior as web `/receivings/create` and `/receivings`.

---

## What this feature does

**Receivings** record stock arriving from a supplier. Each receiving:

- Increases item `current_stock`
- Updates item packaging **cost** and **selling** prices
- Stores a reference number (`RCV-YYYYMMDD-XXXX`)
- Can be cancelled (reverses stock where still available)

### Typical mobile flow (matches `/receivings/create`)

1. `GET /receivings/create-form` — suppliers, categories, items, branches  
2. User picks supplier, date, line items (qty, cost, optional retail prices)  
3. `POST /receivings` — submit stock-in  
4. List / view / cancel from `GET /receivings` and detail endpoints

---

| Environment | Base URL |
|-------------|----------|
| Production | `https://www.mauzolink.co.tz/api/v1` |
| Local | `http://192.168.100.106:5000/api/v1` (or your host) |

All requests require:

```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

Standard envelope:

```json
{
  "success": true,
  "message": "OK",
  "data": { }
}
```

---

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/receivings` | List receivings (filters below) |
| `GET` | `/receivings/create-form` | Form data for create screen |
| `POST` | `/receivings` | Create stock-in |
| `GET` | `/receivings/{id}` | Receiving detail |
| `GET` | `/receivings/{id}/cancel-preview` | Preview stock reversal before cancel |
| `POST` | `/receivings/{id}/cancel` | Cancel receiving |

**Permission:** `receive_stock` (create/list/show). Cancel also allows `cancel_receiving`.

---

## Branch rules

- **Staff:** locked to their branch; `branch_id` on create is ignored.
- **Owner:** list filtered by `POST /auth/switch-branch`; all-branches view shows all.
- **Multi-branch owners:** pass `branch_id` on create when not on a specific branch.

---

## 1. Create form

`GET /receivings/create-form`

Returns suppliers, categories, items grouped by category (with packagings and current stock), branches, and defaults.

### Response highlights

```json
{
  "success": true,
  "data": {
    "suppliers": [{ "id": 1, "name": "ABC Ltd", "phone": "+255..." }],
    "categories": [{ "id": 10, "name": "Drinks", "branch_id": 2 }],
    "items_by_category": {
      "10": [{
        "id": 55,
        "name": "Afya Maji Medium",
        "unit": "Carton",
        "units_per_receiving_pack": 12,
        "current_stock": 48,
        "cost_price": 4500,
        "selling_price": 500,
        "packagings": [
          { "id": 101, "name": "Piece", "quantity_per_unit": 1, "cost_price": 375, "selling_price": 500 },
          { "id": 102, "name": "Carton", "quantity_per_unit": 12, "cost_price": 4500, "selling_price": 5000 }
        ]
      }]
    },
    "branches": [{ "id": 2, "name": "Main", "is_default": true }],
    "default_branch_id": 2,
    "defaults": {
      "received_date": "2026-07-28",
      "qty_mode": "pkg",
      "cost_mode": "pkg"
    },
    "meta": {
      "branch_filter_id": 2,
      "can_pick_branch": false
    }
  }
}
```

Use `items_by_category` to build the line-item picker (same data as web JS).

---

## 2. Create receiving (register)

`POST /receivings`

### Required fields

| Field | Type | Notes |
|-------|------|-------|
| `supplier_id` | integer | Must belong to your business |
| `received_date` | date (`Y-m-d`) | Date stock was received |
| `items` | array (≥1) | At least one line with `qty` > 0 |
| `items[].id` | integer | Item ID from create-form |
| `items[].qty` | integer ≥ 1 | Quantity in selected mode |
| `items[].cost` | number ≥ 0 | Buying price (see `cost_mode`) |

### Optional fields

| Field | Type | Default | Notes |
|-------|------|---------|-------|
| `branch_id` | integer | tenant/default branch | Owners only when multi-branch |
| `notes` | string | null | Free text |
| `items[].qty_mode` | `pkg` \| `piece` | `pkg` | `pkg` = receiving unit (carton); `piece` = individual units |
| `items[].cost_mode` | `pkg` \| `unit` | `pkg` | Whether `cost` is per pack or per piece |
| `items[].selling` | number | — | Single retail price (simple items) |
| `items[].selling_prices` | object | — | Map of `item_packaging_id` → price (multi-packaging items) |
| `items[].discount_type` | `fixed` \| `percent` | null | Line discount |
| `items[].discount_value` | number | 0 | Discount amount or % |

### Example — single packaging item

```json
{
  "supplier_id": 3,
  "received_date": "2026-07-28",
  "branch_id": 2,
  "notes": "Morning delivery",
  "items": [
    {
      "id": 55,
      "qty": 10,
      "qty_mode": "pkg",
      "cost": 4500,
      "cost_mode": "pkg",
      "selling": 5000,
      "discount_type": "percent",
      "discount_value": 5
    }
  ]
}
```

### Example — multi-packaging retail prices

```json
{
  "supplier_id": 3,
  "received_date": "2026-07-28",
  "items": [
    {
      "id": 55,
      "qty": 5,
      "cost": 4500,
      "selling_prices": {
        "101": 500,
        "102": 5000
      }
    }
  ]
}
```

Keys in `selling_prices` are **`item_packagings.id`** values from create-form `packagings[].id`.

### Validation rules (same as web)

- Retail/selling price cannot be **below** net buying cost per unit.
- `items` with `qty` 0 are ignored; at least one active line required.
- On success: stock increases, packaging prices update, SMS may be sent to staff.

### Success response (201)

```json
{
  "success": true,
  "message": "Stock-in record created successfully (RCV-20260728-A1B2).",
  "data": {
    "receiving": {
      "receiving": { "id": 99, "reference_no": "RCV-20260728-A1B2", "total_amount": 21375 },
      "lines": [ ... ],
      "totals": { "net_cost": 21375, "expected_revenue": 25000, "expected_profit": 3625 }
    }
  }
}
```

---

## 3. List receivings

`GET /receivings`

### Query parameters

| Param | Values | Description |
|-------|--------|-------------|
| `period` | `all`, `today`, `weekly`, `monthly`, `yearly`, `custom` | Date filter |
| `start_date` | date | With `period=custom` |
| `end_date` | date | With `period=custom` |
| `status` | `completed`, `cancelled` | Status filter |
| `business_type` | type key | Filter by category business type |
| `per_page` | 1–50 | Pagination (default 20) |

---

## 4. Show detail

`GET /receivings/{id}`

Returns header, line items with quantity labels, packaging prices, and profit totals.

---

## 5. Cancel

There is **no edit** endpoint — receivings are immutable after create. Use cancel to reverse.

### Preview

`GET /receivings/{id}/cancel-preview`

Shows how much stock can be reversed vs already sold.

### Cancel

`POST /receivings/{id}/cancel`

| Field | Required | Notes |
|-------|----------|-------|
| `partial_ok` | No | Set `true` to confirm when some stock was already sold |

If stock was partially sold and `partial_ok` is not `true`, API returns **409** with `data` containing the preview.

```json
{
  "partial_ok": true
}
```

**Body:** only `partial_ok` when needed; no other fields required.

---

## qty_mode & cost_mode quick reference

| `qty_mode` | `qty` means |
|------------|-------------|
| `pkg` (default) | Number of **receiving packs** (e.g. cartons). Stock += `qty × units_per_receiving_pack` |
| `piece` | Individual pieces. Stock += `qty` |

| `cost_mode` | `cost` means |
|-------------|--------------|
| `pkg` (default) | Price per receiving pack |
| `unit` | Price per piece |

Toggle behavior on web maps directly to these two fields per line item.

---

## Related APIs

- Suppliers: [`API_SUPPLIERS.md`](API_SUPPLIERS.md) — register suppliers first if list is empty  
- Items: [`API_ITEMS.md`](API_ITEMS.md) — items must exist before receiving  
- Mobile overview: [`API_MOBILE.md`](API_MOBILE.md)
