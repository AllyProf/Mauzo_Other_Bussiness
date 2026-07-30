# Sales Targets API

Set daily, weekly, or monthly revenue goals by branch, department, or staff member — same as web `/sales-targets`.

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

**Permissions:** `manage_sales_targets` or `manage_business_settings`  
**Plan feature:** `sales_targets`

---

## Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/sales-targets` | List targets + form options (branches, staff, departments) |
| GET | `/sales-targets/{id}` | Single target with progress |
| POST | `/sales-targets` | Create / upsert target |
| PUT | `/sales-targets/{id}` | Update target |
| DELETE | `/sales-targets/{id}` | Remove target |

---

## 1. List sales targets

```
GET /api/v1/sales-targets
```

### Query parameters

| Param | Type | Description |
|-------|------|-------------|
| `branch_id` | `int` | Filter list (owner). Staff locked to their branch |
| `business_type` | `string` | Filter by department key |
| `page` | `int` | Pagination (20 per page) |

### Success `200`

```json
{
  "success": true,
  "data": {
    "targets": [
      {
        "id": 12,
        "period_type": "monthly",
        "period_type_label": "Monthly",
        "period_start": "2026-07-01",
        "period_end": "2026-07-31",
        "period_label": "July 2026",
        "period_date": "2026-07-01",
        "target_amount": 5000000,
        "actual_amount": 1250000,
        "progress": 25,
        "remaining_amount": 3750000,
        "title": "Monthly target · Arusha Main · Retail · John Cashier",
        "scope_label": "Arusha Main · Retail · John Cashier",
        "branch_id": 10,
        "branch_name": "Arusha Main",
        "business_type_key": "retail",
        "business_type_label": "Retail",
        "user_id": 8,
        "user_name": "John Cashier",
        "notes": "July push",
        "created_by": { "id": 2, "name": "Owner" },
        "created_at": "2026-07-01T08:00:00+03:00",
        "updated_at": "2026-07-01T08:00:00+03:00"
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 20,
      "total": 1
    },
    "form": {
      "period_types": [
        { "key": "daily", "label": "Daily" },
        { "key": "weekly", "label": "Weekly" },
        { "key": "monthly", "label": "Monthly" }
      ],
      "branches": [
        { "id": 10, "name": "Arusha Main" }
      ],
      "business_types": [
        { "key": "retail", "label": "Retail", "icon": "fa-store" },
        { "key": "other", "label": "Other", "icon": "fa-ellipsis-h" }
      ],
      "staff": [
        { "id": 8, "name": "John Cashier", "branch_id": 10 }
      ]
    },
    "filters": {
      "branch_id": 10,
      "business_type": null,
      "branch_name": "Arusha Main",
      "viewing_all_branches": false
    },
    "meta": {
      "plan_feature": "sales_targets",
      "available": true
    }
  }
}
```

Use `form.*` to populate the create/edit screen (same fields as web).

### Plan not available `403`

```json
{
  "success": false,
  "message": "Sales targets are not available on your current plan.",
  "errors": {
    "plan": ["Sales targets are not available on your current plan."]
  }
}
```

---

## 2. Get one target

```
GET /api/v1/sales-targets/{id}
```

### Success `200`

```json
{
  "success": true,
  "data": {
    "target": {
      "id": 12,
      "period_type": "monthly",
      "target_amount": 5000000,
      "actual_amount": 1250000,
      "progress": 25,
      "period_date": "2026-07-01",
      "scope_label": "Arusha Main · Retail · John Cashier"
    }
  }
}
```

Use this to prefill the edit form (`period_date` = `period_start`).

---

## 3. Create / upsert target

```
POST /api/v1/sales-targets
```

Same uniqueness as web: same period + branch + department + staff → updates existing row (`updateOrCreate`).

### Body

```json
{
  "period_type": "monthly",
  "period_date": "2026-07-15",
  "target_amount": 5000000,
  "branch_id": 10,
  "business_type_key": "retail",
  "user_id": 8,
  "notes": "July push"
}
```

| Field | Required | Notes |
|-------|----------|-------|
| `period_type` | Yes | `daily`, `weekly`, `monthly` |
| `period_date` | Yes | Any date in the period (server expands to week/month bounds) |
| `target_amount` | Yes | Number ≥ 1 (TZS) |
| `branch_id` | No | Omit / null = all branches. Auto-filled from staff branch if staff selected |
| `business_type_key` | No | Department key or `other`. Omit = all departments |
| `user_id` | No | Staff member. Omit = all staff |
| `notes` | No | Max 255 chars |

### Success `201`

```json
{
  "success": true,
  "message": "Sales target saved successfully.",
  "data": {
    "target": { "id": 12, "period_type": "monthly", "progress": 0, "...": "..." }
  }
}
```

---

## 4. Update target

```
PUT /api/v1/sales-targets/{id}
```

Same body as create. Fails with `422` if another target already has the same period + scope.

### Success `200`

```json
{
  "success": true,
  "message": "Sales target updated successfully.",
  "data": {
    "target": { "id": 12, "...": "..." }
  }
}
```

---

## 5. Delete target

```
DELETE /api/v1/sales-targets/{id}
```

### Success `200`

```json
{
  "success": true,
  "message": "Sales target removed.",
  "data": {
    "deleted": true,
    "id": 12
  }
}
```

---

## Mobile flow

1. `GET /sales-targets` — list + form pickers
2. Compose target → `POST /sales-targets`
3. Edit: `GET /sales-targets/{id}` → `PUT /sales-targets/{id}`
4. Remove: `DELETE /sales-targets/{id}`

Progress (`actual_amount` / `progress`) is live revenue for the target’s period and scope (same calculation as web).

---

## Related

- Mobile overview: [`API_MOBILE.md`](API_MOBILE.md)
- Dashboard may also show active staff targets via existing dashboard endpoints
