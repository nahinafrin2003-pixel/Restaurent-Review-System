<span class="trust {{ $restaurant->trustLevel() }}" title="Trust score {{ number_format($restaurant->trust_score, 2) }} / 5">
    <span class="stars">{{ $restaurant->stars() }}</span>
    <span class="num">{{ number_format($restaurant->trust_score, 1) }}</span>
</span>
