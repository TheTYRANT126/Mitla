<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Calendario.php';
require_once __DIR__ . '/../classes/Database.php';

$auth = new Auth();

if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    redirect(SITE_URL . '/admin/login.php');
}

// Obtener mes y año actual o del parámetro
$year = intval($_GET['year'] ?? date('Y'));
$month = intval($_GET['month'] ?? date('m'));

// Validar mes y año
if ($month < 1 || $month > 12) {
    $month = date('m');
}
if ($year < 2020 || $year > 2030) {
    $year = date('Y');
}

// Obtener paquetes
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT id_paquete, nombre FROM paquetes WHERE activo = 1 ORDER BY nombre");
$paquetes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$idPaqueteSeleccionado = intval($_GET['id_paquete'] ?? ($paquetes[0]['id_paquete'] ?? 0));

$calendarioClass = new Calendario();
$datosCalendario = [];

if ($idPaqueteSeleccionado > 0) {
    $datosCalendario = $calendarioClass->obtenerMes($year, $month, $idPaqueteSeleccionado);
}

// Nombres de meses
$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$pageTitle = 'Calendario de Disponibilidad';
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
        .calendar-day {
            min-height: 100px;
            border: 1px solid #dee2e6;
            padding: 10px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .calendar-day:hover {
            background-color: #f8f9fa;
            transform: scale(1.02);
        }
        .calendar-day.other-month {
            background-color: #f8f9fa;
            opacity: 0.5;
        }
        .calendar-day.desactivado {
            background-color: #ffe6e6;
            border-color: #dc3545;
        }
        .calendar-day.con-reservas {
            background-color: #e6f3ff;
        }
        .calendar-day.hoy {
            border: 2px solid #0066cc;
            font-weight: bold;
        }
        .day-number {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .day-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 0.7rem;
        }
        .reservas-count {
            font-size: 0.8rem;
            color: #0066cc;
        }
        .calendar-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .legend-item {
            display: inline-block;
            margin-right: 20px;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-calendar-alt"></i> Calendario de Disponibilidad
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
                
                <!-- Controles del Calendario -->
                <div class="calendar-header">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <label for="paquete_select" class="form-label">Seleccionar Paquete:</label>
                            <select class="form-select" id="paquete_select" onchange="cambiarPaquete()">
                                <?php foreach ($paquetes as $paquete): ?>
                                <option value="<?php echo $paquete['id_paquete']; ?>"
                                        <?php echo $paquete['id_paquete'] == $idPaqueteSeleccionado ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($paquete['nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4 text-center">
                            <h3 class="mb-0">
                                <?php echo $meses[$month] . ' ' . $year; ?>
                            </h3>
                        </div>
                        
                        <div class="col-md-4 text-end">
                            <div class="btn-group">
                                <a href="?id_paquete=<?php echo $idPaqueteSeleccionado; ?>&year=<?php echo $month == 1 ? $year - 1 : $year; ?>&month=<?php echo $month == 1 ? 12 : $month - 1; ?>" 
                                   class="btn btn-light">
                                    <i class="fas fa-chevron-left"></i> Anterior
                                </a>
                                <a href="?id_paquete=<?php echo $idPaqueteSeleccionado; ?>&year=<?php echo date('Y'); ?>&month=<?php echo date('m'); ?>" 
                                   class="btn btn-light">
                                    Hoy
                                </a>
                                <a href="?id_paquete=<?php echo $idPaqueteSeleccionado; ?>&year=<?php echo $month == 12 ? $year + 1 : $year; ?>&month=<?php echo $month == 12 ? 1 : $month + 1; ?>" 
                                   class="btn btn-light">
                                    Siguiente <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Leyenda -->
                <div class="mb-3">
                    <span class="legend-item" style="background-color: #e6f3ff; border: 1px solid #0066cc;">
                        <i class="fas fa-calendar-check"></i> Con Reservas
                    </span>
                    <span class="legend-item" style="background-color: #ffe6e6; border: 1px solid #dc3545;">
                        <i class="fas fa-ban"></i> Desactivado
                    </span>
                    <span class="legend-item" style="background-color: white; border: 2px solid #0066cc;">
                        <i class="fas fa-star"></i> Hoy
                    </span>
                </div>
                
                <!-- Calendario -->
                <div class="card shadow">
                    <div class="card-body p-0">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="text-center">Domingo</th>
                                    <th class="text-center">Lunes</th>
                                    <th class="text-center">Martes</th>
                                    <th class="text-center">Miércoles</th>
                                    <th class="text-center">Jueves</th>
                                    <th class="text-center">Viernes</th>
                                    <th class="text-center">Sábado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Crear el calendario
                                $primerDia = mktime(0, 0, 0, $month, 1, $year);
                                $diasEnMes = date('t', $primerDia);
                                $diaSemana = date('w', $primerDia);
                                
                                $hoy = date('Y-m-d');
                                $fechaActual = date('Y-m-d', $primerDia);
                                
                                // Días del mes anterior para completar la primera semana
                                $mesAnterior = $month == 1 ? 12 : $month - 1;
                                $yearAnterior = $month == 1 ? $year - 1 : $year;
                                $diasMesAnterior = date('t', mktime(0, 0, 0, $mesAnterior, 1, $yearAnterior));
                                
                                $dia = 1;
                                $diaSiguienteMes = 1;
                                
                                for ($semana = 0; $semana < 6; $semana++) {
                                    if ($dia > $diasEnMes && $diaSiguienteMes > 1) break;
                                    
                                    echo '<tr>';
                                    
                                    for ($diaSemanaNum = 0; $diaSemanaNum < 7; $diaSemanaNum++) {
                                        if ($semana == 0 && $diaSemanaNum < $diaSemana) {
                                            // Días del mes anterior
                                            $diaAnterior = $diasMesAnterior - ($diaSemana - $diaSemanaNum - 1);
                                            echo '<td class="calendar-day other-month">';
                                            echo '<div class="day-number text-muted">' . $diaAnterior . '</div>';
                                            echo '</td>';
                                        } elseif ($dia <= $diasEnMes) {
                                            // Días del mes actual
                                            $fechaDia = sprintf('%04d-%02d-%02d', $year, $month, $dia);
                                            
                                            $clases = ['calendar-day'];
                                            if ($fechaDia == $hoy) {
                                                $clases[] = 'hoy';
                                            }
                                            
                                            $desactivado = isset($datosCalendario['dias_desactivados'][$fechaDia]);
                                            $reservas = $datosCalendario['reservaciones'][$fechaDia] ?? [];
                                            
                                            if ($desactivado) {
                                                $clases[] = 'desactivado';
                                            } elseif (!empty($reservas)) {
                                                $clases[] = 'con-reservas';
                                            }
                                            
                                            echo '<td class="' . implode(' ', $clases) . '" onclick="verDia(\'' . $fechaDia . '\')">';
                                            echo '<div class="day-number">' . $dia . '</div>';
                                            
                                            if ($desactivado) {
                                                echo '<span class="badge bg-danger day-badge">Cerrado</span>';
                                            }
                                            
                                            if (!empty($reservas)) {
                                                echo '<div class="reservas-count">';
                                                echo '<i class="fas fa-users"></i> ' . count($reservas) . ' reserva(s)';
                                                echo '</div>';
                                            }
                                            
                                            echo '</td>';
                                            $dia++;
                                        } else {
                                            // Días del mes siguiente
                                            echo '<td class="calendar-day other-month">';
                                            echo '<div class="day-number text-muted">' . $diaSiguienteMes . '</div>';
                                            echo '</td>';
                                            $diaSiguienteMes++;
                                        }
                                    }
                                    
                                    echo '</tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal para Ver/Editar Día -->
    <div class="modal fade" id="modalDia" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDiaTitle">Día</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalDiaBody">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include __DIR__ . '/includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    const idPaquete = <?php echo $idPaqueteSeleccionado; ?>;
    
    function cambiarPaquete() {
        const select = document.getElementById('paquete_select');
        const idPaquete = select.value;
        window.location.href = `?id_paquete=${idPaquete}&year=<?php echo $year; ?>&month=<?php echo $month; ?>`;
    }
    
    function verDia(fecha) {
        const modal = new bootstrap.Modal(document.getElementById('modalDia'));
        const modalBody = document.getElementById('modalDiaBody');
        const modalTitle = document.getElementById('modalDiaTitle');
        
        // Formatear fecha para mostrar
        const [year, month, day] = fecha.split('-');
        const fechaFormato = new Date(year, month - 1, day).toLocaleDateString('es-MX', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        modalTitle.textContent = fechaFormato.charAt(0).toUpperCase() + fechaFormato.slice(1);
        
        // Mostrar loading
        modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
        
        modal.show();
        
        // Cargar datos del día
        fetch(`<?php echo SITE_URL; ?>/api/admin/obtener-dia.php?fecha=${fecha}&id_paquete=${idPaquete}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarDetallesDia(data.data, fecha);
                } else {
                    modalBody.innerHTML = '<div class="alert alert-danger">Error al cargar los datos</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                modalBody.innerHTML = '<div class="alert alert-danger">Error al cargar los datos</div>';
            });
    }
    
    function mostrarDetallesDia(data, fecha) {
        const modalBody = document.getElementById('modalDiaBody');
        
        let html = '';
        
        // Mostrar si está desactivado
        if (data.desactivado) {
            html += `
                <div class="alert alert-danger">
                    <i class="fas fa-ban"></i> <strong>Este día está desactivado</strong><br>
                    <small>Motivo: ${data.motivo_desactivacion || 'No especificado'}</small>
                </div>
                <button class="btn btn-success w-100 mb-3" onclick="reactivarDia(${data.id_disponibilidad})">
                    <i class="fas fa-check"></i> Reactivar Día
                </button>
            `;
        } else {
            html += `
                <button class="btn btn-danger w-100 mb-3" onclick="desactivarDia('${fecha}')">
                    <i class="fas fa-ban"></i> Desactivar Este Día
                </button>
            `;
        }
        
        // Mostrar reservaciones
        if (data.reservaciones && data.reservaciones.length > 0) {
            html += '<h6 class="mt-3">Reservaciones:</h6>';
            html += '<div class="list-group">';
            
            data.reservaciones.forEach(reserva => {
                const badgeClass = {
                    'pendiente': 'warning',
                    'confirmada': 'info',
                    'pagada': 'success',
                    'cancelada': 'danger',
                    'completada': 'secondary'
                }[reserva.estado] || 'secondary';
                
                html += `
                    <a href="reservaciones/detalle.php?id=${reserva.id_reservacion}" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">${reserva.codigo_reservacion}</h6>
                            <small><span class="badge bg-${badgeClass}">${reserva.estado}</span></small>
                        </div>
                        <p class="mb-1"><strong>${reserva.cliente_nombre}</strong></p>
                        <small>
                            <i class="fas fa-clock"></i> ${reserva.hora_inicio} | 
                            <i class="fas fa-users"></i> ${reserva.numero_personas} personas | 
                            $${parseFloat(reserva.monto_total).toFixed(2)}
                        </small>
                    </a>
                `;
            });
            
            html += '</div>';
        } else {
            html += '<p class="text-muted text-center mt-3">No hay reservaciones para este día</p>';
        }
        
        modalBody.innerHTML = html;
    }
    
    function desactivarDia(fecha) {
        const motivo = prompt('Ingrese el motivo para desactivar este día:');
        
        if (!motivo) {
            alert('Debe proporcionar un motivo');
            return;
        }
        
        if (!confirm('¿Está seguro de desactivar este día?')) {
            return;
        }
        
        fetch('<?php echo SITE_URL; ?>/api/admin/desactivar-fecha.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id_paquete: idPaquete,
                fecha: fecha,
                motivo: motivo
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Día desactivado correctamente');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al desactivar el día');
        });
    }
    
    function reactivarDia(idDisponibilidad) {
        if (!confirm('¿Está seguro de reactivar este día?')) {
            return;
        }
        
        fetch('<?php echo SITE_URL; ?>/api/admin/reactivar-fecha.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id_disponibilidad: idDisponibilidad
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Día reactivado correctamente');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al reactivar el día');
        });
    }
    </script>
</body>
</html>