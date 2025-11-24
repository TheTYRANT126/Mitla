<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Reporte.php';
require_once __DIR__ . '/../classes/PDF.php';

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    redirect(SITE_URL . '/admin/login.php');
}

$mensaje = '';
$mensajeTipo = 'info';

// Procesar generación de reporte
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $mensaje = 'Token de seguridad inválido';
        $mensajeTipo = 'danger';
    } else {
        $tipoReporte = $_POST['tipo_reporte'] ?? '';
        $fechaInicio = $_POST['fecha_inicio'] ?? '';
        $fechaFin = $_POST['fecha_fin'] ?? '';
        $formato = $_POST['formato'] ?? 'pdf';
        
        if (empty($fechaInicio) || empty($fechaFin)) {
            $mensaje = 'Las fechas son requeridas';
            $mensajeTipo = 'danger';
        } else {
            try {
                $reporteClass = new Reporte();
                $pdfClass = new PDF();
                
                switch ($tipoReporte) {
                    case 'ventas':
                        $datos = $reporteClass->reporteVentas($fechaInicio, $fechaFin, $formato);
                        
                        if ($formato === 'pdf') {
                            $rutaPDF = $pdfClass->generarReporteVentas($fechaInicio, $fechaFin, $datos);
                            $mensaje = 'Reporte generado correctamente';
                            $mensajeTipo = 'success';
                            $_SESSION['ultimo_reporte'] = basename($rutaPDF);
                        } else {
                            $rutaCSV = $reporteClass->exportarCSV(
                                $datos['ventas_por_dia'],
                                'reporte_ventas_' . date('Y-m-d'),
                                ['Fecha', 'Total Reservaciones', 'Total Personas', 'Ingresos']
                            );
                            $mensaje = 'Reporte generado correctamente';
                            $mensajeTipo = 'success';
                            $_SESSION['ultimo_reporte'] = basename($rutaCSV);
                        }
                        break;
                        
                    case 'guias':
                        $datos = $reporteClass->reporteGuias($fechaInicio, $fechaFin);
                        
                        if ($formato === 'pdf') {
                            $rutaPDF = $pdfClass->generarReporteGuias($fechaInicio, $fechaFin, $datos);
                            $mensaje = 'Reporte generado correctamente';
                            $mensajeTipo = 'success';
                            $_SESSION['ultimo_reporte'] = basename($rutaPDF);
                        } else {
                            $rutaCSV = $reporteClass->exportarCSV(
                                $datos,
                                'reporte_guias_' . date('Y-m-d'),
                                ['Guía', 'Tours', 'Personas Atendidas', 'Días Trabajados']
                            );
                            $mensaje = 'Reporte generado correctamente';
                            $mensajeTipo = 'success';
                            $_SESSION['ultimo_reporte'] = basename($rutaCSV);
                        }
                        break;
                        
                    case 'ocupacion':
                        $datos = $reporteClass->reporteOcupacion($fechaInicio, $fechaFin);
                        
                        if ($formato === 'pdf') {
                            // Se puede crear un método específico para ocupación si se necesita
                            $rutaPDF = $pdfClass->generarReporteVentas($fechaInicio, $fechaFin, ['ocupacion' => $datos]);
                            $mensaje = 'Reporte generado correctamente';
                            $mensajeTipo = 'success';
                            $_SESSION['ultimo_reporte'] = basename($rutaPDF);
                        } else {
                            $rutaCSV = $reporteClass->exportarCSV(
                                $datos,
                                'reporte_ocupacion_' . date('Y-m-d'),
                                ['Paquete', 'Fecha', 'Hora', 'Capacidad Total', 'Reservados', 'Disponibles', 'Ocupación %']
                            );
                            $mensaje = 'Reporte generado correctamente';
                            $mensajeTipo = 'success';
                            $_SESSION['ultimo_reporte'] = basename($rutaCSV);
                        }
                        break;
                        
                    case 'clientes':
                        $datos = $reporteClass->reporteClientes($fechaInicio, $fechaFin);
                        $rutaCSV = $reporteClass->exportarCSV(
                            $datos,
                            'reporte_clientes_' . date('Y-m-d'),
                            ['Cliente', 'Email', 'Teléfono', 'Total Reservaciones', 'Total Gastado', 'Última Visita']
                        );
                        $mensaje = 'Reporte generado correctamente';
                        $mensajeTipo = 'success';
                        $_SESSION['ultimo_reporte'] = basename($rutaCSV);
                        break;
                        
                    case 'cancelaciones':
                        $datos = $reporteClass->reporteCancelaciones($fechaInicio, $fechaFin);
                        $rutaCSV = $reporteClass->exportarCSV(
                            $datos,
                            'reporte_cancelaciones_' . date('Y-m-d'),
                            ['Código', 'Cliente', 'Paquete', 'Fecha Tour', 'Monto', 'Fecha Cancelación', 'Motivo']
                        );
                        $mensaje = 'Reporte generado correctamente';
                        $mensajeTipo = 'success';
                        $_SESSION['ultimo_reporte'] = basename($rutaCSV);
                        break;
                        
                    default:
                        $mensaje = 'Tipo de reporte no válido';
                        $mensajeTipo = 'danger';
                }
                
            } catch (Exception $e) {
                $mensaje = 'Error al generar el reporte: ' . $e->getMessage();
                $mensajeTipo = 'danger';
            }
        }
    }
}

// Generar nuevo CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$pageTitle = 'Generación de Reportes';
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
        body.admin-reportes-page {
            background-color: #f8f9fa;
        }
        .reporte-card {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .reporte-card:hover {
            border-color: #0066cc;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-5px);
        }
        .reporte-card.selected {
            border-color: #0066cc;
            background-color: #e7f3ff;
        }
        .reporte-icon {
            font-size: 3rem;
            color: #0066cc;
            margin-bottom: 15px;
        }
    </style>
</head>
<body class="admin-reportes-page">
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        Generación de Reportes
                    </h1>
                </div>
                
                <?php if (!empty($mensaje)): ?>
                    <div class="alert alert-<?php echo $mensajeTipo; ?> alert-dismissible fade show">
                        <?php echo htmlspecialchars($mensaje); ?>
                        <?php if ($mensajeTipo === 'success' && isset($_SESSION['ultimo_reporte'])): ?>
                            <br>
                            <a href="<?php echo UPLOADS_URL; ?>/reportes/<?php echo $_SESSION['ultimo_reporte']; ?>" 
                               class="btn btn-sm btn-primary mt-2"
                               target="_blank">
                                <i class="fas fa-download"></i> Descargar Reporte
                            </a>
                            <?php unset($_SESSION['ultimo_reporte']); ?>
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="formReporte">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="tipo_reporte" id="tipo_reporte_input" value="">
                    
                    <!-- Selección de Tipo de Reporte -->
                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Seleccionar Tipo de Reporte</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="reporte-card text-center" onclick="seleccionarReporte('ventas')">
                                        <i class="fas fa-dollar-sign reporte-icon"></i>
                                        <h5>Reporte de Ventas</h5>
                                        <p class="text-muted">Ingresos, reservaciones y estadísticas de ventas</p>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="reporte-card text-center" onclick="seleccionarReporte('guias')">
                                        <i class="fas fa-user-tie reporte-icon"></i>
                                        <h5>Reporte de Guías</h5>
                                        <p class="text-muted">Tours realizados y desempeño de guías</p>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="reporte-card text-center" onclick="seleccionarReporte('ocupacion')">
                                        <i class="fas fa-chart-pie reporte-icon"></i>
                                        <h5>Reporte de Ocupación</h5>
                                        <p class="text-muted">Capacidad y ocupación por paquete</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="reporte-card text-center" onclick="seleccionarReporte('clientes')">
                                        <i class="fas fa-users reporte-icon"></i>
                                        <h5>Reporte de Clientes</h5>
                                        <p class="text-muted">Clientes frecuentes y gastos (Solo CSV)</p>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="reporte-card text-center" onclick="seleccionarReporte('cancelaciones')">
                                        <i class="fas fa-times-circle reporte-icon"></i>
                                        <h5>Reporte de Cancelaciones</h5>
                                        <p class="text-muted">Reservaciones canceladas y motivos (Solo CSV)</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Parámetros del Reporte -->
                    <div class="card shadow mb-4" id="parametros" style="display: none;">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">Parámetros del Reporte</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <label for="fecha_inicio" class="form-label">
                                        Fecha Inicio <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="fecha_inicio" 
                                           name="fecha_inicio"
                                           value="<?php echo date('Y-m-01'); ?>"
                                           required>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="fecha_fin" class="form-label">
                                        Fecha Fin <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="fecha_fin" 
                                           name="fecha_fin"
                                           value="<?php echo date('Y-m-d'); ?>"
                                           required>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="formato" class="form-label">
                                        Formato <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="formato" name="formato" required>
                                        <option value="pdf">PDF</option>
                                        <option value="csv">CSV (Excel)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-file-download"></i> Generar Reporte
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="cancelarSeleccion()">
                                    <i class="fas fa-times"></i> Cancelar
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- Ayuda -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Ayuda</h5>
                    </div>
                    <div class="card-body">
                        <h6>Tipos de Reportes Disponibles:</h6>
                        <ul>
                            <li><strong>Reporte de Ventas:</strong> Muestra ingresos totales, ventas por paquete, ventas por día y tendencias.</li>
                            <li><strong>Reporte de Guías:</strong> Lista tours realizados por cada guía, personas atendidas y días trabajados.</li>
                            <li><strong>Reporte de Ocupación:</strong> Muestra el porcentaje de ocupación por paquete, fecha y horario.</li>
                            <li><strong>Reporte de Clientes:</strong> Lista clientes con total de reservaciones y gasto total (solo CSV).</li>
                            <li><strong>Reporte de Cancelaciones:</strong> Lista todas las reservaciones canceladas con motivos (solo CSV).</li>
                        </ul>
                        
                        <h6 class="mt-3">Formatos:</h6>
                        <ul>
                            <li><strong>PDF:</strong> Documento con formato profesional, ideal para impresión.</li>
                            <li><strong>CSV:</strong> Archivo compatible con Excel, ideal para análisis de datos.</li>
                        </ul>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include __DIR__ . '/includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function seleccionarReporte(tipo) {
        // Remover selección anterior
        document.querySelectorAll('.reporte-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Seleccionar nuevo
        event.currentTarget.classList.add('selected');
        
        // Actualizar input hidden
        document.getElementById('tipo_reporte_input').value = tipo;
        
        // Mostrar parámetros
        document.getElementById('parametros').style.display = 'block';
        
        // Ajustar formato según tipo
        const formatoSelect = document.getElementById('formato');
        if (tipo === 'clientes' || tipo === 'cancelaciones') {
            formatoSelect.value = 'csv';
            formatoSelect.disabled = true;
        } else {
            formatoSelect.disabled = false;
        }
        
        // Scroll suave a parámetros
        document.getElementById('parametros').scrollIntoView({ behavior: 'smooth' });
    }
    
    function cancelarSeleccion() {
        document.querySelectorAll('.reporte-card').forEach(card => {
            card.classList.remove('selected');
        });
        document.getElementById('tipo_reporte_input').value = '';
        document.getElementById('parametros').style.display = 'none';
    }
    
    // Validación del formulario
    document.getElementById('formReporte').addEventListener('submit', function(e) {
        const tipoReporte = document.getElementById('tipo_reporte_input').value;
        
        if (!tipoReporte) {
            e.preventDefault();
            alert('Por favor seleccione un tipo de reporte');
            return false;
        }
        
        const fechaInicio = new Date(document.getElementById('fecha_inicio').value);
        const fechaFin = new Date(document.getElementById('fecha_fin').value);
        
        if (fechaInicio > fechaFin) {
            e.preventDefault();
            alert('La fecha de inicio no puede ser mayor a la fecha fin');
            return false;
        }
    });
    </script>
</body>
</html>
