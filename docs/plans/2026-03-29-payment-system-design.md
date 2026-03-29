# RideMaster Payment System — Design Specification

**Date**: 2026-03-29
**Status**: Approved
**Scope**: Stripe Connect integration, escrow, split payments, cancellations, insurance

---

## 1. Overview

RideMaster needs a payment system that:
- Collects payment from riders (customers) at checkout
- Holds funds in escrow until 15 days before the camp starts (J-15)
- Splits the payout between the coach and the hotel
- Handles cancellations with tiered refund rates
- Includes mandatory accident insurance
- Gives coaches visibility on their earnings from their dashboard

## 2. Architecture

### Approach
Custom Stripe Connect integration built directly into the RideMaster plugin (`class-payments.php`). WooCommerce handles the cart/checkout/orders; our code handles Stripe Connect, escrow, splits, and refunds via the Stripe PHP SDK.

No third-party payment plugin — the flow is too specific for generic solutions.

### Stripe Account Structure

| Account | Type | Purpose |
|---------|------|---------|
| RideMaster | Platform account | Receives all payments, holds escrow |
| Coach | Stripe Connect Express | Receives coach payout at J-15 |
| Hotel | Stripe Connect Custom | Receives hotel payout at J-15, no Stripe interaction |

## 3. Stripe Connect Onboarding

### Coach (Express)
- Button "Connect with Stripe" in the coach dashboard
- Redirects to Stripe-hosted onboarding (handles both new accounts and existing Stripe accounts)
- Stripe handles KYC verification
- Webhook `account.updated` stores `stripe_account_id` on user meta
- **Blocker**: Coach cannot publish a camp until Stripe is connected
- Coach gets a limited Stripe Express dashboard for their records

### Hotel (Custom)
- Created via Stripe API by RideMaster — hotel never interacts with Stripe
- Coach provides hotel info when creating a camp: legal name, IBAN, country, address, representative name + DOB
- If Stripe requires additional KYC docs → admin alert to collect and submit
- Hotel receives payouts as standard bank transfers on their IBAN

### Data stored

**Coach (user meta):**
- `stripe_account_id` — Stripe Express account ID
- `stripe_onboarding_complete` — boolean

**Hotel (JetEngine CPT "hotel"):**
- `hotel_name` — Legal name
- `hotel_iban` — Bank account IBAN
- `hotel_country` — Country code
- `hotel_address` — Full address
- `hotel_representative_name` — Legal representative
- `hotel_representative_dob` — Date of birth
- `hotel_stripe_account_id` — Stripe Custom account ID
- `hotel_stripe_status` — pending / verified / requires_action

**Camp (post meta):**
- `_hotel_amount` — Fixed hotel amount per person (0 if no hotel)
- `_hotel_id` — Reference to hotel CPT post

## 4. Checkout & Payment

### Rider flow
1. Camp product page → "Book Now"
2. Checkout page:
   - Standard WooCommerce fields
   - **Mandatory checkbox**: "I accept the Terms & Conditions and confirm I have read the accident insurance information notice."
   - **Display**: "Individual Accident Insurance included"
   - Payment via Stripe Elements (embedded card form, PCI compliant)
3. Confirmation → Thank you page + confirmation email with insurance PDF attached

### Technical payment flow
1. Rider submits payment → Stripe `PaymentIntent::create()` with full amount
2. Funds are captured immediately (authorization expiry is 7 days, camps can be months away)
3. WooCommerce order created with status `processing`
4. Funds remain on RideMaster platform account (= escrow)
5. No transfers to coach/hotel at this stage

### Order meta stored at checkout
- `_stripe_payment_intent_id`
- `_camp_id`
- `_coach_stripe_account_id`
- `_hotel_stripe_account_id` (or empty)
- `_amount_total` — Total paid by rider
- `_amount_commission` — RideMaster commission
- `_amount_hotel` — Hotel share
- `_amount_coach` — Coach share
- `_camp_start_date`
- `_payout_status` — pending / scheduled / paid / failed / cancelled
- `_payout_date` — Scheduled payout date (J-15)

### Split calculation formula
```
commission = amount_total × commission_rate
amount_hotel = hotel_amount_per_person × quantity
amount_coach = amount_total - commission - amount_hotel
```

Commission rate is 0% at launch, configurable up to 10-15% later.
Stripe fees absorbed by RideMaster (deducted from commission).

## 5. Escrow & Payout (J-15)

### Mechanism
- Daily WP-Cron job (backed by real server crontab for reliability)
- Queries orders where `_payout_status = pending` AND `_payout_date <= today`
- For each eligible order:
  1. `Stripe\Transfer::create()` → coach account for `_amount_coach`
  2. `Stripe\Transfer::create()` → hotel account for `_amount_hotel` (if > 0)
  3. Update `_payout_status = paid`
  4. Log the operation
- On Stripe failure → `_payout_status = failed` + admin email alert

### Payout date calculation
- Standard: `_payout_date = camp_start_date - 15 days`
- Last-minute booking (< 15 days): `_payout_date = order_date + 1 day`

### Payout example (1000€ camp, 400€ hotel, 10% commission)

| Recipient | Amount | Mechanism |
|-----------|--------|-----------|
| RideMaster | 100€ | Stays on platform account |
| Coach | 500€ | Stripe Transfer to acct_coach |
| Hotel | 400€ | Stripe Transfer to acct_hotel |

## 6. Cancellations & Refunds

### Rider cancellation — Tiered refund

| Days before camp | Refund % | Fees retained |
|-----------------|----------|---------------|
| > 45 days | 100% | 0% |
| 45 to 31 days | 90% | 10% |
| 30 to 15 days | 50% | 50% |
| < 15 days | 25% | 75% |

### Rider cancellation flow

**If payout NOT yet made** (`_payout_status = pending`):
1. Calculate refund tier
2. `Stripe\Refund::create()` for refund amount
3. Retained fees stay on RideMaster account
4. Retained fees distribution: commission to RideMaster, rest to coach, nothing to hotel
5. Order status → `refunded` or `partial-refund`
6. `_payout_status = cancelled`

**If payout ALREADY made** (`_payout_status = paid`):
1. `Stripe\Transfer::create_reversal()` on coach transfer
2. `Stripe\Refund::create()` to rider
3. If reverse transfer fails → **urgent admin alert**
4. Hotel transfer NOT automatically reversed (commercial negotiation)

### Coach cancellation (special rule)
- Rider always refunded 100%, regardless of date
- Admin-initiated only (no coach self-service)
- If payout not made → simple refund
- If payout already made → reverse coach transfer + admin alert for hotel situation

### Cancellation meta on order
- `_cancellation_date`
- `_cancellation_by` — rider / coach
- `_cancellation_tier` — 100% / 90% / 50% / 25%
- `_refund_amount`
- `_refund_stripe_id`
- `_reverse_transfer_coach_id`
- `_reverse_transfer_hotel_id`
- `_cancellation_alert` — boolean flag for admin attention

## 7. Insurance & Compliance

### Admin settings
- Upload insurance PDF notice (WordPress media library)
- Configurable frontend label (default: "Individual Accident Insurance included")
- CGV page selector

### Frontend
- Camp product page: insurance mention near price + "View notice" PDF link
- Checkout: mandatory checkbox blocking payment if unchecked

### Email
- Confirmation email includes insurance PDF as attachment (or download link)

### WP options
- `rm_insurance_pdf_id`
- `rm_insurance_label`
- `rm_cgv_page_id`

## 8. Admin Dashboard

### Settings page (RideMaster > Settings)
- **Stripe**: API keys (live + test), mode toggle, webhook secret
- **Commission**: Rate (%), applies to new orders only
- **Insurance**: PDF upload, label text, CGV page
- **Payouts**: Delay in days (default 15), admin notification email

### Payments page (RideMaster > Payments)

| Tab | Content |
|-----|---------|
| Escrow | Orders with funds held, total in escrow, scheduled payout dates |
| Payouts | History of completed payouts, failed payouts with Retry button |
| Coaches | Coach list with Stripe status (connected / not connected / issue) |
| Hotels | Hotel Custom accounts with KYC status |
| Cancellations | Cancellation history with unresolved alerts |

## 9. Coach Dashboard — Payments

### Homepage widget "My Earnings"
- Available balance (already paid out): **X €**
- In escrow (awaiting J-15): **X €**
- Next payout: **X € on DD/MM**
- Total earned (lifetime): **X €**

### New tab "My Payments"
Transaction table with columns: Date, Camp, Rider, Total amount, My share, Hotel share, Status

**Statuses:**
- 🟡 In escrow
- 🟢 Paid (+ date)
- 🔴 Cancelled (+ refund %)
- 🟠 Payout error (contact support)

Filters: by camp, by status, by period.

Data sourced from WooCommerce order meta (no live Stripe API calls on page load).

### Sidebar menu addition
```
📊 Dashboard
👤 Edit my Profile
👁  View my Profile
🏕 My Camps
⛺ Create a Camp
📍 My Spots
📍 Create a Spot
💰 My Payments    ← NEW
```

## 10. Implementation Order

```
Sub-project 1: Stripe Connect Onboarding (Coach Express + Admin settings)
    → Sub-project 2: Checkout & Split Payment (Gateway + Stripe Elements)
        → Sub-project 3: Hotels (CPT + Custom accounts + camp form fields)
            → Sub-project 4: Escrow & Payout J-15 (Cron + Transfers + Admin dashboard)
                → Sub-project 5: Cancellations & Refunds (Tiers + Reverse transfers + Alerts)

Sub-project 6: Insurance & Compliance (parallel from sub-project 2 onward)
Sub-project 7: Coach Dashboard Payments (parallel from sub-project 4 onward)
```

Each sub-project follows its own spec → plan → implementation cycle.

## 11. New Files

| File | Purpose |
|------|---------|
| `includes/class-payments.php` | Stripe Connect orchestration, PaymentIntents, Transfers |
| `includes/class-payment-gateway.php` | WooCommerce payment gateway (extends WC_Payment_Gateway) |
| `includes/class-payout-cron.php` | WP-Cron job for J-15 payouts |
| `includes/class-cancellation.php` | Refund/cancellation logic |
| `includes/class-payment-admin.php` | Admin pages (Settings, Payments dashboard) |
| `assets/js/stripe-checkout.js` | Stripe Elements frontend integration |
| `assets/js/coach-payments.js` | Coach payments dashboard frontend |
