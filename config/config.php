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

function logActivity($message, $level = 'info') {
    $logFile = BASE_PATH . '/logs/activity.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user = $_SESSION['user_id'] ?? 'guest';
    
    $logMessage = "[$timestamp] [$level] [User: $user] [IP: $ip] - $message" . PHP_EOL;
    
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}