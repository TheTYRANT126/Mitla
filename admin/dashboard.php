<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/ReservacionAdmin.php';

$auth = new Auth();

// Verificar autenticación
if (!$auth->isAuthenticated()) {
    redirect(SITE_URL . '/admin/login.php');
}

// Solo admins pueden acceder
if (!$auth->isAdmin()) {
    redirect(SITE_URL . '/admin/guia/mis-tours.php');
}

$reservacionAdmin = new ReservacionAdmin();

// Obtener estadísticas
$estadisticas = $reservacionAdmin->obtenerEstadisticas();
$reservasHoy = $reservacionAdmin->obtenerDelDia();

$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin/admin.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin/dashboard.css">
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                         Dashboard
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                                <i class="fas fa-sync-alt"></i> Actualizar
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Tarjetas de estadísticas -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Reservas Hoy
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $estadisticas['reservas_hoy']['total_reservas'] ?? 0; ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $estadisticas['reservas_hoy']['total_personas'] ?? 0; ?> personas
                                        </small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-check fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Ingresos Totales
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo formatearPrecio($estadisticas['ingresos']['total_ingresos'] ?? 0); ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $estadisticas['ingresos']['total_reservas'] ?? 0; ?> reservas
                                        </small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Próximas Reservas
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $estadisticas['proximas_reservas']; ?>
                                        </div>
                                        <small class="text-muted">Próximos 7 días</small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clipboard-list fa-2x text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Ocupación Promedio
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php 
                                            $ocupacionTotal = 0;
                                            $count = 0;
                                            foreach ($estadisticas['ocupacion_hoy'] as $ocupacion) {
                                                if ($ocupacion['capacidad_maxima'] > 0) {
                                                    $ocupacionTotal += ($ocupacion['ocupados'] / $ocupacion['capacidad_maxima']) * 100;
                                                    $count++;
                                                }
                                            }
                                            echo $count > 0 ? round($ocupacionTotal / $count, 1) : 0;
                                            ?>%
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-percentage fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Ocupación de hoy por paquete y horario -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    Ocupación de Hoy 
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($estadisticas['ocupacion_hoy'])): ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-info-circle fa-3x mb-3"></i>
                                        <p>No hay tours programados para hoy</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Paquete</th>
                                                    <th>Horario</th>
                                                    <th>Ocupación</th>
                                                    <th>Disponibles</th>
                                                    <th>Progreso</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($estadisticas['ocupacion_hoy'] as $ocupacion): 
                                                    $porcentaje = $ocupacion['capacidad_maxima'] > 0 
                                                        ? ($ocupacion['ocupados'] / $ocupacion['capacidad_maxima']) * 100 
                                                        : 0;
                                                    
                                                    $colorBarra = $porcentaje >= 90 ? 'danger' : ($porcentaje >= 70 ? 'warning' : 'success');
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($ocupacion['nombre_paquete']); ?></td>
                                                    <td><?php echo date('g:i a', strtotime($ocupacion['hora_inicio'])); ?></td>
                                                    <td>
                                                        <strong><?php echo $ocupacion['ocupados']; ?></strong> / <?php echo $ocupacion['capacidad_maxima']; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $ocupacion['capacidad_maxima'] - $ocupacion['ocupados'] > 0 ? 'success' : 'danger'; ?>">
                                                            <?php echo $ocupacion['capacidad_maxima'] - $ocupacion['ocupados']; ?> lugares
                                                        </span>
                                                    </td>
                                                    <td style="width: 200px;">
                                                        <div class="progress">
                                                            <div class="progress-bar bg-<?php echo $colorBarra; ?>" 
                                                                 role="progressbar" 
                                                                 style="width: <?php echo $porcentaje; ?>%"
                                                                 aria-valuenow="<?php echo $porcentaje; ?>" 
                                                                 aria-valuemin="0" 
                                                                 aria-valuemax="100">
                                                                <?php echo round($porcentaje, 1); ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Reservas de hoy -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    Reservas de Hoy
                                </h6>
                                <a href="<?php echo SITE_URL; ?>/admin/reservaciones/" class="btn btn-sm btn-primary">
                                    Ver Todas
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($reservasHoy)): ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-calendar-times fa-3x mb-3"></i>
                                        <p>No hay reservas para hoy</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Código</th>
                                                    <th>Cliente</th>
                                                    <th>Paquete</th>
                                                    <th>Hora</th>
                                                    <th>Personas</th>
                                                    <th>Guías</th>
                                                    <th>Estado</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($reservasHoy as $reserva): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($reserva['codigo_reservacion']); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($reserva['nombre_completo']); ?></td>
                                                    <td><?php echo htmlspecialchars($reserva['nombre_paquete']); ?></td>
                                                    <td><?php echo date('g:i a', strtotime($reserva['hora_inicio'])); ?></td>
                                                    <td>
                                                        <i class="fas fa-users"></i> <?php echo $reserva['numero_personas']; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($reserva['guias_asignados'] > 0): ?>
                                                            <span class="badge bg-success">
                                                                <?php echo $reserva['guias_asignados']; ?> asignados
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">
                                                                Sin asignar
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $badgeClass = [
                                                            'pendiente' => 'warning',
                                                            'confirmada' => 'info',
                                                            'pagada' => 'success',
                                                            'cancelada' => 'danger',
                                                            'completada' => 'secondary'
                                                        ];
                                                        $class = $badgeClass[$reserva['estado']] ?? 'secondary';
                                                        ?>
                                                        <span class="badge bg-<?php echo $class; ?>">
                                                            <?php echo ucfirst($reserva['estado']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="<?php echo SITE_URL; ?>/admin/reservaciones/detalle.php?id=<?php echo $reserva['id_reservacion']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include __DIR__ . '/includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/admin/dashboard.js"></script>
</body>
</html>