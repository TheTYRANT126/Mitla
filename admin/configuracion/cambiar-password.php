<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';

$auth = new Auth();

if (!$auth->isAuthenticated()) {
    redirect(SITE_URL . '/admin/login.php');
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errores[] = 'Token de seguridad inválido';
    } else {
        $passwordActual = $_POST['password_actual'] ?? '';
        $passwordNueva = $_POST['password_nueva'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        
        // Validaciones
        if (empty($passwordActual)) {
            $errores[] = 'La contraseña actual es requerida';
        }
        
        if (empty($passwordNueva)) {
            $errores[] = 'La nueva contraseña es requerida';
        }
        
        if (strlen($passwordNueva) < 8) {
            $errores[] = 'La nueva contraseña debe tener al menos 8 caracteres';
        }
        
        if ($passwordNueva !== $passwordConfirm) {
            $errores[] = 'Las contraseñas nuevas no coinciden';
        }
        
        if ($passwordActual === $passwordNueva) {
            $errores[] = 'La nueva contraseña debe ser diferente a la actual';
        }
        
        // Validar complejidad de contraseña
        if (!preg_match('/[A-Z]/', $passwordNueva)) {
            $errores[] = 'La contraseña debe contener al menos una letra mayúscula';
        }
        
        if (!preg_match('/[a-z]/', $passwordNueva)) {
            $errores[] = 'La contraseña debe contener al menos una letra minúscula';
        }
        
        if (!preg_match('/[0-9]/', $passwordNueva)) {
            $errores[] = 'La contraseña debe contener al menos un número';
        }
        
        if (empty($errores)) {
            try {
                $resultado = $auth->cambiarPassword($auth->getUserId(), $passwordActual, $passwordNueva);
                
                if ($resultado) {
                    $_SESSION['mensaje'] = 'Contraseña actualizada correctamente. Por favor, inicie sesión nuevamente.';
                    $_SESSION['mensaje_tipo'] = 'success';
                    
                    // Cerrar sesión por seguridad
                    $auth->logout();
                    redirect(SITE_URL . '/admin/login.php');
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

$pageTitle = 'Cambiar Contraseña';
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
        .password-strength {
            height: 10px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        .strength-weak {
            background-color: #dc3545;
            width: 33%;
        }
        .strength-medium {
            background-color: #ffc107;
            width: 66%;
        }
        .strength-strong {
            background-color: #28a745;
            width: 100%;
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
                        Cambiar Contraseña
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver a Configuración
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
                
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Actualizar Contraseña</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="formPassword">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    
                                    <div class="mb-4">
                                        <label for="password_actual" class="form-label">
                                            <strong>Contraseña Actual</strong> <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-lock"></i>
                                            </span>
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="password_actual" 
                                                   name="password_actual"
                                                   required>
                                            <button class="btn btn-outline-secondary" 
                                                    type="button" 
                                                    onclick="togglePassword('password_actual')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="mb-3">
                                        <label for="password_nueva" class="form-label">
                                            <strong>Nueva Contraseña</strong> <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-key"></i>
                                            </span>
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="password_nueva" 
                                                   name="password_nueva"
                                                   minlength="8"
                                                   required>
                                            <button class="btn btn-outline-secondary" 
                                                    type="button" 
                                                    onclick="togglePassword('password_nueva')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        
                                        <!-- Medidor de Fortaleza -->
                                        <div class="mt-2">
                                            <div class="password-strength" id="strength-bar"></div>
                                            <small id="strength-text" class="text-muted">Ingrese una contraseña</small>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="password_confirm" class="form-label">
                                            <strong>Confirmar Nueva Contraseña</strong> <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-key"></i>
                                            </span>
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="password_confirm" 
                                                   name="password_confirm"
                                                   minlength="8"
                                                   required>
                                            <button class="btn btn-outline-secondary" 
                                                    type="button" 
                                                    onclick="togglePassword('password_confirm')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <h6>Requisitos de Contraseña:</h6>
                                        <ul class="mb-0" id="requisitos">
                                            <li id="req-length">Mínimo 8 caracteres</li>
                                            <li id="req-uppercase">Al menos una letra mayúscula</li>
                                            <li id="req-lowercase">Al menos una letra minúscula</li>
                                            <li id="req-number">Al menos un número</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="alert alert-warning">
                                        <strong>Importante:</strong> Después de cambiar tu contraseña, tendrás que iniciar sesión nuevamente.
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            Cambiar Contraseña
                                        </button>
                                        <a href="index.php" class="btn btn-outline-secondary btn-lg">
                                            <i class="fas fa-times"></i> Cancelar
                                        </a>
                                    </div>
                                </form>
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
    // Toggle mostrar/ocultar contraseña
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = event.currentTarget.querySelector('i');
        
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    
    // Verificar fortaleza de contraseña
    document.getElementById('password_nueva').addEventListener('input', function() {
        const password = this.value;
        const strengthBar = document.getElementById('strength-bar');
        const strengthText = document.getElementById('strength-text');
        
        let strength = 0;
        let feedback = [];
        
        // Longitud
        const reqLength = document.getElementById('req-length');
        if (password.length >= 8) {
            strength++;
            reqLength.classList.add('text-success');
            reqLength.innerHTML = '<i class="fas fa-check"></i> Mínimo 8 caracteres';
        } else {
            reqLength.classList.remove('text-success');
            reqLength.innerHTML = 'Mínimo 8 caracteres';
            feedback.push('longitud mínima');
        }
        
        // Mayúsculas
        const reqUppercase = document.getElementById('req-uppercase');
        if (/[A-Z]/.test(password)) {
            strength++;
            reqUppercase.classList.add('text-success');
            reqUppercase.innerHTML = '<i class="fas fa-check"></i> Al menos una letra mayúscula';
        } else {
            reqUppercase.classList.remove('text-success');
            reqUppercase.innerHTML = 'Al menos una letra mayúscula';
            feedback.push('mayúscula');
        }
        
        // Minúsculas
        const reqLowercase = document.getElementById('req-lowercase');
        if (/[a-z]/.test(password)) {
            strength++;
            reqLowercase.classList.add('text-success');
            reqLowercase.innerHTML = '<i class="fas fa-check"></i> Al menos una letra minúscula';
        } else {
            reqLowercase.classList.remove('text-success');
            reqLowercase.innerHTML = 'Al menos una letra minúscula';
            feedback.push('minúscula');
        }
        
        // Números
        const reqNumber = document.getElementById('req-number');
        if (/[0-9]/.test(password)) {
            strength++;
            reqNumber.classList.add('text-success');
            reqNumber.innerHTML = '<i class="fas fa-check"></i> Al menos un número';
        } else {
            reqNumber.classList.remove('text-success');
            reqNumber.innerHTML = 'Al menos un número';
            feedback.push('número');
        }
        
        // Actualizar barra de fortaleza
        strengthBar.className = 'password-strength';
        
        if (strength === 0) {
            strengthBar.style.width = '0%';
            strengthText.textContent = 'Ingrese una contraseña';
            strengthText.className = 'text-muted';
        } else if (strength <= 2) {
            strengthBar.classList.add('strength-weak');
            strengthText.textContent = 'Débil - Falta: ' + feedback.join(', ');
            strengthText.className = 'text-danger';
        } else if (strength === 3) {
            strengthBar.classList.add('strength-medium');
            strengthText.textContent = 'Media - Falta: ' + feedback.join(', ');
            strengthText.className = 'text-warning';
        } else {
            strengthBar.classList.add('strength-strong');
            strengthText.textContent = 'Fuerte - Todos los requisitos cumplidos';
            strengthText.className = 'text-success';
        }
    });
    
    // Validación del formulario
    document.getElementById('formPassword').addEventListener('submit', function(e) {
        const passwordNueva = document.getElementById('password_nueva').value;
        const passwordConfirm = document.getElementById('password_confirm').value;
        const passwordActual = document.getElementById('password_actual').value;
        
        // Verificar que las contraseñas coincidan
        if (passwordNueva !== passwordConfirm) {
            e.preventDefault();
            alert('Las contraseñas nuevas no coinciden');
            return false;
        }
        
        // Verificar que la nueva contraseña sea diferente
        if (passwordActual === passwordNueva) {
            e.preventDefault();
            alert('La nueva contraseña debe ser diferente a la actual');
            return false;
        }
        
        // Verificar requisitos
        if (passwordNueva.length < 8 || 
            !/[A-Z]/.test(passwordNueva) || 
            !/[a-z]/.test(passwordNueva) || 
            !/[0-9]/.test(passwordNueva)) {
            e.preventDefault();
            alert('La contraseña no cumple con todos los requisitos de seguridad');
            return false;
        }
        
        // Confirmación final
        if (!confirm('¿Está seguro de cambiar su contraseña? Tendrá que iniciar sesión nuevamente.')) {
            e.preventDefault();
            return false;
        }
    });
    </script>
</body>
</html>
