<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';

$paginaTitulo = 'Inventário';

$erroCarregamento = '';
$classes = [];
$statuses = [];
$inventario = [];
$totalFerramentas = 0;
$disponiveis = 0;
$emUso = 0;

try {
    $totStmt = $pdo->query('SELECT COALESCE(SUM(quantidade_total),0) AS total, COALESCE(SUM(quantidade_disponivel),0) AS disponivel FROM ferramentas');
    $totais = $totStmt->fetch() ?: [];
    $totalFerramentas = (int)($totais['total'] ?? 0);
    $disponiveis = (int)($totais['disponivel'] ?? 0);
    $emUso = max(0, $totalFerramentas - $disponiveis);

    $classes = $pdo->query('SELECT id, nome FROM classes ORDER BY nome')->fetchAll();
    $statuses = $pdo->query('SELECT DISTINCT status FROM ferramentas ORDER BY status')->fetchAll(PDO::FETCH_COLUMN);

    $invStmt = $pdo->query('SELECT f.id, f.codigo, f.descricao, c.nome AS classe, m.nome AS modelo, f.quantidade_total,
        f.quantidade_disponivel, (f.quantidade_total - f.quantidade_disponivel) AS em_uso, f.status
        FROM ferramentas f
        INNER JOIN classes c ON c.id = f.classe_id
        INNER JOIN modelos m ON m.id = f.modelo_id
        ORDER BY f.codigo');
    $inventario = $invStmt->fetchAll();
} catch (PDOException $e) {
    $erroCarregamento = 'Não foi possível carregar os dados do inventário agora.';
}

include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
?>
<div class="content-area inventario-page">
    <div class="page-header with-actions">
        <div>
            <h1>Inventário</h1>
            <span>Visualize todas as ferramentas cadastradas e seus status em tempo real</span>
        </div>
    </div>

    <?php if ($erroCarregamento): ?>
        <div class="alert alert-danger rounded-3 py-2 px-3"><?= htmlspecialchars($erroCarregamento); ?></div>
    <?php endif; ?>

    <div class="summary-grid">
        <div class="summary-card">
            <div>
                <span>Quantidade Total</span>
                <strong><?= number_format($totalFerramentas, 0, ',', '.'); ?></strong>
            </div>
            <div class="summary-icon">&#128230;</div>
        </div>
        <div class="summary-card">
            <div>
                <span>Disponíveis</span>
                <strong class="text-success"><?= number_format($disponiveis, 0, ',', '.'); ?></strong>
            </div>
            <div class="summary-icon">&#9989;</div>
        </div>
        <div class="summary-card">
            <div>
                <span>Em uso</span>
                <strong class="text-warning"><?= number_format($emUso, 0, ',', '.'); ?></strong>
            </div>
            <div class="summary-icon">&#128295;</div>
        </div>
    </div>

    <div class="filters-row">
        <div class="search-box">
            <span>&#128269;</span>
            <input type="search" placeholder="Buscar por nome ou código..." data-table-filter="#tabela-inventario">
        </div>
        <select class="filter-select" id="classeFilter">
            <option value="">Todas as Classes</option>
            <?php foreach ($classes as $classe): ?>
                <option value="<?= htmlspecialchars(strtolower($classe['nome'])); ?>"><?= htmlspecialchars($classe['nome']); ?></option>
            <?php endforeach; ?>
        </select>
        <select class="filter-select" id="statusFilter">
            <option value="">Todos os Status</option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= htmlspecialchars(strtolower($status)); ?>"><?= htmlspecialchars($status); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="table-wrapper">
        <table id="tabela-inventario">
            <thead>
            <tr>
                <th>Código</th>
                <th>Nome</th>
                <th>Classe</th>
                <th>Modelo</th>
                <th>Qtd Total</th>
                <th>Disponível</th>
                <th>Em Uso</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($inventario)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">Nenhuma ferramenta cadastrada.</td>
                </tr>
            <?php else: ?>
                <?php
                $statusClasseMap = [
                    'disponível' => 'status-disponivel',
                    'emprestada' => 'status-emprestada',
                    'em manutenção' => 'status-manutencao',
                    'indisponível' => 'status-fora',
                ];
                foreach ($inventario as $item):
                    $statusLower = mb_strtolower($item['status'], 'UTF-8');
                    $classeLower = mb_strtolower($item['classe'], 'UTF-8');
                    $searchText = mb_strtolower($item['codigo'] . ' ' . $item['descricao'] . ' ' . $item['classe'] . ' ' . $item['modelo'], 'UTF-8');
                    $badgeClass = $statusClasseMap[$statusLower] ?? 'status-disponivel';
                    ?>
                    <tr data-search="<?= htmlspecialchars($searchText); ?>" data-classe="<?= htmlspecialchars($classeLower); ?>" data-status="<?= htmlspecialchars($statusLower); ?>">
                        <td><span class="code-pill"><?= htmlspecialchars($item['codigo']); ?></span></td>
                        <td><?= htmlspecialchars($item['descricao']); ?></td>
                        <td><?= htmlspecialchars($item['classe']); ?></td>
                        <td><?= htmlspecialchars($item['modelo']); ?></td>
                        <td><?= (int)$item['quantidade_total']; ?></td>
                        <td class="text-success"><?= (int)$item['quantidade_disponivel']; ?></td>
                        <td><?= max(0, (int)$item['em_uso']); ?></td>
                        <td><span class="badge <?= $badgeClass; ?>"><?= htmlspecialchars($item['status']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
    .inventario-page { padding-top: 1rem; }
    .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; margin-bottom: 1.2rem; }
    .summary-card { border: 1px solid #E5E7EB; border-radius: 0.9rem; padding: 1rem 1.1rem; display: flex; justify-content: space-between; align-items: center; background: #FFF; }
    .summary-card span { color: #6B7280; font-size: 0.85rem; }
    .summary-card strong { font-size: 1.6rem; }
    .summary-icon { font-size: 1.5rem; }
    .filters-row { display: flex; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap; }
    .search-box { position: relative; flex: 1; min-width: 220px; }
    .search-box span { position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
    .search-box input { width: 100%; border-radius: 0.75rem; border: 1px solid #CBD5F5; padding: 0.6rem 0.6rem 0.6rem 2.3rem; }
    .filter-select { border-radius: 0.75rem; border: 1px solid #CBD5F5; padding: 0.6rem; min-width: 160px; }
    .table-wrapper { background: #FFF; border-radius: 1rem; border: 1px solid #E5E7EB; overflow: hidden; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 0.85rem 1rem; border-bottom: 1px solid #F3F4F6; text-align: left; font-size: 0.95rem; }
    th { text-transform: uppercase; font-size: 0.75rem; color: #6B7280; letter-spacing: 0.05em; background: #F9FAFB; }
    .code-pill { background: #F3F4F6; padding: 0.25rem 0.6rem; border-radius: 0.6rem; font-weight: 600; display: inline-block; }
    .badge { border-radius: 999px; padding: 0.25rem 0.85rem; font-size: 0.85rem; font-weight: 600; }
    .status-disponivel { background: #DBEAFE; color: #1D4ED8; }
    .status-emprestada { background: #FEE2E2; color: #B91C1C; }
    .status-manutencao { background: #FEF3C7; color: #B45309; }
    .status-fora { background: #E5E7EB; color: #374151; }
    .text-success { color: #16A34A; }
    .text-warning { color: #EA580C; }
    @media (max-width: 768px) {
        .filters-row { flex-direction: column; }
        .filter-select { width: 100%; }
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.querySelector('[data-table-filter="#tabela-inventario"]');
        const classeSelect = document.getElementById('classeFilter');
        const statusSelect = document.getElementById('statusFilter');
        const rows = Array.from(document.querySelectorAll('#tabela-inventario tbody tr[data-search]'));

        function applyFilters() {
            const term = (searchInput?.value || '').toLowerCase();
            const classe = (classeSelect?.value || '').toLowerCase();
            const status = (statusSelect?.value || '').toLowerCase();

            rows.forEach((row) => {
                const matchesSearch = row.dataset.search.includes(term);
                const matchesClasse = !classe || row.dataset.classe === classe;
                const matchesStatus = !status || row.dataset.status === status;
                row.style.display = (matchesSearch && matchesClasse && matchesStatus) ? '' : 'none';
            });
        }

        if (searchInput) searchInput.addEventListener('input', applyFilters);
        if (classeSelect) classeSelect.addEventListener('change', applyFilters);
        if (statusSelect) statusSelect.addEventListener('change', applyFilters);
    });
</script>
<?php include __DIR__ . '/inc/footer.php'; ?>
