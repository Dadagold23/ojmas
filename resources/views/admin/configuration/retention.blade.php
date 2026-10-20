@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-light text-danger px-3 py-2 mb-2">System configuration</span>
            <h3 class="fw-bold mb-1">Document retention</h3>
            <p class="text-muted mb-0">
                How long each class of record is kept. Deleting anything is a separate, audited operation —
                this screen never removes a file.
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success rounded-4 mb-4">{{ session('success') }}</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">{{ $policy->name }}
                    <small class="text-muted fw-normal">· {{ $policy->slug }}</small>
                </h6>
                <span class="badge {{ $policy->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                    {{ $policy->is_active ? 'Active policy' : 'Inactive' }}
                </span>
            </div>

            <form method="POST" action="{{ route('admin.configuration.retention.update', $policy) }}">
                @csrf
                @method('PUT')

                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Original submissions (days)</label>
                        <input type="number" name="retain_original_days" value="{{ $policy->retain_original_days }}" min="1" max="36500" class="form-control form-control-sm">
                        <div class="form-text">The scholarly record of what was submitted.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Formatted documents (days)</label>
                        <input type="number" name="retain_formatted_days" value="{{ $policy->retain_formatted_days }}" min="1" max="36500" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Workspace versions (days)</label>
                        <input type="number" name="retain_workspace_days" value="{{ $policy->retain_workspace_days }}" min="1" max="36500" class="form-control form-control-sm">
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Review records (days)</label>
                        <input type="number" name="retain_review_days" value="{{ $policy->retain_review_days }}" min="1" max="36500" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Audit trail (days)</label>
                        <input type="number" name="retain_audit_days" value="{{ $policy->retain_audit_days }}" min="365" max="36500" class="form-control form-control-sm" required>
                        <div class="form-text">Minimum one year, by policy; the journal keeps seven.</div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input type="hidden" name="delete_formatted_on_publication" value="0">
                            <input type="checkbox" name="delete_formatted_on_publication" value="1" id="del-formatted" class="form-check-input" @checked($policy->delete_formatted_on_publication)>
                            <label class="form-check-label small" for="del-formatted">Discard formatted copies on publication</label>
                        </div>
                        <div class="form-check form-switch">
                            <input type="hidden" name="anonymise_after_review" value="0">
                            <input type="checkbox" name="anonymise_after_review" value="1" id="anon-after" class="form-check-input" @checked($policy->anonymise_after_review)>
                            <label class="form-check-label small" for="anon-after">Anonymise material after review</label>
                        </div>
                    </div>
                </div>

                <button class="btn btn-primary btn-sm">Save retention policy</button>
            </form>
        </div>
    </div>

    @if($policies->count() > 1)
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">All policies on record</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Name</th><th>Original</th><th>Formatted</th><th>Workspace</th><th>Review</th><th>Audit</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach($policies as $entry)
                                <tr>
                                    <td class="small fw-semibold">{{ $entry->name }}</td>
                                    <td class="small">{{ $entry->labelFor($entry->retain_original_days) }}</td>
                                    <td class="small">{{ $entry->labelFor($entry->retain_formatted_days) }}</td>
                                    <td class="small">{{ $entry->labelFor($entry->retain_workspace_days) }}</td>
                                    <td class="small">{{ $entry->labelFor($entry->retain_review_days) }}</td>
                                    <td class="small">{{ $entry->labelFor($entry->retain_audit_days) }}</td>
                                    <td class="small">
                                        <span class="badge {{ $entry->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $entry->is_active ? 'active' : 'inactive' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
