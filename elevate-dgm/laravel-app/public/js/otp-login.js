(() => {
    'use strict';
    const panel = document.getElementById('otp-panel');
    if (!panel) return;
    const form = document.getElementById('otp-form');
    const original = document.getElementById('otp');
    const group = document.getElementById('otp-digits');
    const digits = [...group.querySelectorAll('input')];
    const verify = document.getElementById('otp-verify');
    const resendForm = document.getElementById('otp-resend-form');
    const resend = document.getElementById('otp-resend');
    const timer = document.getElementById('otp-timer');
    const lock = document.getElementById('otp-lock');
    const feedback = document.getElementById('otp-feedback');
    const announcement = document.getElementById('otp-announcement');
    const limit = document.getElementById('otp-resend-limit');
    let state = JSON.parse(panel.dataset.state);
    let serverTime = Date.parse(state.server_now);
    let synchronizedAt = performance.now();
    let busy = false;
    let refreshing = false;
    let observedState = '';
    let requestSequence = 0;
    const now = () => serverTime + performance.now() - synchronizedAt;
    const secondsTo = value => value ? Math.max(0, Math.ceil((Date.parse(value) - now()) / 1000)) : 0;
    const mmss = seconds => `${Math.floor(seconds / 60).toString().padStart(2, '0')}:${(seconds % 60).toString().padStart(2, '0')}`;
    const expired = () => !secondsTo(state.expires_at);
    const locked = () => secondsTo(state.locked_until) > 0;
    const unavailable = () => expired() || locked() || Number(state.attempts_left) <= 0 || state.delivery_failed;

    function message(text, error = false) {
        feedback.textContent = text || '';
        feedback.className = `${error ? 'error' : 'notice'} otp-feedback`;
    }
    function render() {
        const remaining = secondsTo(state.expires_at);
        const timerState = remaining === 0 ? 'expired' : remaining <= 60 ? 'warning' : 'normal';
        timer.dataset.state = timerState;
        timer.textContent = remaining ? `Code expires in ${mmss(remaining)}` : 'Code expired. Request a new code.';
        if (timerState !== observedState) {
            if (timerState === 'warning') announcement.textContent = 'One minute or less left. Enter your code soon.';
            if (timerState === 'expired') announcement.textContent = 'Code expired. Request a new code.';
            observedState = timerState;
        }
        const lockSeconds = secondsTo(state.locked_until);
        lock.hidden = !lockSeconds && Number(state.attempts_left) > 0;
        lock.textContent = lockSeconds
            ? `Too many attempts. Try again in ${mmss(lockSeconds)} or request a new code.`
            : 'No attempts left. Request a new code.';
        digits.forEach(input => { input.disabled = busy || unavailable(); });
        verify.disabled = busy || unavailable();
        verify.querySelector('.otp-spinner').hidden = !busy;
        verify.querySelector('[data-button-label]').textContent = busy ? 'Please wait…' : 'Verify code and sign in';
        form.setAttribute('aria-busy', String(busy));
        const cooldown = secondsTo(state.resend_available_at);
        const limitSeconds = secondsTo(state.resend_limit_resets_at);
        const exhausted = Number(state.resends_left) <= 0 && limitSeconds > 0;
        resend.disabled = busy || cooldown > 0 || exhausted;
        resend.textContent = cooldown > 0 ? `Resend code in ${Math.floor(cooldown / 60)}:${String(cooldown % 60).padStart(2, '0')}` : exhausted ? 'Resend limit reached' : 'Resend code';
        resend.classList.toggle('resend-emphasis', unavailable() && !resend.disabled);
        limit.textContent = exhausted
            ? `Resend limit reached. Try again in ${mmss(limitSeconds)}.`
            : `${state.resends_left} resend${Number(state.resends_left) === 1 ? '' : 's'} remaining in this window.`;
    }
    function synchronize(data) {
        if (!data.server_now) return;
        state = { ...state, ...data };
        serverTime = Date.parse(data.server_now);
        synchronizedAt = performance.now();
        if (state.delivery_failed) message('We could not send a code. Please request a new code when available.', true);
        render();
    }
    function clear() {
        original.value = '';
        digits.forEach(input => { input.value = ''; });
    }
    function focusEmpty() {
        if (!unavailable()) (digits.find(input => !input.value) || digits[0]).focus();
    }
    function updateValue(autoSubmit = true) {
        original.value = digits.map(input => input.value).join('');
        digits.forEach(input => input.setAttribute('aria-invalid', 'false'));
        if (autoSubmit && original.value.length === 6 && !busy && !unavailable()) form.requestSubmit();
    }
    function fill(value, start) {
        const cleaned = value.replace(/\D/g, '');
        const offset = cleaned.length >= 6 ? 0 : start;
        cleaned.slice(0, 6 - offset).split('').forEach((digit, index) => { digits[offset + index].value = digit; });
        digits[Math.min(offset + cleaned.length, 5)].focus();
        updateValue();
    }
    digits.forEach((input, index) => {
        input.addEventListener('input', () => {
            const value = input.value.replace(/\D/g, '');
            input.value = value.slice(0, 1);
            if (value.length > 1) return fill(value, index);
            if (value && index < 5) digits[index + 1].focus();
            updateValue();
        });
        input.addEventListener('paste', event => {
            event.preventDefault();
            fill(event.clipboardData.getData('text'), index);
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'Backspace' && !input.value && index > 0) {
                event.preventDefault();
                digits[index - 1].value = '';
                digits[index - 1].focus();
                updateValue(false);
            } else if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                event.preventDefault();
                digits[Math.max(0, Math.min(5, index + (event.key === 'ArrowLeft' ? -1 : 1)))].focus();
            }
        });
        input.addEventListener('focus', () => input.select());
    });
    async function submit(target, isResend) {
        if (busy || (isResend ? resend.disabled : unavailable())) return;
        if (!isResend && original.value.length !== 6) {
            message('Enter all six digits to continue.', true);
            focusEmpty();
            return;
        }
        busy = true;
        requestSequence++;
        render();
        let succeeded = false;
        try {
            const response = await fetch(target.action, { method: 'POST', body: new FormData(target), credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json();
            if (data.redirect) { window.location.assign(data.redirect); return; }
            synchronize(data);
            if (!data.server_now && response.status === 419) {
                message('Your session expired. Refresh this page and try again.', true);
            } else {
                message(data.message || (response.ok ? 'A new code has been requested. Check your email.' : 'We could not verify that code. Please try again.'), !response.ok);
            }
            succeeded = response.ok;
            if (isResend && response.ok) { clear(); observedState = ''; }
            if (!isResend && !response.ok) {
                clear();
                digits.forEach(input => input.setAttribute('aria-invalid', 'true'));
            }
        } catch {
            message('Connection interrupted. Please try again.', true);
        } finally {
            busy = false;
            render();
            if (!isResend || succeeded) focusEmpty();
        }
    }
    form.addEventListener('submit', event => { event.preventDefault(); submit(form, false); });
    resendForm.addEventListener('submit', event => { event.preventDefault(); submit(resendForm, true); });
    async function refreshState() {
        if (document.hidden || busy || refreshing) return;
        refreshing = true;
        const sequence = requestSequence;
        try {
            const response = await fetch(panel.dataset.stateUrl, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!busy && sequence === requestSequence) {
                if (data.redirect) { window.location.assign(data.redirect); return; }
                if (response.ok) synchronize(data);
            }
        } catch { /* Keep the conservative monotonic timer; the server verifies every action. */ }
        finally { refreshing = false; }
    }
    original.type = 'hidden';
    original.required = false;
    group.hidden = false;
    document.getElementById('otp-label').htmlFor = '';
    synchronize(state);
    focusEmpty();
    setInterval(render, 1000);
    document.addEventListener('visibilitychange', refreshState);
    window.addEventListener('pageshow', refreshState);
})();
