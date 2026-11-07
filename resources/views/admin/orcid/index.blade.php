@extends('layouts.app')

@section('content')
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .admin-hero { border: none; border-radius: 1.5rem;
        background: linear-gradient(135deg, rgba(166,206,57,.95), rgba(15,23,42,.92));
        color:#fff; box-shadow: 0 20px 50px rgba(15,23,42,.16); }
    .stat-value { font-size: 1.75rem; font-weight: 800; color: #0f172a; }
    .stat-label { font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; font-weight: 600; }
</style>

<div class="container py-4">

    <div class="card admin-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="fw-bold mb-2 d-flex align-items-center gap-2">
                        <x-orcid-icon :size="32" /> ORCID Integration
                    </h2>
                    <p class="mb-0 text-white-50">
                        Control verified ORCID sign-in, review linked identities, and correct byline associations.
                        The OAuth client secret stays in the environment and is never shown here.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <span class="badge rounded-pill {{ $credentialsConfigured ? 'text-bg-success' : 'text-bg-warning' }} px-3 py-2">
                        <i class="bi bi-{{ $credentialsConfigured ? 'check-circle-fill' : 'exclamation-triangle-fill' }} me-1"></i>
                        {{ $credentialsConfigured ? 'Credentials configured' : 'Credentials missing' }}
                    </span>
                    <div class="small text-white-50 mt-2">Active host: {{ ucfirst($activeHost) }}</div>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4">
        {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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

    @unless($credentialsConfigured)
    <div class="alert alert-warning rounded-4 mb-4">
        <strong>ORCID cannot be enabled yet.</strong>
        Set <code>ORCID_CLIENT_ID</code> and <code>ORCID_CLIENT_SECRET</code> in the environment, then clear the
        configuration cache. Register this exact callback URL in ORCID Developer Tools:
        <code>{{ $callbackUrl }}</code>
    </div>
    @endunless

    <div class="row g-3 mb-4" data-aos="fade-up">
        @foreach([
            ['Verified accounts', $stats['connected'], 'person-check'],
            ['Accounts without verified ORCID', $stats['accounts_without_verified'], 'person-dash'],
            ['Verified bylines', $stats['verified_bylines'], 'journal-check'],
            ['Pending co-author links', $stats['pending_claims'], 'link-45deg'],
            ['Failed attempts (30d)', $stats['failed_attempts_30d'], 'shield-exclamation'],
        ] as [$label, $value, $icon])
        <div class="col-6 col-lg">
            <div class="card panel-card h-100">
                <div class="card-body p-3">
                    <div class="stat-label mb-1">{{ $label }}</div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="stat-value">{{ number_format($value) }}</span>
                        <i class="bi bi-{{ $icon }} fs-4 text-muted"></i>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-lg-5" data-aos="fade-up">
            <div class="card panel-card mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-sliders me-2 text-primary"></i>Integration settings</h5>

                    <form method="POST" action="{{ route('admin.orcid.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="orcid_enabled"
                                   name="orcid_enabled" value="1" @checked($setting->orcid_enabled)
                                   @disabled(! $credentialsConfigured)>
                            <label class="form-check-label" for="orcid_enabled">
                                <strong>Enable ORCID sign-in</strong>
                                <div class="small text-muted">Shows the connect prompt to signed-in users.</div>
                            </label>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="orcid_sandbox"
                                   name="orcid_sandbox" value="1" @checked($setting->orcid_sandbox)>
                            <label class="form-check-label" for="orcid_sandbox">
                                <strong>Use the ORCID sandbox</strong>
                                <div class="small text-muted">Test against sandbox.orcid.org. Identities from the sandbox are not production iDs.</div>
                            </label>
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" role="switch" id="orcid_required_for_new_authors"
                                   name="orcid_required_for_new_authors" value="1" @checked($setting->orcid_required_for_new_authors)>
                            <label class="form-check-label" for="orcid_required_for_new_authors">
                                <strong>Require a verified ORCID iD for new submissions</strong>
                                <div class="small text-muted">Only applies while ORCID sign-in is enabled, so authors are never blocked without a way to verify. Existing manuscripts and already-published bylines are never blocked or changed.</div>
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary fw-semibold" @disabled(! $credentialsConfigured)>
                            <i class="bi bi-save me-1"></i>Save settings
                        </button>
                    </form>
                </div>
            </div>

            <div class="card panel-card">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-link-45deg me-2 text-primary"></i>OAuth callback</h6>
                    <p class="small text-muted mb-2">This value must match the redirect URI registered with ORCID.</p>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" value="{{ $callbackUrl }}" readonly
                               id="orcid-callback-url" onfocus="this.select()">
                        <button class="btn btn-outline-secondary" type="button"
                                onclick="navigator.clipboard.writeText(document.getElementById('orcid-callback-url').value)">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                    <p class="small text-muted mt-3 mb-0">
                        Requested scope: <code>/authenticate</code>. OJMAS never receives the user's ORCID password.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-7" data-aos="fade-up">
            <div class="card panel-card mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-person-check me-2 text-success"></i>Verified connections</h5>

                    @if($connections->isEmpty())
                        <p class="text-muted small mb-0">No OJMAS account has connected an ORCID iD yet.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Account</th>
                                    <th>ORCID</th>
                                    <th>Authenticated</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($connections as $connection)
                                <tr>
                                    <td>
                                        <div class="fw-semibold small">{{ $connection->user?->name ?? 'Unknown account' }}</div>
                                        <div class="text-muted" style="font-size:.78rem">{{ $connection->user?->email }}</div>
                                    </td>
                                    <td>
                                        <a href="https://orcid.org/{{ $connection->orcid_id }}" target="_blank" rel="noopener noreferrer"
                                           class="small text-decoration-none d-inline-flex align-items-center gap-1">
                                            <x-orcid-icon :size="14" /> {{ $connection->orcid_id }}
                                        </a>
                                    </td>
                                    <td class="small text-muted">{{ $connection->authenticated_at?->format('j M Y') ?? '—' }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('admin.orcid.connections.release', $connection) }}"
                                              onsubmit="return confirm('Release this ORCID iD from the account? The journal record is not changed.');">
                                            @csrf
                                            <input type="hidden" name="reason" value="administrator_release">
                                            <button class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size:.75rem">Release</button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $connections->links() }}</div>
                    @endif
                </div>
            </div>

            <div class="card panel-card mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-hourglass-split me-2 text-warning"></i>Pending co-author links</h5>

                    @if($pendingClaims->isEmpty())
                        <p class="text-muted small mb-0">No outstanding verification links.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Co-author</th>
                                    <th>Submission</th>
                                    <th>Expires</th>
                                    <th class="text-end">Link / associate</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pendingClaims as $claim)
                                @php($author = $claim->articleAuthor)
                                <tr>
                                    <td>
                                        <div class="fw-semibold small">{{ $author?->full_name ?? 'Removed co-author' }}</div>
                                        <div class="text-muted" style="font-size:.78rem">{{ $author?->email }}</div>
                                    </td>
                                    <td class="small text-muted">{{ $author?->submission?->submission_no ?? '—' }}</td>
                                    <td class="small text-muted">{{ $claim->expires_at?->format('j M Y') }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('orcid.coauthor.show', ['token' => $claim->token]) }}"
                                           target="_blank" rel="noopener noreferrer"
                                           class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:.75rem">
                                            Open link
                                        </a>
                                        @if($author && $connections->isNotEmpty())
                                        <form method="POST" action="{{ route('admin.orcid.authors.link', $author) }}"
                                              class="d-inline-flex gap-1 mt-2">
                                            @csrf
                                            <select name="user_id" class="form-select form-select-sm" style="max-width:200px" required>
                                                <option value="">Associate verified account…</option>
                                                @foreach($connections as $connection)
                                                <option value="{{ $connection->user_id }}">
                                                    {{ $connection->user?->name }} ({{ $connection->orcid_id }})
                                                </option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-outline-success btn-sm py-0 px-2" style="font-size:.75rem">Link</button>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>

            <div class="card panel-card">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-person-dash me-2 text-secondary"></i>Accounts without a verified ORCID</h5>

                    @if($unverifiedUsers->isEmpty())
                        <p class="text-muted small mb-0">Every account has a verified ORCID iD.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Account</th>
                                    <th>Typed ORCID (unverified)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($unverifiedUsers as $user)
                                <tr>
                                    <td>
                                        <div class="fw-semibold small">{{ $user->name }}</div>
                                        <div class="text-muted" style="font-size:.78rem">{{ $user->email }}</div>
                                    </td>
                                    <td class="small text-muted">{{ $user->orcid ?: '—' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $unverifiedUsers->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
