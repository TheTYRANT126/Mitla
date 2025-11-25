<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/PaqueteAdmin.php';

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    redirect(SITE_URL . '/admin/login.php');
}

$errores = [];
$datos = [
    'nombre' => '',
    'descripcion' => '',
    'descripcion_corta' => '',
    'precio_base' => '',
    'max_personas' => '',
    'min_personas' => 1,
    'num_guias_requeridos' => 1,
    'incluye' => '',
    'no_incluye' => '',
    'recomendaciones' => '',
    'horarios' => []
];

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
            'incluye' => trim($_POST['incluye'] ?? ''),
            'no_incluye' => trim($_POST['no_incluye'] ?? ''),
            'recomendaciones' => trim($_POST['recomendaciones'] ?? ''),
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
        
        // Si no hay errores, crear el paquete
        if (empty($errores)) {
            try {
                $paqueteClass = new PaqueteAdmin();
                $resultado = $paqueteClass->crear($datos);
                
                if (!($resultado['success'] ?? false)) {
                    throw new Exception($resultado['message'] ?? 'No se pudo crear el paquete');
                }
                
                $idPaquete = intval($resultado['id_paquete'] ?? 0);
                if ($idPaquete <= 0) {
                    throw new Exception('No se pudo determinar el ID del paquete creado');
                }
                
                // Subir imagen banner si se proporcionó
                if (isset($_FILES['imagen_banner']) && $_FILES['imagen_banner']['error'] === UPLOAD_ERR_OK) {
                    $paqueteClass->subirImagen($idPaquete, $_FILES['imagen_banner'], 'banner');
                }
                
                // Subir imágenes de galería
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
                
                $_SESSION['mensaje'] = 'Paquete creado correctamente';
                $_SESSION['mensaje_tipo'] = 'success';
                redirect(SITE_URL . '/admin/paquetes/editar.php?id=' . $idPaquete);
                
            } catch (Exception $e) {
                $errores[] = 'Error al crear el paquete: ' . $e->getMessage();
            }
        }
    }
}

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
foreach ($diasMeta as $diaConfig) {
    $estadoHorariosInicial[$diaConfig['key']] = [
        'horarios' => [],
        'cerrado' => true
    ];
}
foreach ($datos['horarios'] as $horario) {
    $dia = $horario['dias_semana'] ?? null;
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
$duracionPreview = calcularDuracionMinutosHorarios($datos['horarios']);

// Generar nuevo CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$pageTitle = 'Crear Paquete';
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
                        Crear Nuevo Paquete
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver a Lista
                        </a>
                    </div>
                </div>
                
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
                                               value="<?php echo htmlspecialchars($datos['nombre']); ?>"
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
                                               value="<?php echo htmlspecialchars($datos['descripcion_corta']); ?>"
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
                                                  required><?php echo htmlspecialchars($datos['descripcion']); ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="precio_base" class="form-label">
                                                Precio Base <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" 
                                                       class="form-control" 
                                                       id="precio_base" 
                                                       name="precio_base"
                                                       value="<?php echo $datos['precio_base']; ?>"
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
                                                $duracionTexto = $duracionPreview > 0
                                                    ? $duracionPreview . ' min'
                                                    : 'Se calculará con los horarios';
                                            ?>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="duracion_preview"
                                                   value="<?php echo htmlspecialchars($duracionTexto, ENT_QUOTES, 'UTF-8'); ?>"
                                                   readonly>
                                            <div class="form-text">La duración se calcula automáticamente cuando define los horarios.</div>
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
                                                   value="<?php echo $datos['min_personas']; ?>"
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
                                                   value="<?php echo $datos['max_personas']; ?>"
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
                                                   value="<?php echo $datos['num_guias_requeridos']; ?>"
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
                                    <p class="text-muted">Selecciona los días y aplica el horario. Puedes repetir el proceso con distintos bloques o marcar un día como cerrado.</p>
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
                                        <label for="incluye" class="form-label">
                                            ¿Qué Incluye?
                                        </label>
                                        <textarea class="form-control" 
                                                  id="incluye" 
                                                  name="incluye"
                                                  rows="4"
                                                  placeholder="Ejemplo:&#10;- Guía certificado&#10;- Equipo de seguridad&#10;- Entrada a las cuevas"><?php echo htmlspecialchars($datos['incluye']); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="no_incluye" class="form-label">
                                            ¿Qué NO Incluye?
                                        </label>
                                        <textarea class="form-control" 
                                                  id="no_incluye" 
                                                  name="no_incluye"
                                                  rows="4"
                                                  placeholder="Ejemplo:&#10;- Transporte&#10;- Alimentos&#10;- Bebidas"><?php echo htmlspecialchars($datos['no_incluye']); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="recomendaciones" class="form-label">
                                            Recomendaciones
                                        </label>
                                        <textarea class="form-control" 
                                                  id="recomendaciones" 
                                                  name="recomendaciones"
                                                  rows="4"
                                                  placeholder="Ejemplo:&#10;- Ropa cómoda&#10;- Zapatos cerrados&#10;- Protector solar"><?php echo htmlspecialchars($datos['recomendaciones']); ?></textarea>
                                    </div>
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
                                         src="<?php echo ASSETS_URL; ?>/img/no-image.png" 
                                         class="imagen-preview mb-3"
                                         alt="Preview">
                                    <div>
                                        <label for="imagen_banner" class="btn btn-outline-secondary">
                                            <i class="fas fa-upload"></i> Seleccionar Imagen
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
                                    <label for="imagenes_galeria" class="btn btn-outline-dark w-100">
                                        <i class="fas fa-upload"></i> Seleccionar Imágenes
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
                                <i class="fas fa-save"></i> Crear Paquete
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
            infoDiasSeleccionados.textContent = diasSeleccionados.size
                ? Array.from(diasSeleccionados).map(obtenerNombreDia).join(', ')
                : 'Ningún día seleccionado';
        }
    }

    function limpiarSeleccionDias() {
        diasSeleccionados.clear();
        refrescarSeleccionVisual();
    }

    if (selectorDias) {
        selectorDias.addEventListener('click', (event) => {
            const boton = event.target.closest('.dia-btn');
            if (!boton) return;
            const dia = boton.getAttribute('data-dia');
            if (diasSeleccionados.has(dia)) {
                diasSeleccionados.delete(dia);
            } else {
                diasSeleccionados.add(dia);
            }
            refrescarSeleccionVisual();
        });
    }

    function convertirHoraAMinutos(valor) {
        if (!valor) return null;
        const [h, m] = valor.split(':').map(Number);
        if (Number.isNaN(h) || Number.isNaN(m)) return null;
        return h * 60 + m;
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
            estado.horarios.forEach(h => {
                horariosHidden.insertAdjacentHTML('beforeend', `
                    <input type="hidden" name="horarios[${index}][hora_inicio]" value="${h.hora_inicio}">
                    <input type="hidden" name="horarios[${index}][hora_fin]" value="${h.hora_fin}">
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
            const estaCerrado = estado.horarios.length === 0 && estado.cerrado;
            const card = document.createElement('div');
            card.className = 'col-md-6 col-lg-4';
            let contenido = '';
            if (estado.horarios.length > 0) {
                contenido = estado.horarios.map((horario, idx) => `
                    <span class="horario-tag">
                        ${horario.hora_inicio} - ${horario.hora_fin}
                        <button type="button" class="btn btn-sm btn-link text-danger p-0" data-action="eliminar-horario" data-dia="${dia.key}" data-index="${idx}">
                            <i class="fas fa-times"></i>
                        </button>
                    </span>
                `).join('');
            } else {
                contenido = `<span class="badge ${estaCerrado ? 'bg-danger' : 'bg-secondary'}">${estaCerrado ? 'Cerrado' : 'Sin horario'}</span>`;
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
                    <div>${contenido}</div>
                </div>
            `;
            resumenHorarios.appendChild(card);
        });
        sincronizarHorariosHidden();
        const duracion = calcularDuracionLocal();
        if (duracionPreviewInput) {
            duracionPreviewInput.value = duracion > 0 ? `${duracion} min` : 'Se calculará con los horarios';
        }
    }

    function manejarResumenClick(event) {
        const accion = event.target.closest('[data-action]');
        if (!accion) return;
        const dia = accion.getAttribute('data-dia');
        const tipo = accion.getAttribute('data-action');
        if (!estadoDias[dia]) {
            estadoDias[dia] = {horarios: [], cerrado: true};
        }
        if (tipo === 'seleccionar-dia') {
            limpiarSeleccionDias();
            diasSeleccionados.add(dia);
            refrescarSeleccionVisual();
            horaInicioField && horaInicioField.focus();
        } else if (tipo === 'toggle-cerrado') {
            estadoDias[dia].horarios = [];
            estadoDias[dia].cerrado = !estadoDias[dia].cerrado;
            renderizarHorarios();
        } else if (tipo === 'eliminar-horario') {
            const idx = parseInt(accion.getAttribute('data-index'), 10);
            estadoDias[dia].horarios.splice(idx, 1);
            if (estadoDias[dia].horarios.length === 0) {
                estadoDias[dia].cerrado = true;
            }
            renderizarHorarios();
        }
    }

    if (resumenHorarios) {
        resumenHorarios.addEventListener('click', manejarResumenClick);
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

    renderizarHorarios();
    
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
