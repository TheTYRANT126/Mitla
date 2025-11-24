<?php
/**
 * ============================================
 * RUTA: api/admin/cambiar-estado-reservacion.php
 * ============================================
 * API para cambiar el estado de una reservación
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/ReservacionAdmin.php';

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id_reservacion']) || !isset($data['nuevo_estado'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$idReservacion = intval($data['id_reservacion']);
$nuevoEstado = $data['nuevo_estado'];
$motivo = $data['motivo'] ?? '';

// Validar estado
$estadosValidos = ['pendiente', 'confirmada', 'pagada', 'cancelada', 'completada'];
if (!in_array($nuevoEstado, $estadosValidos)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Estado no válido']);
    exit;
}

try {
    $reservacionClass = new ReservacionAdmin();
    $resultado = $reservacionClass->cambiarEstado($idReservacion, $nuevoEstado, $motivo);
    
    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => 'Estado actualizado correctamente'
        ]);
    } else {
        throw new Exception('No se pudo cambiar el estado');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}