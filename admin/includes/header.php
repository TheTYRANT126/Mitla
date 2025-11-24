<?php

if (!isset($auth)) {
    require_once __DIR__ . '/../../classes/Auth.php';
    $auth = new Auth();
}

$userName = $_SESSION['user_name'] ?? 'Usuario';
$userRole = $_SESSION['user_role'] ?? 'guia';
?>
<nav class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
    <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="<?php echo SITE_URL; ?>/admin/dashboard.php">
        <i class="fas fa-mountain"></i> 
        Mitla Tours Admin
    </a>
    
    <button class="navbar-toggler position-absolute d-md-none collapsed" 
            type="button" 
            data-bs-toggle="collapse" 
            data-bs-target="#sidebarMenu">
        <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="navbar-nav">
        <div class="nav-item text-nowrap">
            <div class="dropdown">
                <button class="btn btn-dark dropdown-toggle" 
                        type="button" 
                        id="userDropdown" 
                        data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle"></i> 
                    <?php echo htmlspecialchars($userName); ?>
                    <?php if ($userRole === 'admin'): ?>
                        <span class="badge bg-danger">Admin</span>
                    <?php else: ?>
                        <span class="badge bg-info">Guía</span>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="<?php echo SITE_URL; ?>">
                            <i class="fas fa-home"></i> Ir al Sitio
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/configuracion/cambiar-password.php">
                            <i class="fas fa-key"></i> Cambiar Contraseña
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="<?php echo SITE_URL; ?>/admin/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>