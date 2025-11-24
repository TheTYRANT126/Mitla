<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Guia.php';

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id_guia']) || !isset($data['comentario'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$idGuia = intval($data['id_guia']);
$comentario = trim($data['comentario']);

if (empty($comentario)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El comentario no puede estar vacío']);
    exit;
}

try {
    $guiaClass = new Guia();
    $resultado = $guiaClass->agregarComentario($idGuia, $comentario, $auth->getUserId());
    
    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => 'Comentario agregado correctamente'
        ]);
    } else {
        throw new Exception('No se pudo agregar el comentario');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}