document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', () => {
        const input  = document.getElementById(btn.dataset.target);
        const isText = input.type === 'text';
        input.type   = isText ? 'password' : 'text';
        btn.querySelector('.eye-icon').style.display     = isText ? 'block' : 'none';
        btn.querySelector('.eye-off-icon').style.display = isText ? 'none'  : 'block';
    });
});

document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();

    const form      = e.target;
    const submitBtn = document.getElementById('submit-btn');
    const banner    = document.getElementById('error-banner');

    banner.style.display = 'none';
    form.querySelectorAll('input').forEach(i => i.classList.remove('is-invalid'));
    form.querySelectorAll('.field-error').forEach(el => el.textContent = '');

    submitBtn.disabled = true;
    document.getElementById('btn-label').style.display   = 'none';
    document.getElementById('btn-spinner').style.display = 'inline';

    try {
        const res = await fetch('/api/auth/login', {
            method:      'POST',
            credentials: 'include',
            headers:     { 'Content-Type': 'application/json' },
            body:        JSON.stringify({
                email:    form.email.value.trim(),
                password: form.password.value.trim(),
            }),
        });

        if (res.ok) {
            window.location.href = '/';
            return;
        }

        const data = await res.json();

        if (res.status === 401) {
            const emailInput    = form.email;
            const passwordInput = form.password;
            emailInput.classList.add('is-invalid');
            passwordInput.classList.add('is-invalid');
            banner.textContent   = data.message ?? 'Invalid credentials.';
            banner.style.display = 'block';
        } else {
            banner.textContent   = data.message ?? 'Something went wrong. Please try again.';
            banner.style.display = 'block';
        }
    } catch {
        banner.textContent   = 'Network error. Please check your connection.';
        banner.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        document.getElementById('btn-label').style.display   = 'inline';
        document.getElementById('btn-spinner').style.display = 'none';
    }
});
