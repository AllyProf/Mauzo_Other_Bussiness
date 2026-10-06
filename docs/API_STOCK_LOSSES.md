# Stock Losses API (Mobile)

Same as web **`/stock-losses`**.

**Permissions:** list `record_stock_loss`, `view_stock_history`, `open_shift` or `process_sales` · show `record_stock_loss` or `view_stock_history` · create `record_stock_loss` · cancel `cancel_stock_loss` or `record_stock_loss`  
**Auth:** `Authorization: Bearer {token}` · `Accept: application/json`

---

## What this feature does

Write off goods that are **lost, damaged, destroyed or expired**. Stock is reduced by the quantity and the **cost value** (qty × unit cost) is recorded as a loss. Cancelling adds the stock back.

Cashiers who work in shifts also see **"My stock shortages"** — items they were short on at shift stock checks (owner decides: will be paid / waived).

---

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/stock-losses` | List + stats (+ my shortages for shift staff) |
| `GET` | `/stock-losses/create-form` | Categories, items in stock, reasons |
| `POST` | `/stock-losses` | Record loss |
| `GET` | `/stock-losses/{id}` | Detail with lines |
| `POST` | `/stock-losses/{id}/cancel` | Cancel & restore stock |

---

## 1. List

`GET /stock-losses?status=completed&reason=damaged&date_from=2026-09-01&date_to=2026-09-30&business_type=liquor&per_page=15`

```json
{
  "stats": { "total_records": 3, "total_units_lost": 14, "total_cost_value": 52500 },
  "losses": [
    {
      "id": 5, "reference_no": "LOSS-20261002-626F", "loss_date": "2026-10-02",
      "reason": "damaged", "reason_label": "Damaged",
      "items_count": 1, "total_quantity": 1, "total_cost_value": 3750,
      "status": "completed", "notes": null, "recorded_by": "SINDATO",
      "created_at": "2026-10-02T11:41:00+03:00"
    }
  ],
  "can_view_losses": true,
  "can_record": true,
  "reasons": [{ "key": "lost", "label": "Lost / Missing" }, "..."],
  "my_stock_shortages": [
    {
      "id": 811, "item": "Heaven Sun 350Ml", "category": "Juice", "shift_id": 96,
      "system_stock": 40, "counted_stock": 38, "shortage_qty": 2, "cost_value": 1600,
      "notes": "Broken", "owner_decision": "will_be_paid", "is_verified": true,
      "recorded_at": "2026-09-30T07:10:00+03:00"
    }
  ],
  "my_shortage_stats": { "total": 1, "pending": 0, "will_be_paid": 1, "waived": 0, "amount_due": 1600 },
  "show_staff_shortages": true,
  "filters": { "branch_id": 14, "branch_name": "Main", "viewing_all_branches": false, "business_types": [], "multi_business": false, "active_business_type": null },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 3 }
}
```

- If `can_view_losses` is `false` (cashier without loss permissions), `losses` is empty — show only the **My stock shortages** tab.
- `stats` count **completed** records only.

---

## 2. Create form

`GET /stock-losses/create-form` — only items with **stock > 0**.

```json
{
  "categories": [{ "id": 10, "name": "Beers", "branch_id": 14, "business_type_key": "liquor" }],
  "items_by_category": {
    "10": [{ "id": 285, "name": "baltika", "sku": "SP-7E25A804", "brand": "", "stock": 204, "unit": "Robo", "unit_cost": 3750 }]
  },
  "reasons": [
    { "key": "lost", "label": "Lost / Missing" },
    { "key": "damaged", "label": "Damaged" },
    { "key": "destroyed", "label": "Destroyed / Written Off" },
    { "key": "expired", "label": "Expired" },
    { "key": "other", "label": "Other" }
  ],
  "business_types": [], "multi_business": false,
  "branch_id": 14, "branch_name": "Main",
  "defaults": { "loss_date": "2026-10-02" }
}
```

---

## 3. Record loss

`POST /stock-losses`

```json
{
  "loss_date": "2026-10-02",
  "reason": "damaged",
  "notes": "Dropped crate",
  "items": [
    { "id": 285, "qty": 2, "line_notes": "2 bottles broken" }
  ]
}
```

| Field | Rule |
|-------|------|
| `loss_date` | required date |
| `reason` | required, one of `reasons[].key` |
| `items[].id` | required |
| `items[].qty` | required, > 0, **in pieces**, cannot exceed current stock |
| `items[].line_notes` | optional |
| `notes` | optional |

- `422` "Not enough stock for {item}. Available: N."
- Success `201`: `loss` with `items[]` (`quantity`, `unit_cost`, `cost_value`).

**UI tip:** show `qty × unit_cost` as the loss value while typing.

---

## 4. Detail / 5. Cancel

- `GET /stock-losses/5` → `loss` (list fields + `items[]`)
- `POST /stock-losses/5/cancel` → adds the quantities back to stock, status `cancelled`. `422` if already cancelled.
