<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/ReservacionAdmin.php';
require_once __DIR__ . '/../../classes/Database.php';

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id_reservacion']) || !isset($data['id_guias'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$idReservacion = intval($data['id_reservacion']);
$idGuias = array_map('intval', $data['id_guias']);

if (empty($idGuias)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Debe seleccionar al menos un guía']);
    exit;
}

try {
    $reservacionClass = new ReservacionAdmin();
    $resultado = $reservacionClass->asignarGuias($idReservacion, $idGuias, $auth->getUserId());
    
    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => 'Guías asignados correctamente. Se han enviado las notificaciones.'
        ]);
    } else {
        throw new Exception('No se pudo asignar los guías');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
