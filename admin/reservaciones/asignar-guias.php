<?php


require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Guia.php';
require_once __DIR__ . '/../../classes/Database.php';

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    redirect(SITE_URL . '/admin/login.php');
}

$idReservacion = intval($_GET['id'] ?? 0);

if ($idReservacion === 0) {
    $_SESSION['mensaje'] = 'ID de reservación no válido';
    $_SESSION['mensaje_tipo'] = 'danger';
    redirect(SITE_URL . '/admin/reservaciones/');
}

$db = Database::getInstance()->getConnection();

// Obtener información de la reservación
$stmt = $db->prepare("
    SELECT r.*, 
           c.nombre as cliente_nombre,
           p.nombre as paquete_nombre,
           p.num_guias_requeridos
    FROM reservaciones r
    INNER JOIN clientes c ON r.id_cliente = c.id_cliente
    INNER JOIN paquetes p ON r.id_paquete = p.id_paquete
    WHERE r.id_reservacion = ?
");
$stmt->execute([$idReservacion]);
$reservacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reservacion) {
    $_SESSION['mensaje'] = 'Reservación no encontrada';
    $_SESSION['mensaje_tipo'] = 'danger';
    redirect(SITE_URL . '/admin/reservaciones/');
}

// Verificar que la reservación no esté cancelada o completada
if (in_array($reservacion['estado'], ['cancelada', 'completada'])) {
    $_SESSION['mensaje'] = 'No se pueden asignar guías a una reservación ' . $reservacion['estado'];
    $_SESSION['mensaje_tipo'] = 'warning';
    redirect(SITE_URL . '/admin/reservaciones/detalle.php?id=' . $idReservacion);
}

// Obtener guías actualmente asignados
$stmt = $db->prepare("
    SELECT ag.*, g.nombre_completo
    FROM asignacion_guias ag
    INNER JOIN guias g ON ag.id_guia = g.id_guia
    WHERE ag.id_reservacion = ?
");
$stmt->execute([$idReservacion]);
$guiasActuales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$errores = [];
$guiasSugeridos = [];

// Obtener guías sugeridos
$guiaClass = new Guia();
try {
    $guiasSugeridos = $guiaClass->obtenerSugeridos(
        $reservacion['fecha_reservacion'],
        $reservacion['hora_inicio'],
        $reservacion['idioma'],
        $reservacion['num_guias_requeridos']
    );
} catch (Exception $e) {
    $errores[] = 'Error al obtener guías sugeridos: ' . $e->getMessage();
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errores[] = 'Token de seguridad inválido';
    } else {
        $idsGuias = $_POST['guias'] ?? [];
        
        if (empty($idsGuias)) {
            $errores[] = 'Debe seleccionar al menos un guía';
        } else {
            // Validar que los IDs sean numéricos
            $idsGuias = array_map('intval', $idsGuias);
            
            if (empty($errores)) {
                try {
                    // Eliminar asignaciones anteriores
                    $stmt = $db->prepare("DELETE FROM asignacion_guias WHERE id_reservacion = ?");
                    $stmt->execute([$idReservacion]);
                    
                    // Crear nuevas asignaciones usando la API
                    $ch = curl_init(SITE_URL . '/api/admin/asignar-guias.php');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                        'id_reservacion' => $idReservacion,
                        'id_guias' => $idsGuias
                    ]));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Cookie: ' . session_name() . '=' . session_id()
                    ]);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode === 200) {
                        $_SESSION['mensaje'] = 'Guías asignados correctamente. Se han enviado las notificaciones.';
                        $_SESSION['mensaje_tipo'] = 'success';
                        redirect(SITE_URL . '/admin/reservaciones/detalle.php?id=' . $idReservacion);
                    } else {
                        $errores[] = 'Error al asignar los guías';
                    }
                    
                } catch (Exception $e) {
                    $errores[] = 'Error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Generar nuevo CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$pageTitle = 'Asignar Guías';
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
        .guia-card {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .guia-card:hover {
            border-color: #0066cc;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .guia-card.selected {
            border-color: #28a745;
            background-color: #f0fff4;
        }
        .guia-card.sugerido {
            border-color: #ffc107;
            background-color: #fffef0;
        }
        .guia-card.ocupado {
            opacity: 0.6;
            border-color: #dc3545;
        }
        .guia-foto {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
        }
        .badge-sugerido {
            position: absolute;
            top: 10px;
            right: 10px;
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
                        <i class="fas fa-user-plus"></i> Asignar Guías
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="detalle.php?id=<?php echo $idReservacion; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver a Detalle
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
                
                <!-- Información de la Reservación -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-ticket-alt"></i> 
                            Reservación: <?php echo htmlspecialchars($reservacion['codigo_reservacion']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Cliente:</strong><br>
                                <?php echo htmlspecialchars($reservacion['cliente_nombre']); ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Paquete:</strong><br>
                                <?php echo htmlspecialchars($reservacion['paquete_nombre']); ?>
                            </div>
                            <div class="col-md-2">
                                <strong>Fecha:</strong><br>
                                <?php echo date('d/m/Y', strtotime($reservacion['fecha_reservacion'])); ?>
                            </div>
                            <div class="col-md-2">
                                <strong>Hora:</strong><br>
                                <?php echo date('H:i', strtotime($reservacion['hora_inicio'])); ?>
                            </div>
                            <div class="col-md-2">
                                <strong>Idioma:</strong><br>
                                <span class="badge bg-info"><?php echo ucfirst($reservacion['idioma']); ?></span>
                            </div>
                        </div>
                        <hr>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle"></i>
                            <strong>Guías requeridos:</strong> <?php echo $reservacion['num_guias_requeridos']; ?>
                            <?php if (!empty($guiasActuales)): ?>
                                | <strong>Guías actualmente asignados:</strong> <?php echo count($guiasActuales); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($guiasActuales)): ?>
                <!-- Guías Actualmente Asignados -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-users"></i> Guías Actualmente Asignados
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($guiasActuales as $guia): ?>
                            <div class="col-md-6">
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i>
                                    <strong><?php echo htmlspecialchars($guia['nombre_completo']); ?></strong>
                                    <br>
                                    <small>Asignado: <?php echo date('d/m/Y H:i', strtotime($guia['fecha_asignacion'])); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Formulario de Asignación -->
                <form method="POST" id="formAsignar">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="card shadow mb-4">
                        <div class="card-header bg-warning">
                            <h5 class="mb-0">
                                <i class="fas fa-hand-pointer"></i> Seleccionar Guías
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-3">
                                <i class="fas fa-info-circle text-primary"></i>
                                Haga clic en las tarjetas para seleccionar los guías. 
                                Los guías <span class="badge bg-warning">destacados</span> están disponibles y hablan el idioma requerido.
                            </p>
                            
                            <?php if (empty($guiasSugeridos)): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    No hay guías disponibles para esta fecha y hora.
                                    Puede seleccionar manualmente de la lista de todos los guías activos.
                                </div>
                            <?php endif; ?>
                            
                            <div class="row" id="guiasContainer">
                                <?php foreach ($guiasSugeridos as $guia): ?>
                                <div class="col-md-6">
                                    <div class="guia-card position-relative <?php echo $guia['preferido'] ? 'sugerido' : ''; ?> <?php echo !$guia['disponible'] ? 'ocupado' : ''; ?>"
                                         data-id-guia="<?php echo $guia['id_guia']; ?>"
                                         onclick="toggleGuia(this)">
                                        
                                        <?php if ($guia['preferido']): ?>
                                        <span class="badge bg-warning badge-sugerido">
                                            <i class="fas fa-star"></i> Sugerido
                                        </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!$guia['disponible']): ?>
                                        <span class="badge bg-danger badge-sugerido">
                                            <i class="fas fa-times"></i> Ocupado
                                        </span>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <?php if ($guia['foto_perfil']): ?>
                                                    <img src="<?php echo UPLOADS_URL; ?>/guias/<?php echo $guia['foto_perfil']; ?>" 
                                                         class="guia-foto"
                                                         alt="<?php echo htmlspecialchars($guia['nombre_completo']); ?>">
                                                <?php else: ?>
                                                    <div class="guia-foto bg-secondary d-flex align-items-center justify-content-center">
                                                        <i class="fas fa-user fa-2x text-white"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($guia['nombre_completo']); ?></h6>
                                                <p class="mb-1">
                                                    <i class="fas fa-phone text-success"></i>
                                                    <small><?php echo htmlspecialchars($guia['telefono']); ?></small>
                                                </p>
                                                <p class="mb-1">
                                                    <small>
                                                        <?php 
                                                        $idiomas = explode(', ', $guia['idiomas']);
                                                        foreach ($idiomas as $idioma): 
                                                        ?>
                                                            <span class="badge bg-info me-1"><?php echo ucfirst($idioma); ?></span>
                                                        <?php endforeach; ?>
                                                    </small>
                                                </p>
                                                <?php if (!$guia['disponible']): ?>
                                                <p class="mb-0">
                                                    <small class="text-danger">
                                                        <i class="fas fa-exclamation-circle"></i>
                                                        Ya tiene tours asignados en este horario
                                                    </small>
                                                </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <input type="checkbox" 
                                                       name="guias[]" 
                                                       value="<?php echo $guia['id_guia']; ?>"
                                                       class="form-check-input guia-checkbox"
                                                       style="width: 25px; height: 25px;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if (empty($guiasSugeridos)): ?>
                            <div class="alert alert-info mt-3">
                                <p class="mb-2"><strong>Lista de todos los guías activos:</strong></p>
                                <?php
                                // Obtener todos los guías activos
                                $todosGuias = $guiaClass->obtenerTodos(true);
                                ?>
                                <div class="row">
                                    <?php foreach ($todosGuias as $guia): ?>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   name="guias[]" 
                                                   value="<?php echo $guia['id_guia']; ?>"
                                                   id="guia_<?php echo $guia['id_guia']; ?>">
                                            <label class="form-check-label" for="guia_<?php echo $guia['id_guia']; ?>">
                                                <?php echo htmlspecialchars($guia['nombre_completo']); ?>
                                                <small class="text-muted">(<?php echo $guia['idiomas'] ?? 'Sin idiomas'; ?>)</small>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card shadow mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="mb-0">
                                        <strong>Guías seleccionados:</strong> 
                                        <span id="contadorGuias" class="badge bg-primary">0</span> / 
                                        <span class="badge bg-secondary"><?php echo $reservacion['num_guias_requeridos']; ?> requeridos</span>
                                    </p>
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-save"></i> Asignar Guías Seleccionados
                                    </button>
                                    <a href="detalle.php?id=<?php echo $idReservacion; ?>" class="btn btn-outline-secondary btn-lg">
                                        <i class="fas fa-times"></i> Cancelar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function toggleGuia(card) {
        const checkbox = card.querySelector('.guia-checkbox');
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            updateCardState(card, checkbox.checked);
            actualizarContador();
        }
    }
    
    function updateCardState(card, selected) {
        if (selected) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
    }
    
    function actualizarContador() {
        const checkboxes = document.querySelectorAll('input[name="guias[]"]:checked');
        document.getElementById('contadorGuias').textContent = checkboxes.length;
    }
    
    // Prevenir que el click en el checkbox propague al card
    document.querySelectorAll('.guia-checkbox').forEach(function(checkbox) {
        checkbox.addEventListener('click', function(e) {
            e.stopPropagation();
            const card = this.closest('.guia-card');
            updateCardState(card, this.checked);
            actualizarContador();
        });
        
        // Marcar como seleccionados los guías ya asignados
        if (checkbox.checked) {
            const card = checkbox.closest('.guia-card');
            updateCardState(card, true);
        }
    });
    
    // Validación antes de enviar
    document.getElementById('formAsignar').addEventListener('submit', function(e) {
        const checkboxes = document.querySelectorAll('input[name="guias[]"]:checked');
        
        if (checkboxes.length === 0) {
            e.preventDefault();
            alert('Debe seleccionar al menos un guía');
            return false;
        }
        
        const requeridos = <?php echo $reservacion['num_guias_requeridos']; ?>;
        if (checkboxes.length < requeridos) {
            if (!confirm(`Se requieren ${requeridos} guías pero solo ha seleccionado ${checkboxes.length}. ¿Desea continuar de todos modos?`)) {
                e.preventDefault();
                return false;
            }
        }
    });
    
    // Inicializar contador
    actualizarContador();
    </script>
</body>
</html>