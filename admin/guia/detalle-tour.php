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

$idReservacion = intval($_GET['id'] ?? 0);

if ($idReservacion === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de reservación requerido']);
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
    
    // Obtener información del tour
    $stmt = $db->prepare("
        SELECT r.*, 
               p.nombre as paquete_nombre, p.duracion,
               c.nombre as cliente_nombre, c.email as cliente_email, c.telefono as cliente_telefono
        FROM reservaciones r
        INNER JOIN paquetes p ON r.id_paquete = p.id_paquete
        INNER JOIN clientes c ON r.id_cliente = c.id_cliente
        WHERE r.id_reservacion = ?
    ");
    $stmt->execute([$idReservacion]);
    $tour = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener otros guías asignados
    $stmt = $db->prepare("
        SELECT g.nombre_completo
        FROM asignacion_guias ag
        INNER JOIN guias g ON ag.id_guia = g.id_guia
        WHERE ag.id_reservacion = ? AND ag.id_guia != ?
    ");
    $stmt->execute([$idReservacion, $guiaActual['id_guia']]);
    $otrosGuias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener observaciones
    $stmt = $db->prepare("
        SELECT og.*, g.nombre_completo as guia_nombre
        FROM observaciones_guia og
        INNER JOIN guias g ON og.id_guia = g.id_guia
        WHERE og.id_reservacion = ?
        ORDER BY og.fecha_observacion DESC
    ");
    $stmt->execute([$idReservacion]);
    $observaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear fechas
    $tour['fecha_reservacion'] = date('d/m/Y', strtotime($tour['fecha_reservacion']));
    $tour['hora_inicio'] = date('H:i', strtotime($tour['hora_inicio']));
    
    foreach ($observaciones as &$obs) {
        $obs['fecha_observacion'] = date('d/m/Y H:i', strtotime($obs['fecha_observacion']));
    }
    
    echo json_encode([
        'success' => true,
        'data' => array_merge($tour, [
            'otros_guias' => $otrosGuias,
            'observaciones' => $observaciones
        ])
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}