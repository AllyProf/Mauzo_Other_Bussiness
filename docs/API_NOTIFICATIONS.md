# In-app Notifications API (Mobile)

Poll-based inbox + banners for MauzoLink POS. **No Firebase** — backend stores notification rows; the app polls while logged in.

**Base URL:** `/api/v1`  
**Auth:** `Authorization: Bearer {token}`

---

| Environment | Base URL |
|-------------|----------|
| Production | `https://www.mauzolink.co.tz/api/v1` |
| Local | `http://192.168.100.106:5000/api/v1` |

---

## Endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `/devices` | Register / upsert device after login |
| DELETE | `/devices/{token}` | Unregister on logout |
| GET | `/notifications` | Inbox / poll (`?unread_only=true`) |
| PATCH | `/notifications/{id}/read` | Mark one as read |
| POST | `/notifications/read-all` | Mark all as read |
| GET | `/notifications/preferences` | Category toggles |
| PUT | `/notifications/preferences` | Update toggles |

---

## 1. Register device

```
POST /api/v1/devices
```

```json
{
  "token": "device_uuid_string",
  "platform": "android",
  "user_id": "123",
  "business_id": "45",
  "branch_id": "7",
  "device_name": "android_1719..."
}
```

`user_id` / `business_id` in the body are optional — server uses the authenticated tenant. Upserts by `(user_id, token)` or existing `(business_id, token)`.

### Success `200` / `201`

```json
{
  "success": true,
  "message": "Device registered.",
  "data": {
    "id": 1,
    "token": "device_uuid_string",
    "platform": "android",
    "device_name": "android_1719...",
    "branch_id": 7,
    "updated_at": "2026-07-31T10:00:00+03:00"
  }
}
```

---

## 2. Unregister device

```
DELETE /api/v1/devices/{token}
```

URL-encode the token if needed. Hard-deletes the row for the current user.

```json
{ "success": true, "message": "Device unregistered.", "data": { "unregistered": true } }
```

---

## 3. List notifications

```
GET /api/v1/notifications?page=1&limit=30&unread_only=true
```

| Param | Default | Notes |
|-------|---------|-------|
| `page` | 1 | |
| `limit` | 30 | Max 100 (`per_page` also accepted) |
| `unread_only` | false | `true` for poll banners |

### Success `200`

```json
{
  "success": true,
  "message": "OK",
  "data": [
    {
      "id": 101,
      "type": "stock.low",
      "title": "Low stock",
      "body": "Apple Punch is low (5 Carton)",
      "payload": "stock:low",
      "read_at": null,
      "created_at": "2026-07-31T07:00:00Z",
      "branch_id": 7,
      "business_id": 45
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 30,
    "total": 1,
    "unread_count": 1
  }
}
```

Poll every ~45s with `unread_only=true` while logged in. Show a local banner for new `id`s.

---

## 4. Mark read

```
PATCH /api/v1/notifications/{id}/read
```

```json
{
  "success": true,
  "message": "Marked as read.",
  "data": { "id": 101, "read_at": "2026-07-31T07:05:00+03:00", "...": "..." }
}
```

```
POST /api/v1/notifications/read-all
```

```json
{
  "success": true,
  "message": "All notifications marked as read.",
  "data": { "marked": 4 }
}
```

---

## 5. Preferences

```
GET /api/v1/notifications/preferences
PUT /api/v1/notifications/preferences
```

```json
{
  "sales": true,
  "stock": true,
  "day_closing": true,
  "targets": true,
  "customers": true,
  "system": true
}
```

Defaults are all `true` until the user saves. Creating notifications skips users who disabled that category.

| Category | Type prefixes |
|----------|---------------|
| `sales` | `sales.*` |
| `stock` | `stock.*` |
| `day_closing` | `day_closing.*` |
| `targets` | `targets.*` |
| `customers` | `customers.*` |
| `system` | `auth.*`, `system.*`, `staff.*`, `branch.*`, `reports.*` |

---

## Wired events (MVP)

| type | When | Who |
|------|------|-----|
| `day_closing.handover_submitted` | Cashier submits handover | Owner + managers |
| `day_closing.handover_verified` | Owner verifies | That cashier |
| `day_closing.handover_rejected` | Owner disputes | That cashier |
| `day_closing.cash_variance` | Verified with money short | That cashier |
| `sales.payment_received` | Payment / partial collection | Owner + managers (+ cashier) |
| `stock.low` | After sale deduct, qty ≤ threshold | Owner + managers |
| `stock.out` | After sale deduct, qty = 0 | Owner + managers |

Threshold = business automation `low_stock_threshold` (default 5).

### Payload examples (deep link)

- `day_closing:submitted` / `day_closing:verified` / `day_closing:rejected` / `day_closing:variance`
- `stock:low` / `stock:out`
- `sales:payment`

---

## App flow

1. Login → `POST /devices`
2. Poll `GET /notifications?unread_only=true` ~every 45s → banner for new ids
3. Bell → full inbox `GET /notifications`
4. Tap → `PATCH /notifications/{id}/read`
5. Logout → `DELETE /devices/{token}`

---

## Helper (backend)

```php
app(InAppNotificationService::class)->createNotification(
    userId: $userId,
    businessId: $businessId,
    type: 'stock.low',
    title: 'Low stock',
    body: 'Apple Punch is low (5 Carton)',
    payload: 'stock:low',
    branchId: $branchId,
);
```

---

## Related

- Mobile overview: [`API_MOBILE.md`](API_MOBILE.md)
