@extends('layouts.app')
@section('title', 'Restaurants')
@section('content')
    <div class="page-head">
        <div><h1>Restaurants</h1><p class="sub">Trust scores update automatically as complaints are verified and reviews approved.</p></div>
        @if($authUser)<a href="/restaurants/create?return=/restaurants" class="btn">+ Add restaurant</a>@endif
    </div>

    <form class="filters card flat" method="GET" style="margin-bottom:16px">
        <div class="field" style="flex:1"><label>Search</label><input type="search" name="q" value="{{ request('q') }}" placeholder="Restaurant name"></div>
        <div class="field"><label>District</label>
            <select name="district"><option value="">All districts</option>@foreach($districts as $d)<option @selected(request('district') === $d)>{{ $d }}</option>@endforeach</select>
        </div>
        <div class="field"><label>Sort by</label>
            <select name="sort"><option value="trust">Trust score</option><option value="name" @selected(request('sort') === 'name')>Name</option></select>
        </div>
        <button class="btn">Filter</button>
    </form>

    <div class="grid grid-3">
        @forelse($restaurants as $r)
            <a href="/restaurants/{{ $r->id }}" class="card" style="color:inherit;text-decoration:none">
                <div class="row between"><h3 style="margin:0">{{ $r->name }}</h3><span class="tag">{{ $r->district }}</span></div>
                <p class="small muted" style="margin:4px 0 10px">{{ $r->address ?: 'Address not provided' }}</p>
                @include('partials.trust', ['restaurant' => $r])
                <div class="meter" style="margin-top:8px"><span style="width:{{ $r->trust_score * 20 }}%;background:{{ $r->trustLevel() === 'good' ? 'var(--brand)' : ($r->trustLevel() === 'warn' ? 'var(--accent)' : 'var(--danger)') }}"></span></div>
                <p class="small muted" style="margin:8px 0 0">{{ $r->verified_complaints }} verified complaint(s)</p>
            </a>
        @empty
            <div class="card empty" style="grid-column:1/-1"><div class="icon">🔍</div>No restaurants match.</div>
        @endforelse
    </div>
    {{ $restaurants->links('partials.pager') }}
@endsection
