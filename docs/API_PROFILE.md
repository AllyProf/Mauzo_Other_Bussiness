# User Profile API (Mobile)

Same as web `/profile` — view and update the logged-in user’s name, email, phone, language, profile photo, and password.

---

| Environment | Base URL |
|-------------|----------|
| Production | `https://www.mauzolink.co.tz/api/v1` |
| Local | `http://192.168.100.106:5000/api/v1` |

```
Authorization: Bearer {token}
Accept: application/json
```

No special permission — any authenticated active user can manage **their own** profile.

> Not the same as `PUT /settings/profile` (that updates the **business** profile).

---

## Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/profile` | Profile details + form meta |
| PUT | `/profile` | Update profile (JSON) |
| POST | `/profile` | Update profile (multipart — use for photo upload) |
| PUT | `/profile/password` | Change password |
| POST | `/profile/password` | Change password (alias) |

---

## 1. Get profile

```
GET /api/v1/profile
```

### Success `200`

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "profile": {
      "id": 24,
      "name": "SINDATO",
      "email": "hsindato@yahoo.com",
      "phone": "+255712345678",
      "phone_local": "712345678",
      "locale": "en",
      "role": "owner",
      "role_label": "Owner",
      "is_staff": false,
      "profile_image": "profile-images/abc.jpg",
      "profile_image_url": "http://192.168.100.106:5000/storage/profile-images/abc.jpg?v=1710000000",
      "business": { "id": 10, "name": "SINDATO STORE" },
      "branch": { "id": 3, "name": "Main Branch" },
      "member_since": "2026-01-15",
      "member_since_label": "15 Jan, 2026",
      "updated_at": "2026-08-04T11:00:00+03:00"
    },
    "meta": {
      "supported_locales": [
        { "code": "en", "label": "English" },
        { "code": "sw", "label": "Kiswahili" }
      ],
      "min_password_length": 8,
      "phone_hint": "9 digits starting with 6, 7, or 8 (e.g. 712345678)"
    }
  }
}
```

Use `phone_local` in the edit form (prefix `255` in the UI). `profile_image_url` may be `null`.

---

## 2. Update profile

### JSON

```
PUT /api/v1/profile
Content-Type: application/json
```

```json
{
  "name": "SINDATO",
  "email": "hsindato@yahoo.com",
  "phone": "712345678",
  "locale": "sw",
  "remove_profile_image": false
}
```

### Multipart (with photo)

```
POST /api/v1/profile
Content-Type: multipart/form-data
```

| Field | Required | Notes |
|-------|----------|-------|
| `name` | yes | Max 255 |
| `email` | yes | Unique among users |
| `phone` | no | Local 9 digits `6/7/8…`, or `0712…` / `255712…` (normalized) |
| `locale` | no | `en` or `sw` |
| `profile_image` | no | jpeg/jpg/png, max 2 MB |
| `remove_profile_image` | no | `1` / `true` to clear current photo |

Changing email clears email verification on the account (same as web).

### Success `200`

Returns the same shape as **Get profile**, with message `Profile updated successfully.`

### Errors `422`

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "email": ["The email has already been taken."],
    "phone": ["The phone format is invalid."]
  }
}
```

---

## 3. Change password

```
PUT /api/v1/profile/password
Content-Type: application/json
```

```json
{
  "current_password": "optional-old-password",
  "password": "NewSecurePass1",
  "password_confirmation": "NewSecurePass1"
}
```

| Field | Required | Notes |
|-------|----------|-------|
| `password` | yes | Min length from `meta.min_password_length` (platform setting, ≥ 8) |
| `password_confirmation` | yes | Must match `password` |
| `current_password` | no | If sent, must match the current password |

### Success `200`

```json
{
  "success": true,
  "message": "Password updated successfully.",
  "data": {
    "profile": { "...": "same profile object as GET" }
  }
}
```

---

## Mobile screen mapping

| Web `/profile` section | API |
|------------------------|-----|
| Account fields + photo + language | `GET` / `PUT`/`POST` `/profile` |
| Security password | `PUT`/`POST` `/profile/password` |
| Account information (read-only) | `profile.*` from `GET /profile` |

Session locale cookies from web are not used on API — store `profile.locale` on the device and send it on update.
