<?php


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

if (!isset($data['id_reservacion']) || !isset($data['monto']) || !isset($data['metodo'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$idReservacion = intval($data['id_reservacion']);
$monto = floatval($data['monto']);
$metodo = $data['metodo'];
$notas = $data['notas'] ?? '';

// Validar método
$metodosValidos = ['efectivo', 'transferencia', 'tarjeta', 'otro'];
if (!in_array($metodo, $metodosValidos)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Método de reembolso no válido']);
    exit;
}

if ($monto <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El monto debe ser mayor a cero']);
    exit;
}

try {
    $reservacionClass = new ReservacionAdmin();
    $resultado = $reservacionClass->procesarReembolso($idReservacion, $monto, $metodo, $notas);
    
    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => 'Reembolso procesado correctamente'
        ]);
    } else {
        throw new Exception('No se pudo procesar el reembolso');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}