<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/ReservacionAdmin.php';
require_once __DIR__ . '/../../classes/Reporte.php';

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$periodo = $_GET['periodo'] ?? '7dias';

try {
    $reservacionClass = new ReservacionAdmin();
    $reporteClass = new Reporte();
    
    // Estadísticas principales
    $estadisticas = $reservacionClass->obtenerEstadisticas();
    
    // Datos para gráficas
    $datosGraficas = $reporteClass->datosGraficas($periodo);
    
    echo json_encode([
        'success' => true,
        'estadisticas' => $estadisticas,
        'graficas' => $datosGraficas
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}