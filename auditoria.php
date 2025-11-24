<?php
require_once __DIR__ . '/inc/auth.php';
exigirPermissaoPagina('auditoria');
require_once __DIR__ . '/inc/db.php';

$paginaTitulo = 'Auditoria';

$erro = '';
$mensagem = '';
$acoes = [];
$registros = [];
$stats = [
    'total' => 0,
    'ultimas24h' => 0,
    'semana' => 0,
];

$filtros = [
    'query' => trim($_GET['q'] ?? ''),
    'acao' => trim($_GET['acao'] ?? ''),
    'data_inicial' => $_GET['data_inicial'] ?? '',
    'data_final' => $_GET['data_final'] ?? '',
];

function normalizarData(?string $data, bool $fim = false): ?string
{
    if (!$data) {
        return null;
    }
    try {
        $dt = new DateTime($data);
        if ($fim) {
            $dt->setTime(23, 59, 59);
        } else {
            $dt->setTime(0, 0, 0);
        }
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return null;
    }
}

function formatarDataHoraBr(?string $data): string
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
    $stats['total'] = (int) ($pdo->query('SELECT COUNT(*) FROM historico')->fetchColumn() ?: 0);
    $stats['ultimas24h'] = (int) ($pdo->query("SELECT COUNT(*) FROM historico WHERE data_registro >= DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchColumn() ?: 0);
    $stats['semana'] = (int) ($pdo->query("SELECT COUNT(*) FROM historico WHERE YEARWEEK(data_registro, 1) = YEARWEEK(NOW(), 1)")->fetchColumn() ?: 0);

    $acoes = $pdo->query('SELECT DISTINCT acao FROM historico ORDER BY acao')->fetchAll(PDO::FETCH_COLUMN);

    $query = 'SELECT h.id, h.acao, h.descricao, h.data_registro, e.id AS emprestimo_id,
                    f.codigo AS ferramenta_codigo, f.descricao AS ferramenta_nome,
                    CONCAT(u.nome, " ", u.sobrenome) AS usuario_nome,
                    CONCAT(op.nome, " ", op.sobrenome) AS operador_nome
              FROM historico h
              LEFT JOIN emprestimos e ON e.id = h.emprestimo_id
              LEFT JOIN ferramentas f ON f.id = e.ferramenta_id
              LEFT JOIN usuarios u ON u.id = e.usuario_id
              LEFT JOIN usuarios op ON op.id = e.operador_id';

    $condicoes = [];
    $params = [];

    if ($filtros['acao'] !== '') {
        $condicoes[] = 'h.acao = :acao';
        $params[':acao'] = $filtros['acao'];
    }

    if ($filtros['query'] !== '') {
        $condicoes[] = '(h.descricao LIKE :term OR f.codigo LIKE :term OR f.descricao LIKE :term OR CONCAT(u.nome, " ", u.sobrenome) LIKE :term)';
        $params[':term'] = '%' . $filtros['query'] . '%';
    }

    $dataInicio = normalizarData($filtros['data_inicial']);
    $dataFim = normalizarData($filtros['data_final'], true);
    if ($dataInicio) {
        $condicoes[] = 'h.data_registro >= :data_inicio';
        $params[':data_inicio'] = $dataInicio;
    }
    if ($dataFim) {
        $condicoes[] = 'h.data_registro <= :data_fim';
        $params[':data_fim'] = $dataFim;
    }

    if ($condicoes) {
        $query .= ' WHERE ' . implode(' AND ', $condicoes);
    }
    $query .= ' ORDER BY h.data_registro DESC LIMIT 120';

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $registros = $stmt->fetchAll();
} catch (PDOException $e) {
    $erro = 'Não foi possível carregar os logs de auditoria agora.';
}

include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
?>
<div class="content-area auditoria-page">
    <div class="page-header with-actions">
        <div>
            <h1>Auditoria</h1>
            <span>Registro das ações administrativas executadas no sistema</span>
        </div>
        <form method="GET">
            <button class="btn btn-outline-secondary rounded-pill px-3">Exportar log (CSV)</button>
        </form>
    </div>

    <?php if ($erro): ?>
        <div class="alert alert-danger rounded-3 py-2 px-3"><?= htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div>
                <span>Registros totais</span>
                <strong><?= number_format($stats['total'], 0, ',', '.'); ?></strong>
            </div>
            <div class="stat-icon">🗂️</div>
        </div>
        <div class="stat-card">
            <div>
                <span>Últimas 24h</span>
                <strong><?= number_format($stats['ultimas24h'], 0, ',', '.'); ?></strong>
            </div>
            <div class="stat-icon">⏱️</div>
        </div>
        <div class="stat-card">
            <div>
                <span>Semana atual</span>
                <strong><?= number_format($stats['semana'], 0, ',', '.'); ?></strong>
            </div>
            <div class="stat-icon">📅</div>
        </div>
    </div>

    <form class="filters-card" method="GET">
        <div class="filter-group">
            <label>Buscar</label>
            <div class="search-box">
                <span>🔍</span>
                <input type="search" name="q" placeholder="Descrição, usuário ou código..." value="<?= htmlspecialchars($filtros['query']); ?>">
            </div>
        </div>
        <div class="filter-row">
            <div class="filter-group">
                <label>Tipo de ação</label>
                <select name="acao">
                    <option value="">Todas</option>
                    <?php foreach ($acoes as $acao): ?>
                        <option value="<?= htmlspecialchars($acao); ?>" <?= $filtros['acao'] === $acao ? 'selected' : ''; ?>><?= htmlspecialchars($acao); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Data inicial</label>
                <input type="date" name="data_inicial" value="<?= htmlspecialchars($filtros['data_inicial']); ?>">
            </div>
            <div class="filter-group">
                <label>Data final</label>
                <input type="date" name="data_final" value="<?= htmlspecialchars($filtros['data_final']); ?>">
            </div>
        </div>
        <div class="filter-actions">
            <button class="btn-primary-modern" type="submit">Aplicar filtros</button>
            <a class="btn-reset" href="auditoria.php">Limpar</a>
        </div>
    </form>

    <div class="table-card">
        <table>
            <thead>
            <tr>
                <th>Momento</th>
                <th>Ação</th>
                <th>Descrição</th>
                <th>Usuário</th>
                <th>Operador</th>
                <th>Ferramenta</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($registros)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">Nenhum registro encontrado para os filtros selecionados.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($registros as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars(formatarDataHoraBr($log['data_registro'] ?? null)); ?></td>
                        <td><span class="badge badge-neutral"><?= htmlspecialchars($log['acao'] ?? '-'); ?></span></td>
                        <td><?= htmlspecialchars($log['descricao'] ?? '-'); ?></td>
                        <td><?= htmlspecialchars($log['usuario_nome'] ?? '-'); ?></td>
                        <td><?= htmlspecialchars($log['operador_nome'] ?? '-'); ?></td>
                        <td>
                            <?php if (!empty($log['ferramenta_codigo'])): ?>
                                <strong><?= htmlspecialchars($log['ferramenta_codigo']); ?></strong><br>
                                <small><?= htmlspecialchars($log['ferramenta_nome'] ?? '-'); ?></small>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
    .auditoria-page { padding-top: 1rem; }
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
    .stat-card strong { font-size: 1.7rem; }
    .stat-icon { font-size: 1.7rem; }
    .filters-card {
        border: 1px solid #E5E7EB;
        border-radius: 1rem;
        background: #FFF;
        padding: 1.1rem 1.3rem;
        margin-bottom: 1rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .filter-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 0.9rem;
    }
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }
    .filter-group input,
    .filter-group select {
        border: 1px solid #CBD5F5;
        border-radius: 0.85rem;
        padding: 0.6rem 0.8rem;
        font-family: inherit;
    }
    .search-box { position: relative; }
    .search-box span { position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
    .search-box input { padding-left: 2.4rem; }
    .filter-actions {
        display: flex;
        gap: 0.6rem;
        flex-wrap: wrap;
    }
    .btn-reset {
        border: 1px solid #E5E7EB;
        border-radius: 999px;
        padding: 0.55rem 1.2rem;
        color: #374151;
        text-decoration: none;
    }
    .btn-primary-modern {
        border: none;
        border-radius: 0.85rem;
        padding: 0.6rem 1.3rem;
        background: #1F56D8;
        color: #FFF;
        font-weight: 600;
        cursor: pointer;
    }
    .table-card {
        border: 1px solid #E5E7EB;
        border-radius: 1rem;
        overflow: hidden;
        background: #FFF;
    }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 0.85rem 1rem; border-bottom: 1px solid #F3F4F6; text-align: left; }
    th { text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; color: #6B7280; background: #F9FAFB; }
    .badge { border-radius: 999px; padding: 0.2rem 0.8rem; font-size: 0.8rem; font-weight: 600; display: inline-block; }
    .badge-neutral { background: #E5E7EB; color: #374151; }
    .text-muted { color: #6B7280; }
    @media (max-width: 768px) {
        .filter-actions { flex-direction: column; }
        .btn-reset { text-align: center; }
    }
</style>
<?php include __DIR__ . '/inc/footer.php'; ?>
