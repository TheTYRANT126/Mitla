<?php
/**
 * Página Principal - Mitla Tours
 * Sistema de Reservaciones
 */

require_once __DIR__ . '/config/config.php';

// Cambiar idioma si se solicita
if (isset($_GET['lang']) && in_array($_GET['lang'], AVAILABLE_LANGUAGES)) {
    setLanguage($_GET['lang']);
    redirect($_SERVER['PHP_SELF']);
}

$lang = getLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo t('meta.description'); ?>">
    <title><?php echo t('home.title'); ?> - <?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
</head>
<body>
    
    <!-- Navegación -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="fas fa-mountain"></i> <?php echo SITE_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="/">
                            <?php echo t('nav.home'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/pages/paquetes.php">
                            <?php echo t('nav.packages'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/pages/mis-reservas.php">
                            <?php echo t('nav.my_bookings'); ?>
                        </a>
                    </li>
                    
                    <!-- Selector de idioma -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="langDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-globe"></i> 
                            <?php 
                                $langs = ['es' => 'ES', 'en' => 'EN', 'fr' => 'FR'];
                                echo $langs[$lang];
                            ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?lang=es">🇲🇽 Español</a></li>
                            <li><a class="dropdown-item" href="?lang=en">🇺🇸 English</a></li>
                            <li><a class="dropdown-item" href="?lang=fr">🇫🇷 Français</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Hero Section -->
    <section class="hero-section" style="margin-top: 70px; background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('<?php echo ASSETS_URL; ?>/img/hero-caves.jpg') center/cover; height: 500px; display: flex; align-items: center; color: white;">
        <div class="container text-center">
            <h1 class="display-3 fw-bold mb-4">
                <?php echo t('home.hero_title'); ?>
            </h1>
            <p class="lead mb-4">
                <?php echo t('home.hero_subtitle'); ?>
            </p>
            <a href="/pages/paquetes.php" class="btn btn-primary btn-lg">
                <i class="fas fa-calendar-check"></i> 
                <?php echo t('home.book_now'); ?>
            </a>
        </div>
    </section>
    
    <!-- Características -->
    <section class="features py-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3 mb-4">
                    <div class="feature-box p-4">
                        <i class="fas fa-user-shield fa-3x text-primary mb-3"></i>
                        <h4><?php echo t('home.feature1_title'); ?></h4>
                        <p><?php echo t('home.feature1_desc'); ?></p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="feature-box p-4">
                        <i class="fas fa-language fa-3x text-primary mb-3"></i>
                        <h4><?php echo t('home.feature2_title'); ?></h4>
                        <p><?php echo t('home.feature2_desc'); ?></p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="feature-box p-4">
                        <i class="fas fa-cloud-sun fa-3x text-primary mb-3"></i>
                        <h4><?php echo t('home.feature3_title'); ?></h4>
                        <p><?php echo t('home.feature3_desc'); ?></p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="feature-box p-4">
                        <i class="fas fa-mobile-alt fa-3x text-primary mb-3"></i>
                        <h4><?php echo t('home.feature4_title'); ?></h4>
                        <p><?php echo t('home.feature4_desc'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Paquetes Destacados -->
    <section class="packages bg-light py-5">
        <div class="container">
            <h2 class="text-center mb-5"><?php echo t('home.featured_packages'); ?></h2>
            
            <div class="row">
                <?php
                // Obtener paquetes desde la base de datos
                $db = Database::getInstance();
                $paquetes = $db->fetchAll("SELECT * FROM paquetes WHERE activo = 1 ORDER BY id_paquete LIMIT 2");
                
                foreach ($paquetes as $paquete):
                    $descripcion_campo = 'descripcion_' . $lang;
                ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h3 class="card-title"><?php echo htmlspecialchars($paquete['nombre_paquete']); ?></h3>
                            <p class="card-text"><?php echo htmlspecialchars($paquete[$descripcion_campo]); ?></p>
                            
                            <div class="package-details">
                                <p>
                                    <i class="fas fa-clock"></i> 
                                    <strong><?php echo t('packages.duration'); ?>:</strong> 
                                    <?php echo $paquete['duracion_horas']; ?> <?php echo t('packages.hours'); ?>
                                </p>
                                <p>
                                    <i class="fas fa-users"></i> 
                                    <strong><?php echo t('packages.capacity'); ?>:</strong> 
                                    <?php echo $paquete['capacidad_maxima']; ?> <?php echo t('packages.people'); ?>
                                </p>
                                <p>
                                    <i class="fas fa-money-bill-wave"></i> 
                                    <strong><?php echo t('packages.price_guide'); ?>:</strong> 
                                    <?php echo formatearPrecio($paquete['precio_guia']); ?>
                                </p>
                                <p>
                                    <i class="fas fa-ticket-alt"></i> 
                                    <strong><?php echo t('packages.price_entry'); ?>:</strong> 
                                    <?php echo formatearPrecio($paquete['precio_entrada_persona']); ?>
                                </p>
                            </div>
                            
                            <a href="/pages/reservar.php?id=<?php echo $paquete['id_paquete']; ?>" 
                               class="btn btn-primary w-100 mt-3">
                                <i class="fas fa-calendar-alt"></i> 
                                <?php echo t('packages.book'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="/pages/paquetes.php" class="btn btn-outline-primary">
                    <?php echo t('home.view_all_packages'); ?>
                </a>
            </div>
        </div>
    </section>
    
    <!-- Información adicional -->
    <section class="info py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6 mb-4">
                    <h3><i class="fas fa-map-marker-alt text-primary"></i> <?php echo t('home.location_title'); ?></h3>
                    <p><?php echo t('home.location_desc'); ?></p>
                    <ul>
                        <li><?php echo t('home.location_mitla'); ?></li>
                        <li><?php echo t('home.location_oaxaca'); ?></li>
                    </ul>
                </div>
                <div class="col-md-6 mb-4">
                    <h3><i class="fas fa-info-circle text-primary"></i> <?php echo t('home.info_title'); ?></h3>
                    <p><?php echo t('home.info_desc'); ?></p>
                    <ul>
                        <li><?php echo t('home.info_comfortable_shoes'); ?></li>
                        <li><?php echo t('home.info_water'); ?></li>
                        <li><?php echo t('home.info_sunscreen'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container text-center">
            <p class="mb-2">
                <i class="fas fa-envelope"></i> <?php echo SITE_EMAIL; ?> | 
                <i class="fas fa-phone"></i> 951-123-4567
            </p>
            <p class="mb-0">
                &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. 
                <?php echo t('footer.rights'); ?>
            </p>
            <div class="mt-3">
                <a href="/admin" class="text-white-50 small"><?php echo t('footer.admin_access'); ?></a>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
</body>
</html>
