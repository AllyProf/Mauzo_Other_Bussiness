# Business Reports API (Mobile)

Same reports as web `/reports/*` (defaults to Circulation vs Profit).

**Permission:** `view_reports`  
**Auth:** `Authorization: Bearer {token}`

| Environment | Base URL |
|-------------|----------|
| Production | `https://www.mauzolink.co.tz/api/v1` |
| Local | `http://192.168.0.124:5000/api/v1` |

---

## Shared query params (all report endpoints)

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `start_date` | `YYYY-MM-DD` | end − 6 days | Range start (clamped to 5–62 days) |
| `end_date` | `YYYY-MM-DD` | today | Range end |
| `business_type` | string | all | Department key from `filters.business_types` |
| `branch_id` | int | tenant branch | Owner only — scope to one branch |

Date range rules match web: **min 5 days**, **max 62 days**.

---

## Envelope shape (every report)

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "report": "circulation-profit",
    "title": "Circulation vs Profit",
    "date_range": {
      "start_date": "2026-07-23",
      "end_date": "2026-07-29",
      "start_date_label": "23 Jul, 2026",
      "end_date_label": "29 Jul, 2026",
      "days": 7
    },
    "filters": {
      "branch_id": 10,
      "branch_name": "Main Branch",
      "viewing_all_branches": false,
      "business_types": [
        { "key": "retail", "label": "Retail", "icon": "fa-store" }
      ],
      "multi_business": false,
      "active_business_type": null,
      "active_business_label": null
    },
    "data": { }
  }
}
```

Use `data.labels` + series arrays for charts; use `data.rows` / list arrays for tables; use `data.summary` for KPI cards.

---

## 0. Reports catalog

```
GET /api/v1/reports
```

```json
{
  "success": true,
  "data": {
    "reports": [
      { "key": "daily-report", "label": "Daily Report", "path": "/reports/daily-report" },
      { "key": "payment-channels", "label": "Payment channels", "path": "/reports/payment-channels" },
      { "key": "circulation-profit", "label": "Circulation vs Profit", "path": "/reports/circulation-profit" },
      { "key": "daily-sales", "label": "Daily Sales", "path": "/reports/daily-sales" },
      { "key": "expenses", "label": "Expense Report", "path": "/reports/expenses" },
      { "key": "profit", "label": "Profit Report", "path": "/reports/profit" },
      { "key": "sales-analytics", "label": "Sales Analytics", "path": "/reports/sales-analytics" },
      { "key": "products", "label": "Product Report", "path": "/reports/products" },
      { "key": "debts", "label": "Debt Report", "path": "/reports/debts" }
    ],
    "date_range": {
      "start_date": "2026-07-23",
      "end_date": "2026-07-29",
      "default_days": 7,
      "min_days": 5,
      "max_days": 62
    },
    "filters": { "branch_id": 10, "business_types": [], "multi_business": false }
  }
}
```

---

## Payment channels (single day)

```
GET /api/v1/reports/payment-channels?report_date=2026-10-08
```

Web: `/reports/payment-channels`

Day-only breakdown of collections by payment method. Same filters as the daily report payment section (`report_date`, optional `business_type`, optional `branch_id` for owners). If this endpoint is unavailable, mobile clients may fall back to `GET /api/v1/reports/daily-report` and read `data.data.sources` for the same day (`day_amount`, `day_orders` as transaction count).

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `report_date` | `YYYY-MM-DD` | today | Day to report on (future dates fall back to today). `date` / `end_date` are also accepted. |
| `business_type` | string | all | Department key from `filters.business_types` |
| `branch_id` | int | tenant branch | Owner only |

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "report": "payment-channels",
    "title": "Payment channels",
    "report_date": "2026-10-08",
    "sources": [
      { "method": "cash", "label": "Cash", "day_amount": 150000, "count": 12 },
      { "method": "mpesa", "label": "M-Pesa", "day_amount": 80000, "count": 5 }
    ]
  }
}
```

---

## Daily Report (day vs month-to-date)

```
GET /api/v1/reports/daily-report?report_date=2026-09-20
```

Web: `/reports/daily-report`

Single-day snapshot compared with the month so far (1st of the month → `report_date`). Uses **`report_date`** instead of `start_date`/`end_date`, and it has **no 5-day minimum**.

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `report_date` | `YYYY-MM-DD` | today | Day to report on (future dates fall back to today). `date` / `end_date` are also accepted. |
| `business_type` | string | all | Department key from `filters.business_types` |
| `branch_id` | int | tenant branch | Owner only |

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "report": "daily-report",
    "title": "Daily Report",
    "report_date": "2026-09-20",
    "date_range": {
      "start_date": "2026-09-01",
      "end_date": "2026-09-20",
      "day_label": "20-Sep",
      "period_label": "01-Sep to 20-Sep"
    },
    "filters": { "branch_id": 10, "business_types": [], "multi_business": false, "active_business_type": null, "active_business_label": null },
    "data": {
      "report_date": "2026-09-20",
      "report_date_label": "20-Sep",
      "period_label": "01-Sep to 20-Sep",
      "period_from": "2026-09-01",
      "period_to": "2026-09-20",
      "currency": "TZS",
      "metrics": [
        { "key": "orders", "label": "Orders", "day": 3, "period": 88, "format": "number" },
        { "key": "gross_sales", "label": "Gross Sales", "day": 6057600, "period": 153048300, "format": "money" },
        { "key": "collected", "label": "Collected", "day": 0, "period": 108468000, "format": "money" },
        { "key": "outstanding", "label": "Outstanding", "day": 6057600, "period": 44580300, "format": "money" },
        { "key": "avg_order", "label": "Avg Order Value", "day": 2019200, "period": 1739185.23, "format": "money" },
        { "key": "gross_profit", "label": "Gross Profit", "day": 399500.04, "period": 9649242.71, "format": "money" }
      ],
      "sources": [
        { "method": "cash", "label": "Cash", "day_orders": 0, "day_amount": 0, "period_orders": 40, "period_amount": 60000000 }
      ],
      "source_totals": { "day_orders": 0, "day_amount": 0, "period_orders": 40, "period_amount": 60000000 },
      "expenses": [
        { "key": "restock", "label": "Restock / Supply", "day": 0, "period": 906877 }
      ],
      "expense_totals": { "day": 0, "period": 906877 },
      "net_cash": { "day": 0, "period": 108468000 },
      "net_profit": { "day": 399500.04, "period": 8742365.71 },
      "circulation": { "day_opening": 0, "period_opening": 0, "closing": 0, "closing_profit": 0 }
    }
  }
}
```

**Mobile layout (matches web):** a two-column table per section, with a **Day** column and a **Period** column:

1. **Summary**: `metrics` (use `format` to show a count or money; match on `key`, not `label`, because labels are translated).
2. **Collections by payment method**: `sources` plus the `source_totals` footer row.
3. **Expenses**: `expenses` plus the `expense_totals` footer row.
4. **Net Cash** (`net_cash`) and **Net Profit** (`net_profit`) as highlighted totals.
5. **Circulation**: `circulation` opening and closing balances.

---

## 1. Circulation vs Profit

```
GET /api/v1/reports/circulation-profit?start_date=2026-07-23&end_date=2026-07-29
```

Web: `/reports/circulation-profit`

### `data` payload

```json
{
  "labels": ["23 Jul", "24 Jul", "25 Jul"],
  "circulation": [500000, 480000, 510000],
  "profit": [120000, 135000, 140000],
  "gross_profit": [45000, 40000, 52000],
  "net_profit": [38000, 35000, 41000],
  "rows": [
    {
      "date": "2026-07-29",
      "date_label": "29 Jul, 2026",
      "opening_circulation": 500000,
      "closing_circulation": 510000,
      "opening_profit": 120000,
      "closing_profit": 140000,
      "gross_profit": 52000,
      "net_profit": 41000,
      "status": "draft"
    }
  ],
  "summary": {
    "current_circulation": 510000,
    "current_profit": 140000,
    "peak_circulation": 510000,
    "peak_profit": 140000,
    "avg_daily_gross_profit": 45666.67,
    "avg_daily_net_profit": 38000
  }
}
```

`rows` are newest-first. `status`: `finalized` | `draft` | `computed`.

---

## 2. Daily Sales

```
GET /api/v1/reports/daily-sales
```

```json
{
  "labels": ["23 Jul", "24 Jul"],
  "gross": [85000, 92000],
  "collected": [70000, 92000],
  "orders": [12, 15],
  "rows": [
    {
      "date": "2026-07-24",
      "date_label": "24 Jul, 2026",
      "orders": 15,
      "gross": 92000,
      "collected": 92000,
      "outstanding": 0
    }
  ],
  "summary": {
    "total_orders": 27,
    "gross_sales": 177000,
    "collected": 162000,
    "avg_order_value": 6555.56
  }
}
```

---

## 3. Expenses

```
GET /api/v1/reports/expenses
```

```json
{
  "labels": ["23 Jul", "24 Jul"],
  "staff": [5000, 0],
  "owner": [15000, 85000],
  "rows": [
    {
      "date": "2026-07-24",
      "date_label": "24 Jul, 2026",
      "staff": 0,
      "owner": 85000,
      "total": 85000
    }
  ],
  "owner_by_category": [
    { "key": "restock", "label": "Restock / Supply", "amount": 85000 }
  ],
  "owner_by_fund": {
    "circulation": 85000,
    "profit": 15000
  },
  "summary": {
    "staff_total": 5000,
    "owner_total": 100000,
    "grand_total": 105000
  },
  "recent_staff": [
    { "id": 3, "description": "Transport", "amount": 5000, "date": "2026-07-23", "date_label": "23 Jul, 2026" }
  ],
  "recent_owner": [
    {
      "id": 12,
      "description": "Restock Coca-Cola",
      "amount": 85000,
      "category": "restock",
      "category_label": "Restock / Supply",
      "fund_source": "circulation",
      "fund_source_label": "Money in Circulation",
      "date": "2026-07-24",
      "date_label": "24 Jul, 2026"
    }
  ],
  "business_type_filtered": false,
  "business_type_note": null
}
```

> Rows with `total == 0` are omitted. Expenses are business-wide (not split by department) — see `business_type_note` when a type filter is active.

---

## 4. Profit

```
GET /api/v1/reports/profit
```

```json
{
  "labels": ["23 Jul", "24 Jul"],
  "gross_sales": [85000, 92000],
  "gross_profit": [22000, 25000],
  "net_profit": [18000, 21000],
  "cogs": [63000, 67000],
  "rows": [
    {
      "date": "2026-07-24",
      "date_label": "24 Jul, 2026",
      "gross_sales": 92000,
      "cost_of_goods": 67000,
      "gross_profit": 25000,
      "net_profit": 21000,
      "margin": 27.2
    }
  ],
  "summary": {
    "gross_sales": 177000,
    "gross_profit": 47000,
    "net_profit": 39000,
    "avg_margin": 26.6
  }
}
```

When `business_type` is set, `net_profit` may be `null` (gross only for that department).

---

## 5. Sales Analytics

```
GET /api/v1/reports/sales-analytics
```

```json
{
  "labels": ["23 Jul", "24 Jul"],
  "orders_trend": [12, 15],
  "gross_trend": [85000, 92000],
  "by_method": [
    { "method": "cash", "label": "Cash", "amount": 90000 },
    { "method": "mobile_money", "label": "Mobile Money", "amount": 72000 }
  ],
  "by_staff": [
    { "name": "Jane Cashier", "orders": 18, "gross": 110000, "collected": 100000 }
  ],
  "by_source": [
    { "source": "pos", "label": "Pos", "orders": 20, "amount": 140000 },
    { "source": "invoice", "label": "Invoice", "orders": 7, "amount": 37000 }
  ],
  "summary": {
    "total_orders": 27,
    "gross_sales": 177000,
    "collected": 162000,
    "avg_order_value": 6555.56,
    "unique_staff": 2,
    "top_payment_method": "Cash"
  }
}
```

---

## 6. Products

```
GET /api/v1/reports/products
```

```json
{
  "top_products": [
    {
      "item_id": 101,
      "name": "Coca-Cola 500ml",
      "category": "Drinks",
      "qty": 120,
      "revenue": 180000,
      "cost": 96000,
      "profit": 84000
    }
  ],
  "products": [ "…all products sorted by revenue…" ],
  "by_category": [
    { "category": "Drinks", "qty": 200, "revenue": 250000, "profit": 110000 }
  ],
  "category_labels": ["Drinks", "Snacks"],
  "category_revenue": [250000, 80000],
  "product_labels": ["Coca-Cola 500ml", "…"],
  "product_revenue": [180000, 45000],
  "summary": {
    "products_sold": 24,
    "units_sold": 410,
    "total_revenue": 330000,
    "total_profit": 145000
  },
  "business_type_filtered": false
}
```

---

## 7. Debts

```
GET /api/v1/reports/debts
```

```json
{
  "aging": {
    "current": { "label": "Not yet due", "amount": 50000, "count": 2 },
    "1_30": { "label": "1–30 days overdue", "amount": 30000, "count": 1 },
    "31_60": { "label": "31–60 days overdue", "amount": 0, "count": 0 },
    "61_90": { "label": "61–90 days overdue", "amount": 0, "count": 0 },
    "90_plus": { "label": "90+ days overdue", "amount": 15000, "count": 1 },
    "no_due_date": { "label": "No due date", "amount": 10000, "count": 1 }
  },
  "aging_labels": ["Not yet due", "1–30 days overdue", "…"],
  "aging_values": [50000, 30000, 0, 0, 15000, 10000],
  "top_debtors": [
    { "name": "Acme Traders", "orders": 3, "balance": 65000 }
  ],
  "customer_summaries": [ "…all debtors…" ],
  "recent_debts": [
    {
      "id": 320,
      "reference_no": "INV-20260729-A1B2",
      "sale_date": "2026-07-29",
      "customer_name": "Acme Traders",
      "customer_phone": "+255712345678",
      "total_amount": 65000,
      "amount_paid": 0,
      "balance_due": 65000,
      "payment_status": "pending",
      "due_date": null
    }
  ],
  "summary": {
    "total_outstanding": 105000,
    "open_accounts": 5,
    "overdue_count": 2,
    "collected_in_period": 40000,
    "new_debt_in_period": 65000
  }
}
```

---

## Mobile UX tips

1. Call `GET /reports` once for the menu + date defaults.
2. Keep shared date pickers + business-type tabs; change report by path only.
3. Charts: use `labels` + series arrays.
4. Tables: use `rows` / `products` / `by_staff` / `customer_summaries`.
5. KPI strip: always from `summary`.
6. Collect debt from debt rows via existing `POST /debts/{sale}/collect` or `POST /sales/{id}/pay`.

---

## Related

- Owner Master Sheet (day finalize / expenses): [`API_OWNER_REPORTS.md`](API_OWNER_REPORTS.md)
- Petty cash: [`API_PETTY_CASH.md`](API_PETTY_CASH.md)
- Mobile overview: [`API_MOBILE.md`](API_MOBILE.md)
