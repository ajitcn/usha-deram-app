(function () {
    'use strict';
    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-password-visibility]');
        if (!toggle) return;
        var target = document.getElementById(toggle.getAttribute('data-password-visibility'));
        if (!target) return;
        var visible = target.type === 'text';
        target.type = visible ? 'password' : 'text';
        toggle.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
        toggle.setAttribute('aria-pressed', visible ? 'false' : 'true');
        var icon = toggle.querySelector('i');
        if (icon) icon.className = visible ? 'fas fa-eye' : 'fas fa-eye-slash';
    });
    document.querySelectorAll('.password-auth-form').forEach(function (form) {
        form.addEventListener('submit', function () {
            var submit = form.querySelector('[type="submit"]');
            if (!submit || !form.checkValidity()) return;
            submit.disabled = true;
            submit.setAttribute('aria-busy', 'true');
            var label = submit.querySelector('[data-submit-label]');
            if (label) label.textContent = submit.getAttribute('data-loading-label') || 'Please wait…';
        });
    });
}());
