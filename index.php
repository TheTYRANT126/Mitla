<?php


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
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <?php 
                $logoPath = ASSETS_PATH . '/img/logo.png';
                if (file_exists($logoPath)): 
                ?>
                    <img src="<?php echo ASSETS_URL; ?>/img/logo.png" alt="<?php echo SITE_NAME; ?>" class="navbar-logo">
                <?php else: ?>
                    <i class="fas fa-mountain"></i> <?php echo SITE_NAME; ?>
                <?php endif; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="<?php echo SITE_URL; ?>">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/pages/paquetes.php">Paquetes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/pages/mis-reservas.php">Mis Reservas</a>
                    </li>
                    
                    <!-- Selector de idioma -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="langDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-globe"></i> 
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
    
    <!-- Hero Section con Carrusel -->
    <section class="hero-carousel-section" style="margin-top: 70px;">
        <div class="hero-carousel">
            <!-- Slides del carrusel -->
            <div class="hero-slide active" style="background-image: url('<?php echo ASSETS_URL; ?>/img/hero/hero-slide-1.jpg');"></div>
            <div class="hero-slide" style="background-image: url('<?php echo ASSETS_URL; ?>/img/hero/hero-slide-2.jpg');"></div>
            <div class="hero-slide" style="background-image: url('<?php echo ASSETS_URL; ?>/img/hero/hero-slide-3.jpg');"></div>
            <div class="hero-slide" style="background-image: url('<?php echo ASSETS_URL; ?>/img/hero/hero-slide-4.jpg');"></div>
            
            <!-- Overlay con transparencia configurable -->
            <div class="hero-overlay"></div>
            
            <!-- Contenido -->
            <div class="hero-content">
                <div class="container text-center">
                    <h1 class="display-3 fw-bold mb-4 animate-fade-in">
                        Descubre las Cuevas Prehistóricas de Mitla
                    </h1>
                    <p class="lead mb-4 animate-fade-in-delay">
                        Vive una experiencia única con guías certificados
                    </p>
                    <a href="<?php echo SITE_URL; ?>/pages/paquetes.php" class="btn btn-primary btn-lg animate-fade-in-delay-2">
                        <i class="fas fa-calendar-check"></i> Reservar Ahora
                    </a>
                </div>
            </div>
            
            <!-- Indicadores -->
            <div class="hero-indicators">
                <span class="indicator active" data-slide="0"></span>
                <span class="indicator" data-slide="1"></span>
                <span class="indicator" data-slide="2"></span>
                <span class="indicator" data-slide="3"></span>
            </div>
        </div>
    </section>
    
    <!-- Características con Hover Effects -->
    <section class="features py-5">
        <div class="container">
            <div class="row text-center">
                <!-- Paseos Seguros -->
                <div class="col-md-3 mb-4">
                    <div class="feature-box">
                        <div class="feature-icon-wrapper">
                            <i class="fas fa-user-shield fa-3x feature-icon feature-icon-safe"></i>
                        </div>
                        <h4 class="feature-title">Paseos seguros</h4>
                        <p class="feature-description">Con guías locales capacitados</p>
                    </div>
                </div>
                
                <!-- Guías en Diferentes Idiomas -->
                <div class="col-md-3 mb-4">
                    <div class="feature-box">
                        <div class="feature-icon-wrapper">
                            <i class="fas fa-language fa-3x feature-icon feature-icon-lang"></i>
                        </div>
                        <h4 class="feature-title">Guías en diferentes idiomas</h4>
                        <p class="feature-description">Senderos señalizados, áreas de descanso, barandales en puntos estratégicos, señalética bilingüe (español-inglés), Centro Interpretativo con sala de exposición y audiovisuales</p>
                    </div>
                </div>
                
                <!-- Consulta el Clima -->
                <div class="col-md-3 mb-4">
                    <div class="feature-box">
                        <div class="feature-icon-wrapper">
                            <i class="fas fa-cloud-sun fa-3x feature-icon feature-icon-weather"></i>
                        </div>
                        <h4 class="feature-title">Consulta el clima el día de tu guía</h4>
                        <p class="feature-description">
                            <!-- Imagen del clima dentro de la descripción -->
                            <img src="<?php echo ASSETS_URL; ?>/img/features/clima-icon.png" 
                                 alt="Clima" 
                                 class="clima-preview-image">
                            Planifica tu recorrido para el clima que más te gusta al momento de reservar
                        </p>
                    </div>
                </div>
                
                <!-- Tu Ticket en tu Teléfono -->
                <div class="col-md-3 mb-4">
                    <div class="feature-box feature-box-phone">
                        <div class="feature-icon-wrapper">
                            <i class="fas fa-mobile-alt fa-3x feature-icon feature-icon-phone"></i>
                        </div>
                        <h4 class="feature-title">Tu ticket en tu teléfono</h4>
                        <div class="feature-description feature-phone-container">
                            <!-- Imagen del teléfono como fondo -->
                            <img src="<?php echo ASSETS_URL; ?>/img/features/telefono.png" 
                                 alt="Teléfono" 
                                 class="phone-image">
                            <!-- Texto dentro del teléfono -->
                            <p class="phone-text">
                                Cuando reserves tu recorrido se genera un ticket con toda la información
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Paquetes Destacados con Hover Effects -->
    <section class="packages bg-light py-5">
        <div class="container">
            <h2 class="text-center mb-5">Conoce nuestros paquetes</h2>
            
            <div class="row">
                <?php
                // Obtener paquetes destacados
                $db = Database::getInstance();
                $paquetes = $db->fetchAll("SELECT * FROM paquetes WHERE activo = 1 ORDER BY id_paquete LIMIT 2");
                
                foreach ($paquetes as $paquete):
                    $descripcion_campo = 'descripcion_' . $lang;
                    // Usar el ID del paquete para la imagen con fallback a default
                    $packageId = $paquete['id_paquete'];
                    $imagenPaquete = ASSETS_PATH . '/img/packages/package-' . $packageId . '.jpg';
                    $imagenUrl = file_exists($imagenPaquete) 
                        ? ASSETS_URL . '/img/packages/package-' . $packageId . '.jpg'
                        : ASSETS_URL . '/img/packages/default.jpg';
                ?>
                <div class="col-md-6 mb-4">
                    <div class="package-card">
                        <!-- Imagen de fondo con overlay -->
                        <div class="package-bg" style="background-image: url('<?php echo $imagenUrl; ?>');"></div>
                        <div class="package-overlay"></div>
                        
                        <!-- Contenido -->
                        <div class="package-content">
                            <h3 class="package-title"><?php echo htmlspecialchars($paquete['nombre_paquete']); ?></h3>
                            <p class="package-description"><?php echo htmlspecialchars($paquete[$descripcion_campo]); ?></p>
                            
                            <div class="package-details">
                                <p>
                                    <i class="fas fa-clock"></i> 
                                    <strong>Duración:</strong> 
                                    <?php echo $paquete['duracion_horas']; ?> horas
                                </p>
                                <p>
                                    <i class="fas fa-users"></i> 
                                    <strong>Capacidad máxima:</strong> 
                                    <?php echo $paquete['capacidad_maxima']; ?> personas
                                </p>
                                <p>
                                    <i class="fas fa-money-bill-wave"></i> 
                                    <strong>Precio guía:</strong> 
                                    <?php echo formatearPrecio($paquete['precio_guia']); ?>
                                </p>
                                <p>
                                    <i class="fas fa-ticket-alt"></i> 
                                    <strong>Entrada por persona:</strong> 
                                    <?php echo formatearPrecio($paquete['precio_entrada_persona']); ?>
                                </p>
                            </div>
                            
                            <a href="pages/paquetes.php?id=<?php echo $paquete['id_paquete']; ?>"
                               class="btn btn-primary w-100 mt-3">
                                <i class="fas fa-calendar-alt"></i> Reservar
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-4">
                <!-- 
                BOTÓN "TODOS LOS PAQUETES" COMENTADO PARA FUTURO USO
                <a href="<?php echo SITE_URL; ?>/pages/paquetes.php" class="btn btn-outline-primary btn-lg">
                    <?php echo t('home.view_all_packages'); ?>
                </a>
                -->
                 <p class="packages-subtext">
                     <?php echo t('home.packages_additional_info'); ?>
                 </p>
            </div>
        </div>
    </section>
    
    <!-- Más Información con Wikipedia Icon -->
    <section class="info-section py-5">
        <div class="container">
            <!-- Imagen de fondo con overlay -->
            <div class="info-bg" style="background-image: url('<?php echo ASSETS_URL; ?>/img/info/info-bg.jpg');"></div>
            <div class="info-overlay"></div>
            
            <div class="info-content">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3 class="text-white mb-3">
                            Conoce más sobre Mitla
                        </h3>
                        <p class="text-white">
                            <?php echo t('home.info_section_title') . ' ' . t('home.info_section_desc'); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-center">
                        <a href="https://es.wikipedia.org/wiki/San_Pablo_Villa_de_Mitla" 
                           target="_blank" 
                           class="wiki-icon-link">
                            <div class="wiki-icon-wrapper">
                                <i class="fas fa-info-circle fa-5x text-white info-icon"></i>
                                <img src="<?php echo ASSETS_URL; ?>/img/info/wikipedia-logo.png" 
                                     alt="Wikipedia" 
                                     class="wikipedia-icon">
                            </div>
                            <p class="text-white mt-0">Más información</p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container text-center">
            <p class="mb-2">
                <i class="fas fa-envelope"></i> noreply@mitla.com | 
                <i class="fas fa-phone"></i> 951-123-4567
            </p>
            <p class="mb-0">
                &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. 
                Todos los derechos reservados
            </p>
            <div class="mt-3">
                <a href="/mitla-tours/admin/" class="text-white-50 small">Acceso Administrador</a>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JS -->
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>

    <!-- Easter Egg del Footer -->
    <script src="<?php echo ASSETS_URL; ?>/js/footer-easter-egg.js"></script>
</body>
</html>
