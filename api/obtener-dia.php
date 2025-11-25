<?php


require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Calendario.php';

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$fecha = $_GET['fecha'] ?? '';
$idPaquete = intval($_GET['id_paquete'] ?? 0);

if (empty($fecha) || $idPaquete === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
    exit;
}

try {
    $calendarioClass = new Calendario();
    
    // Obtener reservaciones del día
    $reservaciones = $calendarioClass->obtenerReservasDia($fecha, $idPaquete);
    
    // Verificar si está desactivado
    $desactivado = !$calendarioClass->estaDisponible($idPaquete, $fecha, null);
    $motivoDesactivacion = '';
    $idDisponibilidad = null;
    
    if ($desactivado) {
        $motivoDesactivacion = $calendarioClass->obtenerMotivo($idPaquete, $fecha, null);
        
        // Obtener ID de disponibilidad
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT id_disponibilidad 
            FROM disponibilidad_calendario 
            WHERE id_paquete = ? AND fecha = ? AND hora_inicio IS NULL AND activo = 0
            LIMIT 1
        ");
        $stmt->execute([$idPaquete, $fecha]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $idDisponibilidad = $result['id_disponibilidad'] ?? null;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'reservaciones' => $reservaciones,
            'desactivado' => $desactivado,
            'motivo_desactivacion' => $motivoDesactivacion,
            'id_disponibilidad' => $idDisponibilidad
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
