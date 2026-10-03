@extends('layouts.app')
@section('title', 'Moderation trail')
@section('content')
    <div class="page-head"><div><h1>Moderation audit trail</h1><p class="sub">Every approve/reject decision on reviews.</p></div><a href="/admin/audit" class="btn ghost">Full audit log</a></div>
    <div class="table-wrap"><table>
        <thead><tr><th>When</th><th>Admin</th><th>Review</th><th>Decision</th></tr></thead>
        <tbody>
        @forelse($actions as $a)
            <tr><td class="small">{{ optional($a->action_time)->format('d M Y H:i') }}</td><td>#{{ $a->admin_id }}</td>
                <td>#{{ $a->review_id }} · {{ $a->review->restaurant_name ?? '' }}</td>
                <td><span class="badge s-{{ $a->status }}">{{ ucfirst($a->status) }}</span></td></tr>
        @empty
            <tr><td colspan="4" class="muted">No moderation actions yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $actions->links('partials.pager') }}
@endsection
