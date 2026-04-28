document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', () => {
        const input  = document.getElementById(btn.dataset.target);
        const isText = input.type === 'text';
        input.type   = isText ? 'password' : 'text';
        btn.querySelector('.eye-icon').style.display     = isText ? 'block' : 'none';
        btn.querySelector('.eye-off-icon').style.display = isText ? 'none'  : 'block';
    });
});

function fieldFromApiViolation (v) {
    const raw = v && (v.field != null && v.field !== '' ? v.field : v.propertyPath);
    if (raw == null || String(raw) === '') return null;
    let s = String(raw).trim();
    if (s.startsWith('[') && s.endsWith(']')) s = s.slice(1, -1);
    const dot = s.lastIndexOf('.');
    if (dot >= 0) s = s.slice(dot + 1);
    const b = s.indexOf('[');
    if (b >= 0) s = s.slice(0, b);
    return s || null;
}

function parseJsonBody (raw) {
    if (raw == null || String(raw) === '') return null;
    let t = String(raw);
    t = t.replace(/^\uFEFF/, '');
    t = t.trim();
    if (t === '' || t[0] === '<') {
        return null;
    }
    if (t[0] !== '{' && t[0] !== '[') {
        return null;
    }
    try {
        return JSON.parse(t);
    } catch {
        return null;
    }
}

function problemMessage (data) {
    if (!data || typeof data !== 'object') {
        return '';
    }
    if (data.detail) {
        return String(data.detail);
    }
    if (data.title) {
        return String(data.title);
    }
    if (data.message) {
        return String(data.message);
    }
    return '';
}

function bannerTextForUnknownError (res, data) {
    const m = problemMessage(data);
    if (m) {
        return m;
    }
    if (res.status) {
        return 'The server returned an error (HTTP ' + res.status
            + ') and the response was not a JSON error object we can read. Request failed. Try again, or use devtools Network tab to inspect the response body.';
    }
    return 'The request could not be completed (no HTTP status, often blocked or offline).';
}

document.getElementById('register-form').addEventListener('submit', async (e) => {
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

    const fieldToInputId = {
        firstName: 'firstName',
        lastName:  'lastName',
        email:     'email',
        password:  'password',
    };

    const applyFieldError = (field, message) => {
        const id  = fieldToInputId[field] ?? null;
        if (!id) return;
        const input = document.getElementById(id);
        const errEl = document.getElementById('err-' + id);
        if (input) input.classList.add('is-invalid');
        if (errEl) errEl.textContent = message;
    };

    const payload = {
        firstName: (document.getElementById('firstName')?.value ?? '').trim(),
        lastName:  (document.getElementById('lastName')?.value ?? '').trim(),
        email:     (document.getElementById('email')?.value ?? '').trim(),
        password:  (document.getElementById('password')?.value ?? '').trim(),
    };

    try {
        const res  = await fetch('/api/v1/auth/register', {
            method:      'POST',
            credentials: 'include',
            headers:     {
                'Content-Type': 'application/json',
                Accept:         'application/json, application/problem+json',
            },
            body: JSON.stringify(payload),
        });

        const raw  = await res.text();
        const data = parseJsonBody(raw);

        if (res.ok) {
            window.location.href = '/';
            return;
        }

        const isValidation = (res.status === 422) || (data && data.code === 'validation_error');
        const violations   = (data && Array.isArray(data.violations)) ? data.violations : [];

        if (isValidation) {
            if (violations.length > 0) {
                let mapped = 0;
                violations.forEach((v) => {
                    const field   = fieldFromApiViolation(v);
                    const message = (v && v.message) ? String(v.message) : '';
                    if (field) {
                        applyFieldError(field, message);
                        mapped++;
                    }
                });
                if (mapped > 0) {
                    if (data && (data.detail || data.title) && (mapped < violations.length)) {
                        banner.textContent   = (data.detail || data.title) + '';
                        banner.style.display = 'block';
                    }
                } else {
                    banner.textContent   = (data && (data.detail || data.title)) || 'Validation failed, but the server did not return field names we understand.';
                    banner.style.display = 'block';
                }
            } else {
                banner.textContent   = (data && (data.detail || data.title))
                    || (res.status
                        ? 'The server said the request is invalid (HTTP ' + res.status
                            + ') but we could not read the field list. Check your connection and try again.'
                        : 'Validation failed.');
                banner.style.display = 'block';
            }
        } else {
            banner.textContent   = bannerTextForUnknownError(res, data);
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
