<?php


require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isGuia()) {
    redirect(SITE_URL . '/admin/login.php');
}

$db = Database::getInstance()->getConnection();

// Obtener ID del guía actual
$stmt = $db->prepare("SELECT id_guia FROM guias WHERE id_usuario = ?");
$stmt->execute([$auth->getUserId()]);
$guia = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guia) {
    $_SESSION['mensaje'] = 'Perfil de guía no encontrado';
    $_SESSION['mensaje_tipo'] = 'danger';
    redirect(SITE_URL . '/admin/logout.php');
}

$idGuia = $guia['id_guia'];

// Obtener tours asignados (próximos y recientes)
$stmt = $db->prepare("
    SELECT r.*, 
           p.nombre_paquete AS paquete_nombre,
           ROUND(p.duracion_horas * 60) AS duracion,
           r.fecha_tour AS fecha_reservacion,
           c.nombre_completo AS cliente_nombre, c.email AS cliente_email, c.telefono AS cliente_telefono,
           ag.fecha_asignacion,
           (SELECT COUNT(*) FROM asignacion_guias ag2 WHERE ag2.id_reservacion = r.id_reservacion) as num_guias
    FROM asignacion_guias ag
    INNER JOIN reservaciones r ON ag.id_reservacion = r.id_reservacion
    INNER JOIN paquetes p ON r.id_paquete = p.id_paquete
    INNER JOIN clientes c ON r.id_cliente = c.id_cliente
    WHERE ag.id_guia = ?
      AND r.fecha_tour >= CURDATE() - INTERVAL 7 DAY
    ORDER BY r.fecha_tour DESC, r.hora_inicio DESC
");
$stmt->execute([$idGuia]);
$tours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separar en próximos y completados
$toursProximos = array_filter($tours, fn($t) => $t['fecha_reservacion'] >= date('Y-m-d') && in_array($t['estado'], ['confirmada', 'pagada']));
$toursCompletados = array_filter($tours, fn($t) => $t['estado'] === 'completada');
$toursCancelados = array_filter($tours, fn($t) => $t['estado'] === 'cancelada');

// Obtener estadísticas del guía
$stmt = $db->prepare("
    SELECT
        COUNT(*) as total_tours,
        COALESCE(SUM(r.numero_personas), 0) as total_personas,
        COUNT(DISTINCT DATE(r.fecha_tour)) as dias_trabajados
    FROM asignacion_guias ag
    INNER JOIN reservaciones r ON ag.id_reservacion = r.id_reservacion
    WHERE ag.id_guia = ? AND r.estado = 'completada'
");
$stmt->execute([$idGuia]);
$estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

$pageTitle = 'Mis Tours';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Guía</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin/admin.css">
    
    <style>
        .tour-card {
            border: 1px solid #0066cc;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .tour-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .tour-card.completado {
            border-color: #28a745;
            opacity: 0.9;
        }
        .tour-card.cancelado {
            border-color: #dc3545;
            opacity: 0.7;
        }
        .stat-card {
            background: #764ba2;
            color: white;
            border-radius: 10px;
            padding: 20px;
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
                        Mis Tours Asignados
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="badge bg-primary fs-6">
                            <i class="fas fa-calendar-check"></i> 
                            <?php echo count($toursProximos); ?> próximos
                        </span>
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
                
                <!-- Estadísticas -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card text-center">
                            <i class="fas fa-route fa-2x mb-2"></i>
                            <h3><?php echo number_format($estadisticas['total_tours']); ?></h3>
                            <p class="mb-0">Tours Completados</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card text-center" style="background: #f5576c;">
                            <i class="fas fa-users fa-2x mb-2"></i>
                            <h3><?php echo number_format($estadisticas['total_personas']); ?></h3>
                            <p class="mb-0">Personas Atendidas</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card text-center" style="background: #00bcd4;">
                            <i class="fas fa-calendar-day fa-2x mb-2"></i>
                            <h3><?php echo number_format($estadisticas['dias_trabajados']); ?></h3>
                            <p class="mb-0">Días Trabajados</p>
                        </div>
                    </div>
                </div>
                
                <!-- Tours Próximos -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            Tours Próximos
                            <span class="badge bg-light text-dark"><?php echo count($toursProximos); ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($toursProximos)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No tienes tours próximos asignados</h5>
                                <p class="text-muted">Los tours te serán asignados por el administrador</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($toursProximos as $tour): ?>
                            <div class="card tour-card mb-3">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h5 class="card-title">
                                                <?php echo htmlspecialchars($tour['paquete_nombre']); ?>
                                                <span class="badge bg-<?php echo ['pendiente' => 'warning', 'confirmada' => 'info', 'pagada' => 'success'][$tour['estado']] ?? 'secondary'; ?>">
                                                    <?php echo ucfirst($tour['estado']); ?>
                                                </span>
                                            </h5>
                                            
                                            <p class="mb-2">
                                                <i class="fas fa-calendar text-primary"></i> 
                                                <strong><?php echo date('l, d \d\e F \d\e Y', strtotime($tour['fecha_reservacion'])); ?></strong>
                                            </p>
                                            
                                            <p class="mb-2">
                                                <i class="fas fa-clock text-success"></i> 
                                                <strong><?php echo date('H:i', strtotime($tour['hora_inicio'])); ?></strong>
                                                (Duración: <?php echo $tour['duracion']; ?> minutos)
                                            </p>
                                            
                                            <p class="mb-2">
                                                <i class="fas fa-user text-info"></i> 
                                                <strong>Cliente:</strong> <?php echo htmlspecialchars($tour['cliente_nombre']); ?>
                                            </p>
                                            
                                            <p class="mb-2">
                                                <i class="fas fa-users text-warning"></i> 
                                                <strong><?php echo $tour['numero_personas']; ?></strong> personas | 
                                                <i class="fas fa-language"></i> 
                                                <?php echo ucfirst($tour['idioma']); ?>
                                            </p>
                                            
                                            <p class="mb-0">
                                                <i class="fas fa-code text-secondary"></i> 
                                                <small>Código: <strong><?php echo htmlspecialchars($tour['codigo_reservacion']); ?></strong></small>
                                            </p>
                                        </div>
                                        
                                        <div class="col-md-4 text-end">
                                            <div class="mb-3">
                                                <?php if ($tour['num_guias'] > 1): ?>
                                                <span class="badge bg-info mb-2">
                                                    <i class="fas fa-user-friends"></i> 
                                                    <?php echo $tour['num_guias']; ?> guías asignados
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="d-grid gap-2">
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick="verDetalles(<?php echo $tour['id_reservacion']; ?>)">
                                                    <i class="fas fa-eye"></i> Ver Detalles
                                                </button>
                                                
                                                <button class="btn btn-sm btn-outline-secondary" 
                                                        onclick="agregarObservacion(<?php echo $tour['id_reservacion']; ?>)">
                                                    <i class="fas fa-comment"></i> Agregar Observación
                                                </button>
                                                
                                                <a href="tel:<?php echo $tour['cliente_telefono']; ?>" 
                                                   class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-phone"></i> Llamar Cliente
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Tours Completados Recientes -->
                <?php if (!empty($toursCompletados)): ?>
                <div class="card shadow mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-check-circle"></i> Tours Completados Recientes
                            <span class="badge bg-light text-dark"><?php echo count($toursCompletados); ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($toursCompletados as $tour): ?>
                        <div class="card tour-card completado mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($tour['paquete_nombre']); ?></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($tour['fecha_reservacion'])); ?> |
                                            <i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($tour['hora_inicio'])); ?> |
                                            <i class="fas fa-users"></i> <?php echo $tour['numero_personas']; ?> personas
                                        </small>
                                    </div>
                                    <span class="badge bg-success">Completado</span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Tours Cancelados -->
                <?php if (!empty($toursCancelados)): ?>
                <div class="card shadow mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            Tours Cancelados
                            <span class="badge bg-light text-dark"><?php echo count($toursCancelados); ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($toursCancelados as $tour): ?>
                        <div class="card tour-card cancelado mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($tour['paquete_nombre']); ?></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($tour['fecha_reservacion'])); ?> |
                                            <i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($tour['hora_inicio'])); ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-danger">Cancelado</span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <!-- Modal de Detalles -->
    <div class="modal fade" id="modalDetalles" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalles del Tour</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalDetallesBody">
                    <div class="text-center">
                        <div class="spinner-border" role="status"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Observación -->
    <div class="modal fade" id="modalObservacion" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Observación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formObservacion">
                        <input type="hidden" id="id_reservacion_obs" name="id_reservacion">
                        
                        <div class="mb-3">
                            <label for="observacion" class="form-label">
                                Observación sobre el tour:
                            </label>
                            <textarea class="form-control" 
                                      id="observacion" 
                                      name="observacion"
                                      rows="5"
                                      placeholder="Ejemplo: El grupo llegó 10 minutos tarde. El cliente solicitó hacer paradas adicionales para fotos."
                                      required></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <small>Esta observación será visible para el administrador y otros guías asignados a este tour.</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarObservacion()">
                        <i class="fas fa-save"></i> Guardar Observación
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function verDetalles(idReservacion) {
        const modal = new bootstrap.Modal(document.getElementById('modalDetalles'));
        const modalBody = document.getElementById('modalDetallesBody');
        
        modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
        modal.show();
        
        fetch(`<?php echo SITE_URL; ?>/api/guia/detalle-tour.php?id=${idReservacion}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarDetalles(data.data);
                } else {
                    modalBody.innerHTML = '<div class="alert alert-danger">Error al cargar los datos</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                modalBody.innerHTML = '<div class="alert alert-danger">Error al cargar los datos</div>';
            });
    }
    
    function mostrarDetalles(data) {
        const modalBody = document.getElementById('modalDetallesBody');
        
        let html = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Información del Tour</h6>
                    <p><strong>Paquete:</strong> ${data.paquete_nombre}</p>
                    <p><strong>Fecha:</strong> ${data.fecha_reservacion}</p>
                    <p><strong>Hora:</strong> ${data.hora_inicio}</p>
                    <p><strong>Duración:</strong> ${data.duracion} minutos</p>
                    <p><strong>Personas:</strong> ${data.numero_personas}</p>
                    <p><strong>Idioma:</strong> ${data.idioma}</p>
                </div>
                <div class="col-md-6">
                    <h6>Información del Cliente</h6>
                    <p><strong>Nombre:</strong> ${data.cliente_nombre}</p>
                    <p><strong>Email:</strong> <a href="mailto:${data.cliente_email}">${data.cliente_email}</a></p>
                    <p><strong>Teléfono:</strong> <a href="tel:${data.cliente_telefono}">${data.cliente_telefono}</a></p>
                </div>
            </div>
        `;
        
        if (data.otros_guias && data.otros_guias.length > 0) {
            html += '<hr><h6>Otros Guías Asignados</h6><ul>';
            data.otros_guias.forEach(guia => {
                html += `<li>${guia.nombre_completo}</li>`;
            });
            html += '</ul>';
        }
        
        if (data.observaciones && data.observaciones.length > 0) {
            html += '<hr><h6>Observaciones del Tour</h6>';
            data.observaciones.forEach(obs => {
                html += `
                    <div class="alert alert-info">
                        <strong>${obs.guia_nombre}</strong>
                        <small class="float-end">${obs.fecha_observacion}</small>
                        <p class="mb-0 mt-2">${obs.observacion}</p>
                    </div>
                `;
            });
        }
        
        modalBody.innerHTML = html;
    }
    
    function agregarObservacion(idReservacion) {
        document.getElementById('id_reservacion_obs').value = idReservacion;
        document.getElementById('observacion').value = '';
        
        const modal = new bootstrap.Modal(document.getElementById('modalObservacion'));
        modal.show();
    }
    
    function guardarObservacion() {
        const form = document.getElementById('formObservacion');
        const formData = new FormData(form);
        
        const observacion = formData.get('observacion').trim();
        if (!observacion) {
            alert('Por favor ingrese una observación');
            return;
        }
        
        fetch('<?php echo SITE_URL; ?>/api/guia/agregar-observacion.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id_reservacion: formData.get('id_reservacion'),
                observacion: observacion
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Observación agregada correctamente');
                bootstrap.Modal.getInstance(document.getElementById('modalObservacion')).hide();
                form.reset();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al guardar la observación');
        });
    }
    </script>
</body>
</html>
