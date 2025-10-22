function confirmDelete(itemId, itemName) {
    if (confirm('¿Está seguro de eliminar el ítem de inventario: ' + itemName + '? Esta acción es irreversible.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'gestion_inventario.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_item';
        form.appendChild(actionInput);

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'item_id';
        idInput.value = itemId;
        form.appendChild(idInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function toggleFormInventario() {
    const form = document.getElementById('form-inventario');
    const btn = document.querySelector('.toggle-form-btn');

    if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block';
        btn.textContent = '− Ocultar Formulario';
    } else {
        form.style.display = 'none';
        btn.textContent = '+ Registrar Ítem de Inventario';
    }
}
