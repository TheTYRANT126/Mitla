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

// Obtener datos del paquete
$stmt = $db->prepare("SELECT * FROM paquetes WHERE id_paquete = ?");
$stmt->execute([$idPaquete]);
$paquete = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$paquete) {
    $_SESSION['mensaje'] = 'Paquete no encontrado';
    $_SESSION['mensaje_tipo'] = 'danger';
    redirect(SITE_URL . '/admin/paquetes/');
}

// Obtener horarios del paquete
$stmt = $db->prepare("SELECT * FROM horarios WHERE id_paquete = ? ORDER BY hora_inicio");
$stmt->execute([$idPaquete]);
$horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener imágenes de galería
$stmt = $db->prepare("SELECT * FROM imagenes_paquetes WHERE id_paquete = ? ORDER BY orden");
$stmt->execute([$idPaquete]);
$imagenesGaleria = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            'duracion' => intval($_POST['duracion'] ?? 0),
            'max_personas' => intval($_POST['max_personas'] ?? 0),
            'min_personas' => intval($_POST['min_personas'] ?? 1),
            'num_guias_requeridos' => intval($_POST['num_guias_requeridos'] ?? 1),
            'incluye' => trim($_POST['incluye'] ?? ''),
            'no_incluye' => trim($_POST['no_incluye'] ?? ''),
            'recomendaciones' => trim($_POST['recomendaciones'] ?? ''),
            'orden' => intval($_POST['orden'] ?? 0),
            'horarios' => []
        ];
        
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
        
        if ($datos['duracion'] <= 0) {
            $errores[] = 'La duración debe ser mayor a cero';
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
                
                $_SESSION['mensaje'] = 'Paquete actualizado correctamente';
                $_SESSION['mensaje_tipo'] = 'success';
                
                // Recargar datos del paquete
                $stmt = $db->prepare("SELECT * FROM paquetes WHERE id_paquete = ?");
                $stmt->execute([$idPaquete]);
                $paquete = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("SELECT * FROM horarios WHERE id_paquete = ? ORDER BY hora_inicio");
                $stmt->execute([$idPaquete]);
                $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("SELECT * FROM imagenes_paquetes WHERE id_paquete = ? ORDER BY orden");
                $stmt->execute([$idPaquete]);
                $imagenesGaleria = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } catch (Exception $e) {
                $errores[] = 'Error al actualizar el paquete: ' . $e->getMessage();
            }
        }
    }
}

// Generar nuevo CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$pageTitle = 'Editar Paquete';
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
        .horario-item {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f8f9fa;
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
                        <i class="fas fa-edit"></i> Editar Paquete
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
                                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Información Básica</h5>
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
                                        <div class="col-md-4">
                                            <label for="precio_base" class="form-label">
                                                Precio Base <span class="text-danger">*</span>
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
                                        
                                        <div class="col-md-4">
                                            <label for="duracion" class="form-label">
                                                Duración (minutos) <span class="text-danger">*</span>
                                            </label>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="duracion" 
                                                   name="duracion"
                                                   value="<?php echo $paquete['duracion']; ?>"
                                                   min="1"
                                                   required>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label for="orden" class="form-label">
                                                Orden de Visualización
                                            </label>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="orden" 
                                                   name="orden"
                                                   value="<?php echo $paquete['orden']; ?>"
                                                   min="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Capacidad y Guías -->
                            <div class="card shadow mb-4">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="fas fa-users"></i> Capacidad y Guías</h5>
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
                                    <h5 class="mb-0"><i class="fas fa-clock"></i> Horarios Disponibles</h5>
                                </div>
                                <div class="card-body">
                                    <div id="horariosContainer">
                                        <?php foreach ($horarios as $index => $horario): ?>
                                        <div class="horario-item" id="horario_<?php echo $index + 1; ?>">
                                            <div class="d-flex justify-content-between mb-2">
                                                <h6>Horario #<?php echo $index + 1; ?></h6>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="eliminarHorario(<?php echo $index + 1; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label class="form-label">Hora Inicio <span class="text-danger">*</span></label>
                                                    <input type="time" 
                                                           class="form-control" 
                                                           name="horarios[<?php echo $index + 1; ?>][hora_inicio]"
                                                           value="<?php echo substr($horario['hora_inicio'], 0, 5); ?>"
                                                           required>
                                                </div>
                                                
                                                <div class="col-md-6">
                                                    <label class="form-label">Hora Fin</label>
                                                    <input type="time" 
                                                           class="form-control" 
                                                           name="horarios[<?php echo $index + 1; ?>][hora_fin]"
                                                           value="<?php echo $horario['hora_fin'] ? substr($horario['hora_fin'], 0, 5) : ''; ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="mt-2">
                                                <label class="form-label">Días Disponibles</label>
                                                <select class="form-select" name="horarios[<?php echo $index + 1; ?>][dias_semana]">
                                                    <option value="todos" <?php echo $horario['dias_semana'] == 'todos' ? 'selected' : ''; ?>>Todos los días</option>
                                                    <option value="lunes_viernes" <?php echo $horario['dias_semana'] == 'lunes_viernes' ? 'selected' : ''; ?>>Lunes a Viernes</option>
                                                    <option value="fines_semana" <?php echo $horario['dias_semana'] == 'fines_semana' ? 'selected' : ''; ?>>Fines de Semana</option>
                                                    <option value="lunes" <?php echo $horario['dias_semana'] == 'lunes' ? 'selected' : ''; ?>>Solo Lunes</option>
                                                    <option value="martes" <?php echo $horario['dias_semana'] == 'martes' ? 'selected' : ''; ?>>Solo Martes</option>
                                                    <option value="miercoles" <?php echo $horario['dias_semana'] == 'miercoles' ? 'selected' : ''; ?>>Solo Miércoles</option>
                                                    <option value="jueves" <?php echo $horario['dias_semana'] == 'jueves' ? 'selected' : ''; ?>>Solo Jueves</option>
                                                    <option value="viernes" <?php echo $horario['dias_semana'] == 'viernes' ? 'selected' : ''; ?>>Solo Viernes</option>
                                                    <option value="sabado" <?php echo $horario['dias_semana'] == 'sabado' ? 'selected' : ''; ?>>Solo Sábado</option>
                                                    <option value="domingo" <?php echo $horario['dias_semana'] == 'domingo' ? 'selected' : ''; ?>>Solo Domingo</option>
                                                </select>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <button type="button" class="btn btn-outline-info" onclick="agregarHorario()">
                                        <i class="fas fa-plus"></i> Agregar Horario
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Detalles Adicionales -->
                            <div class="card shadow mb-4">
                                <div class="card-header bg-warning">
                                    <h5 class="mb-0"><i class="fas fa-list"></i> Detalles del Tour</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="incluye" class="form-label">
                                            ¿Qué Incluye?
                                        </label>
                                        <textarea class="form-control" 
                                                  id="incluye" 
                                                  name="incluye"
                                                  rows="4"><?php echo htmlspecialchars($paquete['incluye']); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="no_incluye" class="form-label">
                                            ¿Qué NO Incluye?
                                        </label>
                                        <textarea class="form-control" 
                                                  id="no_incluye" 
                                                  name="no_incluye"
                                                  rows="4"><?php echo htmlspecialchars($paquete['no_incluye']); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="recomendaciones" class="form-label">
                                            Recomendaciones
                                        </label>
                                        <textarea class="form-control" 
                                                  id="recomendaciones" 
                                                  name="recomendaciones"
                                                  rows="4"><?php echo htmlspecialchars($paquete['recomendaciones']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Columna Derecha -->
                        <div class="col-md-4">
                            <!-- Imagen Banner -->
                            <div class="card shadow mb-4">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="mb-0"><i class="fas fa-image"></i> Imagen Principal</h5>
                                </div>
                                <div class="card-body text-center">
                                    <?php if ($paquete['imagen_banner']): ?>
                                        <img id="preview_banner" 
                                             src="<?php echo ASSETS_URL; ?>/img/packages/<?php echo $paquete['imagen_banner']; ?>" 
                                             class="imagen-preview mb-3"
                                             alt="Banner">
                                    <?php else: ?>
                                        <img id="preview_banner" 
                                             src="<?php echo ASSETS_URL; ?>/img/no-image.png" 
                                             class="imagen-preview mb-3"
                                             alt="Preview">
                                    <?php endif; ?>
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
                                    <h5 class="mb-0"><i class="fas fa-images"></i> Galería</h5>
                                </div>
                                <div class="card-body">
                                    <!-- Imágenes existentes -->
                                    <?php if (!empty($imagenesGaleria)): ?>
                                    <div class="mb-3">
                                        <h6>Imágenes actuales:</h6>
                                        <div id="galeria_actual">
                                            <?php foreach ($imagenesGaleria as $imagen): ?>
                                            <div class="galeria-imagen" id="imagen_<?php echo $imagen['id_imagen']; ?>">
                                                <img src="<?php echo ASSETS_URL; ?>/img/packages/<?php echo $imagen['nombre_archivo']; ?>" 
                                                     alt="Galería">
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger btn-eliminar"
                                                        onclick="eliminarImagenGaleria(<?php echo $imagen['id_imagen']; ?>)">
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
    let horarioCount = <?php echo count($horarios); ?>;
    
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
    document.getElementById('imagenes_galeria').addEventListener('change', function(e) {
        const preview = document.getElementById('galeria_preview');
        preview.innerHTML = '';
        
        Array.from(e.target.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'img-thumbnail me-2 mb-2';
                img.style.width = '100px';
                img.style.height = '100px';
                img.style.objectFit = 'cover';
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
    });
    
    // Agregar horario
    function agregarHorario() {
        horarioCount++;
        
        const horarioHTML = `
            <div class="horario-item" id="horario_${horarioCount}">
                <div class="d-flex justify-content-between mb-2">
                    <h6>Horario #${horarioCount}</h6>
                    <button type="button" class="btn btn-sm btn-danger" onclick="eliminarHorario(${horarioCount})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Hora Inicio <span class="text-danger">*</span></label>
                        <input type="time" 
                               class="form-control" 
                               name="horarios[${horarioCount}][hora_inicio]"
                               required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Hora Fin</label>
                        <input type="time" 
                               class="form-control" 
                               name="horarios[${horarioCount}][hora_fin]">
                    </div>
                </div>
                
                <div class="mt-2">
                    <label class="form-label">Días Disponibles</label>
                    <select class="form-select" name="horarios[${horarioCount}][dias_semana]">
                        <option value="todos">Todos los días</option>
                        <option value="lunes_viernes">Lunes a Viernes</option>
                        <option value="fines_semana">Fines de Semana</option>
                        <option value="lunes">Solo Lunes</option>
                        <option value="martes">Solo Martes</option>
                        <option value="miercoles">Solo Miércoles</option>
                        <option value="jueves">Solo Jueves</option>
                        <option value="viernes">Solo Viernes</option>
                        <option value="sabado">Solo Sábado</option>
                        <option value="domingo">Solo Domingo</option>
                    </select>
                </div>
            </div>
        `;
        
        document.getElementById('horariosContainer').insertAdjacentHTML('beforeend', horarioHTML);
    }
    
    function eliminarHorario(id) {
        if (confirm('¿Está seguro de eliminar este horario?')) {
            document.getElementById(`horario_${id}`).remove();
        }
    }
    
    function eliminarImagenGaleria(idImagen) {
        if (!confirm('¿Está seguro de eliminar esta imagen de la galería?')) {
            return;
        }
        
        fetch('<?php echo SITE_URL; ?>/api/admin/eliminar-imagen-galeria.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id_imagen: idImagen
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById(`imagen_${idImagen}`).remove();
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
        
        // Verificar que haya al menos un horario
        const horarios = document.querySelectorAll('.horario-item');
        if (horarios.length === 0) {
            e.preventDefault();
            alert('Debe agregar al menos un horario');
            return false;
        }
    });
    </script>
</body>
</html>