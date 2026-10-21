# Subscription & upgrade (mobile API)

Web screen: **`GET /subscription/upgrade`** (browser, authenticated).

Mobile equivalent: **`GET /api/v1/subscription/upgrade`** (Bearer token).

| Environment | Upgrade API |
|-------------|-------------|
| LAN dev server | `http://192.168.100.114:5000/api/v1/subscription/upgrade` |
| XAMPP | `http://localhost/SpareParts/public/api/v1/subscription/upgrade` |
| Production | `https://www.mauzolink.co.tz/api/v1/subscription/upgrade` |

**Auth:** `Authorization: Bearer {token}` (same as login).

**Subscription lock:** Works even when the business is subscription-expired (route is exempt from `api.subscription` block), so the app can show pay/renew UI.

---

## GET `/subscription/upgrade`

Single payload for the **Upgrade plan** screen: current plan, renewal alert, all catalog plans, invoices, and platform payment methods (CRDB, M-Pesa, etc.).

### Example request

```http
GET /api/v1/subscription/upgrade HTTP/1.1
Host: 192.168.100.114:5000
Authorization: Bearer 1|xxxxxxxx
Accept: application/json
```

### Example response `200`

```json
{
  "success": true,
  "message": "Subscription upgrade screen",
  "data": {
    "subscription": {
      "account_status": "Active",
      "is_active": true,
      "expiry_date": "2026-11-20",
      "days_remaining": 30,
      "expiring_soon": false,
      "expiring_soon_days_threshold": 7,
      "grace_until": "2026-11-20",
      "blocked_reason": null,
      "plan": { "name": "Free Plan", "code": "Free Plan" },
      "current_fee": { "amount": 0, "currency": "TZS" },
      "unpaid_invoice": null
    },
    "renewal_notice": {
      "notice_type": "expiring",
      "days_left": 30,
      "expiry_date": "2026-11-20",
      "plan_name": "Free Plan",
      "is_free_renewal": true,
      "renewal_amount": 0
    },
    "current_plan": {
      "id": 4,
      "name": "Free Plan",
      "is_current": true,
      "is_free_plan": true,
      "billing_summary": "TZS 0 / 1 mo",
      "expiry_date": "2026-11-20",
      "account_status": "Active",
      "amount_due": {
        "monthly": 0,
        "renewal": 0,
        "monthly_label": "Fixed monthly fee",
        "renewal_label": "Renewal fee"
      },
      "limits": {
        "max_items": 10000,
        "max_items_label": "10,000",
        "max_users": 100,
        "max_users_label": "100",
        "storage_label": "Unlimited"
      },
      "latest_invoice": null,
      "disabled_features": [],
      "all_modules_enabled": true
    },
    "available_plans": [
      {
        "id": 4,
        "name": "Free Plan",
        "is_current": true,
        "price": 0,
        "duration_months": 1,
        "billing_summary": "TZS 0 / 1 mo",
        "modules_summary": "Customers Management, Debt Management, …",
        "modules": [{ "key": "customers", "label": "Customers Management" }],
        "limits": {
          "max_items": 10000,
          "max_users": 100,
          "storage_label": "Unlimited"
        }
      }
    ],
    "invoices": [],
    "payment_options": {
      "payment_intro": "The payment can be made through any of the following details:",
      "payment_instructions": "",
      "payment_methods": [
        {
          "provider": "CRDB BANK",
          "account_name": "EMCATECHONOLOGIESLTD",
          "number_label": "A/C no.",
          "number": "015C0007UXJ00"
        }
      ],
      "support_phone": "+255 749 719 998",
      "support_whatsapp": "",
      "support_email": "emca@emca.tech"
    },
    "meta": {
      "currency": "TZS",
      "upgrade_managed_by": "platform_admin",
      "contact_after_payment": true
    }
  }
}
```

### `renewal_notice.notice_type`

| Value | Meaning |
|-------|---------|
| `expiring` | Plan ends within warning window — show renew banner |
| `expired` | Past expiry — urge renewal / contact admin |
| `repayment` | Unpaid platform invoice — use `unpaid_invoice` + payment_options |

Plan changes are **not** self-service in the app; user pays using `payment_options`, then contacts support (same as web).

---

## Related endpoints

| Method | Endpoint | Use |
|--------|----------|-----|
| GET | `/subscription/status` | Lightweight poll (active, expiry, blocked) |
| GET | `/subscription/payment-options` | Payment methods only |
| GET | `/subscription/invoices` | Invoice list |
| GET | `/subscription/invoices/{id}` | One invoice + payment options |
| GET | `/subscription/invoices/{id}/pdf` | Invoice PDF (Bearer token; open in WebView or download) |

PDF example: `GET /api/v1/subscription/invoices/12/pdf?download=1`

---

## Auth `/auth/me`

Login and `GET /auth/me` also include a compact `subscription` object (see main mobile API doc). Use **`GET /subscription/upgrade`** for the full upgrade screen.
