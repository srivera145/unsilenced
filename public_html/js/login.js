/* The admin sign-in page (views/auth/login.php). Which panels exist depends
 * on AUTH_METHOD, so each part checks its elements are on the page. The CSRF
 * token comes from the csrf-token meta tag every session page carries.
 */
(() => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const byId = (id) => document.getElementById(id);

    const showError = (msg) => {
        const el = byId('auth-error');
        el.textContent = msg;
        el.hidden = false;
    };

    const post = async (url, body) => {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(body),
        });
        return res.json();
    };

    /* Deck styles .tab from aria-selected but ships no tab behaviour, so the roving
       tabindex and the arrow keys are ours. */
    const tabs = Array.from(document.querySelectorAll('[role="tab"]'));

    const selectTab = (tab) => {
        tabs.forEach((candidate) => {
            const selected = candidate === tab;
            candidate.setAttribute('aria-selected', selected ? 'true' : 'false');
            candidate.tabIndex = selected ? 0 : -1;
            byId(candidate.getAttribute('aria-controls')).hidden = !selected;
        });
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => selectTab(tab));
        tab.addEventListener('keydown', (event) => {
            const step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
            if (!step) {
                return;
            }
            event.preventDefault();
            const next = tabs[(index + step + tabs.length) % tabs.length];
            next.focus();
            selectTab(next);
        });
    });

    byId('otp-send')?.addEventListener('click', async () => {
        const data = await post('/auth/otp/request', { email: byId('otp-email').value });
        if (data.success) {
            byId('otp-step-email').hidden = true;
            byId('otp-step-code').hidden = false;
            byId('otp-code').focus();
        } else {
            showError(data.message || 'Something went wrong.');
        }
    });

    byId('otp-verify')?.addEventListener('click', async () => {
        const data = await post('/auth/otp/verify', {
            email: byId('otp-email').value,
            code: byId('otp-code').value,
        });
        if (data.success) {
            window.location.href = data.redirect || '/admin';
        } else {
            showError(data.message || 'Invalid code.');
        }
    });

    byId('magic-send')?.addEventListener('click', async () => {
        const data = await post('/auth/magic/request', { email: byId('magic-email').value });
        if (data.success) {
            byId('magic-sent').hidden = false;
        } else {
            showError(data.message || 'Something went wrong.');
        }
    });
})();
