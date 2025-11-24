<?php
session_start();
require_once __DIR__ . '/inc/db.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if ($login === '' || $senha === '') {
        $erro = 'Informe o login e a senha.';
    } else {
        $stmt = $pdo->prepare('SELECT id, nome, sobrenome, perfil, senha_hash FROM usuarios WHERE login = :login LIMIT 1');
        $stmt->execute([':login' => $login]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = trim($usuario['nome'] . ' ' . $usuario['sobrenome']);
            $_SESSION['usuario_perfil'] = $usuario['perfil'];
            header('Location: dashboard.php');
            exit;
        }

        $erro = 'Login ou senha inválidos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JOMAGA – Centro de Ferramentas | Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #020617;
            --brand-blue: #1D4ED8;
            --brand-blue-light: #2563EB;
            --gray-light: #F9FAFB;
            --gray-medium: #6B7280;
            --gray-dark: #111827;
            --error-bg: #FEE2E2;
            --error-text: #991B1B;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: #FFF;
        }
        .login-wrapper {
            width: min(1100px, 100%);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            background: transparent;
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 30px 70px rgba(2, 6, 23, 0.7);
        }
        .info-panel {
            background: linear-gradient(180deg, var(--brand-blue-light), var(--brand-blue));
            padding: 3rem;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        .brand { display: flex; align-items: center; gap: 1rem; }
        .brand-icon {
            width: 54px; height: 54px; border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
        }
        .brand-text span {
            display: block; font-size: 0.85rem; letter-spacing: 2px;
        }
        .brand-text strong { font-size: 1.8rem; font-weight: 600; display: block; }
        .info-panel h2 {
            font-size: 1rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.1em; color: #93C5FD;
        }
        .info-panel p { color: rgba(255, 255, 255, 0.9); line-height: 1.6; }
        .benefits { list-style: none; display: flex; flex-direction: column; gap: 0.9rem; }
        .benefits li { display: flex; align-items: center; gap: 0.5rem; font-weight: 500; }
        .secure-access { margin-top: auto; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.12em; }
        .secure-access p { margin-top: 0.5rem; text-transform: none; font-size: 0.75rem; color: rgba(255,255,255,0.85); }
        .form-panel {
            background: var(--gray-light);
            padding: 3rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            color: var(--gray-dark);
        }
        .form-icon {
            width: 48px; height: 48px; border-radius: 50%;
            background: rgba(29, 78, 216, 0.1);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; color: var(--brand-blue);
        }
        .form-card {
            background: #FFF;
            border-radius: 18px;
            padding: 2rem;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        .error-message {
            background: var(--error-bg);
            color: var(--error-text);
            padding: 0.85rem 1rem;
            border-radius: 10px;
            font-weight: 600;
            border: 1px solid rgba(153, 27, 27, 0.25);
        }
        label { font-weight: 600; color: var(--gray-dark); font-size: 0.95rem; }
        input[type="text"], input[type="password"] {
            width: 100%; padding: 0.85rem 1rem; border-radius: 12px;
            border: 1px solid #E5E7EB; background: #F9FAFB; font-size: 1rem; margin-top: 0.4rem;
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        input:focus { outline: none; border-color: var(--brand-blue); box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.2); }
        .btn-submit {
            border: none; border-radius: 12px; background: var(--brand-blue);
            color: #FFF; padding: 0.95rem 1rem; font-size: 1rem; font-weight: 700;
            cursor: pointer; transition: background .2s ease; width: 100%;
        }
        .btn-submit:hover { background: #1739a6; }
        .form-footer { font-size: 0.85rem; color: var(--gray-medium); line-height: 1.5; text-align: center; }
        .form-footer span { display: block; font-size: 0.75rem; color: #9CA3AF; }
        @media (max-width: 768px) {
            body { padding: 1rem; }
            .info-panel, .form-panel { padding: 2.5rem; }
        }
    </style>
</head>
<body>
<div class="login-wrapper">
    <section class="info-panel">
        <div class="brand">
            <div class="brand-icon">🔧</div>
            <div class="brand-text">
                <span>JOMAGA</span>
                <strong>Centro de Ferramentas</strong>
            </div>
        </div>
        <div>
            <h2>Controle total de empréstimos em um só lugar</h2>
            <p>Organize ferramentas, acompanhe prazos e mantenha a equipe alinhada com um sistema feito para o dia a dia da operação.</p>
        </div>
        <ul class="benefits">
            <li>Dashboard com indicadores em tempo real</li>
            <li>Fluxo de empréstimo guiado e intuitivo</li>
            <li>Termo de responsabilidade gerado automaticamente</li>
        </ul>
        <div class="secure-access">
            <strong>ACESSO SEGURO</strong>
            <p>Uso exclusivo de operadores autorizados. Dados protegidos com autenticação individual.</p>
        </div>
    </section>
    <section class="form-panel">
        <div class="form-icon">→</div>
        <div>
            <h3>Bem-vindo de volta</h3>
            <p>Faça login com suas credenciais corporativas para acessar o Sistema JOMAGA.</p>
        </div>
        <div class="form-card">
            <?php if (!empty($erro)) : ?>
                <div class="error-message"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="login">Username</label>
                    <input type="text" id="login" name="login" placeholder="Digite seu username" required>
                </div>
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" placeholder="Digite sua senha" required>
                </div>
                <button type="submit" class="btn-submit">Entrar</button>
            </form>
            <div class="form-footer">
                Precisa de ajuda? Entre em contato com o time de suporte interno.
                <span>O acesso é exclusivo para colaboradores autorizados.</span>
            </div>
        </div>
    </section>
</div>
</body>
</html>
