<?php
$paginaAtual = basename($_SERVER['PHP_SELF']);
$usuarioNome = $usuarioNome ?? ($_SESSION['usuario_nome'] ?? 'Admin Teste');
$usuarioPerfil = $usuarioPerfil ?? ($_SESSION['usuario_perfil'] ?? 'Admin');

$menuItens = [
    ['label' => 'Dashboard', 'arquivo' => 'dashboard.php', 'icon' => '📊', 'permissao' => 'dashboard'],
    ['label' => 'Ferramentas', 'arquivo' => 'ferramentas.php', 'icon' => '🛠️', 'permissao' => 'ferramentas'],
    ['label' => 'Empréstimos', 'arquivo' => 'emprestimos.php', 'icon' => '🔁', 'permissao' => 'emprestimos'],
    ['label' => 'Devoluções', 'arquivo' => 'devolucoes.php', 'icon' => '↩️', 'permissao' => 'devolucoes'],
    ['label' => 'Inventário', 'arquivo' => 'inventario.php', 'icon' => '📦', 'permissao' => 'inventario'],
    ['label' => 'Calibração', 'arquivo' => 'calibracao.php', 'icon' => '⏱️', 'permissao' => 'calibracao'],
    ['label' => 'Relatórios', 'arquivo' => 'relatorios.php', 'icon' => '📑', 'permissao' => 'relatorios'],
    ['label' => 'Histórico e Auditoria', 'arquivo' => 'historico.php', 'icon' => '📜', 'permissao' => 'historico'],
    ['label' => 'Usuários', 'arquivo' => 'usuarios.php', 'icon' => '👥', 'permissao' => 'usuarios'],
    ['label' => 'Classes', 'arquivo' => 'classes.php', 'icon' => '📚', 'permissao' => 'classes'],
    ['label' => 'Modelos', 'arquivo' => 'modelos.php', 'icon' => '🧩', 'permissao' => 'modelos'],
];
?>
<aside class="sidebar-modern">
    <div class="sidebar-logo">
        <span>🛠️</span>
        <strong>Sistema JOMAGA</strong>
    </div>
    <p class="sidebar-label">MENU PRINCIPAL</p>
    <nav class="sidebar-menu">
        <?php foreach ($menuItens as $item) :
            if (!podeAcessarPagina($item['permissao'])) {
                continue;
            }
            $ativo = $paginaAtual === $item['arquivo'] ? 'active' : '';
            ?>
            <a class="<?= $ativo; ?>" href="<?= htmlspecialchars($item['arquivo']); ?>">
                <span class="menu-icon"><?= htmlspecialchars($item['icon']); ?></span>
                <?= htmlspecialchars($item['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-user">
        <div class="sidebar-user-card">
            <div class="avatar">AT</div>
            <div>
                <span><?= htmlspecialchars($usuarioNome); ?></span>
                <small><?= htmlspecialchars($usuarioPerfil); ?></small>
            </div>
        </div>
        <a class="btn-logout" href="logout.php">Sair</a>
    </div>
</aside>
<div class="main-area">
    <div class="page-content">
