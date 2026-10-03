@extends('layouts.app')
@section('title', 'Audit log')
@section('content')
    <div class="page-head"><div><h1>Audit log</h1><p class="sub">Logins, evidence views, status changes, report downloads, account changes and integrity failures.</p></div></div>
    <div class="tabs">
        <a href="/admin/audit" class="{{ request('action') ? '' : 'active' }}">All</a>
        @foreach($groups as $g)<a href="?action={{ $g }}" class="{{ request('action') === $g ? 'active' : '' }}">{{ ucfirst($g) }}</a>@endforeach
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>When</th><th>Action</th><th>User</th><th>Subject</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
        @forelse($logs as $log)
            <tr>
                <td class="small" style="white-space:nowrap">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                <td><span class="tag" style="{{ str_contains($log->action, 'fail') || str_contains($log->action, 'tamper') ? 'background:var(--danger-soft);color:var(--danger)' : '' }}">{{ $log->action }}</span></td>
                <td class="small">{{ $log->user ? $log->user->roleLabel() . ' #' . $log->user_id : '—' }}</td>
                <td class="small">{{ $log->subject_type ? $log->subject_type . ' #' . $log->subject_id : '' }}</td>
                <td class="mono" style="max-width:320px">{{ $log->details ? json_encode($log->details, JSON_UNESCAPED_SLASHES) : '' }}</td>
                <td class="small">{{ $log->ip_address }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No entries.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $logs->links('partials.pager') }}
@endsection
