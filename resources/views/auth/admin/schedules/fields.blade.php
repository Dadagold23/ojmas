{{--
    Shared form fields for creating and editing an issue schedule
    (Addendum Module 5). Expects: $volumes; $schedule (null on create).
    The two submit buttons decide what happens:
      - "Save draft": the plan is recorded, no announcement surfaces yet.
      - "Schedule announcement": requires the announcement time; the campaign
        window and (optional) push notification are prepared at that instant.
--}}
@php $old = old(); @endphp
@if(!empty($errors->all()))
    <div class="alert alert-danger rounded-4 mb-4">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card panel-card mb-4">
    <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
        <h5 class="fw-bold mb-1">Volume and issue</h5>
        <p class="text-muted mb-3">An existing volume and issue are reused; a genuinely new combination is recorded as a draft placeholder that editors complete. Nothing is published by scheduling.</p>
    </div>
    <div class="card-body px-4 pb-4">
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label fw-semibold small">Volume <span class="text-danger">*</span></label>
                <select name="volume_id" class="form-select" id="volumeSelect">
                    <option value="">— create a new volume —</option>
                    @foreach($volumes as $volume)
                        <option value="{{ $volume->id }}"
                            @selected(($old['volume_id'] ?? $schedule->volume_id ?? null) == $volume->id)>
                            Vol {{ $volume->volume_no }} — {{ $volume->year }}{{ $volume->status === 'published' ? ' (published)' : '' }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Pick an existing volume, or choose “create a new volume” and record it below.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">New volume number</label>
                <input type="number" name="new_volume_no" min="1" max="200"
                       value="{{ $old['new_volume_no'] ?? '' }}" class="form-control" placeholder="e.g. 8">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">New volume year</label>
                <input type="number" name="new_volume_year" min="1990" max="2999"
                       value="{{ $old['new_volume_year'] ?? '' }}" class="form-control" placeholder="e.g. {{ now()->year + 1 }}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Issue number <span class="text-danger">*</span></label>
                <input type="text" name="issue_no" maxlength="50"
                       value="{{ $old['issue_no'] ?? '' }}" class="form-control" placeholder="e.g. 2 or 3-4">
                <div class="form-text">If this volume already has an issue with that number it is reused (never duplicated); otherwise a draft placeholder issue is created.</div>
            </div>
        </div>
    </div>
</div>

<div class="card panel-card mb-4">
    <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
        <h5 class="fw-bold mb-1">Announcement</h5>
        <p class="text-muted mb-3">The public-facing text. The announcement appears only inside its window (the campaign surface) and, if asked for, as a scheduled push at the announcement time.</p>
    </div>
    <div class="card-body px-4 pb-4">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label fw-semibold small">Title <span class="text-danger">*</span></label>
                <input type="text" name="title" maxlength="200" required
                       value="{{ $old['title'] ?? $schedule?->title ?? '' }}" class="form-control"
                       placeholder="e.g. Volume 8, Issue 2 — publication announced">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Announcement type <span class="text-danger">*</span></label>
                <select name="announcement_type" class="form-select">
                    @foreach(\App\Models\IssueSchedule::ANNOUNCEMENT_TYPES as $typeKey => $typeLabel)
                        <option value="{{ $typeKey }}"
                            @selected(($old['announcement_type'] ?? $schedule?->announcement_type ?? 'issue_announcement') === $typeKey)>
                            {{ $typeLabel }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Issue announcements run on the sitewide announcement strip; calls for papers on the Calls for Papers page.</div>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold small">Announcement message <span class="text-muted fw-normal">(max 300 characters; also used as the push text)</span></label>
                <textarea name="message" rows="3" maxlength="300" class="form-control">{{ $old['message'] ?? $schedule?->message ?? '' }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Related landing page</label>
                <input type="url" name="landing_url" maxlength="500"
                       value="{{ $old['landing_url'] ?? $schedule?->landing_url ?? '' }}" class="form-control"
                       placeholder="https://…/call-for-papers">
                <div class="form-text">Full http(s) address the announcement and notification link to.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Optional banner / promotional media</label>
                <input type="file" name="banner" accept="image/*" class="form-control">
                @if($schedule?->banner_path)
                    <div class="form-text">Current: {{ basename($schedule->banner_path) }} — uploading a new file replaces it.</div>
                @endif
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="notifyPush" name="notify_push" value="1"
                        @checked(old('notify_push') === '1' || $schedule?->notify_push)>
                    <label class="form-check-label" for="notifyPush">
                        Also queue a push notification at the announcement time
                        <span class="text-muted">(honoured only if the category is marked eligible on the push screens; otherwise it is left out and reported here, never silently failed)</span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card panel-card mb-4">
    <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
        <h5 class="fw-bold mb-1">Dates on record</h5>
        <p class="text-muted mb-3">All dates are journal plans, recorded exactly as entered.</p>
    </div>
    <div class="card-body px-4 pb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Planned publication date</label>
                <input type="date" name="planned_publication_date"
                       value="{{ $old['planned_publication_date'] ?? $schedule?->planned_publication_date?->format('Y-m-d') ?? '' }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Call-for-papers opens</label>
                <input type="date" name="cfp_opens_at"
                       value="{{ $old['cfp_opens_at'] ?? $schedule?->cfp_opens_at?->format('Y-m-d') ?? '' }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Submission deadline</label>
                <input type="date" name="submission_deadline"
                       value="{{ $old['submission_deadline'] ?? $schedule?->submission_deadline?->format('Y-m-d') ?? '' }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Announcement publication time <span class="text-muted fw-normal">(needed when scheduling)</span></label>
                <input type="datetime-local" name="announce_at"
                       value="{{ $old['announce_at'] ?? $schedule?->announce_at?->format('Y-m-d\TH:i') ?? '' }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Announcement window ends</label>
                <input type="datetime-local" name="announcement_ends_at"
                       value="{{ $old['announcement_ends_at'] ?? $schedule?->announcement_ends_at?->format('Y-m-d\TH:i') ?? '' }}" class="form-control">
                <div class="form-text">A sensible window end is the planned publication date; you decide and can change it any time.</div>
            </div>
        </div>
    </div>
</div>
