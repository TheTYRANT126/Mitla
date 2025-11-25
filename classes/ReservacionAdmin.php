<?php

class ReservacionAdmin {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtener todas las reservaciones con filtros
     */
    public function obtenerTodas($filtros = []) {
        $sql = "SELECT r.*,
                       c.nombre_completo AS cliente_nombre,
                       c.email,
                       p.nombre_paquete AS paquete_nombre,
                       r.fecha_tour AS fecha_reservacion,
                       r.total AS monto_total,
                       COUNT(DISTINCT ag.id_guia) as guias_asignados
                FROM reservaciones r
                INNER JOIN clientes c ON r.id_cliente = c.id_cliente
                INNER JOIN paquetes p ON r.id_paquete = p.id_paquete
                LEFT JOIN asignacion_guias ag ON r.id_reservacion = ag.id_reservacion
                WHERE 1=1";
        
        $params = [];
        
        // Aplicar filtros
        if (!empty($filtros['fecha_desde'])) {
            $sql .= " AND r.fecha_tour >= ?";
            $params[] = $filtros['fecha_desde'];
        }
        
        if (!empty($filtros['fecha_hasta'])) {
            $sql .= " AND r.fecha_tour <= ?";
            $params[] = $filtros['fecha_hasta'];
        }
        
        if (!empty($filtros['estado'])) {
            $sql .= " AND r.estado = ?";
            $params[] = $filtros['estado'];
        }
        
        if (!empty($filtros['id_paquete'])) {
            $sql .= " AND r.id_paquete = ?";
            $params[] = $filtros['id_paquete'];
        }
        
        $sql .= " GROUP BY r.id_reservacion ORDER BY r.fecha_tour DESC, r.hora_inicio DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Obtener reservaciones del día
     */
    public function obtenerDelDia($fecha = null) {
        if (!$fecha) {
            $fecha = date('Y-m-d');
        }
        
        return $this->obtenerTodas(['fecha_desde' => $fecha, 'fecha_hasta' => $fecha]);
    }
    
    /**
     * Obtener estadísticas del dashboard
     */
    public function obtenerEstadisticas() {
        $hoy = date('Y-m-d');
        
        // Reservas de hoy
        $reservasHoy = $this->db->fetchOne(
            "SELECT COUNT(*) as total,
                    SUM(numero_personas) as total_personas
             FROM reservaciones 
             WHERE fecha_tour = ? AND estado IN ('confirmada', 'pagada')",
            [$hoy]
        );
        
        // Ingresos totales
        $ingresos = $this->db->fetchOne(
            "SELECT SUM(total) as total_ingresos,
                    COUNT(*) as total_reservas
             FROM reservaciones 
             WHERE estado = 'pagada'"
        );
        
        // Próximas reservas (7 días)
        $proximasReservas = $this->db->fetchOne(
            "SELECT COUNT(*) as total
             FROM reservaciones 
             WHERE fecha_tour BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY)
             AND estado IN ('confirmada', 'pagada')",
            [$hoy, $hoy]
        );
        
        // Ocupación por paquete hoy
        $ocupacionHoy = $this->db->fetchAll(
            "SELECT p.nombre_paquete,
                    p.capacidad_maxima,
                    r.hora_inicio,
                    SUM(r.numero_personas) as ocupados
             FROM reservaciones r
             INNER JOIN paquetes p ON r.id_paquete = p.id_paquete
             WHERE r.fecha_tour = ?
             AND r.estado IN ('confirmada', 'pagada')
             GROUP BY r.id_paquete, r.hora_inicio",
            [$hoy]
        );
        
        return [
            'reservas_hoy' => $reservasHoy,
            'ingresos' => $ingresos,
            'proximas_reservas' => $proximasReservas['total'] ?? 0,
            'ocupacion_hoy' => $ocupacionHoy
        ];
    }
    
    /**
     * Asignar guías a una reservación
     */
    public function asignarGuias($idReservacion, $idsGuias, $usuarioId = null) {
        try {
            $this->db->beginTransaction();
            
            $reservaActual = $this->db->fetchOne(
                "SELECT estado FROM reservaciones WHERE id_reservacion = ?",
                [$idReservacion]
            );
            $estadoAnterior = $reservaActual['estado'] ?? null;

            // Eliminar asignaciones anteriores
            $this->db->execute(
                "DELETE FROM asignacion_guias WHERE id_reservacion = ?",
                [$idReservacion]
            );
            
            // Insertar nuevas asignaciones
            foreach ($idsGuias as $idGuia) {
                $this->db->insert(
                    "INSERT INTO asignacion_guias (id_reservacion, id_guia, fecha_asignacion)
                     VALUES (?, ?, NOW())",
                    [$idReservacion, $idGuia]
                );
            }
            
            // Actualizar estado de la reserva
            $this->db->execute(
                "UPDATE reservaciones SET estado = 'confirmada' WHERE id_reservacion = ?",
                [$idReservacion]
            );
            
            // Registrar en historial
            if (!empty($idsGuias)) {
                $placeholders = implode(',', array_fill(0, count($idsGuias), '?'));
                $nombresGuias = $this->db->fetchAll(
                    "SELECT nombre_completo FROM guias WHERE id_guia IN ($placeholders)",
                    $idsGuias
                );
                $listaGuias = array_column($nombresGuias, 'nombre_completo');
                $motivo = !empty($listaGuias)
                    ? 'Se asignaron los guías: ' . implode(', ', $listaGuias)
                    : 'Se actualizaron las asignaciones de guías.';
                
                $this->db->insert(
                    "INSERT INTO historial_estados (id_reservacion, estado_anterior, estado_nuevo, motivo, fecha_cambio, cambiado_por)
                     VALUES (?, ?, ?, ?, NOW(), ?)",
                    [
                        $idReservacion,
                        $estadoAnterior,
                        'Asignación de guías',
                        $motivo,
                        $usuarioId
                    ]
                );
            }
            
            $this->db->commit();
            
            // Enviar notificación por email
            $this->enviarNotificacionAsignacion($idReservacion);
            
            logActivity("Guías asignados a reservación ID: $idReservacion");
            
            return [
                'success' => true,
                'message' => 'Guías asignados correctamente'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            
            return [
                'success' => false,
                'message' => 'Error al asignar guías: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Cambiar estado de reservación
     */
    public function cambiarEstado($idReservacion, $nuevoEstado, $motivo = null) {
        $this->db->execute(
            "UPDATE reservaciones SET estado = ? WHERE id_reservacion = ?",
            [$nuevoEstado, $idReservacion]
        );
        
        // Registrar cambio
        $this->db->insert(
            "INSERT INTO historial_estados (id_reservacion, estado_anterior, estado_nuevo, motivo, fecha_cambio)
             SELECT ?, estado, ?, ?, NOW() FROM reservaciones WHERE id_reservacion = ?",
            [$idReservacion, $nuevoEstado, $motivo, $idReservacion]
        );
        
        logActivity("Estado de reservación $idReservacion cambiado a: $nuevoEstado");
        
        // Enviar notificación según el estado
        if ($nuevoEstado === 'cancelada') {
            $this->enviarNotificacionCancelacion($idReservacion, $motivo);
        } elseif ($nuevoEstado === 'confirmada') {
            $this->enviarNotificacionConfirmacion($idReservacion);
        }
        
        return ['success' => true];
    }
    
    /**
     * Procesar reembolso
     */
    public function procesarReembolso($idReservacion, $monto, $metodo) {
        try {
            $this->db->beginTransaction();
            
            // Registrar reembolso
            $this->db->insert(
                "INSERT INTO reembolsos (id_reservacion, monto, metodo, fecha_reembolso, procesado_por)
                 VALUES (?, ?, ?, NOW(), ?)",
                [$idReservacion, $monto, $metodo, $_SESSION['user_id']]
            );
            
            // Cambiar estado
            $this->cambiarEstado($idReservacion, 'reembolsada', 'Reembolso procesado');
            
            $this->db->commit();
            
            logActivity("Reembolso procesado para reservación ID: $idReservacion");
            
            return [
                'success' => true,
                'message' => 'Reembolso procesado correctamente'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            
            return [
                'success' => false,
                'message' => 'Error al procesar reembolso'
            ];
        }
    }
    
    /**
     * Enviar notificación de asignación de guías
     */
    private function enviarNotificacionAsignacion($idReservacion) {
        // Implementar envío de email
        // Se hará en la clase Email
    }
    
    /**
     * Enviar notificación de cancelación
     */
    private function enviarNotificacionCancelacion($idReservacion, $motivo) {
        // Implementar envío de email
    }
    
    /**
     * Enviar notificación de confirmación
     */
    private function enviarNotificacionConfirmacion($idReservacion) {
        // Implementar envío de email
    }
}
