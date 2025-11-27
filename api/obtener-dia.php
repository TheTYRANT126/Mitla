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

if (empty($fecha)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
    exit;
}

try {
    $calendarioClass = new Calendario();

    // Si idPaquete es 0, obtener todas las reservaciones; si no, filtrar por paquete
    $paqueteFiltro = $idPaquete > 0 ? $idPaquete : null;

    // Obtener reservaciones del día
    $reservaciones = $calendarioClass->obtenerReservasDia($fecha, $paqueteFiltro);

    // Inicializar variables
    $desactivado = false;
    $motivoDesactivacion = '';
    $idDisponibilidad = null;
    $paquetesDesactivados = [];

    if ($idPaquete > 0) {
        // Verificar si está desactivado para un paquete específico
        $desactivado = !$calendarioClass->estaDisponible($idPaquete, $fecha, null);

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
    } else {
        // Si es modo "Todos los paquetes", obtener qué paquetes tienen el día desactivado
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT dc.id_disponibilidad, dc.id_paquete, dc.motivo, p.nombre_paquete
            FROM disponibilidad_calendario dc
            INNER JOIN paquetes p ON dc.id_paquete = p.id_paquete
            WHERE dc.fecha = ?
            AND dc.hora_inicio IS NULL
            AND dc.activo = 0
            ORDER BY p.nombre_paquete
        ");
        $stmt->execute([$fecha]);
        $paquetesDesactivados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'reservaciones' => $reservaciones,
            'desactivado' => $desactivado,
            'motivo_desactivacion' => $motivoDesactivacion,
            'id_disponibilidad' => $idDisponibilidad,
            'paquetes_desactivados' => $paquetesDesactivados
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
