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

if (!isset($data['id_paquete']) || !isset($data['fecha']) || !isset($data['motivo'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$idPaquete = intval($data['id_paquete']);
$fecha = $data['fecha'];
$motivo = $data['motivo'];
$horaInicio = $data['hora_inicio'] ?? null;
$horaFin = $data['hora_fin'] ?? null;

try {
    $calendarioClass = new Calendario();
    
    // Si no hay hora específica, desactivar el día completo
    if (empty($horaInicio)) {
        $resultado = $calendarioClass->desactivarDia($idPaquete, $fecha, $motivo);
    } else {
        $resultado = $calendarioClass->desactivarHorario($idPaquete, $fecha, $horaInicio, $horaFin, $motivo);
    }
    
    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => 'Fecha/horario desactivado correctamente'
        ]);
    } else {
        throw new Exception('No se pudo desactivar la fecha/horario');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}