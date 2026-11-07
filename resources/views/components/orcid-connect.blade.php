@props([
    'user' => auth()->user(),
    'enabled' => app(\App\Services\Orcid\OrcidSettings::class)->enabled(),
    'allowDisconnect' => false,
    'compact' => false,
    'returnTo' => request()->getRequestUri(),
])

@php
    $connection = $user?->orcidConnection()->first();
    $verified = $connection !== null
        && $connection->isConnected()
        && $connection->orcid_id === $user?->orcid;
    $orcid = $verified ? $connection->orcid_id : null;
    $profileUrl = $orcid !== null
        ? app(\App\Services\Orcid\OrcidSettings::class)->profileUrl($orcid)
        : null;
@endphp

@if($enabled)
<div {{ $attributes->merge(['class' => 'card border-0 shadow-sm orcid-card'.($compact ? ' orcid-card--compact' : '')]) }}>
    <div class="card-body {{ $compact ? 'p-3' : 'p-4' }}">
        @if(session('orcid_error'))
            <div class="alert alert-danger d-flex align-items-start gap-2 py-2 px-3 mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                <div class="small">
                    <strong>ORCID authorisation did not complete.</strong><br>
                    {{ session('orcid_error') }}
                </div>
            </div>
        @endif

        @if($verified)
            <div class="d-flex align-items-start gap-3">
                <x-orcid-icon :size="$compact ? 28 : 36" />
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="fw-bold">ORCID iD connected</span>
                        <span class="badge rounded-pill text-bg-success">
                            <i class="bi bi-patch-check-fill me-1"></i>Verified
                        </span>
                    </div>
                    <div class="mt-1">
                        <a href="{{ $profileUrl }}" target="_blank" rel="noopener noreferrer"
                           class="fw-semibold text-decoration-none orcid-id-link">{{ $orcid }}</a>
                    </div>
                    <div class="small text-muted mt-1">
                        Verified via ORCID on {{ $connection->authenticated_at?->format('j M Y') }}.
                        <a href="{{ $profileUrl }}" target="_blank" rel="noopener noreferrer">View ORCID profile</a>
                    </div>
                </div>
            </div>

            @if($allowDisconnect)
                <hr class="my-3">
                <details class="small">
                    <summary class="text-muted" style="cursor:pointer">Disconnect this ORCID iD</summary>
                    <form method="POST" action="{{ route('orcid.disconnect') }}" class="mt-2"
                          onsubmit="return confirm('Disconnect your ORCID iD from this account? Your manuscripts and publications will not change.');">
                        @csrf
                        @method('DELETE')
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="confirm" value="1" id="orcid-disconnect-confirm" required>
                            <label class="form-check-label" for="orcid-disconnect-confirm">
                                I understand my published byline will keep its current ORCID display value.
                            </label>
                        </div>
                        <button type="submit" class="btn btn-outline-danger btn-sm mt-2">Disconnect</button>
                    </form>
                </details>
            @endif
        @else
            <div class="d-flex align-items-start gap-3">
                <x-orcid-icon :size="$compact ? 28 : 36" />
                <div class="flex-grow-1">
                    <h2 class="h6 fw-bold mb-1">Connect your ORCID iD</h2>
                    <p class="text-muted small mb-3">Connect your ORCID account to your OJMAS researcher profile.</p>

                    @if(filled($user?->orcid))
                        <div class="alert alert-warning py-2 px-3 small mb-3">
                            Saved ORCID iD: <span class="fw-semibold">{{ $user->orcid }}</span>.
                            It is <strong>not verified</strong> yet. Authenticate with ORCID to prove ownership.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('orcid.connect') }}" class="d-inline-block">
                        @csrf
                        <input type="hidden" name="return_to" value="{{ $returnTo }}">
                        <button type="submit" class="btn orcid-btn fw-semibold d-inline-flex align-items-center gap-2">
                            <x-orcid-icon :size="18" />
                            Authenticate with ORCID
                        </button>
                    </form>

                    <div class="small text-muted mt-2">
                        Don't have an ORCID iD?
                        <a href="https://orcid.org/register" target="_blank" rel="noopener noreferrer">Register here</a>
                    </div>
                    <div class="small text-muted mt-1">
                        OJMAS never asks for your ORCID password; authentication happens on orcid.org.
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

@once
    <style>
        .orcid-card { border-left: 4px solid #A6CE39; }
        .orcid-btn { background: #A6CE39; color: #1f2a0b; border: 1px solid #95bb2f; }
        .orcid-btn:hover, .orcid-btn:focus { background: #95bb2f; color: #111a05; }
        .orcid-id-link { color: #527a00; }
    </style>
@endonce
@endif
