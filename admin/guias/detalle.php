<?php
/**
 * ============================================
 * RUTA: admin/guias/detalle.php
 * ============================================
 * Vista detallada de un guía con historial y estadísticas
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Guia.php';
require_once __DIR__ . '/../../classes/Database.php';

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    redirect(SITE_URL . '/admin/login.php');
}

$idGuia = intval($_GET['id'] ?? 0);

if ($idGuia === 0) {
    $_SESSION['mensaje'] = 'ID de guía no válido';
    $_SESSION['mensaje_tipo'] = 'danger';
    redirect(SITE_URL . '/admin/guias/');
}

$guiaClass = new Guia();
$guia = $guiaClass->obtenerPorId($idGuia);

if (!$guia) {
    $_SESSION['mensaje'] = 'Guía no encontrado';
    $_SESSION['mensaje_tipo'] = 'danger';
    redirect(SITE_URL . '/admin/guias/');
}

// Obtener estadísticas del guía
$db = Database::getInstance()->getConnection();

// Tours realizados
$stmt = $db->prepare("
    SELECT COUNT(*) as total_tours,
           COALESCE(SUM(r.numero_personas), 0) as total_personas
    FROM asignacion_guias ag
    INNER JOIN reservaciones r ON ag.id_reservacion = r.id_reservacion
    WHERE ag.id_guia = ? AND r.estado = 'completada'
");
$stmt->execute([$idGuia]);
$estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

// Próximos tours
$stmt = $db->prepare("
    SELECT r.*, 
           r.fecha_tour as fecha_reservacion,
           p.nombre_paquete as paquete_nombre, 
           c.nombre_completo as cliente_nombre, 
           c.email as cliente_email
    FROM asignacion_guias ag
    INNER JOIN reservaciones r ON ag.id_reservacion = r.id_reservacion
    INNER JOIN paquetes p ON r.id_paquete = p.id_paquete
    INNER JOIN clientes c ON r.id_cliente = c.id_cliente
    WHERE ag.id_guia = ? 
      AND r.fecha_tour >= CURDATE()
      AND r.estado IN ('confirmada', 'pagada')
    ORDER BY r.fecha_tour ASC, r.hora_inicio ASC
    LIMIT 10
");
$stmt->execute([$idGuia]);
$proximosTours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Historial de tours completados (últimos 20)
$stmt = $db->prepare("
    SELECT r.*, 
           r.fecha_tour as fecha_reservacion,
           p.nombre_paquete as paquete_nombre, 
           c.nombre_completo as cliente_nombre
    FROM asignacion_guias ag
    INNER JOIN reservaciones r ON ag.id_reservacion = r.id_reservacion
    INNER JOIN paquetes p ON r.id_paquete = p.id_paquete
    INNER JOIN clientes c ON r.id_cliente = c.id_cliente
    WHERE ag.id_guia = ? AND r.estado = 'completada'
    ORDER BY r.fecha_tour DESC, r.hora_inicio DESC
    LIMIT 20
");
$stmt->execute([$idGuia]);
$historialTours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Observaciones del guía
$stmt = $db->prepare("
    SELECT og.*, r.codigo_reservacion, p.nombre_paquete as paquete_nombre, r.fecha_tour as fecha_reservacion
    FROM observaciones_guia og
    INNER JOIN reservaciones r ON og.id_reservacion = r.id_reservacion
    INNER JOIN paquetes p ON r.id_paquete = p.id_paquete
    WHERE og.id_guia = ?
    ORDER BY og.fecha_observacion DESC
    LIMIT 10
");
$stmt->execute([$idGuia]);
$observaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Detalle del Guía';
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
        .guia-foto {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #dee2e6;
        }
        .info-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .stat-card h3 {
            font-size: 2.5rem;
            margin-bottom: 0;
        }
        .comentario-item {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .observacion-item {
            background-color: #d1ecf1;
            border-left: 4px solid #0dcaf0;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .guia-info p {
            font-size: 1.1rem;
            line-height: 1.6;
        }
        .guia-info span {
            font-size: 1.1rem;
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
                        Detalle del Guía
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="editar.php?id=<?php echo $idGuia; ?>" class="btn btn-primary me-2">
                            <i class="fas fa-edit"></i> Editar
                        </a>
                        <button type="button"
                                class="btn btn-<?php echo $guia['activo'] ? 'danger' : 'success'; ?> me-2"
                                onclick="toggleEstado(<?php echo $idGuia; ?>, <?php echo $guia['activo'] ? 0 : 1; ?>)">
                            <i class="fas fa-power-off"></i> 
                            <?php echo $guia['activo'] ? 'Desactivar' : 'Activar'; ?>
                        </button>
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
                
                <!-- Información del Guía -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="card shadow mb-4">
                            <div class="card-body text-center">
                                <?php if ($guia['foto_perfil']): ?>
                                    <img src="<?php echo UPLOADS_URL; ?>/guias/<?php echo $guia['foto_perfil']; ?>" 
                                         class="guia-foto mb-3"
                                         alt="<?php echo htmlspecialchars($guia['nombre_completo']); ?>">
                                <?php else: ?>
                                    <div class="guia-foto mx-auto mb-3 bg-secondary d-flex align-items-center justify-content-center">
                                        <i class="fas fa-user fa-4x text-white"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <h4><?php echo htmlspecialchars($guia['nombre_completo']); ?></h4>
                                
                                <?php if ($guia['activo']): ?>
                                    <span class="badge bg-success mb-3">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger mb-3">Inactivo</span>
                                <?php endif; ?>
                                
                                <div class="text-start mt-3 guia-info">
                                    <p class="mb-3">
                                        <i class="fas fa-envelope text-primary"></i>
                                        <strong>Email:</strong><br>
                                        <span class="text-wrap d-inline-block"><?php echo htmlspecialchars($guia['email']); ?></span>
                                    </p>
                                    <p class="mb-3">
                                        <i class="fas fa-phone text-success"></i>
                                        <strong>Teléfono:</strong><br>
                                        <span class="text-wrap d-inline-block"><?php echo htmlspecialchars($guia['telefono']); ?></span>
                                    </p>
                                    <p class="mb-3">
                                        <i class="fas fa-birthday-cake text-info"></i>
                                        <strong>Fecha de Nacimiento:</strong><br>
                                        <span><?php echo date('d/m/Y', strtotime($guia['fecha_nacimiento'])); ?></span>
                                    </p>
                                    <p class="mb-3">
                                        <i class="fas fa-id-card text-warning"></i>
                                        <strong>CURP:</strong><br>
                                        <span><?php echo htmlspecialchars($guia['curp']); ?></span>
                                    </p>
                                    <p class="mb-3">
                                        <i class="fas fa-home text-secondary"></i>
                                        <strong>Domicilio:</strong><br>
                                        <span><?php echo nl2br(htmlspecialchars($guia['domicilio'])); ?></span>
                                    </p>
                                    <p class="mb-0">
                                        <i class="fas fa-language text-danger"></i>
                                        <strong>Idiomas:</strong><br>
                                        <span class="fw-medium"><?php echo implode(' ', array_map(fn($idioma) => ucfirst($idioma['idioma']), $guia['idiomas'])); ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <!-- Estadísticas -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="stat-card">
                                    <i class="fas fa-route fa-2x mb-2"></i>
                                    <h3><?php echo number_format($estadisticas['total_tours']); ?></h3>
                                    <p class="mb-0">Tours Completados</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <h3><?php echo number_format($estadisticas['total_personas']); ?></h3>
                                    <p class="mb-0">Personas Atendidas</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Próximos Tours -->
                        <div class="card shadow mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    Próximos Tours (<?php echo count($proximosTours); ?>)
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($proximosTours)): ?>
                                    <p class="text-muted text-center py-3">No hay tours programados</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Código</th>
                                                    <th>Paquete</th>
                                                    <th>Fecha</th>
                                                    <th>Hora</th>
                                                    <th>Cliente</th>
                                                    <th>Personas</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($proximosTours as $tour): ?>
                                                <tr>
                                                    <td>
                                                        <a href="../reservaciones/detalle.php?id=<?php echo $tour['id_reservacion']; ?>">
                                                            <strong><?php echo htmlspecialchars($tour['codigo_reservacion']); ?></strong>
                                                        </a>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($tour['paquete_nombre']); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($tour['fecha_reservacion'])); ?></td>
                                                    <td><?php echo date('H:i', strtotime($tour['hora_inicio'])); ?></td>
                                                    <td><?php echo htmlspecialchars($tour['cliente_nombre']); ?></td>
                                                    <td><?php echo $tour['numero_personas']; ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Comentarios del Admin -->
                        <div class="card shadow mb-4">
                            <div class="card-header bg-warning">
                                <h5 class="mb-0">
                                    Comentarios del Administrador
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($guia['comentarios'])): ?>
                                    <p class="text-muted text-center py-3">No hay comentarios registrados</p>
                                <?php else: ?>
                                    <?php foreach ($guia['comentarios'] as $comentario): ?>
                                    <div class="comentario-item">
                                        <p class="mb-2"><?php echo nl2br(htmlspecialchars($comentario['comentario'])); ?></p>
                                        <small class="text-muted">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($comentario['admin_nombre']); ?> - 
                                            <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($comentario['fecha_comentario'])); ?>
                                        </small>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <!-- Formulario para agregar comentario -->
                                <hr>
                                <form id="formComentario">
                                    <div class="mb-3">
                                        <label for="comentario" class="form-label">
                                            <strong>Agregar nuevo comentario:</strong>
                                        </label>
                                        <textarea class="form-control" 
                                                  id="comentario" 
                                                  name="comentario"
                                                  rows="3"
                                                  placeholder="Escriba su comentario aquí..."
                                                  required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-plus"></i> Agregar Comentario
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Observaciones del Guía -->
                <?php if (!empty($observaciones)): ?>
                <div class="card shadow mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-sticky-note"></i> Observaciones del Guía sobre Tours
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($observaciones as $obs): ?>
                        <div class="observacion-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong>
                                        <a href="../reservaciones/detalle.php?id=<?php echo $obs['id_reservacion']; ?>">
                                            <?php echo htmlspecialchars($obs['codigo_reservacion']); ?>
                                        </a> - 
                                        <?php echo htmlspecialchars($obs['paquete_nombre']); ?>
                                    </strong>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($obs['fecha_reservacion'])); ?>
                                    </small>
                                </div>
                                <small class="text-muted">
                                    <?php echo date('d/m/Y H:i', strtotime($obs['fecha_observacion'])); ?>
                                </small>
                            </div>
                            <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($obs['observacion'])); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Historial de Tours Completados -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            Historial de Tours Completados
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($historialTours)): ?>
                            <p class="text-muted text-center py-3">No hay tours completados</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Paquete</th>
                                            <th>Fecha</th>
                                            <th>Hora</th>
                                            <th>Cliente</th>
                                            <th>Personas</th>
                                            <th>Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historialTours as $tour): ?>
                                        <tr>
                                            <td>
                                                <a href="../reservaciones/detalle.php?id=<?php echo $tour['id_reservacion']; ?>">
                                                    <?php echo htmlspecialchars($tour['codigo_reservacion']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($tour['paquete_nombre']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($tour['fecha_reservacion'])); ?></td>
                                            <td><?php echo date('H:i', strtotime($tour['hora_inicio'])); ?></td>
                                            <td><?php echo htmlspecialchars($tour['cliente_nombre']); ?></td>
                                            <td><?php echo $tour['numero_personas']; ?></td>
                                            <td>$<?php echo number_format($tour['monto_total'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
    // Toggle estado del guía
    function toggleEstado(idGuia, nuevoEstado) {
        const accion = nuevoEstado ? 'activar' : 'desactivar';
        
        if (!confirm(`¿Está seguro de ${accion} este guía?`)) {
            return;
        }
        
        fetch('<?php echo SITE_URL; ?>/api/admin/toggle-guia.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id_guia: idGuia,
                activo: nuevoEstado
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
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
    
    // Agregar comentario
    document.getElementById('formComentario').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const comentario = document.getElementById('comentario').value.trim();
        
        if (!comentario) {
            alert('Por favor escriba un comentario');
            return;
        }
        
        fetch('<?php echo SITE_URL; ?>/api/admin/agregar-comentario-guia.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id_guia: <?php echo $idGuia; ?>,
                comentario: comentario
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Comentario agregado correctamente');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al agregar el comentario');
        });
    });
    </script>
</body>
</html>
