<?php


require_once __DIR__ . '/../config/config.php';

$lang = getLanguage();
$reservacion = null;
$error = null;

// Si se envió el formulario de búsqueda (POST), redirigir a GET
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo_reservacion'])) {
    $codigo = sanitize($_POST['codigo_reservacion']);

    if (!empty($codigo)) {
        // Redirigir usando GET (patrón Post-Redirect-Get)
        redirect(SITE_URL . '/pages/mis-reservas.php?codigo=' . urlencode($codigo));
    } else {
        $error = "Por favor ingrese un código de reservación.";
    }
}

// Si hay un código en GET, buscar la reservación
if (isset($_GET['codigo']) && !empty($_GET['codigo'])) {
    $codigo = sanitize($_GET['codigo']);

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
        body {
            background-image: url('<?php echo ASSETS_URL; ?>/img/Reservacion.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }

        .search-section {
            background: transparent;
            <?php if (!$reservacion): ?>
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            <?php else: ?>
            padding: 100px 0 50px 0;
            <?php endif; ?>
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
        
        .results-section {
            background: transparent;
            padding-bottom: 50px;
        }

        .result-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="langDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-globe"></i>
                            <?php 
                                $langs = ['es' => 'ES', 'en' => 'EN', 'fr' => 'FR'];
                                echo $langs[$lang];
                            ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?lang=es">🇲🇽 Español</a></li>
                            <li><a class="dropdown-item" href="?lang=en">🇺🇸 English</a></li>
                            <li><a class="dropdown-item" href="?lang=fr">🇫🇷 Français</a></li>
                        </ul>
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
                    Buscar Mi Reservación
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
                               value="<?php echo isset($_GET['codigo']) ? htmlspecialchars($_GET['codigo']) : ''; ?>"
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
    <section class="results-section">
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
                    <a href="<?php echo SITE_URL; ?>/pages/confirmacion.php?codigo=<?php echo $reservacion['codigo_reservacion']; ?>"
                       class="btn btn-primary">
                        <i class="fas fa-file-alt"></i> Ver Ticket Completo
                    </a>

                    <?php if (esFechaFutura($reservacion['fecha_tour']) && $reservacion['estado'] != 'cancelada'): ?>
                        <button class="btn btn-warning"
                                data-bs-toggle="tooltip"
                                data-bs-placement="bottom"
                                data-bs-title="La modificación está sujeta a disponibilidad."
                                onclick="modificarReserva('<?php echo $reservacion['codigo_reservacion']; ?>')">
                            <i class="fas fa-edit"></i> Modificar Reserva
                        </button>
                        <button class="btn btn-danger"
                                onclick="confirmarCancelacion('<?php echo $reservacion['codigo_reservacion']; ?>')">
                            <i class="fas fa-times"></i> Cancelar Reserva
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-4 <?php echo !$reservacion ? 'mt-5' : ''; ?>">
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
    
    <!-- Modal para Modificar Reserva -->
    <div class="modal fade" id="modalModificar" tabindex="-1" aria-labelledby="modalModificarLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalModificarLabel">
                        <i class="fas fa-edit"></i> Modificar Reservación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Nota importante:</strong> La modificación está sujeta a disponibilidad.
                    </div>
                    <p>¿Qué deseas modificar?</p>
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action" onclick="alert('Funcionalidad en desarrollo'); return false;">
                            <i class="fas fa-calendar-alt"></i> Cambiar fecha del tour
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="alert('Funcionalidad en desarrollo'); return false;">
                            <i class="fas fa-clock"></i> Cambiar horario
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="alert('Funcionalidad en desarrollo'); return false;">
                            <i class="fas fa-users"></i> Cambiar número de personas
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="alert('Funcionalidad en desarrollo'); return false;">
                            <i class="fas fa-language"></i> Cambiar idioma del tour
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Cancelar Reserva -->
    <div class="modal fade" id="modalCancelar" tabindex="-1" aria-labelledby="modalCancelarLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalCancelarLabel">
                        <i class="fas fa-exclamation-triangle"></i> Cancelar Reservación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>¡Atención!</strong> Esta acción no se puede deshacer.
                    </div>
                    <p>¿Estás seguro que deseas cancelar tu reservación?</p>
                    <form id="formCancelar" method="POST" action="<?php echo SITE_URL; ?>/api/cancelar-reservacion.php">
                        <input type="hidden" name="codigo_reservacion" id="codigoCancelar">
                        <div class="mb-3">
                            <label for="motivo_cancelacion" class="form-label">Motivo de cancelación (opcional):</label>
                            <textarea class="form-control" id="motivo_cancelacion" name="motivo_cancelacion" rows="3" placeholder="Cuéntanos por qué cancelas tu reserva..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, mantener reserva</button>
                    <button type="button" class="btn btn-danger" onclick="procesarCancelacion()">
                        <i class="fas fa-times"></i> Sí, cancelar reserva
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Inicializar tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Función para modificar reserva
        function modificarReserva(codigo) {
            const modal = new bootstrap.Modal(document.getElementById('modalModificar'));
            modal.show();
        }

        // Función para confirmar cancelación
        function confirmarCancelacion(codigo) {
            document.getElementById('codigoCancelar').value = codigo;
            const modal = new bootstrap.Modal(document.getElementById('modalCancelar'));
            modal.show();
        }

        // Función para procesar la cancelación
        function procesarCancelacion() {
            const form = document.getElementById('formCancelar');
            const formData = new FormData(form);

            // Mostrar loading
            const btnCancelar = event.target;
            const originalText = btnCancelar.innerHTML;
            btnCancelar.disabled = true;
            btnCancelar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelando...';

            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Cerrar modal y recargar página
                    bootstrap.Modal.getInstance(document.getElementById('modalCancelar')).hide();
                    alert('Tu reservación ha sido cancelada exitosamente.');
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'No se pudo cancelar la reservación'));
                    btnCancelar.disabled = false;
                    btnCancelar.innerHTML = originalText;
                }
            })
            .catch(error => {
                alert('Error al procesar la cancelación. Por favor intenta de nuevo.');
                btnCancelar.disabled = false;
                btnCancelar.innerHTML = originalText;
            });
        }
    </script>

    <!-- Easter Egg del Footer -->
    <script src="<?php echo ASSETS_URL; ?>/js/footer-easter-egg.js"></script>
</body>
</html>