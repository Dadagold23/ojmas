@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h2>My Profile</h2> 

    <div class="card show-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf 
                @method('PATCH')

                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" value="{{ auth()->user()->name }}">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" value="{{ auth()->user()->email }}">
                </div> 

                <button class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
</div>
@endsection