# Customer Communications API

Send SMS and email to customers — promotions, new products, debt reminders, or general messages.
Same as web `/customer-communications`.

**Base URL:** `http://192.168.100.106:5000/api/v1` (local) · `https://www.mauzolink.co.tz/api/v1` (production)

```
Authorization: Bearer {token}
Accept: application/json
```

**Permissions:** `manage_customer_communications` or `manage_customers`

**Plan:** Requires `customer_communication` plan feature. SMS and/or email must be enabled on the plan.

---

## Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/customer-communications` | Quota, customers, logs, scheduled campaigns |
| POST | `/customer-communications/send` | Send now or schedule |
| DELETE | `/customer-communications/campaigns/{id}` | Cancel a scheduled campaign |

---

## 1. Get communications screen

```
GET /api/v1/customer-communications
```

### Query parameters

| Param | Type | Description |
|-------|------|-------------|
| `page` | `int` | Log history page (20 per page, default 1) |

### Success `200`

```json
{
  "success": true,
  "data": {
    "available": true,
    "quota": {
      "sms": {
        "enabled": true,
        "used": 12,
        "limit": 500,
        "remaining": 488,
        "quota_exhausted": false
      },
      "email": {
        "enabled": true,
        "used": 3,
        "limit": 200,
        "remaining": 197,
        "quota_exhausted": false
      }
    },
    "purposes": [
      { "key": "new_product", "label": "New product announcement" },
      { "key": "promotion", "label": "Promotion / offer" },
      { "key": "debt_reminder", "label": "Debt reminder" },
      { "key": "general", "label": "General message" }
    ],
    "channels": [
      { "key": "sms", "label": "SMS", "quota_exhausted": false },
      { "key": "email", "label": "Email", "quota_exhausted": false }
    ],
    "customers": [
      {
        "id": 5,
        "name": "John Mwangi",
        "phone": "0712345678",
        "phone_display": "0712 345 678",
        "email": "john@email.com",
        "has_phone": true,
        "has_email": true
      }
    ],
    "scheduled_campaigns": [
      {
        "id": 3,
        "purpose": "promotion",
        "purpose_label": "Promotion / offer",
        "channels": ["sms"],
        "channels_label": "SMS",
        "subject": null,
        "message": "20% off this weekend!",
        "customer_ids": [5, 8],
        "recipient_count": 2,
        "status": "scheduled",
        "status_label": "Scheduled",
        "scheduled_at": "2026-07-31T09:00:00+03:00",
        "scheduled_at_label": "Jul 31, 2026 09:00",
        "sent_at": null,
        "created_by": { "id": 2, "name": "Owner" },
        "result_summary": null
      }
    ],
    "logs": [
      {
        "id": 42,
        "channel": "sms",
        "channel_label": "SMS",
        "purpose": "general",
        "purpose_label": "General",
        "status": "sent",
        "status_label": "Sent",
        "message": "Thank you for shopping with us.",
        "recipient_name": "John Mwangi",
        "recipient_contact": "0712345678",
        "phone": "0712345678",
        "recipient_email": null,
        "customer": { "id": 5, "name": "John Mwangi" },
        "sent_by": { "id": 2, "name": "Owner" },
        "campaign_id": null,
        "created_at": "2026-07-30T10:15:00+03:00",
        "created_at_label": "Jul 30, 2026 10:15"
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 3,
      "per_page": 20,
      "total": 42
    }
  }
}
```

### Plan not available `403`

```json
{
  "success": false,
  "message": "Customer communication is not available on your current plan.",
  "errors": {
    "plan": ["Customer communication is not available on your current plan."]
  }
}
```

---

## 2. Send or schedule message

```
POST /api/v1/customer-communications/send
Content-Type: application/json
```

### Body

```json
{
  "purpose": "promotion",
  "message": "20% off all items this weekend!",
  "subject": "Weekend Sale",
  "customer_ids": [5, 8, 12],
  "channels": ["sms", "email"],
  "send_mode": "now",
  "scheduled_at": null
}
```

| Field | Required | Notes |
|-------|----------|-------|
| `purpose` | Yes | `general`, `new_product`, `promotion`, `debt_reminder` |
| `message` | Yes | 5–480 characters. `new_product` auto-prefixes "New arrival: " if message lacks "new" |
| `subject` | If email channel | Required when `channels` includes `email` |
| `customer_ids` | Yes | Active customers in your business |
| `channels` | Yes | At least one of `sms`, `email` (must be enabled and have quota) |
| `send_mode` | Yes | `now` or `scheduled` |
| `scheduled_at` | If scheduled | ISO datetime, must be in the future |

### Send now — success `201`

```json
{
  "success": true,
  "message": "3 message(s) sent, 1 skipped (missing contact for selected channel).",
  "data": {
    "mode": "now",
    "sent": 3,
    "failed": 0,
    "skipped": 1,
    "errors": []
  }
}
```

### Schedule — success `201`

```json
{
  "success": true,
  "message": "Message scheduled for Jul 31, 2026 09:00 via SMS.",
  "data": {
    "mode": "scheduled",
    "campaign": {
      "id": 4,
      "purpose": "promotion",
      "channels": ["sms"],
      "scheduled_at": "2026-07-31T09:00:00+03:00",
      "status": "scheduled",
      "recipient_count": 3
    }
  }
}
```

### Validation `422`

- No valid customers selected
- Channel disabled or quota exhausted
- `subject` missing when email selected
- `scheduled_at` missing or in the past when `send_mode` is `scheduled`
- No messages sent (all failed/skipped)

---

## 3. Cancel scheduled campaign

```
DELETE /api/v1/customer-communications/campaigns/{id}
```

Only campaigns with `status: scheduled` can be cancelled.

### Success `200`

```json
{
  "success": true,
  "message": "Scheduled message cancelled.",
  "data": {
    "campaign": {
      "id": 4,
      "status": "cancelled",
      "status_label": "Cancelled"
    }
  }
}
```

---

## Mobile flow

1. `GET /customer-communications` — load quota, customer picker, history, scheduled list
2. User composes message, picks customers + channels + purpose
3. `POST /customer-communications/send` with `send_mode: now` or `scheduled`
4. To cancel: `DELETE /customer-communications/campaigns/{id}`

---

## Related

- Customer list/register: [`API_CUSTOMERS.md`](API_CUSTOMERS.md) or `GET /customers`
- Mobile overview: [`API_MOBILE.md`](API_MOBILE.md)
