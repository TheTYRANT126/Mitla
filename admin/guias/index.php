<?php


require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Guia.php';

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    redirect(SITE_URL . '/admin/login.php');
}

$mostrarSoloActivos = isset($_GET['mostrar']) ? ($_GET['mostrar'] !== 'todos') : true;

$guiaClass = new Guia();
$guias = $guiaClass->obtenerTodos($mostrarSoloActivos);

$pageTitle = 'Gestión de Guías';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin/admin.css">
    <style>
        .btn-activos {
            background-color: #fff;
            color: #198754;
            border: 1px solid #58CD3D;
        }
        .btn-activos:hover {
            background-color: #58CD3D;
            color: #000;
        }
        .btn-activos-active {
            background-color: #58CD3D;
            color: #000;
            border: none;
            box-shadow: none;
            font-weight: 600;
        }
        .btn-activos-active:hover {
            background-color: #4BB034;
            color: #000;
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
                        Gestión de Guías
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group">
                            <a href="?mostrar=activos" class="btn <?php echo $mostrarSoloActivos ? 'btn-activos-active' : 'btn-activos'; ?>">
                                <i class="fa-solid fa-check"></i> Solo activos
                            </a>
                            <a href="?mostrar=todos" class="btn btn-<?php echo !$mostrarSoloActivos ? 'primary' : 'outline-primary'; ?>">
                                <i class="fas fa-list"></i> Todos
                            </a>
                        </div>
                        <a href="crear.php" class="btn btn-success ms-2">
                            <i class="fas fa-plus"></i> Registrar Nuevo Guía
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
                
                <div class="card shadow">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="guiasTable" class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>Foto</th>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Teléfono</th>
                                        <th>Idiomas</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($guias as $guia): ?>
                                    <tr>
                                        <td>
                                            <?php if ($guia['foto_perfil']): ?>
                                                <img src="<?php echo UPLOADS_URL; ?>/guias/<?php echo $guia['foto_perfil']; ?>" 
                                                     class="rounded-circle" 
                                                     width="40" 
                                                     height="40"
                                                     style="object-fit: cover;">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center" 
                                                     style="width: 40px; height: 40px;">
                                                    <i class="fas fa-user text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($guia['nombre_completo']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($guia['email']); ?></td>
                                        <td><?php echo htmlspecialchars($guia['telefono']); ?></td>
                                        <td>
                                            <?php if ($guia['idiomas']): ?>
                                                <?php 
                                                $idiomas = explode(', ', $guia['idiomas']);
                                                foreach ($idiomas as $idioma): 
                                                ?>
                                                    <span class="badge bg-info me-1"><?php echo ucfirst($idioma); ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($guia['activo']): ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="detalle.php?id=<?php echo $guia['id_guia']; ?>" 
                                                   class="btn btn-sm btn-outline-primary"
                                                   title="Ver detalle">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="editar.php?id=<?php echo $guia['id_guia']; ?>" 
                                                   class="btn btn-sm btn-outline-secondary"
                                                   title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-<?php echo $guia['activo'] ? 'danger' : 'success'; ?>"
                                                        onclick="toggleEstado(<?php echo $guia['id_guia']; ?>, <?php echo $guia['activo'] ? 0 : 1; ?>)"
                                                        title="<?php echo $guia['activo'] ? 'Desactivar' : 'Activar'; ?>">
                                                    <i class="fas fa-power-off"></i>
                                                </button>
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
        $('#guiasTable').DataTable({
            language: {
                decimal: ",",
                thousands: ".",
                lengthMenu: "Mostrar _MENU_ registros",
                zeroRecords: "No se encontraron resultados",
                info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
                infoEmpty: "Mostrando 0 a 0 de 0 registros",
                infoFiltered: "(filtrado de _MAX_ registros totales)",
                search: "Buscar:",
                paginate: {
                    first: "Primero",
                    last: "Último",
                    next: "Siguiente",
                    previous: "Anterior"
                }
            },
            order: [[1, 'asc']],
            pageLength: 25
        });
    });
    
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
    </script>
</body>
</html>
