<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Guia.php';
require_once __DIR__ . '/../../classes/Database.php';

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isGuia()) {
    redirect(SITE_URL . '/admin/login.php');
}

$guiaClass = new Guia();
$db = Database::getInstance()->getConnection();

// Obtener información del guía
$stmt = $db->prepare("SELECT id_guia FROM guias WHERE id_usuario = ?");
$stmt->execute([$auth->getUserId()]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    $_SESSION['mensaje'] = 'Perfil de guía no encontrado';
    $_SESSION['mensaje_tipo'] = 'danger';
    redirect(SITE_URL . '/admin/logout.php');
}

$idGuia = $result['id_guia'];
$guia = $guiaClass->obtenerPorId($idGuia);

$errores = [];

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_password'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errores[] = 'Token de seguridad inválido';
    } else {
        $passwordActual = $_POST['password_actual'] ?? '';
        $passwordNueva = $_POST['password_nueva'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        
        if (empty($passwordActual) || empty($passwordNueva) || empty($passwordConfirm)) {
            $errores[] = 'Todos los campos son requeridos';
        } elseif ($passwordNueva !== $passwordConfirm) {
            $errores[] = 'Las contraseñas nuevas no coinciden';
        } elseif (strlen($passwordNueva) < 8) {
            $errores[] = 'La contraseña debe tener al menos 8 caracteres';
        } else {
            try {
                $resultado = $auth->cambiarPassword($auth->getUserId(), $passwordActual, $passwordNueva);
                
                if ($resultado) {
                    $_SESSION['mensaje'] = 'Contraseña actualizada correctamente';
                    $_SESSION['mensaje_tipo'] = 'success';
                    redirect(SITE_URL . '/admin/guia/perfil.php');
                } else {
                    $errores[] = 'La contraseña actual es incorrecta';
                }
            } catch (Exception $e) {
                $errores[] = 'Error al cambiar la contraseña: ' . $e->getMessage();
            }
        }
    }
}

// Generar nuevo CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Obtener estadísticas del guía
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_tours,
        SUM(r.numero_personas) as total_personas,
        COUNT(DISTINCT DATE(r.fecha_reservacion)) as dias_trabajados
    FROM asignacion_guias ag
    INNER JOIN reservaciones r ON ag.id_reservacion = r.id_reservacion
    WHERE ag.id_guia = ? AND r.estado = 'completada'
");
$stmt->execute([$idGuia]);
$estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener comentarios del admin
$comentarios = $guia['comentarios'] ?? [];

$pageTitle = 'Mi Perfil';
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
        .perfil-foto {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #dee2e6;
        }
        .info-card {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-box {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .comentario-admin {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
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
                        <i class="fas fa-user"></i> Mi Perfil
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
                    <!-- Columna Izquierda -->
                    <div class="col-md-4">
                        <!-- Foto y Datos Básicos -->
                        <div class="card shadow mb-4">
                            <div class="card-body text-center">
                                <?php if ($guia['foto_perfil']): ?>
                                    <img src="<?php echo UPLOADS_URL; ?>/guias/<?php echo $guia['foto_perfil']; ?>" 
                                         class="perfil-foto mb-3"
                                         alt="<?php echo htmlspecialchars($guia['nombre_completo']); ?>">
                                <?php else: ?>
                                    <div class="perfil-foto mx-auto mb-3 bg-secondary d-flex align-items-center justify-content-center">
                                        <i class="fas fa-user fa-4x text-white"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <h4><?php echo htmlspecialchars($guia['nombre_completo']); ?></h4>
                                <span class="badge bg-success mb-3">Guía Activo</span>
                                
                                <div class="text-start mt-3">
                                    <p class="mb-2">
                                        <i class="fas fa-envelope text-primary"></i>
                                        <small><?php echo htmlspecialchars($guia['email']); ?></small>
                                    </p>
                                    <p class="mb-2">
                                        <i class="fas fa-phone text-success"></i>
                                        <small><?php echo htmlspecialchars($guia['telefono']); ?></small>
                                    </p>
                                    <p class="mb-2">
                                        <i class="fas fa-birthday-cake text-info"></i>
                                        <small><?php echo date('d/m/Y', strtotime($guia['fecha_nacimiento'])); ?></small>
                                    </p>
                                    <p class="mb-2">
                                        <i class="fas fa-home text-secondary"></i>
                                        <small><?php echo nl2br(htmlspecialchars($guia['domicilio'])); ?></small>
                                    </p>
                                    <p class="mb-0">
                                        <i class="fas fa-language text-danger"></i>
                                        <?php foreach ($guia['idiomas'] as $idioma): ?>
                                            <span class="badge bg-info me-1"><?php echo ucfirst($idioma['idioma']); ?></span>
                                        <?php endforeach; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estadísticas -->
                        <div class="row">
                            <div class="col-12 mb-3">
                                <div class="stat-box">
                                    <i class="fas fa-route fa-2x mb-2"></i>
                                    <h3><?php echo number_format($estadisticas['total_tours']); ?></h3>
                                    <p class="mb-0">Tours Completados</p>
                                </div>
                            </div>
                            <div class="col-12 mb-3">
                                <div class="stat-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <h3><?php echo number_format($estadisticas['total_personas']); ?></h3>
                                    <p class="mb-0">Personas Atendidas</p>
                                </div>
                            </div>
                            <div class="col-12 mb-3">
                                <div class="stat-box" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                    <i class="fas fa-calendar-day fa-2x mb-2"></i>
                                    <h3><?php echo number_format($estadisticas['dias_trabajados']); ?></h3>
                                    <p class="mb-0">Días Trabajados</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Columna Derecha -->
                    <div class="col-md-8">
                        <!-- Información Detallada -->
                        <div class="card shadow mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Información Detallada</h5>
                            </div>
                            <div class="card-body">
                                <div class="info-card">
                                    <h6><i class="fas fa-id-card"></i> CURP</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($guia['curp']); ?></p>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Nota:</strong> Si necesitas actualizar tu información personal, por favor contacta al administrador.
                                </div>
                            </div>
                        </div>
                        
                        <!-- Cambiar Contraseña -->
                        <div class="card shadow mb-4">
                            <div class="card-header bg-warning">
                                <h5 class="mb-0"><i class="fas fa-key"></i> Cambiar Contraseña</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="cambiar_password" value="1">
                                    
                                    <div class="mb-3">
                                        <label for="password_actual" class="form-label">
                                            Contraseña Actual <span class="text-danger">*</span>
                                        </label>
                                        <input type="password" 
                                               class="form-control" 
                                               id="password_actual" 
                                               name="password_actual"
                                               required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password_nueva" class="form-label">
                                            Nueva Contraseña <span class="text-danger">*</span>
                                        </label>
                                        <input type="password" 
                                               class="form-control" 
                                               id="password_nueva" 
                                               name="password_nueva"
                                               minlength="8"
                                               required>
                                        <div class="form-text">Mínimo 8 caracteres</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password_confirm" class="form-label">
                                            Confirmar Nueva Contraseña <span class="text-danger">*</span>
                                        </label>
                                        <input type="password" 
                                               class="form-control" 
                                               id="password_confirm" 
                                               name="password_confirm"
                                               minlength="8"
                                               required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-save"></i> Cambiar Contraseña
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Comentarios del Administrador -->
                        <?php if (!empty($comentarios)): ?>
                        <div class="card shadow mb-4">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0"><i class="fas fa-comment-dots"></i> Comentarios del Administrador</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($comentarios as $comentario): ?>
                                <div class="comentario-admin">
                                    <p class="mb-2"><?php echo nl2br(htmlspecialchars($comentario['comentario'])); ?></p>
                                    <small class="text-muted">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($comentario['admin_nombre']); ?> - 
                                        <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($comentario['fecha_comentario'])); ?>
                                    </small>
                                </div>
                                <?php endforeach; ?>
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
    // Validación de contraseñas
    document.querySelector('form').addEventListener('submit', function(e) {
        const passwordNueva = document.getElementById('password_nueva').value;
        const passwordConfirm = document.getElementById('password_confirm').value;
        
        if (passwordNueva !== passwordConfirm) {
            e.preventDefault();
            alert('Las contraseñas nuevas no coinciden');
            return false;
        }
    });
    </script>
</body>
</html>