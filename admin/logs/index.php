<?php


require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    redirect(SITE_URL . '/admin/login.php');
}

$db = Database::getInstance()->getConnection();

// Filtros
$filtros = [
    'fecha_desde' => $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-7 days')),
    'fecha_hasta' => $_GET['fecha_hasta'] ?? date('Y-m-d'),
    'usuario' => $_GET['usuario'] ?? '',
    'accion' => $_GET['accion'] ?? '',
    'tabla' => $_GET['tabla'] ?? ''
];

// Construir query con filtros
$query = "
    SELECT al.*, u.nombre_completo as usuario_nombre, u.email as usuario_email
    FROM audit_log al
    LEFT JOIN usuarios u ON al.id_usuario = u.id_usuario
    WHERE DATE(al.fecha_accion) BETWEEN ? AND ?
";

$params = [$filtros['fecha_desde'], $filtros['fecha_hasta']];

if (!empty($filtros['usuario'])) {
    $query .= " AND al.id_usuario = ?";
    $params[] = $filtros['usuario'];
}

if (!empty($filtros['accion'])) {
    $query .= " AND al.accion LIKE ?";
    $params[] = '%' . $filtros['accion'] . '%';
}

if (!empty($filtros['tabla'])) {
    $query .= " AND al.tabla_afectada = ?";
    $params[] = $filtros['tabla'];
}

$query .= " ORDER BY al.fecha_accion DESC LIMIT 500";

$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener usuarios para filtro
$stmt = $db->query("SELECT id_usuario, nombre_completo FROM usuarios WHERE rol = 'admin' ORDER BY nombre_completo");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener tablas únicas
$stmt = $db->query("SELECT DISTINCT tabla_afectada FROM audit_log WHERE tabla_afectada IS NOT NULL ORDER BY tabla_afectada");
$tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Registro de Actividad';
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
        .log-item {
            border-left: 4px solid #0066cc;
            padding: 15px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .log-item.success {
            border-left-color: #28a745;
        }
        .log-item.danger {
            border-left-color: #dc3545;
        }
        .log-item.warning {
            border-left-color: #ffc107;
        }
        .json-viewer {
            background-color: #f4f4f4;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            font-family: monospace;
            font-size: 0.85rem;
            max-height: 200px;
            overflow-y: auto;
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
                        Registro de Actividad
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filtrosCollapse">
                            <i class="fas fa-filter"></i> Filtros
                        </button>
                    </div>
                </div>
                
                <!-- Filtros -->
                <div class="collapse show" id="filtrosCollapse">
                    <div class="card shadow mb-4">
                        <div class="card-body">
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
                                        <label for="usuario" class="form-label">Usuario</label>
                                        <select class="form-select" id="usuario" name="usuario">
                                            <option value="">Todos</option>
                                            <?php foreach ($usuarios as $usuario): ?>
                                            <option value="<?php echo $usuario['id_usuario']; ?>"
                                                    <?php echo $filtros['usuario'] == $usuario['id_usuario'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($usuario['nombre_completo']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label for="tabla" class="form-label">Tabla</label>
                                        <select class="form-select" id="tabla" name="tabla">
                                            <option value="">Todas</option>
                                            <?php foreach ($tablas as $tabla): ?>
                                            <option value="<?php echo $tabla; ?>"
                                                    <?php echo $filtros['tabla'] === $tabla ? 'selected' : ''; ?>>
                                                <?php echo ucfirst($tabla); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <label for="accion" class="form-label">Buscar Acción</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="accion" 
                                               name="accion"
                                               value="<?php echo htmlspecialchars($filtros['accion']); ?>"
                                               placeholder="Ej: creado, eliminado, actualizado...">
                                    </div>
                                    
                                    <div class="col-md-6 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary me-2">
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
                </div>
                
                <!-- Resumen -->
                <div class="alert alert-info mb-4">
                    Mostrando <strong><?php echo count($logs); ?></strong> registros
                    (últimos 500 registros del período seleccionado)
                </div>
                
                <!-- Logs -->
                <div class="card shadow">
                    <div class="card-body">
                        <?php if (empty($logs)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No hay registros para los filtros seleccionados</h5>
                            </div>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <div class="log-item <?php 
                                if (stripos($log['accion'], 'eliminado') !== false || stripos($log['accion'], 'desactivado') !== false) {
                                    echo 'danger';
                                } elseif (stripos($log['accion'], 'creado') !== false || stripos($log['accion'], 'activado') !== false) {
                                    echo 'success';
                                } elseif (stripos($log['accion'], 'actualizado') !== false || stripos($log['accion'], 'modificado') !== false) {
                                    echo 'warning';
                                }
                            ?>">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong><?php echo htmlspecialchars($log['accion']); ?></strong>
                                        <?php if ($log['tabla_afectada']): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($log['tabla_afectada']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($log['registro_id']): ?>
                                            <span class="badge bg-info">ID: <?php echo $log['registro_id']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y H:i:s', strtotime($log['fecha_accion'])); ?>
                                    </small>
                                </div>
                                
                                <div class="mb-2">
                                    <i class="fas fa-user text-primary"></i>
                                    <strong><?php echo htmlspecialchars($log['usuario_nombre'] ?? 'Sistema'); ?></strong>
                                    <?php if ($log['usuario_email']): ?>
                                        <small class="text-muted">(<?php echo htmlspecialchars($log['usuario_email']); ?>)</small>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($log['ip']): ?>
                                <div class="mb-2">
                                    <i class="fas fa-network-wired text-secondary"></i>
                                    <small>IP: <?php echo htmlspecialchars($log['ip']); ?></small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($log['datos_anteriores'] || $log['datos_nuevos']): ?>
                                <button class="btn btn-sm btn-outline-secondary" 
                                        type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#log-<?php echo $log['id_log']; ?>">
                                    <i class="fas fa-code"></i> Ver Datos
                                </button>
                                
                                <div class="collapse mt-2" id="log-<?php echo $log['id_log']; ?>">
                                    <?php if ($log['datos_anteriores']): ?>
                                    <div class="mb-2">
                                        <strong>Datos Anteriores:</strong>
                                        <div class="json-viewer">
                                            <?php 
                                            $datosAnteriores = json_decode($log['datos_anteriores'], true);
                                            echo '<pre>' . json_encode($datosAnteriores, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
                                            ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($log['datos_nuevos']): ?>
                                    <div>
                                        <strong>Datos Nuevos:</strong>
                                        <div class="json-viewer">
                                            <?php 
                                            $datosNuevos = json_decode($log['datos_nuevos'], true);
                                            echo '<pre>' . json_encode($datosNuevos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
                                            ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>