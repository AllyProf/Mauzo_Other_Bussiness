# Live Sales Pulse API (Mobile)

Real-time sales monitor — same as web `/live-sales`.

Shows today’s (or open-shift) KPIs, hourly velocity, product/service mix, live feed, staff leaderboard, and trending items. Mobile should poll this endpoint every few seconds (web polls ~5–10s).

---

| Environment | Base URL |
|-------------|----------|
| Production | `https://www.mauzolink.co.tz/api/v1` |
| Local | `http://192.168.100.106:5000/api/v1` |

```
Authorization: Bearer {token}
Accept: application/json
```

**Permission:** `view_live_sales` **or** `view_reports` **or** `view_sales_history` **or** `process_sales`  
**Plan feature:** `live_sales_pulse`

---

## Endpoint

```
GET /api/v1/live-sales
```

### Query params

| Param | Type | Description |
|-------|------|-------------|
| `business_type` | string | Filter by department key (retail / service type) |
| `branch_id` | int | Owner only — scope to one branch |

### Scope rules (same as web)

| User | What they see |
|------|----------------|
| Staff with open shift required | Their open shift only (or empty until shift opens) |
| Staff without shift requirement | Their sales today (or their open shift if open) |
| Owner / business-wide | Active open shift for branch/business, else all of today’s sales |

---

## Success response `200`

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "filters": {
      "branch_id": 10,
      "branch_name": "Main Branch",
      "viewing_all_branches": false,
      "business_types": [
        { "key": "retail", "label": "Retail", "icon": "fa-store" },
        { "key": "print_copy", "label": "Print & Copy", "icon": "fa-briefcase" }
      ],
      "multi_business": true,
      "active_business_type": null,
      "active_business_label": null
    },
    "pulse": {
      "synced_at": "2026-07-30T09:35:00+03:00",
      "context": {
        "mode": "shift",
        "date": "2026-07-30",
        "scope_label": "Your open shift #44",
        "filter_note": ""
      },
      "shift": {
        "id": 44,
        "user_id": 5,
        "cashier": "Jane Cashier",
        "opened_at": "2026-07-30T08:00:00+03:00",
        "status": "open"
      },
      "kpis": {
        "total_revenue": 285000,
        "cash_revenue": 120000,
        "digital_revenue": 165000,
        "gross_profit": 98000,
        "money_in_circulation": 187000,
        "margin_percent": 34.4,
        "total_orders": 42,
        "active_orders": 3,
        "served_orders": 39
      },
      "hourly_velocity": [
        { "hour": 0, "label": "00:00", "orders": 0 },
        { "hour": 8, "label": "08:00", "orders": 4 },
        { "hour": 9, "label": "09:00", "orders": 11 }
      ],
      "category_mix": {
        "products": 210000,
        "services": 75000
      },
      "live_feed": [
        {
          "id": 901,
          "reference_no": "ORD-20260730-A1B2",
          "sale_source": "pos",
          "channel": "store",
          "payment_status": "paid",
          "payment_method": "cash",
          "total_amount": 15000,
          "amount_paid": 15000,
          "customer_name": null,
          "cashier": "Jane Cashier",
          "items_count": 2,
          "item_summary": ["Coca-Cola 500ml", "Water 1L"],
          "created_at": "2026-07-30T09:32:10+03:00",
          "created_at_label": "2 minutes ago"
        }
      ],
      "staff_pulse": [
        {
          "user_id": 5,
          "name": "Jane Cashier",
          "orders": 18,
          "revenue": 110000
        }
      ],
      "top_products": [
        { "name": "Coca-Cola 500ml", "qty": 48, "revenue": 72000 }
      ],
      "top_services": [
        { "name": "A4 Colour Print", "qty": 30, "revenue": 45000 }
      ]
    }
  }
}
```

---

## Field notes

| Field | Use in UI |
|-------|-----------|
| `context.mode` | `shift` / `day` / `none` — title: “Live shift pulse” vs “Daily sales monitor” |
| `context.scope_label` | Subtitle under the page title |
| `kpis.*` | Four KPI cards (revenue, profit, circulation, orders) |
| `hourly_velocity` | Bar chart (24 hours; hours before shift open are 0) |
| `category_mix` | Doughnut: products vs services |
| `live_feed` | Latest ~30 sales (newest first) |
| `staff_pulse` | Top 8 cashiers by revenue |
| `top_products` / `top_services` | Trending lists (top 5 each) |
| `filters.business_types` | Department tabs when `multi_business` is true |

Open a sale detail with existing `GET /sales/{id}` (or invoices endpoint when `sale_source` is `invoice`).

---

## Polling

Recommended: poll `GET /live-sales` every **5–10 seconds** while the screen is visible. Use `pulse.synced_at` for the “Synced …” label.

Example:

```
GET /api/v1/live-sales?business_type=retail
```

---

## Related

- Sales: [`API_MOBILE.md`](API_MOBILE.md) (Sales section)
- Business reports: [`API_REPORTS.md`](API_REPORTS.md)
- Day closing: [`API_DAY_CLOSING.md`](API_DAY_CLOSING.md)
