<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Solufeed</title>

    <!-- Estilos CSS principales -->
    <link rel="stylesheet" href="/solufeed/assets/css/style.css">

    <!-- Chart.js para gráficos -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">🐮</div>
            <h1 class="sidebar-title">Solufeed</h1>
            <p class="sidebar-subtitle">Sistema de Gestión</p>
        </div>

        <nav class="sidebar-menu">
            <ul>
                <li>
                    <a href="/solufeed/admin/dashboard.php">
                        <span class="menu-icono">🏠</span>
                        <span class="menu-texto">Dashboard</span>
                    </a>
                </li>

                <li class="menu-separador"></li>
                <li class="menu-titulo">Configuración</li>

                <li>
                    <a href="/solufeed/admin/insumos/listar.php">
                        <span class="menu-icono">🌾</span>
                        <span class="menu-texto">Insumos</span>
                    </a>
                </li>

                <li>
                    <a href="/solufeed/admin/dietas/listar.php">
                        <span class="menu-icono">📋</span>
                        <span class="menu-texto">Dietas</span>
                    </a>
                </li>

                <li>
                    <a href="/solufeed/admin/lotes/listar.php">
                        <span class="menu-icono">🐮</span>
                        <span class="menu-texto">Lotes</span>
                    </a>
                </li>

                <li class="menu-separador"></li>
                <li class="menu-titulo">Gestión</li>

                <li>
                    <a href="/solufeed/admin/reportes/consumo.php">
                        <span class="menu-icono">📈</span>
                        <span class="menu-texto">Reportes</span>
                    </a>
                </li>
                
                <li class="menu-separador"></li>
                <li class="menu-titulo">Usuario de Campo</li>
                
                <li>
                    <a href="/solufeed/admin/campo/index.php">
                        <span class="menu-icono">👷</span>
                        <span class="menu-texto">Hub de Campo</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">👤</div>
                <div class="user-details">
                    <div class="user-name">Administrador</div>
                    <div class="user-role">Admin</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN WRAPPER -->
    <div class="main-wrapper">
        <header class="top-bar">
            <div class="breadcrumb">
                <?php
                if (isset($page_title)) {
                    echo '<a href="/solufeed/admin/dashboard.php">Inicio</a> / ' . htmlspecialchars($page_title);
                }
                ?>
            </div>
            <div class="top-bar-actions">
                <span><?php echo date('d/m/Y'); ?></span>
            </div>
        </header>

        <main class="contenido-principal">
            <div class="container">