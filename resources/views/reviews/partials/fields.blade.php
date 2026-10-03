<div class="field">
    <label for="review_score">Score: <span id="score-out">{{ old('review_score', $review->review_score ?? 8) }}</span>/10</label>
    <input type="range" id="review_score" name="review_score" min="1" max="10" value="{{ old('review_score', $review->review_score ?? 8) }}" oninput="document.getElementById('score-out').textContent=this.value" style="width:100%;accent-color:var(--brand)">
</div>
<div class="field">
    <label for="review_text">Your review</label>
    <textarea id="review_text" name="review_text" maxlength="1000" required>{{ old('review_text', $review->review_text ?? '') }}</textarea>
    <div class="hint">10–1000 characters. Encrypted with RSA-OAEP, signed with ECDSA.</div>
</div>
<div class="field">
    <label for="location">Branch / location note <span class="muted">(optional)</span></label>
    <input id="location" name="location" maxlength="200" value="{{ old('location', $review->location ?? '') }}">
</div>
