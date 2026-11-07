@php
    $statusClasses = ['draft' => 'secondary', 'scheduled' => 'primary', 'sending' => 'warning', 'sent' => 'success', 'failed' => 'danger', 'cancelled' => 'dark'];
    $sentCount = $deliveries->get('sent')->total ?? 0;
    $failedCount = $deliveries->get('failed')->total ?? 0;
    $pendingCount = $deliveries->get('pending')->total ?? 0;
    $totalDeliveries = $sentCount + $failedCount + $pendingCount;
@endphp

@extends('layouts.app')

@section('content')
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
</style>

<div class="container py-4" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">{{ \Illuminate\Support\Str::limit($notification->title, 60) }}</h3>
            <span class="badge text-bg-{{ $statusClasses[$notification->status] ?? 'light' }} me-1">
                {{ \App\Models\PushNotification::STATUSES[$notification->status] }}
            </span>
            <span class="small text-muted">
                {{ $notification->created_by ? 'by '.$notification->creator?->name : '' }}
                · {{ $notification->created_at->format('d M Y H:i') }}
            </span>
        </div>
        <a href="{{ route('admin.push.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4">
            {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger rounded-4">
            <ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card panel-card mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-1">Record</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    <dl class="row mb-0">
                        <dt class="col-sm-4 small text-muted">Message</dt>
                        <dd class="col-sm-8 small">{{ $notification->message }}</dd>
                        <dt class="col-sm-4 small text-muted">Category</dt>
                        <dd class="col-sm-8 small">{{ \App\Models\PushNotification::CATEGORIES[$notification->category] ?? $notification->category }}</dd>
                        <dt class="col-sm-4 small text-muted">Audience</dt>
                        <dd class="col-sm-8 small">{{ \App\Models\PushNotification::AUDIENCES[$notification->audience] ?? $notification->audience }}</dd>
                        <dt class="col-sm-4 small text-muted">Destination</dt>
                        <dd class="col-sm-8 small">{{ $notification->action_url ?? '—' }}</dd>
                        <dt class="col-sm-4 small text-muted">Window</dt>
                        <dd class="col-sm-8 small">
                            {{ $notification->scheduled_at?->format('d M Y H:i') ?? 'immediate' }}
                            @if($notification->expires_at) → {{ $notification->expires_at->format('d M Y H:i') }}@endif
                        </dd>
                        <dt class="col-sm-4 small text-muted">Linked campaign</dt>
                        <dd class="col-sm-8 small">
                            @if($notification->campaign)
                                <a href="{{ route('admin.advertisements.show', $notification->campaign) }}">{{ \Illuminate\Support\Str::limit($notification->campaign->title, 50) }}</a>
                            @else
                                —
                            @endif
                        </dd>
                        <dt class="col-sm-4 small text-muted">Icon</dt>
                        <dd class="col-sm-8 small">
                            @if($notification->icon_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($notification->icon_path) }}"
                                     alt="Notification icon" class="rounded-3 mb-1 d-block"
                                     style="width: 72px; height: 72px; object-fit: cover;">
                                <span class="text-muted">{{ $notification->icon_path }}</span>
                            @else
                                —
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card panel-card mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-1">Delivery report</h5>
                    <p class="text-muted mb-3">
                        Per-endpoint acceptance only. Web Push has no read or click channel, so none is claimed.
                    </p>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="d-flex gap-3 mb-3">
                        <div class="border rounded-3 p-3 flex-fill text-center">
                            <div class="fw-bold fs-5">{{ $totalDeliveries }}</div>
                            <div class="text-muted small">staged</div>
                        </div>
                        <div class="border rounded-3 p-3 flex-fill text-center">
                            <div class="fw-bold fs-5 text-success">{{ $sentCount }}</div>
                            <div class="text-muted small">accepted</div>
                        </div>
                        <div class="border rounded-3 p-3 flex-fill text-center">
                            <div class="fw-bold fs-5 text-danger">{{ $failedCount }}</div>
                            <div class="text-muted small">failed</div>
                        </div>
                        <div class="border rounded-3 p-3 flex-fill text-center">
                            <div class="fw-bold fs-5 text-warning">{{ $pendingCount }}</div>
                            <div class="text-muted small">pending</div>
                        </div>
                    </div>

                    @if($recent->count())
                        <div class="table-responsive">
                            <table class="table table-sm small mb-0">
                                <thead><tr><th>Endpoint</th><th>Status</th><th>Attempts</th><th>When</th></tr></thead>
                                <tbody>
                                    @foreach($recent as $delivery)
                                        <tr>
                                            <td class="text-truncate" style="max-width: 220px;">
                                                <span class="font-monospace" style="font-size:.7rem;">{{ \Illuminate\Support\Str::limit($delivery->subscription?->endpoint, 60) }}</span>
                                                @if(! $delivery->subscription?->is_active)
                                                    <span class="badge text-bg-secondary">disabled</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge text-bg-{{ $delivery->status === 'sent' ? 'success' : ($delivery->status === 'failed' ? 'danger' : 'secondary') }}">
                                                    {{ $delivery->status }}
                                                </span>
                                                @if($delivery->last_error)
                                                    <div class="text-muted" style="font-size:.7rem;">{{ \Illuminate\Support\Str::limit($delivery->last_error, 80) }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $delivery->attempts }}</td>
                                            <td>{{ $delivery->delivered_at?->format('d M H:i') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-muted small">Nothing dispatched yet.</div>
                    @endif
                </div>
            </div>

            @if($audit->count())
                <div class="card panel-card">
                    <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                        <h5 class="fw-bold mb-1">Audit trail</h5>
                    </div>
                    <div class="card-body px-4 pb-4 small">
                        @foreach($audit as $entry)
                            <div class="border-bottom py-1">
                                <i class="bi bi-clock-history text-muted me-1"></i>
                                {{ $entry->created_at->format('d M Y H:i') }} —
                                <span class="fw-semibold">{{ $entry->event }}</span>
                                @if($entry->user) · {{ $entry->user->name }}@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            <div class="card panel-card mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-1">Dispatch</h5>
                    <p class="text-muted mb-3">
                        Queue driver: <code>{{ $queueDriver }}</code>
                        {{ $publicKey ? '' : ' — VAPID keys are missing, delivery would honestly do nothing.' }}
                    </p>
                </div>
                <div class="card-body px-4 pb-4">
                    <form method="POST" action="{{ route('admin.push.send', $notification) }}" class="d-flex gap-2 mb-3">
                        @csrf
                        <input type="hidden" name="when" value="now">
                        <button type="submit" class="btn btn-success btn-sm"
                                onclick="return confirm('Dispatch now?')">Dispatch immediately</button>
                    </form>

                    <form method="POST" action="{{ route('admin.push.send', $notification) }}">
                        @csrf
                        <input type="hidden" name="when" value="schedule">
                        <div class="input-group mb-2">
                            <input type="datetime-local" name="scheduled_at" class="form-control form-control-sm" required>
                            <button type="submit" class="btn btn-primary btn-sm">Schedule</button>
                        </div>
                    </form>

                    <hr>

                    <a href="{{ route('admin.push.preview', $notification) }}" class="btn btn-outline-secondary btn-sm d-block mb-3">
                        <i class="bi bi-eye me-1"></i>Preview as it arrives
                    </a>

                    <div class="d-flex gap-2">
                        @unless(in_array($notification->status, ['cancelled', 'sent']))
                            <form method="POST" action="{{ route('admin.push.cancel', $notification) }}" onsubmit="return confirm('Cancel this notification?')">
                                @csrf
                                <button type="submit" class="btn btn-outline-dark btn-sm">Cancel</button>
                            </form>
                        @endunless

                        @if($notification->status === 'failed')
                            <form method="POST" action="{{ route('admin.push.retry', $notification) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary btn-sm">Queue a retry</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
