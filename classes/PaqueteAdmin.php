<?php

class PaqueteAdmin {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Crear nuevo paquete
     */
    public function crear($datos) {
        try {
            $this->db->beginTransaction();
            
            // Insertar paquete
            $idPaquete = $this->db->insert(
                "INSERT INTO paquetes (
                    nombre_paquete, descripcion_es, descripcion_en, descripcion_fr,
                    duracion_horas, capacidad_maxima, personas_por_guia,
                    precio_guia, precio_entrada_persona, activo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $datos['nombre_paquete'],
                    $datos['descripcion_es'],
                    $datos['descripcion_en'] ?? null,
                    $datos['descripcion_fr'] ?? null,
                    $datos['duracion_horas'],
                    $datos['capacidad_maxima'],
                    $datos['personas_por_guia'],
                    $datos['precio_guia'],
                    $datos['precio_entrada_persona'],
                    $datos['activo'] ?? 1
                ]
            );
            
            // Insertar horarios
            if (!empty($datos['horarios'])) {
                foreach ($datos['horarios'] as $horario) {
                    $this->db->insert(
                        "INSERT INTO horarios (id_paquete, dia_semana, hora_inicio, hora_fin, activo)
                         VALUES (?, ?, ?, ?, 1)",
                        [
                            $idPaquete,
                            $horario['dia_semana'],
                            $horario['hora_inicio'],
                            $horario['hora_fin']
                        ]
                    );
                }
            }
            
            $this->db->commit();
            
            logActivity("Paquete creado: {$datos['nombre_paquete']} (ID: $idPaquete)");
            
            return [
                'success' => true,
                'id_paquete' => $idPaquete,
                'message' => 'Paquete creado exitosamente'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error al crear paquete: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error al crear paquete: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Actualizar paquete existente
     */
    public function actualizar($idPaquete, $datos) {
        try {
            $this->db->beginTransaction();
            
            // Actualizar datos del paquete
            $this->db->execute(
                "UPDATE paquetes SET
                    nombre_paquete = ?,
                    descripcion_es = ?,
                    descripcion_en = ?,
                    descripcion_fr = ?,
                    duracion_horas = ?,
                    capacidad_maxima = ?,
                    personas_por_guia = ?,
                    precio_guia = ?,
                    precio_entrada_persona = ?,
                    activo = ?
                 WHERE id_paquete = ?",
                [
                    $datos['nombre_paquete'],
                    $datos['descripcion_es'],
                    $datos['descripcion_en'] ?? null,
                    $datos['descripcion_fr'] ?? null,
                    $datos['duracion_horas'],
                    $datos['capacidad_maxima'],
                    $datos['personas_por_guia'],
                    $datos['precio_guia'],
                    $datos['precio_entrada_persona'],
                    $datos['activo'] ?? 1,
                    $idPaquete
                ]
            );
            
            // Actualizar horarios si se proporcionan
            if (isset($datos['horarios'])) {
                // Eliminar horarios anteriores
                $this->db->execute(
                    "DELETE FROM horarios WHERE id_paquete = ?",
                    [$idPaquete]
                );
                
                // Insertar nuevos horarios
                foreach ($datos['horarios'] as $horario) {
                    $this->db->insert(
                        "INSERT INTO horarios (id_paquete, dia_semana, hora_inicio, hora_fin, activo)
                         VALUES (?, ?, ?, ?, 1)",
                        [
                            $idPaquete,
                            $horario['dia_semana'],
                            $horario['hora_inicio'],
                            $horario['hora_fin']
                        ]
                    );
                }
            }
            
            $this->db->commit();
            
            logActivity("Paquete actualizado: ID $idPaquete");
            
            return [
                'success' => true,
                'message' => 'Paquete actualizado exitosamente'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error al actualizar paquete: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error al actualizar paquete: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Activar/Desactivar paquete
     */
    public function cambiarEstado($idPaquete, $activo) {
        // Verificar si hay reservas futuras
        if (!$activo) {
            $reservasFuturas = $this->db->fetchOne(
                "SELECT COUNT(*) as total
                 FROM reservaciones
                 WHERE id_paquete = ?
                 AND fecha_tour >= CURDATE()
                 AND estado IN ('confirmada', 'pagada', 'pendiente')",
                [$idPaquete]
            );
            
            if ($reservasFuturas['total'] > 0) {
                return [
                    'success' => false,
                    'message' => "No se puede desactivar. Hay {$reservasFuturas['total']} reserva(s) futuras."
                ];
            }
        }
        
        $this->db->execute(
            "UPDATE paquetes SET activo = ? WHERE id_paquete = ?",
            [$activo, $idPaquete]
        );
        
        $estado = $activo ? 'activado' : 'desactivado';
        logActivity("Paquete $estado: ID $idPaquete");
        
        return [
            'success' => true,
            'message' => "Paquete $estado correctamente"
        ];
    }
    
    /**
     * Subir imagen de paquete
     */
    public function subirImagen($idPaquete, $archivo, $tipo = 'banner') {
        $uploadDir = ASSETS_PATH . '/img/packages/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Validar que sea imagen
        $imageInfo = getimagesize($archivo['tmp_name']);
        
        if (!$imageInfo) {
            return [
                'success' => false,
                'message' => 'El archivo no es una imagen válida'
            ];
        }
        
        // Generar nombre
        $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
        $nombreArchivo = "package-{$idPaquete}-{$tipo}." . $extension;
        $rutaDestino = $uploadDir . $nombreArchivo;
        
        if (move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
            logActivity("Imagen subida para paquete $idPaquete: $tipo");
            
            return [
                'success' => true,
                'filename' => $nombreArchivo,
                'url' => ASSETS_URL . '/img/packages/' . $nombreArchivo
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Error al subir archivo'
        ];
    }
    
    /**
     * Obtener estadísticas de un paquete
     */
    public function obtenerEstadisticas($idPaquete, $fechaInicio = null, $fechaFin = null) {
        if (!$fechaInicio) {
            $fechaInicio = date('Y-m-01'); // Primer día del mes actual
        }
        if (!$fechaFin) {
            $fechaFin = date('Y-m-t'); // Último día del mes actual
        }
        
        // Total de reservas
        $reservas = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_reservas,
                SUM(numero_personas) as total_personas,
                SUM(total) as ingresos_totales
             FROM reservaciones
             WHERE id_paquete = ?
             AND fecha_tour BETWEEN ? AND ?
             AND estado IN ('confirmada', 'pagada')",
            [$idPaquete, $fechaInicio, $fechaFin]
        );
        
        // Promedio de ocupación
        $ocupacion = $this->db->fetchOne(
            "SELECT 
                AVG((r.numero_personas / p.capacidad_maxima) * 100) as ocupacion_promedio
             FROM reservaciones r
             INNER JOIN paquetes p ON r.id_paquete = p.id_paquete
             WHERE r.id_paquete = ?
             AND r.fecha_tour BETWEEN ? AND ?
             AND r.estado IN ('confirmada', 'pagada')",
            [$idPaquete, $fechaInicio, $fechaFin]
        );
        
        return [
            'reservas' => $reservas,
            'ocupacion_promedio' => round($ocupacion['ocupacion_promedio'] ?? 0, 2)
        ];
    }
}