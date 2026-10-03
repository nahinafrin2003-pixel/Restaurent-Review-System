@extends('layouts.app')
@section('title', 'Edit review')
@section('container_class', 'narrow')
@section('content')
    <div class="page-head"><div><h1>Edit review · {{ $review->restaurant_name }}</h1><p class="sub">Edit {{ $review->edit_count + 1 }} of {{ $review->max_edit_limit ?: 3 }}. Saving sends the review back to the moderation queue.</p></div></div>
    <form method="POST" action="/reviews/{{ $review->review_id }}" class="card">
        @csrf @method('PUT')
        @include('reviews.partials.fields', ['review' => $review])
        <div class="row"><button class="btn">Save changes</button><a href="/reviews" class="btn ghost">Cancel</a></div>
    </form>
@endsection
