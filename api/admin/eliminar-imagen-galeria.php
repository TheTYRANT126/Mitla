<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id_imagen'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de imagen requerido']);
    exit;
}

$idImagen = intval($data['id_imagen']);

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener información de la imagen
    $stmt = $db->prepare("SELECT nombre_archivo FROM imagenes_paquetes WHERE id_imagen = ?");
    $stmt->execute([$idImagen]);
    $imagen = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$imagen) {
        throw new Exception('Imagen no encontrada');
    }
    
    // Eliminar archivo físico
    $rutaArchivo = __DIR__ . '/../../assets/img/packages/' . $imagen['nombre_archivo'];
    if (file_exists($rutaArchivo)) {
        unlink($rutaArchivo);
    }
    
    // Eliminar registro de la base de datos
    $stmt = $db->prepare("DELETE FROM imagenes_paquetes WHERE id_imagen = ?");
    $stmt->execute([$idImagen]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Imagen eliminada correctamente'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}