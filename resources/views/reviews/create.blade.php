@extends('layouts.app')
@section('title', 'Write a review')
@section('container_class', 'narrow')
@section('content')
    <div class="page-head"><div><h1>Write a review</h1><p class="sub">You have {{ $activity->remaining_reviews }} review(s) left in this window. Reviews are moderated before publication.</p></div></div>
    <form method="POST" action="/reviews" class="card">
        @csrf
        <div class="field">
            <label for="restaurant_id">Restaurant</label>
            <select id="restaurant_id" name="restaurant_id" required>
                <option value="">— Select —</option>
                @foreach($restaurants as $r)<option value="{{ $r->id }}" @selected((string) old('restaurant_id', $selectedRestaurant) === (string) $r->id)>{{ $r->name }} ({{ $r->district }})</option>@endforeach
            </select>
            <div class="hint">Not listed? <a href="/restaurants/create?return=/reviews/create">Add it</a>.</div>
        </div>
        @include('reviews.partials.fields', ['review' => null])
        <button class="btn">Submit for moderation</button>
    </form>
@endsection
