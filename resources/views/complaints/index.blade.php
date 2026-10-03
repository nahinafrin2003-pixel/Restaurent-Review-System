@extends('layouts.app')
@section('title', 'My complaints')
@section('content')
    <div class="page-head">
        <div><h1>My complaints</h1><p class="sub">Track every complaint from submission to resolution.</p></div>
        <a href="/complaints/create" class="btn danger">+ New complaint</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Reference</th><th>Restaurant</th><th>Category</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
            <tbody>
            @forelse($complaints as $c)
                <tr>
                    <td class="mono">{{ $c->reference }}</td>
                    <td>{{ $c->restaurant->name ?? '—' }}<div class="small muted">{{ $c->district }}</div></td>
                    <td>{{ $c->categoryLabel() }}</td>
                    <td>@include('partials.status', ['status' => $c->status])</td>
                    <td class="small">{{ $c->created_at->format('d M Y') }}</td>
                    <td><a href="/complaints/{{ $c->id }}" class="btn ghost sm">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><div class="icon">📋</div>You have not filed any complaints. <a href="/complaints/create">File one now</a>.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $complaints->links('partials.pager') }}
@endsection
