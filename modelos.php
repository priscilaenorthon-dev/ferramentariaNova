<?php
require_once __DIR__ . '/inc/auth.php';
exigirPermissaoPagina('modelos');
require_once __DIR__ . '/inc/db.php';

$paginaTitulo = 'Modelos de Ferramentas';

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'criar';
    $modeloId = (int)($_POST['modelo_id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $requer = isset($_POST['requer_calibracao']) ? 1 : 0;
    $intervalo = $requer ? trim($_POST['intervalo_dias'] ?? '') : '';

    if ($acao === 'excluir') {
        if ($modeloId <= 0) {
            $erro = 'Modelo inválido para exclusão.';
        } else {
            try {
                $stmt = $pdo->prepare('DELETE FROM modelos WHERE id = :id');
                $stmt->execute([':id' => $modeloId]);
                $mensagem = 'Modelo excluído com sucesso.';
            } catch (PDOException $e) {
                $erro = 'Não foi possível excluir. Há ferramentas usando este modelo?';
            }
        }
    } else {
        if ($nome === '') {
            $erro = 'Informe o nome do modelo.';
        } elseif ($requer && ($intervalo === '' || !ctype_digit($intervalo) || (int)$intervalo <= 0)) {
            $erro = 'Informe o intervalo de calibração em dias.';
        } elseif ($acao === 'atualizar' && $modeloId <= 0) {
            $erro = 'Modelo inválido para edição.';
        } else {
            try {
                $dados = [
                    ':nome' => $nome,
                    ':requer' => $requer,
                    ':intervalo' => $requer ? (int)$intervalo : null,
                ];
                if ($acao === 'atualizar') {
                    $dados[':id'] = $modeloId;
                    $stmt = $pdo->prepare('UPDATE modelos SET nome = :nome, requer_calibracao = :requer, intervalo_dias = :intervalo WHERE id = :id');
                    $stmt->execute($dados);
                    $mensagem = 'Modelo atualizado com sucesso.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO modelos (nome, requer_calibracao, intervalo_dias) VALUES (:nome, :requer, :intervalo)');
                    $stmt->execute($dados);
                    $mensagem = 'Modelo cadastrado com sucesso.';
                }
            } catch (PDOException $e) {
                $erro = 'Não foi possível salvar o modelo. Verifique se o nome já existe.';
            }
        }
    }
}

$stmt = $pdo->query('SELECT id, nome, requer_calibracao, intervalo_dias FROM modelos ORDER BY nome');
$modelos = $stmt->fetchAll();

include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
?>
<div class="content-area modelos-page">
    <div class="page-header d-flex justify-content-between align-items-start">
        <div>
            <h1>Modelos de Ferramentas</h1>
            <p class="text-muted mb-0">Gerencie os modelos (Normal ou Calibração)</p>
        </div>
        <button class="btn btn-primary rounded-pill px-3" id="openModal">+ Novo Modelo</button>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-success rounded-3 py-2 px-3"><?= htmlspecialchars($mensagem); ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alert alert-danger rounded-3 py-2 px-3"><?= htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <div class="search-wrapper mb-3">
        <span>&#128269;</span>
        <input type="text" id="searchInput" placeholder="Buscar modelo...">
    </div>

    <div class="table-card">
        <table class="table mb-0" id="modelosTable">
            <thead>
            <tr>
                <th>Nome</th>
                <th>Requer Calibração</th>
                <th>Intervalo (dias)</th>
                <th class="text-end">Ações</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($modelos)): ?>
                <tr>
                    <td colspan="4" class="text-center text-muted py-4">Nenhum modelo cadastrado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($modelos as $modelo) : ?>
                    <tr data-nome="<?= htmlspecialchars(strtolower($modelo['nome'])); ?>">
                        <td><?= htmlspecialchars($modelo['nome']); ?></td>
                        <td>
                            <?php if ((int)$modelo['requer_calibracao'] === 1) : ?>
                                <span class="badge badge-sim">Sim</span>
                            <?php else : ?>
                                <span class="badge badge-nao">Não</span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$modelo['requer_calibracao'] === 1 ? htmlspecialchars($modelo['intervalo_dias']) . ' dias' : '-'; ?></td>
                        <td class="text-end actions">
                            <button type="button"
                                    class="btn-action btn-edit"
                                    data-id="<?= (int)$modelo['id']; ?>"
                                    data-nome="<?= htmlspecialchars($modelo['nome']); ?>"
                                    data-requer="<?= (int)$modelo['requer_calibracao']; ?>"
                                    data-intervalo="<?= (int)$modelo['intervalo_dias']; ?>"
                                    title="Editar">
                                ✏️
                            </button>
                            <button type="button"
                                    class="btn-action btn-delete"
                                    data-id="<?= (int)$modelo['id']; ?>"
                                    data-nome="<?= htmlspecialchars($modelo['nome']); ?>"
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
                <h3 id="modalTitle">Novo Modelo</h3>
                <span id="modalSubtitle">Cadastre um novo modelo de ferramenta</span>
            </div>
            <button class="modal-close" id="closeModal">×</button>
        </div>
        <form class="modal-body" id="modeloForm" method="POST">
            <input type="hidden" name="acao" value="criar" id="modeloAcao">
            <input type="hidden" name="modelo_id" value="" id="modeloId">
            <div class="form-group">
                <label>Nome *</label>
                <input type="text" name="nome" id="nomeModelo" placeholder="Ex: Normal ou Calibração" required>
            </div>
            <div class="form-group toggle-group">
                <label>Requer calibração?</label>
                <label class="switch">
                    <input type="checkbox" name="requer_calibracao" id="requerCalibracao">
                    <span class="slider"></span>
                </label>
            </div>
            <div class="form-group intervalo-group" id="intervaloGroup">
                <label>Intervalo de Calibração (dias) *</label>
                <input type="number" name="intervalo_dias" id="intervaloDias" placeholder="Ex: 100, 300">
                <small class="text-muted">Número de dias entre cada calibração</small>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="cancelModal">Cancelar</button>
                <button type="submit" class="btn-modal-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<form method="POST" id="deleteModeloForm" style="display:none;">
    <input type="hidden" name="acao" value="excluir">
    <input type="hidden" name="modelo_id" id="deleteModeloId">
</form>

<style>
    .modelos-page { padding: 1.5rem; }
    .search-wrapper { position: relative; }
    .search-wrapper span { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
    .search-wrapper input { width: 100%; border-radius: 0.75rem; border: 1px solid #CBD5F5; padding: 0.6rem 0.6rem 0.6rem 2.3rem; background: #FFF; }
    .table-card { background: #FFF; border-radius: 1rem; border: 1px solid #E5E7EB; overflow-x: auto; }
    .table-card table { width: 100%; border-collapse: collapse; }
    .table-card th, .table-card td { padding: 0.85rem 1rem; border-bottom: 1px solid #F3F4F6; font-size: 0.95rem; }
    .table-card th { text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; color: #6B7280; background: #F9FAFB; }
    .actions .btn-action { border: none; background: transparent; cursor: pointer; font-size: 1.05rem; margin-left: 0.35rem; }
    .badge { border-radius: 999px; padding: 0.2rem 0.75rem; font-weight: 600; font-size: 0.82rem; }
    .badge-sim { background: #1F56D8; color: #FFF; }
    .badge-nao { background: #ECEFF3; color: #1F2937; }
    .modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.55); display: none; align-items: center; justify-content: center; padding: 1rem; z-index: 1000; }
    .modal-overlay.active { display: flex; }
    .modal-card { background: #FFF; border-radius: 1rem; width: min(520px, 100%); box-shadow: 0 40px 90px rgba(15, 23, 42, 0.3); overflow: hidden; }
    .modal-header { padding: 1.4rem 1.6rem; border-bottom: 1px solid #F0F2F5; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
    .modal-header h3 { margin-bottom: 0.2rem; font-size: 1.2rem; }
    .modal-header span { color: #6B7280; }
    .modal-close { border: none; background: transparent; font-size: 1.4rem; cursor: pointer; }
    .modal-body { padding: 1.5rem; background: #F9FAFB; display: flex; flex-direction: column; gap: 1rem; }
    .modal-body .form-group { display: flex; flex-direction: column; gap: 0.35rem; }
    .modal-body input, .modal-body textarea { border: 1px solid #CBD5F5; border-radius: 0.85rem; padding: 0.65rem 0.8rem; background: #FFF; font-family: inherit; font-size: 0.95rem; }
    .intervalo-group { display: none; }
    .intervalo-group.show { display: flex; flex-direction: column; gap: 0.35rem; }
    .toggle-group { display: flex; justify-content: space-between; align-items: center; }
    .switch { position: relative; display: inline-block; width: 46px; height: 24px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; inset: 0; background: #D1D5DB; border-radius: 24px; transition: .3s; }
    .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 2px; bottom: 2px; background-color: white; transition: .3s; border-radius: 50%; }
    .switch input:checked + .slider { background: #1F56D8; }
    .switch input:checked + .slider:before { transform: translateX(22px); }
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
    const requerCalibracao = document.getElementById('requerCalibracao');
    const intervaloDias = document.getElementById('intervaloDias');
    const intervaloGroup = document.getElementById('intervaloGroup');
    const modalTitle = document.getElementById('modalTitle');
    const modalSubtitle = document.getElementById('modalSubtitle');
    const acaoInput = document.getElementById('modeloAcao');
    const modeloIdInput = document.getElementById('modeloId');
    const nomeModeloInput = document.getElementById('nomeModelo');
    const deleteModeloForm = document.getElementById('deleteModeloForm');
    const deleteModeloId = document.getElementById('deleteModeloId');

    function toggleModal(show) {
        modal.classList.toggle('active', show);
    }

    function atualizarIntervalo() {
        if (requerCalibracao.checked) {
            intervaloGroup.classList.add('show');
            intervaloDias.disabled = false;
        } else {
            intervaloGroup.classList.remove('show');
            intervaloDias.disabled = true;
            intervaloDias.value = '';
        }
    }

    function prepararModalCriar() {
        modalTitle.textContent = 'Novo Modelo';
        modalSubtitle.textContent = 'Cadastre um novo modelo de ferramenta';
        acaoInput.value = 'criar';
        modeloIdInput.value = '';
        nomeModeloInput.value = '';
        requerCalibracao.checked = false;
        intervaloDias.value = '';
        atualizarIntervalo();
        toggleModal(true);
        nomeModeloInput.focus();
    }

    function prepararModalEditar(button) {
        modalTitle.textContent = 'Editar Modelo';
        modalSubtitle.textContent = 'Atualize os dados do modelo selecionado';
        acaoInput.value = 'atualizar';
        modeloIdInput.value = button.dataset.id;
        nomeModeloInput.value = button.dataset.nome || '';
        requerCalibracao.checked = button.dataset.requer === '1';
        atualizarIntervalo();
        if (requerCalibracao.checked) {
            intervaloDias.value = button.dataset.intervalo || '';
        }
        toggleModal(true);
        nomeModeloInput.focus();
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
            const nome = button.dataset.nome || 'este modelo';
            if (confirm(`Deseja realmente excluir ${nome}?`)) {
                deleteModeloId.value = button.dataset.id;
                deleteModeloForm.submit();
            }
        });
    });

    searchInput.addEventListener('input', () => {
        const term = searchInput.value.toLowerCase();
        document.querySelectorAll('#modelosTable tbody tr').forEach((row) => {
            row.style.display = row.dataset.nome.includes(term) ? '' : 'none';
        });
    });

    requerCalibracao.addEventListener('change', atualizarIntervalo);
    atualizarIntervalo();
</script>
<?php include __DIR__ . '/inc/footer.php'; ?>
