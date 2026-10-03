@extends('layouts.app')
@section('title', 'Review moderation')
@section('content')
    <div class="page-head"><div><h1>Review moderation</h1><p class="sub">At most {{ $max }} reviews are pending at once; the rest wait and are promoted automatically, oldest first.</p></div></div>
    <div class="card"><div class="card-head"><h2>Pending ({{ $pendingRequests->count() }} / {{ $max }})</h2></div>@include('admin.partials.review-table', ['reviews' => $pendingRequests])</div>
    <div class="card" style="margin-top:20px"><div class="card-head"><h2>Waitlist ({{ $waiting->count() }})</h2></div>@include('admin.partials.review-table', ['reviews' => $waiting])</div>
@endsection
