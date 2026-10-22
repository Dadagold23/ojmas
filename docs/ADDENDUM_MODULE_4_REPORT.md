# OJMAS ENHANCEMENT — ADDENDUM MODULE 4 IMPLEMENTATION REPORT

Module: Advertisement Revenue and Campaign Analytics (Addendum brief §4).
The work extends the Module 2 advertising system rather than duplicating it:
the same campaign records gain optional revenue fields, and the existing
`ad_events` ledger (one impression/click per visitor fingerprint per day)
becomes the single source of the numbers, exactly as it already was on the
campaign pages. Everything is additive — one migration of nullable columns;
no existing data, status machine or public behaviour was altered.

---

## 1. What was added (§4.1, §4.2)

**`ad_campaigns` revenue columns** (migration
`2026_10_21_000004_add_revenue_columns_to_ad_campaigns`, all nullable):

| Column | Purpose |
| --- | --- |
| `agreed_price` (decimal 12,2) | The recorded agreed price. Announcement campaigns legitimately have none — leaving it empty keeps them priced "not priced", honestly. |
| `currency` (3 chars) | Only ever stored/used together with a price; uppercased on save. |
| `agreement_reference` | Quotation / agreement reference. |
| `invoice_reference` | Invoice reference. |
| `payment_status` | `agreed` / `invoiced` / `paid` — recorded states only. |
| `paid_at` (date) | Payment date, accepted only when the status is `paid`. |

The revenue block appears in the campaign create/edit forms and on the
campaign page, but **only for holders of `advertising.reports`** — it is a
financial surface, not part of the general manage grant. Two server-side
guarantees back this: posting any revenue field without the financial
permission is refused with 403, and a normal edit that posts no revenue
fields can never wipe recorded money.

**Bookkeeping fictions are refused** (validated server-side, nothing
auto-derived):

- an invoice state without an invoice reference;
- a `paid` state without a payment date;
- a payment date while the state is not `paid`;
- an invoice/payment state with no recorded price;
- a price without its currency.

## 2. Counting definitions (§4.1, stated on every report)

- **Impressions / clicks / CTR** come only from the `ad_events` rows Module 2
  records — one per visitor fingerprint per campaign per day, salted-hashed,
  rotated daily. No viewability, render-count or unobserved estimate is added.
- **Agreed revenue** = Σ agreed_price over campaigns that reached an approved
  status (`scheduled`/`published`/`paused`/`expired`). **Drafts and archived
  records are excluded openly** — a draft was never approved and an archived
  record may never have been, so their prices are not revenue.
- **Invoiced** = Σ over records whose state says invoiced *or* paid.
- **Paid (collected)** = Σ over records whose state says paid, filtered by the
  recorded payment date when a window is given. **An unpaid invoice is never
  presented as collected revenue** — it is the *outstanding* figure
  (invoiced − paid), shown as such on screen and in the invoice table.
- **Sums are grouped per currency**; totals of different currencies are never
  added together.

## 3. The reports screen (§4.3)

`admin/advertisements/reports` (`admin.advertisements.reports`), behind
`advertising.reports` (Admin + Super Admin by the role matrix; a manually
revoked permission is honoured — tested). Date-window filter (from/to,
`to` ≥ `from` enforced) that every section obeys:

- **Campaign counts**: live, scheduled, expired (including published/paused
  records whose window already closed), draft/upcoming, archived.
- **Engagement**: impressions, clicks, CTR for the window (CTR renders "—"
  when no impression existed rather than dividing by zero).
- **Recorded advertising income**: agreed / invoiced / paid / outstanding per
  currency, in cards that repeat the definitions verbatim.
- **Campaign performance**: top 25 campaigns by counted impressions with CTR,
  price and payment state.
- **Invoices**: every invoiced/paid record with amounts, references and
  status badges ("outstanding" vs "paid").
- **Notification delivery results** — the brief's push reporting need
  (§4.3 last bullet) — read from the Module 3 ledger: notifications created,
  dispatched, endpoint deliveries accepted/failed, pending rows explained
  (queue/cron resume, no duplicates possible), and the statement on screen
  that the report **never claims reads or clicks**.

Empty states are honest everywhere: an empty system shows "no prices recorded",
"no engagement recorded", "no invoices recorded — nothing is invented on the
journal's behalf", not zeros pretending to be figures.

## 4. Deliberately not done

- **No automatic charging, no invented payment records**: fields record what
  happened; no worker infers a payment from anything.
- **Paystack untouched**: online advertiser payments would use the existing
  approved gateway only if explicitly authorised later (§4.2).
- The pre-existing per-fingerprint impression rule and the Push report's
  HTTP-only honesty are inherited unchanged from Modules 2 and 3 — they were
  test-pinned there and the analytics adds no new counting mechanism.

## 5. Verification

New suite `tests/Feature/Addendum/Module4RevenueAnalyticsTest.php` —
**11 tests, 58 assertions**, all passing:

- financial-screen gating (guest/Author/Editor/Managing Editor; Admin whose
  `advertising.reports` was revoked; Super Admin holder);
- revenue-field write boundary (financial gate enforced on POST; non-financial
  edits leave recorded money untouched);
- price + currency + references recorded (currency normalised to NGN);
- all five bookkeeping-fiction refusals;
- the revenue maths: agreed 47,000 / invoiced 35,000 / paid 30,000 /
  outstanding 5,000 across priced, invoiced, paid, draft and archived
  fixtures;
- date-window discipline for engagement and payments (and the all-time view);
- delivery results from the real push ledger (sent/failed/pending/dispatched),
  the on-screen no-reads-no-clicks statement, and a fully empty state;
- invalid window refused (`to` before `from`).

The Module 2 advertising suite (20 tests, 81 assertions) was re-run against
the extended controller and passes unchanged.

## 6. Open items

1. Nothing host-dependent is new in this module: no new dependency, no new
   cron, no new queue use. The only deployment step is
   `php artisan migrate --force` and re-running the (idempotent)
   `EditorialConfigurationSeeder` is *not* required — `advertising.reports`
   already existed since Module 2's permission grant.
2. Multi-currency totals are shown per currency line; the journal currently
   records one currency at a time. If mixed currencies ever become real, a
   conversion-table decision belongs to the journal's treasurer, not to this
   system's inference.
