@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="badge text-bg-light text-danger px-3 py-2 mb-2">System configuration</span>
            <h3 class="fw-bold mb-1">Roles and permissions</h3>
            <p class="text-muted mb-0">
                Permissions are granted per role. Changes take effect on the next request.
            </p>
        </div>
        <form method="POST" action="{{ route('admin.configuration.permissions.sync') }}">
            @csrf
            <button class="btn btn-outline-primary btn-sm" onclick="return confirm('Re-sync the permission catalogue? Missing permissions are created and default grants re-applied; nothing is revoked.')">
                <i class="bi bi-arrow-repeat me-1"></i>Re-sync catalogue
            </button>
        </form>
    </div>

    @if(session('success'))
        <div class="alert alert-success rounded-4 mb-4">{{ session('success') }}</div>
    @endif

    <div class="alert alert-info rounded-4 small mb-4">
        <i class="bi bi-shield-lock me-1"></i>
        The <strong>Managing Editor</strong> role can never be granted these capabilities:
        @foreach($prohibitions as $prohibition)
            <code class="me-1">{{ $prohibition }}</code>
        @endforeach
        — the server refuses them even if they are submitted, so user management, payments, publication,
        editorial decisions and system configuration always stay out of its reach.
    </div>

    @foreach($grouped as $domain => $group)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 pt-3 px-4">
                <h6 class="fw-bold mb-0">{{ $group['label'] }}</h6>
            </div>
            <div class="card-body pt-0 px-4 pb-3">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40%">Permission</th>
                                @foreach($roles as $role)
                                    <th class="text-center small">{{ $role->name }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($group['permissions'] as $permission)
                                <tr>
                                    <td><code class="small">{{ $permission }}</code></td>
                                    @foreach($roles as $role)
                                        <td class="text-center">
                                            @if(in_array($permission, $granted[$role->name] ?? [], true))
                                                <i class="bi bi-check-lg text-success"></i>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3">Adjust a role</h6>
            <p class="text-muted small">
                Editing is per-role rather than per-checkbox-grid so every change is one deliberate action.
                Sync the catalogue first if a permission you need is not yet in the database.
            </p>
            <form method="POST" action="#" id="role-edit-form">
                @csrf
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Role</label>
                        <select id="role-picker" class="form-select form-select-sm">
                            <option value="">Select a role</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Permissions to grant</label>
                        <input type="text" id="permissions-input" class="form-control form-control-sm"
                               placeholder="space or comma separated permission names">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary btn-sm w-100" disabled id="role-save">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var grants = @json($granted);
    var picker = document.getElementById('role-picker');
    var input = document.getElementById('permissions-input');
    var save = document.getElementById('role-save');
    var form = document.getElementById('role-edit-form');

    picker.addEventListener('change', function () {
        if (picker.value === '') { return; }
        input.value = (grants[picker.value] || []).join(', ');
        save.disabled = false;
        form.setAttribute('action', picker.value
            ? '{{ url("admin/configuration/permissions") }}/' + encodeURIComponent(picker.value)
            : '#');
    });

    form.addEventListener('submit', function (event) {
        if (!picker.value) { event.preventDefault(); }
    });
});
</script>
@endsection
