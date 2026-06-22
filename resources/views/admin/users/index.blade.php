@extends('layouts.app')

@section('content')
<style>
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .role-badge { font-size: .75rem; padding: .3em .75em; border-radius: 50px; }
    .admin-hero { border: none; border-radius: 1.5rem;
        background: linear-gradient(135deg, rgba(13,110,253,.92), rgba(15,23,42,.92));
        color:#fff; box-shadow: 0 20px 50px rgba(15,23,42,.16); }
</style>

<div class="container py-4">

    <div class="card admin-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="fw-bold mb-2">Editors, Reviewers & Authors</h2>
                    <p class="mb-0 text-white-50">Create, update and manage all journal staff and author accounts.</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <a href="{{ route('admin.users.create') }}" class="btn btn-warning btn-lg fw-semibold">
                        <i class="bi bi-person-plus me-2"></i>Add User
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4">
        {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Filters --}}
    <div class="card panel-card mb-4" data-aos="fade-up">
        <div class="card-body p-4">
            <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Name or email..." value="{{ request('search') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Filter by Role</label>
                    <select name="role" class="form-select form-select-sm">
                        <option value="">All Roles</option>
                        @foreach($roles as $role)
                        <option value="{{ $role->name }}" {{ request('role') === $role->name ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary btn-sm flex-grow-1">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Table --}}
    <div class="card panel-card" data-aos="fade-up">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Institution</th>
                            <th>Joined</th>
                            <th class="pe-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                        <tr>
                            <td class="px-4">
                                <div class="fw-semibold">{{ $user->name }}</div>
                                <div class="text-muted" style="font-size:.78rem">{{ $user->phone ?? '' }}</div>
                            </td>
                            <td class="small">{{ $user->email }}</td>
                            <td>
                                @foreach($user->roles as $role)
                                @php
                                    $rc = ['Editor'=>'primary','Reviewer'=>'success','Author'=>'warning',
                                           'Managing Editor'=>'info','Super Admin'=>'dark'][$role->name] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $rc }} role-badge">{{ $role->name }}</span>
                                @endforeach
                            </td>
                            <td class="small text-muted">{{ $user->institution ?? '—' }}</td>
                            <td class="small text-muted">{{ $user->created_at->format('d M Y') }}</td>
                            <td class="pe-4 text-end">
                                <a href="{{ route('admin.users.show', $user) }}"
                                   class="btn btn-sm btn-outline-secondary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('admin.users.edit', $user) }}"
                                   class="btn btn-sm btn-outline-primary ms-1" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @if(!$user->hasRole('Super Admin') && $user->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                      class="d-inline" onsubmit="return confirm('Delete {{ $user->name }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger ms-1" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">No users found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($users->hasPages())
        <div class="card-footer bg-white border-0 px-4 pb-4">
            {{ $users->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
