# Owner Reports / Master Sheet API (Mobile)

REST endpoints for the owner **Master Sheet** (daily financial ledger) — same behavior as web `/owner-reports`.

---

## What this feature does

After staff handovers are **verified**, the owner reviews each day on the Master Sheet: collections, expenses, profit, circulation rollover, and optional owner expenses. Owners can **finalize** a day to lock totals and carry circulation forward.

| Role | What they do |
|------|----------------|
| **Owner** | Browse ledger rows, add/remove owner expenses, finalize days |
| **Staff with `view_reports`** | Read-only access to the ledger |

### Typical mobile flows

**Owner — browse**
1. `GET /owner-reports` — paginated ledger (open-day placeholders on page 1)
2. Expand a row locally using `expense_list`, `platform_breakdown`, `business_type_breakdown`
3. Tap through via `review_api_path` or `day_review_api_path` → day-closing APIs

**Owner — one day**
1. `GET /owner-reports/2026-06-18` — all ledger rows for that date
2. `POST /owner-reports/2026-06-18/expenses` — record restock / operational expense
3. `POST /owner-reports/2026-06-18/finalize` — lock the day

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
| `GET` | `/owner-reports` | Owner / `view_reports` | Master Sheet list |
| `GET` | `/owner-reports/{date}` | Owner / `view_reports` | Single-day ledger rows |
| `POST` | `/owner-reports/{date}/expenses` | Owner | Add owner expense |
| `DELETE` | `/owner-reports/{date}/expenses/{id}` | Owner | Remove owner expense |
| `POST` | `/owner-reports/{date}/finalize` | Owner | Finalize daily report |

**Permissions**
- List / show: `view_reports` (owners always allowed)
- Expenses + finalize: **owner** or **super_admin** only

**Branch context:** respects `POST /auth/switch-branch` (owners). Staff see their assigned branch.

---

## 1. List Master Sheet

`GET /owner-reports?start_date=2026-06-01&end_date=2026-06-30&business_type=retail&per_page=20`

| Query | Required | Notes |
|-------|----------|-------|
| `start_date` | No | Filter verified handovers from this date |
| `end_date` | No | Filter verified handovers to this date |
| `business_type` | No | Filter expanded multi-business rows (`retail`, `liquor`, etc.) |
| `per_page` | No | Default 20, max 50 |
| `highlight_date` | No | Echoed in `filters` (for UI scroll/highlight) |

### Response highlights

```json
{
  "success": true,
  "data": {
    "ledgers": [
      {
        "id": "42",
        "handover_id": 42,
        "detail_handover_id": 42,
        "ledger_date": "2026-06-18",
        "is_placeholder": false,
        "business_status": "VERIFIED",
        "handover_label": "John · Shift #7",
        "opening_cash": 500000,
        "total_cash_received": 180000,
        "total_digital_received": 95000,
        "sub_total": 275000,
        "combined_expenses": 12000,
        "daily_net_profit": 45000,
        "circulation_refill": 120000,
        "money_in_circulation": 643000,
        "profit_rollover": 89000,
        "is_finalized": false,
        "expense_list": [],
        "platform_breakdown": [],
        "business_type_breakdown": [],
        "review_api_path": "/day-closing/42",
        "day_review_api_path": "/day-closing/review?date=2026-06-18&handover_id=42"
      }
    ],
    "pending_handovers": [
      {
        "id": 15,
        "closing_date": "2026-06-19",
        "staff": { "id": 3, "name": "Mary" },
        "payments_received": 210000,
        "review_api_path": "/day-closing/review?date=2026-06-19&handover_id=15"
      }
    ],
    "business_types": [
      { "key": "retail", "label": "Retail Shop", "icon": "fa-shopping-basket" }
    ],
    "multi_business": false,
    "filters": {
      "start_date": "2026-06-01",
      "end_date": "2026-06-30",
      "business_type": null,
      "highlight_date": null
    },
    "meta": {
      "current_page": 1,
      "last_page": 3,
      "per_page": 20,
      "total": 45,
      "branch_filter_id": null,
      "viewing_all_branches": true,
      "expense_categories": [
        { "key": "restock", "label": "Restock / Supply" }
      ],
      "fund_sources": [
        { "key": "circulation", "label": "Money in Circulation" },
        { "key": "profit", "label": "Profit" }
      ]
    }
  }
}
```

**Open-day placeholders** (`is_placeholder: true`) appear at the top of page 1 when no date filter is set — same as web. They show live circulation/profit before the day is verified.

**Multi-business rows:** when the business runs more than one type (e.g. retail + liquor), verified days may expand into one row per type (`is_business_type_row: true`). Rollover columns appear only on the last type row of the day (`show_rollover_columns: true`).

---

## 2. Single day

`GET /owner-reports/2026-06-18`

Returns `date`, `ledgers` (all rows for that date), `pending_handovers` for that date, and `business_types`.

---

## 3. Add owner expense

`POST /owner-reports/2026-06-18/expenses`

```json
{
  "description": "Restock Coca-Cola crates",
  "amount": 85000,
  "category": "restock",
  "fund_source": "circulation"
}
```

| Field | Required | Notes |
|-------|----------|-------|
| `description` | Yes | Max 255 chars |
| `amount` | Yes | Min 0.01 |
| `category` | No | `restock`, `payment`, `salary`, `operational`, `other` (default `restock`) |
| `fund_source` | No | `circulation` or `profit` (default: business `expense_deduct_from`) |

Returns `expense` + refreshed `day` snapshot.

**Errors:** 422 if the day is already finalized.

---

## 4. Delete owner expense

`DELETE /owner-reports/2026-06-18/expenses/12`

Returns `deleted_expense_id` + refreshed `day` snapshot.

---

## 5. Finalize day

`POST /owner-reports/2026-06-18/finalize`

```json
{
  "owner_notes": "All counts matched. Carry forward approved."
}
```

- Syncs the daily report from verified handovers
- Verifies a `submitted` handover if needed (same as web)
- Sets report status to `finalized` and updates business `circulation_balance`
- Returns `report` + refreshed `day`

**Errors:** 422 if no handover exists for the date or the report is already finalized.

---

## Related APIs

| Web page | Mobile API |
|----------|------------|
| `/day-closing` (verify handover) | `GET /day-closing/review`, `POST /day-closing/{id}/verify` |
| `/day-closing/{id}` (handover detail) | `GET /day-closing/{id}` |

See [`API_DAY_CLOSING.md`](API_DAY_CLOSING.md).

---

## Ledger status values

| `business_status` | Meaning |
|-------------------|---------|
| `OPEN` | Day not yet closed/finalized |
| `VERIFIED` | Handover verified, not finalized |
| `CLOSED` | Finalized (`is_finalized: true`) |
| `DISPUTED` | Handover disputed |

---

## App developer instructions (Master Sheet table)

**Web screen:** `GET /owner-reports` on `http://192.168.100.106:5000/owner-reports`  
**Mobile:** build the same table from `GET /api/v1/owner-reports` → `data.ledgers[]`

### APIs you need

| # | When | Method | Endpoint |
|---|------|--------|----------|
| 1 | Login | `POST` | `/auth/login` |
| 2 | Main table (required) | `GET` | `/owner-reports` |
| 3 | Filter by date range | `GET` | `/owner-reports?start_date=&end_date=` |
| 4 | Filter by business type tab | `GET` | `/owner-reports?business_type=liquor` |
| 5 | Single day detail | `GET` | `/owner-reports/{date}` |
| 6 | Action — view handover | `GET` | `/day-closing/{id}` (use `review_api_path`) |
| 7 | Pending banner — review | `GET` | `/day-closing/review?date=&handover_id=` |
| 8 | Add expense (owner) | `POST` | `/owner-reports/{date}/expenses` |
| 9 | Delete expense (owner) | `DELETE` | `/owner-reports/{date}/expenses/{id}` |
| 10 | Finalize day (owner) | `POST` | `/owner-reports/{date}/finalize` |
| 11 | Switch branch (owner) | `POST` | `/auth/switch-branch` |

**Test account (local):** `sindato@gmail.com` / `123456`

```http
GET http://192.168.100.106:5000/api/v1/owner-reports?per_page=50
Authorization: Bearer {token}
Accept: application/json
```

---

### Table columns → API fields

Build each table row from one object in `data.ledgers[]`.

| # | Web column | API field | Display notes |
|---|------------|-----------|---------------|
| 1 | DATE | `ledger_date` | Format: `28 Jul, 2026` |
| 2 | BUSINESS | `business_type_label` | `null` → show `—` |
| 3 | STAFF | see below | Open day logic |
| 4 | STATUS | `business_status` | `OPEN`, `VERIFIED`, `CLOSED`, `DISPUTED`, `NOT STARTED` |
| 5 | OPENING CASH | `opening_cash` | `null` → `—` |
| 6 | CASH | `total_cash_received` | Open-day & value `0` → `—` |
| 7 | DIGITAL | `total_digital_received` | `0` → `—` |
| 8 | TOTAL (collections) | `sub_total` | Open-day & `0` → `—` |
| 9 | ASSETS | `total_assets` | Always show number |
| 10 | EXPENSES | `combined_expenses` | Verified: `(0)` or `(1,400)`. Open-day & `0` → `—` |
| 11 | DAILY PROFIT | `daily_net_profit` | Open-day & `0` → `—` |
| 12 | CIRCULATION REFILL | `circulation_refill` | `0` → `—` |
| 13 | SHORT → TO PROFIT | `staff_profit_recoveries` | `0` → `—`, else `+1,200` |
| 14 | SHORT → TO CIRCULATION | `staff_circulation_recoveries` | `0` → `—`, else `+5,150` |
| 15 | CIRCULATION ROLLOVER | `money_in_circulation` | See rollover rules |
| 16 | PROFIT ROLLOVER | `profit_rollover` | See rollover rules |
| 17 | ACTION | `review_api_path` | Eye icon → `GET /api/v1` + path |

**Currency:** TZS, no decimals on list. Example: `63,555,600`.

---

### STAFF column (compute in app)

```
if is_placeholder == true:
    if has_open_shift == true  → "Open day"
    else                       → "Awaiting shift"
else:
    show handover_label  (e.g. "SINDATO · Shift #17")
```

---

### ROLLOVER columns + badge

Show amount only when `show_rollover_columns == true` **OR** `is_placeholder == true`.  
If field is `null` → show `—`.

| Condition | Badge under amount |
|-----------|-------------------|
| `is_finalized == true` | **Finalized** ✓ |
| Otherwise | **AVAILABLE** |

---

### Row types (3 kinds)

**1. Open-day rows** (`is_placeholder: true`)  
- Appear at top of page 1 when no date filter.  
- BUSINESS = `—`, STAFF = Awaiting shift / Open day, STATUS = `OPEN`.  
- Collections show `—` when zero. Rollover always shown with **AVAILABLE**.  
- `review_api_path` is usually `null`.

**2. Verified handover rows** (`is_placeholder: false`, `business_status: VERIFIED`)  
- BUSINESS = `business_type_label` (e.g. `Liquor Store / Bar`).  
- EXPENSES always in brackets: `(0)` even when zero.  
- ACTION: `GET /api/v1{review_api_path}`.

**3. Multi-business split** (`is_business_type_row: true`)  
- Same date can have 2+ rows (e.g. Liquor + Other on 16 Jun).  
- Only the last type row of the day has rollover values (`show_rollover_columns: true`).  
- Other split rows show `—` for OPENING CASH and rollover columns.

---

### Screen layout (recommended)

```
┌─────────────────────────────────────┐
│ Master Sheet                        │
│ [From date] [To date] [Search]      │
├─────────────────────────────────────┤
│ ⚠ 12 handovers awaiting verify      │  ← data.pending_handovers[]
│   Tap → GET /day-closing/review...  │
├─────────────────────────────────────┤
│ [All] [Liquor] [Other]              │  ← data.business_types[] if multi_business
├─────────────────────────────────────┤
│ 28 Jul | Awaiting shift | OPEN      │  ← data.ledgers[] (card or table row)
│ Cash 341,500 | Total 377,000        │
│ Circulation 202,622,100 AVAILABLE   │
├─────────────────────────────────────┤
│ 23 Jun | SINDATO · Shift #17        │
│ VERIFIED | Total 16,053,100    [👁]  │  ← review_api_path
└─────────────────────────────────────┘
```

**Phone:** use cards with main columns; tap to expand full 17-column grid.  
**Tablet:** horizontal-scroll table matching web Excel layout.

---

### Pagination

- Use `meta.current_page`, `meta.last_page`, `meta.total`, `meta.per_page`.
- Open-day placeholder rows are **prepended on page 1 only** (when no `start_date` / `end_date`).
- `ledgers.length` can be greater than `meta.total` (multi-business expansion + open days).

---

### Expanded row detail (no extra API call)

When user taps a row, show from the same ledger object:

| Section | Field |
|---------|-------|
| Expenses list | `expense_list[]` |
| Collections by platform | `platform_breakdown[]` |
| By business type | `business_type_breakdown[]` |
| Gross sales / COGS | `gross_sales`, `cost_of_goods` |
| Outstanding debt | `outstanding_debt` (if &gt; 0) |
| Owner notes | `owner_notes` (after finalize) |

---

### Owner actions on day screen

Only show if user role is `owner` and `is_finalized == false`:

1. **Add expense** — `POST /owner-reports/{date}/expenses`  
   Use `meta.expense_categories` and `meta.fund_sources` for pickers.

2. **Finalize** — `POST /owner-reports/{date}/finalize`  
   Body: `{ "owner_notes": "optional" }`

Both return refreshed `day` object — rebind UI from response.

---

### Example: open-day row (28 Jul 2026)

API returns:

```json
{
  "id": "open-2026-07-28",
  "ledger_date": "2026-07-28",
  "is_placeholder": true,
  "has_open_shift": false,
  "business_type_label": null,
  "business_status": "OPEN",
  "opening_cash": 202245100,
  "total_cash_received": 341500,
  "total_digital_received": 35500,
  "sub_total": 377000,
  "total_assets": 202622100,
  "combined_expenses": 0,
  "daily_net_profit": 1400,
  "circulation_refill": 375600,
  "money_in_circulation": 202622100,
  "profit_rollover": 27748588,
  "is_finalized": false,
  "review_api_path": null
}
```

Renders as:

| DATE | BUSINESS | STAFF | STATUS | OPENING | CASH | DIG | TOTAL | ASSETS | EXP | PROFIT | CIRC REFILL | S→P | S→C | CIRC ROLLOVER | PROFIT ROLLOVER |
|------|----------|-------|--------|---------|------|-----|-------|--------|-----|--------|-------------|-----|-----|---------------|-----------------|
| 28 Jul | — | Awaiting shift | OPEN | 202,245,100 | 341,500 | 35,500 | 377,000 | 202,622,100 | — | 1,400 | 375,600 | — | — | 202,622,100 AVAILABLE | 27,748,588 AVAILABLE |

---

### Example: verified row (23 Jun 2026)

```json
{
  "ledger_date": "2026-06-23",
  "is_placeholder": false,
  "business_type_label": "Liquor Store / Bar",
  "handover_label": "SINDATO · Shift #17",
  "business_status": "VERIFIED",
  "opening_cash": 49269154,
  "total_cash_received": 16053100,
  "total_digital_received": 0,
  "sub_total": 16053100,
  "total_assets": 65322254,
  "combined_expenses": 0,
  "daily_net_profit": 1766654,
  "circulation_refill": 14286446,
  "money_in_circulation": 63555600,
  "profit_rollover": 6560300,
  "review_api_path": "/day-closing/42"
}
```

---

### Full ledger object fields (reference)

| Field | Type | Use |
|-------|------|-----|
| `id` | string | Row id (`"42"` or `"open-2026-07-28"` or `"42-liquor"`) |
| `handover_id` | int? | Day closing id |
| `detail_handover_id` | int? | Use for day-closing links |
| `report_id` | int? | Owner daily report id |
| `is_placeholder` | bool | Open/live day |
| `is_business_type_row` | bool | Multi-business split row |
| `show_rollover_columns` | bool | Show rollover amounts on this row |
| `is_last_handover_of_day` | bool | Last staff handover that day |
| `is_finalized` | bool | Day locked |
| `has_open_shift` | bool | Staff shift still open |
| `has_open_day_activity` | bool | Sales/expenses on open day |
| `handover_scope` | string? | `retail`, `service`, etc. |
| `status_color` | string? | Hex color from web |
| `day_review_api_path` | string? | Boss day review link |
| `expense_list` | array | `{ id?, description, amount, category, category_label, fund_source, deletable, source }` — owner rows include `id` + `deletable: true` |
| `platform_breakdown` | array | `{ key, label, amount }` |
| `business_type_breakdown` | array | Per-type sales split |

---

### Related docs

- Day closing / verify handover: [`API_DAY_CLOSING.md`](API_DAY_CLOSING.md)
- Mobile overview: [`API_MOBILE.md`](API_MOBILE.md) lines 776–790

