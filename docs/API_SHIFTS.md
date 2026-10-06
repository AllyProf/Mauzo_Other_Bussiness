# Shifts & Opening Stock Check API (Mobile)

Same as web **`/shifts/open`** (Open Shift — Physical Stock Check) and **`/shifts`**.

**Permission:** `open_shift` or `process_sales` (history/detail also `view_all_shifts`)  
**Auth:** `Authorization: Bearer {token}` · `Accept: application/json` · `Content-Type: application/json`

| Environment | Base URL |
|-------------|----------|
| Production | `https://www.mauzolink.co.tz/api/v1` |

Standard envelope: `{ "success": true, "message": "OK", "data": { } }` — errors: `{ "success": false, "message": "...", "errors": { "code": "..." } }`

---

## What this feature does

A **sales officer (cashier)** sells inside a **shift**. After the day is closed (handover submitted) the shift is closed, so on the next login the cashier must **open a new shift** by doing a **physical stock check**: count every item on the shelf and confirm it against the system stock. Only then can they sell on POS.

- Counts are always in **pieces (pcs)**.
- If the count is **lower** than the system stock, a **reason** is required (it becomes a stock shortage the owner reviews: *will be paid* / *waived*).
- Where the count differs, the system stock is set to the counted value so POS sells the real quantity.
- The owner can limit opening to certain **days / hours** (Settings → Shift rules).

---

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/shifts/current` | Does the user have an open shift? Must they open one? Can they open now? |
| `GET` | `/shifts/open-form` | Items for the physical stock check + scope + my shortages |
| `POST` | `/shifts/open` | Submit counts and open the shift |
| `GET` | `/shifts` | Shift history (paginated) |
| `GET` | `/shifts/{id}` | Shift detail with opening checks |
| `POST` | `/shifts/{id}/close` | Close shift only (full handover = `POST /day-closing`, see [`API_DAY_CLOSING.md`](API_DAY_CLOSING.md)) |

---

## Mobile flow (after login / after day closing)

```
Login
  └─ GET /shifts/current
       ├─ shift != null                         → POS
       ├─ requires_open_shift && shift == null
       │     ├─ can_open == false               → show can_open_message (blocked)
       │     └─ can_open == true                → Open Shift screen
       │           └─ GET /shifts/open-form
       │                 └─ user counts items
       │                       └─ POST /shifts/open → 201 → POS
       └─ requires_open_shift == false (owner)  → POS
```

---

## 1. Current shift

`GET /shifts/current`

```json
{
  "success": true,
  "data": {
    "shift": null,
    "needs_shift_opened": true,
    "requires_open_shift": true,
    "can_open": true,
    "can_open_message": ""
  }
}
```

| Field | Meaning |
|-------|---------|
| `shift` | Open shift (see [Shift object](#shift-object)) or `null` |
| `requires_open_shift` | This user must work in shifts (cashiers). Owners = `false` |
| `needs_shift_opened` | `true` when a shift is required and none is open |
| `can_open` / `can_open_message` | Shift-rule check (allowed days / hours). Show the message when `can_open` is `false` |

---

## 2. Open-form (items to count)

`GET /shifts/open-form`

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 154,
        "name": "Absolute 1 Litre",
        "sku": "SP-9F05A6E3",
        "brand": null,
        "category_id": 225,
        "category": "Vodca,Spirit & Wisky",
        "system_stock": 8,
        "stock_display": "8 Piece (Pcs)",
        "unit": "Piece (Pcs)",
        "has_bulk_stock": false,
        "pack_size": null,
        "bulk_name": null,
        "count_step": 0.01,
        "default_count": 8
      },
      {
        "id": 30,
        "name": "Castle Lite Crate",
        "sku": "SP-1A2B3C4D",
        "brand": null,
        "category_id": 220,
        "category": "Beers",
        "system_stock": 74,
        "stock_display": "3 Crate · 2 pcs (74 pcs total)",
        "unit": "Piece",
        "has_bulk_stock": true,
        "pack_size": 24,
        "bulk_name": "Crate",
        "count_step": 1,
        "default_count": 74
      }
    ],
    "items_count": 240,
    "has_bulk_items": true,
    "scope": { "branch_name": "Main Branch", "business_label": "Liquor Store / Bar" },
    "my_stock_shortages": [
      {
        "id": 811, "item": "Heaven Sun 350Ml", "category": "Juice", "shift_id": 96,
        "shortage_qty": 2, "cost_value": 1600, "notes": "Broken",
        "owner_decision": "will_be_paid", "is_verified": true,
        "recorded_at": "2026-09-30T07:10:00+03:00"
      }
    ],
    "my_shortage_stats": { "total": 1, "pending": 0, "will_be_paid": 1, "waived": 0, "amount_due": 1600 },
    "rules": { "count_unit": "pcs", "reason_required_when_below_system": true }
  }
}
```

### Item fields

| Field | Use |
|-------|-----|
| `system_stock` | Stock the system expects, **in pieces** |
| `stock_display` | Human text for the "System Stock" column |
| `default_count` | Pre-fill the count input with this (= system stock) |
| `has_bulk_stock`, `pack_size`, `bulk_name` | Item also sold by box/crate. Show a hint under the input: `floor(count / pack_size)` {bulk_name} + `count % pack_size` pcs |
| `count_step` | `1` for bulk items (whole pieces), `0.01` otherwise |

Only items with **stock > 0** in the cashier's **branch** and **assigned business types** are returned (sorted A–Z).

### Screen (matches web)

1. **Title:** "Open Shift — Physical Stock Check". Sub-text: "Enter physical count in pieces (pcs) only — change only where different from system stock."
2. **My stock shortages** card (only if `my_shortage_stats.total > 0`): past shortages with owner decision and `amount_due`.
3. **Scope banner** (if `scope` has values): "Your stock check shows items for branch **{branch_name}** · business **{business_label}** only."
4. If `has_bulk_items`: info note "Items sold by box and piece need only one count — enter the **total pieces** you physically have."
5. **Search** box filtering by name / SKU / category.
6. **List** — each item row:
   - Name (+ SKU), Category
   - System Stock: `stock_display`
   - Physical Count (pcs): number input, pre-filled `default_count`, step `count_step`
   - Variance = count − `system_stock` → **green** when 0, **red** when negative (short), **orange** when positive
   - Reason input — becomes **required** (show "Reason required — physical count is below system stock") when variance < 0
7. **Opening notes** (optional, max 2000)
8. **Open Shift** button → `POST /shifts/open`

If `items_count` is 0: show "No items with stock on hand. Receive stock or add items before opening a shift."

### Errors (`422`)

| `errors.code` | Meaning | App action |
|---------------|---------|------------|
| `SHIFT_ALREADY_OPEN` | User already has an open shift (`errors.shift` included) | Go to POS |
| `SHIFT_OPEN_NOT_ALLOWED` | Outside allowed days / hours | Show `message` |

---

## 3. Open shift (submit counts)

`POST /shifts/open`

```json
{
  "opening_notes": "Morning count",
  "counts": {
    "154": 8,
    "30": 72
  },
  "notes": {
    "30": "2 bottles broken"
  }
}
```

| Field | Rule |
|-------|------|
| `counts` | **Required.** Object `{ item_id: pieces }` — send a count for **every** item from open-form (unchanged items = `system_stock`) |
| `notes` | Object `{ item_id: reason }` — **required** for each item whose count is lower than `system_stock` (max 500 chars) |
| `opening_notes` | Optional, max 2000 |

Success **`201`**:

```json
{
  "success": true,
  "message": "Shift opened. Physical stock check saved — you can now sell on POS.",
  "data": {
    "shift": { "id": 120, "status": "open", "opened_at": "2026-10-02T07:05:00+03:00", "closed_at": null, "sales_count": 0, "gross_sales": 0, "amount_collected": 0, "opening_variance_count": 1, "user": null },
    "variance_count": 1,
    "next": "pos"
  }
}
```

### Errors (`422`, check `errors.code`)

| `errors.code` | Meaning | App action |
|---------------|---------|------------|
| `COUNT_REQUIRED` | A count is missing (`errors.item_id`) | Scroll to / highlight that item |
| `REASON_REQUIRED` | Count below system stock with no reason (`errors.item_id`) | Highlight its reason field |
| `SHIFT_ALREADY_OPEN` | Already open | Go to POS |
| `SHIFT_OPEN_NOT_ALLOWED` | Outside allowed days / hours | Show `message` |
| `NO_ITEMS` | Nothing in stock to count | Show `message` |

`403` "One or more items are outside your assigned scope." — an item id not returned by open-form was sent.

**Tip:** validate on the device first (every item has a count; reason filled where variance < 0) so the user isn't bounced back one item at a time.

---

## 4. Shift history

`GET /shifts?page=1&per_page=20`

Cashiers see their own shifts; owners / managers / `view_all_shifts` see everyone's.

```json
{
  "shifts": [ { "id": 120, "status": "open", "opened_at": "...", "closed_at": null, "sales_count": 12, "gross_sales": 450000, "amount_collected": 400000, "opening_variance_count": 1, "user": { "id": 29, "name": "SINDATO" } } ],
  "meta": { "current_page": 1, "last_page": 4, "per_page": 20, "total": 70 }
}
```

---

## 5. Shift detail

`GET /shifts/120` → `shift` object plus:

```json
{
  "opening_notes": "Morning count",
  "closing_notes": null,
  "opening_checks": [
    { "item": "Castle Lite Crate", "system_stock": 74, "counted_stock": 72, "variance": -2 }
  ]
}
```

---

## 6. Close shift

`POST /shifts/120/close` — body `{ "closing_notes": "optional" }`

Closes the shift only. For the end-of-day **handover** (cash, expenses, money short) use `POST /day-closing` — see [`API_DAY_CLOSING.md`](API_DAY_CLOSING.md). After closing, the next login goes back to step 1 (open a new shift).

---

## Shift object

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Shift ID |
| `status` | string | `open` / `closed` |
| `opened_at` / `closed_at` | ISO 8601 | Times |
| `sales_count` | int | Sales in this shift |
| `gross_sales` | number | Total sales value |
| `amount_collected` | number | Money collected |
| `opening_variance_count` | int | Items whose count differed at opening |
| `user` | object/null | `{ id, name }` (when loaded) |

---

## Related

- Day closing / handover: [`API_DAY_CLOSING.md`](API_DAY_CLOSING.md)
- POS sales: [`API_MOBILE.md`](API_MOBILE.md) — Sales (POS)
- Stock losses & my shortages: [`API_STOCK_LOSSES.md`](API_STOCK_LOSSES.md)
