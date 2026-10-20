@extends('layouts.app')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-light text-primary px-3 py-2 mb-2">
                <i class="bi bi-magic me-1"></i>APA 7 Automated Formatting
            </span>
            <h3 class="fw-bold mb-1">{{ $submission->submission_no }}</h3>
            <p class="text-muted mb-0">{{ $submission->title }}</p>
        </div>
        <a href="{{ route('author.submissions.show', $submission) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to submission
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show rounded-4 mb-4">{{ session('warning') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-4 mb-4">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    @if($versions->isEmpty())
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4 p-lg-5 text-center">
                <i class="bi bi-file-earmark-word" style="font-size:3rem;opacity:.2;"></i>
                <h5 class="fw-bold mt-3">No Word manuscript uploaded yet</h5>
                <p class="text-muted mb-0">
                    Upload a Microsoft Word <strong>.docx</strong> file to have it checked and reformatted to the
                    OJMAS submission profile. PDF and legacy .doc files cannot be reformatted automatically because
                    they carry no editable source structure.
                </p>
                <a href="{{ route('author.submissions.show', $submission) }}" class="btn btn-primary mt-3">
                    Upload a manuscript
                </a>
            </div>
        </div>
    @else
        <div class="row g-4">
            <div class="col-lg-8">

                {{-- Summary --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-clipboard-data me-2 text-primary"></i>Formatting report</h5>

                        @if($validation)
                            @php
                                $summary = $validation->report_json['summary'] ?? [];
                            @endphp
                            <div class="row g-3 mb-3">
                                <div class="col-6 col-md-3">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="text-muted small">Corrected automatically</div>
                                        <div class="fs-4 fw-bold text-success">{{ $summary['auto_fixed'] ?? 0 }}</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="text-muted small">Errors</div>
                                        <div class="fs-4 fw-bold text-danger">{{ $summary['errors'] ?? 0 }}</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="text-muted small">Needs your attention</div>
                                        <div class="fs-4 fw-bold text-warning">{{ $summary['warnings'] ?? 0 }}</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="text-muted small">Preserved as-is</div>
                                        <div class="fs-4 fw-bold text-secondary">{{ $summary['unsupported_elements'] ?? 0 }}</div>
                                    </div>
                                </div>
                            </div>

                            <p class="text-muted small mb-3">
                                Rules applied:
                                <strong>{{ data_get($validation->report_json, 'profile.name', 'OJMAS Submission Profile') }}</strong>
                                (version {{ $validation->profile_version }}).
                                Your original file is never replaced.
                            </p>

                            <a href="{{ route('author.manuscripts.report', [$submission, $validation]) }}"
                               class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-list-check me-1"></i>View the full report
                            </a>
                        @else
                            <p class="text-muted mb-3">
                                No formatting run has been performed yet. The check is read-only and takes a few seconds.
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Actions --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-lightning me-2 text-primary"></i>Actions</h6>

                        <div class="d-flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('author.manuscripts.format', $submission) }}">
                                @csrf
                                <button class="btn btn-primary btn-sm">
                                    <i class="bi bi-magic me-1"></i>
                                    {{ $validation ? 'Run the check again' : 'Check and format my manuscript' }}
                                </button>
                            </form>

                            @if($formattedPath)
                                <a href="{{ route('author.manuscripts.download', $submission) }}" class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-download me-1"></i>Download the formatted DOCX
                                </a>
                            @endif

                            @if($validation && $validation->isCompleted() && ! $validation->isApproved())
                                <form method="POST" action="{{ route('author.manuscripts.approve', $submission) }}">
                                    @csrf
                                    <button class="btn btn-success btn-sm">
                                        <i class="bi bi-check2-circle me-1"></i>I have reviewed and approve this version
                                    </button>
                                </form>
                            @elseif($validation && $validation->isApproved())
                                <span class="btn btn-success btn-sm disabled">
                                    <i class="bi bi-patch-check me-1"></i>Approved {{ $validation->approved_at?->format('d M Y, H:i') }}
                                </span>
                            @endif
                        </div>

                        <p class="text-muted small mt-3 mb-0">
                            Formatting never changes what your research says. It corrects page layout, spacing,
                            indentation and reference presentation, and reports everything else for you to decide.
                        </p>
                    </div>
                </div>

                {{-- Versions --}}
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-primary"></i>Manuscript versions</h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Version</th>
                                        <th>Uploaded</th>
                                        <th>Checksum</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($versions as $version)
                                        <tr>
                                            <td class="fw-semibold">{{ $version->label }}</td>
                                            <td>{{ $version->created_at?->format('d M Y, H:i') }}</td>
                                            <td><code class="small">{{ substr($version->sha256, 0, 12) }}…</code></td>
                                            <td>
                                                @if($version->document?->hasParsedSuccessfully())
                                                    <span class="badge text-bg-success">Checked</span>
                                                @elseif($version->document?->status === 'failed')
                                                    <span class="badge text-bg-danger">Failed</span>
                                                @else
                                                    <span class="badge text-bg-secondary">Pending</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2 text-primary"></i>What gets checked</h6>
                        <ul class="small text-muted mb-0" style="padding-left:1.1rem;line-height:1.9;">
                            <li>A4 paper with 1&nbsp;inch margins</li>
                            <li>Double spacing and 0.5&nbsp;inch paragraph indents</li>
                            <li>Title within 18 words</li>
                            <li>Abstract on a separate sheet, 150 words or fewer, without author names</li>
                            <li>APA heading levels 1&ndash;5</li>
                            <li>Hanging indents and alphabetical reference order</li>
                            <li>Citations matched to reference entries</li>
                            <li>Complete DOIs and page ranges</li>
                        </ul>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2 text-primary"></i>Your original is safe</h6>
                        <p class="small text-muted mb-0">
                            The formatting engine creates a <strong>new</strong> document. Your uploaded file is kept
                            exactly as you submitted it, together with a checksum, and is never overwritten or
                            replaced. Nothing is deleted even if the check fails.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection