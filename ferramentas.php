<?php
require_once __DIR__ . '/inc/auth.php';
exigirPermissaoPagina('ferramentas');
require_once __DIR__ . '/inc/db.php';

$paginaTitulo = 'Ferramentas';
$mensagem = '';
$erro = '';

$classes = $pdo->query('SELECT id, nome FROM classes ORDER BY nome')->fetchAll();
$modelos = $pdo->query('SELECT id, nome FROM modelos ORDER BY nome')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'criar';
    $ferramentaId = (int)($_POST['ferramenta_id'] ?? 0);
    $codigo = trim($_POST['codigo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $classe = (int)($_POST['classe_id'] ?? 0);
    $modelo = (int)($_POST['modelo_id'] ?? 0);
    $quantidade = (int)($_POST['quantidade_total'] ?? 0);
    $localizacao = trim($_POST['localizacao'] ?? '');

    if ($acao === 'excluir') {
        if ($ferramentaId <= 0) {
            $erro = 'Ferramenta inválida para exclusão.';
        } else {
            try {
                $stmt = $pdo->prepare('DELETE FROM ferramentas WHERE id = :id');
                $stmt->execute([':id' => $ferramentaId]);
                $mensagem = 'Ferramenta excluída com sucesso.';
            } catch (PDOException $e) {
                $erro = 'Não foi possível excluir a ferramenta. Existem empréstimos vinculados?';
            }
        }
    } else {
        if ($codigo === '' || $descricao === '' || $classe <= 0 || $modelo <= 0 || $quantidade <= 0) {
            $erro = 'Preencha todos os campos obrigatórios.';
        } elseif ($acao === 'atualizar' && $ferramentaId <= 0) {
            $erro = 'Ferramenta inválida para edição.';
        } else {
            try {
                if ($acao === 'atualizar') {
                    $stmt = $pdo->prepare('SELECT quantidade_total, quantidade_disponivel FROM ferramentas WHERE id = :id');
                    $stmt->execute([':id' => $ferramentaId]);
                    $atual = $stmt->fetch();
                    if (!$atual) {
                        throw new RuntimeException('Ferramenta não encontrada.');
                    }
                    $delta = $quantidade - (int)$atual['quantidade_total'];
                    $novaDisponivel = (int)$atual['quantidade_disponivel'] + $delta;
                    $novaDisponivel = max(0, min($quantidade, $novaDisponivel));

                    $stmt = $pdo->prepare('UPDATE ferramentas SET codigo = :codigo, descricao = :descricao, classe_id = :classe, modelo_id = :modelo, quantidade_total = :quantidade, quantidade_disponivel = :disponivel, localizacao = :localizacao WHERE id = :id');
                    $stmt->execute([
                        ':codigo' => $codigo,
                        ':descricao' => $descricao,
                        ':classe' => $classe,
                        ':modelo' => $modelo,
                        ':quantidade' => $quantidade,
                        ':disponivel' => $novaDisponivel,
                        ':localizacao' => $localizacao !== '' ? $localizacao : null,
                        ':id' => $ferramentaId,
                    ]);
                    $mensagem = 'Ferramenta atualizada com sucesso.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO ferramentas (codigo, descricao, classe_id, modelo_id, quantidade_total, quantidade_disponivel, localizacao, status) VALUES (:codigo, :descricao, :classe, :modelo, :quantidade, :quantidade, :localizacao, "Disponível")');
                    $stmt->execute([
                        ':codigo' => $codigo,
                        ':descricao' => $descricao,
                        ':classe' => $classe,
                        ':modelo' => $modelo,
                        ':quantidade' => $quantidade,
                        ':localizacao' => $localizacao !== '' ? $localizacao : null,
                    ]);
                    $mensagem = 'Ferramenta cadastrada com sucesso.';
                }
            } catch (RuntimeException $e) {
                $erro = $e->getMessage();
            } catch (PDOException $e) {
                $erro = 'Não foi possível salvar a ferramenta. Verifique se o código já existe.';
            }
        }
    }
}

$sql = 'SELECT f.id, f.codigo, f.descricao, f.quantidade_total, f.quantidade_disponivel, f.localizacao, f.status, c.id AS classe_id, c.nome AS classe, m.id AS modelo_id, m.nome AS modelo
        FROM ferramentas f
        INNER JOIN classes c ON c.id = f.classe_id
        INNER JOIN modelos m ON m.id = f.modelo_id
        ORDER BY f.descricao';
$ferramentas = $pdo->query($sql)->fetchAll();

include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
?>
<div class="content-area ferramentas-page">
    <div class="page-header d-flex justify-content-between align-items-start">
        <div>
            <h1>Ferramentas</h1>
            <p class="text-muted mb-0">Gerencie o cadastro e o estoque de ferramentas</p>
        </div>
        <button class="btn btn-primary rounded-pill px-3" id="openModal">+ Nova Ferramenta</button>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-success rounded-3 py-2 px-3"><?= htmlspecialchars($mensagem); ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alert alert-danger rounded-3 py-2 px-3"><?= htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <div class="search-wrapper mb-3">
        <span>&#128269;</span>
        <input type="text" id="searchInput" placeholder="Buscar por código, descrição ou modelo...">
    </div>

    <div class="table-card">
        <table class="table mb-0" id="ferramentasTable">
            <thead>
            <tr>
                <th>Código</th>
                <th>Descrição</th>
                <th>Classe</th>
                <th>Modelo</th>
                <th>Qtd. Total</th>
                <th>Disponível</th>
                <th>Localização</th>
                <th>Status</th>
                <th class="text-end">Ações</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($ferramentas)): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">Nenhuma ferramenta cadastrada.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($ferramentas as $item): ?>
                    <tr data-pesquisa="<?= htmlspecialchars(strtolower($item['codigo'] . ' ' . $item['descricao'] . ' ' . $item['modelo'])); ?>">
                        <td><?= htmlspecialchars($item['codigo']); ?></td>
                        <td><?= htmlspecialchars($item['descricao']); ?></td>
                        <td><?= htmlspecialchars($item['classe']); ?></td>
                        <td><?= htmlspecialchars($item['modelo']); ?></td>
                        <td><?= (int)$item['quantidade_total']; ?></td>
                        <td><?= (int)$item['quantidade_disponivel']; ?></td>
                        <td><?= htmlspecialchars($item['localizacao'] ?? '-'); ?></td>
                        <td><?= htmlspecialchars($item['status']); ?></td>
                        <td class="text-end actions">
                            <button type="button"
                                    class="btn-action btn-edit"
                                    data-id="<?= (int)$item['id']; ?>"
                                    data-codigo="<?= htmlspecialchars($item['codigo']); ?>"
                                    data-descricao="<?= htmlspecialchars($item['descricao']); ?>"
                                    data-classe="<?= (int)$item['classe_id']; ?>"
                                    data-modelo="<?= (int)$item['modelo_id']; ?>"
                                    data-quantidade="<?= (int)$item['quantidade_total']; ?>"
                                    data-localizacao="<?= htmlspecialchars($item['localizacao'] ?? ''); ?>"
                                    title="Editar">
                                ✏️
                            </button>
                            <button type="button"
                                    class="btn-action btn-delete"
                                    data-id="<?= (int)$item['id']; ?>"
                                    data-nome="<?= htmlspecialchars($item['descricao']); ?>"
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
                <h3 id="modalTitle">Nova Ferramenta</h3>
                <span id="modalSubtitle">Cadastre uma nova ferramenta no sistema</span>
            </div>
            <button class="modal-close" id="closeModal">×</button>
        </div>
        <form class="modal-body" id="ferramentaForm" method="POST">
            <input type="hidden" name="acao" value="criar" id="ferramentaAcao">
            <input type="hidden" name="ferramenta_id" id="ferramentaId">
            <div class="form-group">
                <label>Código *</label>
                <input type="text" name="codigo" id="codigo" placeholder="Ex: TOOL-0801" required>
            </div>
            <div class="form-group">
                <label>Descrição *</label>
                <input type="text" name="descricao" id="descricao" placeholder="Descrição da ferramenta" required>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Classe *</label>
                    <select name="classe_id" id="classeId" required>
                        <option value="">Selecione</option>
                        <?php foreach ($classes as $classeOption): ?>
                            <option value="<?= (int)$classeOption['id']; ?>"><?= htmlspecialchars($classeOption['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Modelo *</label>
                    <select name="modelo_id" id="modeloId" required>
                        <option value="">Selecione</option>
                        <?php foreach ($modelos as $modeloOption): ?>
                            <option value="<?= (int)$modeloOption['id']; ?>"><?= htmlspecialchars($modeloOption['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Quantidade Total *</label>
                    <input type="number" name="quantidade_total" id="quantidade" min="1" placeholder="Ex: 10" required>
                </div>
                <div class="form-group">
                    <label>Localização</label>
                    <input type="text" name="localizacao" id="localizacao" placeholder="Ex: Prateleira A3">
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="cancelModal">Cancelar</button>
                <button type="submit" class="btn-modal-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<form method="POST" id="deleteFerramentaForm" style="display:none;">
    <input type="hidden" name="acao" value="excluir">
    <input type="hidden" name="ferramenta_id" id="deleteFerramentaId">
</form>

<style>
    .ferramentas-page { padding: 1.5rem; }
    .search-wrapper { position: relative; }
    .search-wrapper span { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
    .search-wrapper input { width: 100%; border-radius: 0.75rem; border: 1px solid #CBD5F5; padding: 0.6rem 0.6rem 0.6rem 2.3rem; }
    .table-card { border: 1px solid #E5E7EB; border-radius: 1rem; overflow-x: auto; background: #FFF; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 0.85rem 1rem; border-bottom: 1px solid #F3F4F6; font-size: 0.95rem; }
    th { text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; color: #6B7280; background: #F9FAFB; }
    .actions .btn-action { border: none; background: transparent; cursor: pointer; font-size: 1.1rem; margin-left: 0.35rem; }
    .modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.55); display: none; align-items: center; justify-content: center; padding: 1rem; z-index: 1000; }
    .modal-overlay.active { display: flex; }
    .modal-card { background: #FFF; border-radius: 1rem; width: min(640px, 100%); box-shadow: 0 40px 90px rgba(15, 23, 42, 0.3); overflow: hidden; }
    .modal-header { padding: 1.4rem 1.6rem; border-bottom: 1px solid #F0F2F5; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
    .modal-header h3 { margin-bottom: 0.2rem; font-size: 1.2rem; }
    .modal-header span { color: #6B7280; }
    .modal-close { border: none; background: transparent; font-size: 1.4rem; cursor: pointer; }
    .modal-body { padding: 1.5rem; background: #F9FAFB; display: flex; flex-direction: column; gap: 1rem; }
    .form-group { display: flex; flex-direction: column; gap: 0.35rem; }
    .form-group input,
    .form-group select { border: 1px solid #CBD5F5; border-radius: 0.85rem; padding: 0.65rem 0.8rem; font-family: inherit; font-size: 0.95rem; }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.9rem; }
    .modal-actions { padding: 1rem 1.5rem 1.5rem; background: #F9FAFB; display: flex; justify-content: flex-end; gap: 0.7rem; }
    .btn-secondary { border: 1px solid #E5E7EB; border-radius: 0.85rem; padding: 0.6rem 1.2rem; background: #FFF; cursor: pointer; }
    .btn-modal-primary { border: none; border-radius: 0.85rem; padding: 0.6rem 1.3rem; background: #1F56D8; color: #FFF; font-weight: 600; cursor: pointer; }
</style>
<script>
    const modal = document.getElementById('modal');
    const openModalBtn = document.getElementById('openModal');
    const closeModalBtn = document.getElementById('closeModal');
    const cancelModalBtn = document.getElementById('cancelModal');
    const searchInput = document.getElementById('searchInput');
    const modalTitle = document.getElementById('modalTitle');
    const modalSubtitle = document.getElementById('modalSubtitle');
    const acaoInput = document.getElementById('ferramentaAcao');
    const idInput = document.getElementById('ferramentaId');
    const codigoInput = document.getElementById('codigo');
    const descricaoInput = document.getElementById('descricao');
    const classeSelect = document.getElementById('classeId');
    const modeloSelect = document.getElementById('modeloId');
    const quantidadeInput = document.getElementById('quantidade');
    const localizacaoInput = document.getElementById('localizacao');
    const deleteForm = document.getElementById('deleteFerramentaForm');
    const deleteIdInput = document.getElementById('deleteFerramentaId');

    function toggleModal(show) {
        modal.classList.toggle('active', show);
    }

    function prepararModalCriar() {
        modalTitle.textContent = 'Nova Ferramenta';
        modalSubtitle.textContent = 'Cadastre uma nova ferramenta no sistema';
        acaoInput.value = 'criar';
        idInput.value = '';
        codigoInput.value = '';
        descricaoInput.value = '';
        classeSelect.value = '';
        modeloSelect.value = '';
        quantidadeInput.value = '';
        localizacaoInput.value = '';
        toggleModal(true);
        codigoInput.focus();
    }

    function prepararModalEditar(button) {
        modalTitle.textContent = 'Editar Ferramenta';
        modalSubtitle.textContent = 'Atualize os dados cadastrados da ferramenta';
        acaoInput.value = 'atualizar';
        idInput.value = button.dataset.id;
        codigoInput.value = button.dataset.codigo || '';
        descricaoInput.value = button.dataset.descricao || '';
        classeSelect.value = button.dataset.classe || '';
        modeloSelect.value = button.dataset.modelo || '';
        quantidadeInput.value = button.dataset.quantidade || '';
        localizacaoInput.value = button.dataset.localizacao || '';
        toggleModal(true);
        descricaoInput.focus();
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
            const nome = button.dataset.nome || 'esta ferramenta';
            if (confirm(`Deseja realmente excluir ${nome}?`)) {
                deleteIdInput.value = button.dataset.id;
                deleteForm.submit();
            }
        });
    });

    searchInput.addEventListener('input', () => {
        const term = searchInput.value.toLowerCase();
        document.querySelectorAll('#ferramentasTable tbody tr').forEach((row) => {
            row.style.display = row.dataset.pesquisa.includes(term) ? '' : 'none';
        });
    });
</script>
<?php include __DIR__ . '/inc/footer.php'; ?>
