# Debt Management API (Mobile)

Same as web **`/debts`** (Outstanding accounts) and **`/debts/history`** (Collections history).

**Permission:** `manage_debts`, `process_sales` or `collect_payments`  
**Auth:** `Authorization: Bearer {token}` · `Accept: application/json`

| Environment | Base URL |
|-------------|----------|
| Production | `https://www.mauzolink.co.tz/api/v1` |

---

## What this feature does

A **debt** is a sale that still has a balance: status `debt` (Pay Later), `partial` (part paid) or `pending` (saved, not paid) and that has a customer. The list shows who owes what, overdue accounts and the top debtors. Collecting a payment reduces the balance; when it reaches 0 the account moves to **settled** in history.

| Who | Sees |
|-----|------|
| **Sales officer / staff** | Only debts from **their own** sales |
| **Payment cashier** ([`API_CASHIER.md`](API_CASHIER.md)) | All debts in **his branch** |
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
| `branch_id` | Owner only — `0` = all branches |
| `per_page` | 1–50 (default 15) |

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
        "items_summary": "Absolute Kati 350 Ml × 1"
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

**Screen (matches web):** 4 stat cards → filter chips (All / Pay Later / Partial / Pending / Overdue) → search → list (customer, ref, balance, due date, overdue badge) → "Top customers" section. Row actions: **Collect**, **Remind (SMS)**, **View**.

`payment_methods` already **excludes Pay Later (credit)** — a debt can't be paid with credit.

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

**Still not fully paid after this payment** → also send the new due date and customer:
```json
{ "payment_method": "cash", "amount_paid": 30000, "customer_name": "John Mwangi", "customer_phone": "0712345678", "due_date": "2026-10-20" }
```

- `amount_paid` above the balance is capped to the balance.
- Response: updated `debt` + `payment_message` (e.g. "Payment of TZS 50,000 recorded. Balance TZS 50,000 due 2026-10-20.").
- Errors: `422` "No outstanding balance on this account." / validation errors.

---

## 4. History

`GET /debts/history?tab=payments&page=1` — every payment collected on debt accounts (newest first).

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

`GET /debts/history?tab=settled` — fully paid accounts; each row = debt fields + `payments_count` + `settled_at`.

---

## Related

- Sales / POS: [`API_MOBILE.md`](API_MOBILE.md) — `POST /sales/{id}/pay`, `POST /sales/{id}/remind`
- Customers: [`API_CUSTOMERS.md`](API_CUSTOMERS.md)
- Debt report (aging, charts): [`API_REPORTS.md`](API_REPORTS.md) — `GET /reports/debts`
