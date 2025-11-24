<?php
require_once __DIR__ . '/inc/auth.php';
exigirPermissaoPagina('devolucoes');
require_once __DIR__ . '/inc/db.php';

$paginaTitulo = 'Devoluções';
$mensagem = '';
$erro = '';

if (!function_exists('formatarDataSimples')) {
    function formatarDataSimples(?string $data, string $formato = 'd/m/Y H:i') : string
    {
        if (empty($data)) {
            return '-';
        }
        return date($formato, strtotime($data));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emprestimoId = (int)($_POST['emprestimo_id'] ?? 0);
    $condicao = trim($_POST['condicao'] ?? '');

    if ($emprestimoId <= 0) {
        $erro = 'Selecione um empréstimo válido.';
    } else {
        try {
            $pdo->beginTransaction();

            $select = $pdo->prepare('SELECT ferramenta_id, status FROM emprestimos WHERE id = :id FOR UPDATE');
            $select->execute([':id' => $emprestimoId]);
            $emprestimo = $select->fetch();

            if (!$emprestimo) {
                throw new RuntimeException('Empréstimo não encontrado.');
            }
            if ($emprestimo['status'] === 'Devolvido') {
                throw new RuntimeException('Este empréstimo já está marcado como devolvido.');
            }

            $updateEmp = $pdo->prepare('UPDATE emprestimos SET status = "Devolvido", data_retorno = NOW() WHERE id = :id');
            $updateEmp->execute([':id' => $emprestimoId]);

            $updateFerr = $pdo->prepare('UPDATE ferramentas SET quantidade_disponivel = quantidade_disponivel + 1 WHERE id = :id');
            $updateFerr->execute([':id' => $emprestimo['ferramenta_id']]);

            $hist = $pdo->prepare('INSERT INTO historico (emprestimo_id, acao, descricao) VALUES (:id, :acao, :descricao)');
            $hist->execute([
                ':id' => $emprestimoId,
                ':acao' => 'Devolução',
                ':descricao' => $condicao !== '' ? $condicao : 'Ferramenta devolvida.'
            ]);

            $pdo->commit();
            $mensagem = 'Devolução registrada com sucesso.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = $e instanceof RuntimeException ? $e->getMessage() : 'Erro ao registrar devolução.';
        }
    }
}

$sqlDevolucoes = "SELECT e.id,
                         f.descricao AS ferramenta,
                         f.codigo,
                         u.nome AS usuario,
                         u.sobrenome,
                         e.quantidade,
                         e.data_saida,
                         e.data_prevista,
                         e.data_retorno,
                         CONCAT(op.nome, ' ', op.sobrenome) AS operador,
                         (SELECT h.descricao
                            FROM historico h
                           WHERE h.emprestimo_id = e.id
                             AND h.acao = 'Devolução'
                           ORDER BY h.data_registro DESC
                           LIMIT 1) AS condicao
                  FROM emprestimos e
                  INNER JOIN ferramentas f ON f.id = e.ferramenta_id
                  INNER JOIN usuarios u ON u.id = e.usuario_id
                  LEFT JOIN usuarios op ON op.id = e.operador_id
                  WHERE e.status = 'Devolvido'
                  ORDER BY e.data_retorno DESC
                  LIMIT 20";
$devolucoes = $pdo->query($sqlDevolucoes)->fetchAll();

$sqlAbertos = "SELECT e.id, f.descricao, f.codigo, u.nome, u.sobrenome
               FROM emprestimos e
               INNER JOIN ferramentas f ON f.id = e.ferramenta_id
               INNER JOIN usuarios u ON u.id = e.usuario_id
               WHERE e.status <> 'Devolvido'
               ORDER BY e.data_saida DESC";
$emprestimosAbertos = $pdo->query($sqlAbertos)->fetchAll();
$podeRegistrar = !empty($emprestimosAbertos);

include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
?>
<div class="content-area devolucoes-page">
    <div class="page-header d-flex justify-content-between align-items-start">
        <div>
            <h1>Devoluções</h1>
            <p class="text-muted mb-0">Registre e acompanhe as devoluções de ferramentas</p>
        </div>
        <button class="btn btn-primary rounded-pill px-3" id="openModal" <?= $podeRegistrar ? '' : 'disabled'; ?>>+ Registrar Devolução</button>
    </div>

    <?php if (!$podeRegistrar): ?>
        <div class="alert alert-warning rounded-3 py-2 px-3">Não há empréstimos pendentes de devolução.</div>
    <?php endif; ?>
    <?php if ($mensagem): ?>
        <div class="alert alert-success rounded-3 py-2 px-3"><?= htmlspecialchars($mensagem); ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alert alert-danger rounded-3 py-2 px-3"><?= htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <div class="search-wrapper mb-3">
        <span>🔍</span>
        <input type="text" id="searchInput" placeholder="Buscar devolução...">
    </div>

    <div class="table-card">
        <table class="table mb-0" id="devolucoesTable">
            <thead>
            <tr>
                <th>Ferramenta</th>
                <th>Usuário</th>
                <th>Data</th>
                <th>Condição</th>
                <th class="text-end">Ações</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($devolucoes)): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">Nenhuma devolução registrada.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($devolucoes as $dev):
                    $usuarioCompleto = trim(($dev['usuario'] ?? '') . ' ' . ($dev['sobrenome'] ?? ''));
                    ?>
                    <tr
                        data-pesquisa="<?= htmlspecialchars(strtolower($dev['ferramenta'] . ' ' . $usuarioCompleto . ' ' . formatarDataSimples($dev['data_retorno'], 'd/m/Y'))); ?>"
                        data-ferramenta="<?= htmlspecialchars($dev['ferramenta']); ?>"
                        data-codigo="<?= htmlspecialchars($dev['codigo']); ?>"
                        data-usuario="<?= htmlspecialchars($usuarioCompleto); ?>"
                        data-quantidade="<?= (int)($dev['quantidade'] ?? 1); ?>"
                        data-data-saida="<?= htmlspecialchars($dev['data_saida'] ?? ''); ?>"
                        data-data-prevista="<?= htmlspecialchars($dev['data_prevista'] ?? ''); ?>"
                        data-data-retorno="<?= htmlspecialchars($dev['data_retorno'] ?? ''); ?>"
                        data-operador="<?= htmlspecialchars($dev['operador'] ?? '-'); ?>"
                        data-condicao="<?= htmlspecialchars($dev['condicao'] ?? '-'); ?>"
                    >
                        <td>
                            <div class="d-flex flex-column">
                                <strong><?= htmlspecialchars($dev['ferramenta']); ?></strong>
                                <small class="text-muted"><?= htmlspecialchars($dev['codigo']); ?></small>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($usuarioCompleto); ?></td>
                        <td><?= formatarDataSimples($dev['data_retorno'], 'd/m/Y H:i'); ?></td>
                        <td><?= htmlspecialchars($dev['condicao'] ?? '-'); ?></td>
                        <td class="text-end actions">
                            <button type="button" class="btn-details" title="Ver detalhes">🔍</button>
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
                <h3>Registrar Devolução</h3>
                <span>Selecione o empréstimo que está sendo devolvido</span>
            </div>
            <button class="modal-close" id="closeModal">×</button>
        </div>
        <form class="modal-body" method="POST">
            <div class="form-group">
                <label>Empréstimo *</label>
                <select name="emprestimo_id" required <?= $podeRegistrar ? '' : 'disabled'; ?>>
                    <option value="">Selecione</option>
                    <?php foreach ($emprestimosAbertos as $aberto): ?>
                        <option value="<?= $aberto['id']; ?>">
                            <?= htmlspecialchars($aberto['descricao']); ?> · <?= htmlspecialchars($aberto['codigo']); ?> (<?= htmlspecialchars(trim($aberto['nome'] . ' ' . $aberto['sobrenome'])); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Condição / Observações</label>
                <textarea name="condicao" rows="3" placeholder="Descreva o estado da ferramenta"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="cancelModal">Cancelar</button>
                <button type="submit" class="btn-modal-primary" <?= $podeRegistrar ? '' : 'disabled'; ?>>Salvar</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="detailsModal">
    <div class="modal-card details-card">
        <div class="modal-header">
            <div>
                <h3>Detalhes da Devolução</h3>
                <span>Veja as informações completas deste empréstimo</span>
            </div>
            <button class="modal-close" id="closeDetails">×</button>
        </div>
        <div class="modal-body details-body">
            <div class="detail-item">
                <span>Ferramenta</span>
                <strong id="detFerramenta">-</strong>
                <small id="detCodigo">-</small>
            </div>
            <div class="detail-grid">
                <div>
                    <span>Usuário</span>
                    <strong id="detUsuario">-</strong>
                </div>
                <div>
                    <span>Operador</span>
                    <strong id="detOperador">-</strong>
                </div>
            </div>
            <div class="detail-grid">
                <div>
                    <span>Quantidade</span>
                    <strong id="detQuantidade">-</strong>
                </div>
                <div>
                    <span>Condição</span>
                    <strong id="detCondicao">-</strong>
                </div>
            </div>
            <div class="detail-grid">
                <div>
                    <span>Data de Saída</span>
                    <strong id="detDataSaida">-</strong>
                </div>
                <div>
                    <span>Previsão de Devolução</span>
                    <strong id="detDataPrevista">-</strong>
                </div>
            </div>
            <div class="detail-grid">
                <div>
                    <span>Data de Devolução</span>
                    <strong id="detDataRetorno">-</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .devolucoes-page { padding: 1.5rem; }
    .search-wrapper { position: relative; }
    .search-wrapper span { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
    .search-wrapper input { width: 100%; border-radius: 0.75rem; border: 1px solid #CBD5F5; padding: 0.6rem 0.6rem 0.6rem 2.3rem; background: #FFF; }
    .table-card { background: #FFF; border-radius: 1rem; border: 1px solid #E5E7EB; overflow-x: auto; }
    .table-card th, .table-card td { padding: 0.85rem 1rem; border-bottom: 1px solid #F3F4F6; font-size: 0.95rem; }
    .table-card th { text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; color: #6B7280; background: #F9FAFB; }
    .actions button { border: none; background: transparent; cursor: pointer; font-size: 1.1rem; }
    .modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.55); display: none; align-items: center; justify-content: center; padding: 1rem; z-index: 1000; }
    .modal-overlay.active { display: flex; }
    .modal-card { background: #FFF; border-radius: 1rem; width: min(520px, 100%); box-shadow: 0 40px 90px rgba(15, 23, 42, 0.3); overflow: hidden; }
    .modal-header { padding: 1.4rem 1.6rem; border-bottom: 1px solid #F0F2F5; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
    .modal-header span { color: #6B7280; }
    .modal-close { border: none; background: transparent; font-size: 1.2rem; cursor: pointer; }
    .modal-body { padding: 1.5rem; background: #F9FAFB; display: flex; flex-direction: column; gap: 1rem; }
    .modal-body .form-group { display: flex; flex-direction: column; gap: 0.35rem; }
    .modal-body input, .modal-body select, .modal-body textarea { border: 1px solid #CBD5F5; border-radius: 0.85rem; padding: 0.65rem 0.85rem; background: #FFF; font-size: 0.95rem; font-family: inherit; }
    .modal-actions { padding: 1rem 1.5rem 1.5rem; background: #F9FAFB; display: flex; justify-content: flex-end; gap: 0.7rem; }
    .btn-secondary { border: 1px solid #E5E7EB; border-radius: 0.85rem; padding: 0.65rem 1.2rem; background: #FFF; cursor: pointer; }
    .btn-modal-primary { border: none; border-radius: 0.85rem; padding: 0.65rem 1.3rem; background: #1F56D8; color: #FFF; font-weight: 600; cursor: pointer; }
    .details-card { width: min(640px, 100%); }
    .details-body { gap: 1rem; }
    .detail-item span { display: block; color: #6B7280; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; }
    .detail-item strong { font-size: 1.2rem; display: block; }
    .detail-item small { color: #9CA3AF; }
    .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.8rem; }
    .detail-grid span { display: block; color: #6B7280; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.03em; }
    .detail-grid strong { font-size: 1.05rem; }
</style>
<script>
    const modal = document.getElementById('modal');
    const openModalBtn = document.getElementById('openModal');
    const closeModalBtn = document.getElementById('closeModal');
    const cancelModalBtn = document.getElementById('cancelModal');
    const searchInput = document.getElementById('searchInput');

    function toggleModal(show) {
        if (!openModalBtn || openModalBtn.disabled) {
            return;
        }
        modal.classList.toggle('active', show);
    }

    if (openModalBtn && !openModalBtn.disabled) {
        openModalBtn.addEventListener('click', () => toggleModal(true));
    }
    closeModalBtn.addEventListener('click', () => toggleModal(false));
    cancelModalBtn.addEventListener('click', () => toggleModal(false));
    modal.addEventListener('click', (event) => { if (event.target === modal) toggleModal(false); });

    searchInput.addEventListener('input', () => {
        const term = searchInput.value.toLowerCase();
        document.querySelectorAll('#devolucoesTable tbody tr').forEach((row) => {
            const pesquisa = row.dataset.pesquisa || '';
            row.style.display = pesquisa.includes(term) ? '' : 'none';
        });
    });

    const detailsModal = document.getElementById('detailsModal');
    const closeDetails = document.getElementById('closeDetails');
    const detailMap = {
        ferramenta: document.getElementById('detFerramenta'),
        codigo: document.getElementById('detCodigo'),
        usuario: document.getElementById('detUsuario'),
        operador: document.getElementById('detOperador'),
        quantidade: document.getElementById('detQuantidade'),
        condicao: document.getElementById('detCondicao'),
        dataSaida: document.getElementById('detDataSaida'),
        dataPrevista: document.getElementById('detDataPrevista'),
        dataRetorno: document.getElementById('detDataRetorno'),
    };

    function formatarTextoData(valor) {
        if (!valor) return '-';
        const data = new Date(valor);
        if (Number.isNaN(data.getTime())) {
            return valor;
        }
        return data.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
    }

    function abrirDetalhes(row) {
        detailMap.ferramenta.textContent = row.dataset.ferramenta || '-';
        detailMap.codigo.textContent = row.dataset.codigo || '-';
        detailMap.usuario.textContent = row.dataset.usuario || '-';
        detailMap.operador.textContent = row.dataset.operador || '-';
        detailMap.quantidade.textContent = row.dataset.quantidade || '-';
        detailMap.condicao.textContent = row.dataset.condicao || '-';
        detailMap.dataSaida.textContent = formatarTextoData(row.dataset.dataSaida);
        detailMap.dataPrevista.textContent = formatarTextoData(row.dataset.dataPrevista);
        detailMap.dataRetorno.textContent = formatarTextoData(row.dataset.dataRetorno);
        detailsModal.classList.add('active');
    }

    document.querySelectorAll('.btn-details').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('tr');
            if (row) {
                abrirDetalhes(row);
            }
        });
    });

    closeDetails.addEventListener('click', () => detailsModal.classList.remove('active'));
    detailsModal.addEventListener('click', (event) => {
        if (event.target === detailsModal) {
            detailsModal.classList.remove('active');
        }
    });
</script>
<?php include __DIR__ . '/inc/footer.php'; ?>
