<?php
require_once __DIR__ . '/inc/auth.php';
exigirPermissaoPagina('relatorios');
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/vendor/fpdf/fpdf.php';

$tipo = $_POST['tipo_relatorio'] ?? '';
if ($tipo === '') {
    header('Location: relatorios.php');
    exit;
}

$formData = [
    'data_inicial' => $_POST['data_inicial'] ?? '',
    'data_final' => $_POST['data_final'] ?? '',
    'usuario_id' => $_POST['usuario_id'] ?? '',
    'setor' => $_POST['setor'] ?? '',
];

$tiposRelatorios = [
    'emprestimos_abertos' => 'Ferramentas Emprestadas',
    'historico_periodo' => 'Histórico de Empréstimos',
    'agenda_calibracao' => 'Agenda de Calibração',
    'inventario_geral' => 'Inventário Geral',
];

if (!isset($tiposRelatorios[$tipo])) {
    header('Location: relatorios.php');
    exit;
}

function formatarData(?string $data, string $formato = 'd/m/Y') : string
{
    if (empty($data)) {
        return '-';
    }
    try {
        return (new DateTime($data))->format($formato);
    } catch (Exception $e) {
        return '-';
    }
}

function formatarDataHora(?string $data) : string
{
    return formatarData($data, 'd/m/Y H:i');
}

function filtroPeriodo(array $formData): string
{
    $inicio = $formData['data_inicial'] ? formatarData($formData['data_inicial']) : 'Sem início';
    $fim = $formData['data_final'] ? formatarData($formData['data_final']) : 'Sem fim';
    return "Período: {$inicio} - {$fim}";
}

$dados = [];
$colunas = [];
$informacoes = [];

try {
    switch ($tipo) {
        case 'emprestimos_abertos':
            $sql = "SELECT f.codigo, f.descricao, CONCAT(u.nome, ' ', u.sobrenome) AS usuario, e.quantidade,
                           e.data_saida, e.data_prevista, CONCAT(op.nome, ' ', op.sobrenome) AS operador
                    FROM emprestimos e
                    INNER JOIN ferramentas f ON f.id = e.ferramenta_id
                    INNER JOIN usuarios u ON u.id = e.usuario_id
                    INNER JOIN usuarios op ON op.id = e.operador_id
                    WHERE e.status = 'Emprestado'";
            $params = [];
            if ($formData['usuario_id'] !== '') {
                $sql .= ' AND e.usuario_id = :usuario';
                $params[':usuario'] = (int)$formData['usuario_id'];
            }
            if ($formData['setor'] !== '') {
                $sql .= ' AND u.setor = :setor';
                $params[':setor'] = $formData['setor'];
            }
            if ($formData['data_inicial'] !== '') {
                $sql .= ' AND e.data_saida >= :inicio';
                $params[':inicio'] = $formData['data_inicial'] . ' 00:00:00';
            }
            if ($formData['data_final'] !== '') {
                $sql .= ' AND e.data_saida <= :fim';
                $params[':fim'] = $formData['data_final'] . ' 23:59:59';
            }
            $sql .= ' ORDER BY e.data_saida DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $dados = $stmt->fetchAll();
            $colunas = [
                ['label' => 'Código', 'campo' => 'codigo', 'width' => 30],
                ['label' => 'Ferramenta', 'campo' => 'descricao', 'width' => 70],
                ['label' => 'Usuário', 'campo' => 'usuario', 'width' => 55],
                ['label' => 'Qtd', 'campo' => 'quantidade', 'width' => 15],
                ['label' => 'Saída', 'campo' => 'data_saida', 'width' => 35, 'format' => 'datahora'],
                ['label' => 'Prevista', 'campo' => 'data_prevista', 'width' => 35, 'format' => 'data'],
                ['label' => 'Operador', 'campo' => 'operador', 'width' => 55],
            ];
            $informacoes[] = filtroPeriodo($formData);
            break;

        case 'historico_periodo':
            $sql = "SELECT h.data_registro, h.acao, h.descricao, f.codigo, f.descricao AS ferramenta,
                           e.quantidade, e.status AS status_emprestimo,
                           CONCAT(u.nome, ' ', u.sobrenome) AS usuario
                    FROM historico h
                    LEFT JOIN emprestimos e ON e.id = h.emprestimo_id
                    LEFT JOIN ferramentas f ON f.id = e.ferramenta_id
                    LEFT JOIN usuarios u ON u.id = e.usuario_id
                    WHERE 1=1";
            $params = [];
            if ($formData['data_inicial'] !== '') {
                $sql .= ' AND h.data_registro >= :inicio';
                $params[':inicio'] = $formData['data_inicial'] . ' 00:00:00';
            }
            if ($formData['data_final'] !== '') {
                $sql .= ' AND h.data_registro <= :fim';
                $params[':fim'] = $formData['data_final'] . ' 23:59:59';
            }
            if ($formData['usuario_id'] !== '') {
                $sql .= ' AND e.usuario_id = :usuario';
                $params[':usuario'] = (int)$formData['usuario_id'];
            }
            $sql .= ' ORDER BY h.data_registro DESC LIMIT 300';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $dados = $stmt->fetchAll();
            $colunas = [
                ['label' => 'Momento', 'campo' => 'data_registro', 'width' => 40, 'format' => 'datahora'],
                ['label' => 'Ação', 'campo' => 'acao', 'width' => 30],
                ['label' => 'Descrição', 'campo' => 'descricao', 'width' => 70],
                ['label' => 'Ferramenta', 'campo' => 'codigo', 'width' => 30],
                ['label' => 'Qtd', 'campo' => 'quantidade', 'width' => 15],
                ['label' => 'Status', 'campo' => 'status_emprestimo', 'width' => 25],
                ['label' => 'Usuário', 'campo' => 'usuario', 'width' => 55],
            ];
            $informacoes[] = filtroPeriodo($formData);
            break;

        case 'agenda_calibracao':
            $sql = "SELECT f.codigo, f.descricao, c.ultima_calibracao, c.proxima_calibracao, c.status
                    FROM calibracoes c
                    INNER JOIN ferramentas f ON f.id = c.ferramenta_id
                    ORDER BY c.proxima_calibracao ASC";
            $stmt = $pdo->query($sql);
            $dados = $stmt->fetchAll();
            $colunas = [
                ['label' => 'Código', 'campo' => 'codigo', 'width' => 30],
                ['label' => 'Ferramenta', 'campo' => 'descricao', 'width' => 80],
                ['label' => 'Última', 'campo' => 'ultima_calibracao', 'width' => 40, 'format' => 'data'],
                ['label' => 'Próxima', 'campo' => 'proxima_calibracao', 'width' => 40, 'format' => 'data'],
                ['label' => 'Status', 'campo' => 'status', 'width' => 40],
            ];
            break;

        case 'inventario_geral':
        default:
            $sql = "SELECT f.codigo, f.descricao, c.nome AS classe, m.nome AS modelo,
                           f.quantidade_total, f.quantidade_disponivel
                    FROM ferramentas f
                    INNER JOIN classes c ON c.id = f.classe_id
                    INNER JOIN modelos m ON m.id = f.modelo_id
                    ORDER BY f.descricao";
            $stmt = $pdo->query($sql);
            $dados = $stmt->fetchAll();
            $colunas = [
                ['label' => 'Código', 'campo' => 'codigo', 'width' => 30],
                ['label' => 'Descrição', 'campo' => 'descricao', 'width' => 80],
                ['label' => 'Classe', 'campo' => 'classe', 'width' => 40],
                ['label' => 'Modelo', 'campo' => 'modelo', 'width' => 40],
                ['label' => 'Total', 'campo' => 'quantidade_total', 'width' => 25],
                ['label' => 'Disponível', 'campo' => 'quantidade_disponivel', 'width' => 25],
            ];
            break;
    }
} catch (PDOException $e) {
    $dados = [];
    $informacoes[] = 'Ocorreu um erro ao consultar o banco de dados.';
}

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetTitle('Relatório - ' . $tiposRelatorios[$tipo]);
$pdf->SetAuthor('Sistema JOMAGA');
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, utf8_decode('Sistema JOMAGA - ' . $tiposRelatorios[$tipo]), 0, 1, 'C');
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 6, utf8_decode('Gerado em ' . formatarDataHora(date('Y-m-d H:i:s'))), 0, 1, 'C');
$pdf->Ln(4);

$pdf->SetFont('Arial', '', 11);
if ($formData['usuario_id'] !== '') {
    $usuarioStmt = $pdo->prepare('SELECT CONCAT(nome, " ", sobrenome) AS nome FROM usuarios WHERE id = :id');
    $usuarioStmt->execute([':id' => (int)$formData['usuario_id']]);
    $usuarioNome = $usuarioStmt->fetchColumn();
    if ($usuarioNome) {
        $informacoes[] = 'Usuário filtrado: ' . $usuarioNome;
    }
}
if ($formData['setor'] !== '') {
    $informacoes[] = 'Setor: ' . $formData['setor'];
}
if (!$informacoes) {
    $informacoes[] = 'Filtros não informados (todos os registros).';
}
foreach ($informacoes as $info) {
    $pdf->Cell(0, 6, utf8_decode($info), 0, 1, 'L');
}
$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 10);
foreach ($colunas as $coluna) {
    $pdf->Cell($coluna['width'], 8, utf8_decode($coluna['label']), 1, 0, 'L');
}
$pdf->Ln();

$pdf->SetFont('Arial', '', 9);
if (empty($dados)) {
    $totalWidth = array_sum(array_column($colunas, 'width'));
    $pdf->Cell($totalWidth, 8, utf8_decode('Nenhum registro encontrado.'), 1, 1, 'C');
} else {
    foreach ($dados as $linha) {
        foreach ($colunas as $coluna) {
            $valor = $linha[$coluna['campo']] ?? '';
            if (($coluna['format'] ?? null) === 'datahora') {
                $valor = formatarDataHora($valor);
            } elseif (($coluna['format'] ?? null) === 'data') {
                $valor = formatarData($valor);
            }
            $pdf->Cell($coluna['width'], 7, utf8_decode((string)$valor), 1, 0, 'L');
        }
        $pdf->Ln();
    }
}

$pdf->Ln(4);
$pdf->SetFont('Arial', 'I', 9);
$pdf->Cell(0, 6, utf8_decode('Documento gerado automaticamente pelo Sistema JOMAGA.'), 0, 1, 'C');

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="relatorio.pdf"');
$pdf->Output('I', 'relatorio.pdf');
exit;
?>
