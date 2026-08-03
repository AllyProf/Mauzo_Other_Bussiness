# Mauzo Link Mobile API (v1)

REST API for the Flutter mobile app. Base URL:

| Environment | Base URL |
|-------------|----------|
| Production | `https://www.mauzolink.co.tz/api/v1` |
| Local (XAMPP) | `http://localhost/SpareParts/public/api/v1` or your virtual host |

All requests and responses are **JSON**. Currency is **TZS** (whole numbers).

---

## Business registration (public)

No authentication required. Same flow as web `/register-business`: fill form → SMS verification code → submit → pending admin approval.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/register-business` | Form options (regions, districts, business types) |
| POST | `/register-business/send-code` | Validate form and send 6-digit SMS code |
| POST | `/register-business` | Submit registration with verification code |

### Form options
`GET /register-business`

Returns `registration_open`, `platform_name`, `regions`, `locations` (region → districts map), `business_types`, and `phone_hint`.

### Send verification code
`POST /register-business/send-code`
```json
{
  "name": "John Doe",
  "phone": "0712345678",
  "email": "john@shop.com",
  "region": "Dar es Salaam",
  "district": "Kinondoni",
  "address": "Mikocheni, Plot 12",
  "business_type": "grocery"
}
```

- `phone`: 9 digits starting with 6, 7, or 8
- `email`: optional (login email if provided; otherwise phone-based email is created)
- `business_type`: key from `business_types` in options; use `"other"` with `custom_business_type`

**Response 200**
```json
{
  "success": true,
  "message": "Verification code sent.",
  "data": { "phone_display": "+255 712345678" }
}
```

### Complete registration
`POST /register-business`

Same body as send-code, plus:
```json
{
  "verification_code": "123456"
}
```

**Response 201**
```json
{
  "success": true,
  "message": "Thank you! Your registration was received and is pending review...",
  "data": {
    "pending_approval": true,
    "business": {
      "id": 42,
      "name": "John Doe - Grocery / Mini-Market",
      "contact_person": "John Doe",
      "phone": "0712345678",
      "email": "john@shop.com",
      "region": "Dar es Salaam",
      "district": "Kinondoni"
    }
  }
}
```

After approval, login credentials are sent by SMS. Account cannot log in until approved (`403 Business is pending approval` on login).

---

Uses **Laravel Sanctum** bearer tokens.

### Login
`POST /auth/login`

```json
{
  "email": "owner@shop.com",
  "password": "secret",
  "device_name": "Pixel 7"
}
```

**Response 200**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "1|abc...",
    "token_type": "Bearer",
    "user": {
      "id": 1,
      "name": "Shop Owner",
      "email": "owner@shop.com",
      "role": "owner",
      "permissions": ["process_sales", "..."],
      "needs_shift_opened": false,
      "open_shift_id": null,
      "business": { "id": 10, "name": "My Shop", "operation_mode": "retail" },
      "branch": { "id": null, "name": "All Branches", "viewing_all": true },
      "available_businesses": [],
      "available_branches": []
    }
  }
}
```

### Authenticated requests
Add header:
```
Authorization: Bearer {token}
Accept: application/json
```

### Logout
`POST /auth/logout`

### Current user
`GET /auth/me`

### Switch business (owner only)
`POST /auth/switch-business`
```json
{ "business_id": 12 }
```

### Switch branch (owner only)
`POST /auth/switch-branch`
```json
{ "branch_id": 3 }
```
Pass `branch_id: null` or omit to view **all branches**.

---

## Standard response format

**Success**
```json
{
  "success": true,
  "message": "OK",
  "data": { }
}
```

**Error**
```json
{
  "success": false,
  "message": "Human readable message",
  "errors": { "field": ["validation error"] }
}
```

**Special codes**
| HTTP | code | Meaning |
|------|------|---------|
| 401 | — | Invalid/missing token |
| 403 | `SUBSCRIPTION_EXPIRED` | Business subscription locked |
| 403 | `ACCOUNT_DEACTIVATED` | User inactive |
| 422 | — | Validation / business rule error |

---

## Dashboard

### Today stats
`GET /dashboard/today`

**Owner / manager** — business-wide (respects branch filter):
```json
{
  "date": "2026-07-27",
  "orders": 15,
  "gross_sales": 450000,
  "collected": 420000,
  "outstanding": 80000,
  "open_shifts": 2,
  "today_revenue": 420000
}
```

**Cashier** — current shift only if open.

---

## Reference data

### Payment methods
`GET /payment-methods`

```json
{
  "payment_methods": [
    {
      "key": "cash",
      "label": "Cash",
      "type": "immediate",
      "requires_reference": false,
      "providers": []
    },
    {
      "key": "mobile_money",
      "label": "Mobile Money",
      "type": "immediate",
      "requires_reference": true,
      "providers": ["M-Pesa", "Tigo Pesa", "Airtel Money"]
    },
    {
      "key": "debt",
      "label": "Pay Later (Credit)",
      "type": "credit",
      "requires_reference": false,
      "providers": []
    }
  ]
}
```

### Branches

**Full docs:** [`API_BRANCHES.md`](API_BRANCHES.md)

| Method | Endpoint | Permission | Description |
|--------|----------|------------|-------------|
| GET | `/branches` | any (full if `manage_branches`) | List branches |
| POST | `/branches` | `manage_branches` | Register branch |
| PUT | `/branches/{id}` | `manage_branches` | Update branch |
| DELETE | `/branches/{id}` | `manage_branches` | Delete branch |

Switch active branch: `POST /auth/switch-branch`

---

## Business settings

Same as web `/settings` — profile, finance, payments, automation, shift rules, subscription (read-only).

**Full docs:** [`API_SETTINGS.md`](API_SETTINGS.md)

| Method | Endpoint | Permission |
|--------|----------|------------|
| GET | `/settings` | `manage_business_settings` or `manage_payment_methods` |
| PUT | `/settings/profile` | `manage_business_settings` |
| PUT | `/settings/finance` | `manage_business_settings` |
| PUT | `/settings/automation` | `manage_business_settings` |
| PUT | `/settings/shift-rules` | `manage_business_settings` |
| PUT | `/settings/payment-methods` | `manage_payment_methods` or `manage_business_settings` |

---

## Shifts

Cashiers with `open_shift` / `process_sales` must open a shift before selling.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/shifts/current` | Open shift for user + can_open flag |
| GET | `/shifts/open-form` | Items list for physical stock count |
| POST | `/shifts/open` | Open shift with counts |
| GET | `/shifts` | Shift history (paginated) |
| GET | `/shifts/{id}` | Shift detail |
| POST | `/shifts/{id}/close` | Close shift only (no handover — use day-closing submit for full handover) |

### Open shift
`POST /shifts/open`
```json
{
  "opening_notes": "Morning count",
  "counts": {
    "12": 50,
    "15": 100
  },
  "notes": {
    "12": "2 pieces damaged"
  }
}
```
- `counts` keys = **item IDs**
- If physical count < system stock, `notes[item_id]` is **required**

---

## Items (POS catalog)

### Search
`GET /items/search?q=filter&limit=30`

Returns items with stock > 0 and packaging prices (includes `barcode` per packaging).

### Scan barcode (fast checkout)
`GET /items/lookup-barcode?code=ML001000000123`

Returns item + packaging + ready `cart_line`. Use after camera/hardware scan.

### Barcode labels
`GET /items/{id}/barcodes` — PNG base64 for print  
Web: `/items/{id}/barcodes/print`

### Detail
`GET /items/{id}`

---

## Items (inventory / create)

Register and manage products — same as web `/items` and `/items/create`.

**Full docs:** [`API_ITEMS.md`](API_ITEMS.md)

### What this feature does

An item is a sellable product: name, category, receiving unit, and selling packagings (Piece / Carton with qty-per-unit). Stock starts at 0; add stock via receivings later.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/items` | Inventory list |
| GET | `/items/create-form` | Categories + packagings for create screen |
| GET | `/items/check-name` | Duplicate name check |
| POST | `/items` | Create item |
| GET | `/items/{id}` | Detail |
| PUT | `/items/{id}` | Update |
| DELETE | `/items/{id}` | Delete |
| GET | `/items/search` | POS search (stock > 0) |
| GET | `/items/lookup-barcode` | POS scan by barcode |
| GET | `/items/{id}/barcodes` | Label PNGs for print |

### Create (register)
`POST /items`

**Required fields:** `name`, `receiving_packaging_id`, `units_per_receiving_pack`, `selling_packagings` (≥1 with `packaging_id` + `quantity_per_unit`).  
**If multi business type:** also `business_type_key`.  
**Optional:** `category_id`, `brand`, `description`, `cost_price`, `selling_price` per packaging.

```json
{
  "name": "Afya Maji Medium",
  "category_id": 226,
  "receiving_packaging_id": 41,
  "units_per_receiving_pack": 12,
  "selling_packagings": [
    { "packaging_id": 42, "quantity_per_unit": 1, "selling_price": 500 },
    { "packaging_id": 41, "quantity_per_unit": 12, "selling_price": 5000 }
  ]
}
```

### Edit
1. `GET /items/{id}` — prefill form  
2. `PUT /items/{id}` — **same required fields as create** (send full body, not partial)

Optional while typing: `GET /items/check-name?name=...&exclude_id={id}`

### Delete
`DELETE /items/{id}`

- **Required:** item `{id}` in the URL only  
- **Body:** none  

Use `GET /items/create-form` before register. Full field tables: [`API_ITEMS.md`](API_ITEMS.md).

---

## Stock on hand

Live inventory dashboard — same as web `/items/stock`.

**Full docs:** [`API_STOCK.md`](API_STOCK.md)

### What this feature does

Shows **only items with stock > 0** (categorized). Includes packaging breakdown, selling prices, low-stock alerts, and expected revenue/profit (owners). Tap history for receivings, sales, losses, and adjustments.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/items/stock` | Stock list + stats + filters |
| GET | `/items/{id}/history` | Movement timeline for one item |

### Stock list
`GET /items/stock`

**Query (optional):** `q`, `low_stock=1`, `business_type_key`, `category_slug`, `category_id`

**Permission:** `view_stock_history` or `view_inventory`

Returns `items[]`, `stats` (total_items, low_stock), `totals` (expected_revenue/profit — owners only), and `meta` (category_filters, business_types, low_stock_threshold).

### Item history
`GET /items/{id}/history`

Returns `item`, `movements[]` (stock_in, sale, stock_loss, stock_adjustment), and `stats` (total_received, total_sold, current_stock).

**Difference from `GET /items`:** stock endpoint excludes zero-stock and uncategorized items; includes valuation and low-stock flags.

---

## Receivings (stock-in)

Record incoming stock from suppliers — same as web `/receivings/create` and `/receivings`.

**Full docs:** [`API_RECEIVINGS.md`](API_RECEIVINGS.md)

### What this feature does

A **receiving** is a stock-in document: you pick a supplier, date, and one or more items with quantities and buying prices. On submit:

- Item **stock increases** (pieces added to `current_stock`)
- Item packaging **cost** and **selling** prices are updated
- A reference number is generated (`RCV-YYYYMMDD-XXXX`)
- Staff may get an SMS notification

There is **no edit** after create — use **cancel** to reverse stock (full or partial if some was already sold).

**Typical mobile flow (matches `/receivings/create`):**

1. `GET /receivings/create-form` — suppliers, categories, items by category, branches  
2. User selects supplier, date, line items (qty, cost, retail prices)  
3. `POST /receivings` — submit  
4. `GET /receivings` / `GET /receivings/{id}` — history & detail  
5. Cancel: `GET /receivings/{id}/cancel-preview` → `POST /receivings/{id}/cancel`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/receivings` | List (`?period=`, `?status=`, `?business_type=`) |
| GET | `/receivings/create-form` | Form data for create screen |
| POST | `/receivings` | Create stock-in |
| GET | `/receivings/{id}` | Detail with line totals & profit |
| GET | `/receivings/{id}/cancel-preview` | Preview stock reversal before cancel |
| POST | `/receivings/{id}/cancel` | Cancel receiving |

**Permission:** `receive_stock` (list/create/show). Cancel also allows `cancel_receiving`.

### Branch rules

- **Staff:** locked to their branch; `branch_id` on create is ignored.
- **Owner:** list filtered by `POST /auth/switch-branch`; all-branches view shows all.
- **Multi-branch owners:** pass `branch_id` on create when not on a specific branch.

---

### Create form

`GET /receivings/create-form`

Call this before building the register screen. Returns:

| Field | Use on mobile |
|-------|----------------|
| `suppliers` | Supplier dropdown |
| `categories` | Category tabs / filters |
| `items_by_category` | Item picker keyed by category ID |
| `items_by_category[].packagings` | Retail price modal (`selling_prices` keys) |
| `items_by_category[].cost_price` | Default buying price per pack |
| `items_by_category[].units_per_receiving_pack` | Pcs per receiving unit |
| `items_by_category[].current_stock` / `remains_display` | Stock hint |
| `branches`, `default_branch_id` | Branch picker (owners) |
| `defaults.received_date` | Pre-fill date (today) |
| `defaults.qty_mode`, `defaults.cost_mode` | Default toggles (`pkg`) |

`packagings[].id` = **`item_packagings.id`** — use these IDs as keys in `selling_prices` on submit.

---

### Create (register)

`POST /receivings`

#### Header fields

| Field | Required | Notes |
|-------|----------|-------|
| `supplier_id` | **Yes** | From create-form `suppliers[].id` |
| `received_date` | **Yes** | `YYYY-MM-DD` |
| `items` | **Yes** | Array, ≥1 line with `qty` > 0 |
| `branch_id` | No | Owners, multi-branch only |
| `notes` | No | Free text |

#### Line item fields (`items[]`)

| Field | Required | Default | Notes |
|-------|----------|---------|-------|
| `id` | **Yes** | — | Item ID |
| `qty` | **Yes** | — | Integer ≥ 1 |
| `cost` | **Yes** | — | Buying price ≥ 0 (see `cost_mode`) |
| `qty_mode` | No | `pkg` | `pkg` = receiving packs (cartons); `piece` = individual pcs |
| `cost_mode` | No | `pkg` | `pkg` = cost per pack; `unit` = cost per piece |
| `selling` | No | — | Single retail price (simple / one packaging) |
| `selling_prices` | No | — | Object: `{ "item_packaging_id": price }` for multi-packaging items |
| `discount_type` | No | — | `fixed` or `percent` |
| `discount_value` | No | `0` | Discount on line gross cost |

#### qty_mode & cost_mode (web toggles)

| `qty_mode` | `qty` means | Stock added |
|------------|-------------|-------------|
| `pkg` | Number of **receiving packs** (e.g. cartons) | `qty × units_per_receiving_pack` |
| `piece` | Individual pieces | `qty` |

| `cost_mode` | `cost` means |
|-------------|--------------|
| `pkg` | Price per receiving pack |
| `unit` | Price per piece |

**Validation (same as web):** retail/selling price cannot be lower than net buying cost. Lines with `qty` 0 are skipped.

#### Example — single packaging

```json
{
  "supplier_id": 10,
  "received_date": "2026-07-28",
  "notes": "Morning delivery",
  "items": [
    {
      "id": 30,
      "qty": 10,
      "qty_mode": "pkg",
      "cost": 4500,
      "cost_mode": "pkg",
      "selling": 5000,
      "discount_type": "percent",
      "discount_value": 5
    }
  ]
}
```

#### Example — multi-packaging retail prices

```json
{
  "supplier_id": 10,
  "received_date": "2026-07-28",
  "items": [
    {
      "id": 55,
      "qty": 5,
      "cost": 4500,
      "selling_prices": {
        "101": 500,
        "102": 5000
      }
    }
  ]
}
```

**Response 201:** `data.receiving` with `reference_no`, `lines`, `totals` (net cost, expected revenue, profit).

Use `GET /receivings/create-form` before register. Register suppliers first via [`/suppliers`](API_SUPPLIERS.md) if the list is empty.

---

### List

`GET /receivings`

| Query | Values |
|-------|--------|
| `period` | `all`, `today`, `weekly`, `monthly`, `yearly`, `custom` |
| `start_date`, `end_date` | With `period=custom` |
| `status` | `completed`, `cancelled` |
| `business_type` | Category business-type key |
| `per_page` | 1–50 (default 20) |

Returns `receivings[]`, `stats`, and pagination `meta`.

---

### Detail

`GET /receivings/{id}`

Returns header (`reference_no`, supplier, branch, status), `lines[]` (quantity labels, costs, packaging prices), and `totals`.

---

### Cancel

No edit endpoint. To undo a receiving:

1. `GET /receivings/{id}/cancel-preview` — shows `reversible` vs `not_reversible` stock per item  
2. `POST /receivings/{id}/cancel`

| Field | Required | Notes |
|-------|----------|-------|
| `partial_ok` | No | Set `true` if preview shows some stock was already sold |

If partial cancel is needed and `partial_ok` is not `true`, API returns **409** with preview data in `data`.

```json
{ "partial_ok": true }
```

**Body:** only `partial_ok` when needed; no other fields.

---

## Sales (POS)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/sales` | List sales (`?shift_id=`, `?date=`, `?payment_status=`, `?branch_id=`) |
| POST | `/sales` | Create order (pending payment) |
| GET | `/sales/{id}` | Receipt detail |
| POST | `/sales/{id}/pay` | Collect payment |
| POST | `/sales/{id}/cancel` | Cancel unpaid sale |

### Sales history branch filter (same as web `/sales`)

| Who | Behavior |
|-----|----------|
| **Staff** | Own sales only |
| **Owner** | Active branch from `POST /auth/switch-branch`, or pass `?branch_id=10`. Use `branch_id=0` / omit with no switch = **all branches** |

Filter matches web: sales whose **products/services belong to that branch** (item category / service `branch_id`). Response includes `meta.branch_id` and `meta.viewing_all_branches`.

### Create sale
`POST /sales`
```json
{
  "sale_date": "2026-07-27",
  "items": [
    {
      "id": 12,
      "item_packaging_id": 5,
      "qty": 2,
      "price": 15000
    }
  ],
  "customer_id": null,
  "customer_name": "Walk-in",
  "customer_phone": null,
  "notes": null
}
```

### Pay sale
`POST /sales/{id}/pay`

**Cash / mobile money / bank**
```json
{
  "payment_method": "cash",
  "amount_paid": 30000
}
```

**Mobile money (reference required)**
```json
{
  "payment_method": "mobile_money",
  "amount_paid": 30000,
  "payment_provider": "M-Pesa",
  "transaction_reference": "ABC123XYZ"
}
```

**Credit (pay later)**
```json
{
  "payment_method": "debt",
  "customer_name": "John Doe",
  "customer_phone": "0712345678",
  "due_date": "2026-08-27",
  "amount_paid": 0
}
```

**Partial payment** — include `customer_name`, `customer_phone`, `due_date`.

---

## Debts

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/debts` | Outstanding accounts (`?search=`, `?filter=overdue`) |
| POST | `/debts/{sale_id}/collect` | Record collection (same body as `/sales/{id}/pay`) |

---

## Customers

Register and manage buyers — same as web `/customers`.

**Full docs:** [`API_CUSTOMERS.md`](API_CUSTOMERS.md)

### What this feature does

Customers are people you sell to (especially on credit). Register name + phone; optional email, address, region, notes. Inactive customers stay in the list but are hidden from POS search.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/customers` | List (`?q=`, `?status=active\|inactive`) |
| GET | `/customers/create-form` | Regions + phone hint for register screen |
| GET | `/customers/search?q=` | Active customers for POS picker |
| POST | `/customers` | Register customer |
| GET | `/customers/{id}` | Detail + recent sales |
| PUT | `/customers/{id}` | Update |
| DELETE | `/customers/{id}` | Delete (only if no sales) |

### Create (register)
`POST /customers`

**Required:** `name`, `phone` (9 digits e.g. `712345678`).  
**Optional:** `email`, `address`, `region`, `notes`, `is_active` (default true).

```json
{
  "name": "John Mwangi",
  "phone": "712345678",
  "email": "john@email.com",
  "address": "Mikocheni",
  "region": "Dar es Salaam",
  "notes": null,
  "is_active": true
}
```

Use `GET /customers/create-form` first for regions. Duplicate phone returns 422.

### Edit
1. `GET /customers/{id}` — prefill  
2. `PUT /customers/{id}` — same fields as create

### Delete
`DELETE /customers/{id}` — fails if customer has sales; set `is_active: false` instead.

**Permission:** `manage_customers` (search also allows POS/debt permissions).

---

## Customer communications

Send SMS/email to customers — same as web `/customer-communications`.

**Full docs:** [`API_CUSTOMER_COMMUNICATIONS.md`](API_CUSTOMER_COMMUNICATIONS.md)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/customer-communications` | Quota, customers, logs, scheduled campaigns |
| POST | `/customer-communications/send` | Send now or schedule |
| DELETE | `/customer-communications/campaigns/{id}` | Cancel scheduled message |

**Permission:** `manage_customer_communications` or `manage_customers`. Requires `customer_communication` plan feature.

---

## Sales targets

Set daily / weekly / monthly revenue goals by branch, department, or staff — same as web `/sales-targets`.

**Full docs:** [`API_SALES_TARGETS.md`](API_SALES_TARGETS.md)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/sales-targets` | List + form options (`?branch_id=`, `?business_type=`, `?page=`) |
| GET | `/sales-targets/{id}` | Detail with live progress |
| POST | `/sales-targets` | Create / upsert |
| PUT | `/sales-targets/{id}` | Update |
| DELETE | `/sales-targets/{id}` | Remove |

**Permission:** `manage_sales_targets` or `manage_business_settings`. Requires `sales_targets` plan feature.

---

## In-app notifications

Poll-based inbox + banners — **no Firebase**. Backend stores rows; app polls while logged in.

**Full docs:** [`API_NOTIFICATIONS.md`](API_NOTIFICATIONS.md)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/devices` | Register device after login |
| DELETE | `/devices/{token}` | Unregister on logout |
| GET | `/notifications` | Inbox / poll (`?unread_only=true&limit=30`) |
| PATCH | `/notifications/{id}/read` | Mark one read |
| POST | `/notifications/read-all` | Mark all read |
| GET | `/notifications/preferences` | Category toggles |
| PUT | `/notifications/preferences` | Update toggles |

**MVP events wired:** day-closing handover submit/verify/dispute, payment received, low/out of stock after sales.

---

## Day closing / handover

End-of-shift cash count, expenses, reconciliation, and owner verification — same as web `/day-closing`.

**Full docs:** [`API_DAY_CLOSING.md`](API_DAY_CLOSING.md)

### What this feature does

Staff submit a **handover** (declared cash / mobile / bank + expenses). Owner verifies or disputes the oldest pending handover first. Submitting closes an open shift and sends SMS/email (same as web).

| Method | Endpoint | Who | Description |
|--------|----------|-----|-------------|
| GET | `/day-closing/preview` | Staff | Form data (`?shift_id=`, `?closing_date=`) |
| POST | `/day-closing` | Staff | Submit handover |
| GET | `/day-closing` | All | History (`?date=`, `?status=`, `?per_page=`) |
| GET | `/day-closing/review` | Owner | Boss day review (`?date=` + optional `handover_id`) — same as web `/day-closing?date=…#handover-…` |
| GET | `/day-closing/owner-direct` | Owner | Preview owner's own POS sales for date |
| POST | `/day-closing/owner-direct` | Owner | One-step Verify & Close owner POS sales |
| GET | `/day-closing/pending` | Owner | Pending / disputed queue |
| GET | `/day-closing/{id}` | All | Full handover detail |
| POST | `/day-closing/{id}/verify` | Owner | Approve or dispute |

### Boss day review (web `/day-closing?date=2026-06-18#handover-15`)

`GET /day-closing/review?date=2026-06-18&handover_id=15`

Returns staff handover cards, pending queue, awaiting shifts, **`owner_direct`** (Verify & Close card), and `next_to_verify`.

Or open one card directly: `GET /day-closing/15`

### Staff flow
1. `GET /day-closing/preview?shift_id=123` — expected totals + `platform_breakdown`  
2. Adjust amounts / add expenses  
3. `POST /day-closing`

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
    { "description": "Transport", "amount": 5000, "payment_method": "cash" }
  ]
}
```

- `platform_amounts` keys = `platform_breakdown[].key` from preview (optional — defaults to system totals)  
- Expenses reduce the matching payment method  
- **Owners cannot submit** a staff handover here

### Owner verify staff handovers
`GET /day-closing/pending` → `POST /day-closing/{id}/verify`

**Approve:**
```json
{
  "actual_received": 225000,
  "shortage_note": "Missing 2000 from drawer"
}
```

**Dispute:** add `"dispute_reason": "…"`.  
`shortage_note` required when actual &lt; expected. Oldest pending must be verified first.

### Owner sold on POS (one-step Verify & Close)

Owner sales do **not** show as a staff handover. On web: one button **Verify & Close**.

1. `GET /day-closing/review?date=today` → check `owner_direct.can_post`  
   (or `GET /day-closing/owner-direct?date=today`)
2. If `can_post: true` → show Verify & Close card with `expected_handover`
3. `POST /day-closing/owner-direct` → creates `verified` closing and syncs Master Sheet (draft)

```json
{
  "closing_date": "2026-07-31",
  "actual_received": 150000,
  "report_notes": "Owner counter sales"
}
```

Does **not** auto-finalize. Finalize later on Master Sheet / owner-reports.
---

## Owner reports / Master Sheet

Owner daily financial ledger — same as web `/owner-reports`.

**Full docs:** [`API_OWNER_REPORTS.md`](API_OWNER_REPORTS.md)

| Method | Endpoint | Who | Description |
|--------|----------|-----|-------------|
| GET | `/owner-reports` | Owner / `view_reports` | Master Sheet list (`?start_date=`, `?end_date=`, `?business_type=`) |
| GET | `/owner-reports/{date}` | Owner / `view_reports` | Single-day ledger rows |
| POST | `/owner-reports/{date}/expenses` | Owner | Add owner expense |
| DELETE | `/owner-reports/{date}/expenses/{id}` | Owner | Remove owner expense |
| POST | `/owner-reports/{date}/finalize` | Owner | Finalize day (carry circulation forward) |

Each ledger row includes `review_api_path` and `day_review_api_path` linking to day-closing APIs for handover detail.

---

## Staff (roles & employees)

Same as web `/roles`, `/roles/create`, `/employees`, and `/employees/create`.

**Full docs:** [`API_STAFF.md`](API_STAFF.md)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/roles` | List roles |
| GET | `/roles/create-form` | Permission groups + presets |
| POST | `/roles` | Create role |
| GET | `/employees` | Staff list (`?q=` search) |
| GET | `/employees/create-form` | Branches, roles, business types (`?branch_id=`) |
| POST | `/employees` | Register employee |

**Permission:** `manage_staff`  
**Prerequisite:** create at least one role; branch must have imported business types (Categories).

---

## Petty Cash

Issue cash from circulation or profit for restock, payments, salaries, or operations.

**Full docs:** [`API_PETTY_CASH.md`](API_PETTY_CASH.md)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/petty-cash` | Index: balances, expenses history, categories, staff |
| GET | `/petty-cash/balances` | Refresh balances for a date (`?date=&business_type=`) |
| POST | `/petty-cash` | Issue petty cash |
| DELETE | `/petty-cash/{id}` | Remove petty cash entry |

**Permission:** `manage_petty_cash` (issue/delete) or `view_reports` (read-only)

---

## Live Sales Pulse

Real-time sales monitor — same as web `/live-sales`. KPIs, hourly velocity, live feed, staff leaderboard, trending products/services.

**Full docs:** [`API_LIVE_SALES.md`](API_LIVE_SALES.md)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/live-sales` | Full pulse snapshot (`?business_type=&branch_id=`) |

**Permission:** `view_live_sales` / `view_reports` / `view_sales_history` / `process_sales`  
**Tip:** Poll every 5–10s while the screen is open.

---

## Invoices

Product invoices — same as web `/invoices` and `/invoices/create`. Creates pending `sale_source: invoice` documents; pay later via `POST /sales/{id}/pay`.

**Full docs:** [`API_INVOICES.md`](API_INVOICES.md)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/invoices` | List invoices + stats |
| GET | `/invoices/create-form` | Catalog, customers, business types, shift |
| POST | `/invoices` | Create invoice |
| GET | `/invoices/{id}` | Invoice detail + payment methods |

**Permission:** `create_invoices` / `view_invoices` (or `process_sales` / `view_sales_history`)  
**Prerequisite:** open shift when required

---

## Business reports

Same as web `/reports/*` (Circulation vs Profit, Daily Sales, Expenses, Profit, Sales Analytics, Products, Debts).

**Full docs:** [`API_REPORTS.md`](API_REPORTS.md)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/reports` | Catalog of reports + date defaults |
| GET | `/reports/circulation-profit` | Circulation vs profit chart + daily rows |
| GET | `/reports/daily-sales` | Daily gross / collected / orders |
| GET | `/reports/expenses` | Staff + owner expenses |
| GET | `/reports/profit` | Gross / net profit by day |
| GET | `/reports/sales-analytics` | By method, staff, source |
| GET | `/reports/products` | Product & category performance |
| GET | `/reports/debts` | Aging, debtors, outstanding |

**Query:** `?start_date=&end_date=&business_type=&branch_id=` (range 5–62 days)  
**Permission:** `view_reports`

---

## Categories

Same operations as web `/categories`: list, create, rename, delete, import business-type templates, and clear all.

**Full docs:** [`API_CATEGORIES.md`](API_CATEGORIES.md)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/categories` | List categories + templates / import meta |
| POST | `/categories` | Create a category |
| PUT | `/categories/{id}` | Rename category |
| DELETE | `/categories/{id}` | Delete category |
| POST | `/categories/import-templates` | Import template or custom categories |
| DELETE | `/categories/clear-all` | Clear all categories (branch-scoped if branch selected) |

See [`API_CATEGORIES.md`](API_CATEGORIES.md) for request/response examples, permissions, and Flutter flow.

---

## Packagings

**Full docs:** [`API_PACKAGINGS.md`](API_PACKAGINGS.md)

Sell/stock units (Piece, Carton, Bottle…) — same as web `/packagings`.

### What this feature does

Packagings are the units you sell and count stock in. Example: soft drinks as **Piece (Pcs)** or **Carton**; pharmacy as **Tablet** / **Strip**. When you create an item later, you attach these units with prices and how many pieces each packaging holds (e.g. 1 Carton = 12 Pcs).

**Setup order:** Categories (business type) → Packagings (units for that type) → Items.

Units are stored per business; with a branch selected, the list is filtered to types imported on that branch.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/packagings` | List units + templates / tabs |
| POST | `/packagings` | Create packaging unit |
| PUT | `/packagings/{id}` | Rename |
| DELETE | `/packagings/{id}` | Delete |
| POST | `/packagings/import-templates` | Import units for a business type |
| DELETE | `/packagings/clear-all` | Clear units (branch-type scoped) |

### Import (after categories)
`POST /packagings/import-templates`
```json
{ "business_type_key": "liquor" }
```
Use `"all"` to import units for every business type on the active branch.

### Create
`POST /packagings`
```json
{
  "name": "Half Carton",
  "source_business_type_key": "liquor"
}
```

### Update / delete
`PUT /packagings/{id}` → `{ "name": "Carton (12)" }`  
`DELETE /packagings/{id}`  
`DELETE /packagings/clear-all`

**Permissions:** `manage_packaging`, or `view_inventory` (list), `add_items` (create/import), `edit_items` (rename), `delete_items` (delete/clear).

See [`API_PACKAGINGS.md`](API_PACKAGINGS.md) for full responses, branch rules, and curl examples.

---

## Suppliers

Supplier contacts for purchases/receivings — same as web `/suppliers` and `/suppliers/create`.

**Full docs:** [`API_SUPPLIERS.md`](API_SUPPLIERS.md)

### What this feature does

Register vendors you buy stock from (name, phone, email, region), each tied to a branch. Create-form returns branches + regions for the mobile register screen. Owners can copy/move suppliers between branches.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/suppliers` | List (`?q=` search) |
| GET | `/suppliers/create-form` | Form options for create screen |
| POST | `/suppliers` | Register supplier |
| GET | `/suppliers/{id}` | Detail |
| PUT | `/suppliers/{id}` | Update |
| DELETE | `/suppliers/{id}` | Delete |
| GET | `/suppliers/branch/{branchId}/list` | List for one branch |
| POST | `/suppliers/migrate-from-branch` | Copy or move between branches |

### Create
`POST /suppliers`
```json
{
  "name": "Arusha Auto Parts",
  "phone": "712345678",
  "email": "info@supplier.com",
  "region": "Arusha",
  "branch_id": 14
}
```

Phone: 9 digits (stored as `+255…`). Use `GET /suppliers/create-form` first for branches/regions.

**Permission:** `manage_suppliers`

---

## Permissions (UI gating)

Use `user.permissions` from login/me response. Server enforces the same rules as the web app.

| Permission | Mobile feature |
|------------|----------------|
| `open_shift` | Open/close shift |
| `process_sales` | POS, create sales |
| `submit_day_closing` | Submit day handover |
| `verify_day_closing` | View all handovers (manager) |
| `view_closing_history` | Handover history list |
| `collect_payments` | Pay on sales, collect debts |
| `view_sales_history` | Sales list |
| `manage_debts` | Debts screen |
| `manage_customers` | Customers list / register / edit / delete |
| `manage_customer_communications` | Customer SMS/email campaigns |
| `manage_sales_targets` | Sales targets (daily / weekly / monthly) |
| `manage_categories` | Categories CRUD / import / clear |
| `manage_packaging` | Packagings CRUD / import / clear |
| `manage_suppliers` | Suppliers CRUD / migrate |
| `view_inventory` | View categories, packagings & stock |
| `view_stock_history` | Stock on hand + item movement history |
| `add_items` | Create category/packaging / import templates |
| `edit_items` | Rename category / packaging |
| `delete_items` | Delete category/packaging / clear all |
| `receive_stock` | Receivings list / create / show |
| `cancel_receiving` | Cancel a receiving |
| `view_reports` | Owner dashboard stats |
| `cancel_sales` | Cancel unpaid orders |

Owners receive **all** permissions automatically.

---

## Recommended Flutter flow

```
1. Login → store token securely
2. GET /auth/me → build navigation from permissions
3. If needs_shift_opened → GET /shifts/open-form → POST /shifts/open
4. GET /items/search **or** GET /items/lookup-barcode → cart → POST /sales → POST /sales/{id}/pay
5. Stock-in: GET /receivings/create-form → POST /receivings
6. End of shift: GET /day-closing/preview → POST /day-closing
7. Owner: GET /day-closing/pending → POST /day-closing/{id}/verify
8. Owner: GET /dashboard/today, GET /debts
```

---

## Tech notes for developer

- **Stateless auth:** Token in `flutter_secure_storage`
- **HTTP client:** Dio with `Authorization` interceptor
- **401 handling:** Clear token → login screen
- **Pagination:** `meta.current_page`, `meta.last_page`, `meta.total`
- **Dates:** `YYYY-MM-DD` for sale_date, due_date
- **Branch context:** Owners call switch endpoints; staff are fixed to their branch
- **Phase 2 (not in API yet):** service POS, push notifications

---

## Local setup (backend team)

```bash
composer install
php artisan migrate
php artisan config:clear
```

Sanctum migration: `personal_access_tokens` table (included).

Test login:
```bash
curl -X POST http://localhost/SpareParts/public/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"you@shop.com","password":"secret","device_name":"curl"}'
```

---

## Endpoint summary (78 routes)

```
GET    /register-business
POST   /register-business/send-code
POST   /register-business
POST   /auth/login
POST   /auth/logout
GET    /auth/me
POST   /auth/switch-business
POST   /auth/switch-branch
POST   /devices
DELETE /devices/{token}
GET    /notifications
PATCH  /notifications/{id}/read
POST   /notifications/read-all
GET    /notifications/preferences
PUT    /notifications/preferences
GET    /dashboard/today
GET    /payment-methods
GET    /branches
POST   /branches
PUT    /branches/{id}
DELETE /branches/{id}
GET    /settings
PUT    /settings/profile
PUT    /settings/finance
PUT    /settings/automation
PUT    /settings/shift-rules
PUT    /settings/payment-methods
GET    /categories
POST   /categories
POST   /categories/import-templates
DELETE /categories/clear-all
PUT    /categories/{id}
DELETE /categories/{id}
GET    /packagings
POST   /packagings
POST   /packagings/import-templates
DELETE /packagings/clear-all
PUT    /packagings/{id}
DELETE /packagings/{id}
GET    /suppliers
GET    /suppliers/create-form
POST   /suppliers
POST   /suppliers/migrate-from-branch
GET    /suppliers/branch/{branchId}/list
GET    /suppliers/{id}
PUT    /suppliers/{id}
DELETE /suppliers/{id}
GET    /shifts
GET    /shifts/current
GET    /shifts/open-form
POST   /shifts/open
GET    /shifts/{shift}
POST   /shifts/{shift}/close
GET    /items
GET    /items/stock
GET    /items/create-form
GET    /items/check-name
GET    /items/search
GET    /items/lookup-barcode
POST   /items
GET    /items/{id}/barcodes
GET    /items/{id}/history
GET    /items/{item}
PUT    /items/{item}
DELETE /items/{item}
GET    /receivings
GET    /receivings/create-form
POST   /receivings
GET    /receivings/{id}
GET    /receivings/{id}/cancel-preview
POST   /receivings/{id}/cancel
GET    /sales
POST   /sales
GET    /sales/{sale}
POST   /sales/{sale}/pay
POST   /sales/{sale}/cancel
GET    /debts
POST   /debts/{sale}/collect
GET    /day-closing/preview
GET    /day-closing/pending
GET    /day-closing/review
GET    /day-closing/owner-direct
POST   /day-closing/owner-direct
GET    /day-closing
POST   /day-closing
GET    /day-closing/{id}
POST   /day-closing/{id}/verify
GET    /owner-reports
GET    /owner-reports/{date}
POST   /owner-reports/{date}/expenses
DELETE /owner-reports/{date}/expenses/{id}
POST   /owner-reports/{date}/finalize
GET    /customers
GET    /customers/create-form
GET    /customers/search
POST   /customers
GET    /customers/{id}
PUT    /customers/{id}
DELETE /customers/{id}
GET    /customer-communications
POST   /customer-communications/send
DELETE /customer-communications/campaigns/{id}
GET    /sales-targets
POST   /sales-targets
GET    /sales-targets/{id}
PUT    /sales-targets/{id}
DELETE /sales-targets/{id}
GET    /roles
GET    /roles/create-form
POST   /roles
GET    /employees
GET    /employees/create-form
POST   /employees
PUT    /employees/{id}
POST   /employees/{id}/reset-password
POST   /employees/{id}/toggle-status
DELETE /employees/{id}
GET    /petty-cash
GET    /petty-cash/balances
POST   /petty-cash
DELETE /petty-cash/{id}
GET    /live-sales
GET    /invoices
GET    /invoices/create-form
POST   /invoices
GET    /invoices/{id}
GET    /reports
GET    /reports/circulation-profit
GET    /reports/daily-sales
GET    /reports/expenses
GET    /reports/profit
GET    /reports/sales-analytics
GET    /reports/products
GET    /reports/debts
```
