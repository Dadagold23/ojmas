{{-- Shared push-notification composer fields. --}}
@php
    $n = $notification ?? null;
@endphp

<div class="mb-3">
    <label class="form-label fw-semibold">Notification title <span class="text-danger">*</span></label>
    <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
           value="{{ old('title', $n?->title ?? '') }}" maxlength="120" required>
    <div class="form-text">Shown as the notification's bold first line (max 120 characters).</div>
    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">Short message <span class="text-danger">*</span></label>
    <textarea name="message" rows="3" maxlength="300" class="form-control @error('message') is-invalid @enderror"
              placeholder="One or two sentences — push payloads are small.">{{ old('message', $n?->message ?? '') }}</textarea>
    @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
        <select name="category" class="form-select @error('category') is-invalid @enderror">
            @foreach($categories as $categoryKey => $categoryLabel)
                <option value="{{ $categoryKey }}" @selected(old('category', $n?->category ?? '') === $categoryKey)>{{ $categoryLabel }}</option>
            @endforeach
        </select>
        <div class="form-text">Non-eligible categories cannot be dispatched to subscribers.</div>
        @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Target audience <span class="text-danger">*</span></label>
        <select name="audience" class="form-select @error('audience') is-invalid @enderror">
            @foreach(\App\Models\PushNotification::AUDIENCES as $audienceKey => $audienceLabel)
                <option value="{{ $audienceKey }}" @selected(old('audience', $n?->audience ?? 'all') === $audienceKey)>{{ $audienceLabel }}</option>
            @endforeach
        </select>
        <div class="form-text">Promotional or sponsored content reaches only subscribers who opted in.</div>
        @error('audience')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">Destination URL (optional)</label>
    <input type="url" name="action_url" class="form-control @error('action_url') is-invalid @enderror"
           value="{{ old('action_url', $n?->action_url ?? '') }}"
           placeholder="https://ojmaspublish.online/calls-for-papers — http(s) only">
    <div class="form-text">Clicking the notification opens this page.</div>
    @error('action_url')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Icon (optional)</label>
        <input type="file" name="icon" class="form-control @error('icon') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,.gif">
        <div class="form-text">Image ≤ 2 MB — shown inside the notification where the browser supports it.</div>
        @if($n?->icon_path)
            <div class="mt-2">
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($n->icon_path) }}"
                     alt="Current icon" class="rounded-2 border d-block mb-1"
                     style="width: 56px; height: 56px; object-fit: cover;">
                <div class="small text-muted">Current: {{ $n->icon_path }}</div>
            </div>
        @endif
        @error('icon')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Linked advertisement campaign (optional)</label>
        <select name="ad_campaign_id" class="form-select @error('ad_campaign_id') is-invalid @enderror">
            <option value="">— none —</option>
            @foreach($campaigns as $campaign)
                <option value="{{ $campaign->id }}" @selected(old('ad_campaign_id', $n?->ad_campaign_id ?? '') == $campaign->id)>
                    {{ \Illuminate\Support\Str::limit($campaign->title, 60) }}
                </option>
            @endforeach
        </select>
        <div class="form-text">Campaign-to-notification linkage for Module 4 analytics.</div>
        @error('ad_campaign_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Publication time (for scheduled sends)</label>
        <input type="datetime-local" name="scheduled_at" class="form-control @error('scheduled_at') is-invalid @enderror"
               value="{{ old('scheduled_at', isset($n) && $n->scheduled_at ? $n->scheduled_at->format('Y-m-d\TH:i') : '') }}">
        @error('scheduled_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Expiry time (optional)</label>
        <input type="datetime-local" name="expires_at" class="form-control @error('expires_at') is-invalid @enderror"
               value="{{ old('expires_at', isset($n) && $n->expires_at ? $n->expires_at->format('Y-m-d\TH:i') : '') }}">
        <div class="form-text">After expiry the scheduler will not dispatch it any more.</div>
        @error('expires_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
