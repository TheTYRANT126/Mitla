<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Calendario.php';
require_once __DIR__ . '/../../classes/Database.php';

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['fecha']) || !isset($data['motivo'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$fecha = $data['fecha'];
$motivo = $data['motivo'];

try {
    $db = Database::getInstance()->getConnection();

    // Obtener todos los paquetes activos
    $stmt = $db->query("SELECT id_paquete, nombre_paquete FROM paquetes WHERE activo = 1 ORDER BY nombre_paquete");
    $paquetes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($paquetes)) {
        throw new Exception('No hay paquetes activos');
    }

    $calendarioClass = new Calendario();
    $paquetesDesactivados = 0;
    $paquetesConReservas = [];
    $errores = [];

    // Intentar desactivar cada paquete
    foreach ($paquetes as $paquete) {
        $resultado = $calendarioClass->desactivarDia($paquete['id_paquete'], $fecha, $motivo);

        if ($resultado['success']) {
            $paquetesDesactivados++;
        } elseif (isset($resultado['tiene_reservas']) && $resultado['tiene_reservas']) {
            // Guardar paquetes que tienen reservas
            $paquetesConReservas[] = [
                'nombre' => $paquete['nombre_paquete'],
                'total_reservas' => $resultado['total_reservas']
            ];
        } else {
            // Otros errores
            $errores[] = $paquete['nombre_paquete'];
        }
    }

    // Preparar mensaje de respuesta
    $mensaje = '';
    if ($paquetesDesactivados > 0) {
        $mensaje = "Día desactivado correctamente en {$paquetesDesactivados} paquete(s).";
    }

    if (!empty($paquetesConReservas)) {
        $mensaje .= "\n\nLos siguientes paquetes tienen reservaciones y no se pudieron desactivar:\n";
        foreach ($paquetesConReservas as $paquete) {
            $mensaje .= "- {$paquete['nombre']}: {$paquete['total_reservas']} reserva(s)\n";
        }
    }

    if (!empty($errores)) {
        $mensaje .= "\n\nError al desactivar: " . implode(', ', $errores);
    }

    // Considerar éxito si al menos un paquete fue desactivado
    if ($paquetesDesactivados > 0) {
        logActivity("Día {$fecha} desactivado en {$paquetesDesactivados} paquete(s)");
        echo json_encode([
            'success' => true,
            'message' => $mensaje,
            'paquetes_desactivados' => $paquetesDesactivados,
            'paquetes_con_reservas' => count($paquetesConReservas),
            'detalles_reservas' => $paquetesConReservas
        ]);
    } else {
        throw new Exception($mensaje ?: 'No se pudo desactivar ningún paquete');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
