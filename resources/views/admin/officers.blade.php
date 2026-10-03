@extends('layouts.app')
@section('title', 'Officers')
@section('content')
    <div class="page-head">
        <div><h1>Verification officers</h1><p class="sub">Officers cannot self-register — only the administrator can provision them.</p></div>
        <a href="/admin/users" class="btn ghost">Customers →</a>
    </div>
    <div class="split">
        <div class="table-wrap"><table>
            <thead><tr><th>Officer</th><th>Status</th><th>Open cases</th><th>Sessions</th><th></th></tr></thead>
            <tbody>
            @forelse($officers as $o)
                <tr>
                    <td><strong>{{ $o->username }}</strong><div class="small muted">{{ $o->email }}</div></td>
                    <td><span class="badge {{ $o->isActive() ? 'good' : 'bad' }}">{{ $o->isActive() ? 'Active' : 'Deactivated' }}</span></td>
                    <td>{{ $assigned[$o->user_id] ?? 0 }}</td>
                    <td>{{ $sessions[$o->user_id] ?? 0 }}</td>
                    <td><div class="row">
                        <form method="POST" action="/admin/users/{{ $o->user_id }}/toggle">@csrf<button class="btn sm {{ $o->isActive() ? 'danger' : '' }}">{{ $o->isActive() ? 'Deactivate' : 'Activate' }}</button></form>
                        <form method="POST" action="/admin/users/{{ $o->user_id }}/revoke-sessions">@csrf<button class="btn ghost sm" @disabled(empty($sessions[$o->user_id]))>Revoke sessions</button></form>
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty">No officers yet — create one.</div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        <form method="POST" action="/admin/officers" class="card">
            @csrf
            <h2>Provision an officer</h2>
            <div class="field"><label>Username</label><input name="username" value="{{ old('username') }}" maxlength="10" required><div class="hint">10 chars: letters, numbers, symbol.</div></div>
            <div class="field"><label>E-mail</label><input type="email" name="email" value="{{ old('email') }}" required></div>
            <div class="field"><label>Mobile</label><input name="contact_info" value="{{ old('contact_info') }}" maxlength="11" required></div>
            <div class="field"><label>Initial password</label><input type="password" name="password" minlength="12" required></div>
            <button class="btn block">Create officer</button>
        </form>
    </div>
@endsection
