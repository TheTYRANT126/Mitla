<?php


$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

function isActive($page, $dir = null) {
    global $currentPage, $currentDir;
    if ($dir) {
        return $currentDir === $dir ? 'active' : '';
    }
    return $currentPage === $page ? 'active' : '';
}

$userRole = $_SESSION['user_role'] ?? 'guia';
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        
        <?php if ($userRole === 'admin'): ?>
        <!-- MENÚ ADMINISTRADOR -->
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('dashboard.php'); ?>" 
                   href="<?php echo SITE_URL; ?>/admin/dashboard.php">
                    <i class="fas fa-chart-line"></i>
                    Dashboard
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('', 'reservaciones'); ?>" 
                   href="<?php echo SITE_URL; ?>/admin/reservaciones/">
                    <i class="fas fa-calendar-check"></i>
                    Reservaciones
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('calendario.php'); ?>" 
                   href="<?php echo SITE_URL; ?>/admin/calendario.php">
                    <i class="fas fa-calendar-alt"></i>
                    Calendario
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('', 'guias'); ?>" 
                   href="<?php echo SITE_URL; ?>/admin/guias/">
                    <i class="fas fa-user-tie"></i>
                    Guías
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('', 'paquetes'); ?>" 
                   href="<?php echo SITE_URL; ?>/admin/paquetes/">
                    <i class="fas fa-box"></i>
                    Paquetes
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('reportes.php'); ?>" 
                   href="<?php echo SITE_URL; ?>/admin/reportes.php">
                    <i class="fas fa-file-pdf"></i>
                    Reportes
                </a>
            </li>
        </ul>
        
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Configuración</span>
        </h6>
        
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('', 'logs'); ?>" 
                   href="<?php echo SITE_URL; ?>/admin/logs/">
                    <i class="fas fa-history"></i>
                    Registro de Actividad
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('', 'configuracion'); ?>" 
                   href="<?php echo SITE_URL; ?>/admin/configuracion/">
                    <i class="fas fa-cog"></i>
                    Configuración
                </a>
            </li>
        </ul>
        
        <?php else: ?>
        <!-- MENÚ GUÍA -->
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('mis-tours.php'); ?>" 
                   href="<?php echo SITE_URL; ?>/admin/guia/mis-tours.php">
                    <i class="fas fa-calendar-check"></i>
                    Mis Tours
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('perfil.php'); ?>" 
                   href="<?php echo SITE_URL; ?>/admin/guia/perfil.php">
                    <i class="fas fa-user"></i>
                    Mi Perfil
                </a>
            </li>
        </ul>
        <?php endif; ?>
        
    </div>
</nav>

<style>
.sidebar {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    z-index: 100;
    padding: 48px 0 0;
    box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
}

.sidebar-sticky {
    position: relative;
    top: 0;
    height: calc(100vh - 48px);
    padding-top: .5rem;
    overflow-x: hidden;
    overflow-y: auto;
}

.sidebar .nav-link {
    font-weight: 500;
    color: #333;
    padding: 12px 20px;
    transition: all 0.3s ease;
}

.sidebar .nav-link:hover {
    color: #0066cc;
    background-color: #f8f9fa;
}

.sidebar .nav-link.active {
    color: #0066cc;
    background-color: #e7f3ff;
    border-left: 3px solid #0066cc;
}

.sidebar .nav-link i {
    margin-right: 10px;
    width: 20px;
    text-align: center;
}

.sidebar-heading {
    font-size: .75rem;
    text-transform: uppercase;
}

@media (max-width: 767.98px) {
    .sidebar {
        top: 56px;
    }
}
</style>