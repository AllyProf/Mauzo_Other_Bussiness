# Invoices API (Mobile)

Product invoices — same behavior as web `/invoices` and `/invoices/create`.

Creates a pending sale (`sale_source: invoice`) with line items. Stock is **not** deducted until payment is collected (same as POS orders). Customer can be notified by SMS and email PDF.

---

| Environment | Base URL |
|-------------|----------|
| Production | `https://www.mauzolink.co.tz/api/v1` |
| Local | `http://192.168.0.124:5000/api/v1` |

All requests require:

```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

**Permissions**

| Action | Ability |
|--------|---------|
| List / show | `view_invoices` **or** `view_sales_history` **or** `process_sales` |
| Create form / create | `create_invoices` **or** `process_sales` |
| Collect payment | Use existing `POST /sales/{id}/pay` (`collect_payments` / `collect_invoice_payments` / `process_sales`) |

**Prerequisite:** If the user requires an open shift, open one first via `POST /shifts/open`.

---

## Typical mobile flow (matches `/invoices/create`)

1. `GET /invoices/create-form` — catalog (in-stock items), customers, business types, shift
2. User picks items + qty/price, optional customer
3. `POST /invoices` — create pending invoice (`INV-…`)
4. Optional: `POST /sales/{id}/pay` — collect payment later
5. `GET /invoices` / `GET /invoices/{id}` — history and detail

---

## 1. Create form

```
GET /api/v1/invoices/create-form
```

### Success `200`

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "shift": {
      "id": 44,
      "opened_at": "2026-07-29T08:00:00+03:00"
    },
    "requires_open_shift": true,
    "branch_id": 10,
    "branch_name": "Main Branch",
    "business_types": [
      { "key": "retail", "label": "Retail", "icon": "fa-store" }
    ],
    "multi_business": false,
    "catalog_items": [
      {
        "id": 101,
        "name": "Coca-Cola 500ml",
        "sku": "CC-500",
        "stock": 48,
        "price": 1500,
        "business_type_key": "retail",
        "business_type_label": "Retail"
      }
    ],
    "catalog_groups": [
      {
        "key": "retail",
        "label": "Retail",
        "items": [
          {
            "id": 101,
            "name": "Coca-Cola 500ml",
            "sku": "CC-500",
            "stock": 48,
            "price": 1500,
            "business_type_key": "retail",
            "business_type_label": "Retail"
          }
        ]
      }
    ],
    "customers": [
      {
        "id": 7,
        "name": "Acme Traders",
        "phone": "+255712345678",
        "email": "acme@example.com"
      }
    ],
    "default_sale_date": "2026-07-29"
  }
}
```

> Only items with **stock > 0** and **price > 0** are included (same as web).

### Shift required `422`

```json
{
  "success": false,
  "message": "Complete a physical stock check and open your shift before creating invoices.",
  "errors": { "code": "SHIFT_REQUIRED" }
}
```

---

## 2. Create invoice

```
POST /api/v1/invoices
```

### Request body

```json
{
  "sale_date": "2026-07-29",
  "customer_id": 7,
  "customer_name": "Acme Traders",
  "customer_phone": "712345678",
  "customer_email": "acme@example.com",
  "notes": "Deliver to warehouse gate",
  "items": [
    { "id": 101, "qty": 10, "price": 1500 },
    { "id": 102, "qty": 2, "price": 25000 }
  ]
}
```

| Field | Required | Notes |
|-------|----------|-------|
| `sale_date` | Yes | `YYYY-MM-DD` |
| `items` | Yes | Min 1 row with `qty > 0` |
| `items[].id` | Yes | Item id from create-form catalog |
| `items[].qty` | Yes | Integer ≥ 1 |
| `items[].price` | Yes | Unit price (≥ 0) |
| `customer_id` | No | If set, name/phone taken from customer record |
| `customer_name` | No | Walk-in / override name |
| `customer_phone` | No | Used for SMS; normalized to `+255…` |
| `customer_email` | No | Optional PDF email override |
| `notes` | No | Max 1000 chars |

### Success `201`

```json
{
  "success": true,
  "message": "Invoice INV-20260729-A1B2 created. SMS sent to customer.",
  "data": {
    "invoice": {
      "id": 320,
      "reference_no": "INV-20260729-A1B2",
      "sale_date": "2026-07-29",
      "total_amount": 65000,
      "amount_paid": 0,
      "balance_due": 65000,
      "payment_status": "pending",
      "payment_method": null,
      "customer_id": 7,
      "customer_name": "Acme Traders",
      "customer_phone": "+255712345678",
      "customer_email": "acme@example.com",
      "cashier": "Jane Cashier",
      "shift_id": 44,
      "sale_source": "invoice",
      "created_at": "2026-07-29T11:20:00+03:00",
      "due_date": null,
      "notes": "Deliver to warehouse gate",
      "items": [
        {
          "id": 901,
          "item_id": 101,
          "name": "Coca-Cola 500ml",
          "sku": "CC-500",
          "quantity": 10,
          "unit_price": 1500,
          "subtotal": 15000,
          "packaging": null
        },
        {
          "id": 902,
          "item_id": 102,
          "name": "Crate deposit",
          "sku": null,
          "quantity": 2,
          "unit_price": 25000,
          "subtotal": 50000,
          "packaging": null
        }
      ],
      "payments": []
    },
    "notifications": {
      "sms_sent": true,
      "email_sent": false,
      "sms_error": null,
      "email_error": null
    }
  }
}
```

### Common errors `422`

- Shift not open
- Item not in selected branch
- Not enough stock for an item
- Empty `items` / qty 0

---

## 3. List invoices

```
GET /api/v1/invoices
```

### Query params

| Param | Description |
|-------|-------------|
| `date` | Filter by `sale_date` |
| `payment_status` | e.g. `pending`, `partial`, `paid`, `debt` |
| `q` | Search reference / customer name / phone |
| `page` | Page number |
| `per_page` | Default 20, max 50 |

### Success `200`

```json
{
  "success": true,
  "data": {
    "invoices": [
      {
        "id": 320,
        "reference_no": "INV-20260729-A1B2",
        "sale_date": "2026-07-29",
        "total_amount": 65000,
        "amount_paid": 0,
        "balance_due": 65000,
        "payment_status": "pending",
        "customer_name": "Acme Traders",
        "customer_phone": "+255712345678",
        "cashier": "Jane Cashier",
        "shift_id": 44,
        "sale_source": "invoice"
      }
    ],
    "stats": {
      "total": 12,
      "unpaid": 4,
      "total_amount": 890000
    },
    "scoped_to_self": false,
    "pagination": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 20,
      "total": 12
    }
  }
}
```

Staff without business-wide access only see **their own** invoices (`scoped_to_self: true`).

---

## 4. Invoice detail

```
GET /api/v1/invoices/{id}
```

### Success `200`

```json
{
  "success": true,
  "data": {
    "invoice": {
      "id": 320,
      "reference_no": "INV-20260729-A1B2",
      "sale_date": "2026-07-29",
      "total_amount": 65000,
      "amount_paid": 0,
      "balance_due": 65000,
      "payment_status": "pending",
      "items": [ "..." ],
      "payments": []
    },
    "payment_methods": [
      { "key": "cash", "label": "Cash" },
      { "key": "mobile_money", "label": "Mobile Money" }
    ],
    "payment_receive_details": [
      {
        "method_key": "mobile_money",
        "method_label": "Mobile Money",
        "platform": "M-Pesa",
        "pay_number": "255712000000",
        "account_name": "Shop Name"
      }
    ]
  }
}
```

---

## Collect payment

Invoices are `Sale` rows. Collect payment with the existing sales endpoint:

```
POST /api/v1/sales/{id}/pay
```

Use the same payment body documented in [`API_MOBILE.md`](API_MOBILE.md) (Sales → Pay).

---

## Related docs

- Mobile overview: [`API_MOBILE.md`](API_MOBILE.md)
- Customers: [`API_CUSTOMERS.md`](API_CUSTOMERS.md)
