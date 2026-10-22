@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0"><i class="bi bi-broadcast-pin me-2 text-primary"></i>Compose notification</h3>
        <a href="{{ route('admin.push.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger rounded-4">
            <ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card border-0 rounded-4 shadow-sm">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('admin.push.store') }}" enctype="multipart/form-data">
                @csrf

                @include('auth.admin.push.fields')

                @if(session('success'))<div class="alert alert-success rounded-3">{{ session('success') }}</div>@endif

                {{-- Browser-style preview rendered inline before sending. --}}
                <div class="border rounded-4 p-3 mb-3 bg-light">
                    <div class="small text-uppercase text-muted fw-semibold mb-2">Preview — how it arrives</div>
                    <div class="d-flex align-items-start gap-3 bg-white border rounded-3 p-3 shadow-sm" style="max-width: 420px;">
                        <i class="bi bi-app fs-4 text-primary"></i>
                        <div class="overflow-hidden">
                            <div class="fw-semibold text-truncate">{{ old('title', 'Notification title') }}</div>
                            <div class="small text-muted">{{ old('message', 'The short message the reader will see.') }}</div>
                            <div class="small text-primary text-truncate mt-1">
                                {{ old('action_url', 'https://ojmaspublish.online') }}
                            </div>
                        </div>
                    </div>
                    <div class="form-text mt-2">Exact rendering depends on the browser and OS; this is the recorded content.</div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" name="submit_action" value="draft" class="btn btn-outline-primary">
                        <i class="bi bi-save me-1"></i>Save as draft
                    </button>
                    <button type="submit" name="submit_action" value="schedule" class="btn btn-primary">
                        <i class="bi bi-clock-history me-1"></i>Schedule
                    </button>
                    <button type="submit" name="submit_action" value="send_now" class="btn btn-success"
                            onclick="return confirm('Dispatch now to the selected audience?')">Send now</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
