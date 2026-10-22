<?php

namespace App\Services\Advertising;

use App\Models\AdCampaign;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Addendum Module 4 — advertisement revenue and campaign analytics.
 *
 * Counting definition (stated on every report):
 *  - Impressions and clicks are the `ad_events` rows already recorded by
 *    Module 2: one per visitor fingerprint per campaign per day. No estimate
 *    of render counts, viewability or anything unobserved is added here.
 *  - Revenue names what was recorded, not what was earned by inference:
 *    * agreed   — Σ agreed_price over campaigns that reached an approved
 *                 status (drafts and archived records are excluded openly);
 *    * invoiced — Σ over records whose payment_status says invoiced or paid;
 *    * paid     — Σ over records whose payment_status is paid, filtered by
 *                 the recorded payment date when a window is given.
 *    An unpaid invoice is therefore NEVER counted as collected revenue, and
 *    "outstanding" is the invoiced total minus what was actually paid.
 *  - Sums are grouped by currency; totals of different currencies are never
 *    added together.
 *  - Push delivery results use only what the system observably records:
 *    HTTP acceptance per endpoint (Module 3). There are no read/click
 *    counts for notifications anywhere in the system.
 */
class AdRevenueReportService
{
    /**
     * Assembles every dataset the reports screen needs for an optional
     * date window (inclusive from/to on event/payment dates).
     *
     * @return array<string, mixed>
     */
    public function build(?string $from = null, ?string $to = null): array
    {
        $from = $from !== null && $from !== '' ? Carbon::parse($from)->startOfDay() : null;
        $to = $to !== null && $to !== '' ? Carbon::parse($to)->endOfDay() : null;

        return [
            'campaignCounts' => $this->campaignCounts(),
            'engagement' => $this->engagement($from, $to),
            'revenue' => $this->revenue($from, $to),
            'invoices' => $this->invoices($from, $to),
            'campaignTable' => $this->campaignTable($from, $to),
            'deliveries' => $this->deliveries($from, $to),
            'from' => $from,
            'to' => $to,
        ];
    }

    /** Active / scheduled / expired / dormant record counts, by approved definition. */
    private function campaignCounts(): array
    {
        $defaults = ['live' => 0, 'scheduled' => 0, 'expired' => 0, 'draft_pending' => 0, 'archived' => 0];

        $rows = AdCampaign::query()
            ->selectRaw("status, count(*) as total")
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = $defaults;
        $counts['live'] = (int) ($rows['published'] ?? 0);
        $counts['scheduled'] = (int) ($rows['scheduled'] ?? 0);
        $counts['expired'] = (int) ($rows['expired'] ?? 0);
        $counts['draft_pending'] = (int) ($rows['draft'] ?? 0) + (int) ($rows['pending'] ?? 0);
        $counts['archived'] = (int) ($rows['archived'] ?? 0);
        // A published/paused campaign whose window already closed has
        // effectively expired even before anyone files it as such.
        $counts['expired'] += (int) AdCampaign::query()
            ->whereIn('status', ['published', 'paused'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->count();

        return $counts;
    }

    /** Impressions, clicks and the ratio they can honestly produce. */
    private function engagement(?Carbon $from, ?Carbon $to): array
    {
        $query = DB::table('ad_events');

        if ($from !== null) {
            $query->where('occurred_at', '>=', $from);
        }
        if ($to !== null) {
            $query->where('occurred_at', '<=', $to);
        }

        $impressions = (int) (clone $query)->where('event', 'impression')->count();
        $clicks = (int) (clone $query)->where('event', 'click')->count();

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $impressions > 0 ? round(100 * $clicks / $impressions, 1) : null,
        ];
    }

    /**
     * Agreed / invoiced / paid / outstanding, grouped per currency. No
     * currency-mixing ever happens.
     *
     * @return array<string, array{agreed: string, invoiced: string, paid: string, outstanding: string}>
     */
    private function revenue(?Carbon $from, ?Carbon $to): array
    {
        $approved = AdCampaign::REVENUE_STATUSES;

        // Agreed: every approved campaign carrying a recorded price.
        $agreed = AdCampaign::query()
            ->whereIn('status', $approved)
            ->whereNotNull('agreed_price')
            ->groupBy('currency')
            ->selectRaw('currency, sum(agreed_price) as total')
            ->pluck('total', 'currency');

        // Invoiced: payment_status invoiced or paid (an invoice came to exist).
        $invoiced = AdCampaign::query()
            ->whereIn('status', $approved)
            ->whereIn('payment_status', ['invoiced', 'paid'])
            ->groupBy('currency')
            ->selectRaw('currency, sum(agreed_price) as total')
            ->pluck('total', 'currency');

        // Paid: payment recorded as paid; filtered by the recorded payment
        // date, never by inference.
        $paidQuery = AdCampaign::query()
            ->whereIn('status', $approved)
            ->where('payment_status', 'paid')
            ->whereNotNull('agreed_price');

        if ($from !== null) {
            $paidQuery->where('paid_at', '>=', $from->toDateString());
        }
        if ($to !== null) {
            $paidQuery->where('paid_at', '<=', $to->toDateString());
        }

        $paid = $paidQuery->groupBy('currency')
            ->selectRaw('currency, sum(agreed_price) as total')
            ->pluck('total', 'currency');

        $out = [];
        foreach ($agreed as $currency => $total) {
            $key = $currency ?? '(no currency)';
            $out[$key] = ['agreed' => (string) $total, 'invoiced' => '0', 'paid' => '0', 'outstanding' => '0'];
        }
        foreach ($invoiced as $currency => $total) {
            $key = $currency ?? '(no currency)';
            $out[$key] = $out[$key] ?? ['agreed' => '0', 'invoiced' => '0', 'paid' => '0', 'outstanding' => '0'];
            $out[$key]['invoiced'] = (string) $total;
        }
        foreach ($paid as $currency => $total) {
            $key = $currency ?? '(no currency)';
            $out[$key] = $out[$key] ?? ['agreed' => '0', 'invoiced' => '0', 'paid' => '0', 'outstanding' => '0'];
            $out[$key]['paid'] = (string) $total;
        }
        foreach ($out as $key => $row) {
            $row['outstanding'] = (string) ($row['invoiced'] - $row['paid']);
            $out[$key] = $row;
        }

        return $out;
    }

    /** Invoice-level rows: what is billed, what is still open, per record. */
    private function invoices(?Carbon $from, ?Carbon $to): \Illuminate\Support\Collection
    {
        $query = AdCampaign::query()
            ->whereIn('payment_status', ['invoiced', 'paid'])
            ->whereNotNull('agreed_price')
            ->orderBy('payment_status')
            ->orderBy('ends_at');

        if ($from !== null) {
            $query->where(fn ($w) => $w
                ->whereNull('paid_at')
                ->orWhere('paid_at', '>=', $from->toDateString()));
        }
        if ($to !== null) {
            $query->where(fn ($w) => $w
                ->whereNull('paid_at')
                ->orWhere('paid_at', '<=', $to->toDateString()));
        }

        return $query->with('placement')
            ->get()
            ->map(fn (AdCampaign $campaign) => [
                'campaign' => $campaign,
                'amount' => $campaign->agreed_price,
                'currency' => $campaign->currency,
                'invoice' => $campaign->invoice_reference,
                'status' => $campaign->payment_status,
                'paid_at' => $campaign->paid_at?->toDateString(),
            ]);
    }

    /** Campaign-level performance, worst-fit honesty: only counted events. */
    private function campaignTable(?Carbon $from, ?Carbon $to): \Illuminate\Support\Collection
    {
        $query = DB::table('ad_events as e')
            ->join('ad_campaigns as c', 'c.id', '=', 'e.campaign_id')
            ->selectRaw("c.id, c.title, c.status, c.agreed_price, c.currency, c.payment_status,
                c.agreed_price as price,
                sum(case when e.event = 'impression' then 1 else 0 end) as impressions,
                sum(case when e.event = 'click' then 1 else 0 end) as clicks")
            ->groupBy('c.id', 'c.title', 'c.status', 'c.agreed_price', 'c.currency', 'c.payment_status');

        if ($from !== null) {
            $query->where('e.occurred_at', '>=', $from);
        }
        if ($to !== null) {
            $query->where('e.occurred_at', '<=', $to);
        }

        return $query
            ->orderByDesc('impressions')
            ->limit(25)
            ->get()
            ->map(function ($row) {
                $row->ctr = ((int) $row->impressions) > 0
                    ? round(100 * $row->clicks / $row->impressions, 1)
                    : null;
                $row->impressions = (int) $row->impressions;
                $row->clicks = (int) $row->clicks;

                return $row;
            });
    }

    /** Push delivery results — HTTP acceptance only, per Module 3's design. */
    private function deliveries(?Carbon $from, ?Carbon $to): array
    {
        $query = DB::table('push_deliveries');

        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }
        if ($to !== null) {
            $query->where('created_at', '<=', $to);
        }

        $totals = ['pending' => 0, 'sent' => 0, 'failed' => 0];
        foreach ($query->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status') as $status => $total) {
            if (array_key_exists($status, $totals)) {
                $totals[$status] = (int) $total;
            }
        }

        $notifications = DB::table('push_notifications');
        if ($from !== null && $to !== null) {
            $notifications->whereBetween('created_at', [$from, $to]);
        } else {
            if ($from !== null) {
                $notifications->where('created_at', '>=', $from);
            }
            if ($to !== null) {
                $notifications->where('created_at', '<=', $to);
            }
        }

        return [
            'statuses' => $totals,
            'dispatched' => (int) (clone $notifications)->whereNotNull('sent_at')->count(),
            'notifications' => (int) (clone $notifications)->count(),
        ];
    }
}
