@extends('layouts.app')

@section('content')
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .role-badge { font-size: .78rem; padding: .35em .8em; border-radius: 50px; }
    .info-label { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; font-weight: 600; }
</style>

<div class="container py-4" style="max-width:900px">

    <div class="d-flex align-items-center gap-3 mb-4" data-aos="fade-up">
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <h3 class="fw-bold mb-0">{{ $user->name }}</h3>
            <span class="text-muted small">{{ $user->email }}</span>
        </div>
        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary btn-sm fw-semibold">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4">
        {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="row g-4">
        {{-- Profile --}}
        <div class="col-lg-4">
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center mx-auto mb-3"
                         style="width:72px;height:72px;font-size:1.8rem">
                        <i class="bi bi-person-fill text-primary"></i>
                    </div>
                    <h5 class="fw-bold mb-1">{{ $user->name }}</h5>
                    <p class="text-muted small mb-3">{{ $user->email }}</p>
                    @foreach($user->roles as $role)
                    @php $rc = ['Editor'=>'primary','Reviewer'=>'success','Author'=>'warning','Managing Editor'=>'info','Super Admin'=>'dark'][$role->name] ?? 'secondary'; @endphp
                    <span class="badge bg-{{ $rc }} role-badge">{{ $role->name }}</span>
                    @endforeach
                </div>
            </div>

            <div class="card panel-card" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Profile Details</h6>
                    <div class="mb-3">
                        <div class="info-label mb-1">Phone</div>
                        <div class="small">{{ $user->phone ?? '—' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="info-label mb-1">Institution</div>
                        <div class="small">{{ $user->institution ?? '—' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="info-label mb-1">Country</div>
                        <div class="small">{{ $user->country ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="info-label mb-1">Joined</div>
                        <div class="small">{{ $user->created_at->format('d M Y') }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Activity --}}
        <div class="col-lg-8">
            {{-- Submissions (for authors) --}}
            @if($submissions->count())
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Recent Submissions</h6>
                    @foreach($submissions as $sub)
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <div class="small fw-semibold">{{ Str::limit($sub->title, 55) }}</div>
                            <div class="text-muted" style="font-size:.78rem">{{ $sub->submission_no }} &middot; {{ $sub->created_at->format('d M Y') }}</div>
                        </div>
                        @php $sc = ['submitted'=>'primary','under_review'=>'info','accepted'=>'success','rejected'=>'danger','published'=>'dark'][$sub->status] ?? 'secondary'; @endphp
                        <span class="badge bg-{{ $sc }} role-badge">{{ ucwords(str_replace('_',' ',$sub->status)) }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Review assignments (for reviewers) --}}
            @if($assignments->count())
            <div class="card panel-card mb-4" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-clipboard-check me-2 text-primary"></i>Review Assignments</h6>
                    @foreach($assignments as $a)
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <div class="small fw-semibold">{{ Str::limit($a->submission->title ?? 'N/A', 55) }}</div>
                            <div class="text-muted" style="font-size:.78rem">Due: {{ $a->due_date?->format('d M Y') ?? 'N/A' }}</div>
                        </div>
                        <span class="badge bg-{{ $a->status === 'completed' ? 'success' : 'warning' }} role-badge">
                            {{ ucfirst($a->status) }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if(!$submissions->count() && !$assignments->count())
            <div class="card panel-card" data-aos="fade-up">
                <div class="card-body p-4 text-center text-muted py-5">
                    <i class="bi bi-inbox fs-2 mb-3 d-block"></i>
                    No activity recorded for this user yet.
                </div>
            </div>
            @endif

            {{-- Danger zone --}}
            @if(!$user->hasRole('Super Admin') && $user->id !== auth()->id())
            <div class="card border-danger" style="border-radius:1.25rem" data-aos="fade-up">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-danger mb-2"><i class="bi bi-exclamation-triangle me-2"></i>Danger Zone</h6>
                    <p class="text-muted small mb-3">Permanently delete this user account. This action cannot be undone.</p>
                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                          onsubmit="return confirm('Permanently delete {{ $user->name }}? This cannot be undone.')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger btn-sm fw-semibold">
                            <i class="bi bi-trash me-1"></i>Delete User
                        </button>
                    </form>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
