# Items API (Mobile)

REST endpoints for product catalog — same behavior as web `/items` and `/items/create`.

Also includes POS search (`/items/search`) used at the till.

---

## What this feature does

**Items** are the products you sell. Creating an item means:

1. Give it a **name** (and optional brand / description)
2. Put it in a **category** (from Categories setup)
3. Choose how you **receive** stock (e.g. Carton) and how many pieces per receiving pack
4. Choose which **selling units** customers can buy (Piece, Carton…) with quantity-per-unit

Stock starts at **0**. You add stock later via receivings. Prices can be set on create/update (`selling_price` / `cost_price`); POS also uses packaging prices.

### Setup order

Categories → Packagings → **Items** → Receivings (stock in) → POS sales

### Duplicate names

Same name is blocked **per branch** (items sharing that branch’s categories). Use `GET /items/check-name` while typing.

---

| Environment | Base URL |
|-------------|----------|
| Production | `https://www.mauzolink.co.tz/api/v1` |
| Local | `http://192.168.100.106:5000/api/v1` (or your host) |

```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

---

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/items` | Inventory list (`?q=`, `?category_id=`, `?business_type_key=`) |
| `GET` | `/items/create-form` | Form options for create screen |
| `GET` | `/items/check-name` | Duplicate name check (`?name=&exclude_id=`) |
| `POST` | `/items` | Create item |
| `GET` | `/items/{id}` | Item detail (edit / POS) |
| `PUT` | `/items/{id}` | Update item |
| `DELETE` | `/items/{id}` | Delete item |
| `GET` | `/items/search` | POS search (stock > 0 only) |
| `GET` | `/items/lookup-barcode` | POS scan lookup (`?code=`) |
| `GET` | `/items/{id}/barcodes` | Label data + PNG base64 for print |

### Permissions

| Action | Permission |
|--------|------------|
| List / show | `view_inventory` (POS show also allows `process_sales`) |
| Create form / create | `add_items` |
| Check name | `add_items` or `edit_items` |
| Update | `edit_items` |
| Delete | `delete_items` |
| POS search / barcode lookup | `process_sales` or `view_inventory` |
| Barcode labels | `view_inventory`, `add_items`, `edit_items`, or `process_sales` |

---

## 1. Create form (for `/items/create`)

`GET /items/create-form`

Returns categories + packagings for the active branch, business types, and plan limits.

### Response `200`

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "business_types": [
      { "key": "liquor", "label": "Liquor Store / Bar", "icon": "fa-glass" }
    ],
    "multi_business": false,
    "default_business_type_key": "liquor",
    "categories": [
      { "id": 226, "name": "Soft Drinks", "business_type_key": "liquor" }
    ],
    "packagings": [
      { "id": 41, "name": "Carton", "business_type_key": "liquor" },
      { "id": 42, "name": "Piece (Pcs)", "business_type_key": "liquor" }
    ],
    "branch_filter_id": 14,
    "plan": {
      "max_items": null,
      "current_items": 216,
      "can_add": true
    },
    "fields": {
      "name": { "required": true },
      "category_id": { "required": false },
      "brand": { "required": false },
      "description": { "required": false },
      "business_type_key": { "required": false },
      "receiving_packaging_id": { "required": true },
      "units_per_receiving_pack": { "required": true, "min": 1 },
      "selling_packagings": { "required": true, "min": 1 }
    }
  }
}
```

`fields.business_type_key.required` is `true` when `multi_business` is true.

If `plan.can_add` is false, block create and show upgrade message.

---

## 2. Check name

`GET /items/check-name?name=Afya%20Maji&exclude_id=`

```json
{
  "success": true,
  "data": {
    "exact": true,
    "exact_item": { "id": 226, "name": "Afya Maji Ndogo" },
    "matches": [
      { "id": 226, "name": "Afya Maji Ndogo", "exact": true, "category": "Soft Drinks", "sku": "SP-…" }
    ]
  }
}
```

---

## 3. Register (create) item

`POST /items`  
Permission: `add_items`

### Fields required to register

| Field | Required | Type | Notes |
|-------|----------|------|-------|
| `name` | **yes** | string | Max 255. Must be unique on this branch |
| `receiving_packaging_id` | **yes** | int | From `create-form.packagings[].id` |
| `units_per_receiving_pack` | **yes** | int | ≥ 1 (pieces inside one receiving pack) |
| `selling_packagings` | **yes** | array | At least **1** row |
| `selling_packagings.*.packaging_id` | **yes** | int | From `create-form.packagings[].id` |
| `selling_packagings.*.quantity_per_unit` | **yes** | int | ≥ 1 (how many base pieces this unit holds) |
| `business_type_key` | **yes if** `multi_business` | string | From `create-form.business_types[].key` |
| `category_id` | no | int | Recommended. From `create-form.categories[].id` |
| `brand` | no | string | |
| `description` | no | string | |
| `cost_price` | no | number | Applied to first packaging row (default `0`) |
| `selling_packagings.*.selling_price` | no | number | Default `0` on create |

**Not sent by client:** `sku` (auto-generated), `current_stock` (starts at `0`).

### Example body

```json
{
  "name": "Afya Maji Medium",
  "brand": null,
  "category_id": 226,
  "business_type_key": "liquor",
  "description": null,
  "receiving_packaging_id": 41,
  "units_per_receiving_pack": 12,
  "cost_price": 2800,
  "selling_packagings": [
    { "packaging_id": 42, "quantity_per_unit": 1, "selling_price": 500 },
    { "packaging_id": 41, "quantity_per_unit": 12, "selling_price": 5000 }
  ]
}
```

### Response `201`

```json
{
  "success": true,
  "message": "Item registered successfully.",
  "data": {
    "item": {
      "id": 300,
      "name": "Afya Maji Medium",
      "sku": "SP-A1B2C3D4",
      "category_id": 226,
      "receiving_packaging_id": 41,
      "units_per_receiving_pack": 12,
      "current_stock": 0,
      "packagings": [
        {
          "id": 901,
          "packaging_id": 42,
          "name": "Piece (Pcs)",
          "quantity_per_unit": 1,
          "selling_price": 500,
          "cost_price": 2800
        }
      ]
    }
  }
}
```

---

## 4. List inventory

`GET /items`  
`GET /items?q=Afya&category_id=226`

Returns all items for the branch filter (including zero stock), unlike POS search.

---

## 5. Edit (update) item

### Load edit screen

`GET /items/{id}`  
Permission: `view_inventory` / `edit_items` / `process_sales`

Use the response to prefill the form. Map `item.packagings[]` → `selling_packagings` using `packaging_id`, `quantity_per_unit`, `selling_price`.

### Save edits

`PUT /items/{id}`  
Permission: `edit_items`

### Fields required to edit

Same required fields as **register**. Send the **full** item body (not a partial patch).

| Field | Required | Notes |
|-------|----------|-------|
| `name` | **yes** | Unique per branch (excluding this item) |
| `receiving_packaging_id` | **yes** | |
| `units_per_receiving_pack` | **yes** | ≥ 1 |
| `selling_packagings` | **yes** | At least 1 row; replaces previous packagings |
| `selling_packagings.*.packaging_id` | **yes** | |
| `selling_packagings.*.quantity_per_unit` | **yes** | ≥ 1 |
| `business_type_key` | **yes if** multi type | |
| `category_id` | no | |
| `brand` | no | |
| `description` | no | |
| `cost_price` | no | If omitted, keeps previous cost on first row when possible |
| `selling_packagings.*.selling_price` | no | If omitted for a packaging that already existed, previous price is kept |

**Do not send:** `sku` (unchanged), `current_stock` (server may rescale stock if `units_per_receiving_pack` increases from 1).

### Example body

```json
{
  "name": "Afya Maji Medium (Updated)",
  "category_id": 226,
  "business_type_key": "liquor",
  "receiving_packaging_id": 41,
  "units_per_receiving_pack": 12,
  "cost_price": 2800,
  "selling_packagings": [
    { "packaging_id": 42, "quantity_per_unit": 1, "selling_price": 550 },
    { "packaging_id": 41, "quantity_per_unit": 12, "selling_price": 5500 }
  ]
}
```

### Before saving

Optional: `GET /items/check-name?name=...&exclude_id={id}` so the current item is not treated as a duplicate of itself.

### Response `200`

```json
{
  "success": true,
  "message": "Item updated successfully.",
  "data": { "item": { } }
}
```

---

## 6. Delete item

`DELETE /items/{id}`  
Permission: `delete_items`

### What to send

| Need | Value |
|------|--------|
| URL | `/items/{id}` — **item id required** |
| Body | **none** (empty) |
| Headers | `Authorization: Bearer {token}`, `Accept: application/json` |

No JSON fields are required or accepted for delete.

### Response `200`

```json
{
  "success": true,
  "message": "Item deleted successfully.",
  "data": null
}
```

### Errors

| Status | When |
|--------|------|
| `403` | Missing `delete_items`, or item belongs to another business |
| `404` | Invalid `{id}` |

Confirm in UI before calling — this permanently removes the item.

---

## 7. POS search (existing)

`GET /items/search?q=&limit=30`

Only items with **stock > 0** and a category. Used by the sales screen. Also matches exact packaging `barcode`.

Each packaging in the response includes `barcode`.

---

## 8. Barcode scan lookup (fast checkout)

`GET /items/lookup-barcode?code=ML001000000123`

Resolves a **selling packaging** barcode generated at item register.

```json
{
  "success": true,
  "data": {
    "barcode": "ML001000000123",
    "item": {
      "id": 226,
      "name": "Castle Lite Can",
      "sku": "SP-…",
      "current_stock": 40,
      "in_stock": true
    },
    "packaging": {
      "id": 501,
      "name": "Can",
      "quantity_per_unit": 1,
      "selling_price": 3500,
      "barcode": "ML001000000123"
    },
    "cart_line": {
      "item_id": 226,
      "item_packaging_id": 501,
      "quantity": 1,
      "unit_price": 3500
    }
  }
}
```

**Flutter flow:** open camera → scan → `lookup-barcode` → push `cart_line` into cart → pay.

`404` if code is unknown for this business.

---

## 9. Barcode labels (print data)

`GET /items/{id}/barcodes`

Returns each selling packaging barcode + `barcode_png_base64` for on-device / Bluetooth label print.

Web print page (browser): `/items/{id}/barcodes/print`

Barcodes are **auto-generated when the item is registered** (one code per selling packaging). Receiving stock does not create new barcodes.

---

## Field cheat sheet

| Field | Register | Edit | Delete |
|-------|----------|------|--------|
| Path `{id}` | — | yes | **yes** (only requirement) |
| Body | yes | yes (full) | **no** |
| `name` | required | required | — |
| `receiving_packaging_id` | required | required | — |
| `units_per_receiving_pack` | required | required | — |
| `selling_packagings` (≥1) | required | required | — |
| `business_type_key` | if multi | if multi | — |
| `category_id` / `brand` / `description` | optional | optional | — |
| `cost_price` / `selling_price` | optional | optional | — |

---

## Recommended Flutter flow

```
REGISTER
1. GET /items/create-form
2. If !plan.can_add → show upgrade
3. Debounce GET /items/check-name while typing name
4. If multi_business → pick business_type_key, filter categories/packagings
5. Collect required fields → POST /items

EDIT
1. GET /items/{id} → prefill
2. Optional check-name with exclude_id
3. PUT /items/{id} with full body

DELETE
1. Confirm dialog
2. DELETE /items/{id} (no body)
```

---

## Curl examples

```bash
TOKEN="your-bearer-token"
BASE="http://192.168.100.106:5000/api/v1"

# Create form
curl -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/items/create-form"

# Check name
curl -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/items/check-name?name=Afya"

# Register
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "name":"Test Item API",
    "category_id":226,
    "receiving_packaging_id":41,
    "units_per_receiving_pack":12,
    "selling_packagings":[
      {"packaging_id":42,"quantity_per_unit":1,"selling_price":500},
      {"packaging_id":41,"quantity_per_unit":12,"selling_price":5000}
    ]
  }' \
  "$BASE/items"

# Edit
curl -X PUT -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "name":"Test Item API Updated",
    "category_id":226,
    "receiving_packaging_id":41,
    "units_per_receiving_pack":12,
    "selling_packagings":[
      {"packaging_id":42,"quantity_per_unit":1,"selling_price":550}
    ]
  }' \
  "$BASE/items/300"

# Delete (no body)
curl -X DELETE -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/items/300"
```

---

## Related

- Categories: [`API_CATEGORIES.md`](API_CATEGORIES.md)
- Packagings: [`API_PACKAGINGS.md`](API_PACKAGINGS.md)
- Overview: [`API_MOBILE.md`](API_MOBILE.md)
- Web: `/items`, `/items/create`
