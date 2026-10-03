@extends('layouts.app')
@section('title', 'Customers')
@section('content')
    <div class="page-head"><div><h1>Customers</h1><p class="sub">Deactivating an account revokes all its sessions immediately.</p></div><a href="/admin/officers" class="btn ghost">← Officers</a></div>
    <div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Customer</th><th>Complaints</th><th>Points</th><th>Status</th><th>Sessions</th><th></th></tr></thead>
        <tbody>
        @forelse($users as $u)
            <tr>
                <td>#{{ $u->user_id }}</td>
                <td><strong>{{ $u->username }}</strong><div class="small muted">{{ $u->email }}</div></td>
                <td>{{ $u->complaints_count }}</td>
                <td>{{ (int) $u->points }}</td>
                <td><span class="badge {{ $u->isActive() ? 'good' : 'bad' }}">{{ $u->isActive() ? 'Active' : 'Deactivated' }}</span></td>
                <td>{{ $sessions[$u->user_id] ?? 0 }}</td>
                <td><div class="row">
                    <form method="POST" action="/admin/users/{{ $u->user_id }}/toggle">@csrf<button class="btn sm {{ $u->isActive() ? 'danger' : '' }}">{{ $u->isActive() ? 'Deactivate' : 'Activate' }}</button></form>
                    <form method="POST" action="/admin/users/{{ $u->user_id }}/revoke-sessions">@csrf<button class="btn ghost sm" @disabled(empty($sessions[$u->user_id]))>Revoke sessions</button></form>
                </div></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No customers yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $users->links('partials.pager') }}
@endsection
