<?php

class Calendario {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtener disponibilidad de un mes específico
     */
    public function obtenerMes($year, $month, $idPaquete = null) {
        $primerDia = sprintf('%04d-%02d-01', (int)$year, (int)$month);
        $ultimoDia = date('Y-m-t', strtotime($primerDia));
        
        // Obtener días/horarios desactivados y agruparlos por fecha
        $sql = "SELECT fecha, hora_inicio, hora_fin, motivo
                FROM disponibilidad_calendario
                WHERE fecha BETWEEN ? AND ?
                AND activo = 0";
        
        $params = [$primerDia, $ultimoDia];
        
        if ($idPaquete) {
            $sql .= " AND id_paquete = ?";
            $params[] = $idPaquete;
        }
        
        $diasDesactivados = [];
        foreach ($this->db->fetchAll($sql, $params) as $registro) {
            $fecha = $registro['fecha'];
            if (!isset($diasDesactivados[$fecha])) {
                $diasDesactivados[$fecha] = [];
            }
            $diasDesactivados[$fecha][] = $registro;
        }
        
        // Obtener reservaciones del mes y agruparlas por día
        $reservasQuery = $this->db->fetchAll(
            "SELECT r.fecha_tour, r.hora_inicio, r.id_paquete, 
                    SUM(r.numero_personas) AS total_personas,
                    p.capacidad_maxima
             FROM reservaciones r
             INNER JOIN paquetes p ON r.id_paquete = p.id_paquete
             WHERE r.fecha_tour BETWEEN ? AND ?
             AND r.estado IN ('confirmada', 'pagada', 'pendiente')
             " . ($idPaquete ? "AND r.id_paquete = ?" : "") . "
             GROUP BY r.fecha_tour, r.hora_inicio, r.id_paquete, p.capacidad_maxima",
            $idPaquete ? [$primerDia, $ultimoDia, $idPaquete] : [$primerDia, $ultimoDia]
        );
        
        $reservacionesPorDia = [];
        foreach ($reservasQuery as $reserva) {
            $fecha = $reserva['fecha_tour'];
            if (!isset($reservacionesPorDia[$fecha])) {
                $reservacionesPorDia[$fecha] = [];
            }
            $reservacionesPorDia[$fecha][] = $reserva;
        }
        
        return [
            'dias_desactivados' => $diasDesactivados,
            'reservaciones' => $reservacionesPorDia
        ];
    }
    
    /**
     * Desactivar un día completo
     */
    public function desactivarDia($idPaquete, $fecha, $motivo = null) {
        // Verificar si hay reservas ese día
        $reservas = $this->db->fetchOne(
            "SELECT COUNT(*) as total
             FROM reservaciones
             WHERE id_paquete = ?
             AND fecha_tour = ?
             AND estado IN ('confirmada', 'pagada', 'pendiente')",
            [$idPaquete, $fecha]
        );
        
        if ($reservas['total'] > 0) {
            return [
                'success' => false,
                'tiene_reservas' => true,
                'total_reservas' => $reservas['total'],
                'message' => "Hay {$reservas['total']} reserva(s) para este día. Debe cancelarlas primero."
            ];
        }
        
        // Desactivar el día
        $this->db->insert(
            "INSERT INTO disponibilidad_calendario 
             (id_paquete, fecha, activo, motivo, creado_por)
             VALUES (?, ?, 0, ?, ?)",
            [$idPaquete, $fecha, $motivo, $_SESSION['user_id']]
        );
        
        logActivity("Día desactivado: $fecha para paquete $idPaquete");
        
        return [
            'success' => true,
            'message' => 'Día desactivado correctamente'
        ];
    }
    
    /**
     * Desactivar un horario específico
     */
    public function desactivarHorario($idPaquete, $fecha, $horaInicio, $horaFin, $motivo = null) {
        // Verificar si hay reservas en ese horario
        $reservas = $this->db->fetchOne(
            "SELECT COUNT(*) as total
             FROM reservaciones
             WHERE id_paquete = ?
             AND fecha_tour = ?
             AND hora_inicio = ?
             AND estado IN ('confirmada', 'pagada', 'pendiente')",
            [$idPaquete, $fecha, $horaInicio]
        );
        
        if ($reservas['total'] > 0) {
            return [
                'success' => false,
                'tiene_reservas' => true,
                'total_reservas' => $reservas['total'],
                'message' => "Hay {$reservas['total']} reserva(s) para este horario. Debe cancelarlas primero."
            ];
        }
        
        // Desactivar el horario
        $this->db->insert(
            "INSERT INTO disponibilidad_calendario 
             (id_paquete, fecha, hora_inicio, hora_fin, activo, motivo, creado_por)
             VALUES (?, ?, ?, ?, 0, ?, ?)",
            [$idPaquete, $fecha, $horaInicio, $horaFin, $motivo, $_SESSION['user_id']]
        );
        
        logActivity("Horario desactivado: $fecha $horaInicio-$horaFin para paquete $idPaquete");
        
        return [
            'success' => true,
            'message' => 'Horario desactivado correctamente'
        ];
    }
    
    /**
     * Reactivar día u horario
     */
    public function reactivar($idDisponibilidad) {
        $this->db->execute(
            "UPDATE disponibilidad_calendario SET activo = 1 WHERE id_disponibilidad = ?",
            [$idDisponibilidad]
        );
        
        logActivity("Disponibilidad reactivada: ID $idDisponibilidad");
        
        return [
            'success' => true,
            'message' => 'Reactivado correctamente'
        ];
    }
    
    /**
     * Obtener reservaciones de un día específico
     */
    public function obtenerReservasDia($fecha, $idPaquete = null) {
        $sql = "SELECT r.*, 
                       c.nombre_completo, 
                       c.email,
                       p.nombre_paquete,
                       COUNT(DISTINCT ag.id_guia) as guias_asignados
                FROM reservaciones r
                INNER JOIN clientes c ON r.id_cliente = c.id_cliente
                INNER JOIN paquetes p ON r.id_paquete = p.id_paquete
                LEFT JOIN asignacion_guias ag ON r.id_reservacion = ag.id_reservacion
                WHERE r.fecha_tour = ?";
        
        $params = [$fecha];
        
        if ($idPaquete) {
            $sql .= " AND r.id_paquete = ?";
            $params[] = $idPaquete;
        }
        
        $sql .= " GROUP BY r.id_reservacion ORDER BY r.hora_inicio";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Verificar si una fecha/horario está disponible
     */
    public function estaDisponible($idPaquete, $fecha, $horaInicio = null) {
        $sql = "SELECT COUNT(*) as bloqueado
                FROM disponibilidad_calendario
                WHERE id_paquete = ?
                AND fecha = ?
                AND activo = 0";
        
        $params = [$idPaquete, $fecha];
        
        if ($horaInicio) {
            $sql .= " AND (hora_inicio IS NULL OR hora_inicio = ?)";
            $params[] = $horaInicio;
        }
        
        $resultado = $this->db->fetchOne($sql, $params);
        
        return $resultado['bloqueado'] == 0;
    }
    
    /**
     * Obtener notas/motivos de desactivación
     */
    public function obtenerMotivo($idPaquete, $fecha, $horaInicio = null) {
        $sql = "SELECT motivo
                FROM disponibilidad_calendario
                WHERE id_paquete = ?
                AND fecha = ?
                AND activo = 0";
        
        $params = [$idPaquete, $fecha];
        
        if ($horaInicio) {
            $sql .= " AND hora_inicio = ?";
            $params[] = $horaInicio;
        } else {
            $sql .= " AND hora_inicio IS NULL";
        }
        
        $sql .= " LIMIT 1";
        
        $resultado = $this->db->fetchOne($sql, $params);
        
        return $resultado['motivo'] ?? null;
    }
}
