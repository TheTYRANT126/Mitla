<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    redirect(SITE_URL . '/admin/login.php');
}

$db = Database::getInstance()->getConnection();

// Obtener todos los paquetes
$stmt = $db->query("
    SELECT p.*,
           (SELECT COUNT(*) FROM reservaciones r WHERE r.id_paquete = p.id_paquete) as total_reservaciones,
           (SELECT COUNT(*) FROM horarios h WHERE h.id_paquete = p.id_paquete) as total_horarios
    FROM paquetes p
    ORDER BY p.nombre_paquete
");
$paquetes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Gestión de Paquetes';
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
        .paquete-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .paquete-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .paquete-imagen {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px 10px 0 0;
        }
        .precio-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 1.2rem;
            padding: 10px 15px;
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
                        Gestión de Paquetes
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="crear.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Crear Nuevo Paquete
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
                
                <!-- Paquetes en Cards -->
                <div class="row">
                    <?php foreach ($paquetes as $paquete): 
                        $nombrePaquete = $paquete['nombre'] 
                            ?? $paquete['nombre_paquete'] 
                            ?? 'Sin nombre';
                        $descripcion = $paquete['descripcion'] 
                            ?? $paquete['descripcion_es'] 
                            ?? '';
                        $precioBase = isset($paquete['precio_base'])
                            ? (float) $paquete['precio_base']
                            : ((isset($paquete['precio_guia']) ? (float) $paquete['precio_guia'] : 0)
                               + (isset($paquete['precio_entrada_persona']) ? (float) $paquete['precio_entrada_persona'] : 0));
                        $duracionMin = isset($paquete['duracion'])
                            ? (int) $paquete['duracion']
                            : (isset($paquete['duracion_horas']) ? (int) round(((float) $paquete['duracion_horas']) * 60) : 0);
                        $capacidadMax = $paquete['max_personas'] 
                            ?? $paquete['capacidad_maxima'] 
                            ?? 0;
                        $candidatosImagen = [];
                        if (!empty($paquete['imagen_banner'])) {
                            $candidatosImagen[] = $paquete['imagen_banner'];
                        }
                        if (!empty($paquete['imagen'])) {
                            $candidatosImagen[] = $paquete['imagen'];
                        }
                        $imagenPrincipal = getPackageCoverImageUrl(
                            $paquete['id_paquete'] ?? 0,
                            $candidatosImagen
                        ) ?? ASSETS_URL . '/img/packages/default.jpg';
                    ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card paquete-card shadow position-relative">
                            <img src="<?php echo htmlspecialchars($imagenPrincipal, ENT_QUOTES, 'UTF-8'); ?>" 
                                 class="paquete-imagen"
                                 alt="<?php echo htmlspecialchars($nombrePaquete); ?>">
                            
                            <span class="badge bg-success precio-badge">
                                $<?php echo number_format($precioBase, 2); ?>
                            </span>
                            
                            <div class="card-body">
                                <h5 class="card-title">
                                    <?php echo htmlspecialchars($nombrePaquete); ?>
                                    <?php if (!$paquete['activo']): ?>
                                        <span class="badge bg-danger ms-2">Inactivo</span>
                                    <?php endif; ?>
                                </h5>
                                
                                <p class="card-text text-muted">
                                    <?php
                                        $resumenDescripcion = strip_tags($descripcion);
                                        if (function_exists('mb_substr')) {
                                            $resumenDescripcion = mb_substr($resumenDescripcion, 0, 100, 'UTF-8');
                                        } else {
                                            $resumenDescripcion = substr($resumenDescripcion, 0, 100);
                                        }
                                        echo htmlspecialchars($resumenDescripcion, ENT_QUOTES, 'UTF-8');
                                    ?>...
                                </p>
                                
                                <hr>
                                
                                <div class="row text-center small mb-3">
                                    <div class="col-4">
                                        <i class="fas fa-clock text-primary"></i><br>
                                        <strong><?php echo $duracionMin; ?></strong> min
                                    </div>
                                    <div class="col-4">
                                        <i class="fas fa-users text-success"></i><br>
                                        <strong><?php echo $capacidadMax; ?></strong> max
                                    </div>
                                    <div class="col-4">
                                        <i class="fas fa-calendar-check text-info"></i><br>
                                        <strong><?php echo $paquete['total_reservaciones']; ?></strong> reservas
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <a href="editar.php?id=<?php echo $paquete['id_paquete']; ?>" class="btn btn-primary">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <button type="button"
                                            class="btn btn-<?php echo $paquete['activo'] ? 'danger' : 'success'; ?>"
                                            onclick="toggleEstado(<?php echo $paquete['id_paquete']; ?>, <?php echo $paquete['activo'] ? 0 : 1; ?>)">
                                        <i class="fas fa-power-off"></i> 
                                        <?php echo $paquete['activo'] ? 'Desactivar' : 'Activar'; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($paquetes)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-5x text-muted mb-3"></i>
                    <h4 class="text-muted">No hay paquetes registrados</h4>
                    <p class="text-muted">Comience creando su primer paquete turístico</p>
                    <a href="crear.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-plus"></i> Crear Primer Paquete
                    </a>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function toggleEstado(idPaquete, nuevoEstado) {
        const accion = nuevoEstado ? 'activar' : 'desactivar';
        
        if (!confirm(`¿Está seguro de ${accion} este paquete?`)) {
            return;
        }
        
        fetch('../../api/admin/toggle-paquete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                id_paquete: idPaquete,
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
    </script>
</body>
</html>
