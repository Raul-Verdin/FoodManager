function toggleForm() {
    const form = document.getElementById('formContainer');
    const btn = document.querySelector('.toggle-form-btn');

    form.classList.toggle('show');

    if (form.classList.contains('show')) {
        btn.textContent = '− Ocultar Formulario';
    } else {
        btn.textContent = '+ Registrar Pago de Nómina';
    }
}

// Si el formulario ya está visible al cargar, hacemos scroll hacia él
window.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('formContainer');
    const btn = document.querySelector('.toggle-form-btn');

    if (form.classList.contains('show')) {
        btn.textContent = '− Ocultar Formulario';
        form.scrollIntoView({ behavior: 'smooth' });
    }
});
