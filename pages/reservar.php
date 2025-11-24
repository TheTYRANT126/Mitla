<?php


require_once __DIR__ . '/../config/config.php';

// Verificar que se haya seleccionado un paquete
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirect(SITE_URL . '/pages/paquetes.php');
}

$paquete_id = (int)$_GET['id'];

// Obtener información del paquete
$db = Database::getInstance();
$paquete = $db->fetchOne("SELECT * FROM paquetes WHERE id_paquete = ? AND activo = 1", [$paquete_id]);

if (!$paquete) {
    redirect(SITE_URL . '/pages/paquetes.php');
}

// Obtener horarios disponibles para este paquete
$horarios = $db->fetchAll(
    "SELECT DISTINCT dia_semana, hora_inicio, hora_fin 
     FROM horarios 
     WHERE id_paquete = ? AND activo = 1 
     ORDER BY FIELD(dia_semana, 'lunes','martes','miercoles','jueves','viernes','sabado','domingo'), hora_inicio",
    [$paquete_id]
);

$lang = getLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservar - <?php echo htmlspecialchars($paquete['nombre_paquete']); ?> - <?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/reservar.css">

    <style>
        /**
         * Aumentar el tamaño del campo de fecha para mejorar la usabilidad en móviles.
         */
        .date-input-wrapper input[type="date"].form-control {
            height: calc(2.5rem + 2px); /* Altura aumentada */
            padding: .5rem 1rem;       /* Más espacio interno */
            font-size: 1.25rem;        /* Texto más grande */
        }
    </style>
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
                            <li><a class="dropdown-item" href="?id=<?php echo $paquete_id; ?>&lang=es">🇲🇽 Español</a></li>
                            <li><a class="dropdown-item" href="?id=<?php echo $paquete_id; ?>&lang=en">🇺🇸 English</a></li>
                            <li><a class="dropdown-item" href="?id=<?php echo $paquete_id; ?>&lang=fr">🇫🇷 Français</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Banner del Paquete -->
    <section class="package-banner" style="margin-top: 70px;">
        <?php
        // 🔧 AJUSTABLE: Ruta de la imagen del banner
        // Guarda tus imágenes en: assets/img/banners/banner-paquete-1.jpg, banner-paquete-2.jpg, etc.
        $bannerPath = ASSETS_PATH . '/img/banners/banner-paquete-' . $paquete_id . '.jpg';
        $bannerUrl = file_exists($bannerPath) 
            ? ASSETS_URL . '/img/banners/banner-paquete-' . $paquete_id . '.jpg'
            : ASSETS_URL . '/img/banners/default-banner.jpg';
        ?>
        <div class="banner-image" style="background-image: url('<?php echo $bannerUrl; ?>');"></div>
        <div class="banner-overlay"></div>
        <div class="banner-content">
            <div class="container">
                <h1 class="banner-title"><?php echo htmlspecialchars($paquete['nombre_paquete']); ?></h1>
                <button class="btn-back" onclick="window.location.href='<?php echo SITE_URL; ?>/pages/paquetes.php?id=<?php echo $paquete_id; ?>'">
                    <i class="fas fa-arrow-left"></i> Regresar / Cambiar de paquete
                </button>
            </div>
        </div>
    </section>
    
    <!-- Formulario de Reservación -->
    <section class="reservation-form-section py-5">
        <div class="container">
            <div class="row">
                <!-- 🔧 AJUSTABLE: Ancho del contenedor principal -->
                <!-- Cambia "col-lg-8" a "col-lg-10" o "col-lg-12" para hacerlo más ancho en pantallas grandes. -->
                <!-- Este div contiene tanto el formulario como el mapa de abajo. -->
                <div class="col-lg-8 mx-auto"> 
                    <form id="reservationForm" class="reservation-form" method="POST" action="<?php echo SITE_URL; ?>/api/procesar_reservacion.php">
                        
                        <!-- Campo oculto para el ID del paquete -->
                        <input type="hidden" name="id_paquete" value="<?php echo $paquete_id; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo generarCSRFToken(); ?>">
                        
                        <!-- Información Personal -->
                        <div class="form-section">
                            <h3 class="section-title">Información Personal</h3>
                            
                            <div class="mb-3">
                                <label for="nombre_completo" class="form-label">Nombre completo: <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Correo electrónico: <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="alergias" class="form-label">Alergias o condiciones médicas del (los) participante(s):</label>
                                <textarea class="form-control" id="alergias" name="alergias" rows="3" placeholder="Opcional: Ingrese alergias o condiciones médicas relevantes"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="idioma" class="form-label">Idioma del recorrido: <span class="text-danger">*</span></label>
                                <select class="form-select" id="idioma" name="idioma" required>
                                    <option value="">Seleccione un idioma</option>
                                    <option value="español">Español</option>
                                    <option value="ingles">Inglés</option>
                                    <option value="frances">Francés</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Fecha y Horario -->
                        <div class="form-section">
                            <h3 class="section-title">Fecha y Horario</h3>
                            
                            <div class="mb-3">
                                <label for="fecha" class="form-label">Fecha: <span class="text-danger">*</span> <span id="nombre-dia-semana" class="text-primary fw-bold"></span></label>
                                <div class="date-input-wrapper">
                                    <input type="date" class="form-control" id="fecha" name="fecha" required>
                                </div>
                                <small class="form-text text-muted">
                                    Días disponibles: 
                                    <?php
                                    $dias_disponibles = [];
                                    foreach ($horarios as $h) {
                                        $dias_disponibles[] = ucfirst($h['dia_semana']);
                                    }
                                    echo implode(', ', array_unique($dias_disponibles));
                                    ?>
                                </small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="horario" class="form-label">Horario: <span class="text-danger">*</span></label>
                                <select class="form-select" id="horario" name="horario" required disabled>
                                    <option value="">Primero seleccione una fecha</option>
                                </select>
                                <div id="horario-info" class="mt-2"></div>
                            </div>
                        </div>
                        
                        <!-- Cantidad de Lugares -->
                        <div class="form-section">
                            <h3 class="section-title">Cupo: <?php echo $paquete['capacidad_maxima']; ?> lugares</h3>
                            
                            <div class="lugares-selector">
                                <label class="form-label">Lugares</label>
                                <div class="input-group">
                                    <button type="button" class="btn btn-outline-primary btn-decrement" id="btn-decrement">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" class="form-control text-center" id="numero_personas" name="numero_personas" 
                                           value="1" min="1" max="<?php echo $paquete['capacidad_maxima']; ?>" readonly>
                                    <button type="button" class="btn btn-outline-primary btn-increment" id="btn-increment">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <input type="hidden" id="numero_guias" name="numero_guias" value="1">
                            </div>
                            
                            <div class="guias-info mt-3" id="guias-info">
                                <i class="fas fa-user-friends"></i> Se necesita de 1 guía
                            </div>
                        </div>
                        
                        <!-- Resumen de Precio -->
<div class="form-section price-summary">
    <h3 class="section-title">Resumen</h3>
    
    <div class="price-breakdown">
        <!-- Entrada al sitio -->
        <div class="price-item">
            <span>Entrada al sitio por persona:</span>
            <span>
                <span class="price-mxn-unitario" style="font-size: 0.9rem; color: #666;">
                    $<?php echo number_format($paquete['precio_entrada_persona'], 2); ?> mxn
                </span> / 
                <span class="price-usd-unitario" data-mxn="<?php echo $paquete['precio_entrada_persona']; ?>" style="font-size: 0.9rem; color: #666;">
                    $0.00 USD
                </span>
            </span>
        </div>
        <div class="price-item">
            <span id="contador-personas" style="font-weight: 600;">x1</span>
            <span>
                <span class="subtotal-entradas-mxn" style="font-weight: 700; color: #333;">
                    $<?php echo number_format($paquete['precio_entrada_persona'], 2); ?> mxn
                </span> / 
                <span class="subtotal-entradas-usd" style="font-weight: 700; color: #0066cc;">
                    $0.00 USD
                </span>
            </span>
        </div>
        
        <!-- Guía -->
        <div class="price-item mt-3">
            <span>Guía:</span>
            <span>
                <span class="price-guia-unitario" style="font-size: 0.9rem; color: #666;">
                    $<?php echo number_format($paquete['precio_guia'], 2); ?> mxn
                </span> / 
                <span class="price-guia-usd-unitario" data-mxn="<?php echo $paquete['precio_guia']; ?>" style="font-size: 0.9rem; color: #666;">
                    $0.00 USD
                </span>
            </span>
        </div>
        <div class="price-item">
            <span id="contador-guias" style="font-weight: 600;">x1</span>
            <span>
                <span class="subtotal-guias-mxn" style="font-weight: 700; color: #333;">
                    $<?php echo number_format($paquete['precio_guia'], 2); ?> mxn
                </span> / 
                <span class="subtotal-guias-usd" style="font-weight: 700; color: #0066cc;">
                    $0.00 USD
                </span>
            </span>
        </div>
        
        <hr>
        
        <div class="price-total">
            <span>Total:</span>
            <span>
                <span class="total-mxn">$0.00 mxn</span> / 
                <span class="total-usd">$0.00 USD</span>
            </span>
        </div>
    </div>
</div>
                        
                        <!-- Botón de Pagar -->
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-lg btn-pagar">
                                <i class="fas fa-credit-card"></i> Pagar
                            </button>
                        </div>
                    </form>
                </div>
            </div>            
        </div>
    </section>
    
    <!-- Widget de Clima en sección de ancho completo -->
    <section class="weather-section py-5 bg-light">
        <div class="container-fluid">
            <h3 class="text-center mb-4">Consulta el clima el día de tu recorrido</h3>
            <div class="row">
                <div class="col-12">
                    <!-- 🔧 AJUSTABLE: Ancho del widget del clima -->
                    <!-- Cambia el valor de "max-width" para ajustar el ancho máximo del widget. Ej: "100%" para ancho completo. -->
                    <div style="background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 1000px; margin: 0 auto;">
                        <iframe
                            id="weather-iframe"
                            src="https://www.meteored.mx/clima_Mitla-America+Norte-Mexico-Oaxaca--1-21105.html"
                            width="100%"
                            height="500"
                            frameborder="0"
                            loading="lazy"
                            style="display: block; border: none;">
                        </iframe>
                    </div>
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
                Todos los derechos reservados
            </p>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Datos del paquete para JavaScript -->
    <script>
        const PAQUETE_DATA = {
            id: <?php echo $paquete_id; ?>,
            precio_entrada: <?php echo $paquete['precio_entrada_persona']; ?>,
            precio_guia: <?php echo $paquete['precio_guia']; ?>,
            capacidad_maxima: <?php echo $paquete['capacidad_maxima']; ?>,
            personas_por_guia: <?php echo $paquete['personas_por_guia']; ?>,
            horarios: <?php echo json_encode($horarios); ?>
        };
    </script>
    
    <!-- Custom JS -->
    <script src="<?php echo ASSETS_URL; ?>/js/reservar.js"></script>

    <!-- Easter Egg del Footer -->
    <script src="<?php echo ASSETS_URL; ?>/js/footer-easter-egg.js"></script>

</body>
</html>