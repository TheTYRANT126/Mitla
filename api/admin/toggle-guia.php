<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Guia.php';
require_once __DIR__ . '/../../classes/Database.php';

header('Content-Type: application/json');

$auth = new Auth();

// Verificar autenticación y permisos
if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Obtener datos
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id_guia']) || !isset($data['activo'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$idGuia = intval($data['id_guia']);
$activo = intval($data['activo']);

try {
    $guiaClass = new Guia();
    $resultado = $guiaClass->cambiarEstado($idGuia, $activo);
    
    if ($resultado) {
        // Registrar en audit log
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO audit_log (id_usuario, accion, tabla_afectada, registro_id, ip, user_agent)
            VALUES (?, ?, 'guias', ?, ?, ?)
        ");
        $stmt->execute([
            $auth->getUserId(),
            $activo ? 'Guía activado' : 'Guía desactivado',
            $idGuia,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => $activo ? 'Guía activado correctamente' : 'Guía desactivado correctamente'
        ]);
    } else {
        throw new Exception('No se pudo cambiar el estado del guía');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}