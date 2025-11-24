<?php


class Email {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Enviar email de confirmación de reserva
     */
    public function enviarConfirmacion($idReservacion) {
        $reservacion = $this->obtenerDatosReservacion($idReservacion);
        
        if (!$reservacion) {
            return ['success' => false, 'message' => 'Reservación no encontrada'];
        }
        
        $asunto = "Confirmación de Reserva - " . $reservacion['codigo_reservacion'];
        
        $cuerpo = $this->generarHTMLConfirmacion($reservacion);
        
        return $this->enviar(
            $reservacion['email'],
            $asunto,
            $cuerpo,
            'confirmacion',
            $idReservacion
        );
    }
    
    /**
     * Enviar recordatorio de tour
     */
    public function enviarRecordatorio($idReservacion) {
        $reservacion = $this->obtenerDatosReservacion($idReservacion);
        
        if (!$reservacion) {
            return ['success' => false, 'message' => 'Reservación no encontrada'];
        }
        
        $asunto = "Recordatorio: Tu tour es mañana - " . $reservacion['codigo_reservacion'];
        
        $cuerpo = $this->generarHTMLRecordatorio($reservacion);
        
        return $this->enviar(
            $reservacion['email'],
            $asunto,
            $cuerpo,
            'recordatorio',
            $idReservacion
        );
    }
    
    /**
     * Enviar notificación de asignación de guías
     */
    public function enviarAsignacionGuias($idReservacion) {
        $reservacion = $this->obtenerDatosReservacion($idReservacion);
        
        if (!$reservacion) {
            return ['success' => false, 'message' => 'Reservación no encontrada'];
        }
        
        // Obtener guías asignados
        $guias = $this->db->fetchAll(
            "SELECT g.nombre_completo, GROUP_CONCAT(gi.idioma) as idiomas
             FROM asignacion_guias ag
             INNER JOIN guias g ON ag.id_guia = g.id_guia
             LEFT JOIN guia_idiomas gi ON g.id_guia = gi.id_guia
             WHERE ag.id_reservacion = ?
             GROUP BY g.id_guia",
            [$idReservacion]
        );
        
        $asunto = "Guías asignados a tu tour - " . $reservacion['codigo_reservacion'];
        
        $cuerpo = $this->generarHTMLAsignacion($reservacion, $guias);
        
        return $this->enviar(
            $reservacion['email'],
            $asunto,
            $cuerpo,
            'asignacion',
            $idReservacion
        );
    }
    
    /**
     * Enviar notificación de cancelación
     */
    public function enviarCancelacion($idReservacion, $motivo = null) {
        $reservacion = $this->obtenerDatosReservacion($idReservacion);
        
        if (!$reservacion) {
            return ['success' => false, 'message' => 'Reservación no encontrada'];
        }
        
        $asunto = "Cancelación de Reserva - " . $reservacion['codigo_reservacion'];
        
        $cuerpo = $this->generarHTMLCancelacion($reservacion, $motivo);
        
        return $this->enviar(
            $reservacion['email'],
            $asunto,
            $cuerpo,
            'cancelacion',
            $idReservacion
        );
    }
    
    /**
     * Método principal de envío
     */
    private function enviar($destinatario, $asunto, $cuerpo, $tipo, $idReservacion = null) {
        // Verificar si los emails están activos
        $config = $this->db->fetchOne(
            "SELECT valor FROM configuracion_sistema WHERE clave = 'emails_activos'"
        );
        
        if ($config && $config['valor'] == '0') {
            // Solo registrar sin enviar
            return $this->registrarEmail($destinatario, $asunto, $cuerpo, $tipo, $idReservacion, false);
        }
        
        // Configurar headers
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: ' . SMTP_FROM_NAME . ' <' . SMTP_USER . '>',
            'Reply-To: ' . SMTP_USER,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        // Enviar email
        $enviado = mail($destinatario, $asunto, $cuerpo, implode("\r\n", $headers));
        
        // Registrar en BD
        $this->registrarEmail($destinatario, $asunto, $cuerpo, $tipo, $idReservacion, $enviado);
        
        // Log
        $this->logEmail($destinatario, $asunto, $enviado);
        
        return [
            'success' => $enviado,
            'message' => $enviado ? 'Email enviado correctamente' : 'Error al enviar email'
        ];
    }
    
    /**
     * Registrar email en base de datos
     */
    private function registrarEmail($destinatario, $asunto, $cuerpo, $tipo, $idReservacion, $enviado) {
        $this->db->insert(
            "INSERT INTO notificaciones_email 
             (id_reservacion, destinatario, asunto, cuerpo, tipo, enviado, fecha_programada, fecha_enviado)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)",
            [
                $idReservacion,
                $destinatario,
                $asunto,
                $cuerpo,
                $tipo,
                $enviado ? 1 : 0,
                $enviado ? date('Y-m-d H:i:s') : null
            ]
        );
        
        return ['success' => true];
    }
    
    /**
     * Log de emails
     */
    private function logEmail($destinatario, $asunto, $enviado) {
        $logFile = BASE_PATH . '/logs/email.log';
        $timestamp = date('Y-m-d H:i:s');
        $estado = $enviado ? 'ENVIADO' : 'ERROR';
        
        $mensaje = "[$timestamp] [$estado] Para: $destinatario | Asunto: $asunto" . PHP_EOL;
        
        @file_put_contents($logFile, $mensaje, FILE_APPEND);
    }
    
    /**
     * Obtener datos completos de reservación
     */
    private function obtenerDatosReservacion($idReservacion) {
        return $this->db->fetchOne(
            "SELECT r.*, c.nombre_completo, c.email, p.nombre_paquete, p.duracion_horas
             FROM reservaciones r
             INNER JOIN clientes c ON r.id_cliente = c.id_cliente
             INNER JOIN paquetes p ON r.id_paquete = p.id_paquete
             WHERE r.id_reservacion = ?",
            [$idReservacion]
        );
    }
    
    /**
     * Generar HTML para email de confirmación
     */
    private function generarHTMLConfirmacion($reservacion) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%); color: white; padding: 30px; text-align: center; }
                .content { background: #f8f9fa; padding: 30px; }
                .info-row { margin: 15px 0; padding: 10px; background: white; border-left: 4px solid #0066cc; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                .button { display: inline-block; padding: 12px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>¡Reserva Confirmada!</h1>
                    <p>Código: <?php echo $reservacion['codigo_reservacion']; ?></p>
                </div>
                
                <div class="content">
                    <p>Hola <strong><?php echo $reservacion['nombre_completo']; ?></strong>,</p>
                    
                    <p>Tu reservación ha sido confirmada exitosamente. Aquí están los detalles:</p>
                    
                    <div class="info-row">
                        <strong>Paquete:</strong> <?php echo $reservacion['nombre_paquete']; ?>
                    </div>
                    
                    <div class="info-row">
                        <strong>Fecha:</strong> <?php echo formatearFecha($reservacion['fecha_tour'], 'd/m/Y'); ?>
                    </div>
                    
                    <div class="info-row">
                        <strong>Horario:</strong> <?php echo date('g:i a', strtotime($reservacion['hora_inicio'])); ?>
                    </div>
                    
                    <div class="info-row">
                        <strong>Personas:</strong> <?php echo $reservacion['numero_personas']; ?>
                    </div>
                    
                    <div class="info-row">
                        <strong>Total:</strong> <?php echo formatearPrecio($reservacion['total']); ?>
                    </div>
                    
                    <p style="text-align: center;">
                        <a href="<?php echo SITE_URL; ?>/pages/confirmacion.php?codigo=<?php echo $reservacion['codigo_reservacion']; ?>" class="button">
                            Ver Ticket Completo
                        </a>
                    </p>
                    
                    <p><strong>Punto de encuentro:</strong> Centro Interpretativo de Unión Zapata, Mitla, Oaxaca.</p>
                    
                    <p>Te enviaremos un recordatorio 1 día antes de tu tour.</p>
                </div>
                
                <div class="footer">
                    <p>Mitla Tours | <?php echo SITE_EMAIL; ?> | 951-123-4567</p>
                    <p>Si tienes alguna pregunta, no dudes en contactarnos.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generar HTML para recordatorio
     */
    private function generarHTMLRecordatorio($reservacion) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #ff9800; color: white; padding: 30px; text-align: center; }
                .content { background: #f8f9fa; padding: 30px; }
                .highlight { background: #fff3cd; padding: 15px; border-left: 4px solid #ff9800; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>⏰ ¡Tu tour es mañana!</h1>
                </div>
                
                <div class="content">
                    <p>Hola <strong><?php echo $reservacion['nombre_completo']; ?></strong>,</p>
                    
                    <p>Te recordamos que mañana tienes programado tu tour:</p>
                    
                    <div class="highlight">
                        <p><strong><?php echo $reservacion['nombre_paquete']; ?></strong></p>
                        <p>📅 Fecha: <?php echo formatearFecha($reservacion['fecha_tour'], 'd/m/Y'); ?></p>
                        <p>🕐 Hora: <?php echo date('g:i a', strtotime($reservacion['hora_inicio'])); ?></p>
                        <p>👥 Personas: <?php echo $reservacion['numero_personas']; ?></p>
                    </div>
                    
                    <p><strong>Recomendaciones:</strong></p>
                    <ul>
                        <li>Llega 15 minutos antes</li>
                        <li>Lleva ropa y calzado cómodo</li>
                        <li>Trae agua y bloqueador solar</li>
                    </ul>
                    
                    <p>Código de reservación: <strong><?php echo $reservacion['codigo_reservacion']; ?></strong></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generar HTML para asignación de guías
     */
    private function generarHTMLAsignacion($reservacion, $guias) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 30px; text-align: center; }
                .content { background: #f8f9fa; padding: 30px; }
                .guia { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #28a745; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>✓ Guías Asignados</h1>
                </div>
                
                <div class="content">
                    <p>Hola <strong><?php echo $reservacion['nombre_completo']; ?></strong>,</p>
                    
                    <p>Nos complace informarte que ya hemos asignado los guías para tu tour:</p>
                    
                    <?php foreach ($guias as $guia): ?>
                    <div class="guia">
                        <p><strong><?php echo $guia['nombre_completo']; ?></strong></p>
                        <p>Idiomas: <?php echo $guia['idiomas']; ?></p>
                    </div>
                    <?php endforeach; ?>
                    
                    <p>Fecha del tour: <?php echo formatearFecha($reservacion['fecha_tour'], 'd/m/Y'); ?></p>
                    <p>Horario: <?php echo date('g:i a', strtotime($reservacion['hora_inicio'])); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generar HTML para cancelación
     */
    private function generarHTMLCancelacion($reservacion, $motivo) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #dc3545; color: white; padding: 30px; text-align: center; }
                .content { background: #f8f9fa; padding: 30px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Reserva Cancelada</h1>
                </div>
                
                <div class="content">
                    <p>Hola <strong><?php echo $reservacion['nombre_completo']; ?></strong>,</p>
                    
                    <p>Tu reservación <strong><?php echo $reservacion['codigo_reservacion']; ?></strong> ha sido cancelada.</p>
                    
                    <?php if ($motivo): ?>
                    <p><strong>Motivo:</strong> <?php echo htmlspecialchars($motivo); ?></p>
                    <?php endif; ?>
                    
                    <p>Si tienes alguna duda, por favor contáctanos.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Programar recordatorios automáticos
     */
    public function programarRecordatorios() {
        $diasAnticipacion = DIAS_ANTICIPACION_RECORDATORIO;
        $fechaRecordatorio = date('Y-m-d', strtotime("+$diasAnticipacion days"));
        
        // Obtener reservas que necesitan recordatorio
        $reservaciones = $this->db->fetchAll(
            "SELECT id_reservacion
             FROM reservaciones
             WHERE fecha_tour = ?
             AND estado IN ('confirmada', 'pagada')
             AND recordatorio_enviado = 0",
            [$fechaRecordatorio]
        );
        
        $enviados = 0;
        foreach ($reservaciones as $reservacion) {
            $resultado = $this->enviarRecordatorio($reservacion['id_reservacion']);
            
            if ($resultado['success']) {
                $this->db->execute(
                    "UPDATE reservaciones SET recordatorio_enviado = 1 WHERE id_reservacion = ?",
                    [$reservacion['id_reservacion']]
                );
                $enviados++;
            }
        }
        
        return [
            'success' => true,
            'enviados' => $enviados,
            'total' => count($reservaciones)
        ];
    }
}