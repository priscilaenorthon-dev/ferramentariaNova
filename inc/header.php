<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$paginaTitulo = $paginaTitulo ?? 'Sistema JOMAGA';
$usuarioNome = $_SESSION['usuario_nome'] ?? 'Admin Teste';
$usuarioPerfil = $_SESSION['usuario_perfil'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($paginaTitulo); ?> | Sistema JOMAGA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <style>
        :root {
            --sidebar-width: 240px;
            --bg-body: #F9FAFB;
            --bg-sidebar: #F3F4F6;
            --text-dark: #111827;
            --text-muted: #6B7280;
            --brand-blue: #2563EB;
            --brand-blue-light: #EFF6FF;
            --card-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Roboto', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-body);
            margin: 0;
            color: var(--text-dark);
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .app-layout {
            display: flex;
            min-height: 100vh;
            background: var(--bg-body);
        }

        .sidebar-modern {
            width: var(--sidebar-width);
            background: var(--bg-sidebar);
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #E5E7EB;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .sidebar-logo span {
            font-size: 1.3rem;
        }

        .sidebar-label {
            color: var(--text-muted);
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            margin-bottom: 0.75rem;
        }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            flex: 1;
        }

        .sidebar-menu a {
            padding: 0.65rem 0.9rem;
            border-radius: 0.8rem;
            color: var(--text-dark);
            font-weight: 500;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .menu-icon {
            width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
        }

        .sidebar-menu a:hover {
            background: rgba(37, 99, 235, 0.08);
        }

        .sidebar-menu a.active {
            background: var(--brand-blue-light);
            color: var(--brand-blue);
            font-weight: 700;
        }

        .sidebar-user {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .sidebar-user-card {
            background: #FFF;
            border-radius: 1rem;
            padding: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.9rem;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
        }

        .sidebar-user-card .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--brand-blue-light);
            color: var(--brand-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .sidebar-user-card span {
            display: block;
            font-weight: 600;
        }

        .sidebar-user-card small {
            display: block;
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        .main-area {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .topbar-modern {
            display: none;
        }

        .page-content {
            padding: 1.5rem;
            flex: 1;
            background: var(--bg-body);
        }

        .content-area {
            background: transparent;
        }

        .card-modern {
            background: #FFF;
            border-radius: 1.3rem;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
        }

        .page-header {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            margin-bottom: 1.75rem;
        }

        .page-header h2,
        .page-header h1,
        .page-header .h4 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }

        .page-header span,
        .page-header p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin: 0;
        }

        .page-header.with-actions {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .page-header.with-actions > div:first-child {
            min-width: 220px;
        }

        .page-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
        }

        .page-actions .form-control,
        .page-actions select,
        .page-actions button {
            min-width: 220px;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .kpi-card {
            background: #FFF;
            border-radius: 1.3rem;
            padding: 1.4rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 30px 60px rgba(15, 23, 42, 0.12);
        }

        .kpi-card h3 {
            font-size: 1rem;
            color: var(--text-muted);
            margin-bottom: 0.6rem;
        }

        .kpi-card strong {
            font-size: 2.15rem;
            font-weight: 700;
            display: block;
            margin-bottom: 0.2rem;
        }

        .kpi-card span {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .kpi-card .kpi-icon {
            width: 56px;
            height: 56px;
            border-radius: 18px;
            background: var(--brand-blue-light);
            color: var(--brand-blue);
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .grid-two {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .grid-two-bottom {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .card {
            background: #FFF;
            border-radius: 1.3rem;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
        }

        .card h3 {
            font-size: 1.15rem;
            margin-bottom: 0.25rem;
        }

        .card p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 1.2rem;
        }

        .tool-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }

        .tool-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.6rem 0.4rem;
            border-bottom: 1px solid #F3F4F6;
        }

        .tool-info {
            display: flex;
            align-items: center;
            gap: 0.9rem;
        }

        .tool-position {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--brand-blue-light);
            color: var(--brand-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .tool-details span {
            display: block;
            font-weight: 600;
        }

        .tool-details small {
            color: var(--text-muted);
            display: block;
            font-size: 0.85rem;
        }

        .tool-usage {
            text-align: right;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        canvas {
            width: 100% !important;
            height: 320px !important;
        }

        .btn-logout {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.75rem;
            padding: 0.45rem 0.85rem;
            background: rgba(37, 99, 235, 0.1);
            color: var(--brand-blue);
            font-weight: 600;
            font-size: 0.85rem;
        }

        .btn-logout:hover {
            background: rgba(37, 99, 235, 0.2);
        }

        @media (max-width: 1024px) {
            .grid-two {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            :root {
                --sidebar-width: 220px;
            }

            .sidebar-menu a {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 640px) {
            .app-layout {
                flex-direction: column;
            }

            .sidebar-modern {
                position: sticky;
                top: 0;
                z-index: 10;
                width: 100%;
                flex-direction: column;
                height: auto;
                padding: 1rem;
            }

            .sidebar-menu {
                flex-direction: row;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .sidebar-menu a {
                flex: 1 1 calc(50% - 0.5rem);
                text-align: center;
                font-size: 0.85rem;
                justify-content: center;
            }

            .sidebar-user {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }

            .main-area {
                margin-left: 0;
            }

            .page-content {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
<div class="app-layout">
