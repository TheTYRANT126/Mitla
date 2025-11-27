<?php

if (!isset($auth)) {
    require_once __DIR__ . '/../../classes/Auth.php';
    $auth = new Auth();
}

$userName = $_SESSION['user_name'] ?? 'Usuario';
$userRole = $_SESSION['user_role'] ?? 'guia';
$roleLabel = $userRole === 'admin' ? 'Administrador' : 'Guía';
$logoPath = ASSETS_PATH . '/img/logo.png';
$logoUrl = ASSETS_URL . '/img/logo.png';
?>
<!-- Navegación -->
<nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top">
    <div class="container-fluid">
        <!-- Botón toggle para sidebar en móvil -->
        <button class="navbar-toggler me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fas fa-bars"></i>
        </button>

        <a class="navbar-brand" href="<?php echo SITE_URL; ?>/admin/dashboard.php">
            <?php if (file_exists($logoPath)): ?>
                <img src="<?php echo $logoUrl; ?>" alt="<?php echo SITE_NAME; ?>" class="navbar-logo">
            <?php else: ?>
                <?php echo SITE_NAME; ?>
            <?php endif; ?>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo SITE_URL; ?>">Ir al Sitio</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo SITE_URL; ?>/admin/configuracion/cambiar-password.php">Cambiar Contraseña</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="<?php echo SITE_URL; ?>/admin/logout.php">Cerrar Sesión</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
