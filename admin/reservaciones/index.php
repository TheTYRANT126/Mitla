<?php


require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/ReservacionAdmin.php';
require_once __DIR__ . '/../../classes/Database.php';

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    redirect(SITE_URL . '/admin/login.php');
}

// Obtener filtros
$filtros = [
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
    'estado' => $_GET['estado'] ?? '',
    'id_paquete' => $_GET['id_paquete'] ?? ''
];

$reservacionClass = new ReservacionAdmin();
$reservaciones = $reservacionClass->obtenerTodas($filtros);

// Obtener paquetes para el filtro
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT id_paquete, nombre_paquete AS nombre FROM paquetes ORDER BY nombre_paquete");
$paquetes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Gestión de Reservaciones';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin/admin.css">
    
    <style>
        .filters-card {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
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
                        Gestión de Reservaciones
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filtrosCollapse">
                            <i class="fas fa-filter"></i> Filtros
                        </button>
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
                
                <!-- Filtros -->
                <div class="collapse show" id="filtrosCollapse">
                    <div class="filters-card">
                        <form method="GET" id="formFiltros">
                            <div class="row">
                                <div class="col-md-3">
                                    <label for="fecha_desde" class="form-label">Fecha Desde</label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="fecha_desde" 
                                           name="fecha_desde"
                                           value="<?php echo htmlspecialchars($filtros['fecha_desde']); ?>">
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="fecha_hasta" class="form-label">Fecha Hasta</label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="fecha_hasta" 
                                           name="fecha_hasta"
                                           value="<?php echo htmlspecialchars($filtros['fecha_hasta']); ?>">
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="estado" class="form-label">Estado</label>
                                    <select class="form-select" id="estado" name="estado">
                                        <option value="">Todos</option>
                                        <option value="pendiente" <?php echo $filtros['estado'] === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                        <option value="confirmada" <?php echo $filtros['estado'] === 'confirmada' ? 'selected' : ''; ?>>Confirmada</option>
                                        <option value="pagada" <?php echo $filtros['estado'] === 'pagada' ? 'selected' : ''; ?>>Pagada</option>
                                        <option value="cancelada" <?php echo $filtros['estado'] === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                        <option value="completada" <?php echo $filtros['estado'] === 'completada' ? 'selected' : ''; ?>>Completada</option>
                                        <option value="reembolsada" <?php echo $filtros['estado'] === 'reembolsada' ? 'selected' : ''; ?>>Reembolsada</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="id_paquete" class="form-label">Paquete</label>
                                    <select class="form-select" id="id_paquete" name="id_paquete">
                                        <option value="">Todos</option>
                                        <?php foreach ($paquetes as $paquete): ?>
                                        <option value="<?php echo $paquete['id_paquete']; ?>"
                                                <?php echo $filtros['id_paquete'] == $paquete['id_paquete'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($paquete['nombre']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Buscar
                                    </button>
                                    <a href="index.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Limpiar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Estadísticas Rápidas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card border-left-warning shadow h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Pendientes
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php 
                                            $pendientes = array_filter($reservaciones, fn($r) => $r['estado'] === 'pendiente');
                                            echo count($pendientes); 
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card border-left-info shadow h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Confirmadas
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php 
                                            $confirmadas = array_filter($reservaciones, fn($r) => $r['estado'] === 'confirmada');
                                            echo count($confirmadas); 
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card border-left-success shadow h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Pagadas
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php 
                                            $pagadas = array_filter($reservaciones, fn($r) => $r['estado'] === 'pagada');
                                            echo count($pagadas); 
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card border-left-danger shadow h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                            Canceladas
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php 
                                            $canceladas = array_filter($reservaciones, fn($r) => $r['estado'] === 'cancelada');
                                            echo count($canceladas); 
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabla de Reservaciones -->
                <div class="card shadow">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="reservacionesTable" class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Cliente</th>
                                        <th>Paquete</th>
                                        <th>Fecha</th>
                                        <th>Hora</th>
                                        <th>Personas</th>
                                        <th>Monto</th>
                                        <th>Guías</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reservaciones as $reservacion): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($reservacion['codigo_reservacion']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($reservacion['cliente_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($reservacion['paquete_nombre']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($reservacion['fecha_reservacion'])); ?></td>
                                        <td><?php echo date('H:i', strtotime($reservacion['hora_inicio'])); ?></td>
                                        <td><?php echo $reservacion['numero_personas']; ?></td>
                                        <td>$<?php echo number_format($reservacion['monto_total'], 2); ?></td>
                                        <td>
                                            <?php if ($reservacion['guias_asignados']): ?>
                                                <span class="badge bg-success"><?php echo $reservacion['guias_asignados']; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Sin asignar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $badgeClass = [
                                                'pendiente' => 'warning',
                                                'confirmada' => 'info',
                                                'pagada' => 'success',
                                                'cancelada' => 'danger',
                                                'completada' => 'secondary',
                                                'reembolsada' => 'dark'
                                            ];
                                            $clase = $badgeClass[$reservacion['estado']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $clase; ?>">
                                                <?php echo ucfirst($reservacion['estado']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="detalle.php?id=<?php echo $reservacion['id_reservacion']; ?>" 
                                                   class="btn btn-sm btn-outline-primary"
                                                   title="Ver detalle">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if ($reservacion['estado'] !== 'cancelada' && $reservacion['estado'] !== 'completada'): ?>
                                                <a href="asignar-guias.php?id=<?php echo $reservacion['id_reservacion']; ?>" 
                                                   class="btn btn-sm btn-outline-success"
                                                   title="Asignar guías">
                                                    <i class="fas fa-user-plus"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
    $(document).ready(function() {
        $('#reservacionesTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-MX.json'
            },
            order: [[3, 'desc'], [4, 'desc']], // Ordenar por fecha y hora descendente
            pageLength: 25
        });
    });
    </script>
</body>
</html>