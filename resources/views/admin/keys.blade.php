@extends('layouts.app')
@section('title', 'Key management')
@section('content')
    <div class="page-head">
        <div><h1>Key management</h1><p class="sub">RSA-2048 (OAEP) protects PII and complaint/review text; secp256k1 signs records and encrypts messages (EC-ElGamal). Rotated keys stay available for decryption and signature checks.</p></div>
        <form method="POST" action="/admin/keys/rotate" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Generating primes…'">@csrf<button class="btn accent">Rotate keys</button></form>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Type</th><th>Status</th><th>Created</th><th>Public-key fingerprint (SHA-256)</th></tr></thead>
        <tbody>
        @foreach($keys as $k)
            <tr><td>#{{ $k['id'] }}</td><td>{{ $k['type'] }}</td><td><span class="badge {{ $k['status'] === 'active' ? 'good' : '' }}">{{ $k['status'] }}</span></td><td class="small">{{ optional($k['created_at'])->format('d M Y H:i') }}</td><td class="mono">{{ $k['fingerprint'] }}</td></tr>
        @endforeach
        </tbody>
    </table></div>
@endsection
