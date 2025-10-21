<?php


require_once __DIR__ . '/../config/config.php';

// Cambiar idioma si se solicita
if (isset($_GET['lang']) && in_array($_GET['lang'], AVAILABLE_LANGUAGES)) {
    setLanguage($_GET['lang']);
    redirect($_SERVER['PHP_SELF']);
}

$lang = getLanguage();

// Obtener todos los paquetes activos
$db = Database::getInstance();
$paquetes = $db->fetchAll("SELECT * FROM paquetes WHERE activo = 1 ORDER BY id_paquete");

// Determinar cuál paquete está seleccionado (por defecto el primero)
$paquete_seleccionado = isset($_GET['id']) ? (int)$_GET['id'] : $paquetes[0]['id_paquete'];

// Obtener horarios del paquete seleccionado
$horarios = $db->fetchAll(
    "SELECT DISTINCT dia_semana, hora_inicio, hora_fin 
     FROM horarios 
     WHERE id_paquete = ? AND activo = 1 
     ORDER BY FIELD(dia_semana, 'lunes','martes','miercoles','jueves','viernes','sabado','domingo'), hora_inicio",
    [$paquete_seleccionado]
);
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paquetes - <?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/paquetes.css">
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
                        <a class="nav-link" href="<?php echo SITE_URL; ?>">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?php echo SITE_URL; ?>/pages/paquetes.php">Paquetes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/pages/mis-reservas.php">Mis Reservas</a>
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
    
    <!-- Hero Section con Videos (uno por paquete) -->
    <section class="paquetes-hero-section" style="margin-top: 70px;">
        
        <!-- Video para Paquete 1: Cuevas de Unión Zapata -->
        <video class="hero-video video-paquete-1 <?php echo $paquete_seleccionado == 1 ? 'active' : ''; ?>" 
               autoplay muted loop playsinline>
            <source src="<?php echo ASSETS_URL; ?>/video/paquete-1-hero.mp4" type="video/mp4">
            <source src="<?php echo ASSETS_URL; ?>/video/paquete-1-hero.webm" type="video/webm">
        </video>
        
        <!-- Video para Paquete 2: Cuevas Prehistóricas de Mitla -->
        <video class="hero-video video-paquete-2 <?php echo $paquete_seleccionado == 2 ? 'active' : ''; ?>" 
               autoplay muted loop playsinline>
            <source src="<?php echo ASSETS_URL; ?>/video/paquete-2-hero.mp4" type="video/mp4">
            <source src="<?php echo ASSETS_URL; ?>/video/paquete-2-hero.webm" type="video/webm">
        </video>
        
        <!-- Overlay oscuro -->
        <div class="video-overlay"></div>
        
        <!-- Selectores de Paquetes (parte superior del video) -->
        <div class="package-selectors">
            <?php foreach ($paquetes as $index => $paquete): ?>
                <button class="package-selector-btn <?php echo $paquete['id_paquete'] == $paquete_seleccionado ? 'active' : ''; ?>" 
                        data-package-id="<?php echo $paquete['id_paquete']; ?>"
                        onclick="selectPackage(<?php echo $paquete['id_paquete']; ?>)">
                    <?php echo htmlspecialchars($paquete['nombre_paquete']); ?>
                </button>
            <?php endforeach; ?>
        </div>
    </section>
    
    <!-- Figura de Transición (Hexágono) -->
    <div class="transition-shape">
        <div class="hexagon">
            <span>Información de paquete</span>
        </div>
    </div>
    
    <!-- Información del Paquete Seleccionado -->
    <section class="package-info-section py-5" id="package-content">
        <?php 
        // Obtener el paquete seleccionado
        $paquete_actual = array_filter($paquetes, function($p) use ($paquete_seleccionado) {
            return $p['id_paquete'] == $paquete_seleccionado;
        });
        $paquete_actual = reset($paquete_actual);
        $descripcion_campo = 'descripcion_' . $lang;
        ?>
        
        <div class="container">
            <!-- INICIO DE LA NUEVA CUADRÍCULA DE INFORMACIÓN -->
            <div class="info-grid">

                <!-- Fila 1 -->
                <!-- Horario -->
                <div class="info-block horario-block">
                    <div class="info-icon">
                        <img src="<?php echo ASSETS_URL; ?>/img/paquete/icono-horario.png" alt="Horario">
                    </div>
                    <div class="info-content">
                        <h3>Horario</h3>
                        <?php
                        // Agrupar días consecutivos
                        $dias_disponibles = [];
                        foreach ($horarios as $h) {
                            $dias_disponibles[] = ucfirst($h['dia_semana']);
                        }
                        $dias_unicos = array_unique($dias_disponibles);
                        
                        if (count($dias_unicos) == 6) {
                            $dias_texto = ucfirst($horarios[0]['dia_semana']) . ' a domingo.';
                        } else {
                            $dias_texto = implode(', ', $dias_unicos) . '.';
                        }
                        ?>
                        <p><?php echo $dias_texto; ?></p>
                        <?php
                        $horarios_mostrados = [];
                        foreach ($horarios as $h) {
                            $key = $h['hora_inicio'] . '-' . $h['hora_fin'];
                            if (!in_array($key, $horarios_mostrados)) {
                                echo '<p>';
                                if (count(array_unique(array_column($horarios, 'hora_inicio'))) > 1) {
                                    echo 'Recorrido ' . date('g:i a', strtotime($h['hora_inicio'])) . ' - ' . date('g:i a', strtotime($h['hora_fin']));
                                } else {
                                    echo 'Primer recorrido ' . date('g:i a', strtotime($h['hora_inicio'])) . ' - ' . date('g:i a', strtotime($h['hora_fin']));
                                }
                                echo '</p>';
                                $horarios_mostrados[] = $key;
                            }
                        }
                        ?>
                        <p class="text-danger">Cerrado los lunes</p>
                    </div>
                </div>
                
                <!-- Precio -->
                <div class="info-block precio-block">
                    <div class="info-icon">
                        <img src="<?php echo ASSETS_URL; ?>/img/paquete/icono-precio.png" alt="Precio">
                    </div>
                    <div class="info-content">
                        <h3>Precio</h3>
                        <p class="precio-item">
                            Guía: 
                            <span class="precio-mxn">$<?php echo number_format($paquete_actual['precio_guia'], 2); ?> mxn</span> / 
                            <span class="precio-usd" data-mxn="<?php echo $paquete_actual['precio_guia']; ?>">$ 0.00 USD</span>
                        </p>
                        <p class="precio-item">
                            Entrada al sitio por persona: 
                            <span class="precio-mxn">$<?php echo number_format($paquete_actual['precio_entrada_persona'], 2); ?> mxn</span> / 
                            <span class="precio-usd" data-mxn="<?php echo $paquete_actual['precio_entrada_persona']; ?>">$ 0.00 USD</span>
                        </p>
                    </div>
                </div>

                <!-- Fila 2 -->
                <!-- Plan de Visita -->
                <div class="info-block plan-block">
                    <div class="info-icon">
                        <img src="<?php echo ASSETS_URL; ?>/img/paquete/icono-plan.png" alt="Plan">
                    </div>
                    <div class="info-content">
                        <h3>Plan de visita</h3>
                        <p><?php echo htmlspecialchars($paquete_actual[$descripcion_campo]); ?></p>
                        <p>Duración aproximada: <?php echo $paquete_actual['duracion_horas']; ?> horas.</p>
                    </div>
                </div>

                <!-- Galería de Imágenes (Bloque especial 2x2) -->
                <div class="info-block gallery-block">
                    <?php
                    // Definir los textos para la galería de cada paquete
                    $gallery_texts = [
                        1 => [ // Textos para Paquete ID 1
                            "Guilá Naquitz",
                            "La Paloma",
                            "Los Machines"
                        ],
                        2 => [ // Textos para Paquete ID 2
                            "Cueva Oscura",
                            "La Pintada",
                            "Cueva prehistórica"
                        ]
                    ];
                    $current_texts = $gallery_texts[$paquete_actual['id_paquete']] ?? [];
                    ?>
                    <div class="image-gallery">
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                            <div class="gallery-item <?php echo ($i === 1) ? 'active' : ''; ?>" data-index="<?php echo $i - 1; ?>">
                                <img src="<?php echo ASSETS_URL; ?>/img/paquete/galeria-<?php echo $paquete_actual['id_paquete']; ?>-<?php echo $i; ?>.jpg" 
                                     alt="<?php echo htmlspecialchars($current_texts[$i - 1] ?? 'Imagen de galería'); ?>" 
                                     class="gallery-img">
                                <?php if (!empty($current_texts[$i - 1])): ?>
                                    <div class="gallery-caption">
                                        <span><?php echo htmlspecialchars($current_texts[$i - 1]); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>

                        <!-- Botones de navegación manual -->
                        <button class="gallery-nav-btn gallery-nav-prev" aria-label="Imagen anterior"><i class="fas fa-chevron-left"></i></button>
                        <button class="gallery-nav-btn gallery-nav-next" aria-label="Siguiente imagen"><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
                
                <!-- Fila 3 -->
                <!-- Designación de Guías -->
                <div class="info-block guias-block">
                    <div class="info-icon">
                        <img src="<?php echo ASSETS_URL; ?>/img/paquete/icono-guias.png" alt="Guías">
                    </div>
                    <div class="info-content">
                        <h3>Designación de guías comunitarios</h3>
                        <p>Cada grupo de <?php echo $paquete_actual['personas_por_guia']; ?> personas deberá contar con 2 guías locales capacitados, designados por el comité ejidal. Su función es interpretar el patrimonio y garantizar seguridad.</p>
                    </div>
                </div>

                <!-- Servicios Ofrecidos -->
                <div class="info-block servicios-block">
                    <div class="info-icon">
                        <img src="<?php echo ASSETS_URL; ?>/img/paquete/icono-servicios.png" alt="Servicios">
                    </div>
                    <div class="info-content">
                        <h3>Servicios ofrecidos</h3>
                        <p>Visita guiada, interpretación cultural y natural, centro de visitantes con sanitarios, área de hidratación, módulos informativos y venta de artesanías locales.</p>
                    </div>
                </div>
                
                <!-- Fila 4 -->
                <!-- Transporte -->
                <div class="info-block transporte-block">
                    <div class="info-icon">
                        <img src="<?php echo ASSETS_URL; ?>/img/paquete/icono-transporte.png" alt="Transporte">
                    </div>
                    <div class="info-content">
                        <h3>Transporte</h3>
                        <p>Acceso por carretera Mitla-Unión Zapata (aprox. 10 min). Transporte comunitario disponible desde Mitla. Estacionamiento limitado para autos particulares. Posibilidad de transporte contratado para grupos.</p>
                    </div>
                </div>

                <!-- Infraestructura -->
                <div class="info-block infraestructura-block">
                    <div class="info-icon">
                        <img src="<?php echo ASSETS_URL; ?>/img/paquete/icono-infraestructura.png" alt="Infraestructura">
                    </div>
                    <div class="info-content">
                        <h3>Infraestructura de visita</h3>
                        <p>Senderos señalizados, áreas de descanso, barandales en puntos estratégicos, señalética bilingüe (español-inglés), Centro Interpretativo con sala de exposición y audiovisuales.</p>
                    </div>
                </div>
                
                <!-- Fila 5 -->
                <!-- Lugares Cercanos -->
                <div class="info-block lugares-block">
                    <div class="info-icon">
                        <img src="<?php echo ASSETS_URL; ?>/img/paquete/icono-lugares.png" alt="Lugares">
                    </div>
                    <div class="info-content">
                        <h3>Lugares cercanos de interés</h3>
                        <p>Zona arqueológica de Mitla, Templo de San Pablo, Mercado de Mitla, Parador turístico de Hierve el Agua, talleres de textiles y mezcal en comunidades vecinas.</p>
                    </div>
                </div>

                <!-- Recomendaciones -->
                <div class="info-block recomendaciones-block">
                    <div class="info-icon">
                        <img src="<?php echo ASSETS_URL; ?>/img/paquete/icono-recomendaciones.png" alt="Recomendaciones">
                    </div>
                    <div class="info-content">
                        <h3>Recomendaciones para visitantes</h3>
                        <p>Ropa ligera y cómoda, calzado para caminata, sombrero o gorra, bloqueador solar biodegradable, agua personal reutilizable, repelente natural. No se permiten drones, bocinas o basura.</p>
                    </div>
                </div>

            </div>
            <!-- FIN DE LA NUEVA CUADRÍCULA DE INFORMACIÓN -->
            
            <!-- Mapa del Recorrido -->
            <div class="map-section text-center my-5">
                <h3 class="mb-4">Mapa del Recorrido</h3>
                <div class="map-widget-container">
                    <?php if ($paquete_actual['id_paquete'] == 1): ?>
                        <!-- Mapa para Cuevas de Unión Zapata -->
                        <iframe frameBorder="0" scrolling="no" src="https://es.wikiloc.com/wikiloc/embedv2.do?id=181194053&elevation=off&images=off&maptype=H" width="100%" height="500"></iframe>
                        <div style="color:#777;font-size:11px;line-height:16px;">Powered by <a style="color:#4C8C2B;font-size:11px;line-height:16px;" target="_blank" href="https://es.wikiloc.com">Wikiloc</a></div>
                    <?php elseif ($paquete_actual['id_paquete'] == 2): ?>
                        <!-- Mapa para Cuevas Prehistóricas de Mitla -->
                        <iframe frameBorder="0" scrolling="no" src="https://es.wikiloc.com/wikiloc/embedv2.do?id=97179823&elevation=off&images=off&maptype=H" width="100%" height="500"></iframe>
                        <div style="color:#777;font-size:11px;line-height:16px;">Powered by <a style="color:#4C8C2B;font-size:11px;line-height:16px;" target="_blank" href="https://es.wikiloc.com">Wikiloc</a></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Botón de Reservar -->
            <div class="text-center mt-5">
                <a href="<?php echo SITE_URL; ?>/pages/reservar.php?id=<?php echo $paquete_actual['id_paquete']; ?>" 
                   class="btn btn-primary btn-lg btn-reservar">
                    <i class="fas fa-calendar-check"></i> Reservar
                </a>
                
                <!-- Texto de Aviso -->
                <p class="reservas-aviso mt-3">
                    Las reservas únicamente son mediante página web oficial, aplicación 
                    <?php echo $paquete_actual['id_paquete'] == 1 ? 'ZapataRUTA' : 'MitlaCuevasRUTA'; ?>, 
                    o módulo de información en el Centro Interpretativo.
                </p>
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
                Todos los derechos reservados
            </p>
            <div class="mt-3">
                <a href="<?php echo SITE_URL; ?>/admin" class="text-white-50 small">Acceso Administrador</a>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo ASSETS_URL; ?>/js/paquetes.js"></script>
</body>
</html>
