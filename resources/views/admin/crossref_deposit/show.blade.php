@extends('layouts.admin')

@section('page_title', 'Crossref Deposit - '.$article->title)

@section('content')
<div class="row g-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Crossref Metadata Deposition</h4>
            </div>
            <div class="card-body">
                @if($result['status'] ?? old('status') === 'success')
                    <div class="alert alert-success">
                        <h5><i class="bi bi-check-circle me-2"></i>Success</h5>
                        <p>Crossref metadata successfully deposited for:</p>
                        <p><strong>"{{ $article->title }}"</strong></p>
                        <p><strong>DOI:</strong> <a href="https://doi.org/{{ $article->doi }}" target="_blank">{{ $article->doi }}</a></p>
                        @if(old('deposit_event_id'))
                            <p><strong>Crossref Event ID:</strong> {{ old('deposit_event_id') }}</p>
                        @endif
                        <p>The article's DOI is preserved. Only author-related metadata (order, affiliations, correspondence) was updated.</p>
                    </div>
                @elseif(old('status') === 'error')
                    <div class="alert alert-danger">
                        <h5><i class="bi bi-exclamation-circle me-2"></i>Deposition Failed</h5>
                        <p>Crossref deposition failed:</p>
                        <p><strong>{{ old('message') }}</strong></p>
                        @if(old('deposit_error'))
                            <p class="text-muted small">The deposit was attempted but Crossref returned an error. Check the deposit logs for more details.</p>
                        @endif
                    </div>
                @endif

                {{-- Deposition Form --}}
                @if(!$result['status'] ?? old('status') !== 'success')
                    <form method="POST" action="{{ route('publications.crossref-deposit', $article->slug) }}">
                        @method('POST')
                        @csrf

                        <input type="hidden" name="confirm_deposit" value="1">

                        <div class="mb-3">
                            <h5>Confirm Crossref Deposition</h5>
                            <p class="text-muted small">
                                This will redeposit the article's metadata to Crossref.
                                Only author-related fields will be updated (author order, 
                                affiliations, corresponding author status). The DOI {{ $article->doi 
                                    ? 'is preserved' : 'will be generated' }}.
                            </p>

                            {{-- Article eligibility --}}
                            @if(empty($article->doi))
                                <p class="text-danger"><strong>This article has no DOI.</strong> Crossref deposition cannot proceed without a registered DOI.</p>
                            @endif

                            {{-- Recent deposit history --}}
                            @if(!empty($recentDeposits))
                                <h6>Recent Deposit Attempts</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Status</th>
                                                <th>Error</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($recentDeposits as $deposit)
                                                <tr>
                                                    <td>{{ $deposit->initiated_at->format('d M Y H:i') }}</td>
                                                    <td>
                                                        <span class="badge {{ $deposit->status === 'success' ? 'bg-success' : 'bg-danger' }}">
                                                            {{ ucfirst($deposit->status) }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        @if($deposit->error_message)
                                                            <div class="small text-muted truncate-1-line">
                                                                {{ substr($deposit->error_message, 0, 100) }}...
                                                            </div>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            {{-- Confirmation --}}
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send me-2"></i>Deposit to Crossref
                                </button>
                                <a href="{{ route('publications.show', $article->slug) }}" class="btn btn-link text-muted">
                                    <i class="bi bi-x me-2"></i>Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5>Article Details</h5>
            </div>
            <div class="card-body p-3">
                <p><strong>Title:</strong> {{ $article->title }}</p>
                <p><strong>Slug:</strong> {{ $article->slug }}</p>
                <p><strong>DOI:</strong> 
                    @if($article->doi)
                        <a href="https://doi.org/{{ $article->doi }}" target="_blank">{{ $article->doi }}</a>
                    @else
                        <span class="text-danger">No DOI registered</span>
                    @endif
                </p>
                <p><strong>Status:</strong> {{ ucfirst($article->status) }}</p>
                @if($article->submission)
                <p><strong>Submission:</strong> {{ $article->submission->submission_no }}</p>
                @endif
                @if($article->published_on)
                <p><strong>Published:</strong> {{ $article->published_on->format('d M Y') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection