<?php

class PDF {
    
    /**
     * Verifica que exista la librería de PDF y el directorio de salida
     */
    private function preparePdfEnvironment() {
        $autoload = BASE_PATH . '/vendor/autoload.php';
        
        if (!class_exists('\\Mpdf\\Mpdf')) {
            if (!file_exists($autoload)) {
                throw new Exception('La librería para generar PDFs no está instalada. Ejecuta "composer require mpdf/mpdf".');
            }
            require_once $autoload;
        }
        
        $directorio = UPLOADS_PATH . '/reportes';
        if (!is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }
    }
    
    /**
     * Obtiene la ruta absoluta del logo para incrustarlo en los PDFs
     */
    private function getLogoPath() {
        static $logoPath = null;
        
        if ($logoPath === null) {
            $ruta = ASSETS_PATH . '/img/logo.png';
            $logoPath = file_exists($ruta) ? str_replace('\\', '/', $ruta) : '';
        }
        
        return $logoPath;
    }
    
    /**
     * Generar ticket de reservación en PDF
     */
    public function generarTicket($codigoReservacion) {
        $this->preparePdfEnvironment();
        
        $db = Database::getInstance();
        $reservacion = $db->fetchOne(
            "SELECT r.*, c.nombre_completo, c.email, p.nombre_paquete, p.duracion_horas
             FROM reservaciones r
             INNER JOIN clientes c ON r.id_cliente = c.id_cliente
             INNER JOIN paquetes p ON r.id_paquete = p.id_paquete
             WHERE r.codigo_reservacion = ?",
            [$codigoReservacion]
        );
        
        if (!$reservacion) {
            return ['success' => false, 'message' => 'Reservación no encontrada'];
        }
        
        $html = $this->generarHTMLTicket($reservacion);
        
        $mpdf = new \Mpdf\Mpdf([
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15
        ]);
        
        $mpdf->WriteHTML($html);
        
        $nombreArchivo = 'ticket_' . $codigoReservacion . '.pdf';
        $rutaArchivo = UPLOADS_PATH . '/reportes/' . $nombreArchivo;
        
        $mpdf->Output($rutaArchivo, \Mpdf\Output\Destination::FILE);
        
        return [
            'success' => true,
            'ruta' => $rutaArchivo,
            'url' => UPLOADS_URL . '/reportes/' . $nombreArchivo
        ];
    }
    
    /**
     * Generar HTML del ticket
     */
    private function generarHTMLTicket($reservacion) {
        ob_start();
        $logoPath = $this->getLogoPath();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; }
                .header { background: #0066cc; color: white; padding: 20px; text-align: center; position: relative; }
                .logo { position: absolute; top: 20px; left: 20px; }
                .logo img { height: 50px; }
                .codigo { font-size: 24px; font-weight: bold; letter-spacing: 2px; }
                .seccion { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
                .titulo { color: #0066cc; font-size: 18px; font-weight: bold; margin-bottom: 10px; }
                .info { margin: 5px 0; }
                .label { font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="header">
                <?php if ($logoPath): ?>
                    <div class="logo">
                        <img src="<?php echo $logoPath; ?>" alt="Logo">
                    </div>
                <?php endif; ?>
                <h1>MITLA TOURS</h1>
                <p class="codigo"><?php echo $reservacion['codigo_reservacion']; ?></p>
            </div>
            
            <div class="seccion">
                <div class="titulo">Información del Tour</div>
                <div class="info"><span class="label">Paquete:</span> <?php echo $reservacion['nombre_paquete']; ?></div>
                <div class="info"><span class="label">Fecha:</span> <?php echo formatearFecha($reservacion['fecha_tour'], 'd/m/Y'); ?></div>
                <div class="info"><span class="label">Horario:</span> <?php echo date('g:i a', strtotime($reservacion['hora_inicio'])); ?></div>
                <div class="info"><span class="label">Duración:</span> <?php echo $reservacion['duracion_horas']; ?> horas</div>
            </div>
            
            <div class="seccion">
                <div class="titulo">Cliente</div>
                <div class="info"><span class="label">Nombre:</span> <?php echo $reservacion['nombre_completo']; ?></div>
                <div class="info"><span class="label">Email:</span> <?php echo $reservacion['email']; ?></div>
                <div class="info"><span class="label">Personas:</span> <?php echo $reservacion['numero_personas']; ?></div>
            </div>
            
            <div class="seccion">
                <div class="titulo">Pago</div>
                <div class="info"><span class="label">Total:</span> <?php echo formatearPrecio($reservacion['total']); ?></div>
                <div class="info"><span class="label">Estado:</span> <?php echo ucfirst($reservacion['estado']); ?></div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generar reporte de ventas en PDF
     */
    public function generarReporteVentas($fechaInicio, $fechaFin, $datos) {
        $this->preparePdfEnvironment();
        
        $html = $this->generarHTMLReporteVentas($fechaInicio, $fechaFin, $datos);
        
        $mpdf = new \Mpdf\Mpdf(['format' => 'A4-L']); // Horizontal
        $mpdf->WriteHTML($html);
        
        $nombreArchivo = 'reporte_ventas_' . date('Y-m-d') . '.pdf';
        $rutaArchivo = UPLOADS_PATH . '/reportes/' . $nombreArchivo;
        
        $mpdf->Output($rutaArchivo, \Mpdf\Output\Destination::FILE);
        
        return [
            'success' => true,
            'ruta' => $rutaArchivo,
            'url' => UPLOADS_URL . '/reportes/' . $nombreArchivo
        ];
    }
    
    /**
     * Generar reporte de guías en PDF
     */
    public function generarReporteGuias($fechaInicio, $fechaFin, $datos) {
        $this->preparePdfEnvironment();
        
        $html = $this->generarHTMLReporteGuias($fechaInicio, $fechaFin, $datos);
        
        $mpdf = new \Mpdf\Mpdf(['format' => 'A4']);
        $mpdf->WriteHTML($html);
        
        $nombreArchivo = 'reporte_guias_' . date('Y-m-d') . '.pdf';
        $rutaArchivo = UPLOADS_PATH . '/reportes/' . $nombreArchivo;
        
        $mpdf->Output($rutaArchivo, \Mpdf\Output\Destination::FILE);
        
        return [
            'success' => true,
            'ruta' => $rutaArchivo,
            'url' => UPLOADS_URL . '/reportes/' . $nombreArchivo
        ];
    }
    
    /**
     * Generar reporte de ocupación en PDF
     */
    public function generarReporteOcupacion($fechaInicio, $fechaFin, $datos) {
        $this->preparePdfEnvironment();
        
        $html = $this->generarHTMLReporteOcupacion($fechaInicio, $fechaFin, $datos);
        
        $mpdf = new \Mpdf\Mpdf(['format' => 'A4-L']);
        $mpdf->WriteHTML($html);
        
        $nombreArchivo = 'reporte_ocupacion_' . date('Y-m-d') . '.pdf';
        $rutaArchivo = UPLOADS_PATH . '/reportes/' . $nombreArchivo;
        
        $mpdf->Output($rutaArchivo, \Mpdf\Output\Destination::FILE);
        
        return [
            'success' => true,
            'ruta' => $rutaArchivo,
            'url' => UPLOADS_URL . '/reportes/' . $nombreArchivo
        ];
    }
    
    /**
     * HTML del reporte de ventas
     */
    private function generarHTMLReporteVentas($fechaInicio, $fechaFin, $datos) {
        ob_start();
        $logoPath = $this->getLogoPath();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; }
                .header { text-align: center; margin-bottom: 30px; position: relative; }
                .logo { position: absolute; top: 0; left: 0; }
                .logo img { height: 60px; }
                .periodo { color: #666; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th { background: #0066cc; color: white; padding: 10px; text-align: left; }
                td { padding: 8px; border-bottom: 1px solid #ddd; }
                .resumen { background: #f0f8ff; padding: 15px; margin: 20px 0; }
                .total { font-size: 16px; font-weight: bold; color: #0066cc; }
            </style>
        </head>
        <body>
            <div class="header">
                <?php if ($logoPath): ?>
                    <div class="logo">
                        <img src="<?php echo $logoPath; ?>" alt="Logo">
                    </div>
                <?php endif; ?>
                <h1>REPORTE DE VENTAS</h1>
                <div class="periodo">Del <?php echo formatearFecha($fechaInicio, 'd/m/Y'); ?> al <?php echo formatearFecha($fechaFin, 'd/m/Y'); ?></div>
            </div>
            
            <div class="resumen">
                <p><strong>Total Reservaciones:</strong> <?php echo $datos['resumen']['total_reservaciones']; ?></p>
                <p><strong>Total Personas:</strong> <?php echo $datos['resumen']['total_personas']; ?></p>
                <p class="total">Ingresos Totales: <?php echo formatearPrecio($datos['resumen']['ingresos_totales']); ?></p>
            </div>
            
            <h3>Ventas por Paquete</h3>
            <table>
                <thead>
                    <tr>
                        <th>Paquete</th>
                        <th>Reservaciones</th>
                        <th>Personas</th>
                        <th>Ingresos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($datos['por_paquete'] as $paquete): ?>
                    <tr>
                        <td><?php echo $paquete['nombre_paquete']; ?></td>
                        <td><?php echo $paquete['total_reservaciones']; ?></td>
                        <td><?php echo $paquete['total_personas']; ?></td>
                        <td><?php echo formatearPrecio($paquete['ingresos']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * HTML del reporte de guías
     */
    private function generarHTMLReporteGuias($fechaInicio, $fechaFin, $datos) {
        ob_start();
        $logoPath = $this->getLogoPath();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; }
                .header { text-align: center; margin-bottom: 30px; position: relative; }
                .logo { position: absolute; top: 0; left: 0; }
                .logo img { height: 60px; }
                .periodo { color: #666; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { padding: 8px; border: 1px solid #ddd; }
                th { background: #0066cc; color: white; }
            </style>
        </head>
        <body>
            <div class="header">
                <?php if ($logoPath): ?>
                    <div class="logo">
                        <img src="<?php echo $logoPath; ?>" alt="Logo">
                    </div>
                <?php endif; ?>
                <h1>REPORTE DE GUÍAS</h1>
                <div class="periodo">Del <?php echo formatearFecha($fechaInicio, 'd/m/Y'); ?> al <?php echo formatearFecha($fechaFin, 'd/m/Y'); ?></div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Guía</th>
                        <th>Teléfono</th>
                        <th>Idiomas</th>
                        <th>Tours Asignados</th>
                        <th>Personas Atendidas</th>
                        <th>Días Trabajados</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($datos as $guia): ?>
                    <tr>
                        <td><?php echo $guia['nombre_completo']; ?></td>
                        <td><?php echo $guia['telefono']; ?></td>
                        <td><?php echo $guia['idiomas']; ?></td>
                        <td><?php echo $guia['tours_asignados']; ?></td>
                        <td><?php echo $guia['total_personas_atendidas']; ?></td>
                        <td><?php echo $guia['dias_trabajados']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * HTML del reporte de ocupación
     */
    private function generarHTMLReporteOcupacion($fechaInicio, $fechaFin, $datos) {
        ob_start();
        $logoPath = $this->getLogoPath();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; }
                .header { text-align: center; margin-bottom: 30px; position: relative; }
                .logo { position: absolute; top: 0; left: 0; }
                .logo img { height: 60px; }
                .periodo { color: #666; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { padding: 8px; border: 1px solid #ddd; }
                th { background: #28a745; color: white; }
            </style>
        </head>
        <body>
            <div class="header">
                <?php if ($logoPath): ?>
                    <div class="logo">
                        <img src="<?php echo $logoPath; ?>" alt="Logo">
                    </div>
                <?php endif; ?>
                <h1>REPORTE DE OCUPACIÓN</h1>
                <div class="periodo">Del <?php echo formatearFecha($fechaInicio, 'd/m/Y'); ?> al <?php echo formatearFecha($fechaFin, 'd/m/Y'); ?></div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Paquete</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Capacidad</th>
                        <th>Reservados</th>
                        <th>Disponibles</th>
                        <th>% Ocupación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($datos as $fila): ?>
                    <tr>
                        <td><?php echo $fila['nombre_paquete']; ?></td>
                        <td><?php echo formatearFecha($fila['fecha_tour'], 'd/m/Y'); ?></td>
                        <td><?php echo date('H:i', strtotime($fila['hora_inicio'])); ?></td>
                        <td><?php echo $fila['capacidad_maxima']; ?></td>
                        <td><?php echo $fila['ocupados']; ?></td>
                        <td><?php echo $fila['disponibles']; ?></td>
                        <td><?php echo $fila['porcentaje_ocupacion']; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
