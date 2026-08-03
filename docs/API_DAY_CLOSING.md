# Day Closing / Handover API (Mobile)

REST endpoints for end-of-day cash handover — same behavior as web `/day-closing`.

---

## What this feature does

At the end of a shift (or day), staff **submit a handover**: declare cash / mobile money / bank received, record expenses, and send totals to the owner for verification.

| Role | What they do |
|------|----------------|
| **Staff / cashier** | Preview totals → enter platform amounts + expenses → submit |
| **Owner** | Review pending handovers → verify (or dispute) oldest first |

On submit:
- Shift is closed if still open
- SMS/email notifications fire (same as web)
- Status becomes `submitted` until owner verifies

### Typical mobile flows

**Staff**
1. `GET /shifts/current` — ensure shift exists  
2. `GET /day-closing/preview?shift_id=` — load expected totals  
3. User adjusts `platform_amounts` / adds expenses  
4. `POST /day-closing` — submit  
5. `GET /day-closing/{id}` — view submitted card  

**Owner**
1. `GET /day-closing/pending` — list to verify  
2. `GET /day-closing/{id}` — full detail  
3. `POST /day-closing/{id}/verify` — approve or dispute  

---

| Environment | Base URL |
|-------------|----------|
| Production | `https://www.mauzolink.co.tz/api/v1` |
| Local | `http://192.168.100.106:5000/api/v1` |

```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

---

## Endpoints

| Method | Endpoint | Who | Description |
|--------|----------|-----|-------------|
| `GET` | `/day-closing/preview` | Staff | Form data before submit |
| `POST` | `/day-closing` | Staff | Submit handover |
| `GET` | `/day-closing` | Staff/Owner | History (`?date=`, `?status=`, `?per_page=`) |
| `GET` | `/day-closing/review` | Owner | Boss day review (`?date=` + optional `handover_id`) |
| `GET` | `/day-closing/pending` | Owner | Pending / disputed queue |
| `GET` | `/day-closing/{id}` | Staff/Owner | Full handover detail |
| `POST` | `/day-closing/{id}/verify` | Owner | Approve or dispute |
| `GET` | `/day-closing/owner-direct` | Owner | Preview owner's own POS sales for a date |
| `POST` | `/day-closing/owner-direct` | Owner | One-step Verify & Close owner POS sales |

**Permissions**
- Preview / submit: `submit_day_closing` or `process_sales`
- List / show: also `verify_day_closing`, `view_reports`, `view_closing_history`
- Pending + verify: **owner only**
- Owner-direct post: **owner only** (not a staff handover)

**Important:** Owners do **not** submit a cashier-style handover (`POST /day-closing`). When the owner sells on POS, they use **owner-direct** (`/day-closing/owner-direct`) — one-step Verify & Close (verified + Master Sheet draft). Finalize separately on Master Sheet.

`GET /day-closing/review?date=` includes an `owner_direct` block with `can_post`, totals, and `platform_breakdown`.

---

## 1. Preview (handover form)

`GET /day-closing/preview?shift_id=123&closing_date=2026-07-28`

| Query | Required | Notes |
|-------|----------|-------|
| `shift_id` | Usually yes for cashiers | Shift to hand over |
| `closing_date` | No | Defaults to today / shift date |

### Response highlights

```json
{
  "success": true,
  "data": {
    "closing_date": "2026-07-28",
    "shift": {
      "id": 123,
      "status": "open",
      "opened_at": "...",
      "sales_count": 12,
      "gross_sales": 450000,
      "amount_collected": 400000
    },
    "summary": {
      "sales_count": 12,
      "gross_sales": 450000,
      "amount_collected": 400000,
      "outstanding_sales": 50000,
      "cancelled_sales": 0
    },
    "platform_breakdown": [
      { "key": "cash", "label": "Cash", "method": "cash", "amount": 150000 },
      { "key": "mobile_money:mpesa", "label": "M-Pesa", "method": "mobile_money", "amount": 80000 }
    ],
    "expected_handover": 230000,
    "debt_collections": { "total": 20000, "count": 2 },
    "can_submit": true
  }
}
```

Use `platform_breakdown[].key` as keys in `platform_amounts` when submitting.  
If `can_submit` is `false`, handover was already submitted for this shift/date.

---

## 2. Submit handover

`POST /day-closing`

### Required / main fields

| Field | Required | Notes |
|-------|----------|-------|
| `closing_date` | **Yes** | `YYYY-MM-DD` |
| `shift_id` | Yes for cashiers | Must belong to the logged-in user |
| `report_notes` | No | Free text |
| `platform_amounts` | No | Override declared amounts per platform key |
| `expenses` | No | Array of `{ description, amount, payment_method }` |

### Example

```json
{
  "closing_date": "2026-07-28",
  "shift_id": 123,
  "report_notes": "All cash counted",
  "platform_amounts": {
    "cash": 150000,
    "mobile_money:mpesa": 80000
  },
  "expenses": [
    {
      "description": "Transport",
      "amount": 5000,
      "payment_method": "cash"
    }
  ]
}
```

- Omit `platform_amounts` to use system totals from preview  
- Expenses reduce the matching payment method (`cash`, mobile, bank)  
- **Response 201** — full `handover` detail card  

### Errors

- Owner trying to submit → 422  
- Missing / invalid shift → 422  
- Already submitted → 422  

---

## 3. Boss day review (web date + #handover)

Web URL like:

`/day-closing?date=2026-06-18#handover-15`

means: **owner** opens the boss review for **2026-06-18**, scrolled to handover card **#15**.

### API equivalents

| Web | API |
|-----|-----|
| Load the day page | `GET /day-closing/review?date=2026-06-18&handover_id=15` |
| Open only that card | `GET /day-closing/15` |
| Verify that card | `POST /day-closing/15/verify` |

### Review response

```json
{
  "success": true,
  "data": {
    "date": "2026-06-18",
    "display_date": "Thursday, June 18, 2026",
    "focus_handover_id": 15,
    "handovers": [ { "id": 15, "status": "submitted", "can_verify_now": true, "..." : "..." } ],
    "stats": {
      "handovers_count": 1,
      "pending_on_date": 1,
      "pending_other_days": 14,
      "awaiting_shifts": 0
    },
    "pending_from_other_days": [ { "id": 29, "closing_date": "2026-07-28", "review_url": "/day-closing?date=2026-07-28#handover-29" } ],
    "awaiting_handover_shifts": [],
    "next_to_verify": { "id": 15, "closing_date": "2026-06-18" },
    "deep_link": {
      "web": "/day-closing?date=2026-06-18#handover-15",
      "api_handover": "/day-closing/15"
    }
  }
}
```

`#handover-15` on web is only a scroll anchor — mobile should open `focus_handover_id` (or navigate to `GET /day-closing/{id}`).

---

## 4. List history

`GET /day-closing?date=2026-06-18&status=submitted&per_page=20`

| Query | Values |
|-------|--------|
| `date` | `YYYY-MM-DD` — filter by closing date |
| `status` | `submitted`, `verified`, `disputed` |
| `per_page` | 1–50 (default 20) |

Staff see **their own** handovers. Owners / verifiers see the business.

---

## 5. Pending (owner)

`GET /day-closing/pending`

Returns handovers with status `submitted` or `disputed`, oldest first. Each row includes `review_url` for the web deep link pattern.

---

## 6. Detail

`GET /day-closing/{id}`

Full card: summary, platform breakdown, expenses, shortage fields, verifier, notes.

Staff can only view **their own** unless they have verify/report permissions.

---

## 7. Verify (owner)

`POST /day-closing/{id}/verify`

Must verify the **oldest** pending handover first.

### Approve

```json
{
  "actual_received": 225000,
  "shortage_note": "Missing 2000 from drawer"
}
```

- `actual_received` **required**  
- `shortage_note` **required** if `actual_received` < expected net  

### Dispute

```json
{
  "actual_received": 220000,
  "shortage_note": "Count mismatch",
  "dispute_reason": "Staff declared more mobile money than received"
}
```

Passing `dispute_reason` sets status to `disputed`.

On verify, Master Sheet sync runs (same as web). The day is **not** auto-finalized — finalize on Master Sheet when ready. SMS/email notify the staff member.

---

## 8. Owner direct sales (when owner sells on POS)

Same as web `/day-closing` → **Post to Master Sheet**.

Owner sales do **not** appear as a staff handover card. They are posted separately and create an auto-`verified` `DayClosing` with `shift_id: null`.

### Preview

```
GET /api/v1/day-closing/owner-direct?date=2026-07-31
```

Also returned inside `GET /day-closing/review?date=…` → `data.owner_direct`.

```json
{
  "success": true,
  "data": {
    "available": true,
    "can_post": true,
    "already_posted": false,
    "awaiting_verify": false,
    "status": null,
    "posted_closing_id": null,
    "summary": {
      "sales_count": 3,
      "gross_sales": 150000,
      "amount_collected": 150000,
      "outstanding_sales": 0,
      "cancelled_sales": 0
    },
    "expected_handover": 150000,
    "platform_breakdown": [
      { "key": "cash", "label": "Cash", "method": "cash", "amount": 150000 }
    ],
    "hint": "You sold on POS today. One step: Verify & Close to post to the Master Sheet."
  }
}
```

### Post (one-step Verify & Close)

```
POST /api/v1/day-closing/owner-direct
```

```json
{
  "closing_date": "2026-07-31",
  "actual_received": 150000,
  "shortage_note": null,
  "report_notes": "Owner counter sales",
  "handover_scope": "retail"
}
```

| Field | Required | Notes |
|-------|----------|-------|
| `closing_date` | Yes | Date of the owner POS sales |
| `actual_received` | Yes | Cash counted (defaults idea = `expected_handover`) |
| `shortage_note` | If short | Required when actual &lt; expected |
| `report_notes` | No | |
| `handover_scope` | No | `retail` (default) or `service` |

Creates a `verified` closing and syncs Master Sheet as draft. Does **not** auto-finalize — finalize on Master Sheet / `POST /owner-reports/{date}/finalize` when ready.
---

## Status values

| Status | Meaning |
|--------|---------|
| `submitted` | Waiting for owner |
| `verified` | Owner approved |
| `disputed` | Owner disputed (still in pending queue) |

---

## Related

- Shifts: open/close before handover — `API_MOBILE.md` Shifts  
- Sales / debts feed the preview totals  
- Overview: [`API_MOBILE.md`](API_MOBILE.md)
