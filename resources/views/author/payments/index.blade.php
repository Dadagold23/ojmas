@extends('layouts.app')

@section('content')
<style>
    .author-hero{
        border: none;
        border-radius: 1.5rem;
        background:
            linear-gradient(135deg, rgba(245,158,11,.95), rgba(15,23,42,.92)),
            url('{{ asset('images/backgrounds/author-hero.jpg') }}') center center / cover no-repeat;
        color: #fff;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(15,23,42,.16);
    }

    .page-title{
        font-weight: 800;
        color: #0f172a;
    }

    .panel-card{
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 18px 40px rgba(15,23,42,.08);
    }
</style>

<div class="container py-4">
    <div class="card author-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="fw-bold mb-3">Manage Your Payment Records</h2>
                    <p class="mb-0 text-white-50">
                        View submission-related payments, monitor payment status, and access your receipts.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <i class="bi bi-credit-card-2-front" style="font-size: 5rem; opacity: .18;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-4" data-aos="fade-up">
        <h3 class="page-title mb-1">My Payments</h3>
        <p class="text-muted mb-0">All payment records associated with your submissions.</p>
    </div>

    <div class="card panel-card" data-aos="fade-up">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-1">Payment History</h5>
            <p class="text-muted mb-3">Track payment references and receipt availability.</p>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Reference</th>
                            <th>Submission</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="pe-4">Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments ?? [] as $payment)
                            <tr>
                                <td class="px-4 fw-semibold">{{ $payment->reference ?? '-' }}</td>
                                <td>{{ $payment->submission->title ?? 'N/A' }}</td>
                                <td>₦{{ number_format($payment->amount ?? 0, 2) }}</td>
                                <td>
                                    <span class="badge bg-{{ ($payment->status ?? '') === 'paid' ? 'success' : 'warning' }}">
                                        {{ ucfirst($payment->status ?? 'pending') }}
                                    </span>
                                </td>
                                <td>{{ $payment->created_at?->format('d M Y') }}</td>
                                <td class="pe-4">
                                    @if(!empty($payment->receipt_url))
                                        <a href="{{ $payment->receipt_url }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                            Download
                                        </a>
                                    @else
                                        <span class="text-muted">Not available</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">No payments found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
