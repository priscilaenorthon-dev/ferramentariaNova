<?php
require_once __DIR__ . '/inc/auth.php';
exigirPermissaoPagina('relatorios');
require_once __DIR__ . '/inc/db.php';

$paginaTitulo = 'Relatórios';

$mensagem = '';
$erro = '';
$formData = [
    'tipo_relatorio' => $_POST['tipo_relatorio'] ?? 'emprestimos_abertos',
    'data_inicial' => $_POST['data_inicial'] ?? '',
    'data_final' => $_POST['data_final'] ?? '',
    'usuario_id' => $_POST['usuario_id'] ?? '',
    'setor' => $_POST['setor'] ?? '',
];

$tiposRelatorios = [
    'emprestimos_abertos' => [
        'icone' => '📦',
        'titulo' => 'Ferramentas Emprestadas',
        'descricao' => 'Lista completa das ferramentas em posse dos usuários.',
    ],
    'historico_periodo' => [
        'icone' => '📘',
        'titulo' => 'Histórico de Empréstimos',
        'descricao' => 'Movimentações realizadas no período selecionado.',
    ],
    'agenda_calibracao' => [
        'icone' => '📅',
        'titulo' => 'Agenda de Calibração',
        'descricao' => 'Ferramentas com calibração agendada.',
    ],
    'inventario_geral' => [
        'icone' => '🗂️',
        'titulo' => 'Inventário Geral',
        'descricao' => 'Posição atual do estoque e disponibilidade.',
    ],
];

$stats = [
    'emprestados' => 0,
    'devolvidos_mes' => 0,
    'calibracoes_pendentes' => 0,
    'catalogadas' => 0,
];
$usuariosLista = [];
$setoresLista = [];

try {
    $stats['emprestados'] = (int)($pdo->query("SELECT COUNT(*) FROM emprestimos WHERE status = 'Emprestado'")->fetchColumn() ?: 0);
    $stats['devolvidos_mes'] = (int)($pdo->query("SELECT COUNT(*) FROM emprestimos WHERE status = 'Devolvido' AND data_retorno IS NOT NULL AND MONTH(data_retorno) = MONTH(CURDATE()) AND YEAR(data_retorno) = YEAR(CURDATE())")->fetchColumn() ?: 0);
    $stats['calibracoes_pendentes'] = (int)($pdo->query("SELECT COUNT(*) FROM calibracoes WHERE status IN ('Vencida','Próxima')")->fetchColumn() ?: 0);
    $stats['catalogadas'] = (int)($pdo->query('SELECT COUNT(*) FROM ferramentas')->fetchColumn() ?: 0);

    $usuariosLista = $pdo->query('SELECT id, CONCAT(nome, " ", sobrenome) AS nome_completo, setor FROM usuarios ORDER BY nome, sobrenome')->fetchAll();
    $setoresLista = $pdo->query("SELECT DISTINCT setor FROM usuarios WHERE setor IS NOT NULL AND setor <> '' ORDER BY setor")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $erro = 'Não foi possível carregar os dados de apoio. Tente novamente mais tarde.';
}

include __DIR__ . '/inc/header.php';
include __DIR__ . '/inc/sidebar.php';
?>
<div class="content-area relatorios-page">
    <div class="page-header">
        <div>
            <h1>Relatórios</h1>
            <span>Gere relatórios detalhados em PDF</span>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-success rounded-3 py-2 px-3"><?= htmlspecialchars($mensagem); ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alert alert-danger rounded-3 py-2 px-3"><?= htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div>
                <span>Ferramentas Emprestadas</span>
                <strong><?= number_format($stats['emprestados'], 0, ',', '.'); ?></strong>
            </div>
            <div class="stat-icon">📦</div>
        </div>
        <div class="stat-card">
            <div>
                <span>Devoluções neste mês</span>
                <strong><?= number_format($stats['devolvidos_mes'], 0, ',', '.'); ?></strong>
            </div>
            <div class="stat-icon">↩️</div>
        </div>
        <div class="stat-card">
            <div>
                <span>Calibrações pendentes</span>
                <strong><?= number_format($stats['calibracoes_pendentes'], 0, ',', '.'); ?></strong>
            </div>
            <div class="stat-icon">⏱️</div>
        </div>
        <div class="stat-card">
            <div>
                <span>Ferramentas catalogadas</span>
                <strong><?= number_format($stats['catalogadas'], 0, ',', '.'); ?></strong>
            </div>
            <div class="stat-icon">🗂️</div>
        </div>
    </div>

    <div class="relatorios-grid">
        <section class="card-modern">
            <h2>Configurar Relatório</h2>
            <p>Selecione os filtros desejados para gerar o arquivo em PDF</p>
            <form class="relatorio-form" method="POST" action="relatorio_pdf.php" target="_blank" rel="noopener">
                <div class="form-group">
                    <label>Tipo de Relatório</label>
                    <select class="form-control-modern" name="tipo_relatorio" required>
                        <?php foreach ($tiposRelatorios as $chave => $tipo): ?>
                            <option value="<?= htmlspecialchars($chave); ?>" <?= $formData['tipo_relatorio'] === $chave ? 'selected' : ''; ?>><?= htmlspecialchars($tipo['titulo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Data Inicial</label>
                        <input type="date" class="form-control-modern" name="data_inicial" value="<?= htmlspecialchars($formData['data_inicial']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Data Final</label>
                        <input type="date" class="form-control-modern" name="data_final" value="<?= htmlspecialchars($formData['data_final']); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Usuário</label>
                        <select class="form-control-modern" name="usuario_id">
                            <option value="">Todos os usuários</option>
                            <?php foreach ($usuariosLista as $usuario): ?>
                                <option value="<?= (int)$usuario['id']; ?>" <?= $formData['usuario_id'] == $usuario['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($usuario['nome_completo']); ?><?= $usuario['setor'] ? ' - ' . htmlspecialchars($usuario['setor']) : ''; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Setor</label>
                        <select class="form-control-modern" name="setor">
                            <option value="">Todos os setores</option>
                            <?php foreach ($setoresLista as $setor): ?>
                                <option value="<?= htmlspecialchars($setor); ?>" <?= $formData['setor'] === $setor ? 'selected' : ''; ?>><?= htmlspecialchars($setor); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-primary-modern">📄 Gerar Relatório em PDF</button>
                <small class="pdf-hint">O PDF será aberto em uma nova aba para download/impressão.</small>
            </form>
        </section>

        <aside class="side-info">
            <div class="card-modern">
                <h3>Tipos de Relatórios</h3>
                <ul>
                    <?php foreach ($tiposRelatorios as $tipo): ?>
                        <li>
                            <span><?= $tipo['icone']; ?></span>
                            <div>
                                <strong><?= htmlspecialchars($tipo['titulo']); ?></strong>
                                <small><?= htmlspecialchars($tipo['descricao']); ?></small>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="card-modern">
                <h3>Formato do Relatório</h3>
                <div class="pdf-info">
                    <span>📑</span>
                    <div>
                        <strong>PDF</strong>
                        <small>Arquivo preparado para impressão</small>
                    </div>
                </div>
                <ul class="pdf-tips">
                    <li>Aplica filtros por usuário, setor ou período.</li>
                    <li>Perfeito para auditorias internas e apresentações.</li>
                </ul>
            </div>
        </aside>
    </div>
</div>

<style>
    .relatorios-page { padding-top: 1rem; }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
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
    .stat-icon { font-size: 1.8rem; }
    .relatorios-grid {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(320px, 0.9fr);
        gap: 1.5rem;
        align-items: start;
    }
    .card-modern {
        border: 1px solid #E5E7EB;
        border-radius: 1rem;
        background: #FFF;
        padding: 1.5rem;
    }
    .card-modern h2 { margin-bottom: 0.2rem; }
    .card-modern p { color: #6B7280; margin-bottom: 1rem; }
    .relatorio-form { display: flex; flex-direction: column; gap: 1rem; }
    .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
    .form-group { display: flex; flex-direction: column; gap: 0.4rem; }
    .form-control-modern {
        border: 1px solid #CBD5F5;
        border-radius: 0.75rem;
        padding: 0.65rem 0.9rem;
        font-family: inherit;
    }
    .btn-primary-modern {
        border: none;
        border-radius: 0.85rem;
        padding: 0.7rem 1.4rem;
        background: #1747b7;
        color: #FFF;
        font-weight: 600;
        align-self: flex-start;
        cursor: pointer;
    }
    .pdf-hint {
        display: block;
        margin-top: 0.5rem;
        color: #6B7280;
        font-size: 0.85rem;
    }
    .side-info .card-modern ul {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 0.9rem;
    }
    .side-info li { display: flex; gap: 0.6rem; align-items: flex-start; }
    .side-info li span { font-size: 1.3rem; }
    .side-info strong { display: block; }
    .pdf-info { display: flex; gap: 0.6rem; align-items: center; }
    .pdf-info span { font-size: 1.4rem; }
    .pdf-info small { color: #6B7280; }
    .pdf-tips {
        list-style: disc;
        margin: 1rem 0 0 1.1rem;
        color: #6B7280;
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
        font-size: 0.9rem;
    }
    @media (max-width: 992px) {
        .relatorios-grid { grid-template-columns: 1fr; }
    }
</style>
<?php include __DIR__ . '/inc/footer.php'; ?>
