<?php


require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/PaqueteAdmin.php';
require_once __DIR__ . '/../../classes/Database.php';

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id_paquete']) || !isset($data['activo'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$idPaquete = intval($data['id_paquete']);
$activo = intval($data['activo']);

try {
    $paqueteClass = new PaqueteAdmin();
    $resultado = $paqueteClass->cambiarEstado($idPaquete, $activo);

    if ($resultado['success']) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO audit_log (id_usuario, accion, tabla_afectada, registro_id, ip, user_agent)
            VALUES (?, ?, 'paquetes', ?, ?, ?)
        ");
        $stmt->execute([
            $auth->getUserId(),
            $activo ? 'Paquete activado' : 'Paquete desactivado',
            $idPaquete,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        echo json_encode([
            'success' => true,
            'message' => $resultado['message']
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $resultado['message']
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}