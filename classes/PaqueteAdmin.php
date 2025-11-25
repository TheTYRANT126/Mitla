<?php

class PaqueteAdmin {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Convierte los datos del formulario o del esquema nuevo al formato actual de la tabla `paquetes`.
     */
    private function prepararDatosPaquete(array $datos, $duracionMinutos = null) {
        if ($duracionMinutos === null || $duracionMinutos <= 0) {
            if (isset($datos['duracion']) && $datos['duracion'] !== '') {
                $duracionMinutos = (int) $datos['duracion'];
            } elseif (isset($datos['duracion_minutos']) && $datos['duracion_minutos'] !== '') {
                $duracionMinutos = (int) $datos['duracion_minutos'];
            }
        }
        if ($duracionMinutos !== null && $duracionMinutos > 0) {
            $duracionHoras = round($duracionMinutos / 60, 2);
        } else {
            $duracionHoras = isset($datos['duracion_horas']) ? (float) $datos['duracion_horas'] : 0;
        }
        
        $capacidad = (int) ($datos['max_personas'] ?? $datos['capacidad_maxima'] ?? 0);
        if ($capacidad <= 0) {
            $capacidad = 1;
        }
        
        $personasPorGuia = (int) ($datos['num_guias_requeridos'] ?? $datos['personas_por_guia'] ?? 1);
        if ($personasPorGuia <= 0) {
            $personasPorGuia = 1;
        }
        
        $precioGuia = isset($datos['precio_guia']) ? (float) $datos['precio_guia'] : null;
        if ($precioGuia === null) {
            $precioGuia = isset($datos['precio_base']) ? (float) $datos['precio_base'] : 0;
        }
        
        $precioEntrada = isset($datos['precio_entrada_persona']) ? (float) $datos['precio_entrada_persona'] : 0;
        
        return [
            'nombre_paquete' => trim($datos['nombre'] ?? $datos['nombre_paquete'] ?? ''),
            'descripcion_es' => $datos['descripcion'] ?? $datos['descripcion_es'] ?? '',
            'descripcion_en' => $datos['descripcion_en'] ?? null,
            'descripcion_fr' => $datos['descripcion_fr'] ?? null,
            'duracion_horas' => $duracionHoras,
            'capacidad_maxima' => $capacidad,
            'personas_por_guia' => $personasPorGuia,
            'precio_guia' => $precioGuia,
            'precio_entrada_persona' => $precioEntrada,
            'activo' => isset($datos['activo']) ? (int) $datos['activo'] : 1
        ];
    }

    /**
     * Expande los horarios enviados en el formulario a registros compatibles con la tabla `horarios`.
     */
    private function prepararHorarios(array $horarios) {
        $horariosPreparados = [];
        foreach ($horarios as $horario) {
            if (empty($horario['hora_inicio'])) {
                continue;
            }
            
            $horaInicio = $horario['hora_inicio'];
            $horaFin = !empty($horario['hora_fin']) ? $horario['hora_fin'] : $horaInicio;
            $diasSeleccionados = $this->resolverDiasSemana($horario['dias_semana'] ?? $horario['dia_semana'] ?? 'todos');
            
            foreach ($diasSeleccionados as $dia) {
                $horariosPreparados[] = [
                    'dia_semana' => $dia,
                    'hora_inicio' => $horaInicio,
                    'hora_fin' => $horaFin
                ];
            }
        }
        return $horariosPreparados;
    }
    
    /**
     * Mapea valores especiales de días hacia los días individuales requeridos por la BD.
     */
    private function resolverDiasSemana($valor) {
        $valor = strtolower($valor ?? '');
        $todos = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'];
        $mapaEspecial = [
            'todos' => $todos,
            'lunes_viernes' => ['lunes','martes','miercoles','jueves','viernes'],
            'fines_semana' => ['sabado','domingo']
        ];
        if (strpos($valor, ',') !== false) {
            $partes = array_filter(array_map('trim', explode(',', $valor)));
            $diasFiltrados = array_values(array_intersect($todos, $partes));
            if (!empty($diasFiltrados)) {
                return $diasFiltrados;
            }
        }
        
        if (isset($mapaEspecial[$valor])) {
            return $mapaEspecial[$valor];
        }
        
        if (in_array($valor, $todos, true)) {
            return [$valor];
        }
        
        return $todos;
    }
    
    /**
     * Crear nuevo paquete
     */
    public function crear($datos) {
        try {
            $this->db->beginTransaction();
            $duracionMinutos = calcularDuracionMinutosHorarios($datos['horarios'] ?? []);
            $campos = $this->prepararDatosPaquete($datos, $duracionMinutos);
            
            // Insertar paquete
            $idPaquete = $this->db->insert(
                "INSERT INTO paquetes (
                    nombre_paquete, descripcion_es, descripcion_en, descripcion_fr,
                    duracion_horas, capacidad_maxima, personas_por_guia,
                    precio_guia, precio_entrada_persona, activo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $campos['nombre_paquete'],
                    $campos['descripcion_es'],
                    $campos['descripcion_en'],
                    $campos['descripcion_fr'],
                    $campos['duracion_horas'],
                    $campos['capacidad_maxima'],
                    $campos['personas_por_guia'],
                    $campos['precio_guia'],
                    $campos['precio_entrada_persona'],
                    $campos['activo']
                ]
            );
            $idPaquete = (int) $idPaquete;
            
            // Insertar horarios
            if (!empty($datos['horarios'])) {
                $horarios = $this->prepararHorarios($datos['horarios']);
                foreach ($horarios as $horario) {
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
            
            logActivity("Paquete creado: {$campos['nombre_paquete']} (ID: $idPaquete)");
            
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
            $duracionMinutos = calcularDuracionMinutosHorarios($datos['horarios'] ?? []);
            $campos = $this->prepararDatosPaquete($datos, $duracionMinutos);
            
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
                    $campos['nombre_paquete'],
                    $campos['descripcion_es'],
                    $campos['descripcion_en'],
                    $campos['descripcion_fr'],
                    $campos['duracion_horas'],
                    $campos['capacidad_maxima'],
                    $campos['personas_por_guia'],
                    $campos['precio_guia'],
                    $campos['precio_entrada_persona'],
                    $campos['activo'],
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
                $horarios = $this->prepararHorarios($datos['horarios']);
                foreach ($horarios as $horario) {
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
        $idPaquete = (int) $idPaquete;
        $esGaleria = ($tipo === 'galeria');
        $uploadDir = $esGaleria ? getPackageGalleryDirectory() : ASSETS_PATH . '/img/packages/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $uploadDir = rtrim($uploadDir, '/\\') . '/';
        
        // Validar que sea imagen
        $imageInfo = getimagesize($archivo['tmp_name']);
        
        if (!$imageInfo) {
            return [
                'success' => false,
                'message' => 'El archivo no es una imagen válida'
            ];
        }
        
        $extensionOriginal = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $extPermitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        if (!in_array($extensionOriginal, $extPermitidas, true)) {
            return [
                'success' => false,
                'message' => 'Formato de imagen no permitido'
            ];
        }
        
        $requiereConversion = !in_array($extensionOriginal, ['jpg', 'jpeg'], true);
        $extension = 'jpg';
        
        if ($tipo === 'banner') {
            foreach ($extPermitidas as $ext) {
                $rutaExistente = $uploadDir . "package-{$idPaquete}." . $ext;
                if (is_file($rutaExistente)) {
                    @unlink($rutaExistente);
                }
            }
            $nombreArchivo = "package-{$idPaquete}." . $extension;
        } elseif ($esGaleria) {
            $imagenesActuales = getPackageGalleryImages($idPaquete);
            $indice = count($imagenesActuales) + 1;
            $nombreArchivo = sprintf('galeria-%d-%d.%s', $idPaquete, $indice, $extension);
        } else {
            $nombreArchivo = "package-{$idPaquete}-{$tipo}." . $extension;
        }
        
        $rutaDestino = $uploadDir . $nombreArchivo;
        
        $guardado = $requiereConversion
            ? $this->convertirTemporalAJpg($archivo['tmp_name'], $rutaDestino)
            : move_uploaded_file($archivo['tmp_name'], $rutaDestino);

        if ($guardado) {
            if ($tipo === 'banner') {
                try {
                    $this->db->execute(
                        "UPDATE paquetes SET imagen = ? WHERE id_paquete = ?",
                        [$nombreArchivo, $idPaquete]
                    );
                } catch (Exception $e) {
                    error_log("Error al guardar imagen de paquete {$idPaquete}: " . $e->getMessage());
                }
            }
            logActivity("Imagen subida para paquete $idPaquete: $tipo");
            
            return [
                'success' => true,
                'filename' => $nombreArchivo,
                'url' => $esGaleria
                    ? ASSETS_URL . '/img/paquete/' . $nombreArchivo
                    : ASSETS_URL . '/img/packages/' . $nombreArchivo
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Error al subir archivo'
        ];
    }

    private function convertirTemporalAJpg($origen, $destino) {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
            return false;
        }
        $contenido = @file_get_contents($origen);
        if ($contenido === false) {
            return false;
        }
        $imagen = @imagecreatefromstring($contenido);
        if (!$imagen) {
            return false;
        }
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $lienzo = imagecreatetruecolor($ancho, $alto);
        if (!$lienzo) {
            imagedestroy($imagen);
            return false;
        }
        $blanco = imagecolorallocate($lienzo, 255, 255, 255);
        imagefill($lienzo, 0, 0, $blanco);
        imagecopy($lienzo, $imagen, 0, 0, 0, 0, $ancho, $alto);
        $resultado = imagejpeg($lienzo, $destino, 90);
        imagedestroy($imagen);
        imagedestroy($lienzo);
        return $resultado;
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
