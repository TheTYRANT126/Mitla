<?php

class Reporte {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Generar reporte de ventas
     */
    public function reporteVentas($fechaInicio, $fechaFin, $formato = 'array') {
        $datos = [
            'periodo' => [
                'inicio' => $fechaInicio,
                'fin' => $fechaFin
            ],
            'resumen' => $this->obtenerResumenVentas($fechaInicio, $fechaFin),
            'por_paquete' => $this->obtenerVentasPorPaquete($fechaInicio, $fechaFin),
            'por_dia' => $this->obtenerVentasPorDia($fechaInicio, $fechaFin),
            'por_mes' => $this->obtenerVentasPorMes($fechaInicio, $fechaFin),
            'tendencias' => $this->obtenerTendencias($fechaInicio, $fechaFin)
        ];
        
        if ($formato === 'pdf') {
            return $this->generarPDFVentas($datos);
        }
        
        return $datos;
    }
    
    /**
     * Resumen general de ventas
     */
    private function obtenerResumenVentas($fechaInicio, $fechaFin) {
        return $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_reservaciones,
                SUM(numero_personas) as total_personas,
                SUM(total) as ingresos_totales,
                AVG(total) as ticket_promedio,
                COUNT(DISTINCT id_cliente) as clientes_unicos,
                SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) as cancelaciones,
                SUM(CASE WHEN estado = 'cancelada' THEN total ELSE 0 END) as monto_cancelado
             FROM reservaciones
             WHERE fecha_tour BETWEEN ? AND ?",
            [$fechaInicio, $fechaFin]
        );
    }
    
    /**
     * Ventas por paquete
     */
    private function obtenerVentasPorPaquete($fechaInicio, $fechaFin) {
        return $this->db->fetchAll(
            "SELECT 
                p.nombre_paquete,
                COUNT(r.id_reservacion) as total_reservaciones,
                SUM(r.numero_personas) as total_personas,
                SUM(r.total) as ingresos,
                AVG(r.total) as ticket_promedio,
                ROUND(AVG((r.numero_personas / p.capacidad_maxima) * 100), 2) as ocupacion_promedio
             FROM reservaciones r
             INNER JOIN paquetes p ON r.id_paquete = p.id_paquete
             WHERE r.fecha_tour BETWEEN ? AND ?
             AND r.estado IN ('confirmada', 'pagada')
             GROUP BY p.id_paquete
             ORDER BY ingresos DESC",
            [$fechaInicio, $fechaFin]
        );
    }
    
    /**
     * Ventas por día
     */
    private function obtenerVentasPorDia($fechaInicio, $fechaFin) {
        return $this->db->fetchAll(
            "SELECT 
                fecha_tour,
                COUNT(*) as reservaciones,
                SUM(numero_personas) as personas,
                SUM(total) as ingresos
             FROM reservaciones
             WHERE fecha_tour BETWEEN ? AND ?
             AND estado IN ('confirmada', 'pagada')
             GROUP BY fecha_tour
             ORDER BY fecha_tour",
            [$fechaInicio, $fechaFin]
        );
    }
    
    /**
     * Ventas por mes
     */
    private function obtenerVentasPorMes($fechaInicio, $fechaFin) {
        return $this->db->fetchAll(
            "SELECT 
                DATE_FORMAT(fecha_tour, '%Y-%m') as mes,
                COUNT(*) as reservaciones,
                SUM(numero_personas) as personas,
                SUM(total) as ingresos
             FROM reservaciones
             WHERE fecha_tour BETWEEN ? AND ?
             AND estado IN ('confirmada', 'pagada')
             GROUP BY mes
             ORDER BY mes",
            [$fechaInicio, $fechaFin]
        );
    }
    
    /**
     * Análisis de tendencias
     */
    private function obtenerTendencias($fechaInicio, $fechaFin) {
        // Comparar con periodo anterior
        $dias = (strtotime($fechaFin) - strtotime($fechaInicio)) / 86400;
        $fechaInicioAnterior = date('Y-m-d', strtotime($fechaInicio . " -$dias days"));
        $fechaFinAnterior = date('Y-m-d', strtotime($fechaInicio . " -1 day"));
        
        $actual = $this->obtenerResumenVentas($fechaInicio, $fechaFin);
        $anterior = $this->obtenerResumenVentas($fechaInicioAnterior, $fechaFinAnterior);
        
        return [
            'ingresos_variacion' => $this->calcularVariacion(
                $actual['ingresos_totales'], 
                $anterior['ingresos_totales']
            ),
            'reservaciones_variacion' => $this->calcularVariacion(
                $actual['total_reservaciones'], 
                $anterior['total_reservaciones']
            ),
            'personas_variacion' => $this->calcularVariacion(
                $actual['total_personas'], 
                $anterior['total_personas']
            )
        ];
    }
    
    /**
     * Calcular variación porcentual
     */
    private function calcularVariacion($actual, $anterior) {
        if ($anterior == 0) return 100;
        return round((($actual - $anterior) / $anterior) * 100, 2);
    }
    
    /**
     * Reporte de asistencia de guías
     */
    public function reporteGuias($fechaInicio, $fechaFin) {
        return $this->db->fetchAll(
            "SELECT 
                g.nombre_completo,
                g.telefono,
                GROUP_CONCAT(DISTINCT gi.idioma) as idiomas,
                COUNT(DISTINCT ag.id_reservacion) as tours_asignados,
                SUM(r.numero_personas) as total_personas_atendidas,
                COUNT(DISTINCT DATE(r.fecha_tour)) as dias_trabajados
             FROM guias g
             LEFT JOIN asignacion_guias ag ON g.id_guia = ag.id_guia
             LEFT JOIN reservaciones r ON ag.id_reservacion = r.id_reservacion
                AND r.fecha_tour BETWEEN ? AND ?
                AND r.estado IN ('confirmada', 'pagada', 'completada')
             LEFT JOIN guia_idiomas gi ON g.id_guia = gi.id_guia
             WHERE g.activo = 1
             GROUP BY g.id_guia
             ORDER BY tours_asignados DESC",
            [$fechaInicio, $fechaFin]
        );
    }
    
    /**
     * Reporte de ocupación
     */
    public function reporteOcupacion($fechaInicio, $fechaFin) {
        return $this->db->fetchAll(
            "SELECT 
                p.nombre_paquete,
                r.fecha_tour,
                r.hora_inicio,
                p.capacidad_maxima,
                SUM(r.numero_personas) as ocupados,
                p.capacidad_maxima - SUM(r.numero_personas) as disponibles,
                ROUND((SUM(r.numero_personas) / p.capacidad_maxima) * 100, 2) as porcentaje_ocupacion
             FROM reservaciones r
             INNER JOIN paquetes p ON r.id_paquete = p.id_paquete
             WHERE r.fecha_tour BETWEEN ? AND ?
             AND r.estado IN ('confirmada', 'pagada')
             GROUP BY r.id_paquete, r.fecha_tour, r.hora_inicio
             ORDER BY r.fecha_tour, r.hora_inicio",
            [$fechaInicio, $fechaFin]
        );
    }
    
    /**
     * Reporte de clientes
     */
    public function reporteClientes($fechaInicio, $fechaFin) {
        return $this->db->fetchAll(
            "SELECT 
                c.nombre_completo,
                c.email,
                c.idioma_preferido,
                COUNT(r.id_reservacion) as total_reservaciones,
                SUM(r.numero_personas) as total_personas,
                SUM(r.total) as total_gastado,
                MAX(r.fecha_tour) as ultima_visita,
                MIN(r.fecha_creacion) as primera_reserva
             FROM clientes c
             INNER JOIN reservaciones r ON c.id_cliente = r.id_cliente
             WHERE r.fecha_tour BETWEEN ? AND ?
             GROUP BY c.id_cliente
             HAVING total_reservaciones > 0
             ORDER BY total_gastado DESC",
            [$fechaInicio, $fechaFin]
        );
    }
    
    /**
     * Reporte de cancelaciones
     */
    public function reporteCancelaciones($fechaInicio, $fechaFin) {
        return $this->db->fetchAll(
            "SELECT 
                r.codigo_reservacion,
                r.fecha_tour,
                p.nombre_paquete,
                c.nombre_completo,
                c.email,
                r.total as monto_cancelado,
                h.motivo,
                h.fecha_cambio as fecha_cancelacion
             FROM reservaciones r
             INNER JOIN clientes c ON r.id_cliente = c.id_cliente
             INNER JOIN paquetes p ON r.id_paquete = p.id_paquete
             LEFT JOIN historial_estados h ON r.id_reservacion = h.id_reservacion
                AND h.estado_nuevo = 'cancelada'
             WHERE r.estado = 'cancelada'
             AND r.fecha_tour BETWEEN ? AND ?
             ORDER BY h.fecha_cambio DESC",
            [$fechaInicio, $fechaFin]
        );
    }
    
    /**
     * Exportar reporte a CSV
     */
    public function exportarCSV($datos, $nombreArchivo, $encabezados = []) {
        $csv = fopen('php://temp', 'r+');
        
        // Escribir encabezados
        if (!empty($encabezados)) {
            fputcsv($csv, $encabezados);
        } elseif (!empty($datos)) {
            fputcsv($csv, array_keys($datos[0]));
        }
        
        // Escribir datos
        foreach ($datos as $fila) {
            fputcsv($csv, $fila);
        }
        
        rewind($csv);
        $output = stream_get_contents($csv);
        fclose($csv);
        
        // Guardar en archivo
        $rutaArchivo = UPLOADS_PATH . '/reportes/' . $nombreArchivo;
        file_put_contents($rutaArchivo, $output);
        
        return [
            'success' => true,
            'ruta' => $rutaArchivo,
            'url' => UPLOADS_URL . '/reportes/' . $nombreArchivo
        ];
    }
    
    /**
     * Generar reporte consolidado
     */
    public function reporteConsolidado($fechaInicio, $fechaFin) {
        return [
            'ventas' => $this->reporteVentas($fechaInicio, $fechaFin),
            'guias' => $this->reporteGuias($fechaInicio, $fechaFin),
            'ocupacion' => $this->reporteOcupacion($fechaInicio, $fechaFin),
            'clientes' => $this->reporteClientes($fechaInicio, $fechaFin),
            'cancelaciones' => $this->reporteCancelaciones($fechaInicio, $fechaFin)
        ];
    }
    
    /**
     * Obtener datos para gráficas del dashboard
     */
    public function datosGraficas($periodo = '30dias') {
        $fechaFin = date('Y-m-d');
        
        switch ($periodo) {
            case '7dias':
                $fechaInicio = date('Y-m-d', strtotime('-7 days'));
                break;
            case '30dias':
                $fechaInicio = date('Y-m-d', strtotime('-30 days'));
                break;
            case 'mes_actual':
                $fechaInicio = date('Y-m-01');
                break;
            case 'año_actual':
                $fechaInicio = date('Y-01-01');
                break;
            default:
                $fechaInicio = date('Y-m-d', strtotime('-30 days'));
        }
        
        return [
            'ingresos_diarios' => $this->obtenerVentasPorDia($fechaInicio, $fechaFin),
            'ventas_por_paquete' => $this->obtenerVentasPorPaquete($fechaInicio, $fechaFin),
            'ocupacion' => $this->reporteOcupacion($fechaInicio, $fechaFin)
        ];
    }
}