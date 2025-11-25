<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    redirect(SITE_URL . '/admin/login.php');
}

$db = Database::getInstance()->getConnection();

// Obtener configuraciones actuales
$stmt = $db->query("SELECT * FROM configuracion_sistema ORDER BY clave");
$configuraciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar por clave
$config = [];
foreach ($configuraciones as $item) {
    $config[$item['clave']] = $item['valor'];
}

$errores = [];

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errores[] = 'Token de seguridad inválido';
    } else {
        try {
            $db->beginTransaction();
            
            // Actualizar cada configuración
            $configuracionesPost = [
                'emails_activos' => isset($_POST['emails_activos']) ? 1 : 0,
                'dias_recordatorio' => intval($_POST['dias_recordatorio'] ?? 1),
                'permitir_cancelaciones' => isset($_POST['permitir_cancelaciones']) ? 1 : 0,
                'horas_minimas_cancelacion' => intval($_POST['horas_minimas_cancelacion'] ?? 24),
                'modo_mantenimiento' => isset($_POST['modo_mantenimiento']) ? 1 : 0
            ];
            
            foreach ($configuracionesPost as $clave => $valor) {
                $stmt = $db->prepare("
                    UPDATE configuracion_sistema 
                    SET valor = ?, fecha_modificacion = NOW(), modificado_por = ?
                    WHERE clave = ?
                ");
                $stmt->execute([$valor, $auth->getUserId(), $clave]);
            }
            
            $db->commit();
            
            $_SESSION['mensaje'] = 'Configuración actualizada correctamente';
            $_SESSION['mensaje_tipo'] = 'success';
            redirect(SITE_URL . '/admin/configuracion/');
            
        } catch (Exception $e) {
            $db->rollBack();
            $errores[] = 'Error al actualizar la configuración: ' . $e->getMessage();
        }
    }
}

// Generar nuevo CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$pageTitle = 'Configuración del Sistema';
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
        .bg-custom-yellow {
            background-color: #C7C760 !important;
            color: #fff; /* Texto blanco para mejor contraste */
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
                        Configuración del Sistema
                    </h1>
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
                
                <div class="row">
                    <div class="col-md-8">
                        <form method="POST" id="formConfiguracion">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <!-- Configuración de Emails -->
                            <div class="card shadow mb-4">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">Configuración de Emails</h5>
                                </div>
                                <div class="card-body">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="emails_activos" 
                                               name="emails_activos"
                                               <?php echo ($config['emails_activos'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="emails_activos">
                                            <strong>Activar envío de emails</strong>
                                            <br>
                                            <small class="text-muted">
                                                Si está desactivado, no se enviarán emails de confirmación, recordatorio ni notificaciones
                                            </small>
                                        </label>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="dias_recordatorio" class="form-label">
                                            <strong>Días de anticipación para recordatorios</strong>
                                        </label>
                                        <input type="number" 
                                               class="form-control" 
                                               id="dias_recordatorio" 
                                               name="dias_recordatorio"
                                               value="<?php echo $config['dias_recordatorio'] ?? 1; ?>"
                                               min="0"
                                               max="7">
                                        <div class="form-text">
                                            Días antes del tour para enviar el email de recordatorio (0 = mismo día)
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Configuración de Cancelaciones -->
                            <div class="card shadow mb-4">
                                <div class="card-header bg-custom-yellow">
                                    <h5 class="mb-0">Configuración de Cancelaciones</h5>
                                </div>
                                <div class="card-body">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="permitir_cancelaciones" 
                                               name="permitir_cancelaciones"
                                               <?php echo ($config['permitir_cancelaciones'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="permitir_cancelaciones">
                                            <strong>Permitir cancelaciones por clientes</strong>
                                            <br>
                                            <small class="text-muted">
                                                Si está desactivado, solo el administrador podrá cancelar reservaciones
                                            </small>
                                        </label>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="horas_minimas_cancelacion" class="form-label">
                                            <strong>Horas mínimas para cancelación</strong>
                                        </label>
                                        <input type="number" 
                                               class="form-control" 
                                               id="horas_minimas_cancelacion" 
                                               name="horas_minimas_cancelacion"
                                               value="<?php echo $config['horas_minimas_cancelacion'] ?? 24; ?>"
                                               min="1"
                                               max="168">
                                        <div class="form-text">
                                            Horas de anticipación requeridas para poder cancelar una reservación
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Configuración del Sistema -->
                            <div class="card shadow mb-4">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="mb-0">Configuración del Sistema</h5>
                                </div>
                                <div class="card-body">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="modo_mantenimiento" 
                                               name="modo_mantenimiento"
                                               <?php echo ($config['modo_mantenimiento'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="modo_mantenimiento">
                                            <strong>Modo Mantenimiento</strong>
                                            <br>
                                            <small class="text-muted">
                                                Desactiva el sitio público temporalmente. Solo el panel admin estará disponible.
                                            </small>
                                        </label>
                                    </div>
                                    
                                    <div class="alert alert-warning">
                                        <strong>Atención:</strong> Al activar el modo mantenimiento, los clientes no podrán hacer nuevas reservaciones.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg">
                                    Guardar Configuración
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Panel Lateral -->
                    <div class="col-md-4">
                        <!-- Información del Sistema -->
                        <div class="card shadow mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">Información del Sistema</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-2">
                                    <strong>Versión del Sistema:</strong><br>
                                    <span class="badge bg-primary">1.0.0</span>
                                </p>
                                
                                <p class="mb-2">
                                    <strong>Servidor PHP:</strong><br>
                                    <?php echo phpversion(); ?>
                                </p>
                                
                                <p class="mb-2">
                                    <strong>Base de Datos:</strong><br>
                                    <?php 
                                    $stmt = $db->query("SELECT VERSION()");
                                    echo $stmt->fetchColumn();
                                    ?>
                                </p>
                                
                                <hr>
                                
                                <p class="mb-2">
                                    <strong>Última Modificación:</strong><br>
                                    <?php
                                    $stmt = $db->query("
                                        SELECT fecha_modificacion, u.nombre_completo
                                        FROM configuracion_sistema cs
                                        LEFT JOIN usuarios u ON cs.modificado_por = u.id_usuario
                                        ORDER BY cs.fecha_modificacion DESC
                                        LIMIT 1
                                    ");
                                    $ultimaMod = $stmt->fetch(PDO::FETCH_ASSOC);
                                    if ($ultimaMod) {
                                        echo date('d/m/Y H:i', strtotime($ultimaMod['fecha_modificacion']));
                                        if ($ultimaMod['nombre_completo']) {
                                            echo '<br><small class="text-muted">por ' . htmlspecialchars($ultimaMod['nombre_completo']) . '</small>';
                                        }
                                    } else {
                                        echo 'Nunca modificado';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>

                        <!-- Ayuda -->
                        <div class="card shadow">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0">Ayuda</h5>
                            </div>
                            <div class="card-body">
                                <h6>Configuraciones Importantes:</h6>
                                <ul class="small">
                                    <li><strong>Emails:</strong> Asegúrese de que el servidor tenga configurado el envío de emails</li>
                                    <li><strong>Recordatorios:</strong> Se envían automáticamente según la configuración</li>
                                    <li><strong>Cancelaciones:</strong> Las horas mínimas aplican para clientes, no para administradores</li>
                                    <li><strong>Mantenimiento:</strong> Use solo cuando necesite realizar actualizaciones</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Confirmación para modo mantenimiento
    document.getElementById('modo_mantenimiento').addEventListener('change', function() {
        if (this.checked) {
            if (!confirm('¿Está seguro de activar el modo mantenimiento? El sitio público no estará disponible para los clientes.')) {
                this.checked = false;
            }
        }
    });
    
    // Confirmación antes de guardar
    document.getElementById('formConfiguracion').addEventListener('submit', function(e) {
        if (!confirm('¿Está seguro de actualizar la configuración del sistema?')) {
            e.preventDefault();
            return false;
        }
    });
    </script>
</body>
</html>