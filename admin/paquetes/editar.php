<?php


require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/PaqueteAdmin.php';
require_once __DIR__ . '/../../classes/Database.php';

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    redirect(SITE_URL . '/admin/login.php');
}

$idPaquete = intval($_GET['id'] ?? 0);

if ($idPaquete === 0) {
    $_SESSION['mensaje'] = 'ID de paquete no válido';
    $_SESSION['mensaje_tipo'] = 'danger';
    redirect(SITE_URL . '/admin/paquetes/');
}

$db = Database::getInstance()->getConnection();

if (!function_exists('tableExists')) {
    /**
     * Verifica si existe una tabla en la base de datos actual.
     */
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

if (!function_exists('normalizarDatosPaquete')) {
    /**
     * Adapta un registro de paquete al nuevo formulario aun si viene del esquema antiguo.
     */
    function normalizarDatosPaquete(array $fila) {
        $normalizado = $fila;
        
        $normalizado['nombre'] = $fila['nombre'] ?? $fila['nombre_paquete'] ?? '';
        
        $descripcion = $fila['descripcion'] 
            ?? $fila['descripcion_es'] 
            ?? $fila['descripcion_en'] 
            ?? $fila['descripcion_fr'] 
            ?? '';
        $normalizado['descripcion'] = $descripcion;
        
        $descripcionCorta = $fila['descripcion_corta'] ?? '';
        if ($descripcionCorta === '' && $descripcion !== '') {
            $descripcionCorta = substr(strip_tags($descripcion), 0, 150);
        }
        $normalizado['descripcion_corta'] = $descripcionCorta;
        
        $precioBase = $fila['precio_base'] ?? null;
        if ($precioBase === null) {
            $precioGuia = isset($fila['precio_guia']) ? (float) $fila['precio_guia'] : 0;
            $precioEntrada = isset($fila['precio_entrada_persona']) ? (float) $fila['precio_entrada_persona'] : 0;
            $precioBase = ($precioGuia + $precioEntrada) > 0 ? $precioGuia + $precioEntrada : '';
        }
        $normalizado['precio_base'] = $precioBase === null ? '' : $precioBase;
        
        if (!isset($fila['duracion']) || $fila['duracion'] === null || $fila['duracion'] === '') {
            if (isset($fila['duracion_horas'])) {
                $normalizado['duracion'] = (int) round(floatval($fila['duracion_horas']) * 60);
            } else {
                $normalizado['duracion'] = 0;
            }
        } else {
            $normalizado['duracion'] = (int) $fila['duracion'];
        }
        
        $normalizado['min_personas'] = $fila['min_personas'] ?? 1;
        $normalizado['max_personas'] = $fila['max_personas'] ?? ($fila['capacidad_maxima'] ?? 0);
        $normalizado['num_guias_requeridos'] = $fila['num_guias_requeridos'] ?? ($fila['personas_por_guia'] ?? 1);
        $normalizado['incluye'] = $fila['incluye'] ?? '';
        $normalizado['no_incluye'] = $fila['no_incluye'] ?? '';
        $normalizado['recomendaciones'] = $fila['recomendaciones'] ?? '';
        
        return $normalizado;
    }
}

// Obtener datos del paquete
$stmt = $db->prepare("SELECT * FROM paquetes WHERE id_paquete = ?");
$stmt->execute([$idPaquete]);
$paquete = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$paquete) {
    $_SESSION['mensaje'] = 'Paquete no encontrado';
    $_SESSION['mensaje_tipo'] = 'danger';
    redirect(SITE_URL . '/admin/paquetes/');
}

// Normalizar datos del paquete para el formulario
$paquete = normalizarDatosPaquete($paquete);
$detallesTour = getPackageExtraDetails($idPaquete);
$mapaWidgetPersonalizado = trim($detallesTour['mapa_widget'] ?? '');
$mapaWidgetDefault = '';
if ($mapaWidgetPersonalizado === '') {
    if ($paquete['id_paquete'] == 1) {
        $mapaWidgetDefault = 'https://es.wikiloc.com/wikiloc/embedv2.do?id=181194053&elevation=off&images=off&maptype=H';
    } elseif ($paquete['id_paquete'] == 2) {
        $mapaWidgetDefault = 'https://es.wikiloc.com/wikiloc/embedv2.do?id=97179823&elevation=off&images=off&maptype=H';
    }
}
$mapaWidgetPreview = $mapaWidgetPersonalizado !== '' ? $mapaWidgetPersonalizado : $mapaWidgetDefault;
$detallesFormulario = [];

// Obtener horarios del paquete
$stmt = $db->prepare("SELECT * FROM horarios WHERE id_paquete = ? ORDER BY hora_inicio");
$stmt->execute([$idPaquete]);
$horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($horarios as &$horarioRef) {
    if (!isset($horarioRef['dias_semana'])) {
        $horarioRef['dias_semana'] = $horarioRef['dia_semana'] ?? 'todos';
    }
}
unset($horarioRef);
$diasSemanaListado = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'];
$diasMeta = [
    ['key' => 'lunes', 'label' => 'L', 'nombre' => 'Lunes'],
    ['key' => 'martes', 'label' => 'M', 'nombre' => 'Martes'],
    ['key' => 'miercoles', 'label' => 'Mi', 'nombre' => 'Miércoles'],
    ['key' => 'jueves', 'label' => 'J', 'nombre' => 'Jueves'],
    ['key' => 'viernes', 'label' => 'V', 'nombre' => 'Viernes'],
    ['key' => 'sabado', 'label' => 'S', 'nombre' => 'Sábado'],
    ['key' => 'domingo', 'label' => 'D', 'nombre' => 'Domingo']
];
$estadoHorariosInicial = [];
foreach ($diasSemanaListado as $dia) {
    $estadoHorariosInicial[$dia] = [
        'horarios' => [],
        'cerrado' => true
    ];
}
foreach ($horarios as $horario) {
    $dia = $horario['dia_semana'] ?? $horario['dias_semana'] ?? null;
    if (!$dia || !isset($estadoHorariosInicial[$dia])) {
        continue;
    }
    $estadoHorariosInicial[$dia]['horarios'][] = [
        'hora_inicio' => substr($horario['hora_inicio'], 0, 5),
        'hora_fin' => substr(($horario['hora_fin'] ?? $horario['hora_inicio']), 0, 5)
    ];
    $estadoHorariosInicial[$dia]['cerrado'] = false;
}
$estadoHorariosJSON = json_encode($estadoHorariosInicial, JSON_UNESCAPED_UNICODE);

// Obtener imágenes de galería si la tabla existe
$galeriaTablaDisponible = tableExists($db, 'imagenes_paquetes');
$directorioGaleria = getPackageGalleryDirectory();
$galeriaFilesystemDisponible = (is_dir($directorioGaleria) && is_writable($directorioGaleria))
    || (!is_dir($directorioGaleria) && is_writable(dirname($directorioGaleria)));
$galeriaDisponible = $galeriaTablaDisponible || $galeriaFilesystemDisponible;
$imagenesGaleria = [];
if ($galeriaTablaDisponible) {
    $stmt = $db->prepare("SELECT * FROM imagenes_paquetes WHERE id_paquete = ? ORDER BY orden");
    $stmt->execute([$idPaquete]);
    $imagenesGaleriaDB = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($imagenesGaleriaDB as $imagenDB) {
        $url = getPackageImageUrl($imagenDB['nombre_archivo']);
        if (!$url) {
            $url = ASSETS_URL . '/img/no-image.png';
        }
        $imagenesGaleria[] = [
            'id_imagen' => (int) $imagenDB['id_imagen'],
            'filename' => $imagenDB['nombre_archivo'],
            'url' => $url,
            'source' => 'database'
        ];
    }
} elseif ($galeriaFilesystemDisponible) {
    $imagenesGaleria = getPackageGalleryImages($idPaquete);
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errores[] = 'Token de seguridad inválido';
    } else {
        // Recoger datos
        $datos = [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'descripcion_corta' => trim($_POST['descripcion_corta'] ?? ''),
            'precio_base' => floatval($_POST['precio_base'] ?? 0),
            'max_personas' => intval($_POST['max_personas'] ?? 0),
            'min_personas' => intval($_POST['min_personas'] ?? 1),
            'num_guias_requeridos' => intval($_POST['num_guias_requeridos'] ?? 1),
            'incluye' => isset($_POST['incluye']) ? trim($_POST['incluye']) : ($paquete['incluye'] ?? ''),
            'no_incluye' => isset($_POST['no_incluye']) ? trim($_POST['no_incluye']) : ($paquete['no_incluye'] ?? ''),
            'recomendaciones' => trim($_POST['recomendaciones'] ?? ''),
            'horarios' => []
        ];

        $detallesFormulario = [
            'plan_visita' => trim($_POST['plan_visita'] ?? ''),
            'servicios_ofrecidos' => trim($_POST['servicios_ofrecidos'] ?? ''),
            'transporte' => trim($_POST['transporte'] ?? ''),
            'infraestructura' => trim($_POST['infraestructura'] ?? ''),
            'lugares_interes' => trim($_POST['lugares_interes'] ?? ''),
            'recomendaciones_visitantes' => trim($_POST['recomendaciones_visitantes'] ?? ''),
            'mapa_widget' => trim($_POST['mapa_widget'] ?? '')
        ];
        $detallesTour = array_merge($detallesTour, $detallesFormulario);
        
        // Procesar horarios
        if (isset($_POST['horarios']) && is_array($_POST['horarios'])) {
            foreach ($_POST['horarios'] as $horario) {
                if (!empty($horario['hora_inicio'])) {
                    $datos['horarios'][] = [
                        'hora_inicio' => $horario['hora_inicio'],
                        'hora_fin' => $horario['hora_fin'] ?? null,
                        'dias_semana' => $horario['dias_semana'] ?? 'todos'
                    ];
                }
            }
        }
        
        // Validaciones
        if (empty($datos['nombre'])) {
            $errores[] = 'El nombre es requerido';
        }
        
        if (empty($datos['descripcion'])) {
            $errores[] = 'La descripción es requerida';
        }
        
        if (empty($datos['descripcion_corta'])) {
            $errores[] = 'La descripción corta es requerida';
        }
        
        if ($datos['precio_base'] <= 0) {
            $errores[] = 'El precio debe ser mayor a cero';
        }
        
        if ($datos['max_personas'] <= 0) {
            $errores[] = 'El máximo de personas debe ser mayor a cero';
        }
        
        if ($datos['min_personas'] <= 0) {
            $errores[] = 'El mínimo de personas debe ser mayor a cero';
        }
        
        if ($datos['min_personas'] > $datos['max_personas']) {
            $errores[] = 'El mínimo de personas no puede ser mayor al máximo';
        }
        
        if ($datos['num_guias_requeridos'] <= 0) {
            $errores[] = 'El número de guías debe ser mayor a cero';
        }
        
        if (empty($datos['horarios'])) {
            $errores[] = 'Debe agregar al menos un horario';
        } else {
            $duracionCalculadaForm = calcularDuracionMinutosHorarios($datos['horarios']);
            if ($duracionCalculadaForm <= 0) {
                $errores[] = 'Los horarios deben tener una hora fin mayor a la hora de inicio para calcular la duración.';
            }
        }

        if (!empty($detallesFormulario['mapa_widget'])) {
            $mapaUrl = $detallesFormulario['mapa_widget'];
            if (!filter_var($mapaUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $mapaUrl)) {
                $errores[] = 'El enlace del mapa de recorrido debe ser una URL válida (http o https).';
            }
        }
        
        // Si no hay errores, actualizar el paquete
        if (empty($errores)) {
            try {
                $paqueteClass = new PaqueteAdmin();
                $paqueteClass->actualizar($idPaquete, $datos);
                
                // Subir nueva imagen banner si se proporcionó
                if (isset($_FILES['imagen_banner']) && $_FILES['imagen_banner']['error'] === UPLOAD_ERR_OK) {
                    $paqueteClass->subirImagen($idPaquete, $_FILES['imagen_banner'], 'banner');
                }
                
                // Subir nuevas imágenes de galería
                if (isset($_FILES['imagenes_galeria']) && is_array($_FILES['imagenes_galeria']['tmp_name'])) {
                    foreach ($_FILES['imagenes_galeria']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['imagenes_galeria']['error'][$key] === UPLOAD_ERR_OK) {
                            $archivo = [
                                'name' => $_FILES['imagenes_galeria']['name'][$key],
                                'type' => $_FILES['imagenes_galeria']['type'][$key],
                                'tmp_name' => $tmp_name,
                                'error' => $_FILES['imagenes_galeria']['error'][$key],
                                'size' => $_FILES['imagenes_galeria']['size'][$key]
                            ];
                            $paqueteClass->subirImagen($idPaquete, $archivo, 'galeria');
                        }
                    }
                }
                
                if (!savePackageExtraDetails($idPaquete, $detallesFormulario)) {
                    throw new Exception('No se pudieron guardar los detalles personalizados del tour');
                }

                $_SESSION['mensaje'] = 'Paquete actualizado correctamente';
                $_SESSION['mensaje_tipo'] = 'success';
                
                // Recargar datos del paquete
                $stmt = $db->prepare("SELECT * FROM paquetes WHERE id_paquete = ?");
                $stmt->execute([$idPaquete]);
                $paquete = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($paquete) {
                    $paquete = normalizarDatosPaquete($paquete);
                }
                
                $stmt = $db->prepare("SELECT * FROM horarios WHERE id_paquete = ? ORDER BY hora_inicio");
                $stmt->execute([$idPaquete]);
                $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($horarios as &$horarioRef) {
                    if (!isset($horarioRef['dias_semana'])) {
                        $horarioRef['dias_semana'] = $horarioRef['dia_semana'] ?? 'todos';
                    }
                }
                unset($horarioRef);
                
                $directorioGaleria = getPackageGalleryDirectory();
                $galeriaFilesystemDisponible = (is_dir($directorioGaleria) && is_writable($directorioGaleria))
                    || (!is_dir($directorioGaleria) && is_writable(dirname($directorioGaleria)));
                if ($galeriaTablaDisponible) {
                    $stmt = $db->prepare("SELECT * FROM imagenes_paquetes WHERE id_paquete = ? ORDER BY orden");
                    $stmt->execute([$idPaquete]);
                    $imagenesGaleriaDB = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $imagenesGaleria = [];
                    foreach ($imagenesGaleriaDB as $imagenDB) {
                        $url = getPackageImageUrl($imagenDB['nombre_archivo']);
                        if (!$url) {
                            $url = ASSETS_URL . '/img/no-image.png';
                        }
                        $imagenesGaleria[] = [
                            'id_imagen' => (int) $imagenDB['id_imagen'],
                            'filename' => $imagenDB['nombre_archivo'],
                            'url' => $url,
                            'source' => 'database'
                        ];
                    }
                } elseif ($galeriaFilesystemDisponible) {
                    $imagenesGaleria = getPackageGalleryImages($idPaquete);
                } else {
                    $imagenesGaleria = [];
                }

                $detallesTour = getPackageExtraDetails($idPaquete);
                $mapaWidgetPersonalizado = trim($detallesTour['mapa_widget'] ?? '');
                $mapaWidgetDefault = '';
                if ($mapaWidgetPersonalizado === '') {
                    if ($paquete['id_paquete'] == 1) {
                        $mapaWidgetDefault = 'https://es.wikiloc.com/wikiloc/embedv2.do?id=181194053&elevation=off&images=off&maptype=H';
                    } elseif ($paquete['id_paquete'] == 2) {
                        $mapaWidgetDefault = 'https://es.wikiloc.com/wikiloc/embedv2.do?id=97179823&elevation=off&images=off&maptype=H';
                    }
                }
                $mapaWidgetPreview = $mapaWidgetPersonalizado !== '' ? $mapaWidgetPersonalizado : $mapaWidgetDefault;
                
            } catch (Exception $e) {
                $errores[] = 'Error al actualizar el paquete: ' . $e->getMessage();
            }
        }
    }
}

// Generar nuevo CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$pageTitle = 'Editar Paquete';
$imagenesCandidatas = [];
if (!empty($paquete['imagen_banner'])) {
    $imagenesCandidatas[] = $paquete['imagen_banner'];
}
if (!empty($paquete['imagen'])) {
    $imagenesCandidatas[] = $paquete['imagen'];
}
$imagenBannerUrl = getPackageCoverImageUrl($paquete['id_paquete'], $imagenesCandidatas);
if (!$imagenBannerUrl) {
    $imagenBannerUrl = ASSETS_URL . '/img/no-image.png';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin/admin.css">
    
    <style>
        .imagen-preview {
            max-width: 300px;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 10px;
        }
        .dia-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .dia-btn {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            border: 2px solid #0dcaf0;
            background: #fff;
            color: #0dcaf0;
            font-weight: 600;
            transition: all 0.2s;
        }
        .dia-btn.active {
            background: #0dcaf0;
            color: #fff;
            box-shadow: 0 4px 12px rgba(13,202,240,0.3);
        }
        .dia-card {
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 15px;
            background: #f8f9fa;
            min-height: 150px;
        }
        .dia-card.cerrado {
            border-color: #f8d7da;
            background: #fff5f5;
        }
        .horario-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            border-radius: 20px;
            border: 1px solid #dee2e6;
            padding: 5px 12px;
            margin: 0 8px 8px 0;
            font-weight: 600;
        }
        .galeria-imagen {
            position: relative;
            display: inline-block;
            margin: 5px;
        }
        .galeria-imagen img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 5px;
        }
        .galeria-imagen .btn-eliminar {
            position: absolute;
            top: -5px;
            right: -5px;
        }
        .detalle-icono-label img {
            width: 28px;
            height: 28px;
            object-fit: contain;
            margin-right: 8px;
        }
        .detalle-icono-label {
            display: flex;
            align-items: center;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        Editar Paquete 
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver a Lista
                        </a>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['mensaje'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['mensaje_tipo'] ?? 'info'; ?> alert-dismissible fade show">
                        <?php 
                        echo htmlspecialchars($_SESSION['mensaje']); 
                        unset($_SESSION['mensaje']);
                        unset($_SESSION['mensaje_tipo']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errores)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <strong>Error:</strong>
                        <ul class="mb-0">
                            <?php foreach ($errores as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" id="formPaquete">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="row">
                        <!-- Columna Izquierda -->
                        <div class="col-md-8">
                            <!-- Información Básica -->
                            <div class="card shadow mb-4">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">Información Básica</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="nombre" class="form-label">
                                            Nombre del Paquete <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="nombre" 
                                               name="nombre"
                                               value="<?php echo htmlspecialchars($paquete['nombre']); ?>"
                                               required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="descripcion_corta" class="form-label">
                                            Descripción Corta <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="descripcion_corta" 
                                               name="descripcion_corta"
                                               value="<?php echo htmlspecialchars($paquete['descripcion_corta']); ?>"
                                               maxlength="150"
                                               required>
                                        <div class="form-text">Máximo 150 caracteres. Se muestra en la lista de paquetes.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="descripcion" class="form-label">
                                            Descripción Completa <span class="text-danger">*</span>
                                        </label>
                                        <textarea class="form-control" 
                                                  id="descripcion" 
                                                  name="descripcion"
                                                  rows="6"
                                                  required><?php echo htmlspecialchars($paquete['descripcion']); ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="precio_base" class="form-label">
                                                Precio Base en MXN<span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" 
                                                       class="form-control" 
                                                       id="precio_base" 
                                                       name="precio_base"
                                                       value="<?php echo $paquete['precio_base']; ?>"
                                                       min="1"
                                                       step="0.01"
                                                       required>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">
                                                Duración estimada
                                            </label>
                                            <?php 
                                                $duracionTexto = ($paquete['duracion'] ?? 0) > 0
                                                    ? $paquete['duracion'] . ' min'
                                                    : 'Sin calcular';
                                            ?>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="duracion_preview"
                                                   value="<?php echo htmlspecialchars($duracionTexto, ENT_QUOTES, 'UTF-8'); ?>"
                                                   readonly>
                                            <div class="form-text">La duración se actualiza automáticamente con los horarios.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Capacidad y Guías -->
                            <div class="card shadow mb-4">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">Capacidad y Guías</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label for="min_personas" class="form-label">
                                                Mínimo de Personas <span class="text-danger">*</span>
                                            </label>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="min_personas" 
                                                   name="min_personas"
                                                   value="<?php echo $paquete['min_personas']; ?>"
                                                   min="1"
                                                   required>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label for="max_personas" class="form-label">
                                                Máximo de Personas <span class="text-danger">*</span>
                                            </label>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="max_personas" 
                                                   name="max_personas"
                                                   value="<?php echo $paquete['max_personas']; ?>"
                                                   min="1"
                                                   required>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label for="num_guias_requeridos" class="form-label">
                                                Guías Requeridos <span class="text-danger">*</span>
                                            </label>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="num_guias_requeridos" 
                                                   name="num_guias_requeridos"
                                                   value="<?php echo $paquete['num_guias_requeridos']; ?>"
                                                   min="1"
                                                   required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Horarios Disponibles -->
                            <div class="card shadow mb-4">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">Horarios Disponibles</h5>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted">Selecciona los días, ingresa la hora y aplica el horario. También puedes marcar un día como cerrado para mostrarlo en la web.</p>
                                    <div class="mb-3">
                                        <div class="dia-selector" id="selectorDias">
                                            <?php foreach ($diasMeta as $dia): ?>
                                            <button type="button" 
                                                    class="dia-btn" 
                                                    data-dia="<?php echo $dia['key']; ?>"
                                                    title="<?php echo $dia['nombre']; ?>">
                                                <?php echo $dia['label']; ?>
                                            </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-3">
                                            <label class="form-label">Hora inicio</label>
                                            <input type="time" class="form-control" id="hora_general_inicio">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Hora fin</label>
                                            <input type="time" class="form-control" id="hora_general_fin">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Días seleccionados</label>
                                            <div id="infoDiasSeleccionados" class="form-text">Ningún día seleccionado</div>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-info w-100" id="btnAplicarHorario">
                                                <i class="fas fa-check"></i> Aplicar
                                            </button>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">Resumen por día</h6>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimpiarSeleccion">
                                            Limpiar selección
                                        </button>
                                    </div>
                                    <div id="resumenHorarios" class="row g-3"></div>
                                    <div id="horariosHidden"></div>
                                </div>
                            </div>
                            
                            <!-- Detalles Adicionales -->
                            <div class="card shadow mb-4">
                                <div class="card-header bg-warning">
                                    <h5 class="mb-0">Detalles del Tour</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="plan_visita" class="form-label detalle-icono-label">
                                            <img src="<?php echo ASSETS_URL; ?>/img/paquete/icono-plan.png" alt="Plan">
                                            Plan de visita
                                        </label>
                                        <textarea class="form-control"
                                                  id="plan_visita"
                                                  name="plan_visita"
                                                  rows="4"
                                                  placeholder="Si lo dejas vacío se mostrará la descripción principal del paquete."><?php echo htmlspecialchars($detallesTour['plan_visita']); ?></textarea>
                                        <div class="form-text">
                                            Se muestra en la ficha pública del paquete. Dejar en blanco para usar la descripción general.
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="servicios_ofrecidos" class="form-label detalle-icono-label">
                                            <img src="<?php echo ASSETS_URL; ?>/img/paquete/icono-servicios.png" alt="Servicios">
                                            Servicios ofrecidos
                                        </label>
                                        <textarea class="form-control"
                                                  id="servicios_ofrecidos"
                                                  name="servicios_ofrecidos"
                                                  rows="3"><?php echo htmlspecialchars($detallesTour['servicios_ofrecidos']); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="transporte" class="form-label detalle-icono-label">
                                            <img src="<?php echo ASSETS_URL; ?>/img/paquete/icono-transporte.png" alt="Transporte">
                                            Transporte
                                        </label>
                                        <textarea class="form-control"
                                                  id="transporte"
                                                  name="transporte"
                                                  rows="3"><?php echo htmlspecialchars($detallesTour['transporte']); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="infraestructura" class="form-label detalle-icono-label">
                                            <img src="<?php echo ASSETS_URL; ?>/img/paquete/icono-infraestructura.png" alt="Infraestructura">
                                            Infraestructura de visita
                                        </label>
                                        <textarea class="form-control"
                                                  id="infraestructura"
                                                  name="infraestructura"
                                                  rows="3"><?php echo htmlspecialchars($detallesTour['infraestructura']); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="lugares_interes" class="form-label detalle-icono-label">
                                            <img src="<?php echo ASSETS_URL; ?>/img/paquete/icono-lugares.png" alt="Lugares">
                                            Lugares cercanos de interés
                                        </label>
                                        <textarea class="form-control"
                                                  id="lugares_interes"
                                                  name="lugares_interes"
                                                  rows="3"><?php echo htmlspecialchars($detallesTour['lugares_interes']); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="recomendaciones_visitantes" class="form-label detalle-icono-label">
                                            <img src="<?php echo ASSETS_URL; ?>/img/paquete/icono-recomendaciones.png" alt="Recomendaciones">
                                            Recomendaciones para visitantes
                                        </label>
                                        <textarea class="form-control"
                                                  id="recomendaciones_visitantes"
                                                  name="recomendaciones_visitantes"
                                                  rows="3"><?php echo htmlspecialchars($detallesTour['recomendaciones_visitantes']); ?></textarea>
                                    </div>

                                    <hr>
                                    <div class="mb-3">
                                        <label for="recomendaciones" class="form-label">
                                            Recomendaciones internas
                                        </label>
                                        <textarea class="form-control" 
                                                  id="recomendaciones" 
                                                  name="recomendaciones"
                                                  rows="4"><?php echo htmlspecialchars($paquete['recomendaciones']); ?></textarea>
                                        <div class="form-text">No se muestra públicamente, úsalo para notas internas.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Configuración del Mapa de Recorrido -->
                            <div class="card shadow mb-4">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">Mapa de Recorrido</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="mapa_widget" class="form-label">
                                            Enlace del widget de mapa (Wikiloc u otro servicio)
                                        </label>
                                        <input type="url"
                                               class="form-control"
                                               id="mapa_widget"
                                               name="mapa_widget"
                                               value="<?php echo htmlspecialchars($detallesTour['mapa_widget']); ?>"
                                               placeholder="https://es.wikiloc.com/wikiloc/embedv2.do?id=XXXX">
                                        <div class="form-text">
                                            Pega la URL del iframe provisto por <a href="https://es.wikiloc.com/" target="_blank" rel="noopener">Wikiloc</a>. Si lo dejas vacío se usará el mapa predeterminado.
                                        </div>
                                    </div>
                                    <?php if (!empty($mapaWidgetPreview)): ?>
                                    <div class="ratio ratio-16x9 border rounded overflow-hidden">
                                        <iframe src="<?php echo htmlspecialchars($mapaWidgetPreview, ENT_QUOTES, 'UTF-8'); ?>" frameborder="0" scrolling="no"></iframe>
                                    </div>
                                    <?php else: ?>
                                    <p class="text-muted mb-0">Aún no hay un enlace configurado para este mapa.</p>
                                    <?php endif; ?>
                                    <p class="small text-muted mt-3 mb-0">
                                        Puedes generar o editar la ruta desde Wikiloc y pegar el nuevo enlace para que se actualice en la página pública.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Columna Derecha -->
                        <div class="col-md-4">
                            <!-- Imagen Banner -->
                            <div class="card shadow mb-4">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="mb-0">Imagen Principal</h5>
                                </div>
                                <div class="card-body text-center">
                                    <img id="preview_banner" 
                                         src="<?php echo htmlspecialchars($imagenBannerUrl, ENT_QUOTES, 'UTF-8'); ?>" 
                                         class="imagen-preview mb-3"
                                         alt="Banner">
                                    <div>
                                        <label for="imagen_banner" class="btn btn-outline-secondary">
                                            <i class="fas fa-upload"></i> Cambiar Imagen
                                        </label>
                                        <input type="file" 
                                               id="imagen_banner" 
                                               name="imagen_banner" 
                                               accept="image/*"
                                               class="d-none">
                                        <p class="text-muted small mt-2">Tamaño recomendado: 1200x600px</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Galería de Imágenes -->
                            <div class="card shadow mb-4">
                                <div class="card-header bg-dark text-white">
                                    <h5 class="mb-0">Galería</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (!$galeriaDisponible): ?>
                                    <div class="alert alert-warning">
                                        La galería de imágenes no se encuentra disponible en este momento.
                                        Verifique los permisos de escritura del directorio <code>assets/img/paquete</code>.
                                    </div>
                                    <?php else: ?>
                                    <?php if (!$galeriaTablaDisponible && $galeriaFilesystemDisponible): ?>
                                    <p class="text-muted small">
                                        Las imágenes se guardan como archivos locales (formato <strong>JPG</strong>) siguiendo el patrón
                                        <code>galeria-<?php echo $idPaquete; ?>-N.jpg</code> dentro de <code>assets/img/paquete</code>.
                                    </p>
                                    <?php endif; ?>
                                    <!-- Imágenes existentes -->
                                    <?php if (!empty($imagenesGaleria)): ?>
                                    <div class="mb-3">
                                        <h6>Imágenes actuales:</h6>
                                        <div id="galeria_actual">
                                            <?php foreach ($imagenesGaleria as $imagen): ?>
                                            <?php
                                                $imagenGaleriaUrl = $imagen['url'] ?? getPackageImageUrl($imagen['filename']);
                                                if (!$imagenGaleriaUrl) {
                                                    $imagenGaleriaUrl = ASSETS_URL . '/img/no-image.png';
                                                }
                                                $domSuffix = $imagen['id_imagen'] ?? preg_replace('/[^A-Za-z0-9_-]/', '_', $imagen['filename']);
                                                $domId = 'imagen_' . $domSuffix;
                                            ?>
                                            <div class="galeria-imagen" id="<?php echo htmlspecialchars($domId, ENT_QUOTES, 'UTF-8'); ?>" data-filename="<?php echo htmlspecialchars($imagen['filename'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <img src="<?php echo htmlspecialchars($imagenGaleriaUrl, ENT_QUOTES, 'UTF-8'); ?>" 
                                                     alt="Galería">
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger btn-eliminar"
                                                        onclick="eliminarImagenGaleria(<?php echo isset($imagen['id_imagen']) ? intval($imagen['id_imagen']) : 'null'; ?>, '<?php echo htmlspecialchars($imagen['filename'], ENT_QUOTES, 'UTF-8'); ?>')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <hr>
                                    <?php endif; ?>
                                    
                                    <label for="imagenes_galeria" class="btn btn-outline-dark w-100">
                                        <i class="fas fa-upload"></i> Agregar Nuevas Imágenes
                                    </label>
                                    <input type="file" 
                                           id="imagenes_galeria" 
                                           name="imagenes_galeria[]" 
                                           accept="image/*"
                                           multiple
                                           class="d-none">
                                    <div id="galeria_preview" class="mt-3"></div>
                                    <p class="text-muted small mt-2">Puede seleccionar múltiples imágenes</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Guardar Cambios
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    const diasConfig = <?php echo json_encode($diasMeta, JSON_UNESCAPED_UNICODE); ?>;
    let estadoDias = <?php echo $estadoHorariosJSON; ?>;
    const selectorDias = document.getElementById('selectorDias');
    const infoDiasSeleccionados = document.getElementById('infoDiasSeleccionados');
    const resumenHorarios = document.getElementById('resumenHorarios');
    const horariosHidden = document.getElementById('horariosHidden');
    const horaInicioField = document.getElementById('hora_general_inicio');
    const horaFinField = document.getElementById('hora_general_fin');
    const btnAplicarHorario = document.getElementById('btnAplicarHorario');
    const btnLimpiarSeleccion = document.getElementById('btnLimpiarSeleccion');
    const duracionPreviewInput = document.getElementById('duracion_preview');
    const diasSeleccionados = new Set();
    
    // Preview imagen banner
    document.getElementById('imagen_banner').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('preview_banner').src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Preview galería
    const inputGaleria = document.getElementById('imagenes_galeria');
    if (inputGaleria) {
        inputGaleria.addEventListener('change', function(e) {
            const preview = document.getElementById('galeria_preview');
            if (!preview) {
                return;
            }
            preview.innerHTML = '';
            
            Array.from(e.target.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    const img = document.createElement('img');
                    img.src = ev.target.result;
                    img.className = 'img-thumbnail me-2 mb-2';
                    img.style.width = '100px';
                    img.style.height = '100px';
                    img.style.objectFit = 'cover';
                    preview.appendChild(img);
                };
                reader.readAsDataURL(file);
            });
        });
    }

    function obtenerNombreDia(clave) {
        const dia = diasConfig.find(d => d.key === clave);
        return dia ? dia.nombre : clave;
    }

    function refrescarSeleccionVisual() {
        if (!selectorDias) return;
        selectorDias.querySelectorAll('.dia-btn').forEach(btn => {
            const dia = btn.getAttribute('data-dia');
            btn.classList.toggle('active', diasSeleccionados.has(dia));
        });
        if (infoDiasSeleccionados) {
            if (diasSeleccionados.size === 0) {
                infoDiasSeleccionados.textContent = 'Ningún día seleccionado';
            } else {
                infoDiasSeleccionados.textContent = Array.from(diasSeleccionados)
                    .map(obtenerNombreDia)
                    .join(', ');
            }
        }
    }

    function limpiarSeleccionDias() {
        diasSeleccionados.clear();
        refrescarSeleccionVisual();
    }

    function toggleDiaSeleccionado(dia) {
        if (diasSeleccionados.has(dia)) {
            diasSeleccionados.delete(dia);
        } else {
            diasSeleccionados.add(dia);
        }
        refrescarSeleccionVisual();
    }

    if (selectorDias) {
        selectorDias.addEventListener('click', (event) => {
            const boton = event.target.closest('.dia-btn');
            if (!boton) return;
            toggleDiaSeleccionado(boton.getAttribute('data-dia'));
        });
    }

    if (btnAplicarHorario) {
        btnAplicarHorario.addEventListener('click', () => {
            if (diasSeleccionados.size === 0) {
                alert('Seleccione al menos un día.');
                return;
            }
            const inicio = horaInicioField.value;
            const fin = horaFinField.value;
            if (!inicio || !fin) {
                alert('Defina hora de inicio y fin.');
                return;
            }
            const minutosInicio = convertirHoraAMinutos(inicio);
            const minutosFin = convertirHoraAMinutos(fin);
            if (minutosInicio === null || minutosFin === null) {
                alert('Horas inválidas');
                return;
            }
            diasSeleccionados.forEach(dia => {
                if (!estadoDias[dia]) {
                    estadoDias[dia] = {horarios: [], cerrado: true};
                }
                estadoDias[dia].horarios.push({hora_inicio: inicio, hora_fin: fin});
                estadoDias[dia].cerrado = false;
            });
            limpiarSeleccionDias();
            renderizarHorarios();
        });
    }

    if (btnLimpiarSeleccion) {
        btnLimpiarSeleccion.addEventListener('click', limpiarSeleccionDias);
    }

    if (resumenHorarios) {
        resumenHorarios.addEventListener('click', (event) => {
            const accion = event.target.closest('[data-action]');
            if (!accion) return;
            const dia = accion.getAttribute('data-dia');
            const actionType = accion.getAttribute('data-action');
            if (actionType === 'seleccionar-dia') {
                limpiarSeleccionDias();
                diasSeleccionados.add(dia);
                refrescarSeleccionVisual();
                if (horaInicioField) {
                    horaInicioField.focus();
                }
            } else if (actionType === 'toggle-cerrado') {
                if (!estadoDias[dia]) {
                    estadoDias[dia] = {horarios: [], cerrado: true};
                }
                const estabaCerrado = estadoDias[dia].cerrado;
                estadoDias[dia].horarios = [];
                estadoDias[dia].cerrado = !estabaCerrado;
                renderizarHorarios();
            } else if (actionType === 'eliminar-horario') {
                const index = parseInt(accion.getAttribute('data-index'), 10);
                if (estadoDias[dia] && estadoDias[dia].horarios[index]) {
                    estadoDias[dia].horarios.splice(index, 1);
                    if (estadoDias[dia].horarios.length === 0) {
                        estadoDias[dia].cerrado = true;
                    }
                    renderizarHorarios();
                }
            }
        });
    }

    function convertirHoraAMinutos(valor) {
        if (!valor) return null;
        const [horas, minutos] = valor.split(':').map(Number);
        if (Number.isNaN(horas) || Number.isNaN(minutos)) return null;
        return horas * 60 + minutos;
    }

    function calcularDuracionLocal() {
        let maxDuracion = 0;
        diasConfig.forEach(dia => {
            const estado = estadoDias[dia.key] || {horarios: []};
            (estado.horarios || []).forEach(h => {
                const inicio = convertirHoraAMinutos(h.hora_inicio);
                const fin = convertirHoraAMinutos(h.hora_fin);
                if (inicio === null || fin === null) return;
                let duracion = fin - inicio;
                if (duracion <= 0) {
                    duracion += 24 * 60;
                }
                if (duracion > maxDuracion) {
                    maxDuracion = duracion;
                }
            });
        });
        return maxDuracion;
    }

    function sincronizarHorariosHidden() {
        if (!horariosHidden) return;
        horariosHidden.innerHTML = '';
        let index = 0;
        diasConfig.forEach(dia => {
            const estado = estadoDias[dia.key] || {horarios: []};
            estado.horarios.forEach(horario => {
                horariosHidden.insertAdjacentHTML('beforeend', `
                    <input type="hidden" name="horarios[${index}][hora_inicio]" value="${horario.hora_inicio}">
                    <input type="hidden" name="horarios[${index}][hora_fin]" value="${horario.hora_fin}">
                    <input type="hidden" name="horarios[${index}][dias_semana]" value="${dia.key}">
                `);
                index++;
            });
        });
    }

    function renderizarHorarios() {
        if (!resumenHorarios) return;
        resumenHorarios.innerHTML = '';
        diasConfig.forEach(dia => {
            if (!estadoDias[dia.key]) {
                estadoDias[dia.key] = {horarios: [], cerrado: true};
            }
            const estado = estadoDias[dia.key];
            const card = document.createElement('div');
            card.className = 'col-md-6 col-lg-4';
            const estaCerrado = estado.horarios.length === 0 && estado.cerrado;
            let contenidoHorarios = '';
            if (estado.horarios.length > 0) {
                contenidoHorarios = estado.horarios.map((horario, idx) => `
                    <span class="horario-tag">
                        ${horario.hora_inicio} - ${horario.hora_fin}
                        <button type="button" class="btn btn-sm btn-link text-danger p-0" data-action="eliminar-horario" data-dia="${dia.key}" data-index="${idx}">
                            <i class="fas fa-times"></i>
                        </button>
                    </span>
                `).join('');
            } else {
                contenidoHorarios = `<span class="badge ${estaCerrado ? 'bg-danger' : 'bg-secondary'}">
                    ${estaCerrado ? 'Cerrado' : 'Sin horario'}
                </span>`;
            }
            card.innerHTML = `
                <div class="dia-card ${estaCerrado ? 'cerrado' : ''}">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>${dia.nombre}</strong>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary" data-action="seleccionar-dia" data-dia="${dia.key}">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button type="button" class="btn btn-outline-${estaCerrado ? 'success' : 'danger'}" data-action="toggle-cerrado" data-dia="${dia.key}">
                                ${estaCerrado ? 'Reabrir' : 'Cerrar'}
                            </button>
                        </div>
                    </div>
                    <div>${contenidoHorarios}</div>
                </div>
            `;
            resumenHorarios.appendChild(card);
        });
        sincronizarHorariosHidden();
        const duracion = calcularDuracionLocal();
        if (duracionPreviewInput) {
            duracionPreviewInput.value = duracion > 0 ? `${duracion} min` : 'Sin calcular';
        }
    }

    renderizarHorarios();
    
    function eliminarImagenGaleria(idImagen, filename) {
        if (!confirm('¿Está seguro de eliminar esta imagen de la galería?')) {
            return;
        }
        
        const payload = {
            id_paquete: <?php echo (int) $idPaquete; ?>
        };

        if (idImagen) {
            payload.id_imagen = idImagen;
        }
        if (filename) {
            payload.filename = filename;
        }

        fetch('<?php echo SITE_URL; ?>/api/admin/eliminar-imagen-galeria.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let elemento = null;
                if (idImagen) {
                    elemento = document.getElementById(`imagen_${idImagen}`);
                }
                if (!elemento && filename) {
                    const selectorSafeFilename = (filename || '').replace(/"/g, '\\"');
                    elemento = document.querySelector(`.galeria-imagen[data-filename="${selectorSafeFilename}"]`);
                }
                if (elemento) {
                    elemento.remove();
                }
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al eliminar la imagen');
        });
    }
    
    // Validación del formulario
    document.getElementById('formPaquete').addEventListener('submit', function(e) {
        const minPersonas = parseInt(document.getElementById('min_personas').value);
        const maxPersonas = parseInt(document.getElementById('max_personas').value);
        
        if (minPersonas > maxPersonas) {
            e.preventDefault();
            alert('El mínimo de personas no puede ser mayor al máximo');
            return false;
        }
        
        const contHorarios = document.getElementById('horariosHidden');
        if (!contHorarios || contHorarios.querySelectorAll('input').length === 0) {
            e.preventDefault();
            alert('Debe agregar al menos un horario');
            return false;
        }
    });
    </script>
</body>
</html>
