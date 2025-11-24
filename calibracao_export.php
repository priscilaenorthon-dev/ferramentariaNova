<?php
require_once __DIR__ . '/inc/auth.php';
exigirPermissaoPagina('calibracao');
require_once __DIR__ . '/inc/db.php';

$tipo = strtolower($_POST['tipo'] ?? $_GET['tipo'] ?? 'excel');
if (!in_array($tipo, ['excel', 'pdf'], true)) {
    $tipo = 'excel';
}

function formatarDataBr(?string $data): string
{
    if (empty($data) || $data === '0000-00-00') {
        return '-';
    }
    try {
        return (new DateTime($data))->format('d/m/Y');
    } catch (Exception $e) {
        return '-';
    }
}

try {
    $stmt = $pdo->query("SELECT f.codigo, f.descricao, c.ultima_calibracao, c.proxima_calibracao, c.status,
            DATEDIFF(c.proxima_calibracao, CURDATE()) AS dias_restantes
        FROM calibracoes c
        INNER JOIN ferramentas f ON f.id = c.ferramenta_id
        ORDER BY c.status DESC, c.proxima_calibracao ASC");
    $calibracoes = $stmt->fetchAll();
} catch (PDOException $e) {
    $calibracoes = [];
}

if ($tipo === 'pdf') {
    require_once __DIR__ . '/vendor/fpdf/fpdf.php';
    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->SetTitle('Controle de Calibração');
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, utf8_decode('Sistema JOMAGA - Controle de Calibração'), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 6, utf8_decode('Gerado em ' . formatarDataBr(date('Y-m-d'))), 0, 1, 'C');
    $pdf->Ln(4);

    $colunas = [
        ['label' => 'Código', 'campo' => 'codigo', 'width' => 30],
        ['label' => 'Ferramenta', 'campo' => 'descricao', 'width' => 85],
        ['label' => 'Última', 'campo' => 'ultima_calibracao', 'width' => 30],
        ['label' => 'Próxima', 'campo' => 'proxima_calibracao', 'width' => 30],
        ['label' => 'Dias', 'campo' => 'dias_restantes', 'width' => 20],
        ['label' => 'Status', 'campo' => 'status', 'width' => 35],
    ];
    $pdf->SetFont('Arial', 'B', 10);
    foreach ($colunas as $coluna) {
        $pdf->Cell($coluna['width'], 8, utf8_decode($coluna['label']), 1, 0, 'L');
    }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 9);

    if (empty($calibracoes)) {
        $totalWidth = array_sum(array_column($colunas, 'width'));
        $pdf->Cell($totalWidth, 8, utf8_decode('Nenhuma calibração encontrada.'), 1, 1, 'C');
    } else {
        foreach ($calibracoes as $item) {
            foreach ($colunas as $coluna) {
                $valor = $item[$coluna['campo']] ?? '';
                if (in_array($coluna['campo'], ['ultima_calibracao', 'proxima_calibracao'], true)) {
                    $valor = formatarDataBr($valor);
                }
                if ($coluna['campo'] === 'dias_restantes') {
                    if ($valor === null || $valor === '') {
                        $valor = '-';
                    } elseif ((int)$valor < 0) {
                        $valor = abs((int)$valor) . ' atrasado';
                    }
                }
                $pdf->Cell($coluna['width'], 7, utf8_decode((string)$valor), 1, 0, 'L');
            }
            $pdf->Ln();
        }
    }

    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(0, 6, utf8_decode('Documento gerado automaticamente pelo Sistema JOMAGA.'), 0, 1, 'C');
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="calibracao.pdf"');
    $pdf->Output('I', 'calibracao.pdf');
    exit;
}

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="calibracao.xls"');
echo "sep=;\r\n";
echo "Código;Ferramenta;Última Calibração;Próxima Calibração;Dias Restantes;Status\r\n";
foreach ($calibracoes as $item) {
    $ultima = formatarDataBr($item['ultima_calibracao'] ?? null);
    $proxima = formatarDataBr($item['proxima_calibracao'] ?? null);
    $dias = $item['dias_restantes'];
    if ($dias === null || $dias === '') {
        $dias = '-';
    } elseif ((int)$dias < 0) {
        $dias = abs((int)$dias) . ' atrasado';
    }
    $linha = [
        $item['codigo'] ?? '',
        $item['descricao'] ?? '',
        $ultima,
        $proxima,
        $dias,
        $item['status'] ?? '',
    ];
    echo implode(';', array_map(fn($valor) => str_replace([';', "\r", "\n"], ' ', $valor), $linha)) . "\r\n";
}
exit;
?>
