{{-- Shared campaign form fields (create and edit). Media typing follows the
     campaign type; every URL field is scheme-checked server-side. --}}

<div class="mb-3">
    <label class="form-label fw-semibold">Campaign title <span class="text-danger">*</span></label>
    <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
           value="{{ old('title', $campaign->title ?? '') }}" required>
    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">Description</label>
    <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror"
              placeholder="What this campaign communicates...">{{ old('description', $campaign->description ?? '') }}</textarea>
    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Campaign type <span class="text-danger">*</span></label>
        <select name="type" class="form-select @error('type') is-invalid @enderror">
            @foreach(\App\Models\AdCampaign::TYPES as $typeKey => $typeLabel)
                <option value="{{ $typeKey }}" @selected(old('type', $campaign->type ?? 'banner') === $typeKey)>{{ $typeLabel }}</option>
            @endforeach
        </select>
        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Placement <span class="text-danger">*</span></label>
        <select name="placement_id" class="form-select @error('placement_id') is-invalid @enderror" required>
            @foreach($placements as $placement)
                <option value="{{ $placement->id }}" @selected(old('placement_id', $campaign->placement_id ?? '') == $placement->id)>
                    {{ $placement->label }}{{ $placement->is_active ? '' : ' (inactive)' }}
                </option>
            @endforeach
        </select>
        @error('placement_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Advertiser / sponsor name</label>
        <input type="text" name="advertiser_name" class="form-control"
               value="{{ old('advertiser_name', $campaign->advertiser_name ?? '') }}"
               placeholder="Leave empty for internal announcements">
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Audience <span class="text-danger">*</span></label>
        <select name="audience" class="form-select @error('audience') is-invalid @enderror">
            @foreach(\App\Models\AdCampaign::AUDIENCES as $audienceKey => $audienceLabel)
                <option value="{{ $audienceKey }}" @selected(old('audience', $campaign->audience ?? 'public') === $audienceKey)>{{ $audienceLabel }}</option>
            @endforeach
        </select>
        @error('audience')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="card bg-light border-0 rounded-4 p-3 mb-3">
    <h6 class="fw-bold mb-2"><i class="bi bi-file-earmark-play me-1"></i>Media</h6>
    <div class="mb-2">
        <input type="file" name="media" class="form-control @error('media') is-invalid @enderror"
               accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.ogv,.mp3,.wav,.ogg,.m4a">
        <div class="form-text">
            Image ≤ 8 MB (jpg/png/webp/gif), video ≤ 50 MB (mp4/webm/ogv), audio ≤ 20 MB (mp3/wav/ogg/m4a).
            The accepted kind follows the campaign type. Files are stored under hashed names and never executed.
        </div>
        @error('media')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        @if(!empty($campaign->media_path))
            <div class="small text-muted mt-1">Current file: {{ $campaign->media_path }} ({{ $campaign->media_type }})</div>
        @endif
    </div>
    <div>
        <label class="form-label fw-semibold">External media URL (optional)</label>
        <input type="url" name="external_media_url" class="form-control @error('external_media_url') is-invalid @enderror"
               value="{{ old('external_media_url', $campaign->external_media_url ?? '') }}"
               placeholder="https://cdn.example.org/promo.mp4 — http(s) only">
        <div class="form-text">Used only when no file is uploaded. Hosted elsewhere, embedded as media — never as script.</div>
        @error('external_media_url')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">Destination URL (optional)</label>
    <input type="url" name="destination_url" class="form-control @error('destination_url') is-invalid @enderror"
           value="{{ old('destination_url', $campaign->destination_url ?? '') }}"
           placeholder="https://… — clicks are counted, then the visitor is redirected">
    @error('destination_url')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Publication start</label>
        <input type="datetime-local" name="starts_at" class="form-control @error('starts_at') is-invalid @enderror"
               value="{{ old('starts_at', isset($campaign->starts_at) ? $campaign->starts_at->format('Y-m-d\TH:i') : '') }}">
        @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Publication end</label>
        <input type="datetime-local" name="ends_at" class="form-control @error('ends_at') is-invalid @enderror"
               value="{{ old('ends_at', isset($campaign->ends_at) ? $campaign->ends_at->format('Y-m-d\TH:i') : '') }}">
        <div class="form-text">Expired campaigns stop appearing automatically — no manual removal needed.</div>
        @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <label class="form-label fw-semibold">Priority (0–1000)</label>
        <input type="number" name="priority" min="0" max="1000" class="form-control"
               value="{{ old('priority', $campaign->priority ?? 0) }}">
        <div class="form-text">Higher priority shows first within a placement.</div>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Public label <span class="text-danger">*</span></label>
        <input type="text" name="label" class="form-control @error('label') is-invalid @enderror"
               value="{{ old('label', $campaign->label ?? 'Advertisement') }}" required>
        <div class="form-text">Shown on the public item — e.g. "Advertisement", "Sponsored", "Announcement".</div>
        @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Pricing / sponsorship note</label>
        <input type="text" name="sponsor_fee_note" class="form-control"
               value="{{ old('sponsor_fee_note', $campaign->sponsor_fee_note ?? '') }}"
               placeholder="Optional, internal + reporting only">
    </div>
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">Internal notes</label>
    <textarea name="internal_notes" rows="2" class="form-control">{{ old('internal_notes', $campaign->internal_notes ?? '') }}</textarea>
</div>

<div class="mb-3">
    <label class="notify-toggle d-flex align-items-center gap-2 mb-0 user-select-none">
        <input type="checkbox" name="notify_push" value="1"
               {{ old('notify_push', ($campaign->notify_push ?? false)) ? 'checked' : '' }}
               class="form-check-input mt-0">
        <span class="fw-semibold small"><i class="bi bi-bell me-1"></i>Eligible for web push (Module 3 composer can pick it up)</span>
    </label>
</div>
