document.addEventListener('DOMContentLoaded', () => {
    const filterInputs = document.querySelectorAll('[data-table-filter]');

    document.querySelectorAll('.page-header').forEach((header) => {
        if (header.children.length > 1 && !header.classList.contains('with-actions')) {
            header.classList.add('with-actions');
            const actions = header.lastElementChild;
            if (actions && !actions.classList.contains('page-actions')) {
                actions.classList.add('page-actions');
            }
        }
    });

    filterInputs.forEach((input) => {
        input.addEventListener('input', () => {
            const targetSelector = input.dataset.tableFilter;
            const term = input.value.toLowerCase();
            const table = document.querySelector(targetSelector);

            if (!table) {
                return;
            }

            table.querySelectorAll('tbody tr').forEach((row) => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });
    });

    document.querySelectorAll('[data-action="reset-senha"]').forEach((button) => {
        button.addEventListener('click', () => {
            const usuario = button.dataset.usuario || 'usuário';
            alert(`Senha do ${usuario} foi resetada (simulação).`);
        });
    });
});
