# Staff Shift Handover API (Mobile)

This is the mobile version of the web page **`/day-closing?shift={id}`**, e.g. `http://192.168.100.123:5000/day-closing?shift=103`. On the web the cashier sees "Daily Reconciliation", reviews the shift and submits **Handover to Boss**.

Base URL: `/api/v1` · Auth: `Authorization: Bearer {token}` · Accept: `application/json`

Owner-side verification, history and the boss review are covered in [`API_DAY_CLOSING.md`](API_DAY_CLOSING.md). The next shift opening (stock check) is covered in [`API_SHIFTS.md`](API_SHIFTS.md).

---

## Flow

```
GET  /shifts/current                     → has an open shift? (shift.id)
GET  /day-closing/preview?shift=103      → load the full handover screen
(user adds expenses locally, optional note)
POST /day-closing                        → submit handover + close shift
→ next: GET /shifts/open-form            → stock check before the next shift
```

| Endpoint | Permission | Purpose |
|----------|------------|---------|
| `GET /day-closing/preview` | `submit_day_closing`, `process_sales`, `verify_day_closing` or `view_reports` | Handover screen data |
| `POST /day-closing` | `submit_day_closing` or `process_sales` | Submit handover (closes the shift) |

---

## 1. Load handover screen

`GET /day-closing/preview?shift=103`

| Query | Required | Notes |
|-------|----------|-------|
| `shift` or `shift_id` | Recommended | The shift to hand over. If you omit it, the API picks the user's open shift, or else the latest closed shift that has not been handed over yet (same as web). |
| `closing_date` | No | Only used when there is no shift. For shifts, the date is always the **shift's opened date**. |
| `handover_context` | No | `services` for the Service Handover page (`/services/handover`). The default is retail. |

### Response 200

```json
{
  "success": true,
  "data": {
    "context": "retail",
    "closing_date": "2026-10-02",
    "display_date": "Friday, October 02, 2026",
    "shift": {
      "id": 103,
      "status": "open",
      "opened_at": "2026-10-02T15:34:53+03:00",
      "closed_at": null,
      "sales_count": 1,
      "gross_sales": 18000,
      "amount_collected": 0,
      "is_open": true,
      "banner": "Shift #103 open since Oct 02, 2026 03:34 PM"
    },
    "stats": {
      "active_staff": 1,
      "gross_sales": 18000,
      "total_cash": 0,
      "digital_and_bank": 0
    },
    "summary": {
      "sales_count": 1,
      "gross_sales": 18000,
      "amount_collected": 0,
      "outstanding_sales": 18000,
      "cancelled_sales": 0
    },
    "staff_reconciliation": [
      {
        "staff": { "id": 29, "name": "SINDATO", "email": "joshua@gmail.com" },
        "orders": 1,
        "gross_sales": 18000,
        "cash": 0,
        "mobile": 0,
        "bank": 0,
        "debt_paid": 0,
        "expected": 18000,
        "collected": 0,
        "credit": 18000,
        "difference": 0,
        "status": "pending"
      }
    ],
    "debt_collections": {
      "total": 0,
      "count": 0,
      "items": [
        {
          "amount": 5000,
          "method": "mobile_money",
          "provider": "M-Pesa",
          "reference": "QW12...",
          "collected_at": "Oct 02, 2026 04:10 PM",
          "collected_by": "SINDATO",
          "sale_ref": "ORD-20260930-0012",
          "sale_date": "Sep 30, 2026",
          "customer": "John",
          "customer_phone": "0712..."
        }
      ]
    },
    "sales": [
      {
        "ref": "ORD-20261002-1832",
        "sale_txn_ref": null,
        "cashier": "SINDATO",
        "total": 18000,
        "paid": 0,
        "balance": 18000,
        "status": "pending",
        "time": "03:34 PM",
        "customer": "Walk-in",
        "payments": [
          { "method": "cash", "provider": null, "amount": 0, "reference": null, "time": "03:34 PM" }
        ],
        "carried_over": false,
        "origin_shift_id": null,
        "shift_collected": null
      }
    ],
    "sales_count_all": 1,
    "platform_breakdown": [
      { "key": "cash", "label": "Physical Cash", "method": "cash", "amount": 150000 },
      { "key": "m_pesa", "label": "M-Pesa", "method": "mobile_money", "amount": 80000 }
    ],
    "handover_summary": {
      "total_cash": 150000,
      "mobile_money": 80000,
      "bank": 0,
      "gross_collections": 230000
    },
    "expected_handover": 230000,
    "expense_sources": [
      { "key": "cash", "label": "Physical Cash" },
      { "key": "m_pesa", "label": "M-Pesa" }
    ],
    "platform_amounts_locked": true,
    "can_submit": true,
    "cannot_submit_reason": null,
    "existing_handover": null,
    "submit": {
      "label": "Submit Handover & Close Shift",
      "closes_shift": true,
      "payload": { "closing_date": "2026-10-02", "shift_id": 103 }
    }
  }
}
```

### Mapping the response to the screen (same layout as web)

| Web section | Fields |
|-------------|--------|
| Shift banner (blue alert) | `shift.banner`, `summary.sales_count`, `summary.gross_sales` |
| 4 stat cards (Active Staff, Gross Sales, Total Cash, Digital + Bank) | `stats.*` |
| Staff Reconciliation table | `staff_reconciliation[]`. `status` is `paid`, `partial` or `pending`, and the web status filter uses it. |
| "View All Sales (N)" modal | `sales[]`, `sales_count_all`. Rows with `carried_over: true` are earlier-shift orders whose payment came in during this shift; show `shift_collected`. |
| Prior-Shift Collections | `debt_collections.items[]`, `debt_collections.total` |
| Handover Summary (Cash / Mobile / Bank / Gross) | `handover_summary.*` |
| Collection Breakdown by Platform (read-only boxes) | `platform_breakdown[]`. Hide rows with `amount = 0` like web does. |
| Record Expense → "Paid From" dropdown | `expense_sources[]` |
| Final Amount to Handover | Compute on the device: `expected_handover − sum(expenses)`, never below 0 |
| Submit button text | `submit.label` |

### Rules for the app

- **Platform amounts are locked** (`platform_amounts_locked: true`). The cashier cannot edit them; they can only record expenses.
- When an expense is added, subtract it from its platform box on screen: `max(0, platform.amount − expense.amount)`. The server applies the same deduction.
- If `can_submit` is `false`, show `cannot_submit_reason` and hide the submit button. If `existing_handover` is not null, open `GET /day-closing/{existing_handover.id}`; this is where web redirects.

---

## 2. Submit handover

`POST /day-closing`

Start with `submit.payload` from the preview, then add expenses and the note.

| Field | Required | Notes |
|-------|----------|-------|
| `closing_date` | **Yes** | From `submit.payload.closing_date` |
| `shift_id` | **Yes** for cashiers | From `submit.payload.shift_id` |
| `expenses` | No | `[{ description, amount, payment_method }]`. `payment_method` must be a `key` from `expense_sources`. |
| `expenses.*.description` | With expenses | max 255 |
| `expenses.*.amount` | With expenses | ≥ 0.01 |
| `report_notes` | No | "Note to Boss", max 2000 |
| `handover_context` | No | `services` for service handover |

`platform_amounts` is **ignored**. Totals always come from the system, the same as on web.

### Example

```json
{
  "closing_date": "2026-10-02",
  "shift_id": 103,
  "expenses": [
    { "description": "Transport", "amount": 5000, "payment_method": "cash" }
  ],
  "report_notes": "All cash counted"
}
```

### Response 201

```json
{
  "success": true,
  "message": "Handover submitted and your shift is now closed.",
  "data": {
    "handover": { "id": 210, "status": "submitted", "closing_date": "2026-10-02", "summary": { "net_amount": 225000, "total_expenses": 5000 } },
    "shift_closed": true,
    "next": "shifts.open"
  }
}
```

After success:

- The shift is **closed** and the boss is notified (SMS, email, in-app).
- Send the user to the open-shift stock check: [`API_SHIFTS.md`](API_SHIFTS.md) → `GET /shifts/open-form`.

---

## Errors (422)

Format: `{ "success": false, "message": "...", "errors": { "<field>": ["..."], "code": "..." } }`

| `errors.code` | When | App action |
|---------------|------|------------|
| `NO_SHIFT` | Preview without a shift and the user has no open or pending shift | Go to Open Shift |
| `SHIFT_NOT_FOUND` | The shift does not exist or belongs to another user | Reload `GET /shifts/current` |
| `SHIFT_REQUIRED` | Submit without `shift_id` (cashier) | Use `submit.payload` |
| `ALREADY_SUBMITTED` | A handover for this shift already exists | Open the handover detail |
| `DAY_ALREADY_CLOSED` | Non-shift user, and the day is already closed | Show the message |
| `INVALID_EXPENSE_SOURCE` | An expense's `payment_method` is not in `expense_sources` | Fix the dropdown value |
| `OWNER_CANNOT_SUBMIT` | The owner tried to submit | Owners verify instead (`API_DAY_CLOSING.md`) |

Errors without a `code` are normal field validation errors (e.g. a missing `closing_date`).
