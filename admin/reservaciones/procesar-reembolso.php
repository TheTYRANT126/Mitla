<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    redirect(SITE_URL . '/admin/login.php');
}

$idReservacion = intval($_GET['id'] ?? 0);

if ($idReservacion === 0) {
    $_SESSION['mensaje'] = 'ID de reservación no válido';
    $_SESSION['mensaje_tipo'] = 'danger';
    redirect(SITE_URL . '/admin/reservaciones/');
}

$db = Database::getInstance()->getConnection();

// Obtener información de la reservación
$stmt = $db->prepare("
    SELECT r.*, 
           c.nombre_completo as cliente_nombre, c.email as cliente_email, c.telefono as cliente_telefono,
           p.nombre_paquete as paquete_nombre
    FROM reservaciones r
    INNER JOIN clientes c ON r.id_cliente = c.id_cliente
    INNER JOIN paquetes p ON r.id_paquete = p.id_paquete
    WHERE r.id_reservacion = ?
");
$stmt->execute([$idReservacion]);
$reservacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reservacion) {
    $_SESSION['mensaje'] = 'Reservación no encontrada';
    $_SESSION['mensaje_tipo'] = 'danger';
    redirect(SITE_URL . '/admin/reservaciones/');
}

// Verificar que la reservación esté cancelada
if ($reservacion['estado'] !== 'cancelada') {
    $_SESSION['mensaje'] = 'Solo se pueden procesar reembolsos para reservaciones canceladas';
    $_SESSION['mensaje_tipo'] = 'warning';
    redirect(SITE_URL . '/admin/reservaciones/detalle.php?id=' . $idReservacion);
}

// Verificar que haya un pago registrado
if ($reservacion['estado_pago'] !== 'pagado') {
    $_SESSION['mensaje'] = 'Esta reservación no tiene un pago registrado';
    $_SESSION['mensaje_tipo'] = 'warning';
    redirect(SITE_URL . '/admin/reservaciones/detalle.php?id=' . $idReservacion);
}

// Verificar si ya se procesó un reembolso
$stmt = $db->prepare("SELECT COUNT(*) FROM reembolsos WHERE id_reservacion = ?");
$stmt->execute([$idReservacion]);
$yaReembolsado = $stmt->fetchColumn() > 0;

if ($yaReembolsado) {
    $_SESSION['mensaje'] = 'Ya se procesó un reembolso para esta reservación';
    $_SESSION['mensaje_tipo'] = 'info';
    redirect(SITE_URL . '/admin/reservaciones/detalle.php?id=' . $idReservacion);
}

$errores = [];
$datos = [
    'monto' => $reservacion['monto_total'],
    'metodo' => 'efectivo',
    'notas' => ''
];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errores[] = 'Token de seguridad inválido';
    } else {
        $datos = [
            'monto' => floatval($_POST['monto'] ?? 0),
            'metodo' => $_POST['metodo'] ?? '',
            'notas' => trim($_POST['notas'] ?? '')
        ];
        
        // Validaciones
        if ($datos['monto'] <= 0) {
            $errores[] = 'El monto debe ser mayor a cero';
        }
        
        if ($datos['monto'] > $reservacion['monto_total']) {
            $errores[] = 'El monto del reembolso no puede ser mayor al monto total de la reservación';
        }
        
        if (!in_array($datos['metodo'], ['efectivo', 'transferencia', 'tarjeta', 'otro'])) {
            $errores[] = 'Método de reembolso no válido';
        }
        
        if (empty($errores)) {
            try {
                // Procesar reembolso usando la API
                $ch = curl_init(SITE_URL . '/api/admin/procesar-reembolso.php');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'id_reservacion' => $idReservacion,
                    'monto' => $datos['monto'],
                    'metodo' => $datos['metodo'],
                    'notas' => $datos['notas']
                ]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Cookie: ' . session_name() . '=' . session_id()
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    $_SESSION['mensaje'] = 'Reembolso procesado correctamente';
                    $_SESSION['mensaje_tipo'] = 'success';
                    redirect(SITE_URL . '/admin/reservaciones/detalle.php?id=' . $idReservacion);
                } else {
                    $result = json_decode($response, true);
                    $errores[] = $result['message'] ?? 'Error al procesar el reembolso';
                }
                
            } catch (Exception $e) {
                $errores[] = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Generar nuevo CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$pageTitle = 'Procesar Reembolso';
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
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-undo"></i> Procesar Reembolso
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="detalle.php?id=<?php echo $idReservacion; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver a Detalle
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
                
                <div class="row">
                    <!-- Información de la Reservación -->
                    <div class="col-md-5">
                        <div class="card shadow mb-4">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-exclamation-triangle"></i> Reservación Cancelada
                                </h5>
                            </div>
                            <div class="card-body">
                                <h6 class="text-primary">
                                    <?php echo htmlspecialchars($reservacion['codigo_reservacion']); ?>
                                </h6>
                                
                                <hr>
                                
                                <p class="mb-2">
                                    <strong>Cliente:</strong><br>
                                    <?php echo htmlspecialchars($reservacion['cliente_nombre']); ?>
                                </p>
                                
                                <p class="mb-2">
                                    <strong>Email:</strong><br>
                                    <a href="mailto:<?php echo htmlspecialchars($reservacion['cliente_email']); ?>">
                                        <?php echo htmlspecialchars($reservacion['cliente_email']); ?>
                                    </a>
                                </p>
                                
                                <p class="mb-2">
                                    <strong>Teléfono:</strong><br>
                                    <a href="tel:<?php echo htmlspecialchars($reservacion['cliente_telefono']); ?>">
                                        <?php echo htmlspecialchars($reservacion['cliente_telefono']); ?>
                                    </a>
                                </p>
                                
                                <hr>
                                
                                <p class="mb-2">
                                    <strong>Paquete:</strong><br>
                                    <?php echo htmlspecialchars($reservacion['paquete_nombre']); ?>
                                </p>
                                
                                <p class="mb-2">
                                    <strong>Fecha del Tour:</strong><br>
                                    <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($reservacion['fecha_reservacion'])); ?>
                                    <i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($reservacion['hora_inicio'])); ?>
                                </p>
                                
                                <p class="mb-2">
                                    <strong>Personas:</strong><br>
                                    <i class="fas fa-users"></i> <?php echo $reservacion['numero_personas']; ?>
                                </p>
                                
                                <hr>
                                
                                <p class="mb-2">
                                    <strong>Monto Total Pagado:</strong><br>
                                    <span class="h4 text-success">$<?php echo number_format($reservacion['monto_total'], 2); ?></span>
                                </p>
                                
                                <p class="mb-0">
                                    <strong>Estado de Pago:</strong><br>
                                    <span class="badge bg-success"><?php echo ucfirst($reservacion['estado_pago']); ?></span>
                                </p>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle"></i>
                            <strong>Importante:</strong> Esta acción registrará el reembolso en el sistema. 
                            Asegúrese de haber realizado la transacción antes de confirmar.
                        </div>
                    </div>
                    
                    <!-- Formulario de Reembolso -->
                    <div class="col-md-7">
                        <div class="card shadow mb-4">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-dollar-sign"></i> Datos del Reembolso
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="formReembolso">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    
                                    <div class="mb-4">
                                        <label for="monto" class="form-label">
                                            <strong>Monto a Reembolsar</strong> <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group input-group-lg">
                                            <span class="input-group-text">$</span>
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="monto" 
                                                   name="monto"
                                                   value="<?php echo $datos['monto']; ?>"
                                                   min="0.01"
                                                   max="<?php echo $reservacion['monto_total']; ?>"
                                                   step="0.01"
                                                   required>
                                        </div>
                                        <div class="form-text">
                                            Monto máximo: $<?php echo number_format($reservacion['monto_total'], 2); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="metodo" class="form-label">
                                            <strong>Método de Reembolso</strong> <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select form-select-lg" id="metodo" name="metodo" required>
                                            <option value="efectivo" <?php echo $datos['metodo'] === 'efectivo' ? 'selected' : ''; ?>>
                                                Efectivo
                                            </option>
                                            <option value="transferencia" <?php echo $datos['metodo'] === 'transferencia' ? 'selected' : ''; ?>>
                                                Transferencia Bancaria
                                            </option>
                                            <option value="tarjeta" <?php echo $datos['metodo'] === 'tarjeta' ? 'selected' : ''; ?>>
                                                Devolución a Tarjeta
                                            </option>
                                            <option value="otro" <?php echo $datos['metodo'] === 'otro' ? 'selected' : ''; ?>>
                                                Otro
                                            </option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="notas" class="form-label">
                                            <strong>Notas / Referencia</strong>
                                        </label>
                                        <textarea class="form-control" 
                                                  id="notas" 
                                                  name="notas"
                                                  rows="4"
                                                  placeholder="Ingrese cualquier información adicional: número de referencia, motivo específico, etc."><?php echo htmlspecialchars($datos['notas']); ?></textarea>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-info-circle"></i> Antes de procesar el reembolso:</h6>
                                        <ul class="mb-0">
                                            <li>Verifique que el monto sea correcto</li>
                                            <li>Confirme que ya realizó la transacción al cliente</li>
                                            <li>Guarde el comprobante de la transacción</li>
                                        </ul>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-danger btn-lg">
                                            <i class="fas fa-check-circle"></i> Confirmar Reembolso de $<?php echo number_format($reservacion['monto_total'], 2); ?>
                                        </button>
                                        <a href="detalle.php?id=<?php echo $idReservacion; ?>" class="btn btn-outline-secondary btn-lg">
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
    // Actualizar el botón cuando cambie el monto
    document.getElementById('monto').addEventListener('input', function() {
        const monto = parseFloat(this.value) || 0;
        const btnSubmit = document.querySelector('button[type="submit"]');
        btnSubmit.innerHTML = '<i class="fas fa-check-circle"></i> Confirmar Reembolso de $' + monto.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    });
    
    // Confirmación antes de enviar
    document.getElementById('formReembolso').addEventListener('submit', function(e) {
        const monto = parseFloat(document.getElementById('monto').value);
        const metodo = document.getElementById('metodo').options[document.getElementById('metodo').selectedIndex].text;
        
        if (!confirm(`¿Confirma que desea procesar un reembolso de $${monto.toFixed(2)} mediante ${metodo}?\n\nEsta acción no se puede deshacer.`)) {
            e.preventDefault();
            return false;
        }
    });
    </script>
</body>
</html>
