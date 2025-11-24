<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
exigirPermissaoPagina('dashboard');

$paginaTitulo = 'Dashboard';

$erroDashboard = '';
$kpis = [
    'total_ferramentas' => 0,
    'emprestadas' => 0,
    'alertas_calibracao' => 0,
];
$topFerramentas = [];
$usoSetor = ['labels' => [], 'emprestado' => [], 'emUso' => []];
$usoClasse = ['labels' => [], 'emprestado' => [], 'emUso' => []];
$tendencia = ['labels' => [], 'emprestimos' => [], 'devolucoes' => []];
$usuarioAtual = usuarioLogado();
$usuarioId = (int)($usuarioAtual['id'] ?? 0);
$usuarioEhComum = usuarioTemPerfil('Usuário');

function formatarDataCurta(?string $data): string
{
    if (!$data) {
        return '-';
    }
    try {
        return (new DateTime($data))->format('d/m');
    } catch (Exception $e) {
        return '-';
    }
}

try {
    if ($usuarioEhComum && $usuarioId > 0) {
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(quantidade), 0) FROM emprestimos WHERE usuario_id = :usuario');
        $stmt->execute([':usuario' => $usuarioId]);
        $kpis['total_ferramentas'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantidade), 0) FROM emprestimos WHERE status = 'Emprestado' AND usuario_id = :usuario");
        $stmt->execute([':usuario' => $usuarioId]);
        $kpis['emprestadas'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT c.id) FROM calibracoes c
            INNER JOIN emprestimos e ON e.ferramenta_id = c.ferramenta_id
            WHERE e.usuario_id = :usuario AND e.status = 'Emprestado' AND c.status IN ('Vencida','Próxima','Proxima')");
        $stmt->execute([':usuario' => $usuarioId]);
        $kpis['alertas_calibracao'] = (int)$stmt->fetchColumn();
    } else {
        $kpis['total_ferramentas'] = (int)($pdo->query('SELECT COALESCE(SUM(quantidade_total), 0) FROM ferramentas')->fetchColumn() ?: 0);
        $kpis['emprestadas'] = (int)($pdo->query("SELECT COALESCE(SUM(quantidade), 0) FROM emprestimos WHERE status = 'Emprestado'")->fetchColumn() ?: 0);
        $kpis['alertas_calibracao'] = (int)($pdo->query("SELECT COUNT(*) FROM calibracoes WHERE status IN ('Vencida','Próxima','Proxima')")->fetchColumn() ?: 0);
    }

    if ($usuarioEhComum && $usuarioId > 0) {
        $topStmt = $pdo->prepare('SELECT f.codigo, f.descricao, COUNT(e.id) AS total_usos, MAX(e.data_saida) AS ultima_utilizacao
            FROM emprestimos e
            INNER JOIN ferramentas f ON f.id = e.ferramenta_id
            WHERE e.usuario_id = :usuario
            GROUP BY f.id
            ORDER BY total_usos DESC, ultima_utilizacao DESC
            LIMIT 5');
        $topStmt->execute([':usuario' => $usuarioId]);
    } else {
        $topStmt = $pdo->query('SELECT f.codigo, f.descricao, COUNT(e.id) AS total_usos, MAX(e.data_saida) AS ultima_utilizacao
            FROM emprestimos e
            INNER JOIN ferramentas f ON f.id = e.ferramenta_id
            GROUP BY f.id
            ORDER BY total_usos DESC, ultima_utilizacao DESC
            LIMIT 5');
    }
    $topFerramentas = $topStmt->fetchAll();

    if ($usuarioEhComum && $usuarioId > 0) {
        $usoSetorStmt = $pdo->prepare("SELECT COALESCE(NULLIF(u.setor, ''), 'Não informado') AS setor,
                SUM(e.quantidade) AS total_quantidade,
                SUM(CASE WHEN e.status = 'Emprestado' THEN e.quantidade ELSE 0 END) AS em_uso
            FROM emprestimos e
            LEFT JOIN usuarios u ON u.id = e.usuario_id
            WHERE e.usuario_id = :usuario
            GROUP BY setor");
        $usoSetorStmt->execute([':usuario' => $usuarioId]);
    } else {
        $usoSetorStmt = $pdo->query("SELECT COALESCE(NULLIF(u.setor, ''), 'Não informado') AS setor,
                SUM(e.quantidade) AS total_quantidade,
                SUM(CASE WHEN e.status = 'Emprestado' THEN e.quantidade ELSE 0 END) AS em_uso
            FROM emprestimos e
            LEFT JOIN usuarios u ON u.id = e.usuario_id
            GROUP BY setor
            ORDER BY total_quantidade DESC");
    }
    foreach ($usoSetorStmt->fetchAll() as $row) {
        $usoSetor['labels'][] = $row['setor'];
        $usoSetor['emprestado'][] = (int)($row['total_quantidade'] ?? 0);
        $usoSetor['emUso'][] = (int)($row['em_uso'] ?? 0);
    }

    if ($usuarioEhComum && $usuarioId > 0) {
        $usoClasseStmt = $pdo->prepare("SELECT c.nome AS classe,
                SUM(e.quantidade) AS total_quantidade,
                SUM(CASE WHEN e.status = 'Emprestado' THEN e.quantidade ELSE 0 END) AS em_uso
            FROM emprestimos e
            INNER JOIN ferramentas f ON f.id = e.ferramenta_id
            INNER JOIN classes c ON c.id = f.classe_id
            WHERE e.usuario_id = :usuario
            GROUP BY c.id");
        $usoClasseStmt->execute([':usuario' => $usuarioId]);
    } else {
        $usoClasseStmt = $pdo->query("SELECT c.nome AS classe,
                SUM(e.quantidade) AS total_quantidade,
                SUM(CASE WHEN e.status = 'Emprestado' THEN e.quantidade ELSE 0 END) AS em_uso
            FROM emprestimos e
            INNER JOIN ferramentas f ON f.id = e.ferramenta_id
            INNER JOIN classes c ON c.id = f.classe_id
            GROUP BY c.id
            ORDER BY total_quantidade DESC
            LIMIT 6");
    }
    foreach ($usoClasseStmt->fetchAll() as $row) {
        $usoClasse['labels'][] = $row['classe'];
        $usoClasse['emprestado'][] = (int)($row['total_quantidade'] ?? 0);
        $usoClasse['emUso'][] = (int)($row['em_uso'] ?? 0);
    }

    $limiteMes = (new DateTime('first day of this month'))->modify('-5 months');
    if ($usuarioEhComum && $usuarioId > 0) {
        $emprestimosMesStmt = $pdo->prepare('SELECT DATE_FORMAT(data_saida, "%Y-%m") AS ref, COALESCE(SUM(quantidade), 0) AS total
            FROM emprestimos WHERE data_saida >= :limite AND usuario_id = :usuario GROUP BY ref');
        $emprestimosMesStmt->execute([':limite' => $limiteMes->format('Y-m-d'), ':usuario' => $usuarioId]);

        $devolucoesMesStmt = $pdo->prepare('SELECT DATE_FORMAT(data_retorno, "%Y-%m") AS ref, COALESCE(SUM(quantidade), 0) AS total
            FROM emprestimos WHERE data_retorno IS NOT NULL AND data_retorno >= :limite AND usuario_id = :usuario GROUP BY ref');
        $devolucoesMesStmt->execute([':limite' => $limiteMes->format('Y-m-d'), ':usuario' => $usuarioId]);
    } else {
        $emprestimosMesStmt = $pdo->prepare('SELECT DATE_FORMAT(data_saida, "%Y-%m") AS ref, COALESCE(SUM(quantidade), 0) AS total
            FROM emprestimos WHERE data_saida >= :limite GROUP BY ref');
        $emprestimosMesStmt->execute([':limite' => $limiteMes->format('Y-m-d')]);

        $devolucoesMesStmt = $pdo->prepare('SELECT DATE_FORMAT(data_retorno, "%Y-%m") AS ref, COALESCE(SUM(quantidade), 0) AS total
            FROM emprestimos WHERE data_retorno IS NOT NULL AND data_retorno >= :limite GROUP BY ref');
        $devolucoesMesStmt->execute([':limite' => $limiteMes->format('Y-m-d')]);
    }
    $emprestimosMes = $emprestimosMesStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $devolucoesMes = $devolucoesMesStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $mesAtual = new DateTime('first day of this month');
    $nomesMes = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    for ($i = 5; $i >= 0; $i--) {
        $mesRef = (clone $mesAtual)->modify("-$i months");
        $chave = $mesRef->format('Y-m');
        $tendencia['labels'][] = $nomesMes[(int)$mesRef->format('n') - 1];
        $tendencia['emprestimos'][] = (int)($emprestimosMes[$chave] ?? 0);
        $tendencia['devolucoes'][] = (int)($devolucoesMes[$chave] ?? 0);
    }
} catch (PDOException $e) {
    $erroDashboard = 'Não foi possível carregar os dados do dashboard no momento.';
}

$kpiDescricoes = [
    'total' => $usuarioEhComum ? 'Itens já emprestados para você' : 'Cadastradas no sistema',
    'emprestadas' => $usuarioEhComum ? 'Itens com você neste momento' : 'Atualmente em uso',
    'alertas' => $usuarioEhComum ? 'Itens sob sua responsabilidade' : 'Próximas do vencimento',
];
$kpiIcons = ['total' => '🧰', 'emprestadas' => '🔁', 'alertas' => '📅'];
$kpiLinks = [
    'total' => podeAcessarPagina('ferramentas') ? 'ferramentas.php' : '#',
    'emprestadas' => podeAcessarPagina('emprestimos') ? 'emprestimos.php' : '#',
    'alertas' => podeAcessarPagina('calibracao') ? 'calibracao.php' : '#',
];

include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
?>
<div class="content-area">
    <div class="page-header">
        <h2>Dashboard</h2>
        <span><?= $usuarioEhComum ? 'Resumo dos seus empréstimos' : 'Visão geral do Sistema JOMAGA'; ?></span>
    </div>

    <?php if ($erroDashboard): ?>
        <div class="alert alert-danger rounded-3 py-2 px-3 mb-3"><?= htmlspecialchars($erroDashboard); ?></div>
    <?php endif; ?>

    <section class="kpi-grid">
        <a href="<?= htmlspecialchars($kpiLinks['total']); ?>" class="kpi-card<?= $kpiLinks['total'] === '#' ? ' kpi-disabled' : ''; ?>" <?= $kpiLinks['total'] === '#' ? 'aria-disabled="true"' : ''; ?>>
            <div>
                <h3>Total de Ferramentas</h3>
                <strong><?= number_format($kpis['total_ferramentas'], 0, ',', '.'); ?></strong>
                <span><?= htmlspecialchars($kpiDescricoes['total']); ?></span>
            </div>
            <div class="kpi-icon"><?= $kpiIcons['total']; ?></div>
        </a>
        <a href="<?= htmlspecialchars($kpiLinks['emprestadas']); ?>" class="kpi-card<?= $kpiLinks['emprestadas'] === '#' ? ' kpi-disabled' : ''; ?>" <?= $kpiLinks['emprestadas'] === '#' ? 'aria-disabled="true"' : ''; ?>>
            <div>
                <h3>Ferramentas Emprestadas</h3>
                <strong><?= number_format($kpis['emprestadas'], 0, ',', '.'); ?></strong>
                <span><?= htmlspecialchars($kpiDescricoes['emprestadas']); ?></span>
            </div>
            <div class="kpi-icon"><?= $kpiIcons['emprestadas']; ?></div>
        </a>
        <a href="<?= htmlspecialchars($kpiLinks['alertas']); ?>" class="kpi-card<?= $kpiLinks['alertas'] === '#' ? ' kpi-disabled' : ''; ?>" <?= $kpiLinks['alertas'] === '#' ? 'aria-disabled="true"' : ''; ?>>
            <div>
                <h3>Alertas de Calibração</h3>
                <strong><?= number_format($kpis['alertas_calibracao'], 0, ',', '.'); ?></strong>
                <span><?= htmlspecialchars($kpiDescricoes['alertas']); ?></span>
            </div>
            <div class="kpi-icon"><?= $kpiIcons['alertas']; ?></div>
        </a>
    </section>

    <div class="grid-two">
        <section class="card">
            <h3>Uso por Setor</h3>
            <p><?= $usuarioEhComum ? 'Distribuição dos seus empréstimos por setor.' : 'Quantidade de ferramentas emprestadas e em uso por departamento.'; ?></p>
            <canvas id="usoSetorChart"></canvas>
        </section>

        <section class="card">
            <h3>Ferramentas Mais Usadas</h3>
            <p><?= $usuarioEhComum ? 'Ferramentas que você mais utiliza.' : 'Top ferramentas por volume de uso.'; ?></p>
            <?php if (empty($topFerramentas)): ?>
                <p class="text-muted">Ainda não há movimentações registradas.</p>
            <?php else: ?>
                <ol class="tool-list">
                    <?php foreach ($topFerramentas as $index => $item): ?>
                        <li class="tool-item">
                            <div class="tool-info">
                                <div class="tool-position"><?= $index + 1; ?></div>
                                <div class="tool-details">
                                    <span><?= htmlspecialchars($item['descricao']); ?></span>
                                    <small><?= htmlspecialchars($item['codigo']); ?> · <?= (int)$item['total_usos']; ?> usos</small>
                                </div>
                            </div>
                            <div class="tool-usage"><?= htmlspecialchars(formatarDataCurta($item['ultima_utilizacao'] ?? null)); ?></div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>

    <div class="grid-two-bottom">
        <section class="card">
            <h3>Uso por Classe de Ferramenta</h3>
            <p><?= $usuarioEhComum ? 'Comparativo das classes que você utiliza.' : 'Comparativo de empréstimos entre classes cadastradas.'; ?></p>
            <canvas id="usoClasseChart"></canvas>
        </section>

        <section class="card">
            <h3>Tendência de Uso</h3>
            <p><?= $usuarioEhComum ? 'Evolução mensal dos seus empréstimos.' : 'Empréstimos e devoluções nos últimos seis meses.'; ?></p>
            <canvas id="tendenciaChart"></canvas>
        </section>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const usoSetorData = <?= json_encode($usoSetor, JSON_UNESCAPED_UNICODE); ?>;
    const usoClasseData = <?= json_encode($usoClasse, JSON_UNESCAPED_UNICODE); ?>;
    const tendenciaData = <?= json_encode($tendencia, JSON_UNESCAPED_UNICODE); ?>;

    const usoSetorCtx = document.getElementById('usoSetorChart');
    if (usoSetorCtx) {
        new Chart(usoSetorCtx, {
            type: 'bar',
            data: {
                labels: usoSetorData.labels,
                datasets: [
                    {
                        label: 'Emprestado',
                        data: usoSetorData.emprestado,
                        backgroundColor: 'rgba(37, 99, 235, 0.8)',
                        borderRadius: 8,
                        barThickness: 28
                    },
                    {
                        label: 'Em uso',
                        data: usoSetorData.emUso,
                        backgroundColor: 'rgba(99, 102, 241, 0.6)',
                        borderRadius: 8,
                        barThickness: 28
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { ticks: { color: '#6B7280' }, grid: { display: false } },
                    y: { ticks: { color: '#6B7280' }, grid: { color: '#E5E7EB', drawBorder: false }, beginAtZero: true }
                },
                plugins: { legend: { labels: { color: '#374151' } } }
            }
        });
    }

    const usoClasseCtx = document.getElementById('usoClasseChart');
    if (usoClasseCtx) {
        new Chart(usoClasseCtx, {
            type: 'bar',
            data: {
                labels: usoClasseData.labels,
                datasets: [
                    {
                        label: 'Emprestado',
                        data: usoClasseData.emprestado,
                        backgroundColor: 'rgba(37, 99, 235, 0.85)',
                        borderRadius: 10,
                        barThickness: 32
                    },
                    {
                        label: 'Em uso',
                        data: usoClasseData.emUso,
                        backgroundColor: 'rgba(59, 130, 246, 0.55)',
                        borderRadius: 10,
                        barThickness: 32
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { ticks: { color: '#6B7280' }, grid: { display: false } },
                    y: { ticks: { color: '#6B7280' }, grid: { color: '#E5E7EB', drawBorder: false }, beginAtZero: true }
                },
                plugins: { legend: { labels: { color: '#374151' } } }
            }
        });
    }

    const tendenciaCtx = document.getElementById('tendenciaChart');
    if (tendenciaCtx) {
        new Chart(tendenciaCtx, {
            type: 'line',
            data: {
                labels: tendenciaData.labels,
                datasets: [
                    {
                        label: 'Empréstimos',
                        data: tendenciaData.emprestimos,
                        borderColor: 'rgba(37, 99, 235, 1)',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        borderWidth: 3,
                        tension: 0.35,
                        fill: true,
                        pointRadius: 4
                    },
                    {
                        label: 'Devoluções',
                        data: tendenciaData.devolucoes,
                        borderColor: 'rgba(16, 185, 129, 1)',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 3,
                        tension: 0.35,
                        fill: true,
                        pointRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { ticks: { color: '#6B7280' }, grid: { display: false } },
                    y: { ticks: { color: '#6B7280' }, grid: { color: '#E5E7EB', drawBorder: false }, beginAtZero: true }
                },
                plugins: { legend: { labels: { color: '#374151' } } }
            }
        });
    }
</script>
<style>
    .kpi-card.kpi-disabled {
        pointer-events: none;
        opacity: 0.85;
    }
</style>
<?php include __DIR__ . '/inc/footer.php'; ?>
