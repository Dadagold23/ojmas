@php $statusClasses = ['draft' => 'secondary', 'scheduled' => 'primary', 'sending' => 'warning', 'sent' => 'success', 'failed' => 'danger', 'cancelled' => 'dark']; @endphp

@extends('layouts.app')

@section('content')
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .push-hero { border: none; border-radius: 1.5rem;
        background: linear-gradient(135deg, rgba(79,70,229,.92), rgba(15,23,42,.92));
        color:#fff; box-shadow: 0 20px 50px rgba(15,23,42,.16); }
</style>

<div class="container py-4">
    <div class="card push-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="fw-bold mb-2"><i class="bi bi-broadcast-pin me-2"></i>Web Push Notifications</h2>
                    <p class="mb-0 text-white-50">
                        Compose, schedule and report push notifications. Only what the push service can
                        actually observe is ever reported — nothing is claimed as read or clicked.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0 d-flex flex-column gap-2 align-items-lg-end">
                    <a href="{{ route('admin.push.create') }}" class="btn btn-warning fw-semibold">
                        <i class="bi bi-plus-lg me-1"></i>Compose
                    </a>
                    @can('push.configure')
                        <a href="{{ route('admin.push.categories') }}" class="btn btn-outline-light">
                            <i class="bi bi-sliders me-1"></i>Category eligibility
                        </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4">
            {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(!$publicKey)
        <div class="alert alert-warning rounded-4 mb-4">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>VAPID keys are not configured.</strong> Run <code>php artisan push:keys</code> on the
            host and add the printed lines to <code>.env</code>. Until then, notifications cannot be
            delivered — they can only be composed and previewed honestly.
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger rounded-4 mb-4">
            <ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card panel-card mb-4" data-aos="fade-up">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-1">Notifications</h5>
            <p class="text-muted mb-3">Delivery figures count per-endpoint acceptance only.</p>
        </div>
        <div class="card-body p-0">
            <form method="GET" action="{{ route('admin.push.index') }}" class="row g-2 px-4 pt-3">
                <div class="col-md-4">
                    <input type="text" name="q" class="form-control form-control-sm" value="{{ $search }}" placeholder="Search title or message">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        @foreach(\App\Models\PushNotification::STATUSES as $key => $label)
                            <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All categories</option>
                        @foreach(\App\Models\PushNotification::CATEGORIES as $key => $label)
                            <option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-primary w-100">Filter</button>
                </div>
            </form>

            <div class="table-responsive mt-3">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Notification</th>
                            <th>Category</th>
                            <th>Audience</th>
                            <th>Window</th>
                            <th>Status</th>
                            <th>Delivered</th>
                            <th class="pe-4 text-end">Open</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($listings as $notification)
                            @php
                                $summary = $deliverySummary->get($notification->id);
                            @endphp
                            <tr>
                                <td class="px-4">
                                    <div class="fw-semibold small">{{ \Illuminate\Support\Str::limit($notification->title, 55) }}</div>
                                    <div class="text-muted" style="font-size:.75rem;">
                                        {{ $notification->created_by ? ($notification->creator?->name ?? 'staff') : '—' }}
                                        · {{ $notification->created_at->format('d M Y H:i') }}
                                    </div>
                                </td>
                                <td class="small">{{ \App\Models\PushNotification::CATEGORIES[$notification->category] ?? $notification->category }}</td>
                                <td class="small text-muted">{{ \App\Models\PushNotification::AUDIENCES[$notification->audience] ?? $notification->audience }}</td>
                                <td class="small text-muted">
                                    {{ $notification->scheduled_at?->format('d M H:i') ?? 'immediate' }}
                                    @if($notification->expires_at) → {{ $notification->expires_at->format('d M H:i') }}@endif
                                </td>
                                <td>
                                    <span class="badge text-bg-{{ $statusClasses[$notification->status] ?? 'light' }}">
                                        {{ \App\Models\PushNotification::STATUSES[$notification->status] ?? $notification->status }}
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    {{ $summary ? ($summary->sent.' sent · '.$summary->failed.' failed'.($summary->pending > 0 ? ' · '.$summary->pending.' pending' : '')) : '—' }}
                                </td>
                                <td class="pe-4 text-end">
                                    <a href="{{ route('admin.push.show', $notification) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-5">No notifications yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-0 px-4 pb-4">
            {{ $listings->links() }}
        </div>
    </div>

    <div class="alert alert-light border rounded-4 small">
        <i class="bi bi-terminal me-1"></i>
        Scheduled notifications are sent by <code>php artisan push:dispatch</code> — wire it to the host
        cron (every minute). The same command re-processes interrupted sends after a server restart with
        no duplicate sends (delivery rows deduplicate per endpoint).
    </div>
</div>
@endsection
