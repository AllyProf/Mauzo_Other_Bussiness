# Staff API (Roles & Employees)

Mobile endpoints for staff management — same as web:

- `/roles` and `/roles/create`
- `/employees` and `/employees/create`

**Base URL:** `/api/v1`  
**Permission:** `manage_staff`  
**Auth:** `Authorization: Bearer {token}`

---

## Quick reference

| Method | Endpoint | Web page | Description |
|--------|----------|----------|-------------|
| GET | `/roles` | `/roles` | List roles |
| GET | `/roles/create-form` | `/roles/create` | Permission groups + presets |
| POST | `/roles` | `POST /roles` | Create role |
| GET | `/employees` | `/employees` | List staff (`?q=` search) |
| GET | `/employees/create-form` | `/employees/create` | Branches, roles, business types |
| POST | `/employees` | `POST /employees` | Register employee |

---

## Recommended mobile flow

1. **Roles first** — create at least one role before adding employees  
   `GET /roles/create-form` → `POST /roles` → `GET /roles`
2. **Employees** — register staff  
   `GET /employees` → `GET /employees/create-form` → `POST /employees` → `GET /employees`
3. On branch change during employee create, call `GET /employees/create-form?branch_id={id}` to refresh business types

---

## Roles

### List roles

`GET /roles`

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "roles": [
      {
        "id": 3,
        "name": "Cashier",
        "permissions": ["process_sales", "submit_day_closing"],
        "permission_labels": [
          { "key": "process_sales", "label": "Process POS sales (place orders)" }
        ],
        "created_at": "2026-07-20T10:00:00+00:00"
      }
    ],
    "meta": { "roles_count": 1 }
  }
}
```

### Create form

`GET /roles/create-form`

Returns permission groups, presets (Cashier, Store Manager, Supervisor), and field rules.

```json
{
  "success": true,
  "data": {
    "permission_groups": [
      {
        "group": "Store / POS",
        "permissions": [
          { "key": "process_sales", "label": "Process POS sales (place orders)" }
        ]
      }
    ],
    "presets": [
      {
        "name": "Cashier",
        "permissions": ["open_shift", "process_sales", "submit_day_closing"]
      }
    ],
    "fields": {
      "name": { "required": true, "max": 255 },
      "permissions": { "required": false, "type": "array" }
    }
  }
}
```

### Create role

`POST /roles`

```json
{
  "name": "Cashier",
  "permissions": [
    "open_shift",
    "process_sales",
    "view_sales_history",
    "submit_day_closing"
  ]
}
```

**Success (201):**

```json
{
  "success": true,
  "message": "Role created successfully.",
  "data": {
    "role": {
      "id": 4,
      "name": "Cashier",
      "permissions": ["open_shift", "process_sales"],
      "permission_labels": [],
      "created_at": "2026-07-29T07:00:00+00:00"
    }
  }
}
```

Unknown permission keys are ignored (same as web).

---

## Employees

### List employees

`GET /employees`

**Query (optional):** `q` — search name, email, or phone

```json
{
  "success": true,
  "data": {
    "employees": [
      {
        "id": 29,
        "name": "Joshua",
        "email": "joshua@gmail.com",
        "phone": "+255712345678",
        "role": "staff",
        "role_id": 3,
        "role_name": "Cashier",
        "branch_id": 10,
        "branch_name": "Main Branch",
        "business_type_keys": ["retail"],
        "business_type_labels": "Retail",
        "is_active": true,
        "is_owner": false,
        "can_reset_password": true,
        "can_toggle_status": true,
        "created_at": "2026-07-15T08:00:00+00:00"
      }
    ],
    "meta": {
      "employees_count": 4,
      "branch_filter_id": 10,
      "viewing_all_branches": false,
      "has_branches": true,
      "has_roles": true,
      "can_add_employee": true,
      "staff_limit": {
        "max_users": 10,
        "current_users": 4,
        "remaining": 6
      }
    }
  }
}
```

Branch scoping follows active API branch context (same as other modules).

### Create form

`GET /employees/create-form`

**Query (optional):** `branch_id` — pre-select branch and return its business types

```json
{
  "success": true,
  "data": {
    "business": { "id": 10, "name": "SINDATO STORE" },
    "branches": [
      { "id": 10, "name": "Main Branch", "is_default": true }
    ],
    "default_branch_id": 10,
    "can_pick_branch": true,
    "roles": [
      { "id": 3, "name": "Cashier" }
    ],
    "imported_types_by_branch": {
      "10": [
        { "key": "retail", "label": "Retail", "categories": ["Beverages"] }
      ]
    },
    "imported_types": [
      { "key": "retail", "label": "Retail", "categories": ["Beverages"] }
    ],
    "default_business_type_keys": ["retail"],
    "phone_hint": "Enter the last 9 digits (e.g. 712345678). Stored as +255…",
    "fields": {
      "branch_id": { "required": true },
      "business_type_keys": { "required": true, "type": "array", "min": 1 },
      "name": { "required": true, "max": 255 },
      "email": { "required": true, "email": true },
      "phone": { "required": false, "digits": 9 },
      "role_id": { "required": true },
      "password": { "required": true, "min": 6 },
      "password_confirmation": { "required": true, "min": 6 }
    },
    "blockers": {
      "no_branches": false,
      "no_roles": false,
      "no_business_types_for_branch": false
    }
  }
}
```

Check `blockers` before showing the form — same guards as web (need branch, role, and imported business types).

### Create employee

`POST /employees`

```json
{
  "branch_id": 10,
  "business_type_keys": ["retail"],
  "name": "Jane Doe",
  "email": "jane@example.com",
  "phone": "712345678",
  "role_id": 3,
  "password": "123456",
  "password_confirmation": "123456"
}
```

**Success (201):**

```json
{
  "success": true,
  "message": "Staff member added successfully. Login details were sent by SMS.",
  "data": {
    "employee": {
      "id": 38,
      "name": "Jane Doe",
      "email": "jane@example.com",
      "phone": "+255712345678",
      "role": "staff",
      "role_id": 3,
      "role_name": "Cashier",
      "branch_id": 10,
      "branch_name": "Main Branch",
      "business_type_keys": ["retail"],
      "business_type_labels": "Retail",
      "is_active": true,
      "is_owner": false,
      "can_reset_password": true,
      "can_toggle_status": true,
      "created_at": "2026-07-29T07:30:00+00:00"
    },
    "notifications": {
      "sms_sent": true,
      "email_sent": false
    }
  }
}
```

---

## Validation rules (employees)

| Field | Rule |
|-------|------|
| `branch_id` | Must belong to active business |
| `business_type_keys` | Must match types imported for branch (Categories) |
| `role_id` | At least one role must exist |
| Plan limit | `max_users` enforced when set on plan |
| `phone` | 9 digits starting with 6, 7, or 8 → stored as `+255…` |
| `password` | Min 6 chars; must match `password_confirmation` |

---

## 7. Update employee

```
PUT /api/v1/employees/{id}
Authorization: Bearer {token}
```

### Request body

Same fields as create. All are required (full replacement):

```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "phone": "712345678",
  "branch_id": 10,
  "role_id": 3,
  "business_type_keys": ["retail"],
  "password": "newpass123",
  "password_confirmation": "newpass123"
}
```

> `password` + `password_confirmation` are **optional**. If omitted, the password is not changed. If provided (min 6 chars), the new password is sent via SMS/email automatically.

### Success response `200`

```json
{
  "success": true,
  "message": "Staff member updated successfully. New password sent by SMS.",
  "data": {
    "employee": {
      "id": 42,
      "name": "Jane Doe",
      "email": "jane@example.com",
      "phone": "+255712345678",
      "role": "staff",
      "role_id": 3,
      "role_name": "Cashier",
      "branch_id": 10,
      "branch_name": "Main Branch",
      "business_type_keys": ["retail"],
      "business_type_labels": ["Retail"],
      "is_active": true,
      "is_owner": false,
      "can_reset_password": true,
      "can_toggle_status": true,
      "created_at": "2026-07-15T10:30:00+03:00"
    },
    "notifications": {
      "password_sms_sent": true,
      "password_email_sent": false
    }
  }
}
```

---

## 8. Toggle status (activate / deactivate)

```
POST /api/v1/employees/{id}/toggle-status
Authorization: Bearer {token}
```

No request body needed. Flips `is_active` and sends SMS/email notification.

### Restrictions

- Cannot deactivate yourself
- Cannot deactivate owner / super_admin accounts

### Success response `200`

```json
{
  "success": true,
  "message": "Jane Doe has been deactivated.",
  "data": {
    "employee": {
      "id": 42,
      "name": "Jane Doe",
      "is_active": false,
      "...": "..."
    },
    "status": "deactivated"
  }
}
```

---

## 9. Reset password

```
POST /api/v1/employees/{id}/reset-password
Authorization: Bearer {token}
```

No request body. Auto-generates a random password, updates the account, and sends it via SMS + email.

### Restrictions

- Cannot reset owner / super_admin — use the edit page instead

### Success response `200`

```json
{
  "success": true,
  "message": "New password generated for Jane Doe. An SMS was sent to the staff phone number.",
  "data": {
    "employee": { "id": 42, "name": "Jane Doe", "...": "..." },
    "generated_password": "Kx7m2P",
    "notifications": {
      "sms_sent": true,
      "email_sent": false
    }
  }
}
```

---

## 10. Delete employee

```
DELETE /api/v1/employees/{id}
Authorization: Bearer {token}
```

No request body.

### Restrictions

- Cannot delete yourself
- Cannot delete owner / super_admin accounts

### Success response `200`

```json
{
  "success": true,
  "message": "Staff member removed.",
  "data": {
    "deleted_employee_id": 42,
    "deleted_employee_name": "Jane Doe"
  }
}
```

---

## Related docs

- Mobile overview: [`API_MOBILE.md`](API_MOBILE.md)
- Categories (import business types before employees): [`API_CATEGORIES.md`](API_CATEGORIES.md)
