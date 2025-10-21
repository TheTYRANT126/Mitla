<?php


require_once __DIR__ . '/../config/config.php';


header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido'
    ]);
    exit;
}

try {

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Datos inválidos');
    }
    
    $id_paquete = isset($input['id_paquete']) ? (int)$input['id_paquete'] : 0;
    $fecha = isset($input['fecha']) ? sanitize($input['fecha']) : '';
    $horarios = isset($input['horarios']) ? $input['horarios'] : [];
    
    if (!$id_paquete || !$fecha || empty($horarios)) {
        throw new Exception('Faltan parámetros requeridos');
    }
    

    $db = Database::getInstance();
    $paquete = $db->fetchOne(
        "SELECT capacidad_maxima FROM paquetes WHERE id_paquete = ? AND activo = 1",
        [$id_paquete]
    );
    
    if (!$paquete) {
        throw new Exception('Paquete no encontrado');
    }
    
    $capacidad_maxima = $paquete['capacidad_maxima'];
    $disponibilidad = [];
    
  
    foreach ($horarios as $horario) {
        $hora_inicio = $horario['hora_inicio'];
        $hora_fin = $horario['hora_fin'];
        
       
        $reservado = $db->fetchOne(
            "SELECT COALESCE(SUM(numero_personas), 0) as total_reservado
             FROM reservaciones 
             WHERE id_paquete = ? 
             AND fecha_tour = ? 
             AND hora_inicio = ?
             AND estado IN ('pendiente', 'confirmada', 'pagada')",
            [$id_paquete, $fecha, $hora_inicio]
        );
        
        $total_reservado = (int)$reservado['total_reservado'];
        $cupos_disponibles = $capacidad_maxima - $total_reservado;
        
        $disponibilidad[] = [
            'hora_inicio' => $hora_inicio,
            'hora_fin' => $hora_fin,
            'cupos_disponibles' => max(0, $cupos_disponibles),
            'capacidad_maxima' => $capacidad_maxima,
            'porcentaje_ocupacion' => ($total_reservado / $capacidad_maxima) * 100
        ];
    }
    

    echo json_encode([
        'success' => true,
        'disponibilidad' => $disponibilidad,
        'fecha' => $fecha
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}