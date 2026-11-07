@extends('layouts.admin')

@section('page_title', 'Edit Authors - '.$article->title)

@section('content')
<div class="row g-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Correct Author Information</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('publication-authors.update', $article->slug) }}">
                    @method('PUT')
                    @csrf

                    <input type="hidden" name="correction_reason" value="{{ old('correction_reason') }}">

                    <div class="mb-3">
                        <label class="form-label">Correction Reason <span class="text-danger">*</span></label>
                        <textarea name="correction_reason" class="form-control @error('correction_reason') is-invalid @enderror" rows="3">{{ old('correction_reason') }}</textarea>
                        @error('correction_reason')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Lead Author Section -->
                    <div class="mb-4">
                        <h5>Lead Author (Order 1) <span class="text-muted">*</span></h5>
                        <hr>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="lead_author_name" class="form-control @error('lead_author_name') is-invalid @enderror"
                                           value="{{ old('lead_author_name', $leadAuthorName) }}"/>
                                    @error('lead_author_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="lead_author_email" class="form-control @error('lead_author_email') is-invalid @enderror"
                                           value="{{ old('lead_author_email', $leadAuthorEmail) }}"/>
                                    @error('lead_author_email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ORCID</label>
                                    <input type="text" name="lead_author_orcid" class="form-control"
                                           value="{{ old('lead_author_orcid', $leadAuthorOrcid ?? '') }}"/>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Institution</label>
                                    <input type="text" name="lead_author_institution" class="form-control"
                                           value="{{ old('lead_author_institution', $leadAuthorInstitution) }}"/>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Country</label>
                                    <input type="text" name="lead_author_country" class="form-control"
                                           value="{{ old('lead_author_country', $leadAuthorCountry) }}"/>
                                </div>
                            </div>
                        </div>

                        <p class="text-small text-muted mt-2">
                            The lead author is marked as corresponding author. This is the primary author of the article.
                        </p>
                    </div>

                    <!-- Co-Authors Table -->
                    <div class="mb-4">
                        <h5>Co-Authors</h5>
                        <p class="text-small text-muted">
                            Add, edit, or remove co-authors. The lead author is already set above.
                        </p>

                        <!-- Existing co-authors table -->
                        @if($existingAuthors->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Institution</th>
                                        <th>Country</th>
                                        <th>Corresponding</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($existingAuthors as $idx => $author)
                                    <tr>
                                        <td>{{ $author['order'] }}</td>
                                        <td>
                                            <input type="text" name="authors[{{ $author['order'] }}][full_name]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $author['full_name'] }}"
                                                   placeholder="Click to edit">
                                            <input type="hidden" name="authors[{{ $author['order'] }}][id]"
                                                   value="{{ $author['id'] }}">
                                            <input type="hidden" name="authors[{{ $author['order'] }}][order]"
                                                   value="{{ $author['order'] }}">
                                        </td>
                                        <td>
                                            <input type="email" name="authors[{{ $author['order'] }}][email]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $author['email'] }}">
                                        </td>
                                        <td>
                                            <input type="email" name="authors[{{ $author['order'] }}][email]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $author['email'] }}">
                                        </td>
                                        <td>
                                            <input type="text" name="authors[{{ $author['order'] }}][institution]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $author['institution'] }}">
                                        </td>
                                        <td>
                                            <input type="text" name="authors[{{ $author['order'] }}][country]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $author['country'] }}">
                                        </td>
                                        <td>
                                            <input type="text" name="authors[{{ $author['order'] }}][orcid]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $author['orcid'] }}">
                                        </td>
                                        <td>
                                            @if($author['is_corresponding'])
                                            <span class="badge bg-primary">Yes</span>
                                            @else
                                            <span class="badge bg-secondary">No</span>
                                            @endif
                                        </td>
                                        <td>
                                            <form method="POST" action="{{ route('publication-authors.destroy', [$article->slug, $author['id']]) }}"
                                                  onsubmit="return confirm('Are you sure you want to remove co-author: {{ $author['full_name'] }}?');">
                                                @method('DELETE')
                                                @csrf
                                                <button type="submit" class="btn btn-danger btn-sm"
                                                        title="Remove co-author">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <p class="text-muted small">No co-authors currently recorded.</p>
                        @endif

                        <!-- Add New Co-Author Form -->
                        <div class="mt-3">
                            <h6>Add New Co-Author</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <input type="text" name="new_author_name"
                                           class="form-control form-control-sm"
                                           placeholder="Full name">
                                </div>
                                <div class="col-md-3">
                                    <input type="email" name="new_author_email"
                                           class="form-control form-control-sm"
                                           placeholder="Email">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="new_author_institution"
                                           class="form-control form-control-sm"
                                           placeholder="Institution">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" name="new_author_orcid"
                                           class="form-control form-control-sm"
                                           placeholder="ORCID">
                                </div>
                                <div class="col-md-1">
                                    <button type="submit"
                                            name="add_new_author"
                                            class="btn btn-primary btn-sm w-100">
                                        <i class="bi bi-person-plus me-1"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5>Current Article Status</h5>
            </div>
            <div class="card-body p-3">
                <p><strong>Article:</strong> {{ $article->title }}</p>
                <p><strong>Status:</strong> {{ $article->status }}</p>
                @if($article->doi)
                <p><strong>DOI:</strong> <a href="https://doi.org/{{ $article->doi }}" target="_blank">{{ $article->doi }}</a></p>
                @endif
                @if($article->submission)
                <p><strong>Submission:</strong> {{ $article->submission->submission_no }}</p>
                @endif
                @if($article->author)
                <p><strong>Original Author ID:</strong> {{ $article->author->id }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
@empty
<div class="alert alert-info">
    No article found.
</div>
@endsection