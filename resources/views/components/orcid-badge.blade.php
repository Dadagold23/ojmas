@props(['orcid', 'size' => 16, 'showId' => true])

@if(filled($orcid))
    <a href="https://orcid.org/{{ $orcid }}" target="_blank" rel="noopener noreferrer"
       class="text-decoration-none d-inline-flex align-items-center gap-1"
       title="Verified ORCID iD {{ $orcid }}"
       aria-label="Verified ORCID iD {{ $orcid }}">
        <x-orcid-icon :size="$size" />
        @if($showId)
            <span class="small">{{ $orcid }}</span>
        @endif
    </a>
@endif
