<?php
require_once __DIR__ . '/inc/auth.php';
exigirPermissaoPagina('dashboard');
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/vendor/phpqrcode/qrlib.php';

$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    http_response_code(400);
    exit('ID inválido');
}

$stmt = $pdo->prepare('SELECT login, nome, sobrenome, email, matricula, setor, perfil, qr_token FROM usuarios WHERE id = :id');
$stmt->execute([':id' => $userId]);
$usuario = $stmt->fetch();
if (!$usuario) {
    http_response_code(404);
    exit('Usuário não encontrado');
}

$token = $usuario['qr_token'] ?? '';
if (empty($token)) {
    $token = bin2hex(random_bytes(16));
    $update = $pdo->prepare('UPDATE usuarios SET qr_token = :token WHERE id = :id');
    $update->execute([
        ':token' => $token,
        ':id' => $userId,
    ]);
}

$dadosQr = json_encode([
    'login' => $usuario['login'],
    'nome' => trim($usuario['nome'] . ' ' . $usuario['sobrenome']),
    'email' => $usuario['email'],
    'matricula' => $usuario['matricula'],
    'setor' => $usuario['setor'],
    'perfil' => $usuario['perfil'],
    'token' => $token,
    'sistema' => 'JOMAGA'
], JSON_UNESCAPED_UNICODE);

$nivelCorrecao = QR_ECLEVEL_M;
$tamanho = 6;
$margem = 2;

header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');
QRcode::png($dadosQr, null, $nivelCorrecao, $tamanho, $margem);
exit;
?>
