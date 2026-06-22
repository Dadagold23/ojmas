@extends('layouts.app')

@section('content')
<style>
    .diag-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .diag-ok { color: #198754; }
    .diag-bad { color: #dc3545; }
</style>

<div class="container py-4" style="max-width: 980px">
    <div class="d-flex justify-content-between align-items-center mb-4" data-aos="fade-up">
        <div>
            <h3 class="fw-bold mb-1">Upload Diagnostics</h3>
            <p class="text-muted mb-0">Live server checks for manuscript upload limits and writable storage paths.</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>

    <div class="card diag-card" data-aos="fade-up">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Check</th>
                            <th>Value</th>
                            <th class="pe-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($checks as $check)
                        <tr>
                            <td class="px-4 fw-semibold">{{ $check['label'] }}</td>
                            <td><code>{{ $check['value'] }}</code></td>
                            <td class="pe-4">
                                @if($check['ok'])
                                <span class="diag-ok fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>OK</span>
                                @else
                                <span class="diag-bad fw-semibold"><i class="bi bi-x-circle-fill me-1"></i>Problem</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card diag-card mt-4" data-aos="fade-up">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-2">Upload Probe</h5>
            <p class="text-muted mb-3">Upload a small test file here. This checks what PHP reports for the live request before the normal manuscript flow runs.</p>

            <form method="POST" action="{{ route('admin.upload-diagnostics.run') }}" enctype="multipart/form-data" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Probe File</label>
                    <input type="file" name="probe_file" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-warning w-100 fw-semibold">
                        <i class="bi bi-bug me-1"></i>Run Upload Probe
                    </button>
                </div>
            </form>

            @if(session('upload_probe_result'))
            @php($probe = session('upload_probe_result'))
            <div class="table-responsive mt-4">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Probe Check</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-semibold">CONTENT_LENGTH</td>
                            <td><code>{{ $probe['content_length'] ?? 'unknown' }}</code></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">post_max_size</td>
                            <td><code>{{ $probe['post_max_size'] ?? 'unknown' }}</code></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">upload_max_filesize</td>
                            <td><code>{{ $probe['upload_max_filesize'] ?? 'unknown' }}</code></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">PHP saw file?</td>
                            <td><code>{{ $probe['has_file'] ?? 'no' }}</code></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">PHP file error code</td>
                            <td><code>{{ $probe['php_file_error'] ?? 'unknown' }}</code> - <code>{{ $probe['php_file_error_label'] ?? 'unknown' }}</code></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Client file name</td>
                            <td><code>{{ $probe['client_name'] ?? 'n/a' }}</code></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Client file size</td>
                            <td><code>{{ $probe['client_size'] ?? 'n/a' }}</code></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Client mime type</td>
                            <td><code>{{ $probe['client_mime'] ?? 'n/a' }}</code></td>
                        </tr>
                        @if(array_key_exists('is_valid', $probe))
                        <tr>
                            <td class="fw-semibold">Laravel file valid?</td>
                            <td><code>{{ $probe['is_valid'] }}</code></td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    <div class="alert alert-info rounded-4 mt-4 mb-0" data-aos="fade-up">
        Open this page on production after deploy. Any row marked <strong>Problem</strong> is the current blocker for manuscript uploads.
    </div>
</div>
@endsection
