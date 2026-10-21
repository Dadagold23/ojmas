@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0"><i class="bi bi-pencil me-2 text-primary"></i>Edit: {{ \Illuminate\Support\Str::limit($campaign->title, 40) }}</h3>
        <a href="{{ route('admin.advertisements.show', $campaign) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger rounded-4">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 rounded-4 shadow-sm">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('admin.advertisements.update', $campaign) }}" enctype="multipart/form-data">
                @csrf

                @include('auth.admin.advertisements.fields', ['campaign' => $campaign])

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Save changes
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
