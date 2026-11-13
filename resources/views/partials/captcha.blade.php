<div class="mb-3">
    <label class="form-label" for="captcha">Security check: what is <span class="captcha-q">{{ $captcha }}</span> ? <span class="req">*</span></label>
    <input type="text" inputmode="numeric" autocomplete="off" maxlength="3" class="form-control @error('captcha') is-invalid @enderror" id="captcha" name="captcha" required data-digits-only>
    @error('captcha')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
