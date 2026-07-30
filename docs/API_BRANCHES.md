# Branches API (Mobile)

Register and manage shop locations — same as web `/branches`.

Assign which businesses operate at each branch, set branch leader contact details, and view staff counts. Switch active branch via existing `POST /auth/switch-branch`.

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

**Permission:** `manage_branches` (create / update / delete)  
**Reference list:** `GET /branches` without `manage_branches` returns a simple id/name list (for branch switcher).

---

## 1. List branches

```
GET /api/v1/branches
```

### With `manage_branches` — full index `200`

```json
{
  "success": true,
  "data": {
    "branches": [
      {
        "id": 10,
        "name": "Arusha Main Shop",
        "address": "Sokoine Road, Plot 12",
        "location": "Arusha City Centre",
        "leader_name": "John Manager",
        "leader_phone": "+255712345678",
        "leader_email": "john@example.com",
        "is_active": true,
        "is_default": true,
        "staff_count": 4,
        "can_delete": false,
        "businesses": [
          { "id": 1, "name": "Boma Retail", "is_primary": true },
          { "id": 2, "name": "Print & Copy", "is_primary": false }
        ],
        "business_ids": [1, 2],
        "created_at": "2026-01-15T10:00:00+03:00"
      }
    ],
    "meta": {
      "current_count": 2,
      "max_branches": 5,
      "branches_limit_label": "5",
      "can_add_branch": true,
      "plan_name": "Pro",
      "active_branch_id": 10
    },
    "assignable_businesses": [
      { "id": 1, "name": "Boma Retail" },
      { "id": 2, "name": "Print & Copy" }
    ]
  }
}
```

### Without `manage_branches` — reference list `200`

```json
{
  "success": true,
  "data": {
    "branches": [
      { "id": 10, "name": "Arusha Main Shop", "is_default": true }
    ]
  }
}
```

---

## 2. Register branch

```
POST /api/v1/branches
```

### Request body

```json
{
  "name": "Arusha Main Shop",
  "location": "Arusha City Centre",
  "address": "Sokoine Road, Plot 12",
  "leader_name": "John Manager",
  "leader_phone": "712345678",
  "leader_email": "john@example.com",
  "business_ids": [1, 2]
}
```

| Field | Required | Notes |
|-------|----------|-------|
| `name` | Yes | Branch name |
| `business_ids` | Yes | Min 1 — businesses operating at this branch; first = primary |
| `location` | No | City / area / landmark |
| `address` | No | Street address |
| `leader_name` | No | Branch manager |
| `leader_phone` | No | Normalized to `+255…` |
| `leader_email` | No | |

First branch for the owner is auto-set as **default**.

### Success `201`

```json
{
  "success": true,
  "message": "Branch registered successfully.",
  "data": {
    "branch": {
      "id": 11,
      "name": "Arusha Main Shop",
      "is_default": false,
      "is_active": true,
      "staff_count": 0,
      "can_delete": true,
      "businesses": [
        { "id": 1, "name": "Boma Retail", "is_primary": true },
        { "id": 2, "name": "Print & Copy", "is_primary": false }
      ],
      "business_ids": [1, 2]
    }
  }
}
```

### Plan limit `422`

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "branch_limit": ["Your Pro plan allows up to 5 branch(es). Upgrade your plan to add more."]
  }
}
```

---

## 3. Update branch

```
PUT /api/v1/branches/{id}
```

Same fields as create, plus:

| Field | Notes |
|-------|-------|
| `is_active` | `true` / `false` |

### Success `200`

```json
{
  "success": true,
  "message": "Branch updated successfully.",
  "data": {
    "branch": { "id": 11, "name": "Arusha Main Shop", "is_active": true, "...": "..." }
  }
}
```

---

## 4. Delete branch

```
DELETE /api/v1/branches/{id}
```

### Restrictions

- Cannot delete the **default** branch
- Cannot delete if **staff** are still assigned

### Success `200`

```json
{
  "success": true,
  "message": "Branch deleted successfully.",
  "data": {
    "deleted_branch_id": 11,
    "deleted_branch_name": "Arusha Main Shop"
  }
}
```

---

## Switch active branch

Use the existing auth endpoint (not this CRUD):

```
POST /api/v1/auth/switch-branch
```

```json
{ "branch_id": 10 }
```

Pass `branch_id: null` or omit to view **all branches**.

---

## Mobile flow (matches `/branches`)

1. `GET /branches` — list + plan limits + assignable businesses
2. `POST /branches` — register new location
3. `PUT /branches/{id}` — edit details / toggle active
4. `DELETE /branches/{id}` — remove (if allowed)
5. `POST /auth/switch-branch` — set active branch for reporting/POS

---

## Related

- Auth / switch branch: [`API_MOBILE.md`](API_MOBILE.md)
- Staff (assign employees to branch): [`API_STAFF.md`](API_STAFF.md)
