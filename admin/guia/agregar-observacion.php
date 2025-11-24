<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isGuia()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id_reservacion']) || !isset($data['observacion'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$idReservacion = intval($data['id_reservacion']);
$observacion = trim($data['observacion']);

if (empty($observacion)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La observación no puede estar vacía']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener ID del guía actual
    $stmt = $db->prepare("SELECT id_guia FROM guias WHERE id_usuario = ?");
    $stmt->execute([$auth->getUserId()]);
    $guiaActual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$guiaActual) {
        throw new Exception('Perfil de guía no encontrado');
    }
    
    // Verificar que el guía esté asignado a este tour
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM asignacion_guias 
        WHERE id_reservacion = ? AND id_guia = ?
    ");
    $stmt->execute([$idReservacion, $guiaActual['id_guia']]);
    
    if ($stmt->fetchColumn() == 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes acceso a este tour']);
        exit;
    }
    
    // Insertar observación
    $stmt = $db->prepare("
        INSERT INTO observaciones_guia (id_reservacion, id_guia, observacion, fecha_observacion, leido_admin)
        VALUES (?, ?, ?, NOW(), 0)
    ");
    $stmt->execute([$idReservacion, $guiaActual['id_guia'], $observacion]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Observación agregada correctamente'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}