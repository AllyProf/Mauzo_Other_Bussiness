# Cashier (Payments Only): Mobile API Guide

Everything the mobile app needs to build the **cashier** experience. A counter cashier collects money on orders that **sales officers** create on POS. He never sells, never changes prices and never counts stock.

| | |
|---|---|
| Base URL (production) | `https://www.mauzolink.co.tz/api/v1` |
| Auth | `Authorization: Bearer {token}` (Laravel Sanctum) |
| Headers | `Accept: application/json` |
| Envelope | `{ "success": true, "message": "...", "data": { ... } }` |
| Errors | `{ "success": false, "message": "...", "errors": { "code": "SOME_CODE", ... } }` |
| Web pages it mirrors | `/cashier/queue`, `/shifts/open` (cashier version), `/debts`, `/day-closing?shift=…` |

---

## Endpoint index

### Cashier

| # | Method | Endpoint | Purpose |
|---|--------|----------|---------|
| 1 | `POST` | `/auth/login` | Log in, get token and user flags |
| 2 | `GET` | `/auth/me` | Refresh user flags (cashier, shift, collection mode) |
| 3 | `GET` | `/shifts/current` | Is my shift open? |
| 4 | `POST` | `/shifts/open` | Open cashier shift (no stock check) |
| 5 | `GET` | `/cashier/queue` | Payment queue: Unpaid / Paid tabs, live search, polling |
| 6 | `POST` | `/cashier/orders/{id}/lock` | Hold an order while the pay sheet is open (+ heartbeat) |
| 7 | `DELETE` | `/cashier/orders/{id}/lock` | Release the order when the pay sheet closes without paying |
| 8 | `GET` | `/sales/{id}` | Full order detail (optional) |
| 9 | `POST` | `/sales/{id}/pay` | Collect payment / put on credit |
| 10 | `GET` | `/cashier/orders/{id}/receipt` | Receipt text + print / WhatsApp / SMS links |
| 11 | `GET` | `/cashier/collections` | What I collected this shift |
| 12 | `GET` | `/debts` · `/debts/{id}` | Outstanding debts in my branch |
| 13 | `POST` | `/debts/{id}/collect` | Collect on a debt |
| 14 | `GET` | `/day-closing/preview?shift={id}` | My handover (money to hand over) |
| 15 | `POST` | `/day-closing` | Submit handover and close shift |

### Owner / manager

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET` | `/cashier/on-duty` | Live view: cashiers on duty, what they collected, which order they are on |
| `GET` | `/cashier/performance` | Cashier performance report |
| `GET` | `/cashier/queue` | Same queue, all branches or `?branch_id=` |

---

## App flow (screens)

```
Login ──► needs_shift_opened? ──yes──► Open Shift screen (notes only) ──┐
              │ no                                                       │
              ▼                                                          ▼
        Payment Queue  ◄────────────────────────────────────────────────┘
        [Unpaid | Paid] tabs · search box · auto-refresh 10 s · new-order alert
              │ tap Pay on an order
              ▼
        lock order ──409──► toast "JUMA is collecting…" → refresh queue
              │ 200
              ▼
        Pay Sheet (cash / mobile money / bank / pay later) ──close──► unlock
              │ submit POST /sales/{id}/pay
              ▼
        Receipt sheet: Print · WhatsApp · SMS · Copy ──► "Next customer" → queue

Menu: Payment Queue · My Collections · Debts · My Handover · Logout
```

Hide POS, stock, receiving, items and price editing for cashiers.

---

## 1. Login and user flags

`POST /auth/login`

```json
{ "email": "cashier@shop.com", "password": "secret", "device_name": "Galaxy A14" }
```

`data.user` contains (cashier example):

```json
{
  "id": 57,
  "name": "SINDATO CASHIER",
  "role": "staff",
  "permissions": ["open_shift", "collect_payments", "collect_invoice_payments", "view_invoices", "view_price_list", "submit_day_closing", "manage_support", "manage_notes"],
  "is_payment_cashier": true,
  "home_screen": "cashier.queue",
  "needs_shift_opened": true,
  "open_shift_id": null,
  "payment_collection_mode": "both",
  "can_collect_payments": true,
  "business": { "id": 10, "name": "SINDATO STORE" },
  "branch": { "id": 3, "name": "Main Branch" }
}
```

| Flag | App action |
|------|------------|
| `is_payment_cashier: true` | Use the cashier menu and start on `home_screen` = `cashier.queue` |
| `needs_shift_opened: true` | Go to the Open Shift screen first |
| `can_collect_payments: false` | Hide every Pay / Collect button (see the owner setting below) |
| `payment_collection_mode` | `both`, `cashier` or `officer` |

Call `GET /auth/me` after opening or closing a shift, and when the app resumes.

### Who collects payments (owner setting)

The owner sets this on the web under **Settings → Payments → Who Collects Payments?**.

| Mode | Sales officer | Cashier | Owner / manager |
|------|---------------|---------|-----------------|
| `both` (default) | Can collect | Can collect | Can collect |
| `cashier` | **Cannot collect.** Creates orders only; they go to the cashier queue | Can collect | Can collect |
| `officer` | Can collect | **Cannot collect** (queue is view-only) | Can collect |

- In `cashier` mode, an officer's `POST /sales` returns `can_collect_payment: false` and `next: "cashier"`. Show "Send the customer to the cashier" instead of the pay sheet.
- Blocked users get **403 `PAYMENT_COLLECTION_DISABLED`** from pay, debt collect and lock.

---

## 2. Open cashier shift

`GET /shifts/current`

```json
{ "success": true, "data": {
    "shift": null, "needs_shift_opened": true, "requires_open_shift": true,
    "can_open": true, "can_open_message": "",
    "shift_mode": "cashier", "stock_check_required": false, "landing": "cashier.queue" } }
```

If `can_open` is false (outside the owner's shift hours), show `can_open_message`.

`POST /shifts/open` (no `counts` for cashiers):

```json
{ "opening_notes": "Opening float TZS 50,000" }
```

Response **201**:

```json
{ "success": true, "message": "Cashier shift opened. You can now collect payments.",
  "data": { "shift": { "id": 120, "status": "open" }, "variance_count": 0, "next": "cashier.queue" } }
```

Errors: `422 SHIFT_ALREADY_OPEN`. If the business has shift-time rules, you get 422 with a message to show.

---

## 3. Payment queue

`GET /cashier/queue?tab=unpaid&q=&status=&page=1&per_page=20`

**Permission:** `collect_payments`

| Query | Notes |
|-------|-------|
| `tab` | `unpaid` (default) or `paid` |
| `q` | Live search: order no, customer name or phone, officer name. Send on each keystroke with a ~300 ms debounce and cancel the previous request. |
| `status` | Unpaid tab only: `pending` (awaiting payment) or `partial`. Default both. |
| `date` | `YYYY-MM-DD`. Unpaid tab: sale date (optional). Paid tab: **payment** date, default today. |
| `officer_id` | Only one sales officer's orders |
| `branch_id` | Owners and managers only |
| `per_page` | 1–50 (default 20) |

**Scope:** orders in the cashier's **branch** from any sales officer. A cashier with no branch sees the whole business.

### Response

```json
{
  "success": true,
  "data": {
    "tab": "unpaid",
    "latest_order_id": 1832,
    "payment_collection_mode": "both",
    "shift": { "id": 120, "opened_at": "2026-10-03T08:00:00+03:00" },
    "can_collect": true,
    "cannot_collect_reason": null,
    "branch_name": "Main Branch",
    "stats": { "orders": 4, "pending": 3, "partial": 1, "total_due": 86500, "paid_orders": 12 },
    "orders": [
      {
        "id": 1832,
        "reference_no": "ORD-20261003-1832",
        "sale_source": "pos",
        "is_service": false,
        "sale_date": "2026-10-03",
        "created_at": "2026-10-03T09:34:53+03:00",
        "time": "09:34 AM",
        "waiting_minutes": 6,
        "officer": { "id": 29, "name": "SINDATO" },
        "customer": { "id": null, "name": null, "phone": null },
        "payment_status": "pending",
        "total_amount": 18000,
        "amount_paid": 0,
        "balance": 18000,
        "due_date": null,
        "locked_by": null,
        "payments": [],
        "items_count": 1,
        "items": [
          { "id": 501, "name": "Brake Pad", "qty": 2, "unit_price": 9000, "subtotal": 18000 }
        ]
      }
    ],
    "payment_methods": [
      { "key": "cash", "label": "Cash", "type": "immediate", "requires_reference": false, "provider_accounts": [] },
      { "key": "mobile_money", "label": "Mobile Money", "type": "immediate", "requires_reference": true,
        "provider_accounts": [{ "name": "M-Pesa", "pay_number": "123456", "account_name": "SINDATO" }] },
      { "key": "bank", "label": "Bank", "type": "immediate", "requires_reference": true,
        "provider_accounts": [{ "name": "CRDB", "pay_number": "0150...", "account_name": "SINDATO" }] },
      { "key": "debt", "label": "Pay Later (Credit)", "type": "credit", "requires_reference": false, "provider_accounts": [] }
    ],
    "refresh_seconds": 10,
    "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 4 }
  }
}
```

### Paid tab

`GET /cashier/queue?tab=paid&date=2026-10-03` returns fully paid orders that had a payment on that date (newest first). Each order includes `payments[]`:

```json
"payments": [
  { "amount": 18000, "method": "mobile_money", "provider": "M-Pesa", "reference": "QW12AB9Z",
    "collected_by": "SINDATO CASHIER", "collected_at": "2026-10-03T09:41:00+03:00" }
]
```

Show **Collected By**, **Method** and **Paid At**, plus View and Print buttons (no Pay button). The tab badges use `stats.orders` (Unpaid) and `stats.paid_orders` (Paid).

### Screen rules

| Situation | What to show |
|-----------|--------------|
| `can_collect: false` | Banner with `cannot_collect_reason`. If the cashier has no shift, add an "Open shift" button. Disable Pay buttons. |
| `locked_by` not null | Badge "{locked_by.name} collecting" and a disabled lock icon instead of Pay |
| Polling | Re-fetch every `refresh_seconds` (10) while the screen is visible. Pause while the pay sheet is open. |
| New-order alert | Store the last `latest_order_id`. If a refresh returns a bigger value: sound or vibration, a toast or local notification "New order waiting for payment", and highlight orders with `id` greater than the old value. Let the user turn alerts on or off. |
| Empty Unpaid | "No orders waiting for payment" |
| Empty Paid | "No paid orders found for this day" |

---

## 4. Order lock (several cashiers)

Only one person can work on an order's payment at a time.

| Call | When |
|------|------|
| `POST /cashier/orders/{id}/lock` | When Pay is tapped, **before** opening the pay sheet. Repeat every `heartbeat_seconds` (30) while the sheet is open. |
| `DELETE /cashier/orders/{id}/lock` | When the sheet is closed without paying |

A successful payment releases the lock automatically. A lock expires **90 s** after the last heartbeat (for example if the app crashes).

**200**

```json
{ "success": true, "message": "Order locked for payment",
  "data": { "sale_id": 1832, "locked": true, "ttl_seconds": 90, "heartbeat_seconds": 30 } }
```

**409**: someone else has it. Show the message, don't open the sheet, and refresh the queue.

```json
{ "success": false, "message": "JUMA is already collecting payment for order ORD-20261003-1832.",
  "errors": { "code": "SALE_LOCKED", "locked_by": "JUMA" } }
```

---

## 5. Collect payment

`POST /sales/{id}/pay`

Build the form from `payment_methods`:
- **Cash:** amount only.
- `requires_reference: true` (mobile money, bank): provider picker (from `provider_accounts[].name`) plus the transaction reference.
- `type: "credit"`: customer and due date.

Prices and discounts **cannot** be changed by the cashier; any line changes are ignored.

**Full payment, cash**

```json
{ "payment_method": "cash", "amount_paid": 18000 }
```

**Mobile money / bank**

```json
{ "payment_method": "mobile_money", "amount_paid": 18000, "payment_provider": "M-Pesa", "transaction_reference": "QW12AB9Z" }
```

**Part payment** (amount is less than the balance): customer, phone and due date for the rest are required.

```json
{ "payment_method": "cash", "amount_paid": 10000,
  "customer_id": null, "customer_name": "John Mwangi", "customer_phone": "0712345678", "due_date": "2026-10-10" }
```

**Pay later (credit)**: the whole balance becomes a debt. An optional deposit can be sent in `amount_paid`.

```json
{ "payment_method": "debt", "amount_paid": 0,
  "customer_id": 12, "customer_name": "John Mwangi", "customer_phone": "0712345678", "due_date": "2026-10-10" }
```

- `customer_id` is optional. If you send it, the saved customer's name and phone are used. The customer list is `GET /customers`.
- `amount_paid` above the balance is capped to the balance.
- Split payments (several methods in one go) are web-only for now. On mobile, send one payment per method.

**Response 200**

```json
{ "success": true, "message": "Payment recorded",
  "data": { "sale": { "id": 1832, "payment_status": "paid", "amount_paid": 18000, "...": "..." },
            "payment_message": "Payment of TZS 18,000 recorded — invoice fully paid." } }
```

After success, show `payment_message`, then open the receipt sheet (section 6).

### Payment errors

| Status | `errors` | Meaning / app action |
|--------|----------|----------------------|
| 422 | `code: SHIFT_REQUIRED` | No open cashier shift. Go to Open Shift. |
| 403 | `code: PAYMENT_COLLECTION_DISABLED` | Owner setting blocks this user. Show the message. |
| 409 | `code: SALE_LOCKED` | Another cashier is collecting. Refresh the queue. |
| 422 | `transaction_reference: [...]` | **Duplicate reference.** Show the message under the reference field. |
| 422 | field errors | Missing customer, phone, due date or provider |
| 422 | message only | "Sale is already paid or cancelled." Refresh the queue. |
| 403 | — | Order is outside the cashier's branch |

### Duplicate reference guard

A mobile-money or bank reference can be used **once per business**. Spaces, dashes and letter case are ignored, so `QW12-AB9Z` is the same as `qw12ab9z`. Cash, references shorter than 4 characters and cancelled orders are not checked.

```json
{ "success": false, "message": "Validation failed.",
  "errors": { "transaction_reference": [
    "Reference QW12AB9Z was already used on order ORD-20261001-1790 (TZS 18,000, by JUMA on Oct 01, 04:12 PM). Check the SMS and enter the correct reference."
  ] } }
```

---

## 6. Receipt for the customer

`GET /cashier/orders/{id}/receipt`. Call this right after a successful payment, or from the Paid tab.

```json
{
  "success": true,
  "data": {
    "sale_id": 1832,
    "reference_no": "ORD-20261003-1832",
    "customer_name": "John Mwangi",
    "customer_phone": "+255712345678",
    "payment_status": "paid",
    "total_amount": 18000,
    "amount_paid": 18000,
    "balance": 0,
    "text": "SINDATO STORE\nReceipt: ORD-20261003-1832 · 03 Oct 2026 09:41\n- Brake Pad x2 = TZS 18,000\nTotal: TZS 18,000\nPaid: TZS 18,000 (M-Pesa)\nThank you!",
    "print_url": "https://www.mauzolink.co.tz/sales/1832?print=1",
    "whatsapp_url": "https://wa.me/255712345678?text=…",
    "sms_url": "sms:+255712345678?body=…"
  }
}
```

| Button | Action |
|--------|--------|
| Print | Send `text` to a Bluetooth thermal printer, or open `print_url` (web login required) |
| WhatsApp | Open `whatsapp_url`. With no phone on the order, it opens WhatsApp to pick a contact. |
| SMS | Open `sms_url`. Hide the button when it is `null`. |
| Copy / Share | Share `text` through the system share sheet |
| Next customer | Back to the queue |

---

## 7. My collections (this shift)

`GET /cashier/collections?recent=10`. Shown as the "I Collected" card and the My Collections screen.

```json
{
  "success": true,
  "data": {
    "shift": { "id": 120, "opened_at": "2026-10-03T08:00:00+03:00" },
    "total": 245000,
    "payments_count": 14,
    "orders_count": 12,
    "by_method": [
      { "key": "cash", "method": "cash", "label": "Physical Cash", "amount": 150000, "count": 9 },
      { "key": "mobile_money:M-Pesa", "method": "mobile_money", "label": "M-Pesa", "amount": 95000, "count": 5 }
    ],
    "recent": [
      { "id": 900, "sale_id": 1832, "reference_no": "ORD-20261003-1832", "officer": "SINDATO",
        "customer": "John Mwangi", "amount": 18000, "method": "mobile_money", "provider": "M-Pesa",
        "reference": "QW12AB9Z", "time": "09:41 AM" }
    ]
  }
}
```

With no open shift, `shift` is `null` and totals are 0.

---

## 8. Debts (branch)

The cashier sees **all outstanding debts in his branch**: credit he gave and credit given by sales officers. Full field list: [`API_DEBTS.md`](API_DEBTS.md).

| Method | Endpoint | Notes |
|--------|----------|-------|
| `GET` | `/debts?search=&status=&filter=overdue&page=1` | List, stats, top customers. `payment_methods` here excludes credit. |
| `GET` | `/debts/{id}` | Items and payment history |
| `POST` | `/debts/{id}/collect` | Same body and errors as `POST /sales/{id}/pay` (section 5) |

Lock the debt with `POST /cashier/orders/{id}/lock` before opening the collect sheet, the same way as in the queue.

---

## 9. Handover (end of shift)

The cashier uses the same handover API as everyone else ([`API_HANDOVER.md`](API_HANDOVER.md)). In cashier mode the money is **what he collected**, not what he sold.

`GET /day-closing/preview?shift=120`

| Field | Cashier value |
|-------|---------------|
| `handover_mode` | `"cashier"` |
| `platform_breakdown`, `expected_handover` | All money he collected in this shift, on any officer's orders |
| `sales[]` | Orders he collected on. `payments[]` = only his payments; `cashier` = the sales officer. |
| `staff_reconciliation[0]` | `is_cashier: true`, `gross_sales: 0`, cash / mobile / bank = collected |
| `summary.gross_sales` | `0` (sales belong to the officers, so they are not counted twice) |
| `debt_collections` | Empty (already inside his collections) |

`POST /day-closing`

```json
{
  "closing_date": "2026-10-03",
  "shift_id": 120,
  "expenses": [ { "description": "Transport", "amount": 5000, "payment_method": "cash" } ],
  "report_notes": "All cash counted"
}
```

Response **201** includes `shift_closed: true` and `next: "shifts.open"`. Log out, or return to Open Shift for the next day.

- Expense `payment_method` must be `cash` or one of the platform keys from the preview; otherwise you get `422 INVALID_EXPENSE_SOURCE`.
- Officer side: `staff_reconciliation[].collected_by_others` shows what the cashier collected on his orders, so he is not shown as short.

---

## 10. Owner: cashiers on duty (live)

`GET /cashier/on-duty?branch_id=3`. **Owners and managers only**; others get 403. Poll every `refresh_seconds`.

```json
{
  "success": true,
  "data": {
    "branch_id": 3,
    "cashiers": [
      {
        "user_id": 57, "name": "SINDATO CASHIER", "branch": "Main Branch",
        "shift_id": 120, "opened_at": "2026-10-03T08:00:00+03:00", "opened_label": "08:00 AM",
        "total": 245000, "payments_count": 14,
        "last_payment_at": "2026-10-03T10:12:00+03:00", "last_payment_label": "2 minutes ago",
        "collecting_now": { "sale_id": 1832, "reference_no": "ORD-20261003-1832" }
      }
    ],
    "queue": { "orders": 4, "pending": 3, "partial": 1, "total_due": 86500 },
    "latest_order_id": 1832,
    "refresh_seconds": 10
  }
}
```

`collecting_now: null` means **Idle**. Show one card per cashier, plus the queue totals.

---

## 11. Owner: cashier performance report

`GET /cashier/performance?from=2026-10-01&to=2026-10-03&branch_id=3&cashiers_only=1`. **Permission:** `view_reports`. The default period is the start of this month to today. Web: **Reports → Cashier Performance**.

```json
{
  "success": true,
  "data": {
    "period": { "from": "2026-10-01", "to": "2026-10-03" },
    "summary": {
      "collectors": 3, "cashiers": 1,
      "total_collected": 820000, "cashier_collected": 610000, "cashier_share_percent": 74.4,
      "payments_count": 51, "avg_wait_minutes": 4.2,
      "total_short": 2000, "total_over": 0
    },
    "rows": [
      {
        "user_id": 57, "name": "SINDATO CASHIER", "is_cashier": true, "role_label": "Cashier", "branch": "Main Branch",
        "payments_count": 40, "orders_count": 38, "counter_orders": 38,
        "total": 610000, "cash": 400000, "non_cash": 210000, "avg_payment": 15250,
        "avg_wait_minutes": 4.2, "max_wait_minutes": 19,
        "shifts": 3, "handovers": 3, "money_short": 2000, "money_over": 0,
        "last_payment_at": "2026-10-03T10:12:00+03:00",
        "by_method": [ { "label": "Cash", "amount": 400000, "count": 28 }, { "label": "M-Pesa", "amount": 210000, "count": 12 } ]
      }
    ]
  }
}
```

| Field | Meaning |
|-------|---------|
| `counter_orders` | Orders the person collected on but did **not** sell |
| `avg_wait_minutes` / `max_wait_minutes` | Time from order placed to the first counter payment |
| `money_short` / `money_over` | From the person's submitted handovers in the period |
| `cashier_share_percent` | Share of all collections taken by payment cashiers |

---

## Errors summary

| Status | `errors.code` / field | When | App action |
|--------|-----------------------|------|------------|
| 401 | — | Token missing or expired | Back to login |
| 422 | `SHIFT_REQUIRED` | Paying without an open cashier shift | Open Shift screen |
| 422 | `SHIFT_ALREADY_OPEN` | Opening a second shift | Go to queue |
| 409 | `SALE_LOCKED` | Another cashier has the order open | Toast + refresh queue |
| 403 | `PAYMENT_COLLECTION_DISABLED` | Owner setting blocks this user | Show message, hide Pay |
| 422 | `transaction_reference` | Reference already used | Error under the reference field |
| 403 | — | Order outside the cashier's branch, or missing permission | Show message |
| 403 | — | `on-duty` called by a non-owner / non-manager | Hide the screen |
| 422 | `INVALID_EXPENSE_SOURCE` and others | Handover errors ([`API_HANDOVER.md`](API_HANDOVER.md)) | Show message |

---

## Implementation checklist

- [ ] Login → route by `is_payment_cashier`, `needs_shift_opened` and `home_screen`
- [ ] Open Shift screen (notes only)
- [ ] Queue with Unpaid / Paid tabs, debounced live search, 10 s polling paused while the pay sheet is open
- [ ] New-order alert from `latest_order_id` (sound, vibration, highlight; can be turned off)
- [ ] Lock before the pay sheet, 30 s heartbeat, unlock on close, handle 409
- [ ] Pay sheet: cash / reference methods / part payment / pay later; inline duplicate-reference error
- [ ] Receipt sheet: print text, WhatsApp, SMS, share
- [ ] My Collections screen
- [ ] Debts list + collect (with lock)
- [ ] Handover preview + submit → shift closed
- [ ] Respect `can_collect_payments` / `can_collect` everywhere (hide Pay buttons)
- [ ] Owner: on-duty screen and performance report
