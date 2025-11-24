<?php
require_once __DIR__ . '/inc/auth.php';
exigirPermissaoPagina('historico');
require_once __DIR__ . '/inc/db.php';

$paginaTitulo = 'Histórico e Auditoria';

$erro = '';
$ferramentas = [];
$usuarios = [];
$historicoFerramenta = [];
$historicoUsuario = [];
$stats = [
    'registros' => 0,
    'abertos' => 0,
    'devolvidos' => 0,
];

$ferramentaSelecionada = isset($_GET['ferramenta_id']) ? (int) $_GET['ferramenta_id'] : 0;
$usuarioSelecionado = isset($_GET['usuario_id']) ? (int) $_GET['usuario_id'] : 0;

function formatarDataHora(?string $data): string
{
    if (!$data) {
        return '-';
    }
    try {
        return (new DateTime($data))->format('d/m/Y H:i');
    } catch (Exception $e) {
        return '-';
    }
}

try {
    $stats['registros'] = (int) ($pdo->query('SELECT COUNT(*) FROM historico')->fetchColumn() ?: 0);
    $stats['abertos'] = (int) ($pdo->query("SELECT COUNT(*) FROM emprestimos WHERE status = 'Emprestado'")->fetchColumn() ?: 0);
    $stats['devolvidos'] = (int) ($pdo->query("SELECT COUNT(*) FROM emprestimos WHERE status = 'Devolvido'")->fetchColumn() ?: 0);

    $ferramentas = $pdo->query('SELECT id, codigo, descricao FROM ferramentas ORDER BY codigo')->fetchAll();
    $usuarios = $pdo->query('SELECT id, CONCAT(nome, " ", sobrenome) AS nome_completo, perfil FROM usuarios ORDER BY nome, sobrenome')->fetchAll();

    if ($ferramentaSelecionada > 0) {
        $stmtFerramenta = $pdo->prepare('SELECT h.data_registro, h.acao, h.descricao, e.id AS emprestimo_id, e.quantidade,
                e.status AS status_emprestimo, e.data_saida, e.data_retorno,
                CONCAT(u.nome, " ", u.sobrenome) AS usuario_nome,
                CONCAT(op.nome, " ", op.sobrenome) AS operador_nome
            FROM historico h
            INNER JOIN emprestimos e ON e.id = h.emprestimo_id
            LEFT JOIN usuarios u ON u.id = e.usuario_id
            LEFT JOIN usuarios op ON op.id = e.operador_id
            WHERE e.ferramenta_id = :ferramenta
            ORDER BY h.data_registro DESC');
        $stmtFerramenta->execute([':ferramenta' => $ferramentaSelecionada]);
        $historicoFerramenta = $stmtFerramenta->fetchAll();
    }

    if ($usuarioSelecionado > 0) {
        $stmtUsuario = $pdo->prepare('SELECT h.data_registro, h.acao, h.descricao, e.id AS emprestimo_id, e.quantidade,
                e.status AS status_emprestimo, e.data_saida, e.data_retorno,
                f.codigo AS ferramenta_codigo, f.descricao AS ferramenta_nome
            FROM historico h
            INNER JOIN emprestimos e ON e.id = h.emprestimo_id
            INNER JOIN ferramentas f ON f.id = e.ferramenta_id
            WHERE e.usuario_id = :usuario
            ORDER BY h.data_registro DESC');
        $stmtUsuario->execute([':usuario' => $usuarioSelecionado]);
        $historicoUsuario = $stmtUsuario->fetchAll();
    }
} catch (PDOException $e) {
    $erro = 'Não foi possível carregar os dados do histórico agora. Tente novamente mais tarde.';
}

include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
?>
<div class="content-area historico-page">
    <div class="page-header">
        <div>
            <h1>Histórico e Auditoria</h1>
            <span>Rastreabilidade completa de movimentações e usuários</span>
        </div>
    </div>

    <?php if ($erro): ?>
        <div class="alert alert-danger rounded-3 py-2 px-3"><?= htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div>
                <span>Registros de auditoria</span>
                <strong><?= number_format($stats['registros'], 0, ',', '.'); ?></strong>
            </div>
            <div class="stat-icon">🗂️</div>
        </div>
        <div class="stat-card">
            <div>
                <span>Empréstimos em aberto</span>
                <strong><?= number_format($stats['abertos'], 0, ',', '.'); ?></strong>
            </div>
            <div class="stat-icon">📦</div>
        </div>
        <div class="stat-card">
            <div>
                <span>Devoluções registradas</span>
                <strong><?= number_format($stats['devolvidos'], 0, ',', '.'); ?></strong>
            </div>
            <div class="stat-icon">↩️</div>
        </div>
    </div>

    <div class="audit-card">
        <div class="audit-tabs">
            <button class="tab <?= $ferramentaSelecionada || !$usuarioSelecionado ? 'active' : ''; ?>" data-tab="ferramenta">Rastrear Ferramenta</button>
            <button class="tab <?= $usuarioSelecionado > 0 ? 'active' : ''; ?>" data-tab="usuario">Rastrear Usuário</button>
        </div>

        <div class="tab-content" data-tab-content="ferramenta" <?= $usuarioSelecionado > 0 ? 'hidden' : ''; ?>>
            <form method="GET" class="filter-form">
                <label for="ferramenta_id">Selecione uma ferramenta</label>
                <div class="select-row">
                    <select name="ferramenta_id" id="ferramenta_id" onchange="this.form.submit()">
                        <option value="">-- Escolha --</option>
                        <?php foreach ($ferramentas as $ferramenta): ?>
                            <option value="<?= (int) $ferramenta['id']; ?>" <?= $ferramentaSelecionada === (int) $ferramenta['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($ferramenta['codigo'] . ' · ' . $ferramenta['descricao']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($ferramentaSelecionada): ?>
                        <a class="btn-reset" href="historico.php">Limpar</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($ferramentaSelecionada && empty($historicoFerramenta)): ?>
                <p class="text-muted mt-3">Nenhuma movimentação encontrada para esta ferramenta.</p>
            <?php elseif ($ferramentaSelecionada): ?>
                <div class="table-wrapper mt-3">
                    <table>
                        <thead>
                        <tr>
                            <th>Momento</th>
                            <th>Ação</th>
                            <th>Descrição</th>
                            <th>Usuário</th>
                            <th>Operador</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($historicoFerramenta as $item): ?>
                            <?php $status = $item['status_emprestimo'] ?? '-'; ?>
                            <tr>
                                <td><?= htmlspecialchars(formatarDataHora($item['data_registro'] ?? null)); ?></td>
                                <td><span class="badge badge-neutral"><?= htmlspecialchars($item['acao'] ?? '-'); ?></span></td>
                                <td><?= htmlspecialchars($item['descricao'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars($item['usuario_nome'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars($item['operador_nome'] ?? '-'); ?></td>
                                <td><span class="badge <?= strtolower($status) === 'devolvido' ? 'badge-success' : (strtolower($status) === 'emprestado' ? 'badge-warning' : 'badge-neutral'); ?>"><?= htmlspecialchars($status); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted mt-3">Selecione uma ferramenta para visualizar o histórico completo de empréstimos e devoluções.</p>
            <?php endif; ?>
        </div>

        <div class="tab-content" data-tab-content="usuario" <?= $usuarioSelecionado > 0 ? '' : 'hidden'; ?>>
            <form method="GET" class="filter-form">
                <label for="usuario_id">Selecione um usuário</label>
                <div class="select-row">
                    <select name="usuario_id" id="usuario_id" onchange="this.form.submit()">
                        <option value="">-- Escolha --</option>
                        <?php foreach ($usuarios as $usuario): ?>
                            <option value="<?= (int) $usuario['id']; ?>" <?= $usuarioSelecionado === (int) $usuario['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($usuario['nome_completo']); ?> (<?= htmlspecialchars($usuario['perfil']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($usuarioSelecionado): ?>
                        <a class="btn-reset" href="historico.php">Limpar</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($usuarioSelecionado && empty($historicoUsuario)): ?>
                <p class="text-muted mt-3">Nenhuma movimentação encontrada para este usuário.</p>
            <?php elseif ($usuarioSelecionado): ?>
                <div class="table-wrapper mt-3">
                    <table>
                        <thead>
                        <tr>
                            <th>Momento</th>
                            <th>Ação</th>
                            <th>Ferramenta</th>
                            <th>Qtd</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($historicoUsuario as $item): ?>
                            <?php $status = $item['status_emprestimo'] ?? '-'; ?>
                            <tr>
                                <td><?= htmlspecialchars(formatarDataHora($item['data_registro'] ?? null)); ?></td>
                                <td><span class="badge badge-neutral"><?= htmlspecialchars($item['acao'] ?? '-'); ?></span></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['ferramenta_codigo'] ?? '-'); ?></strong><br>
                                    <small><?= htmlspecialchars($item['ferramenta_nome'] ?? '-'); ?></small>
                                </td>
                                <td><?= (int) ($item['quantidade'] ?? 0); ?></td>
                                <td><span class="badge <?= strtolower($status) === 'devolvido' ? 'badge-success' : (strtolower($status) === 'emprestado' ? 'badge-warning' : 'badge-neutral'); ?>"><?= htmlspecialchars($status); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted mt-3">Selecione um usuário para acompanhar os empréstimos e devoluções registrados.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .historico-page { padding-top: 1rem; }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.2rem;
    }
    .stat-card {
        border: 1px solid #E5E7EB;
        border-radius: 1rem;
        background: #FFF;
        padding: 1rem 1.2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .stat-card span { color: #6B7280; font-size: 0.9rem; }
    .stat-card strong { font-size: 1.8rem; }
    .stat-icon { font-size: 1.7rem; }
    .audit-card {
        background: #FFF;
        border: 1px solid #E5E7EB;
        border-radius: 1rem;
        padding: 1.25rem;
    }
    .audit-tabs {
        display: inline-flex;
        border: 1px solid #E5E7EB;
        border-radius: 0.8rem;
        margin-bottom: 1rem;
        overflow: hidden;
    }
    .tab {
        border: none;
        background: transparent;
        padding: 0.65rem 1.3rem;
        cursor: pointer;
        color: #1F2937;
        font-weight: 600;
    }
    .tab.active {
        background: #EEF2FF;
        color: #1D4ED8;
    }
    .filter-form label { font-weight: 600; color: #1F2937; }
    .select-row {
        display: flex;
        gap: 0.6rem;
        margin-top: 0.5rem;
        flex-wrap: wrap;
    }
    .filter-form select {
        border: 1px solid #CBD5F5;
        border-radius: 0.85rem;
        padding: 0.65rem 0.8rem;
        min-width: 260px;
        font-family: inherit;
    }
    .btn-reset {
        border-radius: 999px;
        border: 1px solid #E5E7EB;
        padding: 0.55rem 1rem;
        color: #374151;
        text-decoration: none;
    }
    .table-wrapper {
        border: 1px solid #E5E7EB;
        border-radius: 1rem;
        overflow: hidden;
        background: #FFF;
    }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 0.9rem 1rem; border-bottom: 1px solid #F3F4F6; text-align: left; }
    th { text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; color: #6B7280; background: #F9FAFB; }
    .badge { border-radius: 999px; padding: 0.25rem 0.9rem; font-size: 0.8rem; font-weight: 600; display: inline-block; }
    .badge-neutral { background: #E5E7EB; color: #374151; }
    .badge-warning { background: #FEF3C7; color: #B45309; }
    .badge-success { background: #DCFCE7; color: #166534; }
    .mt-3 { margin-top: 1rem; }
    @media (max-width: 768px) {
        .filter-form select { width: 100%; }
        .audit-tabs { width: 100%; }
        .tab { flex: 1; text-align: center; }
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const tabs = document.querySelectorAll('.tab');
        const contents = document.querySelectorAll('.tab-content');
        tabs.forEach(tab => tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            contents.forEach(content => {
                content.hidden = content.dataset.tabContent !== tab.dataset.tab;
            });
        }));
    });
</script>
<?php include __DIR__ . '/inc/footer.php'; ?>
