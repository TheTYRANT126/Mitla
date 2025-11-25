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
           r.fecha_tour AS fecha_reservacion,
           r.idioma_tour AS idioma,
           r.numero_guias_requeridos AS num_guias_requeridos,
           c.nombre_completo AS cliente_nombre,
           p.nombre_paquete AS paquete_nombre,
           p.personas_por_guia
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
$guiasSeleccionadosIds = array_map('intval', array_column($guiasActuales, 'id_guia'));

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
    
    if (!empty($guiasSeleccionadosIds)) {
        $idsSugeridos = array_map('intval', array_column($guiasSugeridos, 'id_guia'));
        $idsFaltantes = array_diff($guiasSeleccionadosIds, $idsSugeridos);
        
        if (!empty($idsFaltantes)) {
            $placeholders = implode(',', array_fill(0, count($idsFaltantes), '?'));
            $params = array_merge(
                [
                    $reservacion['fecha_reservacion'],
                    $reservacion['hora_inicio'],
                    $reservacion['idioma']
                ],
                $idsFaltantes
            );
            
            $extraStmt = $db->prepare("
                SELECT g.*,
                       GROUP_CONCAT(DISTINCT gi.idioma SEPARATOR ', ') as idiomas,
                       CASE 
                           WHEN EXISTS (
                               SELECT 1 
                               FROM asignacion_guias ag2
                               INNER JOIN reservaciones r2 ON ag2.id_reservacion = r2.id_reservacion
                               WHERE ag2.id_guia = g.id_guia
                               AND r2.fecha_tour = ?
                               AND r2.hora_inicio = ?
                               AND r2.estado IN ('confirmada','pagada')
                           ) THEN 0
                           ELSE 1
                       END AS disponible,
                       CASE WHEN EXISTS (
                           SELECT 1
                           FROM guia_idiomas gi2
                           WHERE gi2.id_guia = g.id_guia
                           AND gi2.idioma = ?
                       ) THEN 1 ELSE 0 END AS preferido
                FROM guias g
                INNER JOIN usuarios u ON g.id_usuario = u.id_usuario
                LEFT JOIN guia_idiomas gi ON g.id_guia = gi.id_guia
                WHERE g.id_guia IN ($placeholders)
                GROUP BY g.id_guia
            ");
            
            $extraStmt->execute($params);
            $guiasExtra = $extraStmt->fetchAll(PDO::FETCH_ASSOC);
            $guiasSugeridos = array_merge($guiasExtra, $guiasSugeridos);
        }
    }
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
            $maxGuiasPermitidos = (int)$reservacion['num_guias_requeridos'];
            
            if (count($idsGuias) > $maxGuiasPermitidos) {
                $errores[] = 'No puede asignar más guías de los requeridos para esta reservación.';
            }
            
            if (empty($errores)) {
                try {
                    // Eliminar asignaciones anteriores
                    $stmt = $db->prepare("DELETE FROM asignacion_guias WHERE id_reservacion = ?");
                    $stmt->execute([$idReservacion]);
                    
                    // Crear nuevas asignaciones usando la API
                    $sessionCookie = session_name() . '=' . session_id();
                    $sessionClosed = false;
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_write_close();
                        $sessionClosed = true;
                    }

                    $ch = curl_init(SITE_URL . '/api/admin/asignar-guias.php');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                        'id_reservacion' => $idReservacion,
                        'id_guias' => $idsGuias
                    ]));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Cookie: ' . $sessionCookie
                    ]);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($sessionClosed) {
                        session_start();
                    }
                    
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
        .guia-phone {
            font-size: 1rem;
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
                        Asignar Guías
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
                                Seleccionar Guías
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-3">

                                Haga clic en las tarjetas para seleccionar los guías. 
                                Los guías <span class="badge bg-warning">destacados</span> están disponibles y hablan el idioma requerido.
                            </p>
                            <?php if (!empty($guiasActuales)): ?>
                                <div class="alert alert-warning d-flex align-items-center">
                                    <i class="fas fa-envelope-open-text me-2"></i>
                                    <span>Al quitar o cambiar un guía se enviará un nuevo correo al cliente con la actualización.</span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (empty($guiasSugeridos)): ?>
                                <div class="alert alert-warning">
                                    No hay guías disponibles para esta fecha y hora.
                                    Puede seleccionar manualmente de la lista de todos los guías activos.
                                </div>
                            <?php endif; ?>
                            
                            <div class="row" id="guiasContainer">
                                <?php foreach ($guiasSugeridos as $guia): ?>
                                <?php 
                                    $esPreferido = !empty($guia['preferido']);
                                    $estaDisponible = isset($guia['disponible']) ? (bool)$guia['disponible'] : true;
                                    $estaAsignado = in_array((int)$guia['id_guia'], $guiasSeleccionadosIds, true);
                                ?>
                                <div class="col-md-6">
                                    <div class="guia-card position-relative <?php echo $esPreferido ? 'sugerido' : ''; ?> <?php echo !$estaDisponible ? 'ocupado' : ''; ?> <?php echo $estaAsignado ? 'selected' : ''; ?>"
                                         data-id-guia="<?php echo $guia['id_guia']; ?>"
                                         onclick="toggleGuia(this)">
                                        
                                        <?php if ($esPreferido): ?>
                                        <span class="badge bg-warning badge-sugerido">
                                            <i class="fas fa-star"></i> Sugerido
                                        </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!$estaDisponible): ?>
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
                                                    <span class="guia-phone"><?php echo htmlspecialchars($guia['telefono']); ?></span>
                                                </p>
                                                <p class="mb-2">
                                                    <?php 
                                                    $idiomas = !empty($guia['idiomas'])
                                                        ? array_filter(preg_split('/\s*,\s*/', trim($guia['idiomas'])))
                                                        : [];
                                                    if ($idiomas):
                                                        foreach ($idiomas as $idioma): ?>
                                                            <span class="badge bg-info text-dark fs-6 me-2 d-inline-block"><?php echo ucfirst($idioma); ?></span>
                                                        <?php endforeach;
                                                    else: ?>
                                                        <span class="text-muted">Sin idiomas registrados</span>
                                                    <?php endif; ?>
                                                </p>
                                                <?php if (!$estaDisponible): ?>
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
                                                       style="width: 25px; height: 25px;"
                                                       <?php echo $estaAsignado ? 'checked' : ''; ?>>
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
                                    <p class="mb-0 fs-4">
                                        <strong>Guías seleccionados:</strong>
                                        <span id="contadorGuias" class="badge bg-primary fs-5">0</span> /
                                        <span class="badge bg-secondary fs-6"><?php echo $reservacion['num_guias_requeridos']; ?> requeridos</span>
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
    const GUIAS_REQUERIDOS = <?php echo (int)$reservacion['num_guias_requeridos']; ?>;
    const MENSAJE_LIMITE = GUIAS_REQUERIDOS === 1
        ? 'Este tour solo necesita asignar un guía.'
        : `Este tour solo necesita asignar hasta ${GUIAS_REQUERIDOS} guías.`;

    function limiteAlcanzado() {
        if (GUIAS_REQUERIDOS <= 0) {
            return false;
        }
        return document.querySelectorAll('input[name="guias[]"]:checked').length >= GUIAS_REQUERIDOS;
    }

    function toggleGuia(card) {
        const checkbox = card.querySelector('.guia-checkbox');
        if (!checkbox) {
            return;
        }

        if (!checkbox.checked) {
            if (limiteAlcanzado()) {
                alert(MENSAJE_LIMITE);
                return;
            }
            checkbox.checked = true;
        } else {
            checkbox.checked = false;
        }
        updateCardState(card, checkbox.checked);
        actualizarContador();
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
            if (this.checked && GUIAS_REQUERIDOS > 0) {
                const seleccionados = document.querySelectorAll('input[name="guias[]"]:checked').length;
                if (seleccionados > GUIAS_REQUERIDOS) {
                    alert(MENSAJE_LIMITE);
                    this.checked = false;
                    e.preventDefault();
                    return;
                }
            }
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
