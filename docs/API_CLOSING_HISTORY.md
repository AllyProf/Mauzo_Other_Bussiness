# Closing History API (Mobile)

This is the mobile version of the web page **`/day-closing/history`**: a list of all submitted, verified and disputed day closings (shift handovers).

Base URL: `/api/v1` · Auth: `Authorization: Bearer {token}` · Accept: `application/json`

Related docs:

- Submitting a handover (web `/day-closing?shift=…`): [`API_HANDOVER.md`](API_HANDOVER.md)
- Verify, dispute and boss review: [`API_DAY_CLOSING.md`](API_DAY_CLOSING.md)

---

## Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/day-closing/history` | Paginated closing history (same as web) |
| `GET` | `/day-closing/{id}` | Full detail of one closing (tap a row) |

**Permission:** `view_closing_history`, `view_reports` or `verify_day_closing`. Without one of these the API returns `403`.

---

## 1. List closing history

`GET /day-closing/history?status=verified&date_from=2026-09-01&date_to=2026-09-30&per_page=20`

| Query | Required | Values |
|-------|----------|--------|
| `status` | No | `submitted`, `verified`, `disputed`. Any other value is ignored, so all are returned. |
| `date_from` | No | `YYYY-MM-DD`, closing date ≥ this |
| `date_to` | No | `YYYY-MM-DD`, closing date ≤ this |
| `business_type` | No | A key from `filters.business_types` (multi-business shops only) |
| `branch_id` | No | Owner only. `0` means all branches; the default is the branch chosen via `/auth/switch-branch`. Staff always see their own branch. |
| `page` | No | Page number (default 1) |
| `per_page` | No | 1–50 (default 20) |

Results are sorted **newest closing date first**.

### Response 200

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
    "filters": {
      "branch_id": null,
      "branch_name": null,
      "viewing_all_branches": true,
      "business_types": [
        { "key": "liquor", "label": "Liquor Store / Bar" }
      ],
      "multi_business": false,
      "active_business_type": null
    },
    "meta": { "current_page": 1, "last_page": 3, "per_page": 20, "total": 52 }
  }
}
```

### Fields

| Field | Meaning |
|-------|---------|
| `closing_date` / `closing_date_label` | Day of the closing (raw / display) |
| `staff` | Who submitted the handover |
| `shift_id` | Shift handed over. `null` for owner-direct or daily closings. |
| `business_types` | Business labels covered by the closing |
| `sales_count` | Number of sales in the closing |
| `gross_sales` | Total sales value |
| `payments_received` | Amount collected ("Collected" column on web) |
| `total_expenses` | Expenses recorded at handover |
| `net_amount` | Amount handed to the boss |
| `money_short` | Shortage found by the owner on verify (`0` if none) |
| `status` | `submitted` (awaiting the boss), `verified` or `disputed` |
| `verifier` | Name of the owner who verified (or `null`) |
| `submitted_at` / `verified_at` | ISO timestamps |

### Screen mapping (same as web table)

| Web column | Field |
|------------|-------|
| Date | `closing_date_label` |
| Staff | `staff.name` |
| Business | `business_types` (join with ", ") |
| Sales | `sales_count` |
| Gross Sales | `gross_sales` |
| Collected | `payments_received` |
| Expenses | `total_expenses` |
| Net | `net_amount` |
| Status | `status` badge: submitted = orange, verified = green, disputed = red |
| Submitted | `submitted_at` |

Filters on screen:

- A status dropdown.
- A date range (from and to).
- A business-type chip row, shown only when `filters.multi_business` is true.
- A branch picker for owners.

Use `meta.last_page` for infinite scroll or pagination.

---

## 2. Closing detail

`GET /day-closing/{id}`

This returns the full handover card: platform breakdown, expenses, shift stats, debt collections, notes, and the money short / dispute reason. Owners also get `finance` and `can_verify`. The full response is shown in [`API_DAY_CLOSING.md`](API_DAY_CLOSING.md) §6.

Staff without `verify_day_closing` can open **only their own** closings; any other closing returns `403`.

---

## Errors

| Status | When |
|--------|------|
| `401` | Missing or expired token |
| `403` | No closing-history permission, or a closing from another business or user (detail) |
| `404` | The closing id does not exist (detail) |
