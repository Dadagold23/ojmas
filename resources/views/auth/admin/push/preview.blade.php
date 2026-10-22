@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 640px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0"><i class="bi bi-eye me-2 text-primary"></i>Preview</h3>
        <a href="{{ route('admin.push.show', $notification) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="alert alert-light border rounded-4 small mb-3">
        <i class="bi bi-info-circle me-1"></i>
        Exact rendering depends on the browser and OS. This is the recorded content, rendered like a
        standard notification. The image and destination come only from the record below.
    </div>

    <div class="card border-0 rounded-4 shadow-sm mb-3">
        <div class="card-body p-4">
            <div class="d-flex align-items-start gap-3 bg-white border rounded-3 p-3 shadow-sm">
                @if($notification->icon_path)
                    <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($notification->icon_path) }}" width="48" height="48" style="border-radius: .5rem; object-fit: cover;" alt="icon">
                @else
                    <i class="bi bi-journal-plus fs-3 text-primary"></i>
                @endif
                <div class="overflow-hidden">
                    <div class="fw-semibold text-truncate">{{ $notification->title }}</div>
                    <div class="small text-muted">{{ $notification->message }}</div>
                    <div class="small text-primary text-truncate mt-1">{{ $notification->action_url ?? 'https://ojmaspublish.online' }}</div>
                </div>
            </div>
        </div>
    </div>

    <dl class="row small">
        <dt class="col-sm-4 text-muted">Category</dt>
        <dd class="col-sm-8">{{ \App\Models\PushNotification::CATEGORIES[$notification->category] ?? $notification->category }}</dd>
        <dt class="col-sm-4 text-muted">Audience</dt>
        <dd class="col-sm-8">{{ \App\Models\PushNotification::AUDIENCES[$notification->audience] ?? $notification->audience }}</dd>
    </dl>
</div>
@endsection
