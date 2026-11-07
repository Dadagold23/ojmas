@extends('layouts.app')

@section('content')
    @include('journal.partials.profile-grid', ['title' => $title, 'subtitle' => $subtitle, 'members' => $members])
@endsection

@section('footer')
    @include('layouts.footer')
@endsection
