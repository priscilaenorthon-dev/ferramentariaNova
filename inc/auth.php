<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

function usuarioLogado(): array
{
    return [
        'id' => $_SESSION['usuario_id'] ?? null,
        'nome' => $_SESSION['usuario_nome'] ?? '',
        'perfil' => $_SESSION['usuario_perfil'] ?? '',
    ];
}

function normalizarPerfil(?string $perfil): ?string
{
    if ($perfil === null) {
        return null;
    }
    $map = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
        'é' => 'e', 'ê' => 'e',
        'í' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ü' => 'u',
        'ç' => 'c',
    ];
    $perfil = mb_strtolower($perfil, 'UTF-8');
    $perfil = strtr($perfil, $map);
    return $perfil;
}

function usuarioTemPerfil($perfis): bool
{
    $perfis = (array) $perfis;
    $perfilAtual = normalizarPerfil($_SESSION['usuario_perfil'] ?? null);
    if ($perfilAtual === null) {
        return false;
    }
    foreach ($perfis as $perfil) {
        if ($perfilAtual === normalizarPerfil($perfil)) {
            return true;
        }
    }
    return false;
}

function mapaPermissoesPorPagina(): array
{
    return [
        'dashboard' => ['Administrador', 'Operador', 'Usuário'],
        'ferramentas' => ['Administrador', 'Operador'],
        'emprestimos' => ['Administrador', 'Operador'],
        'devolucoes' => ['Administrador', 'Operador'],
        'inventario' => ['Administrador', 'Operador'],
        'calibracao' => ['Administrador', 'Operador'],
        'relatorios' => ['Administrador', 'Operador'],
        'historico' => ['Administrador', 'Operador'],
        'auditoria' => ['Administrador'],
        'usuarios' => ['Administrador'],
        'classes' => ['Administrador'],
        'modelos' => ['Administrador'],
    ];
}

function podeAcessarPagina(string $chavePagina): bool
{
    $mapa = mapaPermissoesPorPagina();
    $perfis = $mapa[$chavePagina] ?? ['Administrador'];
    return usuarioTemPerfil($perfis);
}

function exigirPermissaoPagina(string $chavePagina, string $redirect = 'dashboard.php'): void
{
    if (!podeAcessarPagina($chavePagina)) {
        header("Location: {$redirect}");
        exit;
    }
}
