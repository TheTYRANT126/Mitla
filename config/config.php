<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Mexico_City');

// Detección entorno
$isLocal = (
    $_SERVER['SERVER_NAME'] === 'localhost' || 
    $_SERVER['HTTP_HOST'] === 'localhost' ||
    strpos($_SERVER['HTTP_HOST'], 'localhost') !== false
);

if ($isLocal) {

    // CONFIGURACIÓN LOCAL (XAMPP)

    define('SITE_URL', 'http://localhost/mitla-tours');
    define('DEBUG_MODE', true);
} else {

    // CONFIGURACIÓN SERVIDOR 

    define('SITE_URL', 'http://186.96.178.138:8081');
    define('DEBUG_MODE', false);
}

// Configuración general 
define('SITE_NAME', 'Mitla Tours');
define('SITE_EMAIL', 'noreply@mitla.com');

// Configuración de rutas
define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('CLASSES_PATH', BASE_PATH . '/classes');
define('ASSETS_PATH', BASE_PATH . '/assets');
define('UPLOADS_PATH', BASE_PATH . '/uploads');

// URLs públicas
define('ASSETS_URL', SITE_URL . '/assets');
define('UPLOADS_URL', SITE_URL . '/uploads');

// Configuración de idiomas
define('DEFAULT_LANGUAGE', 'es');
define('AVAILABLE_LANGUAGES', ['es', 'en', 'fr']);

// Configuración de emails 
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'tu-email@gmail.com');
define('SMTP_PASS', 'tu-password');
define('SMTP_FROM_NAME', 'Mitla Tours');

// API Keys 
define('OPENWEATHER_API_KEY', '');
define('STRIPE_PUBLIC_KEY', '');
define('STRIPE_SECRET_KEY', '');

// Configuración de reservaciones
define('DIAS_ANTICIPACION_RECORDATORIO', 2);
define('MAX_PERSONAS_POR_GRUPO', 30);
define('PERSONAS_POR_GUIA', 10);

// Configuración de seguridad
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 1800);

// Configuración de errores
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', BASE_PATH . '/logs/php-error.log');
}

// Headers de seguridad
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

// Cargar base de datos
require_once __DIR__ . '/database.php';

/**
 * Funciones auxiliares globales
 */

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generarCodigoReservacion() {
    return 'MT' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function generarCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verificarCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && 
           hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

if (!function_exists('tableExists')) {
    function tableExists($pdoConnection, $tableName) {
        static $cache = [];
        if (!$pdoConnection || !$tableName) {
            return false;
        }
        $tableName = trim($tableName);
        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }
        try {
            $stmt = $pdoConnection->prepare("
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = ?
            ");
            $stmt->execute([$tableName]);
            $cache[$tableName] = $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log('Error verificando tabla ' . $tableName . ': ' . $e->getMessage());
            $cache[$tableName] = false;
        }
        return $cache[$tableName];
    }
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function getLanguage() {
    if (isset($_SESSION['language']) && in_array($_SESSION['language'], AVAILABLE_LANGUAGES)) {
        return $_SESSION['language'];
    }
    return DEFAULT_LANGUAGE;
}

function setLanguage($lang) {
    if (in_array($lang, AVAILABLE_LANGUAGES)) {
        $_SESSION['language'] = $lang;
        return true;
    }
    return false;
}

function getTranslations($lang = null) {
    if ($lang === null) {
        $lang = getLanguage();
    }
    
    $file = ASSETS_PATH . '/lang/' . $lang . '.json';
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    
    return [];
}

function translate($key, $lang = null) {
    static $translations = [];
    
    if ($lang === null) {
        $lang = getLanguage();
    }
    
    if (!isset($translations[$lang])) {
        $translations[$lang] = getTranslations($lang);
    }
    
    $keys = explode('.', $key);
    $value = $translations[$lang];
    
    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $key;
        }
    }
    
    return $value;
}

function t($key, $lang = null) {
    return translate($key, $lang);
}

function formatearPrecio($precio) {
    return '$' . number_format($precio, 2) . ' MXN';
}

function formatearFecha($fecha, $formato = 'd/m/Y') {
    if (is_string($fecha)) {
        $fecha = strtotime($fecha);
    }
    return date($formato, $fecha);
}

function esFechaFutura($fecha) {
    $timestamp = is_string($fecha) ? strtotime($fecha) : $fecha;
    return $timestamp > time();
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect(SITE_URL . '/admin/login.php');
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        redirect(SITE_URL . '/admin/login.php');
    }
}

function getPackageImageUrl($filename) {
    if (empty($filename)) {
        return null;
    }

    if (filter_var($filename, FILTER_VALIDATE_URL)) {
        return $filename;
    }

    $normalized = ltrim(str_replace('\\', '/', $filename), '/');

    $locations = [
        ASSETS_PATH . '/img/packages/' => ASSETS_URL . '/img/packages/',
        ASSETS_PATH . '/img/paquete/' => ASSETS_URL . '/img/paquete/',
        UPLOADS_PATH . '/paquetes/' => UPLOADS_URL . '/paquetes/'
    ];

    foreach ($locations as $diskPath => $baseUrl) {
        $fullDiskPath = rtrim($diskPath, '/\\') . '/' . $normalized;
        if (is_file($fullDiskPath)) {
            return rtrim($baseUrl, '/') . '/' . $normalized;
        }
    }

    $assetRelative = ltrim(preg_replace('#^assets/#i', '', $normalized), '/');
    $assetPath = rtrim(ASSETS_PATH, '/\\') . '/' . $assetRelative;
    if (is_file($assetPath)) {
        return rtrim(ASSETS_URL, '/') . '/' . $assetRelative;
    }

    $absolutePath = BASE_PATH . '/' . $normalized;
    if (is_file($absolutePath)) {
        return SITE_URL . '/' . $normalized;
    }

    return null;
}

function getPackageGalleryDirectory() {
    return rtrim(ASSETS_PATH, '/\\') . '/img/paquete';
}

function getPackageGalleryImages($idPaquete) {
    $idPaquete = intval($idPaquete);
    if ($idPaquete <= 0) {
        return [];
    }

    $dir = getPackageGalleryDirectory();
    if (!is_dir($dir)) {
        return [];
    }

    $entries = scandir($dir);
    $imagenes = [];
    $pattern = '/^galeria-' . $idPaquete . '-(\d+)\.(jpg|jpeg|png|gif|webp)$/i';

    foreach ($entries as $entry) {
        if (!preg_match($pattern, $entry, $matches)) {
            continue;
        }
        $indice = (int) $matches[1];
        $imagenes[] = [
            'id_imagen' => null,
            'filename' => $entry,
            'index' => $indice,
            'url' => ASSETS_URL . '/img/paquete/' . $entry,
            'source' => 'filesystem'
        ];
    }

    usort($imagenes, function ($a, $b) {
        return $a['index'] <=> $b['index'];
    });

    return $imagenes;
}

function normalizePackageGalleryIndices($idPaquete) {
    $imagenes = getPackageGalleryImages($idPaquete);
    if (empty($imagenes)) {
        return [];
    }

    $dir = getPackageGalleryDirectory();
    $contador = 1;
    foreach ($imagenes as $imagen) {
        $extension = pathinfo($imagen['filename'], PATHINFO_EXTENSION);
        $nuevoNombre = sprintf('galeria-%d-%d.%s', $idPaquete, $contador, $extension);
        if (strcasecmp($imagen['filename'], $nuevoNombre) !== 0) {
            $rutaActual = $dir . '/' . $imagen['filename'];
            $rutaNueva = $dir . '/' . $nuevoNombre;
            if (is_file($rutaNueva)) {
                @unlink($rutaNueva);
            }
            @rename($rutaActual, $rutaNueva);
        }
        $contador++;
    }

    clearstatcache();
    return getPackageGalleryImages($idPaquete);
}

function getPackageExtraDetailsDefaults() {
    return [
        'plan_visita' => '',
        'servicios_ofrecidos' => 'Visita guiada, interpretación cultural y natural, centro de visitantes con sanitarios, área de hidratación, módulos informativos y venta de artesanías locales.',
        'transporte' => 'Acceso por carretera Mitla-Unión Zapata (aprox. 10 min). Transporte comunitario disponible desde Mitla. Estacionamiento limitado para autos particulares. Posibilidad de transporte contratado para grupos.',
        'infraestructura' => 'Senderos señalizados, áreas de descanso, barandales en puntos estratégicos, señalética bilingüe (español-inglés), Centro Interpretativo con sala de exposición y audiovisuales.',
        'lugares_interes' => 'Zona arqueológica de Mitla, Templo de San Pablo, Mercado de Mitla, Parador turístico de Hierve el Agua, talleres de textiles y mezcal en comunidades vecinas.',
        'recomendaciones_visitantes' => 'Ropa ligera y cómoda, calzado para caminata, sombrero o gorra, bloqueador solar biodegradable, agua personal reutilizable, repelente natural. No se permiten drones, bocinas o basura.',
        'mapa_widget' => ''
    ];
}

function ensurePackageDetailsTable($pdoConnection) {
    static $ensured = false;
    if ($ensured) {
        return true;
    }

    try {
        if (!tableExists($pdoConnection, 'paquete_detalles')) {
            $pdoConnection->exec("
                CREATE TABLE IF NOT EXISTS `paquete_detalles` (
                    `id_paquete` INT(11) NOT NULL,
                    `plan_visita` TEXT NULL,
                    `servicios_ofrecidos` TEXT NULL,
                    `transporte` TEXT NULL,
                    `infraestructura` TEXT NULL,
                    `lugares_interes` TEXT NULL,
                    `recomendaciones_visitantes` TEXT NULL,
                    `mapa_widget` TEXT NULL,
                    `creado_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    `actualizado_en` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id_paquete`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
        $ensured = true;
        return true;
    } catch (Exception $e) {
        error_log('No se pudo asegurar la tabla paquete_detalles: ' . $e->getMessage());
        return false;
    }
}

function getPackageExtraDetails($idPaquete) {
    $defaults = getPackageExtraDetailsDefaults();
    $idPaquete = (int) $idPaquete;
    if ($idPaquete <= 0) {
        return $defaults;
    }

    $pdo = Database::getInstance()->getConnection();
    if (!ensurePackageDetailsTable($pdo)) {
        return $defaults;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT plan_visita, servicios_ofrecidos, transporte, infraestructura,
                   lugares_interes, recomendaciones_visitantes, mapa_widget
            FROM paquete_detalles
            WHERE id_paquete = ?
        ");
        $stmt->execute([$idPaquete]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return $defaults;
        }
        return array_merge($defaults, array_intersect_key($fila, $defaults));
    } catch (Exception $e) {
        error_log('Error obteniendo detalles de paquete ' . $idPaquete . ': ' . $e->getMessage());
        return $defaults;
    }
}

function savePackageExtraDetails($idPaquete, array $detalles) {
    $idPaquete = (int) $idPaquete;
    if ($idPaquete <= 0) {
        return false;
    }

    $pdo = Database::getInstance()->getConnection();
    if (!ensurePackageDetailsTable($pdo)) {
        return false;
    }

    $defaults = getPackageExtraDetailsDefaults();
    $payload = array_merge($defaults, array_intersect_key($detalles, $defaults));
    $columnas = array_keys($defaults);
    $placeholders = implode(', ', array_fill(0, count($columnas), '?'));
    $updateClause = implode(', ', array_map(function ($col) {
        return "$col = VALUES($col)";
    }, $columnas));

    $sql = "INSERT INTO paquete_detalles (id_paquete, " . implode(', ', $columnas) . ")
            VALUES (? , $placeholders)
            ON DUPLICATE KEY UPDATE $updateClause";

    $params = array_merge([$idPaquete], array_values($payload));

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return true;
    } catch (Exception $e) {
        error_log('Error guardando detalles extra de paquete ' . $idPaquete . ': ' . $e->getMessage());
        return false;
    }
}

function getPackageCoverImageUrl($idPaquete, array $candidatos = []) {
    $idPaquete = intval($idPaquete);
    if ($idPaquete <= 0) {
        return null;
    }

    foreach ($candidatos as $candidate) {
        $url = getPackageImageUrl($candidate);
        if ($url) {
            return $url;
        }
    }

    $packagesDir = ASSETS_PATH . '/img/packages/';
    if (is_dir($packagesDir)) {
        $extensiones = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        foreach ($extensiones as $ext) {
            $ruta = $packagesDir . "package-{$idPaquete}." . $ext;
            if (is_file($ruta)) {
                return ASSETS_URL . '/img/packages/' . "package-{$idPaquete}." . $ext;
            }
        }
    }

    return null;
}

function calcularDuracionMinutosHorarios(array $horarios) {
    $maxDuracion = 0;
    foreach ($horarios as $horario) {
        $horaInicio = $horario['hora_inicio'] ?? null;
        if (empty($horaInicio)) {
            continue;
        }
        $inicio = strtotime($horaInicio);
        if ($inicio === false) {
            continue;
        }
        $horaFin = $horario['hora_fin'] ?? null;
        if (empty($horaFin)) {
            $duracion = 60; // default a 1 hora si no se especifica fin
        } else {
            $fin = strtotime($horaFin);
            if ($fin === false) {
                continue;
            }
            if ($fin <= $inicio) {
                $fin += 24 * 60 * 60; // permitir horarios que cruzan medianoche
            }
            $duracion = ($fin - $inicio) / 60;
        }
        if ($duracion > $maxDuracion) {
            $maxDuracion = $duracion;
        }
    }
    return (int) round($maxDuracion);
}

function logActivity($message, $level = 'info') {
    $logFile = BASE_PATH . '/logs/activity.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user = $_SESSION['user_id'] ?? 'guest';
    
    $logMessage = "[$timestamp] [$level] [User: $user] [IP: $ip] - $message" . PHP_EOL;
    
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}
