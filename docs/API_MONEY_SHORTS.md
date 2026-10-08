# Money Shorts API

Track verified handover shortages, record staff repayments (cash to Master Sheet), or salary deductions. Same as web **[Money Shorts](https://www.mauzolink.co.tz/money-shorts)** (`/money-shorts`).

**Base URL:** `/api/v1`  
**Production:** `https://www.mauzolink.co.tz/api/v1`  
**Web (same data):** [https://www.mauzolink.co.tz/money-shorts](https://www.mauzolink.co.tz/money-shorts)  
**Auth:** `Authorization: Bearer {token}` · `Accept: application/json`  
**Permissions:** `manage_money_shorts`, `verify_day_closing`, or `view_reports` (same as web)

| Method | Endpoint |
|--------|----------|
| GET | `/money-shorts` |
| POST | `/money-shorts/{dayClosing}/pay` |
| POST | `/money-shorts/{dayClosing}/salary-deduction` |
| DELETE | `/money-shorts/settlements/{settlement}` |

**Envelope:** `{ "success": true, "message": "OK", "data": { ... } }` · errors use `success: false` and HTTP `403` / `422`.

---

## 1. List money shorts

```
GET /api/v1/money-shorts
```

### Query parameters

| Param | Type | Description |
|-------|------|-------------|
| `status` | string | `all` (default), `outstanding`, or `settled` |
| `business_type` | string | Department key (multi-business branches); allocates short by sales share |
| `search` | string | Staff name or shortage note |
| `branch_id` | int | Owner only; `0` = all branches (default = branch from `/auth/switch-branch`) |

### Success response `200`

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "title": "Money Shorts",
    "web_path": "/money-shorts",
    "stats": {
      "total_records": 4,
      "outstanding_count": 2,
      "outstanding_total": 15000,
      "settled_count": 2,
      "total_short": 42000
    },
    "status_filter": "all",
    "shorts": [
      {
        "id": 143,
        "day_closing_id": 143,
        "verified_at": "2026-09-20T14:22:00+03:00",
        "verified_at_label": "Sep 20, 2026",
        "staff": { "id": 37, "name": "SINDATO STORE" },
        "verifier": "Owner Name",
        "shift_id": 101,
        "shift_label": "#101",
        "closing_date": "2026-09-20",
        "closing_date_label": "Sep 20, 2026",
        "business_types": ["Liquor Store / Bar"],
        "money_short": 8000,
        "display_short": 8000,
        "amount_paid": 3000,
        "balance_due": 5000,
        "settlement_status": "partial",
        "short_split": { "profit_short": 2000, "circulation_short": 6000 },
        "shortage_note": "Cash count short",
        "can_record_payment": true,
        "can_record_salary_deduction": true,
        "settlements": [],
        "detail_url": "/day-closing/143"
      }
    ],
    "settlement_history": [
      {
        "id": 12,
        "day_closing_id": 143,
        "settlement_type": "cash_payment",
        "type_label": "Cash Payment",
        "amount": 3000,
        "settlement_date": "2026-09-21",
        "payment_method": "cash",
        "payment_provider": null,
        "transaction_reference": null,
        "notes": null,
        "staff": { "id": 37, "name": "SINDATO STORE" },
        "shift_label": "#101",
        "recorded_by": "Owner Name",
        "created_at": "2026-09-21T09:00:00+03:00",
        "voided": false,
        "voided_at": null,
        "voided_by": null,
        "can_undo": true
      }
    ],
    "payment_methods": [
      { "key": "cash", "label": "Cash" },
      { "key": "mpesa", "label": "M-Pesa" }
    ],
    "filters": {
      "branch_id": 10,
      "branch_name": "Main",
      "viewing_all_branches": false,
      "business_types": [],
      "multi_business": false,
      "active_business_type": null,
      "active_business_label": null
    }
  }
}
```

### `settlement_status` values

| Value | Meaning |
|-------|---------|
| `pending` | No payment recorded yet |
| `partial` | Some amount paid; balance remains |
| `paid` | Fully settled via cash payment(s) |
| `salary_deduction` | Cleared via salary deduction |
| `none` | No allocated short for filtered business type |

Handover detail (platform breakdown, verify context): `GET /api/v1/day-closing/{id}` — see [`API_DAY_CLOSING.md`](API_DAY_CLOSING.md).

---

## 2. Record cash repayment

Posts recovery to the **Master Sheet** on `settlement_date` (same as web **Record payment**).

```
POST /api/v1/money-shorts/{dayClosing}/pay
Content-Type: application/json
```

| Field | Required | Description |
|-------|----------|-------------|
| `amount` | yes | Up to outstanding `balance_due` |
| `settlement_date` | yes | `YYYY-MM-DD` (Master Sheet date) |
| `payment_method` | yes | Key from `payment_methods` on index |
| `payment_provider` | no | e.g. M-Pesa provider name |
| `transaction_reference` | no | Receipt / reference |
| `notes` | no | Max 1000 chars |

```json
{
  "success": true,
  "message": "Payment recorded and posted to the Master Sheet for 2026-09-21.",
  "data": {
    "settlement": { "id": 13, "amount": 5000, "settlement_type": "cash_payment" },
    "short": { "id": 143, "balance_due": 0, "settlement_status": "paid" }
  }
}
```

---

## 3. Record salary deduction

Clears balance **without** adding cash to the Master Sheet.

```
POST /api/v1/money-shorts/{dayClosing}/salary-deduction
```

| Field | Required | Description |
|-------|----------|-------------|
| `amount` | no | Defaults to full outstanding balance |
| `settlement_date` | no | Defaults to today |
| `notes` | no | Max 1000 chars |

---

## 4. Undo settlement

```
DELETE /api/v1/money-shorts/settlements/{settlement}
```

Voids the settlement. Cash payments reverse the Master Sheet entry for that settlement date.

```json
{
  "success": true,
  "message": "Cash Payment of 5,000 undone. Master Sheet for 2026-09-21 has been updated.",
  "data": {
    "undone": {
      "settlement_id": 13,
      "amount": 5000,
      "type_label": "Cash Payment",
      "settlement_date": "2026-09-21",
      "was_cash_payment": true
    },
    "new_balance": 5000,
    "short": { "id": 143, "balance_due": 5000, "settlement_status": "partial" }
  }
}
```

---

## Related docs

- Verify handover / record money short: [`API_DAY_CLOSING.md`](API_DAY_CLOSING.md)
- Closing history list: [`API_CLOSING_HISTORY.md`](API_CLOSING_HISTORY.md) · `GET /day-closing/history`
