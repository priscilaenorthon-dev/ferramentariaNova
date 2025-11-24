<?php
require_once __DIR__ . '/inc/auth.php';
exigirPermissaoPagina('emprestimos');
require_once __DIR__ . '/inc/db.php';

$paginaTitulo = 'Empréstimos';
$mensagem = '';
$erro = '';

function formatarData(?string $data, string $formato = 'd/m/Y H:i') : string
{
    if (empty($data)) {
        return '-';
    }
    return date($formato, strtotime($data));
}

function slugStatus(string $status): string
{
    return match ($status) {
        'Atrasado' => 'atraso',
        'Devolvido' => 'devolvido',
        default => 'andamento',
    };
}

$loggedId = $_SESSION['usuario_id'] ?? null;

$acao = $_POST['acao'] ?? 'novo';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($acao === 'devolver') {
        $emprestimoId = (int)($_POST['emprestimo_id'] ?? 0);
        $condicao = trim($_POST['condicao'] ?? '');

        if ($emprestimoId <= 0) {
            $erro = 'Selecione um empréstimo válido para devolução.';
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
                    throw new RuntimeException('Este empréstimo já está devolvido.');
                }

                $updateEmp = $pdo->prepare('UPDATE emprestimos SET status = "Devolvido", data_retorno = NOW() WHERE id = :id');
                $updateEmp->execute([':id' => $emprestimoId]);

                $updateFerr = $pdo->prepare('UPDATE ferramentas SET quantidade_disponivel = quantidade_disponivel + 1 WHERE id = :id');
                $updateFerr->execute([':id' => $emprestimo['ferramenta_id']]);

                $hist = $pdo->prepare('INSERT INTO historico (emprestimo_id, acao, descricao) VALUES (:id, :acao, :descricao)');
                $hist->execute([
                    ':id' => $emprestimoId,
                    ':acao' => 'Devolução',
                    ':descricao' => $condicao !== '' ? $condicao : 'Devolução registrada via módulo de empréstimos.'
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
    } else {
        $ferramenta = (int)($_POST['ferramenta_id'] ?? 0);
        $usuarioDestino = (int)($_POST['usuario_id'] ?? 0);
        $operador = (int)($_POST['operador_id'] ?? 0);
        $dataPrevista = trim($_POST['data_prevista'] ?? '');

        if ($ferramenta <= 0 || $usuarioDestino <= 0 || $operador <= 0) {
            $erro = 'Selecione a ferramenta, o usuário e o operador.';
        } else {
            try {
                $pdo->beginTransaction();

                $update = $pdo->prepare('UPDATE ferramentas SET quantidade_disponivel = quantidade_disponivel - 1 WHERE id = :id AND quantidade_disponivel > 0');
                $update->execute([':id' => $ferramenta]);

                if ($update->rowCount() === 0) {
                    throw new RuntimeException('A ferramenta está sem unidades disponíveis.');
                }

                $insert = $pdo->prepare('INSERT INTO emprestimos (ferramenta_id, usuario_id, operador_id, data_prevista) VALUES (:ferramenta, :usuario, :operador, :prevista)');
                $insert->execute([
                    ':ferramenta' => $ferramenta,
                    ':usuario' => $usuarioDestino,
                    ':operador' => $operador,
                    ':prevista' => $dataPrevista !== '' ? $dataPrevista : null,
                ]);

                $emprestimoId = (int)$pdo->lastInsertId();

                $hist = $pdo->prepare('INSERT INTO historico (emprestimo_id, acao, descricao) VALUES (:id, :acao, :desc)');
                $hist->execute([
                    ':id' => $emprestimoId,
                    ':acao' => 'Empréstimo',
                    ':desc' => 'Registro de empréstimo gerado pelo sistema.'
                ]);

                $pdo->commit();
                $mensagem = 'Empréstimo registrado com sucesso.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $erro = $e instanceof RuntimeException ? $e->getMessage() : 'Erro ao registrar empréstimo.';
            }
        }
    }
}

$statusResumo = [
    'Emprestado' => 0,
    'Atrasado' => 0,
    'Devolvido' => 0,
];
$contagens = $pdo->query('SELECT status, COUNT(*) AS total FROM emprestimos GROUP BY status')->fetchAll();
foreach ($contagens as $item) {
    if (isset($statusResumo[$item['status']])) {
        $statusResumo[$item['status']] = (int)$item['total'];
    }
}

$sqlRecentes = 'SELECT e.id, e.status, e.data_saida, e.data_prevista, e.data_retorno,
                      f.descricao AS ferramenta, f.codigo,
                      u.nome AS usuario
               FROM emprestimos e
               INNER JOIN ferramentas f ON f.id = e.ferramenta_id
               INNER JOIN usuarios u ON u.id = e.usuario_id
               ORDER BY e.data_saida DESC
               LIMIT 6';
$emprestimosRecentes = $pdo->query($sqlRecentes)->fetchAll();

$sqlTabela = 'SELECT e.id, e.quantidade, e.status, e.data_saida, e.data_prevista, e.data_retorno,
                     f.codigo, f.descricao AS ferramenta, c.nome AS classe, m.nome AS modelo,
                     u.nome AS usuario, u.sobrenome,
                     CONCAT(op.nome, " ", op.sobrenome) AS operador
              FROM emprestimos e
              INNER JOIN ferramentas f ON f.id = e.ferramenta_id
              INNER JOIN classes c ON c.id = f.classe_id
              INNER JOIN modelos m ON m.id = f.modelo_id
              INNER JOIN usuarios u ON u.id = e.usuario_id
              LEFT JOIN usuarios op ON op.id = e.operador_id
              ORDER BY e.data_saida DESC
              LIMIT 10';
$tabelaEmprestimos = $pdo->query($sqlTabela)->fetchAll();

$ferramentasDisponiveis = $pdo->query('SELECT id, descricao, codigo, quantidade_disponivel FROM ferramentas WHERE quantidade_disponivel > 0 ORDER BY descricao')->fetchAll();
$usuariosDestino = $pdo->query('SELECT id, nome, sobrenome FROM usuarios ORDER BY nome')->fetchAll();
$operadores = $pdo->query("SELECT id, nome, sobrenome FROM usuarios WHERE perfil IN ('Administrador','Operador') ORDER BY nome")->fetchAll();
$podeEmprestar = !empty($ferramentasDisponiveis);

include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
?>
<div class="content-area emprestimos-page">
    <div class="page-header with-actions">
        <div>
            <h1>Empréstimos</h1>
            <span>Registre empréstimos de ferramentas</span>
        </div>
        <button class="btn-primary-modern" id="openModal" <?= $podeEmprestar ? '' : 'disabled'; ?>>+ Novo Empréstimo</button>
    </div>

    <?php if (!$podeEmprestar): ?>
        <div class="alert alert-warning rounded-3 py-2 px-3">Não há ferramentas disponíveis para empréstimo no momento.</div>
    <?php endif; ?>
    <?php if ($mensagem): ?>
        <div class="alert alert-success rounded-3 py-2 px-3"><?= htmlspecialchars($mensagem); ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alert alert-danger rounded-3 py-2 px-3"><?= htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <div class="cards-grid">
        <section class="card-modern status-block">
            <header>
                <h2>Status dos empréstimos</h2>
                <p>Visão rápida das entregas e pendências com feedback visual imediato.</p>
            </header>
            <div class="status-list">
                <?php if (empty($emprestimosRecentes)): ?>
                    <p class="text-muted">Nenhum registro encontrado.</p>
                <?php else: ?>
                    <?php foreach ($emprestimosRecentes as $item):
                        $slug = slugStatus($item['status']);
                        $textoDevolucao = '-';
                        if ($item['status'] === 'Devolvido') {
                            $textoDevolucao = 'Devolvido em ' . formatarData($item['data_retorno'], 'd/m/Y');
                        } elseif (!empty($item['data_prevista'])) {
                            $textoDevolucao = 'Devolução prevista para ' . formatarData($item['data_prevista'], 'd/m/Y');
                        } else {
                            $textoDevolucao = 'Devolução sem previsão';
                        }
                        ?>
                        <article class="status-item">
                            <div>
                                <div class="status-head">
                                    <strong><?= htmlspecialchars($item['ferramenta']); ?></strong>
                                    <span class="badge badge-<?= $slug; ?>"><?= htmlspecialchars($item['status']); ?></span>
                                </div>
                                <small class="status-user">👤 <?= htmlspecialchars($item['usuario']); ?></small>
                                <small class="status-muted"><?= htmlspecialchars($textoDevolucao); ?></small>
                            </div>
                            <div class="status-meta">
                                <span class="status-muted"><?= htmlspecialchars($item['codigo']); ?></span>
                                <span class="status-muted">Emprestado em <?= formatarData($item['data_saida']); ?></span>
                                <?php if ($item['status'] === 'Devolvido'): ?>
                                    <span class="badge badge-confirmado">Usuário confirmou</span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="card-modern panorama-block">
            <header>
                <h2>Panorama de status</h2>
                <p>Acompanhe quantos empréstimos estão em cada situação.</p>
            </header>
            <div class="panorama-list">
                <div class="panorama-item panorama-azul">
                    <div>
                        <strong>Em andamento</strong>
                        <p>Ferramenta em uso pelo colaborador.</p>
                    </div>
                    <span class="panorama-number"><?= $statusResumo['Emprestado']; ?></span>
                </div>
                <div class="panorama-item panorama-vermelho">
                    <div>
                        <strong>Em atraso</strong>
                        <p>Prazo vencido, priorizar contato com o usuário.</p>
                    </div>
                    <span class="panorama-number"><?= $statusResumo['Atrasado']; ?></span>
                </div>
                <div class="panorama-item panorama-verde">
                    <div>
                        <strong>Devolvido</strong>
                        <p>Ferramenta já foi devolvida.</p>
                    </div>
                    <span class="panorama-number"><?= $statusResumo['Devolvido']; ?></span>
                </div>
            </div>
        </section>
    </div>

    <div class="table-card mt-4">
        <table class="table mb-0">
            <thead>
            <tr>
                <th>Ferramenta</th>
                <th>Usuário</th>
                <th>Status</th>
                <th>Saída</th>
                <th>Prevista</th>
                <th>Retorno</th>
                <th class="text-end">Ações</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($tabelaEmprestimos)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">Nenhum empréstimo registrado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($tabelaEmprestimos as $linha): ?>
                    <tr>
                        <td>
                            <div class="d-flex flex-column">
                                <strong><?= htmlspecialchars($linha['ferramenta']); ?></strong>
                                <small class="text-muted"><?= htmlspecialchars($linha['codigo']); ?> · <?= htmlspecialchars($linha['classe']); ?> / <?= htmlspecialchars($linha['modelo']); ?></small>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($linha['usuario']); ?></td>
                        <td><?= htmlspecialchars($linha['status']); ?></td>
                        <td><?= formatarData($linha['data_saida']); ?></td>
                        <td><?= $linha['status'] === 'Devolvido' ? '-' : formatarData($linha['data_prevista'], 'd/m/Y'); ?></td>
                        <td><?= formatarData($linha['data_retorno'], 'd/m/Y'); ?></td>
                        <td class="text-end actions">
                            <button type="button" onclick="alert('Registrar devolução ainda não implementado')">↩</button>
                            <button type="button" onclick="alert('Detalhes ainda não implementados')">ℹ️</button>
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
                <h3>Novo Empréstimo</h3>
                <span>Selecione a ferramenta e o usuário que irá pegá-la</span>
            </div>
            <button class="modal-close" id="closeModal">×</button>
        </div>
        <form class="modal-body" method="POST">
            <input type="hidden" name="acao" value="novo">
            <div class="form-row">
                <div class="form-group">
                    <label>Ferramenta *</label>
                    <select name="ferramenta_id" required <?= $podeEmprestar ? '' : 'disabled'; ?>>
                        <option value="">Selecione</option>
                        <?php foreach ($ferramentasDisponiveis as $f): ?>
                            <option value="<?= $f['id']; ?>"><?= htmlspecialchars($f['descricao']); ?> (<?= htmlspecialchars($f['codigo']); ?> · disp: <?= (int)$f['quantidade_disponivel']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Usuário *</label>
                    <select name="usuario_id" required>
                        <option value="">Selecione</option>
                        <?php foreach ($usuariosDestino as $u): ?>
                            <option value="<?= $u['id']; ?>"><?= htmlspecialchars(trim($u['nome'] . ' ' . $u['sobrenome'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Operador *</label>
                    <select name="operador_id" required>
                        <?php foreach ($operadores as $op): ?>
                            <option value="<?= $op['id']; ?>" <?= $op['id'] == $loggedId ? 'selected' : ''; ?>><?= htmlspecialchars(trim($op['nome'] . ' ' . $op['sobrenome'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Data prevista de devolução</label>
                    <input type="date" name="data_prevista">
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="cancelModal">Cancelar</button>
                <button type="submit" class="btn-modal-primary" <?= $podeEmprestar ? '' : 'disabled'; ?>>Salvar</button>
            </div>
        </form>
    </div>
</div>

<style>
    .emprestimos-page { padding: 1.5rem; }
    .cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; }
    .card-modern { background: #FFF; border-radius: 1rem; border: 1px solid #E5E7EB; padding: 1.5rem; box-shadow: 0 10px 25px rgba(15,23,42,0.05); }
    .status-list { display: flex; flex-direction: column; gap: 1rem; }
    .status-item { display: flex; justify-content: space-between; gap: 1rem; border: 1px solid #F2F4F7; border-radius: 0.9rem; padding: 1rem; }
    .status-head { display: flex; align-items: center; gap: 0.5rem; }
    .status-user { display: block; color: #4B5563; }
    .status-muted { color: #9CA3AF; font-size: 0.85rem; }
    .badge { border-radius: 999px; padding: 0.25rem 0.75rem; font-weight: 600; font-size: 0.8rem; }
    .badge-andamento { background: #DBEAFE; color: #1D4ED8; }
    .badge-atraso { background: #FEE2E2; color: #B91C1C; }
    .badge-devolvido { background: #DCFCE7; color: #047857; }
    .badge-confirmado { background: #DCFCE7; color: #047857; }
    .panorama-list { display: flex; flex-direction: column; gap: 0.8rem; }
    .panorama-item { border-radius: 0.9rem; padding: 1rem; color: #FFF; display: flex; justify-content: space-between; align-items: center; }
    .panorama-azul { background: #1D4ED8; }
    .panorama-vermelho { background: #B91C1C; }
    .panorama-verde { background: #059669; }
    .panorama-number { font-size: 2rem; font-weight: 700; }
    .table-card { margin-top: 1.5rem; background: #FFF; border-radius: 1rem; border: 1px solid #E5E7EB; overflow-x: auto; }
    .table-card table { width: 100%; border-collapse: collapse; }
    .table-card th, .table-card td { padding: 0.85rem 1rem; border-bottom: 1px solid #F3F4F6; font-size: 0.95rem; }
    .table-card th { text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; color: #6B7280; background: #F9FAFB; }
    .actions button { border: none; background: transparent; cursor: pointer; font-size: 1rem; }
    .modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.55); display: none; align-items: center; justify-content: center; padding: 1rem; z-index: 1000; }
    .modal-overlay.active { display: flex; }
    .modal-card { background: #FFF; border-radius: 1rem; width: min(700px, 100%); box-shadow: 0 40px 90px rgba(15, 23, 42, 0.3); overflow: hidden; }
    .modal-header { padding: 1.4rem 1.6rem; border-bottom: 1px solid #F0F2F5; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
    .modal-close { border: none; background: transparent; font-size: 1.2rem; cursor: pointer; }
    .modal-body { padding: 1.5rem; background: #F9FAFB; display: flex; flex-direction: column; gap: 1rem; }
    .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1rem; }
    .form-group { display: flex; flex-direction: column; gap: 0.35rem; }
    .form-group input, .form-group select { border: 1px solid #CBD5F5; border-radius: 0.85rem; padding: 0.65rem 0.85rem; background: #FFF; font-size: 0.95rem; }
    .modal-actions { padding: 1rem 1.5rem 1.5rem; background: #F9FAFB; display: flex; justify-content: flex-end; gap: 0.7rem; }
    .btn-secondary { border: 1px solid #E5E7EB; border-radius: 0.85rem; padding: 0.65rem 1.2rem; background: #FFF; cursor: pointer; }
    .btn-modal-primary { border: none; border-radius: 0.85rem; padding: 0.65rem 1.3rem; background: #1F56D8; color: #FFF; font-weight: 600; cursor: pointer; }
</style>
<script>
    const modal = document.getElementById('modal');
    const openModalBtn = document.getElementById('openModal');
    const closeModalBtn = document.getElementById('closeModal');
    const cancelModalBtn = document.getElementById('cancelModal');

    function toggleModal(show) {
        if (!openModalBtn || openModalBtn.disabled) {
            return;
        }
        modal.classList.toggle('active', show);
    }

    if (openModalBtn) {
        openModalBtn.addEventListener('click', () => toggleModal(true));
    }
    closeModalBtn.addEventListener('click', () => toggleModal(false));
    cancelModalBtn.addEventListener('click', () => toggleModal(false));
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            toggleModal(false);
        }
    });
</script>
<?php include __DIR__ . '/inc/footer.php'; ?>
