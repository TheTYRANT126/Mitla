<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
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

// Obtener reservación completa
$stmt = $db->prepare("
    SELECT r.*,
           r.fecha_tour as fecha_reservacion,
           r.idioma_tour as idioma,
           r.total as monto_total,
           c.nombre_completo as cliente_nombre, c.email as cliente_email, c.telefono as cliente_telefono,
           p.nombre_paquete as paquete_nombre, p.descripcion_es as paquete_descripcion,
           p.duracion_horas as duracion, p.capacidad_maxima as max_personas
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

// Obtener guías asignados
$stmt = $db->prepare("
    SELECT g.*, u.email, gi.idioma
    FROM asignacion_guias ag
    INNER JOIN guias g ON ag.id_guia = g.id_guia
    INNER JOIN usuarios u ON g.id_usuario = u.id_usuario
    LEFT JOIN guia_idiomas gi ON g.id_guia = gi.id_guia
    WHERE ag.id_reservacion = ?
");
$stmt->execute([$idReservacion]);
$guiasAsignados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar idiomas por guía
$guiasAgrupados = [];
foreach ($guiasAsignados as $guia) {
    $idGuia = $guia['id_guia'];
    if (!isset($guiasAgrupados[$idGuia])) {
        $guiasAgrupados[$idGuia] = $guia;
        $guiasAgrupados[$idGuia]['idiomas'] = [];
    }
    if ($guia['idioma']) {
        $guiasAgrupados[$idGuia]['idiomas'][] = $guia['idioma'];
    }
}

// Obtener historial de estados
$stmt = $db->prepare("
    SELECT he.*, u.nombre_completo as usuario_nombre
    FROM historial_estados he
    LEFT JOIN usuarios u ON he.cambiado_por = u.id_usuario
    WHERE he.id_reservacion = ?
    ORDER BY he.fecha_cambio DESC
");
$stmt->execute([$idReservacion]);
$historialEstados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener reembolsos si existen
$stmt = $db->prepare("
    SELECT r.*, u.nombre_completo as procesado_por_nombre
    FROM reembolsos r
    LEFT JOIN usuarios u ON r.procesado_por = u.id_usuario
    WHERE r.id_reservacion = ?
    ORDER BY r.fecha_reembolso DESC
");
$stmt->execute([$idReservacion]);
$reembolsos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener observaciones de guías
$stmt = $db->prepare("
    SELECT og.*, g.nombre_completo as guia_nombre
    FROM observaciones_guia og
    INNER JOIN guias g ON og.id_guia = g.id_guia
    WHERE og.id_reservacion = ?
    ORDER BY og.fecha_observacion DESC
");
$stmt->execute([$idReservacion]);
$observaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener participantes para información médica
$stmt = $db->prepare("
    SELECT nombre_completo, alergias, condiciones_medicas
    FROM participantes
    WHERE id_reservacion = ?
    ORDER BY id_participante ASC
");
$stmt->execute([$idReservacion]);
$participantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fechaCreacionFormatted = !empty($reservacion['fecha_creacion'])
    ? date('d/m/Y H:i', strtotime($reservacion['fecha_creacion']))
    : 'Sin fecha';

$fechaReservacionFormatted = !empty($reservacion['fecha_reservacion'])
    ? date('d/m/Y', strtotime($reservacion['fecha_reservacion']))
    : 'Sin fecha';

$horaInicioFormatted = !empty($reservacion['hora_inicio'])
    ? date('H:i', strtotime($reservacion['hora_inicio']))
    : '--:--';

$idiomaRequerido = !empty($reservacion['idioma'])
    ? ucfirst($reservacion['idioma'])
    : 'No especificado';

$montoTotalFormatted = number_format((float)($reservacion['monto_total'] ?? 0), 2);
$notasAdmin = $reservacion['notas_admin'] ?? '';
$clienteNombre = $reservacion['cliente_nombre'] ?? 'Cliente sin nombre';
$clienteEmail = $reservacion['cliente_email'] ?? '';
$informacionMedica = [];
$guiasAsignadosTotal = count($guiasAgrupados);
$guiasRequeridos = max(1, (int)ceil(($reservacion['numero_personas'] ?? 0) / 5));

foreach ($participantes as $index => $participante) {
    $alergiasTexto = trim((string)($participante['alergias'] ?? ''));
    $condicionesTexto = trim((string)($participante['condiciones_medicas'] ?? ''));
    $informacionMedica[] = [
        'nombre' => $participante['nombre_completo'] ?: 'Participante ' . ($index + 1),
        'alergias' => $alergiasTexto,
        'condiciones' => $condicionesTexto
    ];
}

$pageTitle = 'Detalle de Reservación';
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
        .info-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        .timeline-item {
            position: relative;
            padding-left: 40px;
            padding-bottom: 30px;
            border-left: 2px solid #dee2e6;
        }
        .timeline-item:last-child {
            border-left: 2px solid transparent;
        }
        .timeline-marker {
            position: absolute;
            left: -8px;
            top: 0;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background-color: #0066cc;
            border: 2px solid white;
        }
        .guia-card {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .guia-card h6 {
            font-size: 1.25rem;
            font-weight: 600;
        }
        .guia-contact {
            font-size: 1.05rem;
        }
        .badge-large {
            font-size: 1rem;
            padding: 0.6rem 1.2rem;
            border-radius: 999px;
        }
        .badge-idioma {
            font-size: 1.1rem;
            padding: 0.5rem 1.3rem;
        }
        .tour-info-text p {
            font-size: 1.35rem;
            line-height: 1.5;
        }
        .cliente-card-body {
            font-size: 1.15rem;
            line-height: 1.6;
        }
        .cliente-card-body h6 {
            font-size: 1.35rem;
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
                        Reservación 
                        <span class="text-primary"><?php echo htmlspecialchars($reservacion['codigo_reservacion']); ?></span>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($reservacion['estado'] !== 'cancelada' && $reservacion['estado'] !== 'completada'): ?>
                        <a href="asignar-guias.php?id=<?php echo $idReservacion; ?>" class="btn btn-success me-2">
                            <i class="fas fa-user-plus"></i> Asignar Guías
                        </a>
                        <?php endif; ?>
                        
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-edit"></i> Cambiar Estado
                            </button>
                            <ul class="dropdown-menu">
                                <?php if ($reservacion['estado'] === 'pendiente'): ?>
                                <li><a class="dropdown-item" href="#" onclick="cambiarEstado('confirmada')">
                                    Confirmar
                                </a></li>
                                <?php endif; ?>
                                
                                <?php if ($reservacion['estado'] === 'confirmada' || $reservacion['estado'] === 'pendiente'): ?>
                                <li><a class="dropdown-item" href="#" onclick="cambiarEstado('pagada')">
                                    Marcar como Pagada
                                </a></li>
                                <?php endif; ?>
                                
                                <?php if ($reservacion['estado'] !== 'cancelada' && $reservacion['estado'] !== 'completada'): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="cancelarReservacion()">
                                    Cancelar Reservación
                                </a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
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
                
                <div class="row">
                    <!-- Columna Izquierda -->
                    <div class="col-md-8">
                        <!-- Estado Actual -->
                        <div class="alert alert-<?php 
                            echo ['pendiente' => 'warning', 'confirmada' => 'info', 'pagada' => 'success', 
                                  'cancelada' => 'danger', 'completada' => 'secondary', 'reembolsada' => 'dark'][$reservacion['estado']] ?? 'secondary'; 
                        ?> d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">
                                    Estado: <strong><?php echo ucfirst($reservacion['estado']); ?></strong>
                                </h5>
                            </div>
                            <div>
                                <span class="badge bg-dark badge-large">
                                    Creada: <?php echo $fechaCreacionFormatted; ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Información del Tour -->
                        <div class="card shadow mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Información del Tour</h5>
                            </div>
                            <div class="card-body tour-info-text">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Paquete:</strong><br>
                                        <?php echo htmlspecialchars($reservacion['paquete_nombre']); ?></p>
                                        
                                        <p><strong>Fecha:</strong><br>
                                        <?php echo $fechaReservacionFormatted; ?></p>
                                        
                                        <p><strong>Hora:</strong><br>
                                        <?php echo $horaInicioFormatted; ?> 
                                        (<?php echo $reservacion['duracion']; ?> minutos)</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Número de Personas:</strong><br>
                                        <?php echo $reservacion['numero_personas']; ?> / <?php echo $reservacion['max_personas']; ?></p>
                                        
                                        <p><strong>Idioma Requerido:</strong><br>
                                        <span class="badge bg-info badge-idioma"><?php echo $idiomaRequerido; ?></span></p>
                                        
                                        <p><strong>Monto Total:</strong><br>
                                        <span class="h4 text-success">$<?php echo $montoTotalFormatted; ?></span></p>
                                    </div>
                                </div>
                                
                                <?php if (!empty($notasAdmin)): ?>
                                <hr>
                                <p><strong>Notas del Administrador:</strong><br>
                                <?php echo nl2br(htmlspecialchars($notasAdmin)); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Guías Asignados -->
                        <div class="card shadow mb-4">
                            <div class="card-header bg-success text-white">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                                    <h5 class="mb-0">
                                        Guías Asignados 
                                        <span class="badge bg-light text-dark"><?php echo $guiasAsignadosTotal; ?></span>
                                    </h5>
                                    <h5 class="mb-0 mt-2 mt-md-0 text-white">
                                        Se necesitan (<?php echo $guiasRequeridos; ?>) guía<?php echo $guiasRequeridos === 1 ? '' : 's'; ?>
                                    </h5>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($guiasAgrupados)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No hay guías asignados a esta reservación</p>
                                        <?php if ($reservacion['estado'] !== 'cancelada' && $reservacion['estado'] !== 'completada'): ?>
                                        <a href="asignar-guias.php?id=<?php echo $idReservacion; ?>" class="btn btn-success">
                                            <i class="fas fa-user-plus"></i> Asignar Guías
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($guiasAgrupados as $guia): ?>
                                    <div class="guia-card">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <a href="../guias/detalle.php?id=<?php echo $guia['id_guia']; ?>">
                                                        <?php echo htmlspecialchars($guia['nombre_completo']); ?>
                                                    </a>
                                                </h6>
                                                <p class="mb-1">
                                                    <i class="fas fa-envelope text-primary"></i> 
                                                    <span class="guia-contact"><?php echo htmlspecialchars($guia['email']); ?></span>
                                                </p>
                                                <p class="mb-1">
                                                    <i class="fas fa-phone text-success"></i> 
                                                    <span class="guia-contact"><?php echo htmlspecialchars($guia['telefono']); ?></span>
                                                </p>
                                                <div>
                                                    <?php foreach ($guia['idiomas'] as $idioma): ?>
                                                        <span class="badge bg-info me-1"><?php echo ucfirst($idioma); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <?php if ($guia['foto_perfil']): ?>
                                            <img src="<?php echo UPLOADS_URL; ?>/guias/<?php echo $guia['foto_perfil']; ?>" 
                                                 class="rounded-circle" 
                                                 width="60" 
                                                 height="60"
                                                 style="object-fit: cover;">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Observaciones de Guías -->
                        <?php if (!empty($observaciones)): ?>
                        <div class="card shadow mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-comment-dots"></i> Observaciones de Guías</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($observaciones as $obs): ?>
                                <div class="alert alert-info">
                                    <strong><?php echo htmlspecialchars($obs['guia_nombre']); ?></strong>
                                    <small class="text-muted float-end">
                                        <?php echo date('d/m/Y H:i', strtotime($obs['fecha_observacion'])); ?>
                                    </small>
                                    <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($obs['observacion'])); ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Reembolsos -->
                        <?php if (!empty($reembolsos)): ?>
                        <div class="card shadow mb-4">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0"><i class="fas fa-undo"></i> Reembolsos</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($reembolsos as $reembolso): ?>
                                <div class="alert alert-warning">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong>Monto: $<?php echo number_format($reembolso['monto'], 2); ?></strong><br>
                                            <small>Método: <?php echo ucfirst($reembolso['metodo']); ?></small><br>
                                            <small>Procesado por: <?php echo htmlspecialchars($reembolso['procesado_por_nombre']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($reembolso['fecha_reembolso'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <?php if ($reembolso['notas']): ?>
                                    <hr>
                                    <p class="mb-0"><small><?php echo nl2br(htmlspecialchars($reembolso['notas'])); ?></small></p>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Columna Derecha -->
                    <div class="col-md-4">
                        <!-- Información del Cliente -->
                        <div class="card shadow mb-4">
                            <div class="card-header" style="background-color: #f8c5d3; color: #5a1f3d;">
                                <h5 class="mb-0"> Cliente</h5>
                            </div>
                            <div class="card-body cliente-card-body">
                                <h6><?php echo htmlspecialchars($clienteNombre); ?></h6>
                                <p class="mb-1">
                                    <i class="fas fa-envelope"></i> 
                                    <?php if ($clienteEmail): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($clienteEmail); ?>">
                                        <?php echo htmlspecialchars($clienteEmail); ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">Sin correo</span>
                                    <?php endif; ?>
                                </p>
                                <div class="mt-3">
                                    <p class="mb-2">
                                        <i class="fas fa-notes-medical text-danger"></i> 
                                        <strong>Información médica de participantes</strong>
                                    </p>
                                    <?php if (!empty($informacionMedica)): ?>
                                        <ul class="list-unstyled small mb-0">
                                            <?php foreach ($informacionMedica as $info): ?>
                                                <?php
                                                $texto = trim(
                                                    ($info['alergias'] !== '' ? $info['alergias'] : '') .
                                                    ($info['condiciones'] !== '' 
                                                        ? ($info['alergias'] !== '' ? ' ' : '') . $info['condiciones'] 
                                                        : '')
                                                );
                                                ?>
                                                <li class="mb-2">
                                                    <?php if ($texto !== ''): ?>
                                                        <?php echo nl2br(htmlspecialchars($texto)); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Sin detalles médicos</span>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <span class="text-muted small">Sin información médica proporcionada</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Historial de Estados -->
                        <div class="card shadow mb-4">
                            <div class="card-header bg-warning">
                                <h5 class="mb-0">Historial</h5>
                            </div>
                            <div class="card-body">
                                <div class="timeline">
                                    <?php foreach ($historialEstados as $historial): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker"></div>
                                        <div>
                                            <strong><?php echo ucfirst($historial['estado_nuevo']); ?></strong>
                                            <?php if ($historial['estado_anterior']): ?>
                                                <small class="text-muted">(de <?php echo $historial['estado_anterior']; ?>)</small>
                                            <?php endif; ?>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($historial['fecha_cambio'])); ?>
                                                <?php if ($historial['usuario_nombre']): ?>
                                                    <br>por <?php echo htmlspecialchars($historial['usuario_nombre']); ?>
                                                <?php endif; ?>
                                            </small>
                                            <?php if ($historial['motivo']): ?>
                                            <br>
                                            <small><em><?php echo htmlspecialchars($historial['motivo']); ?></em></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Acciones Adicionales -->
                        <?php if ($reservacion['estado'] === 'cancelada' && empty($reembolsos) && (($reservacion['estado_pago'] ?? '') === 'pagado')): ?>
                        <div class="card shadow mb-4 border-danger">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Acción Requerida</h5>
                            </div>
                            <div class="card-body">
                                <p>Esta reservación fue cancelada y tiene un pago registrado. ¿Desea procesar un reembolso?</p>
                                <a href="procesar-reembolso.php?id=<?php echo $idReservacion; ?>" class="btn btn-danger w-100">
                                    <i class="fas fa-undo"></i> Procesar Reembolso
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function cambiarEstado(nuevoEstado) {
        let motivo = '';
        
        if (nuevoEstado === 'cancelada') {
            return; // Usar la función especializada
        }
        
        if (nuevoEstado === 'confirmada') {
            motivo = 'Confirmada por administrador';
        } else if (nuevoEstado === 'pagada') {
            motivo = 'Pago recibido en persona';
        }
        
        if (!confirm(`¿Está seguro de cambiar el estado a "${nuevoEstado}"?`)) {
            return;
        }
        
        fetch('<?php echo SITE_URL; ?>/api/admin/cambiar-estado-reservacion.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id_reservacion: <?php echo $idReservacion; ?>,
                nuevo_estado: nuevoEstado,
                motivo: motivo
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Estado actualizado correctamente');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cambiar el estado');
        });
    }
    
    function cancelarReservacion() {
        const motivo = prompt('Ingrese el motivo de la cancelación:');
        
        if (!motivo) {
            alert('Debe proporcionar un motivo para cancelar');
            return;
        }
        
        if (!confirm('¿Está seguro de cancelar esta reservación?')) {
            return;
        }
        
        fetch('<?php echo SITE_URL; ?>/api/admin/cambiar-estado-reservacion.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id_reservacion: <?php echo $idReservacion; ?>,
                nuevo_estado: 'cancelada',
                motivo: motivo
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Reservación cancelada correctamente');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cancelar la reservación');
        });
    }
    </script>
</body>
</html>
