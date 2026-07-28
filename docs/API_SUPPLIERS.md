# Suppliers API (Mobile)

REST endpoints for supplier contacts — same behavior as web `/suppliers` and `/suppliers/create`.

---

## What this feature does

**Suppliers** are the companies/people you buy stock from (used when recording receivings / purchases).

Each supplier belongs to a **branch** and stores contact details:

- Name (required)
- Phone (required, Tanzania +255)
- Email (optional)
- Region (optional)

### Typical mobile flow (matches `/suppliers/create`)

1. `GET /suppliers/create-form` — branches, regions, phone hint  
2. User fills name, 9-digit phone, optional email/region, branch if multi-branch  
3. `POST /suppliers` — register supplier  
4. List / edit / delete from `GET /suppliers`

Owners with multiple branches can also **copy or move** suppliers between branches.

---

| Environment | Base URL |
|-------------|----------|
| Production | `https://www.mauzolink.co.tz/api/v1` |
| Local | `http://192.168.1.200:5000/api/v1` (or your host) |

All requests require:

```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

Standard envelope:

```json
{
  "success": true,
  "message": "OK",
  "data": { }
}
```

---

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/suppliers` | List suppliers (`?q=` search) |
| `GET` | `/suppliers/create-form` | Form options for create screen |
| `POST` | `/suppliers` | Register supplier |
| `GET` | `/suppliers/{id}` | Supplier detail |
| `PUT` | `/suppliers/{id}` | Update supplier |
| `DELETE` | `/suppliers/{id}` | Delete supplier |
| `GET` | `/suppliers/branch/{branchId}/list` | Suppliers on one branch |
| `POST` | `/suppliers/migrate-from-branch` | Copy or move between branches |

**Permission:** `manage_suppliers` (owners have it automatically).

---

## Branch rules

- Staff: locked to their branch for list/create/edit.
- Owner: list filtered by `POST /auth/switch-branch`; all-branches view shows everyone.
- Multi-branch owners must pass `branch_id` on create/update.

---

## 1. Create form (for `/suppliers/create` screen)

`GET /suppliers/create-form`

### Response `200`

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "branches": [
      { "id": 14, "name": "Main", "is_default": true }
    ],
    "default_branch_id": 14,
    "can_pick_branch": false,
    "regions": [
      "Arusha",
      "Dar es Salaam",
      "Dodoma",
      "Mbeya",
      "Mwanza",
      "Morogoro",
      "Tanga",
      "Kilimanjaro",
      "Zanzibar"
    ],
    "phone_hint": "Enter the last 9 digits (e.g. 712345678). Stored as +255…",
    "fields": {
      "name": { "required": true, "max": 255 },
      "phone": { "required": true, "digits": 9 },
      "email": { "required": false, "max": 255 },
      "region": { "required": false },
      "branch_id": { "required": false }
    }
  }
}
```

Show a branch picker only when `can_pick_branch` is `true`.

---

## 2. Register supplier

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

| Field | Required | Notes |
|-------|----------|-------|
| `name` | yes | Max 255 |
| `phone` | yes | 9 digits (`712345678`), or `0712345678` / `255712345678` / `+255712345678` |
| `email` | no | Valid email |
| `region` | no | Prefer values from create-form `regions` |
| `branch_id` | if multi-branch owner | Must be writable |

Stored phone becomes `+255712345678`.

### Response `201`

```json
{
  "success": true,
  "message": "Supplier registered successfully.",
  "data": {
    "supplier": {
      "id": 55,
      "name": "Arusha Auto Parts",
      "phone": "+255712345678",
      "phone_local": "712345678",
      "email": "info@supplier.com",
      "region": "Arusha",
      "contact_person": null,
      "address": null,
      "branch_id": 14,
      "branch": { "id": 14, "name": "Main" }
    }
  }
}
```

Use `phone_local` when editing (same as web stripping `+255`).

---

## 3. List suppliers

`GET /suppliers`  
`GET /suppliers?q=Arusha`

### Response `200`

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "suppliers": [ /* same shape as above */ ],
    "meta": {
      "branch_filter_id": 14,
      "active_branch_name": "Main",
      "viewing_all_branches": false,
      "can_migrate_from_branch": true,
      "branches": [
        { "id": 14, "name": "Main", "is_default": true }
      ]
    }
  }
}
```

---

## 4. Show / update / delete

`GET /suppliers/{id}`

`PUT /suppliers/{id}` — same body as create

`DELETE /suppliers/{id}`

```json
{ "success": true, "message": "Supplier removed.", "data": null }
```

---

## 5. List by branch

`GET /suppliers/branch/{branchId}/list`

Useful for migrate UI source list.

---

## 6. Migrate between branches

`POST /suppliers/migrate-from-branch`

```json
{
  "from_branch_id": 14,
  "to_branch_id": 15,
  "mode": "copy",
  "supplier_ids": [55, 56]
}
```

| Field | Notes |
|-------|-------|
| `mode` | `copy` (duplicate) or `move` (change branch) |
| `supplier_ids` | Optional; omit = all on source branch |

Skips names that already exist on the destination (case-insensitive).

### Response `200`

```json
{
  "success": true,
  "message": "Supplier migrate complete: 2 copied, 1 skipped (already on destination).",
  "data": {
    "copied": 2,
    "moved": 0,
    "skipped": 1,
    "from_branch_id": 14,
    "to_branch_id": 15,
    "mode": "copy"
  }
}
```

---

## Recommended Flutter flow

```
1. GET /suppliers/create-form
2. Build form (branch picker if can_pick_branch)
3. POST /suppliers
4. GET /suppliers → list screen
5. PUT / DELETE as needed
6. Optional: migrate UI when meta.can_migrate_from_branch
```

---

## Curl examples

```bash
TOKEN="your-bearer-token"
BASE="http://192.168.1.200:5000/api/v1"

# Create form
curl -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/suppliers/create-form"

# Register
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"Arusha Auto Parts","phone":"712345678","region":"Arusha","branch_id":14}' \
  "$BASE/suppliers"

# List
curl -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/suppliers?q=Arusha"

# Update
curl -X PUT -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"Arusha Auto Parts Ltd","phone":"712345678","region":"Arusha","branch_id":14}' \
  "$BASE/suppliers/55"

# Delete
curl -X DELETE -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/suppliers/55"
```

---

## Related

- Full mobile API overview: [`API_MOBILE.md`](API_MOBILE.md)
- Web screens: `/suppliers`, `/suppliers/create`
- Branch switch (owners): `POST /auth/switch-branch`
