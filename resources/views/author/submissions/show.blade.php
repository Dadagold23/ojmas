@extends('layouts.app')

@section('content')
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .status-badge { font-size: .82rem; padding: .4em .9em; border-radius: 50px; }
    .file-row:hover { background: #f8fafc; }
    .file-hint { font-size: .82rem; color: #64748b; }
    .info-label { font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; font-weight: 600; }
    .payment-modal-icon { width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg,#0d6efd,#2563eb); display:flex; align-items:center; justify-content:center; margin: 0 auto 1rem; }
    .fee-amount { font-size: 2rem; font-weight: 800; color: #0f172a; }
    .fee-currency { font-size: 1rem; color: #64748b; vertical-align: super; }
</style>

<div class="container py-4" style="max-width:900px">
    <div class="d-flex align-items-center gap-3 mb-4" data-aos="fade-up">
        <a href="{{ route('author.submissions.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <h3 class="fw-bold mb-0">{{ Str::limit($submission->title, 70) }}</h3>
            <span class="text-muted small">{{ $submission->submission_no }}</span>
        </div>
        @php
            $colors = ['pending_payment'=>'warning','submitted'=>'primary','under_review'=>'info','revision_requested'=>'warning','accepted'=>'success','rejected'=>'danger','published'=>'dark'];
            $color = $colors[$submission->status] ?? 'secondary';
        @endphp
        <span class="badge bg-{{ $color }} status-badge">{{ ucwords(str_replace('_',' ',$submission->status)) }}</span>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4">
        {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show rounded-4 mb-4">
        {{ session('warning') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show rounded-4 mb-4">
        {{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if($errors->any())
    <div class="alert alert-danger rounded-4 mb-4">
        <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    @if($canPayForSubmission && !$hasPaidVetting && $submission->status !== 'published')
    <div class="alert alert-warning rounded-4 d-flex align-items-center gap-3 mb-4" data-aos="fade-up">
        <i class="bi bi-exclamation-triangle-fill fs-4 text-warning"></i>
        <div class="flex-grow-1">
            <strong>Vetting fee unpaid.</strong> Your submission will not be processed until the vetting fee of
            <strong>{{ $vettingCurrency }} {{ number_format($vettingFee, 2) }}</strong> is paid.
            Your submission is currently locked until payment is completed.
        </div>
        <button class="btn btn-warning btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#paymentModal">
            <i class="bi bi-credit-card me-1"></i> Pay Now
        </button>
    </div>
    @endif

    @if($canPayForSubmission && $submission->status === 'accepted' && !$hasPaidPublication)
    <div class="alert alert-success rounded-4 d-flex align-items-center gap-3 mb-4" data-aos="fade-up">
        <i class="bi bi-check-circle-fill fs-4 text-success"></i>
        <div class="flex-grow-1">
            <strong>Manuscript Accepted!</strong> Please pay the publication fee of
            <strong>{{ $pubCurrency }} {{ number_format($publicationFee, 2) }}</strong> to proceed to publication.
        </div>
        <button class="btn btn-success btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#pubPaymentModal">
            <i class="bi bi-credit-card me-1"></i> Pay Publication Fee
        </button>
    </div>
    @endif

    @if(!$canPayForSubmission && request('open_payment') && $canApprovePayments)
    <div class="alert alert-info rounded-4 d-flex align-items-start gap-3 mb-4" data-aos="fade-up">
        <i class="bi bi-info-circle-fill fs-4 text-info"></i>
        <div class="flex-grow-1">
            <strong>Payment actions are author-only on this page.</strong> The submitting author can open the Paystack modal here.
            If payment went sideways, use the admin submission screen to record a manual approval instead.
        </div>
        <div class="d-flex flex-column gap-2">
            <a href="{{ route('admin.submissions.show', $submission) }}" class="btn btn-outline-info btn-sm fw-semibold">
                <i class="bi bi-shield-check me-1"></i> Open Admin View
            </a>
            <a href="{{ route('admin.submissions.show', $submission) }}#payment-actions" class="btn btn-info btn-sm fw-semibold text-white">
                <i class="bi bi-credit-card-2-front me-1"></i> Payment Actions
            </a>
        </div>
    </div>
    @endif

    @if($submission->status === 'accepted' && $hasPaidPublication)
    <div class="alert alert-info rounded-4 d-flex align-items-center gap-3 mb-4" data-aos="fade-up">
        <i class="bi bi-journal-check fs-4 text-info"></i>
        <div>Publication fee paid. Your article is awaiting final publication by the editorial team.</div>
    </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2 text-primary"></i>Submission Details</h6>
                    <div class="mb-3">
                        <div class="info-label mb-1">Abstract</div>
                        <p class="mb-0 small">{{ $submission->abstract }}</p>
                    </div>
                    @if($submission->keywords)
                    <div class="mb-3">
                        <div class="info-label mb-1">Keywords</div>
                        <p class="mb-0 small">{{ $submission->keywords }}</p>
                    </div>
                    @endif
                    @if($submission->subject_area)
                    <div>
                        <div class="info-label mb-1">Subject Area</div>
                        <p class="mb-0 small">{{ $submission->subject_area }}</p>
                    </div>
                    @endif
                </div>
            </div>

            @if($submission->coAuthors->count())
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-people me-2 text-primary"></i>Co-Authors</h6>
                    @foreach($submission->coAuthors as $ca)
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <i class="bi bi-person-circle text-muted mt-1"></i>
                        <div>
                            <div class="fw-semibold small">{{ $ca->full_name }}</div>
                            <div class="text-muted" style="font-size:.8rem">{{ $ca->institution }}{{ $ca->country ? ', '.$ca->country : '' }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if($submission->histories->count())
            <div class="card panel-card" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-primary"></i>Status History</h6>
                    @foreach($submission->histories->sortByDesc('created_at') as $h)
                    <div class="d-flex gap-3 mb-3">
                        <div class="text-muted small" style="min-width:90px">{{ $h->created_at->format('d M Y') }}</div>
                        <div>
                            <span class="badge bg-secondary">{{ ucwords(str_replace('_',' ',$h->new_status)) }}</span>
                            @if($h->note)<div class="text-muted small mt-1">{{ $h->note }}</div>@endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <div class="col-lg-5">
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-receipt me-2 text-primary"></i>Vetting Fee</h6>
                    @if($hasPaidVetting)
                        <div class="d-flex align-items-center gap-2 text-success">
                            <i class="bi bi-check-circle-fill fs-5"></i>
                            <span class="fw-semibold">Paid - {{ $vettingCurrency }} {{ number_format($vettingFee, 2) }}</span>
                        </div>
                        <p class="text-muted small mt-2 mb-0">Your manuscript is queued for editorial review.</p>
                    @elseif($canPayForSubmission)
                        <div class="d-flex align-items-center gap-2 text-danger mb-3">
                            <i class="bi bi-x-circle-fill fs-5"></i>
                            <span class="fw-semibold">Unpaid - {{ $vettingCurrency }} {{ number_format($vettingFee, 2) }}</span>
                        </div>
                        <button class="btn btn-primary btn-sm w-100 fw-semibold" data-bs-toggle="modal" data-bs-target="#paymentModal">
                            <i class="bi bi-credit-card me-2"></i>Pay Vetting Fee
                        </button>
                    @elseif($canApprovePayments)
                        <div class="d-flex align-items-center gap-2 text-danger mb-3">
                            <i class="bi bi-x-circle-fill fs-5"></i>
                            <span class="fw-semibold">Unpaid - {{ $vettingCurrency }} {{ number_format($vettingFee, 2) }}</span>
                        </div>
                        <div class="d-grid gap-2">
                            <a href="{{ route('admin.submissions.show', $submission) }}#payment-actions" class="btn btn-info btn-sm w-100 fw-semibold text-white">
                                <i class="bi bi-credit-card-2-front me-2"></i>Open Payment Actions
                            </a>
                            <a href="{{ route('admin.submissions.show', $submission) }}" class="btn btn-outline-secondary btn-sm w-100 fw-semibold">
                                <i class="bi bi-shield-check me-2"></i>Admin Review
                            </a>
                        </div>
                    @else
                        <div class="d-flex align-items-center gap-2 text-danger">
                            <i class="bi bi-x-circle-fill fs-5"></i>
                            <span class="fw-semibold">Unpaid - {{ $vettingCurrency }} {{ number_format($vettingFee, 2) }}</span>
                        </div>
                    @endif
                </div>
            </div>

            @if(in_array($submission->status, ['accepted','published']))
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-journal-arrow-up me-2 text-success"></i>Publication Fee</h6>
                    @if($hasPaidPublication)
                        <div class="d-flex align-items-center gap-2 text-success">
                            <i class="bi bi-check-circle-fill fs-5"></i>
                            <span class="fw-semibold">Paid - {{ $pubCurrency }} {{ number_format($publicationFee, 2) }}</span>
                        </div>
                        <p class="text-muted small mt-2 mb-0">Awaiting final publication.</p>
                    @elseif($canPayForSubmission)
                        <div class="d-flex align-items-center gap-2 text-warning mb-3">
                            <i class="bi bi-exclamation-circle-fill fs-5"></i>
                            <span class="fw-semibold">Unpaid - {{ $pubCurrency }} {{ number_format($publicationFee, 2) }}</span>
                        </div>
                        <button class="btn btn-success btn-sm w-100 fw-semibold" data-bs-toggle="modal" data-bs-target="#pubPaymentModal">
                            <i class="bi bi-credit-card me-2"></i>Pay Publication Fee
                        </button>
                    @elseif($canApprovePayments)
                        <div class="d-flex align-items-center gap-2 text-warning mb-3">
                            <i class="bi bi-exclamation-circle-fill fs-5"></i>
                            <span class="fw-semibold">Unpaid - {{ $pubCurrency }} {{ number_format($publicationFee, 2) }}</span>
                        </div>
                        <div class="d-grid gap-2">
                            <a href="{{ route('admin.submissions.show', $submission) }}#payment-actions" class="btn btn-info btn-sm w-100 fw-semibold text-white">
                                <i class="bi bi-credit-card-2-front me-2"></i>Open Payment Actions
                            </a>
                            <a href="{{ route('admin.submissions.show', $submission) }}" class="btn btn-outline-secondary btn-sm w-100 fw-semibold">
                                <i class="bi bi-shield-check me-2"></i>Admin Review
                            </a>
                        </div>
                    @else
                        <div class="d-flex align-items-center gap-2 text-warning">
                            <i class="bi bi-exclamation-circle-fill fs-5"></i>
                            <span class="fw-semibold">Unpaid - {{ $pubCurrency }} {{ number_format($publicationFee, 2) }}</span>
                        </div>
                    @endif
                </div>
            </div>
            @endif

            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-folder2-open me-2 text-primary"></i>Uploaded Files</h6>
                    @forelse($submission->files as $file)
                    <div class="d-flex align-items-center justify-content-between py-2 file-row rounded px-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-file-earmark-text text-primary fs-5"></i>
                            <div>
                                <div class="small fw-semibold">{{ $file->original_name }}</div>
                                <div class="file-hint">{{ ucwords(str_replace('_',' ',$file->file_type)) }} &middot; {{ number_format($file->file_size/1024, 1) }} KB</div>
                            </div>
                        </div>
                        @if($canManageSubmission && $hasPaidVetting && in_array($submission->status, ['submitted','revision_requested']))
                        <form method="POST" action="{{ route('author.submissions.files.destroy', [$submission, $file]) }}" onsubmit="return confirm('Remove this file?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger border-0" title="Remove">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </form>
                        @endif
                    </div>
                    @empty
                    <p class="text-muted small mb-0">No files uploaded yet.</p>
                    @endforelse
                </div>
            </div>

            @if($canManageSubmission && $hasPaidVetting && in_array($submission->status, ['submitted','revision_requested']))
            <div class="card panel-card" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-1"><i class="bi bi-cloud-upload me-2 text-primary"></i>Upload File</h6>
                    <p class="text-muted small mb-3">Add a revised manuscript, cover letter, or supplementary file.</p>
                    <form method="POST" action="{{ route('author.submissions.files.store', $submission) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">File Type <span class="text-danger">*</span></label>
                            <select name="file_type" class="form-select form-select-sm @error('file_type') is-invalid @enderror" required>
                                <option value="">- Select type -</option>
                                <option value="manuscript" {{ old('file_type') === 'manuscript' ? 'selected' : '' }}>Manuscript</option>
                                <option value="cover_letter" {{ old('file_type') === 'cover_letter' ? 'selected' : '' }}>Cover Letter</option>
                                <option value="supplementary" {{ old('file_type') === 'supplementary' ? 'selected' : '' }}>Supplementary</option>
                            </select>
                            @error('file_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">File <span class="text-danger">*</span></label>
                            <input type="file" name="file" class="form-control form-control-sm @error('file') is-invalid @enderror" accept=".pdf,.doc,.docx,.zip" required>
                            <div class="file-hint mt-1">PDF, DOC, DOCX, ZIP - max 1 GB</div>
                            @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">
                            <i class="bi bi-upload me-2"></i>Upload File
                        </button>
                    </form>
                </div>
            </div>
            @elseif($canManageSubmission && !$hasPaidVetting)
            <div class="card panel-card" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-1"><i class="bi bi-lock me-2 text-warning"></i>Submission Locked</h6>
                    <p class="text-muted small mb-0">File changes are disabled until the vetting fee is paid successfully.</p>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('modals')
@if($canPayForSubmission && !$hasPaidVetting)
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <div class="modal-body p-5 text-center">
                <div class="payment-modal-icon mb-3">
                    <i class="bi bi-credit-card-2-front text-white fs-2"></i>
                </div>
                <h4 class="fw-bold mb-1" id="paymentModalLabel">Vetting Fee Payment</h4>
                <p class="text-muted mb-4">A vetting fee is required to process your manuscript submission.</p>
                <div class="bg-light rounded-3 p-4 mb-4">
                    <div class="text-muted small mb-1">Amount Due</div>
                    <div class="fee-amount"><span class="fee-currency">{{ $vettingCurrency }}</span>{{ number_format($vettingFee, 2) }}</div>
                    <div class="text-muted small mt-2">Submission: <strong>{{ $submission->submission_no }}</strong></div>
                </div>
                <div class="d-flex align-items-center gap-2 justify-content-center text-muted small mb-4">
                    <i class="bi bi-shield-lock-fill text-success"></i> Secured by Paystack - SSL encrypted
                </div>
                <form method="POST" action="{{ route('author.payments.initiate', $submission) }}">
                    @csrf
                    <input type="hidden" name="purpose" value="vetting_fee">
                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold mb-3">
                        <i class="bi bi-lock-fill me-2"></i>Pay {{ $vettingCurrency }} {{ number_format($vettingFee, 2) }} Now
                    </button>
                </form>
                <button type="button" class="btn btn-link text-muted btn-sm" data-bs-dismiss="modal">Pay later</button>
            </div>
        </div>
    </div>
</div>
@endif

@if($canPayForSubmission && in_array($submission->status, ['accepted','published']) && !$hasPaidPublication)
<div class="modal fade" id="pubPaymentModal" tabindex="-1" aria-labelledby="pubPaymentModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <div class="modal-body p-5 text-center">
                <div class="mb-3" style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#198754,#0f5132);display:flex;align-items:center;justify-content:center;margin:0 auto">
                    <i class="bi bi-journal-arrow-up text-white fs-2"></i>
                </div>
                <h4 class="fw-bold mb-1" id="pubPaymentModalLabel">Publication Fee Payment</h4>
                <p class="text-muted mb-4">Your manuscript has been accepted. Pay the publication fee to proceed.</p>
                <div class="bg-light rounded-3 p-4 mb-4">
                    <div class="text-muted small mb-1">Amount Due</div>
                    <div class="fee-amount"><span class="fee-currency">{{ $pubCurrency }}</span>{{ number_format($publicationFee, 2) }}</div>
                    <div class="text-muted small mt-2">Submission: <strong>{{ $submission->submission_no }}</strong></div>
                </div>
                <div class="d-flex align-items-center gap-2 justify-content-center text-muted small mb-4">
                    <i class="bi bi-shield-lock-fill text-success"></i> Secured by Paystack - SSL encrypted
                </div>
                <form method="POST" action="{{ route('author.payments.initiate', $submission) }}">
                    @csrf
                    <input type="hidden" name="purpose" value="publication_fee">
                    <button type="submit" class="btn btn-success btn-lg w-100 fw-bold mb-3">
                        <i class="bi bi-lock-fill me-2"></i>Pay {{ $pubCurrency }} {{ number_format($publicationFee, 2) }} Now
                    </button>
                </form>
                <button type="button" class="btn btn-link text-muted btn-sm" data-bs-dismiss="modal">Pay later</button>
            </div>
        </div>
    </div>
</div>
@endif
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        @if($canPayForSubmission && (session('pay') || request('open_payment') === 'vetting') && !$hasPaidVetting)
            new bootstrap.Modal(document.getElementById('paymentModal')).show();
        @endif
        @if($canPayForSubmission && request('open_payment') === 'publication' && in_array($submission->status, ['accepted','published']) && !$hasPaidPublication)
            new bootstrap.Modal(document.getElementById('pubPaymentModal')).show();
        @endif
    });
</script>
@endpush
