<?php
require_once __DIR__ . '/inc/auth.php';
exigirPermissaoPagina('classes');
require_once __DIR__ . '/inc/db.php';

$paginaTitulo = 'Classes de Ferramentas';

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'criar';
    $classeId = (int)($_POST['classe_id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');

    if ($acao === 'excluir') {
        if ($classeId <= 0) {
            $erro = 'Classe inválida para exclusão.';
        } else {
            try {
                $stmt = $pdo->prepare('DELETE FROM classes WHERE id = :id');
                $stmt->execute([':id' => $classeId]);
                $mensagem = 'Classe excluída com sucesso.';
            } catch (PDOException $e) {
                $erro = 'Não foi possível excluir a classe. Há ferramentas vinculadas?';
            }
        }
    } else {
        if ($nome === '') {
            $erro = 'Informe o nome da classe.';
        } elseif ($acao === 'atualizar' && $classeId <= 0) {
            $erro = 'Classe inválida para edição.';
        } else {
            try {
                if ($acao === 'atualizar') {
                    $stmt = $pdo->prepare('UPDATE classes SET nome = :nome, descricao = :descricao WHERE id = :id');
                    $stmt->execute([
                        ':id' => $classeId,
                        ':nome' => $nome,
                        ':descricao' => $descricao !== '' ? $descricao : null,
                    ]);
                    $mensagem = 'Classe atualizada com sucesso.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO classes (nome, descricao) VALUES (:nome, :descricao)');
                    $stmt->execute([
                        ':nome' => $nome,
                        ':descricao' => $descricao !== '' ? $descricao : null,
                    ]);
                    $mensagem = 'Classe cadastrada com sucesso.';
                }
            } catch (PDOException $e) {
                $erro = 'Não foi possível salvar a classe. Verifique se o nome já existe.';
            }
        }
    }
}

$stmt = $pdo->query('SELECT id, nome, descricao FROM classes ORDER BY nome');
$classes = $stmt->fetchAll();

include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
?>
<div class="content-area classes-page">
    <div class="page-header d-flex justify-content-between align-items-start">
        <div>
            <h1>Classes de Ferramentas</h1>
            <p class="text-muted mb-0">Gerencie as classes (categorias) de ferramentas</p>
        </div>
        <button class="btn btn-primary rounded-pill px-3" id="openModal">+ Nova Classe</button>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-success rounded-3 py-2 px-3"><?= htmlspecialchars($mensagem); ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alert alert-danger rounded-3 py-2 px-3"><?= htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <div class="search-wrapper mb-3">
        <span>&#128269;</span>
        <input type="text" id="searchInput" placeholder="Buscar classe...">
    </div>

    <div class="table-card">
        <table class="table mb-0" id="classesTable">
            <thead>
            <tr>
                <th>Nome</th>
                <th>Descrição</th>
                <th class="text-end">Ações</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($classes)): ?>
                <tr>
                    <td colspan="3" class="text-center text-muted py-4">Nenhuma classe cadastrada.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($classes as $classe): ?>
                    <tr data-nome="<?= htmlspecialchars(strtolower($classe['nome'])); ?>">
                        <td><?= htmlspecialchars($classe['nome']); ?></td>
                        <td><?= htmlspecialchars($classe['descricao'] ?? '-'); ?></td>
                        <td class="text-end actions">
                            <button
                                type="button"
                                class="btn-action btn-edit"
                                data-id="<?= (int)$classe['id']; ?>"
                                data-nome="<?= htmlspecialchars($classe['nome']); ?>"
                                data-descricao="<?= htmlspecialchars($classe['descricao'] ?? ''); ?>"
                                title="Editar">
                                ✏️
                            </button>
                            <button
                                type="button"
                                class="btn-action btn-delete"
                                data-id="<?= (int)$classe['id']; ?>"
                                data-nome="<?= htmlspecialchars($classe['nome']); ?>"
                                title="Excluir">
                                🗑️
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="modal">
    <div class="modal-card">
        <div class="modal-header">
            <div>
                <h3 id="modalTitle">Nova Classe</h3>
                <span id="modalSubtitle">Cadastre uma nova classe de ferramentas</span>
            </div>
            <button class="modal-close" id="closeModal">×</button>
        </div>
        <form class="modal-body" id="classeForm" method="POST">
            <input type="hidden" name="acao" value="criar" id="classeAcao">
            <input type="hidden" name="classe_id" value="" id="classeId">
            <div class="form-group">
                <label>Nome *</label>
                <input type="text" name="nome" id="classeNome" placeholder="Ex: Consumível" required>
            </div>
            <div class="form-group">
                <label>Descrição</label>
                <textarea name="descricao" id="classeDescricao" rows="3" placeholder="Descrição opcional da classe"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="cancelModal">Cancelar</button>
                <button type="submit" class="btn-modal-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="acao" value="excluir">
    <input type="hidden" name="classe_id" id="deleteClasseId">
</form>

<style>
    .classes-page { padding: 1.5rem; }
    .search-wrapper { position: relative; }
    .search-wrapper span {
        position: absolute;
        left: 0.9rem;
        top: 50%;
        transform: translateY(-50%);
        color: #9CA3AF;
    }
    .search-wrapper input {
        width: 100%;
        border-radius: 0.9rem;
        border: 1px solid #CBD5F5;
        padding: 0.65rem 0.9rem 0.65rem 2.6rem;
        font-size: 0.95rem;
    }
    .table-card {
        border: 1px solid #E5E7EB;
        border-radius: 1.1rem;
        overflow: hidden;
        background: #FFF;
    }
    .table-card table { width: 100%; border-collapse: collapse; }
    th, td { padding: 0.9rem 1rem; border-bottom: 1px solid #F3F4F6; font-size: 0.95rem; }
    th {
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        color: #6B7280;
        background: #F9FAFB;
    }
    .actions .btn-action {
        border: none;
        background: transparent;
        cursor: pointer;
        font-size: 1.1rem;
        margin-left: 0.35rem;
    }
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        z-index: 1000;
    }
    .modal-overlay.active { display: flex; }
    .modal-card {
        background: #FFF;
        border-radius: 1rem;
        width: min(520px, 100%);
        box-shadow: 0 40px 90px rgba(15, 23, 42, 0.3);
        overflow: hidden;
    }
    .modal-header {
        padding: 1.4rem 1.6rem;
        border-bottom: 1px solid #F0F2F5;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
    }
    .modal-header h3 { margin-bottom: 0.2rem; font-size: 1.2rem; }
    .modal-header span { color: #6B7280; }
    .modal-close {
        border: none;
        background: transparent;
        font-size: 1.4rem;
        cursor: pointer;
    }
    .modal-body {
        padding: 1.5rem;
        background: #F9FAFB;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .modal-body .form-group { display: flex; flex-direction: column; gap: 0.35rem; }
    .modal-body input,
    .modal-body textarea {
        border: 1px solid #CBD5F5;
        border-radius: 0.85rem;
        padding: 0.65rem 0.8rem;
        background: #FFF;
        font-family: inherit;
        font-size: 0.95rem;
    }
    .modal-actions {
        padding: 1rem 1.5rem 1.5rem;
        background: #F9FAFB;
        display: flex;
        justify-content: flex-end;
        gap: 0.7rem;
    }
    .btn-secondary {
        border: 1px solid #E5E7EB;
        border-radius: 0.85rem;
        padding: 0.6rem 1.2rem;
        background: #FFF;
        cursor: pointer;
    }
    .btn-modal-primary {
        border: none;
        border-radius: 0.85rem;
        padding: 0.6rem 1.3rem;
        background: #1F56D8;
        color: #FFF;
        font-weight: 600;
        cursor: pointer;
    }
</style>
<script>
    const modal = document.getElementById('modal');
    const openModalBtn = document.getElementById('openModal');
    const closeModalBtn = document.getElementById('closeModal');
    const cancelModalBtn = document.getElementById('cancelModal');
    const searchInput = document.getElementById('searchInput');
    const modalTitle = document.getElementById('modalTitle');
    const modalSubtitle = document.getElementById('modalSubtitle');
    const classeForm = document.getElementById('classeForm');
    const acaoInput = document.getElementById('classeAcao');
    const idInput = document.getElementById('classeId');
    const nomeInput = document.getElementById('classeNome');
    const descricaoInput = document.getElementById('classeDescricao');
    const deleteForm = document.getElementById('deleteForm');
    const deleteIdInput = document.getElementById('deleteClasseId');

    function toggleModal(show) {
        modal.classList.toggle('active', show);
    }

    function prepararModalCriar() {
        modalTitle.textContent = 'Nova Classe';
        modalSubtitle.textContent = 'Cadastre uma nova classe de ferramentas';
        acaoInput.value = 'criar';
        idInput.value = '';
        nomeInput.value = '';
        descricaoInput.value = '';
        toggleModal(true);
        nomeInput.focus();
    }

    function prepararModalEditar(button) {
        modalTitle.textContent = 'Editar Classe';
        modalSubtitle.textContent = 'Atualize as informações da classe selecionada';
        acaoInput.value = 'atualizar';
        idInput.value = button.dataset.id;
        nomeInput.value = button.dataset.nome || '';
        descricaoInput.value = button.dataset.descricao || '';
        toggleModal(true);
        nomeInput.focus();
    }

    openModalBtn.addEventListener('click', prepararModalCriar);
    closeModalBtn.addEventListener('click', () => toggleModal(false));
    cancelModalBtn.addEventListener('click', () => toggleModal(false));
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            toggleModal(false);
        }
    });

    document.querySelectorAll('.btn-edit').forEach((button) => {
        button.addEventListener('click', () => prepararModalEditar(button));
    });

    document.querySelectorAll('.btn-delete').forEach((button) => {
        button.addEventListener('click', () => {
            const nome = button.dataset.nome || 'esta classe';
            if (confirm(`Deseja realmente excluir ${nome}?`)) {
                deleteIdInput.value = button.dataset.id;
                deleteForm.submit();
            }
        });
    });

    searchInput.addEventListener('input', () => {
        const term = searchInput.value.toLowerCase();
        document.querySelectorAll('#classesTable tbody tr').forEach((row) => {
            row.style.display = row.dataset.nome.includes(term) ? '' : 'none';
        });
    });
</script>
<?php include __DIR__ . '/inc/footer.php'; ?>
