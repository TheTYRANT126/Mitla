<?php
/**
 * ============================================
 * RUTA: admin/guias/crear.php
 * ============================================
 * Formulario para registrar un nuevo guía
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Guia.php';
require_once __DIR__ . '/../../classes/Database.php';

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    redirect(SITE_URL . '/admin/login.php');
}

$guiaClass = new Guia();
$db = Database::getInstance()->getConnection();
$tmpDir = UPLOADS_PATH . '/tmp_guias';
$tmpUrl = UPLOADS_URL . '/tmp_guias';

$errores = [];
$datos = [
    'nombre_completo'   => '',
    'email'             => '',
    'fecha_nacimiento'  => '',
    'curp'              => '',
    'domicilio'         => '',
    'telefono'          => '',
    'idiomas'           => []
];
$fotoTemp = $_POST['foto_temp'] ?? '';
if ($fotoTemp && (!is_dir($tmpDir) || !is_file($tmpDir . '/' . $fotoTemp))) {
    $fotoTemp = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $errores[] = 'Token de seguridad inválido';
    } else {
        $datos = [
            'nombre_completo'  => trim($_POST['nombre_completo'] ?? ''),
            'email'            => trim($_POST['email'] ?? ''),
            'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? '',
            'curp'             => strtoupper(trim($_POST['curp'] ?? '')),
            'domicilio'        => trim($_POST['domicilio'] ?? ''),
            'telefono'         => trim($_POST['telefono'] ?? ''),
            'idiomas'          => $_POST['idiomas'] ?? []
        ];

        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if ($datos['nombre_completo'] === '') {
            $errores[] = 'El nombre completo es requerido';
        }

        if ($datos['email'] === '' || !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'Email inválido';
        }

        if ($datos['fecha_nacimiento'] === '') {
            $errores[] = 'La fecha de nacimiento es requerida';
        }

        if ($datos['curp'] === '' || strlen($datos['curp']) !== 18) {
            $errores[] = 'CURP inválido (debe tener 18 caracteres)';
        }

        if ($datos['telefono'] === '') {
            $errores[] = 'El teléfono es requerido';
        }

        if (empty($datos['idiomas'])) {
            $errores[] = 'Debe seleccionar al menos un idioma';
        }

        if ($password === '' || strlen($password) < 8) {
            $errores[] = 'La contraseña es obligatoria y debe tener al menos 8 caracteres';
        }

        if ($password !== $passwordConfirm) {
            $errores[] = 'Las contraseñas no coinciden';
        }

        if (empty($errores)) {
            $stmt = $db->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ?');
            $stmt->execute([$datos['email']]);
            if ($stmt->fetchColumn() > 0) {
                $errores[] = 'El email ya está registrado';
            }
        }

        if (empty($errores)) {
            try {
                $resultado = $guiaClass->crear([
                    'nombre_completo'  => $datos['nombre_completo'],
                    'email'            => $datos['email'],
                    'password'         => $password,
                    'fecha_nacimiento' => $datos['fecha_nacimiento'],
                    'curp'             => $datos['curp'],
                    'domicilio'        => $datos['domicilio'],
                    'telefono'         => $datos['telefono'],
                    'idiomas'          => $datos['idiomas'],
                    'foto_perfil'      => null
                ]);

                if (!$resultado['success']) {
                    $errores[] = $resultado['message'];
                } else {
                    $nuevoId = $resultado['id_guia'];

                    $subidaExitosa = false;
                    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
                        $upload = $guiaClass->subirFoto($nuevoId, $_FILES['foto_perfil']);
                        if (!$upload['success']) {
                            $errores[] = $upload['message'];
                        } else {
                            $subidaExitosa = true;
                        }
                        if ($fotoTemp && is_file($tmpDir . '/' . $fotoTemp)) {
                            @unlink($tmpDir . '/' . $fotoTemp);
                            $fotoTemp = '';
                        }
                    } elseif (!empty($fotoTemp)) {
                        $tmpPath = $tmpDir . '/' . basename($fotoTemp);
                        if (is_file($tmpPath)) {
                            $extension = pathinfo($tmpPath, PATHINFO_EXTENSION) ?: 'jpg';
                            $nombreFinal = 'guia_' . $nuevoId . '_' . time() . '.' . $extension;
                            $destino = UPLOADS_PATH . '/guias/' . $nombreFinal;
                            if (!is_dir(UPLOADS_PATH . '/guias')) {
                                mkdir(UPLOADS_PATH . '/guias', 0755, true);
                            }
                            if (@rename($tmpPath, $destino)) {
                                $stmt = $db->prepare("UPDATE guias SET foto_perfil = ? WHERE id_guia = ?");
                                $stmt->execute([$nombreFinal, $nuevoId]);
                                $subidaExitosa = true;
                                $fotoTemp = '';
                            }
                        }
                    }

                    if (empty($errores)) {
                        $_SESSION['mensaje'] = 'Guía registrado correctamente';
                        $_SESSION['mensaje_tipo'] = 'success';
                        redirect(SITE_URL . '/admin/guias/detalle.php?id=' . $nuevoId);
                    }
                }
            } catch (Exception $e) {
                $errores[] = 'Error al registrar el guía: ' . $e->getMessage();
            }
        }
    }

    if (!empty($errores)) {
        if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }
            if ($fotoTemp && is_file($tmpDir . '/' . $fotoTemp)) {
                @unlink($tmpDir . '/' . $fotoTemp);
                $fotoTemp = '';
            }
            $extension = pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION) ?: 'jpg';
            $tempName = 'tmp_guia_' . session_id() . '_' . time() . '.' . strtolower($extension);
            if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $tmpDir . '/' . $tempName)) {
                $fotoTemp = $tempName;
            }
        }
    }
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$pageTitle = 'Registrar Guía';
$previewFoto = ($fotoTemp && is_file($tmpDir . '/' . $fotoTemp))
    ? $tmpUrl . '/' . $fotoTemp
    : ASSETS_URL . '/img/default-avatar.png';
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
                        Registrar Guía
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
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

                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="preview-container">
                                        <img id="preview" src="<?php echo htmlspecialchars($previewFoto); ?>" class="preview-foto mb-3" alt="Preview">
                                        <input type="hidden" id="foto_temp_input" name="foto_temp" value="<?php echo htmlspecialchars($fotoTemp); ?>">
                                        <div class="mb-3">
                                            <label for="foto_perfil" class="form-label">Foto de Perfil (opcional)</label>
                                            <input type="file" class="form-control" id="foto_perfil" name="foto_perfil" accept="image/*">
                                            <div class="form-text">Formato cuadrado recomendado para una mejor visualización.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="nombre_completo" class="form-label">Nombre Completo</label>
                                        <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" value="<?php echo htmlspecialchars($datos['nombre_completo']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                                        <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" value="<?php echo htmlspecialchars($datos['fecha_nacimiento']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="curp" class="form-label">CURP</label>
                                        <input type="text" class="form-control" id="curp" name="curp" maxlength="18" value="<?php echo htmlspecialchars($datos['curp']); ?>" required>
                                        <div class="form-text">Debe contener 18 caracteres.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="domicilio" class="form-label">Domicilio</label>
                                        <textarea class="form-control" id="domicilio" name="domicilio" rows="3" required><?php echo htmlspecialchars($datos['domicilio']); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="telefono" class="form-label">Teléfono</label>
                                        <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($datos['telefono']); ?>" required>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email del Guía</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($datos['email']); ?>" required>
                                        <div class="form-text">Será su usuario para iniciar sesión.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="password" class="form-label">Contraseña</label>
                                        <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                                    </div>

                                    <div class="mb-4">
                                        <label for="password_confirm" class="form-label">Confirmar Contraseña</label>
                                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" minlength="8" required>
                                    </div>

                                    <h5 class="mb-3">Idiomas que domina</h5>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="idioma_espanol" name="idiomas[]" value="español" <?php echo in_array('español', $datos['idiomas']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="idioma_espanol">Español</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="idioma_ingles" name="idiomas[]" value="ingles" <?php echo in_array('ingles', $datos['idiomas']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="idioma_ingles">Inglés</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="idioma_frances" name="idiomas[]" value="frances" <?php echo in_array('frances', $datos['idiomas']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="idioma_frances">Francés</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-save"></i> Registrar Guía
                                    </button>
                                    <a href="index.php" class="btn btn-outline-secondary btn-lg">
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
    const fotoInput = document.getElementById('foto_perfil');
    const fotoTempInput = document.getElementById('foto_temp_input');

    fotoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                document.getElementById('preview').src = ev.target.result;
            };
            reader.readAsDataURL(file);
        }
        if (fotoTempInput) {
            fotoTempInput.value = '';
        }
    });

    document.getElementById('formGuia').addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const passwordConfirm = document.getElementById('password_confirm').value;
        const idiomas = document.querySelectorAll('input[name="idiomas[]"]:checked');

        if (password !== passwordConfirm) {
            e.preventDefault();
            alert('Las contraseñas no coinciden');
            return false;
        }

        if (idiomas.length === 0) {
            e.preventDefault();
            alert('Debe seleccionar al menos un idioma');
            return false;
        }

        return true;
    });
    </script>
</body>
</html>
