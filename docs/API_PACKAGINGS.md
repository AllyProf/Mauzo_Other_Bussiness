# Packagings API (Mobile)

REST endpoints for packaging units (Piece, Carton, Bottle, etc.) — same behavior as web `/packagings`.

---

## What this feature does

**Packagings** are the sell / stock units your shop uses for products — for example:

- Soft drinks sold as **Piece (Pcs)**, **Carton**, or **Crate**
- Hardware sold as **Piece**, **Box**, or **Metre**
- Pharmacy as **Tablet**, **Strip**, **Bottle**

When you create an item later, you attach one or more of these packaging units with prices and how many pieces each packaging contains (e.g. 1 Carton = 12 Pcs).

### Why it exists

- POS needs units to price and sell (sell 1 carton vs 1 piece).
- Receiving / stock count uses the same units.
- Each **business type** (Liquor, Grocery, Spare Parts…) has a standard list of units you can import in one tap.

### How it relates to Categories

1. First set up **Categories** (import a business type for the branch).
2. Then import **Packagings** for that same business type.
3. Then add **Items** using those categories + packaging units.

Packagings are stored **per business** (not per branch), but when a branch is selected the list is filtered to units whose `source_business_type_key` matches that branch’s imported category types.

### Typical setup

1. Import categories for branch (e.g. Liquor).
2. `POST /packagings/import-templates` with `business_type_key: "liquor"` → creates Crate, Carton, Case, Piece, Can.
3. Optionally add / rename / delete units by hand.

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
| `GET` | `/packagings` | List units + templates / tabs meta |
| `POST` | `/packagings` | Create packaging unit |
| `PUT` | `/packagings/{id}` | Rename unit |
| `DELETE` | `/packagings/{id}` | Delete unit |
| `POST` | `/packagings/import-templates` | Import units for a business type |
| `DELETE` | `/packagings/clear-all` | Clear units (branch-type scoped when branch selected) |

---

## Permissions

| Action | Permission (any of) |
|--------|---------------------|
| List | `manage_packaging`, `view_inventory` |
| Create / import | `manage_packaging`, `add_items` |
| Update | `manage_packaging`, `edit_items` |
| Delete / clear all | `manage_packaging`, `delete_items` |

Owners receive all permissions automatically.

---

## Branch rules

- Packagings themselves are **business-wide** (no `branch_id` column).
- With an active branch filter (staff branch, or owner `switch-branch`):
  - List shows only units whose business type is imported on that branch
  - Clear-all deletes only those matching types
- Owner viewing **all branches** sees / can clear every packaging for the business.

---

## 1. List packagings

`GET /packagings`

### Response `200`

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "packagings": [
      {
        "id": 41,
        "name": "Carton",
        "source_business_type_key": "liquor"
      },
      {
        "id": 42,
        "name": "Piece (Pcs)",
        "source_business_type_key": "liquor"
      }
    ],
    "meta": {
      "branch_filter_id": 14,
      "active_branch_name": "Main",
      "viewing_all_branches": false,
      "branch_type_keys": ["liquor"],
      "imported_types": [
        { "key": "liquor", "label": "Liquor Store / Bar" }
      ],
      "tabs": [
        { "key": "liquor", "label": "Liquor Store / Bar", "count": 5, "is_custom": false }
      ],
      "other_count": 0,
      "default_tab": "liquor",
      "business_types_used": 1,
      "max_business_types": 3,
      "templates": [
        {
          "key": "liquor",
          "label": "Liquor Store / Bar",
          "units": ["Crate", "Carton", "Case", "Piece (Pcs)", "Can"]
        }
      ],
      "default_units": ["Piece (Pcs)", "Box", "Set", "Packet", "Carton", "Bag", "Bottle", "Pair"]
    }
  }
}
```

Use `meta.tabs` for UI filters, `meta.templates` / `meta.imported_types` for the import screen.

---

## 2. Create packaging unit

`POST /packagings`

```json
{
  "name": "Half Carton",
  "source_business_type_key": "liquor"
}
```

| Field | Required | Notes |
|-------|----------|-------|
| `name` | yes | Max 255 |
| `source_business_type_key` | recommended | Must be allowed for active branch; omit/`other` = untyped |

### Response `201`

```json
{
  "success": true,
  "message": "Packaging unit added successfully.",
  "data": {
    "packaging": {
      "id": 99,
      "name": "Half Carton",
      "source_business_type_key": "liquor"
    }
  }
}
```

---

## 3. Rename

`PUT /packagings/{id}`

```json
{ "name": "Carton (12 pcs)" }
```

### Response `200`

```json
{
  "success": true,
  "message": "Packaging unit updated.",
  "data": {
    "packaging": {
      "id": 41,
      "name": "Carton (12 pcs)",
      "source_business_type_key": "liquor"
    }
  }
}
```

---

## 4. Delete

`DELETE /packagings/{id}`

```json
{
  "success": true,
  "message": "Packaging unit deleted.",
  "data": null
}
```

---

## 5. Import templates

`POST /packagings/import-templates`

Import the standard unit list for one business type, or for **all** types already configured on the active branch.

### Single type

```json
{
  "business_type_key": "liquor"
}
```

Key must be in `meta.imported_types` when a branch is selected (import categories first).

### All types for current branch / business

```json
{
  "business_type_key": "all"
}
```

### Response `200`

```json
{
  "success": true,
  "message": "Packaging units for Liquor Store / Bar imported successfully. 5 unit(s) added.",
  "data": {
    "imported_keys": ["liquor"],
    "created": 5,
    "skipped": 0
  }
}
```

Existing units with the same name + type are skipped (idempotent).

### Common errors

| Status | When |
|--------|------|
| `422` | No categories/business types yet |
| `422` | Type not on active branch |
| `422` | Invalid `business_type_key` |
| `422` | Plan business-type limit (if registering a new type) |

---

## 6. Clear all

`DELETE /packagings/clear-all`

- Active branch → deletes packagings whose type keys belong to that branch  
- All branches (owner) → deletes **all** packaging units for the business  

### Response `200`

```json
{
  "success": true,
  "message": "All packaging units for this branch have been cleared.",
  "data": {
    "deleted_count": 5,
    "branch_id": 14
  }
}
```

**Warning:** destructive. Confirm in UI before calling.

---

## Recommended Flutter flow

```
1. Ensure categories imported for branch (GET /categories)
2. GET /packagings
3. If packagings empty → POST /packagings/import-templates { business_type_key }
4. List / filter by meta.tabs
5. Add custom unit: POST /packagings
6. Edit / delete as needed
```

---

## Curl examples

```bash
TOKEN="your-bearer-token"
BASE="http://192.168.1.200:5000/api/v1"

# List
curl -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/packagings"

# Import liquor units
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"business_type_key":"liquor"}' \
  "$BASE/packagings/import-templates"

# Create
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"Half Carton","source_business_type_key":"liquor"}' \
  "$BASE/packagings"

# Rename
curl -X PUT -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"Carton (12)"}' \
  "$BASE/packagings/41"

# Delete
curl -X DELETE -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/packagings/99"

# Clear all (current branch filter)
curl -X DELETE -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/packagings/clear-all"
```

---

## Related

- Categories (set up business types first): [`API_CATEGORIES.md`](API_CATEGORIES.md)
- Full mobile API overview: [`API_MOBILE.md`](API_MOBILE.md)
- Web screen: `/packagings`
- Branch switch (owners): `POST /auth/switch-branch`
