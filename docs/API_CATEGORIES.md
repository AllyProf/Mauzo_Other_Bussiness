# Categories API (Mobile)

REST endpoints for inventory categories — same behavior as web `/categories`.

---

## What this feature does

**Categories** are folders that group products in the shop (e.g. Soft Drinks, Beers, Engine Parts). Every sellable item must belong to a category, so the shop must set categories up before (or while) adding stock.

### Why it exists

- **Organize inventory** — POS, stock reports, and item screens group products by category.
- **Match the type of shop** — a liquor store needs “Beers / Soft Drinks”; a spare-parts shop needs “Engine Parts / Filters”. The system does this through **business types**.
- **Multi-branch** — each branch has its own category list. Importing “Liquor” on Main does not automatically create those categories on another branch.

### How setup usually works

1. **Import a business-type template** (e.g. Liquor Store) — creates a ready-made set of category names for that branch.
2. Optionally **add / rename / delete** categories by hand.
3. When adding items later, staff pick one of these categories.

You can also create a **custom** business type (your own name + list of category names) if none of the presets fit.

### Plan limits

Some plans allow only a limited number of business types (`meta.max_business_types`). Importing a new type can fail with 422 if the limit is reached — clear unused categories or upgrade.

### What this API is *not*

- It does **not** manage products/items (that is items/stock/POS APIs).
- It does **not** move stock between branches.
- Clearing categories removes the category records (and resets imported types if none remain); product impact follows the same rules as the web app.

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

Currency and IDs are integers. Standard envelope:

```json
{
  "success": true,
  "message": "OK",
  "data": { }
}
```

Errors: `success: false`, optional `errors` object (422 validation).

---

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/categories` | List categories + templates / branch meta |
| `POST` | `/categories` | Create category |
| `PUT` | `/categories/{id}` | Rename category |
| `DELETE` | `/categories/{id}` | Delete category |
| `POST` | `/categories/import-templates` | Import template or custom categories |
| `DELETE` | `/categories/clear-all` | Clear all (branch-scoped when branch selected) |

---

## Permissions

| Action | Permission (any of) |
|--------|---------------------|
| List | `manage_categories`, `view_inventory` |
| Create / import | `manage_categories`, `add_items` |
| Update | `manage_categories`, `edit_items` |
| Delete / clear all | `manage_categories`, `delete_items` |

Owners receive all permissions automatically.

---

## Branch rules

- **Staff:** fixed to their `branch_id`. List/clear only see their branch. Create/import use that branch automatically (`branch_id` optional).
- **Owner:** branch filter comes from `POST /auth/switch-branch`.
  - Specific branch → list/clear scoped to that branch
  - All branches (`branch_id: null`) → list shows all; clear-all deletes **everything**
- When owner has **more than one** writable branch, `branch_id` is **required** on create and import.

---

## 1. List categories

`GET /categories`

### Response `200`

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "categories": [
      {
        "id": 226,
        "name": "Soft Drinks",
        "source_business_type_key": "liquor",
        "branch": { "id": 14, "name": "Main" },
        "items_count": 12
      }
    ],
    "meta": {
      "branch_filter_id": 14,
      "active_branch_name": "Main",
      "viewing_all_branches": false,
      "can_pick_branch": true,
      "writable_branches": [
        { "id": 14, "name": "Main", "is_default": true }
      ],
      "total_business_categories": 7,
      "categories_hidden_by_branch_filter": false,
      "business_types_used": 1,
      "max_business_types": 3,
      "imported_types": [
        {
          "key": "liquor",
          "label": "Liquor Store / Bar",
          "categories": ["Beers", "Soft Drinks"],
          "branch_names": ["Main"]
        }
      ],
      "templates": [
        {
          "key": "liquor",
          "label": "Liquor Store / Bar",
          "icon": "fa-glass",
          "categories": ["Beers", "Wines", "Spirits", "Soft Drinks", "Cigarettes"]
        }
      ]
    }
  }
}
```

### Meta fields

| Field | Meaning |
|-------|---------|
| `templates` | Preset business types you can import |
| `imported_types` | Types already present for the current filter |
| `can_pick_branch` | Show branch picker on create/import |
| `writable_branches` | Branches allowed for write operations |
| `categories_hidden_by_branch_filter` | Other branches have categories but current filter is empty |
| `max_business_types` | Plan limit (`null` = unlimited) |

---

## 2. Create category

`POST /categories`

```json
{
  "name": "Soft Drinks",
  "source_business_type_key": "liquor",
  "branch_id": 14
}
```

| Field | Required | Notes |
|-------|----------|-------|
| `name` | yes | Max 255 |
| `source_business_type_key` | yes | Must already be imported for that branch (see `meta.imported_types`) |
| `branch_id` | if multi-branch owner | Must be in `writable_branches` |

### Response `201`

```json
{
  "success": true,
  "message": "Category added successfully.",
  "data": {
    "category": {
      "id": 237,
      "name": "Soft Drinks",
      "source_business_type_key": "liquor",
      "branch": { "id": 14, "name": "Main" },
      "items_count": 0
    }
  }
}
```

### Common errors

| Status | When |
|--------|------|
| `422` | No branch / invalid branch |
| `422` | No imported type yet — import a template first |
| `422` | Validation failed (`errors` object) |
| `403` | Missing permission |

---

## 3. Rename category

`PUT /categories/{id}`

```json
{
  "name": "Soft Drinks & Water"
}
```

### Response `200`

```json
{
  "success": true,
  "message": "Category updated.",
  "data": {
    "category": {
      "id": 237,
      "name": "Soft Drinks & Water",
      "source_business_type_key": "liquor",
      "branch": { "id": 14, "name": "Main" },
      "items_count": 0
    }
  }
}
```

`403` if category belongs to another business/branch outside your filter.

---

## 4. Delete category

`DELETE /categories/{id}`

### Response `200`

```json
{
  "success": true,
  "message": "Category deleted.",
  "data": null
}
```

After delete, business imported types are synced (empty types removed).

---

## 5. Import templates

`POST /categories/import-templates`

Use this **before** creating manual categories for a new business type.

### A) Preset template(s)

```json
{
  "template_types": ["liquor", "grocery"],
  "branch_id": 14
}
```

Or a single type:

```json
{
  "template_type": "liquor",
  "branch_id": 14
}
```

Keys come from `meta.templates[].key` on the list endpoint.

### Response `200`

```json
{
  "success": true,
  "message": "Liquor Store / Bar categories imported successfully!",
  "data": {
    "imported_keys": ["liquor"],
    "imported_labels": ["Liquor Store / Bar"],
    "branch_id": 14
  }
}
```

### B) Custom business type

```json
{
  "template_type": "custom",
  "custom_business_name": "My Shop Type",
  "custom_categories": "Drinks, Snacks, Household",
  "branch_id": 14
}
```

- `custom_categories`: comma- or newline-separated names
- Creates key like `custom:my-shop-type`

### Response `200`

```json
{
  "success": true,
  "message": "Custom categories for \"My Shop Type\" imported successfully!",
  "data": {
    "imported_type": {
      "key": "custom:my-shop-type",
      "label": "My Shop Type",
      "categories": ["Drinks", "Snacks", "Household"]
    },
    "branch_id": 14
  }
}
```

### Common errors

| Status | When |
|--------|------|
| `422` | No template selected / empty custom list |
| `422` | Plan business-type limit reached |
| `422` | Unknown template key |
| `422` | Branch required / invalid |

---

## 6. Clear all categories

`DELETE /categories/clear-all`

- With active branch filter → deletes that branch only  
- Owner viewing all branches → deletes **all** business categories  
- If no categories left, imported business types are reset  

### Response `200`

```json
{
  "success": true,
  "message": "All categories for this branch have been cleared.",
  "data": {
    "deleted_count": 7,
    "branch_id": 14,
    "remaining_business_types": 0
  }
}
```

**Warning:** destructive. Confirm in UI before calling.

---

## Recommended Flutter flow

```
1. GET /categories
2. If imported_types empty → show templates → POST /categories/import-templates
3. List screen from data.categories
4. Add: pick source_business_type_key from imported_types (+ branch if can_pick_branch)
5. Edit: PUT /categories/{id}
6. Delete: DELETE /categories/{id}
7. Clear: DELETE /categories/clear-all (with confirm)
```

---

## Curl examples

```bash
TOKEN="your-bearer-token"
BASE="http://192.168.1.200:5000/api/v1"

# List
curl -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/categories"

# Import liquor template
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"template_type":"liquor","branch_id":14}' \
  "$BASE/categories/import-templates"

# Create
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"Soft Drinks","source_business_type_key":"liquor","branch_id":14}' \
  "$BASE/categories"

# Rename
curl -X PUT -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"Soft Drinks & Water"}' \
  "$BASE/categories/237"

# Delete
curl -X DELETE -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/categories/237"

# Clear all (current branch filter)
curl -X DELETE -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/categories/clear-all"
```

---

## Related

- Full mobile API overview: [`API_MOBILE.md`](API_MOBILE.md)
- Web screen: `/categories`
- Branch switch (owners): `POST /auth/switch-branch`
