function toggleForm() {
    const form = document.getElementById('form-nomina');
    const btn = document.querySelector('.toggle-form-btn');
    
    if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block';
        btn.textContent = '− Ocultar Formulario';
    } else {
        form.style.display = 'none';
        btn.textContent = '+ Registrar Pago de Nómina';
    }
}
