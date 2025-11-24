<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Calendario.php';

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id_disponibilidad'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de disponibilidad requerido']);
    exit;
}

$idDisponibilidad = intval($data['id_disponibilidad']);

try {
    $calendarioClass = new Calendario();
    $resultado = $calendarioClass->reactivar($idDisponibilidad);
    
    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => 'Fecha/horario reactivado correctamente'
        ]);
    } else {
        throw new Exception('No se pudo reactivar la fecha/horario');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}