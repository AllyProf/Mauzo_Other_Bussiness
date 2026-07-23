# NBC Bank Payment API Integration Proposal

**Prepared for:** National Bank of Commerce (NBC) — Merchant / API Partnership Team  
**Prepared by:** EmCa Technologies / Mauzo POS  
**Document type:** Technical Integration Proposal  
**Version:** 3.1  
**Date:** 17 July 2026  
**Status:** Draft for discussion  

| Document control | Detail |
|------------------|--------|
| Company | EmCa Technologies |
| Product | Mauzo POS |
| Subject | Merchant collection API integration via control number |
| Classification | Confidential — for NBC partnership discussion |

---

## 1. Overview of the System

### 1.1 What Mauzo POS is

Mauzo POS is a cloud-based point-of-sale and business operations platform used by shops in Tanzania, including:

- Retail and wholesale stores
- Liquor stores / bars
- Print & copy and other service businesses
- Multi-branch businesses with staff shifts and day closing

The platform already handles:

| Area | Current capability |
|------|--------------------|
| Sales | Counter POS orders, invoices, credit sales |
| Payments | Cash, mobile money, bank transfer (manual), pay later |
| Staff operations | Shifts, handover, day closing |
| Finance | Master sheet, petty cash, profit/circulation tracking |
| Documents | Receipts and printable invoices |

### 1.2 Current bank payment limitation

Today, when a customer pays by bank:

1. Cashier selects **Bank Transfer**
2. Chooses bank provider (including NBC)
3. Types a transaction reference manually
4. Marks the sale as paid

This works, but it has gaps:

- No automatic proof that money reached the merchant account
- Wrong or fake references can be entered
- Matching bank statement to POS sales is slow
- Day closing depends on cashier honesty/accuracy

### 1.3 What this integration solves

With NBC API integration, Mauzo will:

1. Create a payment request for the exact amount due
2. Receive a **control number** from NBC
3. Show/print that control number to the customer
4. Wait for NBC confirmation
5. Automatically update the sale/invoice when payment succeeds
6. Store control number + NBC transaction ID for audit and reconciliation

### 1.4 Where integration is used inside Mauzo

| Mauzo screen / process | How NBC payment is used |
|------------------------|-------------------------|
| POS checkout (`/sales/create`) | Customer pays order at counter via NBC control number |
| Sales list payment modal (`/sales`) | Collect unpaid / partial orders |
| Invoice page (`/invoices/{id}`) | Pay invoice balance; print control number on invoice |
| Service POS / service invoices | Same payment flow for service departments |
| Split payment | Part cash/mobile + part NBC |
| Partial payment / credit | Pay some now via NBC, remainder later |
| Day closing / handover | Only NBC-confirmed amounts count as bank collected |
| Reports / master sheet | Bank collections appear with valid references |

### 1.5 System actors

| Actor | Role |
|-------|------|
| Cashier / staff | Starts payment in Mauzo, shows control number to customer |
| Customer | Pays using control number in NBC app / USSD / supported channel |
| Mauzo POS server | Calls NBC APIs, stores payment state, updates sales |
| NBC API gateway | Issues control number, accepts payment, sends confirmation |
| Shop owner | Reviews handover, bank totals, settlement matching |

### 1.6 Responsibility split

| Data / action | Owner |
|---------------|-------|
| Sale / invoice number (`ORD-...`) | Mauzo |
| Internal payment attempt ID (`merchant_reference`) | Mauzo |
| Control number (namba ya malipo) | **NBC (preferred)** |
| Accepting customer funds | NBC |
| Payment success/failure result | NBC |
| Updating sale, receipt, handover, reports | Mauzo |

---

## 2. APIs Needed from NBC

Mauzo needs sandbox and production access to the APIs below.  
Exact endpoint names may differ; this section describes the **capabilities required**.

### 2.1 Authentication API

**Purpose:** Allow Mauzo server to call NBC APIs securely.

**Needed:**
- Client credentials / API key + secret / OAuth2 token (as per NBC standard)
- Token expiry and refresh rules
- Separate **sandbox** and **production** credentials
- Optional IP whitelist or mTLS if required by NBC

**Mauzo will:**
- Store secrets in server environment variables (not in frontend)
- Attach auth headers on every NBC request
- Rotate credentials according to NBC policy

---

### 2.2 Initiate Payment / Collection API

**Purpose:** Create a pending payment for a sale/invoice amount and receive a control number.

**When Mauzo calls it:**
- Cashier selects **Bank → NBC**
- Amount can be full balance, partial amount, or NBC portion of a split payment

#### Request fields (minimum)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `merchantId` | string | Yes | NBC merchant / collection account identifier |
| `amount` | number | Yes | Amount in TZS (whole shillings preferred) |
| `currency` | string | Yes | Always `TZS` |
| `merchantReference` | string | Yes | Unique Mauzo payment-attempt ID (idempotent key) |
| `description` | string | Yes | Human-readable order/invoice ref, e.g. `ORD-20260714-ABF7` |
| `customerMsisdn` | string | Conditional | Customer phone if NBC requires push/USSD |
| `callbackUrl` | string | Yes | Mauzo webhook URL for final status |
| `expiresInSeconds` | number | Preferred | Validity window for control number |
| `metadata` | object | Optional | Branch ID, sale ID, cashier ID (if NBC allows) |

#### Expected response fields

| Field | Type | Description |
|-------|------|-------------|
| `transactionId` | string | NBC-side transaction / session ID |
| `controlNumber` | string | **Control number customer must pay** |
| `merchantReference` | string | Echo of Mauzo reference |
| `amount` | number | Confirmed amount |
| `currency` | string | `TZS` |
| `status` | string | Usually `PENDING` |
| `expiresAt` | datetime | When control number expires |
| `paymentInstructions` | string/object | Optional USSD text, deep link, QR payload |

#### Example request (illustrative)

```json
{
  "merchantId": "NBC_MERCHANT_001",
  "amount": 849000,
  "currency": "TZS",
  "merchantReference": "MAUZO-SALE-187-PAY-9001",
  "description": "ORD-20260714-ABF7",
  "customerMsisdn": "2557XXXXXXXX",
  "callbackUrl": "https://{mauzo-domain}/api/webhooks/nbc/payments",
  "expiresInSeconds": 900
}
```

#### Example response (illustrative)

```json
{
  "transactionId": "NBC202607171234567",
  "controlNumber": "991234567890",
  "merchantReference": "MAUZO-SALE-187-PAY-9001",
  "amount": 849000,
  "currency": "TZS",
  "status": "PENDING",
  "expiresAt": "2026-07-17T14:20:00+03:00"
}
```

---

### 2.3 Payment Status Query API

**Purpose:** Check current status when webhook is delayed or cashier clicks “Refresh status”.

**Query by any of:**
- `controlNumber`
- `transactionId`
- `merchantReference`

**Expected response:**
- Same core fields as webhook (status, amount, timestamps, IDs)

**Mauzo uses this for:**
- Timeout recovery
- Support/debug
- End-of-day unmatched pending payments

---

### 2.4 Webhook / Callback API (NBC → Mauzo)

**Purpose:** Push final payment result to Mauzo automatically.

**Mauzo endpoint (illustrative):**
```text
POST https://{mauzo-domain}/api/webhooks/nbc/payments
```

#### Expected webhook payload

| Field | Description |
|-------|-------------|
| `transactionId` | NBC transaction ID |
| `controlNumber` | Control number that was paid |
| `merchantReference` | Mauzo payment attempt ID |
| `amount` | Amount paid |
| `currency` | `TZS` |
| `status` | `SUCCESS` / `FAILED` / `CANCELLED` / `EXPIRED` |
| `paidAt` | Payment completion time (on success) |
| `payerInfo` | Optional masked payer identity |
| `signature` | HMAC/signature for authenticity |

#### Example webhook (illustrative)

```json
{
  "transactionId": "NBC202607171234567",
  "controlNumber": "991234567890",
  "merchantReference": "MAUZO-SALE-187-PAY-9001",
  "amount": 849000,
  "currency": "TZS",
  "status": "SUCCESS",
  "paidAt": "2026-07-17T14:05:12+03:00",
  "signature": "..."
}
```

#### Webhook technical expectations from NBC
- HTTPS POST
- Retry on non-2xx response (with backoff)
- Clear signature/verification method
- Idempotent delivery support (same event may arrive more than once)

#### Mauzo webhook handling
1. Verify signature
2. Find payment attempt by `merchantReference` / `controlNumber`
3. Ignore duplicates if already processed
4. Update payment + sale status
5. Return HTTP 200 quickly

---

### 2.5 Cancel / Expire Control Number API (preferred)

**Purpose:** Close an unused payment request.

**When used:**
- Cashier cancels before customer pays
- Customer switches to cash
- Control number should not remain payable

If NBC auto-expires control numbers, document the expiry rule clearly.

---

### 2.6 Settlement / Reconciliation API or file (preferred)

**Purpose:** Match bank settlement to Mauzo payments.

Needed daily data points:
- Control number
- NBC transaction ID
- Merchant reference
- Amount
- Status
- Settlement date/time

This allows finance teams to compare NBC statement vs Mauzo bank collections.

---

### 2.7 API summary matrix

| API | Direction | Required | Used for |
|-----|-----------|----------|----------|
| Authentication | Mauzo → NBC | Yes | Secure access |
| Initiate payment | Mauzo → NBC | Yes | Create control number |
| Status query | Mauzo → NBC | Yes | Poll / recovery |
| Webhook callback | NBC → Mauzo | Yes | Auto confirm payment |
| Cancel/expire | Mauzo → NBC | Preferred | Close abandoned requests |
| Settlement report | NBC → Mauzo/merchant | Preferred | Reconciliation |

---

## 3. How It Will Work

### 3.1 High-level architecture

```text
┌──────────────────┐         HTTPS API          ┌──────────────────┐
│                  │  1. Initiate payment       │                  │
│   Mauzo POS      │ ─────────────────────────► │   NBC API        │
│   (Cloud App)    │ ◄───────────────────────── │   Gateway        │
│                  │  2. controlNumber+txnId    │                  │
└────────┬─────────┘                            └────────┬─────────┘
         │                                               │
         │ 3. Show control number                        │ 4. Customer pays
         ▼                                               ▼
┌──────────────────┐                            ┌──────────────────┐
│ Cashier/Customer │                            │ Customer channel │
│ POS screen /     │                            │ NBC App / USSD / │
│ printed invoice  │                            │ other NBC rail   │
└──────────────────┘                            └────────┬─────────┘
                                                         │
                         5. Webhook SUCCESS/FAIL         │
┌──────────────────┐ ◄───────────────────────────────────┘
│ Mauzo updates    │
│ sale + stores    │
│ references       │
└──────────────────┘
```

### 3.2 Detailed sequence flow

```text
Cashier                Mauzo POS                 NBC API                 Customer
   |                       |                        |                       |
   |  Select Bank→NBC      |                        |                       |
   |---------------------->|                        |                       |
   |                       |  Initiate Payment      |                       |
   |                       |----------------------->|                       |
   |                       |  controlNumber PENDING |                       |
   |                       |<-----------------------|                       |
   |  Show control number  |                        |                       |
   |<----------------------|                        |                       |
   |  Tell customer number |                        |                       |
   |---------------------------------------------------------------------->|
   |                       |                        |   Pay control number  |
   |                       |                        |<----------------------|
   |                       |                        |  Debit / accept funds |
   |                       |  Webhook SUCCESS       |                       |
   |                       |<-----------------------|                       |
   |  Sale marked Paid     |                        |                       |
   |<----------------------|                        |                       |
   |  Print receipt        |                        |                       |
```

### 3.3 Step-by-step business process

1. **Create sale in Mauzo**  
   Cart total and balance due are calculated.

2. **Choose NBC payment**  
   Cashier selects Bank → NBC and enters amount (full, partial, or split portion).

3. **Mauzo creates internal payment attempt**  
   Status = `created`, unique `merchantReference` generated.

4. **Mauzo calls NBC Initiate API**  
   Sends amount, merchant reference, callback URL, optional customer phone.

5. **NBC returns control number**  
   Mauzo stores `controlNumber`, `transactionId`, `expiresAt`.  
   Internal status becomes `pending_nbc`.

6. **Customer pays**  
   Uses NBC app/USSD/other supported channel with the control number.

7. **NBC confirms result**  
   Via webhook (primary) or status query (fallback).

8. **Mauzo finalizes**
   - On `SUCCESS`: create `SalePayment` (method=bank, provider=NBC), update sale paid/partial
   - On `FAILED` / `CANCELLED` / `EXPIRED`: keep unpaid, allow retry with new attempt

9. **Downstream updates**
   - Receipt/invoice shows control number + NBC txn ID
   - Handover bank totals include only successful payments
   - Reports/master sheet use confirmed amounts only

### 3.4 Payment status mapping

| NBC status | Mauzo payment attempt | Sale impact |
|------------|------------------------|-------------|
| `PENDING` | `pending_nbc` | Unchanged; waiting |
| `SUCCESS` | `succeeded` | Add payment; paid or partial |
| `FAILED` | `failed` | No payment posted |
| `CANCELLED` | `cancelled` | No payment posted |
| `EXPIRED` | `expired` | No payment posted; new control number needed |

### 3.5 Core business rules

1. **Never mark paid before NBC SUCCESS.**
2. **One active control number per payment attempt.**
3. **Duplicate webhooks must not double-post payment.**
4. **Amount in webhook must match initiated amount** (or follow NBC partial-pay rules if supported).
5. **Handover counts only succeeded NBC payments.**
6. **Pending/failed/expired control numbers are not bank collections.**
7. **Manual bank entry remains available as fallback** if API is down.

### 3.6 Functional scenarios

#### Scenario A — Full POS checkout
- Balance due: TZS 849,000  
- Initiate NBC for 849,000  
- Customer pays control number  
- Sale → **Paid**

#### Scenario B — Partial payment
- Balance due: TZS 1,000,000  
- Initiate NBC for 400,000  
- Success → sale → **Partial**, remaining 600,000 still due

#### Scenario C — Split payment
- Balance due: TZS 150,000  
- Cash 50,000 recorded manually  
- NBC control number for 100,000  
- After NBC success → sale → **Paid**

#### Scenario D — Pay outstanding invoice later
- Old unpaid invoice opened  
- New NBC payment attempt + new control number  
- On success, invoice updates; later handover sees debt collection

#### Scenario E — Expired control number
- Customer does not pay in time  
- Status → `EXPIRED`  
- Cashier requests new control number (new attempt)

#### Scenario F — Day closing
- Only payments with NBC `SUCCESS` appear under bank collected  
- Unpaid orders remain outstanding  
- No pending control numbers inflate cash-up

### 3.7 Timeout and recovery flow

```text
Payment attempt PENDING
        │
        ├─ Webhook arrives quickly ──────────────► Finalize
        │
        ├─ No webhook after X minutes
        │     Mauzo polls Status Query API
        │        ├─ SUCCESS ─────────────────────► Finalize
        │        ├─ PENDING ─────────────────────► Keep waiting / refresh UI
        │        └─ FAILED/EXPIRED ──────────────► Close attempt, allow retry
        │
        └─ Cashier cancels
              Call Cancel API (if available)
              Close attempt, allow cash/mobile fallback
```

---

## 4. Control Number Lifecycle

### 4.1 What a control number is

A **control number (namba ya malipo)** is the customer-facing payment reference used to pay a specific Mauzo amount through NBC channels.

It links:
- Customer payment at NBC  
- Mauzo sale/invoice payment attempt  
- Later settlement/reconciliation

### 4.2 Preferred generation model

**NBC generates the control number** after Mauzo initiates payment.

Why preferred:
- NBC controls numbering format and uniqueness
- Expiry and validation stay on bank side
- Lower risk of merchant-side collisions

Alternative (only if NBC requires it):
- Mauzo generates a bill reference
- Mauzo registers it to NBC before customer pays

### 4.3 Full lifecycle diagram

```text
[0] Sale exists in Mauzo
          │
          ▼
[1] CREATED (Mauzo side)
    Cashier starts NBC payment
    Mauzo creates payment_attempt
    merchantReference assigned
          │
          ▼
[2] REQUESTED
    Mauzo calls NBC Initiate API
          │
          ▼
[3] PENDING / ISSUED
    NBC returns controlNumber
    Mauzo displays/prints control number
    Customer can now pay
          │
          ├──────────────────────┬──────────────────────┐
          ▼                      ▼                      ▼
[4A] SUCCEEDED            [4B] FAILED/CANCELLED   [4C] EXPIRED
 NBC confirms paid         Payment not completed   Validity ended
 Mauzo posts payment       No money recorded       No money recorded
 Sale paid/partial         Retry allowed           New control number
 Lifecycle CLOSED          Lifecycle CLOSED        Lifecycle CLOSED
```

### 4.4 Lifecycle states (technical)

| State | Meaning | Who sets it | Customer can pay? |
|-------|---------|-------------|-------------------|
| `created` | Mauzo local attempt created | Mauzo | No |
| `pending_nbc` | Control number issued, waiting | Mauzo after NBC response | Yes |
| `succeeded` | Funds confirmed | Mauzo after NBC SUCCESS | No (already paid) |
| `failed` | Payment failed | Mauzo after NBC FAILED | No |
| `cancelled` | Cancelled before pay | Mauzo/NBC | No |
| `expired` | Timed out unpaid | Mauzo/NBC | No |

### 4.5 Data stored by Mauzo for each attempt

| Field | Source | Purpose |
|-------|--------|---------|
| `sale_id` / invoice id | Mauzo | Link to order |
| `merchant_reference` | Mauzo | Idempotency key |
| `control_number` | NBC | Customer pay reference |
| `nbc_transaction_id` | NBC | Bank transaction id |
| `amount` | Mauzo/NBC | Expected amount |
| `status` | Mauzo | Lifecycle state |
| `expires_at` | NBC | UI countdown / expiry handling |
| `paid_at` | NBC | Success timestamp |
| `raw_webhook_payload` (secured) | NBC | Audit/debug |

### 4.6 Lifecycle rules

1. **One active pending control number per payment attempt.**
2. A sale may have **many attempts over time** (retry, partial remaining balance).
3. Expired/failed attempts remain in history for audit but do not collect money.
4. Success webhook for an already-succeeded attempt is ignored (idempotent).
5. Receipt/invoice should print:
   - Order reference
   - Control number
   - NBC transaction ID (after success)
6. Day closing ignores non-success states.

### 4.7 Who does what in the lifecycle

| Step | Mauzo | NBC | Customer |
|------|-------|-----|----------|
| Create order | Yes | | |
| Start payment attempt | Yes | | |
| Generate control number | | **Yes (preferred)** | |
| Display control number | Yes | | |
| Pay control number | | Receives payment | **Yes** |
| Confirm result | Receives webhook | **Yes** | |
| Update sale/handover/reports | Yes | | |

### 4.8 Example timeline

| Time | Event |
|------|-------|
| 14:00:00 | Sale `ORD-20260714-ABF7` balance = TZS 849,000 |
| 14:00:05 | Cashier selects NBC payment |
| 14:00:06 | Mauzo sends initiate request (`MAUZO-SALE-187-PAY-9001`) |
| 14:00:07 | NBC returns control number `991234567890` (expires 14:15:00) |
| 14:00:08 | Control number shown on POS + optional print |
| 14:03:40 | Customer pays via NBC app |
| 14:03:42 | NBC webhook SUCCESS |
| 14:03:43 | Mauzo marks sale paid, stores both references |
| 14:03:50 | Receipt printed with control number + NBC txn ID |

---

## 5. Technical Design Notes (Mauzo Side)

### 5.1 Platform
- Application: Laravel (PHP), cloud hosted
- Frontend: existing POS/invoice payment UI
- New backend services for NBC initiate, webhook, status polling

### 5.2 Security
- TLS 1.2+ for all NBC calls and webhooks
- Secrets only on server
- Webhook signature verification mandatory
- Mask phone numbers in logs
- No storage of full card PAN unless NBC card product requires PCI scope (not assumed for control-number collections)

### 5.3 Reliability
- Idempotent `merchantReference`
- Webhook deduplication
- Status polling fallback
- Manual cash/mobile fallback if NBC unavailable
- Clear cashier UI states: waiting, success, failed, expired

### 5.4 Posting into existing Mauzo payment model
Successful NBC payment will create a normal sale payment record:

| Mauzo field | Value |
|-------------|-------|
| `payment_method` | `bank` |
| `payment_provider` | `NBC` |
| `transaction_reference` | Control number and/or NBC transaction ID |
| `amount` | Confirmed amount |
| `user_id` | Cashier who initiated |

This keeps handover, reports, and master sheet compatible with current logic.

### 5.5 Suggested Mauzo internal endpoints

| Endpoint | Purpose |
|----------|---------|
| `POST /api/payments/nbc/initiate` | Start payment for a sale |
| `GET /api/payments/nbc/{attempt}/status` | Refresh status |
| `POST /api/payments/nbc/{attempt}/cancel` | Cancel pending attempt |
| `POST /api/webhooks/nbc/payments` | NBC callback receiver |

---

## 6. Confirmation Needed from NBC

Please confirm and provide:

1. Official API documentation (sandbox + production)
2. Authentication method and credential process
3. Whether **NBC generates control numbers** (preferred)
4. Control number format, length, validity/expiry rules
5. Supported customer payment channels (app, USSD, etc.)
6. Webhook signature method and retry policy
7. Status query API details
8. Cancel/expiry support
9. Settlement file/API format for reconciliation
10. Sandbox test accounts / sample payloads
11. Amount limits, fees, and settlement timeline
12. Merchant onboarding requirements

---

## 7. Contacts

### 7.1 EmCa Technologies / Mauzo POS

| Role | Name | Email | Phone |
|------|------|-------|-------|
| Commercial / Partnership lead | _[Full name]_ | _[email@emca.tech]_ | _[+255 ...]_ |
| Technical integration lead | _[Full name]_ | _[email@emca.tech]_ | _[+255 ...]_ |
| Project support | _[Full name]_ | _[email@emca.tech]_ | _[+255 ...]_ |

**Company website:** https://www.emca.tech  

### 7.2 NBC (to be completed by bank team)

| Role | Name | Email | Phone |
|------|------|-------|-------|
| Relationship / merchant manager | _[NBC contact]_ | _[...]_ | _[...]_ |
| API / technical integration | _[NBC contact]_ | _[...]_ | _[...]_ |
| Sandbox / support desk | _[NBC contact]_ | _[...]_ | _[...]_ |

### 7.3 Pilot merchant details (optional)

| Field | Value |
|-------|-------|
| Pilot shop name | _[Shop / business name]_ |
| NBC account / merchant ID | _[If already assigned]_ |
| Branch / location | _[City / branch]_ |
| Expected go-live window | _[Month / year]_ |

---

## 8. Next Steps

1. NBC reviews this proposal and confirms API capability fit.  
2. NBC shares official API docs + sandbox credentials.  
3. EmCa implements initiate, webhook, and status query in Mauzo sandbox.  
4. Joint UAT (full pay, partial pay, expiry, failed pay, duplicate webhook).  
5. Limited production pilot with one merchant.  
6. Production rollout for eligible Mauzo merchants.

---

**End of proposal**
