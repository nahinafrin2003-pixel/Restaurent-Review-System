@if(session('status'))
    <div class="alert success">{{ session('status') }}</div>
@endif
@if(session('dev_otp'))
    <div class="alert warn"><strong>Development mode:</strong> e-mail delivery is not configured for this address, so here is your one-time code: <span class="mono" style="font-size:1rem;font-weight:700">{{ session('dev_otp') }}</span></div>
@endif
@if($errors->any())
    <div class="alert error">
        <strong>Please check the following:</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
