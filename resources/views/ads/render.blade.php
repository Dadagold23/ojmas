{{-- Public advertisement rendering. Every campaign is rendered through these
     controlled templates only: uploaded media and approved external URLs are
     the only things ever embedded, and every paid item carries its label.
     Impressions are recorded by the serving service, not by beacon scripts. --}}

@if(!empty($campaigns))
    <div class="ads-slot ads-slot-{{ $placementKey }} my-3" aria-label="{{ $placementLabel }}">
        @foreach($campaigns as $adCampaign)
            <div class="ads-item border rounded-4 p-3 mb-3 bg-light">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-secondary-subtle text-secondary-emphasis text-uppercase" style="font-size:.7rem;">
                        {{ $adCampaign->label }}
                    </span>
                    @if($adCampaign->advertiser_name)
                        <span class="text-muted" style="font-size:.75rem;">{{ $adCampaign->advertiser_name }}</span>
                    @endif
                </div>

                @if($adCampaign->media_type === 'video' && $adCampaign->media_path)
                    <video class="w-100 rounded-3" style="max-height: 320px;" controls preload="metadata"
                           src="{{ \Illuminate\Support\Facades\Storage::url($adCampaign->media_path) }}"></video>
                @elseif($adCampaign->media_type === 'audio' && $adCampaign->media_path)
                    <audio class="w-100" controls preload="metadata"
                           src="{{ \Illuminate\Support\Facades\Storage::url($adCampaign->media_path) }}"></audio>
                @elseif($adCampaign->media_path)
                    <img class="img-fluid rounded-3 w-100"
                         style="max-height: 320px; object-fit: cover;"
                         src="{{ \Illuminate\Support\Facades\Storage::url($adCampaign->media_path) }}"
                         alt="{{ $adCampaign->title }}">
                @elseif($adCampaign->external_media_url)
                    <img class="img-fluid rounded-3 w-100"
                         style="max-height: 320px; object-fit: cover;"
                         src="{{ $adCampaign->external_media_url }}"
                         alt="{{ $adCampaign->title }}">
                @endif

                <div class="mt-2">
                    <div class="fw-semibold">{{ $adCampaign->title }}</div>
                    @if($adCampaign->description)
                        <div class="small text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($adCampaign->description), 240) }}</div>
                    @endif
                </div>

                @if($adCampaign->destination_url)
                    <a href="{{ route('ads.click', $adCampaign) }}"
                       class="btn btn-sm btn-outline-primary mt-2">
                        {{ $adCampaign->type === 'call_for_papers' ? 'Read the call' : 'Learn more' }}
                        <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                @endif
            </div>
        @endforeach
    </div>
@endif
