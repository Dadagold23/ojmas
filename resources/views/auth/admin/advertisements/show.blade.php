@extends('layouts.app')

@section('content')
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .status-chip { border-radius: 999px; padding: .3rem .7rem; font-size: .75rem; font-weight: 600; }
    .status-published { background: #d1fae5; color: #065f46; }
    .status-scheduled { background: #dbeafe; color: #1e40af; }
    .status-pending { background: #fef3c7; color: #92400e; }
    .status-draft { background: #e2e8f0; color: #334155; }
    .status-paused { background: #fce7f3; color: #9d174d; }
    .status-expired { background: #fee2e2; color: #991b1b; }
    .status-archived { background: #f1f5f9; color: #64748b; }
</style>

<div class="container py-4" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">{{ \Illuminate\Support\Str::limit($advertisement->title, 60) }}</h3>
            <span class="status-chip status-{{ $advertisement->status }}">{{ \App\Models\AdCampaign::STATUSES[$advertisement->status] }}</span>
            @if($advertisement->isPubliclyVisible())
                <span class="badge bg-success-subtle text-success-emphasis ms-1">live on site</span>
            @endif
        </div>
        <a href="{{ route('admin.advertisements.index') }}" class="btn btn-outline-secondary btn-sm">Back to campaigns</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4">
            {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            {{-- Public preview: exactly what readers will see. --}}
            <div class="card panel-card mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-1">Public preview</h5>
                    <p class="text-muted mb-3">Rendered by the very template used on the public site.</p>
                </div>
                <div class="card-body px-4 pb-4">
                    @include('ads.render', [
                        'campaigns' => [$advertisement],
                        'placementKey' => 'preview',
                        'placementLabel' => $advertisement->placement?->label ?? 'Preview',
                    ])
                </div>
            </div>

            <div class="card panel-card">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-1">Campaign record</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    <dl class="row mb-0">
                        <dt class="col-sm-4 small text-muted">Type</dt>
                        <dd class="col-sm-8 small">{{ \App\Models\AdCampaign::TYPES[$advertisement->type] ?? $advertisement->type }}</dd>
                        <dt class="col-sm-4 small text-muted">Placement</dt>
                        <dd class="col-sm-8 small">{{ $advertisement->placement?->label ?? '—' }}</dd>
                        <dt class="col-sm-4 small text-muted">Advertiser</dt>
                        <dd class="col-sm-8 small">{{ $advertisement->advertiser_name ?? 'Internal' }}</dd>
                        <dt class="col-sm-4 small text-muted">Window</dt>
                        <dd class="col-sm-8 small">
                            {{ $advertisement->starts_at?->format('d M Y H:i') ?? 'open start' }}
                            → {{ $advertisement->ends_at?->format('d M Y H:i') ?? 'no end' }}
                        </dd>
                        <dt class="col-sm-4 small text-muted">Audience</dt>
                        <dd class="col-sm-8 small">{{ \App\Models\AdCampaign::AUDIENCES[$advertisement->audience] ?? $advertisement->audience }}</dd>
                        <dt class="col-sm-4 small text-muted">Label</dt>
                        <dd class="col-sm-8 small">{{ $advertisement->label }}</dd>
                        <dt class="col-sm-4 small text-muted">Push eligible</dt>
                        <dd class="col-sm-8 small">{{ $advertisement->notify_push ? 'Yes — available to the Module 3 composer' : 'No' }}</dd>
                        <dt class="col-sm-4 small text-muted">Pricing note</dt>
                        <dd class="col-sm-8 small">{{ $advertisement->sponsor_fee_note ?? '—' }}</dd>
                        <dt class="col-sm-4 small text-muted">Created by</dt>
                        <dd class="col-sm-8 small">{{ $advertisement->creator?->name ?? '—' }}</dd>
                        @if($advertisement->internal_notes)
                            <dt class="col-sm-4 small text-muted">Internal notes</dt>
                            <dd class="col-sm-8 small">{{ $advertisement->internal_notes }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card panel-card mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-1">Status</h5>
                    <p class="text-muted mb-3">Transitions are validated server-side; invalid moves are refused.</p>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="d-flex flex-wrap gap-2">
                        @foreach(\App\Models\AdCampaign::STATUSES as $statusKey => $statusLabel)
                            @continue($statusKey === $advertisement->status)
                            <form method="POST" action="{{ route('admin.advertisements.transition', $advertisement) }}"
                                  onsubmit="return confirm('Move this campaign to: {{ $statusLabel }}?')">
                                @csrf
                                <input type="hidden" name="status" value="{{ $statusKey }}">
                                <button type="submit" class="btn btn-sm btn-outline-secondary status-chip status-{{ $statusKey }} border-0">
                                    → {{ $statusLabel }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                    <div class="form-text mt-2">Records are never deleted — use Archive to retire a campaign.</div>
                </div>
            </div>

            <div class="card panel-card">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-1">Performance</h5>
                    <p class="text-muted mb-3">Counted server-side, deduplicated per visitor per day.</p>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="d-flex gap-3 mb-3">
                        <div class="border rounded-3 p-3 flex-fill text-center">
                            <div class="fw-bold fs-4">{{ $impressionCount }}</div>
                            <div class="text-muted small">impressions</div>
                        </div>
                        <div class="border rounded-3 p-3 flex-fill text-center">
                            <div class="fw-bold fs-4">{{ $clickCount }}</div>
                            <div class="text-muted small">clicks</div>
                        </div>
                        <div class="border rounded-3 p-3 flex-fill text-center">
                            <div class="fw-bold fs-4">{{ $impressionCount > 0 ? number_format(100 * $clickCount / $impressionCount, 1).'%' : '—' }}</div>
                            <div class="text-muted small">CTR</div>
                        </div>
                    </div>

                    @if($daily->count())
                        <table class="table table-sm small mb-0">
                            <thead><tr><th>Day</th><th>Impressions</th><th>Clicks</th></tr></thead>
                            <tbody>
                                @foreach($daily as $row)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($row->occurred_on)->format('d M Y') }}</td>
                                        <td>{{ $row->impressions }}</td>
                                        <td>{{ $row->clicks }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="text-muted small">No engagement recorded yet.</div>
                    @endif
                </div>
            </div>

            <a href="{{ route('admin.advertisements.edit', $advertisement) }}" class="btn btn-primary w-100 mt-4">
                <i class="bi bi-pencil me-1"></i>Edit campaign
            </a>
        </div>
    </div>
</div>
@endsection
