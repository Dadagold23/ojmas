@extends('layouts.app')

@section('content')
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .metric { border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1rem; text-align: center; background: #fff; }
    .metric .value { font-size: 1.5rem; font-weight: 700; }
    .metric .caption { font-size: .78rem; color: #64748b; }
    .status-chip { border-radius: 999px; padding: .25rem .6rem; font-size: .72rem; font-weight: 600; }
    .status-published { background: #d1fae5; color: #065f46; }
    .status-scheduled { background: #dbeafe; color: #1e40af; }
    .status-draft    { background: #e2e8f0; color: #334155; }
    .status-paused   { background: #fce7f3; color: #9d174d; }
    .status-expired  { background: #fee2e2; color: #991b1b; }
    .status-archived { background: #f1f5f9; color: #64748b; }
</style>

@php
    // Currency symbols are not guessed: a known ISO code renders through
    // Number, anything else prints with the recorded three-letter code.
    $summarise = function ($amount, $currency) {
        if ($currency !== null) {
            try { return \Illuminate\Support\Number::currency((float) $amount, $currency, app()->getLocale()); }
            catch (\Throwable) { return $currency.' '.number_format((float) $amount, 2); }
        }
        return number_format((float) $amount, 2);
    };
@endphp

<div class="container py-4" style="max-width: 1150px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-graph-up-arrow me-2"></i>Revenue &amp; Campaign Performance</h3>
            <p class="text-muted mb-0">Advertisement analytics and recorded revenue (Addendum Module 4).</p>
        </div>
        <a href="{{ route('admin.advertisements.index') }}" class="btn btn-outline-secondary btn-sm">Back to campaigns</a>
    </div>

    {{-- Date filtering: begins with the last 30 days; every section obeys it. --}}
    <div class="card panel-card mb-4">
        <div class="card-body px-4 py-3">
            <form method="GET" action="{{ route('admin.advertisements.reports') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="small fw-semibold mb-1">From</label>
                    <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="small fw-semibold mb-1">To</label>
                    <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Apply window</button>
                </div>
                <div class="col-md-3 text-md-end">
                    <a href="{{ route('admin.advertisements.reports') }}" class="text-decoration-none small">Reset to all-time</a>
                </div>
            </form>
        </div>
    </div>

    <div class="mb-4">
        <div class="row g-3">
            {{-- Engagement: event counts are exactly what was observed. --}}
            <div class="col-6 col-md-3">
                <div class="metric h-100">
                    <div class="value">{{ number_format($report['engagement']['impressions']) }}</div>
                    <div class="caption">impressions<br><span class="text-muted">one per visitor per campaign per day</span></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric h-100">
                    <div class="value">{{ number_format($report['engagement']['clicks']) }}</div>
                    <div class="caption">clicks<br><span class="text-muted">counted on the real redirect</span></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric h-100">
                    <div class="value">{{ $report['engagement']['ctr'] === null ? '—' : $report['engagement']['ctr'].'%' }}</div>
                    <div class="caption">click-through rate<br><span class="text-muted">clicks ÷ impressions; — when no impressions</span></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric h-100">
                    <div class="value">{{ $report['campaignCounts']['live'] }}</div>
                    <div class="caption">live advertisements<br>
                        {{ $report['campaignCounts']['scheduled'] }} scheduled ·
                        {{ $report['campaignCounts']['expired'] }} expired ·
                        {{ $report['campaignCounts']['draft_pending'] }} draft/upcoming ·
                        {{ $report['campaignCounts']['archived'] }} archived
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Revenue: named states, grouped per currency, never mixed. --}}
    <div class="card panel-card mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-1">Recorded advertising income</h5>
            <p class="text-muted small mb-3">
                These totals name what was <em>recorded</em>, not what is credited anywhere:
                <strong>agreed</strong> — every priced campaign that reached an approved status
                (drafts and archived records are excluded); <strong>invoiced</strong> — records with an
                issued invoice (paid ones included); <strong>paid</strong> — status "Paid" with a payment
                date. An unpaid invoice is never presented as collected revenue —
                it appears as <strong>outstanding</strong>.
            </p>
        </div>
        <div class="card-body px-4 pb-4">
            @forelse($report['revenue'] as $currency => $row)
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3">
                        <div class="metric h-100">
                            <div class="value">{{ $summarise($row['agreed'], $currency === '(no currency)' ? null : $currency) }}</div>
                            <div class="caption">agreed{{ $currency === '(no currency)' ? '' : ' ('.$currency.')' }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="metric h-100">
                            <div class="value">{{ $summarise($row['invoiced'], $currency === '(no currency)' ? null : $currency) }}</div>
                            <div class="caption">invoiced total</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="metric h-100">
                            <div class="value text-success">{{ $summarise($row['paid'], $currency === '(no currency)' ? null : $currency) }}</div>
                            <div class="caption">paid (collected)</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="metric h-100">
                            <div class="value {{ $row['outstanding'] > 0 ? 'text-warning' : '' }}">{{ $summarise($row['outstanding'], $currency === '(no currency)' ? null : $currency) }}</div>
                            <div class="caption">outstanding (invoiced − paid)</div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-muted small">No prices have been recorded yet — the revenue totals are empty rather than estimated.</div>
            @endforelse
        </div>
    </div>

    <div class="card panel-card mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-1">Campaign performance</h5>
            <p class="text-muted small mb-3">Top {{ $report['campaignTable']->count() }} campaign(s) by counted impressions inside the window.</p>
        </div>
        <div class="card-body px-4 pb-4 table-responsive">
            <table class="table table-sm small align-middle mb-0">
                <thead class="text-muted">
                    <tr><th>Campaign</th><th>Status</th><th>Impr.</th><th>Clicks</th><th>CTR</th><th>Agreed price</th><th>Payment</th></tr>
                </thead>
                <tbody>
                    @forelse($report['campaignTable'] as $row)
                        <tr>
                            <td><a href="{{ route('admin.advertisements.show', $row->id) }}">{{ \Illuminate\Support\Str::limit($row->title, 50) }}</a></td>
                            <td><span class="status-chip status-{{ $row->status }}">{{ $row->status }}</span></td>
                            <td>{{ number_format($row->impressions) }}</td>
                            <td>{{ number_format($row->clicks) }}</td>
                            <td>{{ $row->ctr === null ? '—' : $row->ctr.'%' }}</td>
                            <td>{{ $row->price !== null ? ($row->currency ?? '').' '.number_format((float) $row->price, 2) : '—' }}</td>
                            <td>{{ $row->payment_status ? (\App\Models\AdCampaign::PAYMENT_STATUSES[$row->payment_status] ?? $row->payment_status) : ($row->price !== null ? 'agreed — not yet invoiced' : '—') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">No engagement recorded in the selected window.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card panel-card mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-1">Invoices</h5>
            <p class="text-muted small mb-3">Every record carrying an invoice reference. Unpaid rows are shown open — never as collected revenue.</p>
        </div>
        <div class="card-body px-4 pb-4 table-responsive">
            <table class="table table-sm small align-middle mb-0">
                <thead class="text-muted">
                    <tr><th>Campaign</th><th>Advertiser</th><th>Amount</th><th>Invoice ref.</th><th>State</th><th>Paid on</th></tr>
                </thead>
                <tbody>
                    @forelse($report['invoices'] as $invoice)
                        <tr>
                            <td><a href="{{ route('admin.advertisements.show', $invoice['campaign']) }}">{{ \Illuminate\Support\Str::limit($invoice['campaign']->title, 40) }}</a></td>
                            <td>{{ $invoice['campaign']->advertiser_name ?? '—' }}</td>
                            <td>{{ $summarise($invoice['amount'], $invoice['currency']) }}</td>
                            <td>{{ $invoice['invoice'] ?? '—' }}</td>
                            <td>
                                @if($invoice['status'] === 'paid')
                                    <span class="badge bg-success-subtle text-success-emphasis">paid</span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning-emphasis">outstanding</span>
                                @endif
                            </td>
                            <td>{{ $invoice['paid_at'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted">No invoices recorded — nothing is invented on the journal's behalf.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Push delivery results (Module 3 data). HTTP acceptance only. --}}
    <div class="card panel-card">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-1">Notification delivery results</h5>
            <p class="text-muted small mb-3">
                From the web push ledger (Module 3). Only observable results are counted:
                an "accepted" row is the push vendor's HTTP confirmation
                — the report <strong>never claims reads or clicks</strong>,
                which Web Push cannot observe.
            </p>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="metric h-100">
                        <div class="value">{{ $report['deliveries']['notifications'] }}</div>
                        <div class="caption">notifications created</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="metric h-100">
                        <div class="value">{{ $report['deliveries']['dispatched'] }}</div>
                        <div class="caption">dispatched (sent stage reached)</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="metric h-100">
                        <div class="value text-success">{{ $report['deliveries']['statuses']['sent'] }}</div>
                        <div class="caption">endpoint deliveries accepted</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="metric h-100">
                        <div class="value {{ $report['deliveries']['statuses']['failed'] > 0 ? 'text-danger' : '' }}">{{ $report['deliveries']['statuses']['failed'] }}</div>
                        <div class="caption">endpoint deliveries failed</div>
                    </div>
                </div>
            </div>
            @if($report['deliveries']['statuses']['pending'] > 0)
                <div class="alert alert-warning rounded-4 mb-0 py-2 small">
                    {{ $report['deliveries']['statuses']['pending'] }} delivery row(s) are still pending —
                    either the queue has not run them yet or the next cron minute will resume them (no duplicate can be staged thanks to the delivery ledger's unique pair).
                </div>
            @elseif($report['deliveries']['notifications'] === 0)
                <div class="text-muted small">No notifications have been created at all — the section stays empty until then.</div>
            @endif
        </div>
    </div>
</div>
@endsection
