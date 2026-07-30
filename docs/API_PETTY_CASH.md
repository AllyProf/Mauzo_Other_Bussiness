# Petty Cash API

Issue cash from circulation or profit for restock, payments, salaries, or operations.
See available balances before issuing, and track all issued petty cash with history.

**Base URL:** `/api/v1`
**Auth:** `Authorization: Bearer {token}`
**Permissions:** `manage_petty_cash` (issue/delete) or `view_reports` (read-only)

---

## 1. List petty cash (index)

```
GET /api/v1/petty-cash
Authorization: Bearer {token}
```

### Query parameters

| Param | Type | Description |
|-------|------|-------------|
| `date` | `string` | Date for balances (YYYY-MM-DD). Defaults to first non-finalized day |
| `business_type` | `string` | Filter by business type key (e.g. `retail`, `service_pos`) |
| `branch_id` | `int` | Filter by branch (owner only) |
| `start_date` | `string` | History filter: from date |
| `end_date` | `string` | History filter: to date |
| `fund_source` | `string` | `circulation` or `profit` |
| `category` | `string` | `restock`, `payment`, `salary`, `operational`, `other` |
| `page` | `int` | Pagination page (20 per page) |

### Success response `200`

```json
{
  "success": true,
  "data": {
    "date": "2026-07-29",
    "date_label": "29 Jul, 2026",
    "balances": {
      "date": "2026-07-29",
      "date_label": "29 Jul, 2026",
      "next_open_date": null,
      "next_open_date_label": null,
      "opening_circulation": 500000,
      "opening_profit": 120000,
      "available_circulation": 415000,
      "available_profit": 120000,
      "owner_circulation_spent": 85000,
      "owner_profit_spent": 0,
      "daily_net_profit": 35000,
      "is_finalized": false,
      "business_type_key": null,
      "business_type_label": null,
      "scoped_to_business_type": false
    },
    "default_fund_source": "circulation",
    "business_types": [
      { "key": "retail", "label": "Retail", "icon": "fa-store", "is_active": false }
    ],
    "multi_business": false,
    "active_business_type": null,
    "staff_members": [
      { "id": 5, "name": "John Doe", "role": "staff" }
    ],
    "expenses": [
      {
        "id": 12,
        "expense_date": "2026-07-29",
        "expense_date_label": "29 Jul, 2026",
        "description": "Restock Coca-Cola crates",
        "amount": 85000,
        "category": "restock",
        "category_label": "Restock / Supply",
        "fund_source": "circulation",
        "fund_source_label": "Money in Circulation",
        "business_type_key": null,
        "business_type_label": null,
        "branch_id": 10,
        "branch_name": "Main Branch",
        "issued_to": { "id": 5, "name": "John Doe" },
        "recorded_by": { "id": 1, "name": "Owner" },
        "is_locked": false,
        "created_at": "2026-07-29T10:30:00+03:00"
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 20,
      "total": 1
    },
    "categories": [
      { "key": "restock", "label": "Restock / Supply" },
      { "key": "payment", "label": "General Payment" },
      { "key": "salary", "label": "Salary / Wages" },
      { "key": "operational", "label": "Operational" },
      { "key": "other", "label": "Other" }
    ],
    "fund_sources": [
      { "key": "circulation", "label": "Money in Circulation" },
      { "key": "profit", "label": "Profit" }
    ]
  }
}
```

---

## 2. Get balances for a date

```
GET /api/v1/petty-cash/balances?date=2026-07-29&business_type=retail
Authorization: Bearer {token}
```

### Required params

| Param | Type | Description |
|-------|------|-------------|
| `date` | `string` | **Required.** Date (YYYY-MM-DD) |
| `business_type` | `string` | Optional business type key |

### Success response `200`

```json
{
  "success": true,
  "data": {
    "date": "2026-07-29",
    "date_label": "29 Jul, 2026",
    "next_open_date": null,
    "next_open_date_label": null,
    "opening_circulation": 500000,
    "opening_profit": 120000,
    "available_circulation": 415000,
    "available_profit": 120000,
    "owner_circulation_spent": 85000,
    "owner_profit_spent": 0,
    "daily_net_profit": 35000,
    "is_finalized": false,
    "business_type_key": "retail",
    "business_type_label": "Retail",
    "scoped_to_business_type": true
  }
}
```

> If `is_finalized` is `true`, the mobile app should use `next_open_date` to redirect to the next available day.

---

## 3. Issue petty cash

```
POST /api/v1/petty-cash
Authorization: Bearer {token}
```

### Request body

```json
{
  "expense_date": "2026-07-29",
  "description": "Restock Coca-Cola crates",
  "amount": 85000,
  "category": "restock",
  "fund_source": "circulation",
  "issued_to_user_id": 5,
  "business_type_key": "retail"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `expense_date` | `string` | Yes | Date (YYYY-MM-DD) |
| `description` | `string` | Yes | Max 1000 chars |
| `amount` | `number` | Yes | Min 0.01. Cannot exceed available balance for chosen fund source |
| `category` | `string` | Yes | `restock`, `payment`, `salary`, `operational`, `other` |
| `fund_source` | `string` | Yes | `circulation` or `profit` |
| `issued_to_user_id` | `int` | No | Staff member this cash is issued to |
| `business_type_key` | `string` | Required if multi-business | Department key |
| `branch_id` | `int` | No | Override branch (owner only) |

### Validation rules

- **Finalized day:** Cannot issue on a finalized day (`422`)
- **Insufficient balance:** Amount cannot exceed available balance for the selected fund source (`422`)
- **Staff member:** Must be active and belong to the same business/branch (`422`)

### Success response `201`

```json
{
  "success": true,
  "message": "Petty cash issued successfully.",
  "data": {
    "expense": {
      "id": 13,
      "expense_date": "2026-07-29",
      "expense_date_label": "29 Jul, 2026",
      "description": "Restock Coca-Cola crates",
      "amount": 85000,
      "category": "restock",
      "category_label": "Restock / Supply",
      "fund_source": "circulation",
      "fund_source_label": "Money in Circulation",
      "business_type_key": "retail",
      "business_type_label": "Retail",
      "branch_id": 10,
      "branch_name": "Main Branch",
      "issued_to": { "id": 5, "name": "John Doe" },
      "recorded_by": { "id": 1, "name": "Owner" },
      "is_locked": false,
      "created_at": "2026-07-29T14:22:00+03:00"
    },
    "balances": {
      "date": "2026-07-29",
      "date_label": "29 Jul, 2026",
      "available_circulation": 330000,
      "available_profit": 120000,
      "...": "..."
    }
  }
}
```

---

## 4. Delete petty cash entry

```
DELETE /api/v1/petty-cash/{id}
Authorization: Bearer {token}
```

### Restrictions

- Cannot delete entries from a **finalized** day
- Cannot delete entries from **another branch** (for branch-scoped users)
- Entry must belong to the authenticated user's business

### Success response `200`

```json
{
  "success": true,
  "message": "Petty cash entry removed.",
  "data": {
    "deleted_expense_id": 13,
    "balances": {
      "date": "2026-07-29",
      "date_label": "29 Jul, 2026",
      "available_circulation": 415000,
      "available_profit": 120000,
      "...": "..."
    }
  }
}
```

---

## Mobile app flow

1. **Load page:** `GET /petty-cash` — shows balances, staff, expenses history, categories, fund sources
2. **Change date:** `GET /petty-cash/balances?date=YYYY-MM-DD` — refresh balances for a different date
3. **Issue cash:** `POST /petty-cash` — validate amount against available balance first
4. **Delete entry:** `DELETE /petty-cash/{id}` — only if `is_locked` is `false`

### Key UX rules

- If `balances.is_finalized` is `true`, disable the issue form and show `next_open_date`
- Before issuing, check `amount <= balances.available_circulation` (or `available_profit`)
- Show `fund_source_label` and `category_label` in the UI (not raw keys)
- `is_locked` on an expense means the day is finalized — hide delete button

---

## Error responses

All errors follow the standard envelope:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "amount": ["Amount exceeds available circulation money on 2026-07-29 (TZS 415,000 available)."]
  }
}
```

---

## Related docs

- Mobile overview: [`API_MOBILE.md`](API_MOBILE.md)
- Owner Reports (expenses via master sheet): [`API_OWNER_REPORTS.md`](API_OWNER_REPORTS.md)
