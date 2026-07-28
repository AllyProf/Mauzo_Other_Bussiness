# Customers API (Mobile)

REST endpoints for customer contacts — same behavior as web `/customers` (register modal + list + edit).

---

## What this feature does

**Customers** are people you sell to on credit or want to keep on file for POS.

Each customer stores:

- Name (required)
- Phone (required, Tanzania +255)
- Email, address, region, notes (optional)
- Active flag (inactive customers are hidden from POS search)

### Typical mobile flow (matches `/customers`)

1. `GET /customers/create-form` — regions + phone hint  
2. User fills name, 9-digit phone, optional fields  
3. `POST /customers` — register  
4. List / edit / delete from `GET /customers`

POS can also use `GET /customers/search?q=` while creating a sale.

---

| Environment | Base URL |
|-------------|----------|
| Production | `https://www.mauzolink.co.tz/api/v1` |
| Local | `http://192.168.100.106:5000/api/v1` |

All requests require:

```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

---

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/customers` | List customers (`?q=`, `?status=`) |
| `GET` | `/customers/create-form` | Form options for register screen |
| `GET` | `/customers/search` | Active customers for POS picker |
| `POST` | `/customers` | Register customer |
| `GET` | `/customers/{id}` | Detail + recent sales |
| `PUT` | `/customers/{id}` | Update |
| `DELETE` | `/customers/{id}` | Delete (only if no sales) |

**Permission:** `manage_customers` for list/create/edit/delete.  
Search also allows `process_sales`, `collect_payments`, `manage_debts`.

---

## 1. Create form

`GET /customers/create-form`

```json
{
  "success": true,
  "data": {
    "regions": ["Arusha", "Dar es Salaam", "Dodoma", "..."],
    "phone_hint": "Enter the last 9 digits (e.g. 712345678). Stored as +255…",
    "defaults": { "is_active": true },
    "fields": {
      "name": { "required": true, "max": 255 },
      "phone": { "required": true, "digits": 9 },
      "email": { "required": false },
      "address": { "required": false },
      "region": { "required": false },
      "notes": { "required": false },
      "is_active": { "required": false, "default": true }
    }
  }
}
```

---

## 2. Register (create)

`POST /customers`

### Required

| Field | Notes |
|-------|-------|
| `name` | Customer name |
| `phone` | 9 digits (e.g. `712345678`) or `+255…` — stored as `+255…` |

### Optional

| Field | Notes |
|-------|-------|
| `email` | Valid email |
| `address` | Free text |
| `region` | From create-form regions list |
| `notes` | Free text |
| `is_active` | Default `true` |

### Example

```json
{
  "name": "John Mwangi",
  "phone": "712345678",
  "email": "john@email.com",
  "address": "Mikocheni",
  "region": "Dar es Salaam",
  "notes": "Regular wholesale buyer",
  "is_active": true
}
```

**Response 201** — `data.customer` with id, phone as `+255712345678`, etc.

**Errors:**
- Invalid phone → 422
- Duplicate phone in same business → 422

---

## 3. List

`GET /customers`

| Query | Values |
|-------|--------|
| `q` / `search` | Name, phone, or email |
| `status` | `active`, `inactive`, or omit for all |

Returns `customers[]`, `stats` (`total`, `active`, `with_debt`).

Each customer includes `outstanding_balance` and `has_debt`.

---

## 4. Search (POS)

`GET /customers/search?q=john&limit=30`

Active customers only. Lighter payload for sale/debt pickers.

---

## 5. Detail

`GET /customers/{id}`

Returns customer, `stats` (total_sales, total_spent, outstanding), and recent `sales[]`.

---

## 6. Update

`PUT /customers/{id}`

**Same required fields as create** (`name`, `phone`). Send full body.

Also updates linked sales’ `customer_name` / `customer_phone` (same as web).

---

## 7. Delete

`DELETE /customers/{id}`

- **Body:** none  
- If customer has sales → **422** — mark inactive via `PUT` instead (`is_active: false`)

---

## Related

- Sales: use `customer_id` on `POST /sales`  
- Debts: [`API_MOBILE.md`](API_MOBILE.md) Debts section  
- Overview: [`API_MOBILE.md`](API_MOBILE.md)
