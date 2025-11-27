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

// Función para convertir hex a rgba
function hexToRgba($hex, $alpha = 0.15) {
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "rgba($r, $g, $b, $alpha)";
}

// Verificar si ya existen colores asignados en la sesión
if (!isset($_SESSION['menu_colors'])) {
    // Colores disponibles para el panel
    $availableColors = ['#04aade', '#72368c', '#e2007d', '#3ca139', '#ca45a2', '#0287aa', '#9dc639', '#e65eb0', '#9e156d', '#3ba13b', '#3ba239', '#71378b'];

    // Mezclar los colores de manera aleatoria una vez
    shuffle($availableColors);

    // Asignar colores únicos a cada opción del menú según el rol
    if ($userRole === 'admin') {
        $menuItems = ['dashboard', 'reservaciones', 'calendario', 'guias', 'paquetes', 'reportes', 'logs', 'configuracion'];
        $_SESSION['menu_colors'] = [];
        foreach ($menuItems as $index => $item) {
            $_SESSION['menu_colors'][$item] = $availableColors[$index];
        }
    } else {
        $menuItems = ['mis-tours', 'perfil'];
        $_SESSION['menu_colors'] = [];
        foreach ($menuItems as $index => $item) {
            $_SESSION['menu_colors'][$item] = $availableColors[$index];
        }
    }
}

$menuColors = $_SESSION['menu_colors'];
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        
        <?php if ($userRole === 'admin'): ?>
        <!-- MENÚ ADMINISTRADOR -->
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('dashboard.php'); ?>"
                   href="<?php echo SITE_URL; ?>/admin/dashboard.php"
                   data-color="<?php echo $menuColors['dashboard']; ?>"
                   data-color-bg="<?php echo hexToRgba($menuColors['dashboard']); ?>">
                    <i class="fas fa-chart-line"></i>
                    Estadísticas
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo isActive('', 'reservaciones'); ?>"
                   href="<?php echo SITE_URL; ?>/admin/reservaciones/"
                   data-color="<?php echo $menuColors['reservaciones']; ?>"
                   data-color-bg="<?php echo hexToRgba($menuColors['reservaciones']); ?>">
                    <i class="fas fa-calendar-check"></i>
                    Reservaciones
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo isActive('calendario.php'); ?>"
                   href="<?php echo SITE_URL; ?>/admin/calendario.php"
                   data-color="<?php echo $menuColors['calendario']; ?>"
                   data-color-bg="<?php echo hexToRgba($menuColors['calendario']); ?>">
                    <i class="fas fa-calendar-alt"></i>
                    Calendario
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo isActive('', 'guias'); ?>"
                   href="<?php echo SITE_URL; ?>/admin/guias/"
                   data-color="<?php echo $menuColors['guias']; ?>"
                   data-color-bg="<?php echo hexToRgba($menuColors['guias']); ?>">
                    <i class="fas fa-user-tie"></i>
                    Guías
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo isActive('', 'paquetes'); ?>"
                   href="<?php echo SITE_URL; ?>/admin/paquetes/"
                   data-color="<?php echo $menuColors['paquetes']; ?>"
                   data-color-bg="<?php echo hexToRgba($menuColors['paquetes']); ?>">
                    <i class="fas fa-box"></i>
                    Paquetes
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo isActive('reportes.php'); ?>"
                   href="<?php echo SITE_URL; ?>/admin/reportes.php"
                   data-color="<?php echo $menuColors['reportes']; ?>"
                   data-color-bg="<?php echo hexToRgba($menuColors['reportes']); ?>">
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
                   href="<?php echo SITE_URL; ?>/admin/logs/"
                   data-color="<?php echo $menuColors['logs']; ?>"
                   data-color-bg="<?php echo hexToRgba($menuColors['logs']); ?>">
                    <i class="fas fa-history"></i>
                    Registro de Actividad
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo isActive('', 'configuracion'); ?>"
                   href="<?php echo SITE_URL; ?>/admin/configuracion/"
                   data-color="<?php echo $menuColors['configuracion']; ?>"
                   data-color-bg="<?php echo hexToRgba($menuColors['configuracion']); ?>">
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
                   href="<?php echo SITE_URL; ?>/admin/guia/mis-tours.php"
                   data-color="<?php echo $menuColors['mis-tours']; ?>"
                   data-color-bg="<?php echo hexToRgba($menuColors['mis-tours']); ?>">
                    <i class="fas fa-calendar-check"></i>
                    Mis Tours
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo isActive('perfil.php'); ?>"
                   href="<?php echo SITE_URL; ?>/admin/guia/perfil.php"
                   data-color="<?php echo $menuColors['perfil']; ?>"
                   data-color-bg="<?php echo hexToRgba($menuColors['perfil']); ?>">
                    <i class="fas fa-user"></i>
                    Mi Perfil
                </a>
            </li>
        </ul>
        <?php endif; ?>
        
    </div>
</nav>

<style>
:root {
    --navbar-height: 72px;
}

.sidebar {
    position: fixed;
    top: var(--navbar-height, 72px);
    bottom: 0;
    left: 0;
    z-index: 100;
    padding: 1rem 0 2rem;
    height: calc(100vh - var(--navbar-height, 72px));
    box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
    background-color: #fff;
}

.sidebar-sticky {
    position: relative;
    top: 0;
    height: calc(100vh - var(--navbar-height, 72px));
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

.sidebar .nav-link i {
    margin-right: 10px;
    width: 20px;
    text-align: center;
}

.sidebar-heading {
    font-size: .75rem;
    text-transform: uppercase;
}

/* Estilos mejorados para móvil */
@media (max-width: 767.98px) {
    .sidebar {
        padding: 1.25rem 0 2rem;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        width: 280px;
        max-width: 80vw;
        z-index: 1050;
        height: calc(100vh - var(--navbar-height, 72px));
    }

    .sidebar.collapse:not(.show) {
        display: none;
    }

    .sidebar.collapse.show {
        display: block;
    }

    .sidebar ~ main {
        margin-left: 0;
    }
}

/* Para pantallas medianas y grandes */
@media (min-width: 768px) {
    .sidebar {
        display: block !important;
    }
}
</style>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    const sidebar = document.getElementById('sidebarMenu');
    const navbarNav = document.getElementById('navbarNav');
    const sidebarToggle = document.querySelector('[data-bs-target="#sidebarMenu"]');
    const navbarToggle = document.querySelector('[data-bs-target="#navbarNav"]');
    const navbar = document.querySelector('.navbar');
    const root = document.documentElement;

    function updateSidebarOffset() {
        if (!navbar) {
            return;
        }
        root.style.setProperty('--navbar-height', `${navbar.offsetHeight}px`);
    }

    updateSidebarOffset();

    navLinks.forEach(link => {
        const color = link.getAttribute('data-color');
        const colorBg = link.getAttribute('data-color-bg');

        if (link.classList.contains('active')) {
            link.style.color = color;
            link.style.backgroundColor = colorBg;
            link.style.borderLeft = `3px solid ${color}`;
        }

        link.addEventListener('mouseenter', function() {
            this.style.color = color;
            this.style.backgroundColor = colorBg;
        });

        link.addEventListener('mouseleave', function() {
            if (!this.classList.contains('active')) {
                this.style.color = '#333';
                this.style.backgroundColor = 'transparent';
            } else {
                this.style.color = color;
                this.style.backgroundColor = colorBg;
            }
        });

        link.addEventListener('click', function() {
            if (window.innerWidth < 768) {
                const bsCollapse = bootstrap.Collapse.getInstance(sidebar);
                if (bsCollapse) {
                    bsCollapse.hide();
                }
            }
        });
    });

    function closeAllSidebars() {
        if (sidebar) {
            const sidebarCollapse = bootstrap.Collapse.getInstance(sidebar);
            if (sidebarCollapse) {
                sidebarCollapse.hide();
            }
        }
        if (navbarNav) {
            const navbarCollapse = bootstrap.Collapse.getInstance(navbarNav);
            if (navbarCollapse) {
                navbarCollapse.hide();
            }
        }
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (navbarNav && navbarNav.classList.contains('show')) {
                const navbarCollapse = bootstrap.Collapse.getInstance(navbarNav);
                if (navbarCollapse) {
                    navbarCollapse.hide();
                }
            }
        });
    }

    if (navbarToggle) {
        navbarToggle.addEventListener('click', function() {
            if (sidebar && sidebar.classList.contains('show')) {
                const sidebarCollapse = bootstrap.Collapse.getInstance(sidebar);
                if (sidebarCollapse) {
                    sidebarCollapse.hide();
                }
            }
        });
    }

    document.addEventListener('click', function(event) {
        if (window.innerWidth < 768) {
            const isClickInsideSidebar = sidebar && sidebar.contains(event.target);
            const isClickInsideNavbar = navbarNav && navbarNav.contains(event.target);
            const isClickOnToggle = (sidebarToggle && sidebarToggle.contains(event.target)) ||
                                    (navbarToggle && navbarToggle.contains(event.target));

            if (!isClickInsideSidebar && !isClickInsideNavbar && !isClickOnToggle) {
                closeAllSidebars();
            }
        }
    });

    window.addEventListener('resize', function() {
        updateSidebarOffset();
        if (window.innerWidth >= 768) {
            closeAllSidebars();
        }
    });
});
</script>

