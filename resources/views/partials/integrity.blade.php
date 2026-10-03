<div class="integrity">
    <span class="badge {{ $integrity['mac'] ? 'good' : 'bad' }}" title="HMAC-SHA256 over the decrypted content">{{ $integrity['mac'] ? '✔' : '✘' }} HMAC</span>
    <span class="badge {{ $integrity['signature'] ? 'good' : 'bad' }}" title="ECDSA signature on secp256k1">{{ $integrity['signature'] ? '✔' : '✘' }} ECC signature</span>
</div>
