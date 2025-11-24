<?php
/**
 * ============================================
 * RUTA: admin/guias/editar.php
 * ============================================
 * Formulario para editar un guía existente
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Guia.php';

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

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errores[] = 'Token de seguridad inválido';
    } else {
        // Recoger datos
        $datos = [
            'nombre_completo' => trim($_POST['nombre_completo'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? '',
            'curp' => strtoupper(trim($_POST['curp'] ?? '')),
            'domicilio' => trim($_POST['domicilio'] ?? ''),
            'telefono' => trim($_POST['telefono'] ?? ''),
            'idiomas' => $_POST['idiomas'] ?? []
        ];
        
        // Validaciones
        if (empty($datos['nombre_completo'])) {
            $errores[] = 'El nombre completo es requerido';
        }
        
        if (empty($datos['email']) || !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'Email inválido';
        }
        
        if (empty($datos['fecha_nacimiento'])) {
            $errores[] = 'La fecha de nacimiento es requerida';
        }
        
        if (empty($datos['curp']) || strlen($datos['curp']) !== 18) {
            $errores[] = 'CURP inválido (debe tener 18 caracteres)';
        }
        
        if (empty($datos['telefono'])) {
            $errores[] = 'El teléfono es requerido';
        }
        
        if (empty($datos['idiomas'])) {
            $errores[] = 'Debe seleccionar al menos un idioma';
        }
        
        // Verificar que el email no exista en otro usuario
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ? AND id_usuario != ?");
        $stmt->execute([$datos['email'], $guia['id_usuario']]);
        if ($stmt->fetchColumn() > 0) {
            $errores[] = 'El email ya está registrado por otro usuario';
        }
        
        // Si hay contraseña nueva, validarla
        if (!empty($_POST['password'])) {
            if (strlen($_POST['password']) < 8) {
                $errores[] = 'La contraseña debe tener al menos 8 caracteres';
            } else {
                $datos['password'] = $_POST['password'];
            }
        }
        
        // Si no hay errores, actualizar el guía
        if (empty($errores)) {
            try {
                $guiaClass->actualizar($idGuia, $datos);
                
                // Subir nueva foto si se proporcionó
                if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
                    $guiaClass->subirFoto($idGuia, $_FILES['foto_perfil']);
                }
                
                $_SESSION['mensaje'] = 'Guía actualizado correctamente';
                $_SESSION['mensaje_tipo'] = 'success';
                redirect(SITE_URL . '/admin/guias/detalle.php?id=' . $idGuia);
                
            } catch (Exception $e) {
                $errores[] = 'Error al actualizar el guía: ' . $e->getMessage();
            }
        }
    }
}

// Generar nuevo CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$pageTitle = 'Editar Guía';
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
        .preview-foto {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #dee2e6;
        }
        .preview-container {
            text-align: center;
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
                        <i class="fas fa-user-edit"></i> Editar Guía
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="detalle.php?id=<?php echo $idGuia; ?>" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-eye"></i> Ver Detalle
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver a Lista
                        </a>
                    </div>
                </div>
                
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
                
                <div class="card shadow">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="formGuia">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <!-- Foto de perfil -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="preview-container">
                                        <?php if ($guia['foto_perfil']): ?>
                                            <img id="preview" 
                                                 src="<?php echo UPLOADS_URL; ?>/guias/<?php echo $guia['foto_perfil']; ?>" 
                                                 class="preview-foto mb-3"
                                                 alt="Preview">
                                        <?php else: ?>
                                            <img id="preview" 
                                                 src="<?php echo ASSETS_URL; ?>/img/default-avatar.png" 
                                                 class="preview-foto mb-3"
                                                 alt="Preview">
                                        <?php endif; ?>
                                        <div>
                                            <label for="foto_perfil" class="btn btn-outline-primary">
                                                <i class="fas fa-camera"></i> Cambiar Foto
                                            </label>
                                            <input type="file" 
                                                   id="foto_perfil" 
                                                   name="foto_perfil" 
                                                   accept="image/*"
                                                   class="d-none">
                                            <p class="text-muted small mt-2">Foto cuadrada recomendada (mín. 300x300px)</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <!-- Información Personal -->
                                <div class="col-md-6">
                                    <h5 class="mb-3">Información Personal</h5>
                                    
                                    <div class="mb-3">
                                        <label for="nombre_completo" class="form-label">
                                            Nombre Completo <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="nombre_completo" 
                                               name="nombre_completo"
                                               value="<?php echo htmlspecialchars($guia['nombre_completo']); ?>"
                                               required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="fecha_nacimiento" class="form-label">
                                            Fecha de Nacimiento <span class="text-danger">*</span>
                                        </label>
                                        <input type="date" 
                                               class="form-control" 
                                               id="fecha_nacimiento" 
                                               name="fecha_nacimiento"
                                               value="<?php echo $guia['fecha_nacimiento']; ?>"
                                               max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>"
                                               required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="curp" class="form-label">
                                            CURP <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" 
                                               class="form-control text-uppercase" 
                                               id="curp" 
                                               name="curp"
                                               value="<?php echo htmlspecialchars($guia['curp']); ?>"
                                               maxlength="18"
                                               pattern="[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z][0-9]"
                                               required>
                                        <div class="form-text">18 caracteres (ej: AAAA000000HDFXXX00)</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="telefono" class="form-label">
                                            Teléfono <span class="text-danger">*</span>
                                        </label>
                                        <input type="tel" 
                                               class="form-control" 
                                               id="telefono" 
                                               name="telefono"
                                               value="<?php echo htmlspecialchars($guia['telefono']); ?>"
                                               pattern="[0-9]{10}"
                                               required>
                                        <div class="form-text">10 dígitos sin espacios</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="domicilio" class="form-label">
                                            Domicilio <span class="text-danger">*</span>
                                        </label>
                                        <textarea class="form-control" 
                                                  id="domicilio" 
                                                  name="domicilio"
                                                  rows="3"
                                                  required><?php echo htmlspecialchars($guia['domicilio']); ?></textarea>
                                    </div>
                                </div>
                                
                                <!-- Información de Acceso -->
                                <div class="col-md-6">
                                    <h5 class="mb-3">Información de Acceso</h5>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">
                                            Email <span class="text-danger">*</span>
                                        </label>
                                        <input type="email" 
                                               class="form-control" 
                                               id="email" 
                                               name="email"
                                               value="<?php echo htmlspecialchars($guia['email']); ?>"
                                               required>
                                        <div class="form-text">Este es el usuario para iniciar sesión</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">
                                            Nueva Contraseña
                                        </label>
                                        <input type="password" 
                                               class="form-control" 
                                               id="password" 
                                               name="password"
                                               minlength="8">
                                        <div class="form-text">Dejar en blanco para mantener la actual. Mínimo 8 caracteres si se cambia.</div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="password_confirm" class="form-label">
                                            Confirmar Nueva Contraseña
                                        </label>
                                        <input type="password" 
                                               class="form-control" 
                                               id="password_confirm"
                                               minlength="8">
                                    </div>
                                    
                                    <h5 class="mb-3">Idiomas</h5>
                                    
                                    <div class="mb-3">
                                        <?php 
                                        $idiomasGuia = array_column($guia['idiomas'], 'idioma');
                                        ?>
                                        <div class="form-check">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   id="idioma_espanol" 
                                                   name="idiomas[]" 
                                                   value="español"
                                                   <?php echo in_array('español', $idiomasGuia) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="idioma_espanol">
                                                <i class="fas fa-check-circle text-success"></i> Español
                                            </label>
                                        </div>
                                        
                                        <div class="form-check">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   id="idioma_ingles" 
                                                   name="idiomas[]" 
                                                   value="inglés"
                                                   <?php echo in_array('inglés', $idiomasGuia) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="idioma_ingles">
                                                <i class="fas fa-check-circle text-info"></i> Inglés
                                            </label>
                                        </div>
                                        
                                        <div class="form-check">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   id="idioma_frances" 
                                                   name="idiomas[]" 
                                                   value="francés"
                                                   <?php echo in_array('francés', $idiomasGuia) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="idioma_frances">
                                                <i class="fas fa-check-circle text-primary"></i> Francés
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-save"></i> Guardar Cambios
                                    </button>
                                    <a href="detalle.php?id=<?php echo $idGuia; ?>" class="btn btn-outline-secondary btn-lg">
                                        <i class="fas fa-times"></i> Cancelar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Preview de la foto
    document.getElementById('foto_perfil').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('preview').src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Validación de contraseñas
    document.getElementById('formGuia').addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const passwordConfirm = document.getElementById('password_confirm').value;
        
        // Solo validar si se ingresó una nueva contraseña
        if (password || passwordConfirm) {
            if (password !== passwordConfirm) {
                e.preventDefault();
                alert('Las contraseñas no coinciden');
                return false;
            }
        }
        
        // Validar que al menos un idioma esté seleccionado
        const idiomas = document.querySelectorAll('input[name="idiomas[]"]:checked');
        if (idiomas.length === 0) {
            e.preventDefault();
            alert('Debe seleccionar al menos un idioma');
            return false;
        }
    });
    
    // Auto-uppercase para CURP
    document.getElementById('curp').addEventListener('input', function(e) {
        e.target.value = e.target.value.toUpperCase();
    });
    </script>
</body>
</html>