/**
 * Exibe/oculta senha ao clicar no botão de olho
 */
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;

    const toggleButton = field.parentElement.querySelector('.password-toggle i');
    if (!toggleButton) return;

    if (field.type === 'password') {
        field.type = 'text';
        toggleButton.classList.remove('fa-eye');
        toggleButton.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        toggleButton.classList.remove('fa-eye-slash');
        toggleButton.classList.add('fa-eye');
    }
}