# New Mobile APIs — Debts, Stock Adjustments, Stock Losses, Notes, Closing History, Price List

All endpoints are under the same base URL and auth as the rest of the mobile API:

| Environment | Base URL |
|-------------|----------|
| Production | `https://www.mauzolink.co.tz/api/v1` |

`Authorization: Bearer {token}` · `Accept: application/json` · `Content-Type: application/json`

Every response uses the standard envelope: `{ "success": true, "message": "OK", "data": { ... } }`. Errors: `403` no permission, `422` validation / business rule (`message` + optional `errors`).

**Who sees what (all modules):** cashiers/staff see their own records; owner and managers (`view_reports`) see the whole business or one branch (`?branch_id=`, `0` = all branches, default = branch chosen with `POST /auth/switch-branch`).

## Contents

1. [Debt management](#debt-management-api-mobile)
2. [Stock adjustments](#stock-adjustments-api-mobile)
3. [Stock losses](#stock-losses-api-mobile)
4. [Notes & reminders](#notes--reminders-api-mobile)
5. [Closing history](#closing-history-same-as-web-day-closinghistory)
6. [Price list](#price-list-api-mobile)

## All endpoints

| Feature | Method | Endpoint |
|---------|--------|----------|
| Debts | GET | `/debts` |
| Debts | GET | `/debts/{sale_id}` |
| Debts | POST | `/debts/{sale_id}/collect` |
| Debts | GET | `/debts/history?tab=payments` / `?tab=settled` |
| Stock adjustments | GET | `/stock-adjustments` |
| Stock adjustments | GET | `/stock-adjustments/create-form` |
| Stock adjustments | POST | `/stock-adjustments` |
| Stock adjustments | GET | `/stock-adjustments/{id}` |
| Stock adjustments | POST | `/stock-adjustments/{id}/cancel` |
| Stock losses | GET | `/stock-losses` |
| Stock losses | GET | `/stock-losses/create-form` |
| Stock losses | POST | `/stock-losses` |
| Stock losses | GET | `/stock-losses/{id}` |
| Stock losses | POST | `/stock-losses/{id}/cancel` |
| Notes | GET | `/notes` |
| Notes | POST | `/notes` |
| Notes | GET / PUT / DELETE | `/notes/{id}` |
| Notes | POST | `/notes/{id}/complete` |
| Notes | POST | `/notes/{id}/reopen` |
| Closing history | GET | `/day-closing/history` |
| Price list | GET | `/price-list` |


---

# Debt Management API (Mobile)

Same as web **`/debts`** (Outstanding accounts) and **`/debts/history`** (Collections history).

**Permission:** `manage_debts`, `process_sales` or `collect_payments`  
**Auth:** `Authorization: Bearer {token}` Â· `Accept: application/json`

| Environment | Base URL |
|-------------|----------|
| Production | `https://www.mauzolink.co.tz/api/v1` |

---

## What this feature does

A **debt** is a sale that still has a balance: status `debt` (Pay Later), `partial` (part paid) or `pending` (saved, not paid) and that has a customer. The list shows who owes what, overdue accounts and the top debtors. Collecting a payment reduces the balance; when it reaches 0 the account moves to **settled** in history.

| Who | Sees |
|-----|------|
| **Cashier / staff** | Only debts from **their own** sales |
| **Owner / managers** (`view_reports`) | Whole business, or one branch (`?branch_id=` or `/auth/switch-branch`) |

---

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/debts` | Outstanding accounts + stats + top customers |
| `GET` | `/debts/{sale_id}` | One account: items, payments, balance |
| `POST` | `/debts/{sale_id}/collect` | Record a payment against the debt |
| `GET` | `/debts/history` | Collections (`tab=payments`) or settled accounts (`tab=settled`) |
| `POST` | `/sales/{sale_id}/remind` | Send SMS reminder to the customer (see sales docs) |

---

## 1. Outstanding accounts

`GET /debts?search=john&status=debt&filter=overdue&business_type=liquor&page=1&per_page=15`

| Query | Description |
|-------|-------------|
| `search` | Reference no, customer name or phone |
| `status` | `debt`, `partial`, `pending` (omit = all) |
| `filter` | `overdue` = due date passed |
| `business_type` | Key from `filters.business_types` |
| `branch_id` | Owner only â€” `0` = all branches |
| `per_page` | 1â€“50 (default 15) |

```json
{
  "success": true,
  "data": {
    "stats": { "total_outstanding": 28834200, "open_accounts": 25, "overdue_count": 0, "customers": 4 },
    "debts": [
      {
        "id": 936,
        "reference_no": "ORD-20260920-A1B2",
        "customer_id": 12,
        "customer_name": "John Mwangi",
        "customer_phone": "0712345678",
        "total_amount": 120000,
        "amount_paid": 20000,
        "balance_due": 100000,
        "payment_status": "partial",
        "due_date": "2026-10-05",
        "is_overdue": false,
        "days_overdue": 0,
        "sale_date": "2026-09-20",
        "cashier": "Jackline",
        "items_summary": "Absolute Kati 350 Ml Ã— 1"
      }
    ],
    "top_customers": [
      { "name": "John Mwangi", "phone": "0712345678", "orders": 3, "balance": 450000 }
    ],
    "payment_methods": [
      { "key": "cash", "label": "Cash", "type": "immediate", "requires_reference": false },
      { "key": "mobile_money", "label": "Mobile Money", "type": "immediate", "requires_reference": true }
    ],
    "customers": [{ "id": 12, "name": "John Mwangi", "phone": "712345678" }],
    "filters": {
      "branch_id": null, "branch_name": null, "viewing_all_branches": true,
      "business_types": [], "multi_business": false, "active_business_type": null,
      "search": "john", "status": "debt", "filter": "overdue", "scoped_to_self": false
    },
    "meta": { "current_page": 1, "last_page": 2, "per_page": 15, "total": 25 }
  }
}
```

**Screen (matches web):** 4 stat cards â†’ filter chips (All / Pay Later / Partial / Pending / Overdue) â†’ search â†’ list (customer, ref, balance, due date, overdue badge) â†’ "Top customers" section. Row actions: **Collect**, **Remind (SMS)**, **View**.

`payment_methods` already **excludes Pay Later (credit)** â€” a debt can't be paid with credit.

---

## 2. Account detail

`GET /debts/936`

Returns `debt` (same fields as the list + `notes`, `items[]`, `payments[]`) and `payment_methods`.

```json
{
  "debt": {
    "id": 936, "reference_no": "ORD-20260920-A1B2", "balance_due": 100000, "...": "...",
    "notes": "2026-09-20 14:10: Will pay end of month",
    "items": [
      { "id": 1201, "name": "Absolute Kati 350 Ml", "packaging": "Piece", "quantity": 1, "unit_price": 120000, "subtotal": 120000 }
    ],
    "payments": [
      { "id": 77, "amount": 20000, "payment_method": "cash", "payment_method_label": "Cash", "payment_provider": null, "transaction_reference": null, "paid_at": "2026-09-21T10:00:00+03:00", "recorded_by": "Jackline" }
    ]
  }
}
```

---

## 3. Collect payment

`POST /debts/936/collect`

**Full or part payment (cash):**
```json
{ "payment_method": "cash", "amount_paid": 50000 }
```

**Mobile money / bank** (methods with `requires_reference: true`):
```json
{ "payment_method": "mobile_money", "amount_paid": 50000, "payment_provider": "M-Pesa", "transaction_reference": "QWE123ABC" }
```

**Still not fully paid after this payment** â†’ also send the new due date and customer:
```json
{ "payment_method": "cash", "amount_paid": 30000, "customer_name": "John Mwangi", "customer_phone": "0712345678", "due_date": "2026-10-20" }
```

- `amount_paid` above the balance is capped to the balance.
- Response: updated `debt` + `payment_message` (e.g. "Payment of TZS 50,000 recorded. Balance TZS 50,000 due 2026-10-20.").
- Errors: `422` "No outstanding balance on this account." / validation errors.

---

## 4. History

`GET /debts/history?tab=payments&page=1` â€” every payment collected on debt accounts (newest first).

```json
{
  "tab": "payments",
  "stats": { "total_collected": 497965700, "payments_count": 260, "settled_accounts": 260, "open_balance": 28834200 },
  "rows": [
    {
      "id": 77, "amount": 20000, "payment_method": "cash", "payment_method_label": "Cash",
      "payment_provider": null, "transaction_reference": null,
      "paid_at": "2026-09-21T10:00:00+03:00", "recorded_by": "Jackline",
      "sale": { "id": 936, "reference_no": "ORD-20260920-A1B2", "customer_name": "John Mwangi", "customer_phone": "0712345678", "balance_due": 100000, "payment_status": "partial" }
    }
  ],
  "meta": { "current_page": 1, "last_page": 13, "per_page": 20, "total": 260 }
}
```

`GET /debts/history?tab=settled` â€” fully paid accounts; each row = debt fields + `payments_count` + `settled_at`.

---

## Related

- Sales / POS: [`API_MOBILE.md`](API_MOBILE.md) â€” `POST /sales/{id}/pay`, `POST /sales/{id}/remind`
- Customers: [`API_CUSTOMERS.md`](API_CUSTOMERS.md)
- Debt report (aging, charts): [`API_REPORTS.md`](API_REPORTS.md) â€” `GET /reports/debts`


---

# Stock Adjustments API (Mobile)

Same as web **`/stock-adjustments`**.

**Permissions:** list/show `adjust_stock` or `view_stock_adjustments` Â· create/cancel `adjust_stock`  
**Auth:** `Authorization: Bearer {token}` Â· `Accept: application/json`

---

## What this feature does

Correct the system stock to the **real counted quantity** (e.g. wrong receiving, physical count, typing error). You enter the **new stock** for each item; the system records the difference (+ / âˆ’) and sets `current_stock` to the new value. Cancelling puts every item back to its **previous** stock.

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
      "stock_label": "8 Carton Â· 12 pcs (204 pcs total)",
      "stock_breakdown": "8 Carton Â· 12 pcs Carton"
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
| `items[].new_stock` | required, â‰¥ 0, **in pieces** (the counted total, not the difference) |
| `items[].line_notes` | optional, max 255 |
| `notes` | optional, max 1000 |
| `confirm_ack` | **required `true`** â€” user confirms the stock will be overwritten |

- Items whose `new_stock` equals current stock are skipped. If nothing changes â†’ `422`.
- Staff can only adjust items in **their branch** (`422` "item belongs to another branch").
- Success `201`: `adjustment` with `items[]` (`previous_stock`, `new_stock`, `adjustment_qty`).

**UI tip:** show current stock, let the user type the counted number, and preview the difference (`new âˆ’ current`) in green/red before saving.

---

## 4. Detail

`GET /stock-adjustments/1` â†’ `adjustment` (list fields + `items[]`):

```json
{ "id": 9, "item_id": 285, "name": "baltika", "sku": "SP-7E25A804", "category": "Beers", "previous_stock": 204, "new_stock": 200, "adjustment_qty": -4, "line_notes": "4 missing on shelf" }
```

---

## 5. Cancel

`POST /stock-adjustments/1/cancel` â€” sets each item back to `previous_stock`, status â†’ `cancelled`.  
`422` if already cancelled.


---

# Stock Losses API (Mobile)

Same as web **`/stock-losses`**.

**Permissions:** list `record_stock_loss`, `view_stock_history`, `open_shift` or `process_sales` Â· show `record_stock_loss` or `view_stock_history` Â· create `record_stock_loss` Â· cancel `cancel_stock_loss` or `record_stock_loss`  
**Auth:** `Authorization: Bearer {token}` Â· `Accept: application/json`

---

## What this feature does

Write off goods that are **lost, damaged, destroyed or expired**. Stock is reduced by the quantity and the **cost value** (qty Ã— unit cost) is recorded as a loss. Cancelling adds the stock back.

Cashiers who work in shifts also see **"My stock shortages"** â€” items they were short on at shift stock checks (owner decides: will be paid / waived).

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

- If `can_view_losses` is `false` (cashier without loss permissions), `losses` is empty â€” show only the **My stock shortages** tab.
- `stats` count **completed** records only.

---

## 2. Create form

`GET /stock-losses/create-form` â€” only items with **stock > 0**.

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

**UI tip:** show `qty Ã— unit_cost` as the loss value while typing.

---

## 4. Detail / 5. Cancel

- `GET /stock-losses/5` â†’ `loss` (list fields + `items[]`)
- `POST /stock-losses/5/cancel` â†’ adds the quantities back to stock, status `cancelled`. `422` if already cancelled.


---

# Notes & Reminders API (Mobile)

Same as web **`/notes`**.

**Permission:** `manage_notes`  
**Auth:** `Authorization: Bearer {token}` Â· `Accept: application/json`

---

## What this feature does

Personal notes for the logged-in user (each user sees **only their own** notes â€” same notes on web and mobile). A note can have a **reminder date/time** (`remind_at`); when it is reached the system sends an **SMS reminder** to the user (once per reminder time). Mark a note **done** when finished.

| `status` | `status_label` | Meaning |
|----------|----------------|---------|
| `active` | No reminder | Open note, no reminder |
| `upcoming` | Scheduled | Reminder set in the future |
| `due` | Due now | Reminder time reached, not done |
| `completed` | Completed | Marked done |

---

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/notes` | List (`?filter=active\|completed\|all`, `?q=`, `?page=`) + stats |
| `POST` | `/notes` | Create |
| `GET` | `/notes/{id}` | One note |
| `PUT` | `/notes/{id}` | Update |
| `DELETE` | `/notes/{id}` | Delete |
| `POST` | `/notes/{id}/complete` | Mark done |
| `POST` | `/notes/{id}/reopen` | Undo done |

---

## 1. List

`GET /notes?filter=active&q=supplier&per_page=15`

```json
{
  "stats": { "active": 5, "due": 1, "upcoming": 2 },
  "filter": "active",
  "notes": [
    {
      "id": 4,
      "title": "Supplier",
      "display_title": "Supplier",
      "body": "Call ABC Ltd about the missing carton",
      "remind_at": "2026-10-04T09:00:00+03:00",
      "remind_at_label": "04 Oct 2026, 09:00",
      "is_due": false,
      "is_completed": false,
      "completed_at": null,
      "reminder_sms_sent_at": null,
      "status": "upcoming",
      "status_label": "Scheduled",
      "created_at": "2026-10-02T11:42:00+03:00",
      "updated_at": "2026-10-02T11:42:00+03:00"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 5 }
}
```

`display_title` = title, or the first 60 characters of the body when there is no title.

---

## 2. Create / Update

`POST /notes` Â· `PUT /notes/{id}`

```json
{
  "title": "Supplier",
  "body": "Call ABC Ltd about the missing carton",
  "remind_at": "2026-10-04 09:00:00"
}
```

| Field | Rule |
|-------|------|
| `title` | optional, max 255 |
| `body` | **required**, max 5000 |
| `remind_at` | optional date-time (`YYYY-MM-DD HH:mm:ss` or ISO 8601). Send `null` to remove the reminder |

- Changing `remind_at` re-arms the SMS reminder.
- If `remind_at` is already in the past, the SMS is sent immediately.
- Response: `note` (`201` on create).

---

## 3. Complete / Reopen / Delete

- `POST /notes/4/complete` â†’ `note.is_completed = true`
- `POST /notes/4/reopen` â†’ back to active
- `DELETE /notes/4` â†’ `{ "success": true, "message": "Note deleted." }`

`403` if the note belongs to another user.


---

# Money Shorts (same as web /money-shorts)

`GET /money-shorts?status=outstanding&business_type=liquor&search=sindato`

**Permission:** `manage_money_shorts`, `verify_day_closing`, or `view_reports`.

Actions: `POST /money-shorts/{dayClosing}/pay`, `POST /money-shorts/{dayClosing}/salary-deduction`, `DELETE /money-shorts/settlements/{settlement}`.

Full request/response shapes: [`API_MONEY_SHORTS.md`](API_MONEY_SHORTS.md).

---

# Closing History (same as web /day-closing/history)
`GET /day-closing/history?status=verified&date_from=2026-09-01&date_to=2026-09-30&business_type=liquor&per_page=20`

**Permission:** `view_closing_history`, `view_reports` or `verify_day_closing`.

| Query | Values |
|-------|--------|
| `status` | `submitted`, `verified`, `disputed` (optional) |
| `date_from` / `date_to` | `YYYY-MM-DD` (optional) |
| `business_type` | key from `filters.business_types` (optional) |
| `branch_id` | owner only; `0` = all branches (default = branch from `/auth/switch-branch`) |
| `per_page` | 1â€“50 (default 20) |

```json
{
  "success": true,
  "data": {
    "closings": [
      {
        "id": 143,
        "closing_date": "2026-09-20",
        "closing_date_label": "Sep 20, 2026",
        "staff": { "id": 37, "name": "SINDATO STORE" },
        "shift_id": 101,
        "business_types": ["Liquor Store / Bar"],
        "sales_count": 2,
        "gross_sales": 956100,
        "payments_received": 956100,
        "total_expenses": 0,
        "net_amount": 956100,
        "money_short": 0,
        "status": "submitted",
        "verifier": null,
        "submitted_at": "2026-09-21T05:44:56+03:00",
        "verified_at": null
      }
    ],
    "filters": { "branch_id": null, "branch_name": null, "viewing_all_branches": true, "business_types": [], "multi_business": false, "active_business_type": null },
    "meta": { "current_page": 1, "last_page": 3, "per_page": 20, "total": 52 }
  }
}
```

Table columns (web): Date Â· Staff Â· Business Â· Sales Â· Gross Sales Â· Collected (`payments_received`) Â· Expenses Â· Net Â· Status Â· Submitted. Tap a row â†’ `GET /day-closing/{id}`.

Full handover detail: `GET /day-closing/{id}` — see `API_DAY_CLOSING.md`.


---

# Price List API (Mobile)

Same as web **`/price-list`**.

**Permission:** `view_price_list`, `view_inventory` or `process_sales`  
**Auth:** `Authorization: Bearer {token}` Â· `Accept: application/json`

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

**Screen (matches web):** category dropdown + search + "show unpriced" toggle â†’ sections per category â†’ each item with its unit prices (`label` + `quantity_per_unit` pcs + price). Groups are sorted Aâ€“Z; items Aâ€“Z inside each group.

To change prices use the items API (`PUT /items/{id}`) â€” see [`API_ITEMS.md`](API_ITEMS.md).
