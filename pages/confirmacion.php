<?php


require_once __DIR__ . '/../config/config.php';

// Verificar que se haya proporcionado un código
if (!isset($_GET['codigo']) || empty($_GET['codigo'])) {
    redirect(SITE_URL);
}

$codigo_reservacion = sanitize($_GET['codigo']);

// Obtener información de la reservación
$db = Database::getInstance();
$reservacion = $db->fetchOne(
    "SELECT r.*, c.nombre_completo, c.email, p.nombre_paquete, p.duracion_horas
     FROM reservaciones r
     INNER JOIN clientes c ON r.id_cliente = c.id_cliente
     INNER JOIN paquetes p ON r.id_paquete = p.id_paquete
     WHERE r.codigo_reservacion = ?",
    [$codigo_reservacion]
);

if (!$reservacion) {
    redirect(SITE_URL);
}

$lang = getLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Reservación - <?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/confirmacion.css">
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
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/pages/mis-reservas.php">Mis Reservas</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Confirmación de Reservación -->
    <section class="confirmation-section" style="margin-top: 100px;">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    
                    <!-- Mensaje de éxito -->
                    <div class="success-message text-center mb-5">
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h1 class="success-title">¡Reservación Exitosa!</h1>
                        <p class="success-subtitle">Tu ticket ha sido generado correctamente</p>
                    </div>
                    
                    <!-- Ticket -->
                    <div class="ticket-container" id="ticket">
                        <div class="ticket-header">
                            <div class="ticket-logo">
                                <?php if (file_exists($logoPath)): ?>
                                    <img src="<?php echo ASSETS_URL; ?>/img/logo.png" alt="<?php echo SITE_NAME; ?>">
                                <?php else: ?>
                                    <h2><?php echo SITE_NAME; ?></h2>
                                <?php endif; ?>
                            </div>
                            <div class="ticket-code">
                                <div class="code-label">Código de Reservación</div>
                                <div class="code-value"><?php echo htmlspecialchars($codigo_reservacion); ?></div>
                            </div>
                        </div>
                        
                        <div class="ticket-divider"></div>
                        
                        <div class="ticket-body">
                            <div class="ticket-section">
                                <h3 class="section-title">Información del Tour</h3>
                                <div class="info-row">
                                    <span class="info-label"><i class="fas fa-map-marked-alt"></i> Paquete:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($reservacion['nombre_paquete']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label"><i class="fas fa-calendar-alt"></i> Fecha:</span>
                                    <span class="info-value"><?php echo formatearFecha($reservacion['fecha_tour'], 'd/m/Y'); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label"><i class="fas fa-clock"></i> Horario:</span>
                                    <span class="info-value"><?php echo date('g:i a', strtotime($reservacion['hora_inicio'])); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label"><i class="fas fa-hourglass-half"></i> Duración:</span>
                                    <span class="info-value"><?php echo $reservacion['duracion_horas']; ?> horas</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label"><i class="fas fa-language"></i> Idioma:</span>
                                    <span class="info-value"><?php echo ucfirst($reservacion['idioma_tour']); ?></span>
                                </div>
                            </div>
                            
                            <div class="ticket-section">
                                <h3 class="section-title">Información del Cliente</h3>
                                <div class="info-row">
                                    <span class="info-label"><i class="fas fa-user"></i> Nombre:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($reservacion['nombre_completo']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label"><i class="fas fa-envelope"></i> Email:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($reservacion['email']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label"><i class="fas fa-users"></i> Número de personas:</span>
                                    <span class="info-value"><?php echo $reservacion['numero_personas']; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label"><i class="fas fa-user-friends"></i> Guías asignados:</span>
                                    <span class="info-value"><?php echo $reservacion['numero_guias_requeridos']; ?></span>
                                </div>
                            </div>
                            
                            <div class="ticket-section">
                                <h3 class="section-title">Resumen de Pago</h3>
                                <div class="info-row">
                                    <span class="info-label">Total:</span>
                                    <span class="info-value total-price"><?php echo formatearPrecio($reservacion['total']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Estado:</span>
                                    <span class="badge bg-warning">
                                        <?php echo ucfirst($reservacion['estado']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="ticket-section ticket-footer">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Importante:</strong> Guarda este código de reservación. 
                                    Lo necesitarás para consultar o modificar tu reserva. 
                                    También hemos enviado una copia a tu correo electrónico.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Acciones -->
                    <div class="ticket-actions text-center mt-4">
                        <button onclick="window.print()" class="btn btn-primary btn-lg">
                            <i class="fas fa-print"></i> Imprimir Ticket
                        </button>
                        <button onclick="descargarPDF()" class="btn btn-outline-primary btn-lg ms-3">
                            <i class="fas fa-file-pdf"></i> Descargar PDF
                        </button>
                        <a href="<?php echo SITE_URL; ?>" class="btn btn-outline-secondary btn-lg ms-3">
                            <i class="fas fa-home"></i> Volver al Inicio
                        </a>
                    </div>
                    
                    <!-- Información adicional -->
                    <div class="additional-info mt-5">
                        <h3 class="text-center mb-4">¿Qué sigue?</h3>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="info-card">
                                    <i class="fas fa-envelope-open-text fa-3x mb-3"></i>
                                    <h5>Revisa tu email</h5>
                                    <p>Hemos enviado una confirmación con todos los detalles a tu correo.</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="info-card">
                                    <i class="fas fa-bell fa-3x mb-3"></i>
                                    <h5>Recordatorio automático</h5>
                                    <p>Te enviaremos un recordatorio <?php echo DIAS_ANTICIPACION_RECORDATORIO; ?> días antes de tu tour.</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="info-card">
                                    <i class="fas fa-map-marker-alt fa-3x mb-3"></i>
                                    <h5>Punto de encuentro</h5>
                                    <p>Centro Interpretativo de Unión Zapata, Mitla, Oaxaca.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </section>
    
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
    
    <!-- jsPDF para exportar PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <script>
        /**
         * Descargar ticket como PDF
         */
        function descargarPDF() {
            const ticket = document.getElementById('ticket');
            const codigoReservacion = '<?php echo $codigo_reservacion; ?>';
            
            // Ocultar botones temporalmente
            const actions = document.querySelector('.ticket-actions');
            actions.style.display = 'none';
            
            html2canvas(ticket, {
                scale: 2,
                logging: false,
                useCORS: true
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const pdf = new jspdf.jsPDF({
                    orientation: 'portrait',
                    unit: 'mm',
                    format: 'a4'
                });
                
                const imgWidth = 210; // A4 width in mm
                const pageHeight = 297; // A4 height in mm
                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                
                pdf.addImage(imgData, 'PNG', 0, 0, imgWidth, imgHeight);
                pdf.save(`ticket-${codigoReservacion}.pdf`);
                
                // Mostrar botones de nuevo
                actions.style.display = 'block';
            });
        }
        
        // Animación de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const ticket = document.querySelector('.ticket-container');
            ticket.style.opacity = '0';
            ticket.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                ticket.style.transition = 'all 0.6s ease';
                ticket.style.opacity = '1';
                ticket.style.transform = 'translateY(0)';
            }, 300);
        });
    </script>
</body>
</html>