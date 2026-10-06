# Stock Adjustments API (Mobile)

Same as web **`/stock-adjustments`**.

**Permissions:** list/show `adjust_stock` or `view_stock_adjustments` · create/cancel `adjust_stock`  
**Auth:** `Authorization: Bearer {token}` · `Accept: application/json`

---

## What this feature does

Correct the system stock to the **real counted quantity** (e.g. wrong receiving, physical count, typing error). You enter the **new stock** for each item; the system records the difference (+ / −) and sets `current_stock` to the new value. Cancelling puts every item back to its **previous** stock.

> Use **Stock Losses** for lost / damaged / expired goods (they carry a cost value). Use **Adjustments** to fix wrong numbers.

| Who | Sees |
|-----|------|
| Staff | Their own adjustments (in their branch) |
| Owner / managers | Business, or one branch (`?branch_id=` / `/auth/switch-branch`) |

---

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/stock-adjustments` | List + stats |
| `GET` | `/stock-adjustments/create-form` | Categories, items (with current stock), reasons |
| `POST` | `/stock-adjustments` | Save adjustment |
| `GET` | `/stock-adjustments/{id}` | Detail with lines |
| `POST` | `/stock-adjustments/{id}/cancel` | Cancel & restore previous stock |

---

## 1. List

`GET /stock-adjustments?status=completed&reason=physical_count&date_from=2026-09-01&date_to=2026-09-30&per_page=15`

```json
{
  "stats": { "total_records": 4, "total_lines": 11, "net_adjustment": -6 },
  "adjustments": [
    {
      "id": 1, "reference_no": "ADJ-20261002-4839", "adjustment_date": "2026-10-02",
      "reason": "physical_count", "reason_label": "Physical count correction",
      "total_items": 1, "net_adjustment": 1, "status": "completed", "notes": null,
      "branch": "Main", "recorded_by": "SINDATO", "created_at": "2026-10-02T11:40:00+03:00"
    }
  ],
  "can_adjust": true,
  "reasons": [{ "key": "incorrect_receiving", "label": "Incorrect receiving" }, "..."],
  "filters": { "branch_id": 14, "branch_name": "Main", "viewing_all_branches": false, "business_types": [], "multi_business": false, "active_business_type": null },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 4 }
}
```

`stats` count **completed** adjustments only.

---

## 2. Create form

`GET /stock-adjustments/create-form`

```json
{
  "categories": [{ "id": 10, "name": "Beers", "branch_id": 14, "business_type_key": "liquor" }],
  "items_by_category": {
    "10": [{
      "id": 285, "name": "baltika", "sku": "SP-7E25A804", "brand": "",
      "stock": 204,
      "stock_label": "8 Carton · 12 pcs (204 pcs total)",
      "stock_breakdown": "8 Carton · 12 pcs Carton"
    }]
  },
  "reasons": [
    { "key": "incorrect_receiving", "label": "Incorrect receiving" },
    { "key": "physical_count", "label": "Physical count correction" },
    { "key": "data_entry_error", "label": "Data entry error" },
    { "key": "other", "label": "Other" }
  ],
  "business_types": [], "multi_business": false,
  "branch_id": 14, "branch_name": "Main",
  "defaults": { "adjustment_date": "2026-10-02" }
}
```

`stock` is in **pieces** (base unit). Search items locally by `name` / `sku` / `brand`; filter by category / business type.

---

## 3. Save

`POST /stock-adjustments`

```json
{
  "adjustment_date": "2026-10-02",
  "reason": "physical_count",
  "notes": "Monthly count",
  "confirm_ack": true,
  "items": [
    { "id": 285, "new_stock": 200, "line_notes": "4 missing on shelf" },
    { "id": 290, "new_stock": 36 }
  ]
}
```

| Field | Rule |
|-------|------|
| `adjustment_date` | required date |
| `reason` | required, one of `reasons[].key` |
| `items[].id` | required item id |
| `items[].new_stock` | required, ≥ 0, **in pieces** (the counted total, not the difference) |
| `items[].line_notes` | optional, max 255 |
| `notes` | optional, max 1000 |
| `confirm_ack` | **required `true`** — user confirms the stock will be overwritten |

- Items whose `new_stock` equals current stock are skipped. If nothing changes → `422`.
- Staff can only adjust items in **their branch** (`422` "item belongs to another branch").
- Success `201`: `adjustment` with `items[]` (`previous_stock`, `new_stock`, `adjustment_qty`).

**UI tip:** show current stock, let the user type the counted number, and preview the difference (`new − current`) in green/red before saving.

---

## 4. Detail

`GET /stock-adjustments/1` → `adjustment` (list fields + `items[]`):

```json
{ "id": 9, "item_id": 285, "name": "baltika", "sku": "SP-7E25A804", "category": "Beers", "previous_stock": 204, "new_stock": 200, "adjustment_qty": -4, "line_notes": "4 missing on shelf" }
```

---

## 5. Cancel

`POST /stock-adjustments/1/cancel` — sets each item back to `previous_stock`, status → `cancelled`.  
`422` if already cancelled.
