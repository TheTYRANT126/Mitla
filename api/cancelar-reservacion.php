<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

// Obtener datos del formulario
$codigo_reservacion = isset($_POST['codigo_reservacion']) ? sanitize($_POST['codigo_reservacion']) : '';
$motivo = isset($_POST['motivo_cancelacion']) ? sanitize($_POST['motivo_cancelacion']) : null;

// Validar código
if (empty($codigo_reservacion)) {
    echo json_encode([
        'success' => false,
        'message' => 'Código de reservación no proporcionado'
    ]);
    exit;
}

try {
    $db = Database::getInstance();

    // Verificar que la reservación existe y puede ser cancelada
    $reservacion = $db->fetchOne(
        "SELECT id_reservacion, estado, fecha_tour
         FROM reservaciones
         WHERE codigo_reservacion = ?",
        [$codigo_reservacion]
    );

    if (!$reservacion) {
        echo json_encode([
            'success' => false,
            'message' => 'Reservación no encontrada'
        ]);
        exit;
    }

    // Verificar que no esté ya cancelada
    if ($reservacion['estado'] === 'cancelada') {
        echo json_encode([
            'success' => false,
            'message' => 'Esta reservación ya ha sido cancelada'
        ]);
        exit;
    }

    // Verificar que sea una fecha futura
    if (strtotime($reservacion['fecha_tour']) < time()) {
        echo json_encode([
            'success' => false,
            'message' => 'No se puede cancelar una reservación de un tour pasado'
        ]);
        exit;
    }

    // Iniciar transacción
    $db->beginTransaction();

    // Actualizar estado de la reservación
    $db->execute(
        "UPDATE reservaciones
         SET estado = 'cancelada',
             fecha_actualizacion = NOW()
         WHERE id_reservacion = ?",
        [$reservacion['id_reservacion']]
    );

    // Registrar el motivo si se proporcionó
    if ($motivo) {
        $db->insert(
            "INSERT INTO historial_reservaciones
             (id_reservacion, accion, descripcion, fecha)
             VALUES (?, 'cancelacion', ?, NOW())",
            [$reservacion['id_reservacion'], $motivo]
        );
    }

    // Liberar los guías asignados (si los hay)
    $db->execute(
        "DELETE FROM asignacion_guias WHERE id_reservacion = ?",
        [$reservacion['id_reservacion']]
    );

    // Confirmar transacción
    $db->commit();

    // TODO: Enviar email de confirmación de cancelación
    // TODO: Procesar reembolso si aplica

    logActivity("Reservación $codigo_reservacion cancelada");

    echo json_encode([
        'success' => true,
        'message' => 'Reservación cancelada exitosamente'
    ]);

} catch (Exception $e) {
    if ($db) {
        $db->rollback();
    }

    logActivity("Error al cancelar reservación $codigo_reservacion: " . $e->getMessage(), 'ERROR');

    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar la cancelación'
    ]);
}
