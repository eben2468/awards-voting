/**
 * Show or hide a password on the login and signup pages.
 */
document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
    button.addEventListener('click', function () {
        var input = document.getElementById(button.dataset.togglePassword);
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        button.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
});
