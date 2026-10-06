# Notes & Reminders API (Mobile)

Same as web **`/notes`**.

**Permission:** `manage_notes`  
**Auth:** `Authorization: Bearer {token}` · `Accept: application/json`

---

## What this feature does

Personal notes for the logged-in user (each user sees **only their own** notes — same notes on web and mobile). A note can have a **reminder date/time** (`remind_at`); when it is reached the system sends an **SMS reminder** to the user (once per reminder time). Mark a note **done** when finished.

| `status` | `status_label` | Meaning |
|----------|----------------|---------|
| `active` | No reminder | Open note, no reminder |
| `upcoming` | Scheduled | Reminder set in the future |
| `due` | Due now | Reminder time reached, not done |
| `completed` | Completed | Marked done |

---

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/notes` | List (`?filter=active\|completed\|all`, `?q=`, `?page=`) + stats |
| `POST` | `/notes` | Create |
| `GET` | `/notes/{id}` | One note |
| `PUT` | `/notes/{id}` | Update |
| `DELETE` | `/notes/{id}` | Delete |
| `POST` | `/notes/{id}/complete` | Mark done |
| `POST` | `/notes/{id}/reopen` | Undo done |

---

## 1. List

`GET /notes?filter=active&q=supplier&per_page=15`

```json
{
  "stats": { "active": 5, "due": 1, "upcoming": 2 },
  "filter": "active",
  "notes": [
    {
      "id": 4,
      "title": "Supplier",
      "display_title": "Supplier",
      "body": "Call ABC Ltd about the missing carton",
      "remind_at": "2026-10-04T09:00:00+03:00",
      "remind_at_label": "04 Oct 2026, 09:00",
      "is_due": false,
      "is_completed": false,
      "completed_at": null,
      "reminder_sms_sent_at": null,
      "status": "upcoming",
      "status_label": "Scheduled",
      "created_at": "2026-10-02T11:42:00+03:00",
      "updated_at": "2026-10-02T11:42:00+03:00"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 5 }
}
```

`display_title` = title, or the first 60 characters of the body when there is no title.

---

## 2. Create / Update

`POST /notes` · `PUT /notes/{id}`

```json
{
  "title": "Supplier",
  "body": "Call ABC Ltd about the missing carton",
  "remind_at": "2026-10-04 09:00:00"
}
```

| Field | Rule |
|-------|------|
| `title` | optional, max 255 |
| `body` | **required**, max 5000 |
| `remind_at` | optional date-time (`YYYY-MM-DD HH:mm:ss` or ISO 8601). Send `null` to remove the reminder |

- Changing `remind_at` re-arms the SMS reminder.
- If `remind_at` is already in the past, the SMS is sent immediately.
- Response: `note` (`201` on create).

---

## 3. Complete / Reopen / Delete

- `POST /notes/4/complete` → `note.is_completed = true`
- `POST /notes/4/reopen` → back to active
- `DELETE /notes/4` → `{ "success": true, "message": "Note deleted." }`

`403` if the note belongs to another user.
