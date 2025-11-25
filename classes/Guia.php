<?php


class Guia {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtener todos los guías
     */
    public function obtenerTodos($soloActivos = true) {
        $sql = "SELECT g.*, u.email, u.activo as usuario_activo,
                GROUP_CONCAT(DISTINCT gi.idioma SEPARATOR ', ') as idiomas
                FROM guias g
                INNER JOIN usuarios u ON g.id_usuario = u.id_usuario
                LEFT JOIN guia_idiomas gi ON g.id_guia = gi.id_guia";
        
        if ($soloActivos) {
            $sql .= " WHERE g.activo = 1 AND u.activo = 1";
        }
        
        $sql .= " GROUP BY g.id_guia ORDER BY g.nombre_completo";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Obtener guía por ID
     */
    public function obtenerPorId($idGuia) {
        $guia = $this->db->fetchOne(
            "SELECT g.*, u.email, u.activo as usuario_activo
             FROM guias g
             INNER JOIN usuarios u ON g.id_usuario = u.id_usuario
             WHERE g.id_guia = ?",
            [$idGuia]
        );
        
        if ($guia) {
            // Obtener idiomas del guía
            $guia['idiomas'] = $this->db->fetchAll(
                "SELECT idioma FROM guia_idiomas WHERE id_guia = ?",
                [$idGuia]
            );
            
            // Obtener comentarios del admin
            $guia['comentarios'] = $this->db->fetchAll(
                "SELECT * FROM guia_comentarios 
                 WHERE id_guia = ? 
                 ORDER BY fecha_comentario DESC",
                [$idGuia]
            );
        }
        
        return $guia;
    }
    
    /**
     * Crear nuevo guía
     */
    public function crear($datos) {
        try {
            $this->db->beginTransaction();
            
            // 1. Crear usuario
            $passwordHash = password_hash($datos['password'], PASSWORD_BCRYPT);
            
            $idUsuario = $this->db->insert(
                "INSERT INTO usuarios (email, password_hash, rol, nombre_completo, activo)
                 VALUES (?, ?, 'guia', ?, 1)",
                [$datos['email'], $passwordHash, $datos['nombre_completo']]
            );
            
            // 2. Crear registro de guía
            $idGuia = $this->db->insert(
                "INSERT INTO guias (
                    id_usuario, nombre_completo, fecha_nacimiento, curp, 
                    domicilio, telefono, foto_perfil, activo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
                [
                    $idUsuario,
                    $datos['nombre_completo'],
                    $datos['fecha_nacimiento'],
                    $datos['curp'],
                    $datos['domicilio'],
                    $datos['telefono'],
                    $datos['foto_perfil'] ?? null
                ]
            );
            
            // 3. Asignar idiomas
            if (!empty($datos['idiomas'])) {
                foreach ($datos['idiomas'] as $idioma) {
                    $this->db->insert(
                        "INSERT INTO guia_idiomas (id_guia, idioma) VALUES (?, ?)",
                        [$idGuia, $idioma]
                    );
                }
            }
            
            $this->db->commit();
            
            logActivity("Guía creado: {$datos['nombre_completo']} (ID: $idGuia)");
            
            return [
                'success' => true,
                'id_guia' => $idGuia,
                'message' => 'Guía registrado exitosamente'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error al crear guía: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error al registrar guía: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Actualizar información de guía
     */
    public function actualizar($idGuia, $datos) {
        try {
            $this->db->beginTransaction();
            
            // 1. Actualizar datos del guía
            $this->db->execute(
                "UPDATE guias SET
                    nombre_completo = ?,
                    fecha_nacimiento = ?,
                    curp = ?,
                    domicilio = ?,
                    telefono = ?,
                    foto_perfil = ?
                 WHERE id_guia = ?",
                [
                    $datos['nombre_completo'],
                    $datos['fecha_nacimiento'],
                    $datos['curp'],
                    $datos['domicilio'],
                    $datos['telefono'],
                    $datos['foto_perfil'] ?? null,
                    $idGuia
                ]
            );
            
            // 2. Actualizar email en usuario si cambió
            if (isset($datos['email'])) {
                $guia = $this->obtenerPorId($idGuia);
                $this->db->execute(
                    "UPDATE usuarios SET email = ?, nombre_completo = ? WHERE id_usuario = ?",
                    [$datos['email'], $datos['nombre_completo'], $guia['id_usuario']]
                );
            }
            
            // 3. Actualizar idiomas
            if (isset($datos['idiomas'])) {
                // Eliminar idiomas anteriores
                $this->db->execute(
                    "DELETE FROM guia_idiomas WHERE id_guia = ?",
                    [$idGuia]
                );
                
                // Insertar nuevos idiomas
                foreach ($datos['idiomas'] as $idioma) {
                    $this->db->insert(
                        "INSERT INTO guia_idiomas (id_guia, idioma) VALUES (?, ?)",
                        [$idGuia, $idioma]
                    );
                }
            }
            
            $this->db->commit();
            
            logActivity("Guía actualizado: ID $idGuia");
            
            return [
                'success' => true,
                'message' => 'Información actualizada correctamente'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error al actualizar guía: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Activar/Desactivar guía
     */
    public function cambiarEstado($idGuia, $activo) {
        try {
            $this->db->beginTransaction();
            
            // Actualizar estado del guía
            $this->db->execute(
                "UPDATE guias SET activo = ? WHERE id_guia = ?",
                [$activo, $idGuia]
            );
            
            // Actualizar estado del usuario
            $guia = $this->obtenerPorId($idGuia);
            $this->db->execute(
                "UPDATE usuarios SET activo = ? WHERE id_usuario = ?",
                [$activo, $guia['id_usuario']]
            );
            
            $this->db->commit();
            
            $estado = $activo ? 'activado' : 'desactivado';
            logActivity("Guía $estado: ID $idGuia");
            
            return [
                'success' => true,
                'message' => "Guía $estado correctamente"
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            
            return [
                'success' => false,
                'message' => 'Error al cambiar estado: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Agregar comentario a un guía
     */
    public function agregarComentario($idGuia, $comentario, $idAdmin) {
        $this->db->insert(
            "INSERT INTO guia_comentarios (id_guia, id_admin, comentario, fecha_comentario)
             VALUES (?, ?, ?, NOW())",
            [$idGuia, $idAdmin, $comentario]
        );
        
        logActivity("Comentario agregado al guía ID: $idGuia");
        
        return [
            'success' => true,
            'message' => 'Comentario agregado'
        ];
    }
    
    /**
     * Subir foto de perfil
     */
    public function subirFoto($idGuia, $archivo) {
        $uploadDir = UPLOADS_PATH . '/guias/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Validar que sea imagen y formato cuadrado
        $imageInfo = getimagesize($archivo['tmp_name']);
        
        if (!$imageInfo) {
            return [
                'success' => false,
                'message' => 'El archivo no es una imagen válida'
            ];
        }
        
        // Generar nombre único
        $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
        $nombreArchivo = 'guia_' . $idGuia . '_' . time() . '.' . $extension;
        $rutaDestino = $uploadDir . $nombreArchivo;
        
        if (move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
            // Actualizar ruta en BD
            $this->db->execute(
                "UPDATE guias SET foto_perfil = ? WHERE id_guia = ?",
                [$nombreArchivo, $idGuia]
            );
            
            return [
                'success' => true,
                'filename' => $nombreArchivo,
                'url' => UPLOADS_URL . '/guias/' . $nombreArchivo
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Error al subir archivo'
        ];
    }
    
    /**
     * Obtener guías sugeridos para una reserva
     */
    public function obtenerSugeridos($fecha, $horaInicio, $idioma, $numGuiasRequeridos) {
        // Obtener guías disponibles (que no tengan asignación a esa hora)
        $guiasDisponibles = $this->db->fetchAll(
            "SELECT g.*, 
                    GROUP_CONCAT(DISTINCT gi.idioma) as idiomas,
                    CASE WHEN gi.idioma = ? THEN 1 ELSE 0 END as habla_idioma_requerido,
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 
                            FROM asignacion_guias ag2
                            INNER JOIN reservaciones r2 ON ag2.id_reservacion = r2.id_reservacion
                            WHERE ag2.id_guia = g.id_guia
                            AND r2.fecha_tour = ?
                            AND r2.hora_inicio = ?
                            AND r2.estado IN ('confirmada','pagada')
                        ) THEN 0
                        ELSE 1
                    END AS disponible,
                    CASE WHEN EXISTS (
                        SELECT 1
                        FROM guia_idiomas gi2
                        WHERE gi2.id_guia = g.id_guia
                        AND gi2.idioma = ?
                    ) THEN 1 ELSE 0 END AS preferido
             FROM guias g
             INNER JOIN usuarios u ON g.id_usuario = u.id_usuario
             LEFT JOIN guia_idiomas gi ON g.id_guia = gi.id_guia
             WHERE g.activo = 1 
             AND u.activo = 1
             GROUP BY g.id_guia
             ORDER BY preferido DESC, disponible DESC, g.nombre_completo
             LIMIT ?",
            [$idioma, $fecha, $horaInicio, $idioma, $numGuiasRequeridos * 2]
        );
        
        return $guiasDisponibles;
    }
}
