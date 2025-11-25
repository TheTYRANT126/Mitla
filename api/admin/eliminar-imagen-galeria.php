<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';

header('Content-Type: application/json');

if (!function_exists('tableExists')) {
    function tableExists($pdoConnection, $tableName) {
        static $cache = [];
        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }
        try {
            $stmt = $pdoConnection->prepare("
                SELECT COUNT(*) 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                  AND table_name = ?
            ");
            $stmt->execute([$tableName]);
            $cache[$tableName] = $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            $cache[$tableName] = false;
        }
        return $cache[$tableName];
    }
}

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$idImagen = isset($data['id_imagen']) ? intval($data['id_imagen']) : null;
$filename = isset($data['filename']) ? trim(basename($data['filename'])) : null;
$idPaquete = isset($data['id_paquete']) ? intval($data['id_paquete']) : 0;

if (!$idImagen && (!$filename || $idPaquete <= 0)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Datos insuficientes para eliminar la imagen'
    ]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $tablaDisponible = tableExists($db, 'imagenes_paquetes');
    $directorioGaleria = getPackageGalleryDirectory();
    $galeriaFilesystemDisponible = (is_dir($directorioGaleria) && is_writable($directorioGaleria))
        || (!is_dir($directorioGaleria) && is_writable(dirname($directorioGaleria)));

    if ($idImagen && $tablaDisponible) {
        $stmt = $db->prepare("SELECT id_paquete, nombre_archivo FROM imagenes_paquetes WHERE id_imagen = ?");
        $stmt->execute([$idImagen]);
        $imagen = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$imagen) {
            throw new Exception('Imagen no encontrada');
        }

        if ($idPaquete <= 0) {
            $idPaquete = (int) ($imagen['id_paquete'] ?? 0);
        }
        
        $rutaArchivo = __DIR__ . '/../../assets/img/packages/' . $imagen['nombre_archivo'];
        if (!is_file($rutaArchivo)) {
            $rutaArchivo = __DIR__ . '/../../assets/img/paquete/' . $imagen['nombre_archivo'];
        }
        if (is_file($rutaArchivo)) {
            @unlink($rutaArchivo);
        }
        
        $stmt = $db->prepare("DELETE FROM imagenes_paquetes WHERE id_imagen = ?");
        $stmt->execute([$idImagen]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Imagen eliminada correctamente'
        ]);
        exit;
    }

    if ($filename && $idPaquete > 0 && $galeriaFilesystemDisponible) {
        $pattern = '/^galeria-' . $idPaquete . '-\d+\.(jpg|jpeg|png|gif|webp)$/i';
        if (!preg_match($pattern, $filename)) {
            throw new Exception('Nombre de archivo inválido');
        }

        $rutaArchivo = $directorioGaleria . '/' . $filename;
        if (!is_file($rutaArchivo)) {
            throw new Exception('El archivo especificado no existe');
        }

        if (!@unlink($rutaArchivo)) {
            throw new Exception('No se pudo eliminar el archivo de la galería');
        }

        normalizePackageGalleryIndices($idPaquete);

        echo json_encode([
            'success' => true,
            'message' => 'Imagen eliminada correctamente'
        ]);
        exit;
    }

    throw new Exception('La galería de imágenes no está disponible en esta instalación');
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
