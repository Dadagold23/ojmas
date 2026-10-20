@extends('layouts.app')

@section('content')
<style>
    .admin-page-title{
        font-weight: 800;
        color: #0f172a;
    }

    .admin-stat-card{
        border: none;
        border-radius: 1.25rem;
        overflow: hidden;
        box-shadow: 0 18px 40px rgba(15,23,42,.08);
        transition: .3s ease;
    }

    .admin-stat-card:hover{
        transform: translateY(-4px);
    }

    .admin-stat-icon{
        width: 58px;
        height: 58px;
        border-radius: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
    }

    .admin-table-card{
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 18px 40px rgba(15,23,42,.08);
    }

    .admin-welcome{
        border: none;
        border-radius: 1.5rem;
        background:
            linear-gradient(135deg, rgba(13,110,253,.95), rgba(15,23,42,.92)),
            url('{{ asset('images/backgrounds/admin-hero.jpg') }}') center center / cover no-repeat;
        color: #fff;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(15,23,42,.16);
    }
</style>

<div class="container py-4">
    <div class="card admin-welcome mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="fw-bold mb-3">Welcome to the OJMAS Admin Dashboard</h2>
                    <p class="mb-0 text-white-50">
                        Monitor journal submissions, published articles, review activity, and payment records from one central dashboard.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <i class="bi bi-speedometer2" style="font-size: 5rem; opacity: .18;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4" data-aos="fade-up">
        <div>
            <h3 class="admin-page-title mb-1">Admin Dashboard</h3>
            <p class="text-muted mb-0">System overview and recent journal activities.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.users.index') }}" class="btn btn-primary btn-sm fw-semibold">
                <i class="bi bi-people me-1"></i>Manage Users
            </a>
            <a href="{{ route('editor.volumes.index') }}" class="btn btn-info btn-sm fw-semibold text-white">
                <i class="bi bi-journal-bookmark me-1"></i>Volumes
            </a>
            <a href="{{ route('editor.issues.index') }}" class="btn btn-secondary btn-sm fw-semibold">
                <i class="bi bi-journals me-1"></i>Issues
            </a>
            <a href="{{ route('admin.publications.index') }}" class="btn btn-success btn-sm fw-semibold">
                <i class="bi bi-journal-check me-1"></i>Publications
            </a>
            <a href="{{ route('admin.upload-diagnostics') }}" class="btn btn-outline-warning btn-sm fw-semibold">
                <i class="bi bi-wrench-adjustable-circle me-1"></i>Upload Diagnostics
            </a>
        </div>
    </div>

    @if(isset($pendingAssignment) && $pendingAssignment->count())
    <div class="card admin-table-card mb-4 border-warning" style="border-left: 4px solid #f59e0b !important" data-aos="fade-up">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-1"><i class="bi bi-person-check me-2 text-warning"></i>Accepted - Awaiting Editor Assignment</h5>
            <p class="text-muted mb-3">These manuscripts have been accepted. Assign an editor to proceed to publication.</p>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Submission No</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th class="pe-4">Assign Editor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingAssignment as $sub)
                        <tr>
                            <td class="px-4 fw-semibold text-primary">{{ $sub->submission_no }}</td>
                            <td class="small">{{ Str::limit($sub->title, 50) }}</td>
                            <td class="small">{{ $sub->author->name ?? 'N/A' }}</td>
                            <td class="pe-4">
                                <form method="POST" action="{{ route('admin.submissions.assign-editor', $sub) }}"
                                      class="d-flex gap-2 align-items-center">
                                    @csrf
                                    <select name="editor_id" class="form-select form-select-sm" required style="min-width:160px">
                                        <option value="">- Select Editor -</option>
                                        @foreach($editors as $editor)
                                        <option value="{{ $editor->id }}">{{ $editor->name }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-warning btn-sm fw-semibold text-nowrap">
                                        <i class="bi bi-person-check me-1"></i>Assign
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-3" data-aos="fade-up">
            <div class="card admin-stat-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-muted small mb-2">Total Submissions</div>
                            <h2 class="mb-0 fw-bold">{{ $stats['submissions'] ?? 0 }}</h2>
                        </div>
                        <div class="admin-stat-icon bg-primary-subtle text-primary">
                            <i class="bi bi-file-earmark-arrow-up"></i>
                        </div>
                    </div>
                    <small class="text-muted">All manuscript entries in the system.</small>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="100">
            <div class="card admin-stat-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-muted small mb-2">Published Articles</div>
                            <h2 class="mb-0 fw-bold">{{ $stats['published'] ?? 0 }}</h2>
                        </div>
                        <div class="admin-stat-icon bg-success-subtle text-success">
                            <i class="bi bi-journal-check"></i>
                        </div>
                    </div>
                    <small class="text-muted">Articles approved and made public.</small>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="200">
            <div class="card admin-stat-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-muted small mb-2">Pending Reviews</div>
                            <h2 class="mb-0 fw-bold">{{ $stats['pending_reviews'] ?? 0 }}</h2>
                        </div>
                        <div class="admin-stat-icon bg-warning-subtle text-warning">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                    <small class="text-muted">Submissions currently in review-related stages.</small>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="300">
            <div class="card admin-stat-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-muted small mb-2">Payments Received</div>
                            <h2 class="mb-0 fw-bold">&#8358;{{ number_format($stats['payments'] ?? 0, 2) }}</h2>
                            <small class="text-muted">{{ $stats['paid_payments'] ?? 0 }} successful payment(s)</small>
                        </div>
                        <div class="admin-stat-icon bg-info-subtle text-info">
                            <i class="bi bi-credit-card-2-front"></i>
                        </div>
                    </div>
                    <small class="text-muted">Total confirmed processing payments.</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card admin-table-card mb-4" data-aos="fade-up">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-1">Recent Payments</h5>
            <p class="text-muted mb-3">Latest successful payments recorded on the journal platform.</p>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Reference</th>
                            <th>Payer</th>
                            <th>Submission</th>
                            <th>Purpose</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th class="pe-4">Paid At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentPayments ?? [] as $payment)
                            <tr>
                                <td class="px-4 fw-semibold text-primary">{{ $payment->reference }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $payment->user->name ?? 'N/A' }}</div>
                                    <div class="small text-muted">{{ $payment->user->email ?? '' }}</div>
                                </td>
                                <td>{{ $payment->submission->submission_no ?? 'N/A' }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $payment->purpose)) }}</td>
                                <td class="fw-semibold">&#8358;{{ number_format($payment->amount, 2) }}</td>
                                <td><span class="badge bg-success">{{ ucfirst($payment->status ?? 'paid') }}</span></td>
                                <td class="pe-4">{{ $payment->paid_at?->format('d M Y, h:i A') ?? $payment->updated_at?->format('d M Y, h:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    No successful payments have been recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card admin-table-card" data-aos="fade-up">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-1">Recent Submissions</h5>
            <p class="text-muted mb-3">Latest manuscripts recently submitted to the journal.</p>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Submission No</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Files</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentSubmissions ?? [] as $submission)
                            @php
                                $primaryFile = $submission->files->firstWhere('file_type', 'manuscript') ?? $submission->files->first();
                                $statusColor = [
                                    'pending_payment' => 'warning',
                                    'submitted' => 'primary',
                                    'under_review' => 'info',
                                    'revision_requested' => 'warning',
                                    'accepted' => 'success',
                                    'rejected' => 'danger',
                                    'published' => 'dark',
                                ][$submission->status] ?? 'secondary';
                            @endphp
                            <tr>
                                <td class="px-4 fw-semibold">{{ $submission->submission_no }}</td>
                                <td>{{ Str::limit($submission->title, 50) }}</td>
                                <td>{{ $submission->author->name ?? 'N/A' }}</td>
                                <td class="small text-muted">{{ $submission->files->count() }} file(s)</td>
                                <td>
                                    <span class="badge bg-{{ $statusColor }}">
                                        {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                                    </span>
                                </td>
                                <td>{{ $submission->created_at?->format('d M Y') }}</td>
                                <td class="pe-4">
                                    <div class="d-inline-flex gap-2">
                                        @if($primaryFile)
                                        <a href="{{ route('admin.submissions.files.download', [$submission, $primaryFile]) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-download me-1"></i>Download Manuscript
                                        </a>
                                        @endif
                                        <a href="{{ route('admin.submissions.show', $submission) }}"
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    No recent submissions found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
