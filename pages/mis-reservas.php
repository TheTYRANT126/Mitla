<?php


require_once __DIR__ . '/../config/config.php';

$lang = getLanguage();
$reservacion = null;
$error = null;

// Si se envió el formulario de búsqueda
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo_reservacion'])) {
    $codigo = sanitize($_POST['codigo_reservacion']);
    
    if (!empty($codigo)) {
        $db = Database::getInstance();
        $reservacion = $db->fetchOne(
            "SELECT r.*, c.nombre_completo, c.email, p.nombre_paquete, p.duracion_horas
             FROM reservaciones r
             INNER JOIN clientes c ON r.id_cliente = c.id_cliente
             INNER JOIN paquetes p ON r.id_paquete = p.id_paquete
             WHERE r.codigo_reservacion = ?",
            [$codigo]
        );
        
        if (!$reservacion) {
            $error = "No se encontró ninguna reservación con ese código.";
        }
    } else {
        $error = "Por favor ingrese un código de reservación.";
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Reservas - <?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    
    <style>
        .search-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 80px 0;
            margin-top: 70px;
        }
        
        .search-box {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .search-box h2 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .search-input {
            font-size: 1.2rem;
            padding: 15px;
            border-radius: 10px;
            border: 2px solid #dee2e6;
        }
        
        .search-input:focus {
            border-color: #0066cc;
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }
        
        .btn-search {
            padding: 15px 40px;
            font-size: 1.2rem;
            border-radius: 50px;
            font-weight: 600;
        }
        
        .result-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }
    </style>
</head>
<body>
    
    <!-- Navegación -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <?php 
                $logoPath = ASSETS_PATH . '/img/logo.png';
                if (file_exists($logoPath)): 
                ?>
                    <img src="<?php echo ASSETS_URL; ?>/img/logo.png" alt="<?php echo SITE_NAME; ?>" class="navbar-logo">
                <?php else: ?>
                    <i class="fas fa-mountain"></i> <?php echo SITE_NAME; ?>
                <?php endif; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/pages/paquetes.php">Paquetes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?php echo SITE_URL; ?>/pages/mis-reservas.php">Mis Reservas</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Sección de Búsqueda -->
    <section class="search-section">
        <div class="container">
            <div class="search-box">
                <h2 class="text-center mb-4">
                    <i class="fas fa-search"></i> Buscar Mi Reservación
                </h2>
                
                <p class="text-center text-muted mb-4">
                    Ingresa tu código de reservación para consultar, modificar o cancelar tu reserva
                </p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="codigo_reservacion" class="form-label">Código de Reservación</label>
                        <input type="text" 
                               class="form-control search-input" 
                               id="codigo_reservacion" 
                               name="codigo_reservacion"
                               placeholder="Ej: MT20251019-ABC123"
                               value="<?php echo isset($_POST['codigo_reservacion']) ? htmlspecialchars($_POST['codigo_reservacion']) : ''; ?>"
                               required>
                        <small class="form-text text-muted">
                            El código se encuentra en tu email de confirmación o en tu ticket
                        </small>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-search">
                            <i class="fas fa-search"></i> Buscar Reservación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
    
    <!-- Resultados de Búsqueda -->
    <?php if ($reservacion): ?>
    <section class="results-section py-5">
        <div class="container">
            <div class="result-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>
                        <i class="fas fa-ticket-alt"></i> Detalles de la Reservación
                    </h3>
                    <span class="badge bg-<?php 
                        echo $reservacion['estado'] == 'confirmada' ? 'success' : 
                            ($reservacion['estado'] == 'pendiente' ? 'warning' : 
                            ($reservacion['estado'] == 'cancelada' ? 'danger' : 'primary'));
                    ?> fs-6">
                        <?php echo ucfirst($reservacion['estado']); ?>
                    </span>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Información del Tour</h5>
                        <p><strong>Paquete:</strong> <?php echo htmlspecialchars($reservacion['nombre_paquete']); ?></p>
                        <p><strong>Fecha:</strong> <?php echo formatearFecha($reservacion['fecha_tour']); ?></p>
                        <p><strong>Horario:</strong> <?php echo date('g:i a', strtotime($reservacion['hora_inicio'])); ?></p>
                        <p><strong>Duración:</strong> <?php echo $reservacion['duracion_horas']; ?> horas</p>
                    </div>
                    
                    <div class="col-md-6">
                        <h5>Información del Cliente</h5>
                        <p><strong>Nombre:</strong> <?php echo htmlspecialchars($reservacion['nombre_completo']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($reservacion['email']); ?></p>
                        <p><strong>Personas:</strong> <?php echo $reservacion['numero_personas']; ?></p>
                        <p><strong>Total:</strong> <?php echo formatearPrecio($reservacion['total']); ?></p>
                    </div>
                </div>
                
                <div class="text-center">
                    <!-- Botones para acciones futuras -->
                    <a href="<?php echo SITE_URL; ?>/pages/confirmacion.php?codigo=<?php echo $reservacion['codigo_reservacion']; ?>" 
                       class="btn btn-primary">
                        <i class="fas fa-file-alt"></i> Ver Ticket Completo
                    </a>
                    
                    <!-- TODO: Implementar más adelante -->
                    <?php if (esFechaFutura($reservacion['fecha_tour']) && $reservacion['estado'] != 'cancelada'): ?>
                        <button class="btn btn-warning" disabled title="Funcionalidad próximamente">
                            <i class="fas fa-edit"></i> Modificar Reserva
                        </button>
                        <button class="btn btn-danger" disabled title="Funcionalidad próximamente">
                            <i class="fas fa-times"></i> Cancelar Reserva
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-2">
                <i class="fas fa-envelope"></i> <?php echo SITE_EMAIL; ?> | 
                <i class="fas fa-phone"></i> 951-123-4567
            </p>
            <p class="mb-0">
                &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. 
                Todos los derechos reservados
            </p>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>