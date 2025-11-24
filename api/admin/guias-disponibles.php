<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Guia.php';

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Obtener parámetros
$fecha = $_GET['fecha'] ?? '';
$horaInicio = $_GET['hora_inicio'] ?? '';
$idioma = $_GET['idioma'] ?? '';
$numGuias = intval($_GET['num_guias'] ?? 1);

if (empty($fecha) || empty($horaInicio)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Fecha y hora son requeridos']);
    exit;
}

try {
    $guiaClass = new Guia();
    $guias = $guiaClass->obtenerSugeridos($fecha, $horaInicio, $idioma, $numGuias);
    
    echo json_encode([
        'success' => true,
        'guias' => $guias
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}