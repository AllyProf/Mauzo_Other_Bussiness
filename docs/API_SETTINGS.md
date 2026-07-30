# Business Settings API (Mobile)

Same as web `/settings` — profile, finance, payment methods, automation, shift rules, and subscription overview.

---

| Environment | Base URL |
|-------------|----------|
| Production | `https://www.mauzolink.co.tz/api/v1` |
| Local | `http://192.168.100.106:5000/api/v1` |

```
Authorization: Bearer {token}
Accept: application/json
```

**Permissions**

| Section | Ability |
|---------|---------|
| Read all tabs | `manage_business_settings` **or** `manage_payment_methods` |
| Profile / Finance / Automation / Shifts | `manage_business_settings` |
| Payment methods | `manage_payment_methods` **or** `manage_business_settings` |

Subscription tab is **read-only** (plan/invoices — managed by platform admin).

---

## Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/settings` | Full settings snapshot (all tabs) |
| PUT | `/settings/profile` | Business profile + logo |
| PUT | `/settings/finance` | Circulation / expense defaults |
| PUT | `/settings/automation` | Notifications & SMS/email automation |
| PUT | `/settings/shift-rules` | When staff can open shifts |
| PUT | `/settings/payment-methods` | Enabled methods & pay numbers |

---

## 1. Get settings

```
GET /api/v1/settings
```

### Success `200` (abbreviated)

```json
{
  "success": true,
  "data": {
    "profile": {
      "name": "Boma Retail",
      "email": "shop@example.com",
      "phone": "+255712345678",
      "address": "Sokoine Road",
      "tin_number": "123-456-789",
      "contact_person": "John Owner",
      "vat_number": null,
      "vat_rate": 18,
      "invoice_show_vat": true,
      "invoice_vat_inclusive": false,
      "logo_url": "http://192.168.100.106:5000/storage/business-logos/abc.png",
      "has_logo": true
    },
    "finance": {
      "expense_deduct_from": "circulation",
      "circulation_balance": 500000
    },
    "payment_methods": [
      {
        "key": "cash",
        "label": "Cash",
        "enabled": true,
        "type": "immediate",
        "requires_reference": false,
        "accounts": []
      },
      {
        "key": "mobile_money",
        "label": "Mobile Money",
        "enabled": true,
        "type": "immediate",
        "requires_reference": true,
        "accounts": [
          { "name": "M-Pesa", "pay_number": "255712000000", "account_name": "Boma Retail" }
        ]
      }
    ],
    "automation": {
      "enabled": true,
      "settings": {
        "notify_debt_overdue": true,
        "notify_low_stock": true,
        "low_stock_threshold": 5,
        "debt_due_reminder_days": 3,
        "default_debt_due_days": 30
      },
      "sms_templates": { "sms_staff_welcome_template": "..." }
    },
    "shift_rules": {
      "shift_open_mode": "anytime",
      "shift_open_time_from": "06:00",
      "shift_open_time_to": "22:00",
      "shift_open_days": [0, 1, 2, 3, 4, 5, 6],
      "shift_max_open_duration": 1,
      "shift_max_open_unit": "days",
      "shift_enforce_max_duration": true
    },
    "subscription": {
      "account_status": "Active",
      "is_active": true,
      "plan": { "id": 2, "name": "Pro", "max_users": 10, "max_branches": 5 },
      "current_fee": { "amount": 50000, "model": "fixed_monthly" },
      "renewal_fee": { "amount": 50000, "model": "fixed_monthly" },
      "limits": {
        "staff_limit": 10,
        "business_types_limit": 3,
        "business_types_used": 2,
        "branch_limit": "5",
        "branches_registered": 2
      },
      "expiry_date": "2026-12-31",
      "invoices": [
        {
          "id": 15,
          "invoice_number": "PLT-202607-001",
          "billing_month": "2026-07-01",
          "billing_month_label": "Jul 2026",
          "amount": 50000,
          "status": "pending",
          "paid_at": null
        }
      ]
    },
    "meta": {
      "plan_features": { "automation_reminders": true },
      "sms_template_defaults": { "...": "..." }
    }
  }
}
```

---

## 2. Update profile

```
PUT /api/v1/settings/profile
Content-Type: application/json
```

Or `multipart/form-data` when uploading a logo.

### JSON body

```json
{
  "name": "Boma Retail",
  "email": "shop@example.com",
  "phone": "+255712345678",
  "address": "Sokoine Road",
  "tin_number": "123-456-789",
  "contact_person": "John Owner",
  "vat_number": "VAT-001",
  "vat_rate": 18,
  "invoice_show_vat": true,
  "invoice_vat_inclusive": false,
  "remove_logo": false
}
```

| Field | Required | Notes |
|-------|----------|-------|
| `name`, `email` | Yes | |
| `logo` | No | File upload (multipart) — jpeg/png/webp, max 2MB |
| `remove_logo` | No | `true` to delete current logo |

### Success `200`

```json
{
  "success": true,
  "message": "Business profile updated successfully.",
  "data": {
    "profile": { "name": "Boma Retail", "logo_url": "...", "...": "..." }
  }
}
```

---

## 3. Update finance

```
PUT /api/v1/settings/finance
```

```json
{
  "expense_deduct_from": "circulation",
  "circulation_balance": 500000
}
```

| Field | Values |
|-------|--------|
| `expense_deduct_from` | `circulation` or `profit` |
| `circulation_balance` | Opening circulation amount (optional) |

---

## 4. Update payment methods

```
PUT /api/v1/settings/payment-methods
```

```json
{
  "methods": [
    {
      "key": "cash",
      "label": "Cash",
      "enabled": true,
      "accounts": []
    },
    {
      "key": "mobile_money",
      "label": "Mobile Money",
      "enabled": true,
      "accounts": [
        {
          "name": "M-Pesa",
          "pay_number": "255712000000",
          "account_name": "Boma Retail"
        }
      ]
    },
    {
      "key": "bank",
      "label": "Bank Transfer",
      "enabled": true,
      "accounts": [
        { "name": "CRDB", "pay_number": "0150123456789", "account_name": "Boma Retail" }
      ]
    },
    {
      "key": "debt",
      "label": "Pay Later (Credit)",
      "enabled": true,
      "accounts": []
    }
  ]
}
```

At least **one** method must be `enabled: true`. Valid keys: `cash`, `mobile_money`, `bank`, `debt`.

---

## 5. Update automation

```
PUT /api/v1/settings/automation
```

Requires plan feature `automation_reminders`.

Send boolean toggles + timing fields + all SMS template keys from `meta.sms_template_defaults`.

Example (partial):

```json
{
  "notify_debt_overdue": true,
  "notify_debt_due_soon": true,
  "notify_low_stock": true,
  "low_stock_threshold": 5,
  "debt_due_reminder_days": 3,
  "debt_due_reminder_days_second": 1,
  "debt_reminder_frequency": "once",
  "debt_reminder_send_time": "08:00",
  "default_debt_due_days": 30,
  "sms_report_send_time": "18:00",
  "sms_weekly_report_day": 1,
  "email_sales_report_enabled": false,
  "email_sales_report_send_time": "18:00",
  "email_sales_report_weekly_day": 1,
  "email_sales_report_monthly_day": 1,
  "email_sales_report_recipients": "owner@example.com, manager@example.com",
  "sms_staff_welcome_template": "Welcome to {business}..."
}
```

Include every key from `GET /settings` → `meta.sms_template_defaults`.

---

## 6. Update shift rules

```
PUT /api/v1/settings/shift-rules
```

```json
{
  "shift_open_mode": "scheduled",
  "shift_open_time_from": "06:00",
  "shift_open_time_to": "22:00",
  "shift_open_days": [1, 2, 3, 4, 5],
  "shift_max_open_duration": 1,
  "shift_max_open_unit": "days",
  "shift_enforce_max_duration": true
}
```

| Field | Notes |
|-------|-------|
| `shift_open_mode` | `anytime` or `scheduled` |
| `shift_open_days` | Required when scheduled — `0`=Sun … `6`=Sat |
| `shift_max_open_unit` | `days` or `weeks` |

---

## Mobile tabs (matches web)

1. **Profile** — `PUT /settings/profile`
2. **Finance** — `PUT /settings/finance`
3. **Payments** — `PUT /settings/payment-methods`
4. **Automation** — `PUT /settings/automation`
5. **Sales Shifts** — `PUT /settings/shift-rules`
6. **Subscription** — read from `GET /settings` → `subscription` (no update API)

---

## Related

- Payment methods reference (POS): `GET /payment-methods`
- Mobile overview: [`API_MOBILE.md`](API_MOBILE.md)
