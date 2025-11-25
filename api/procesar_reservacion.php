<?php


require_once __DIR__ . '/../config/config.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL);
}

try {

    if (!isset($_POST['csrf_token']) || !verificarCSRFToken($_POST['csrf_token'])) {
        throw new Exception('Token de seguridad inválido');
    }
    

    $id_paquete = isset($_POST['id_paquete']) ? (int)$_POST['id_paquete'] : 0;
    $nombre_completo = isset($_POST['nombre_completo']) ? sanitize($_POST['nombre_completo']) : '';
    $email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
    $alergias = isset($_POST['alergias']) ? sanitize($_POST['alergias']) : '';
    $idioma = isset($_POST['idioma']) ? sanitize($_POST['idioma']) : '';
    $fecha = isset($_POST['fecha']) ? sanitize($_POST['fecha']) : '';
    $horario = isset($_POST['horario']) ? sanitize($_POST['horario']) : '';
    $numero_personas = isset($_POST['numero_personas']) ? (int)$_POST['numero_personas'] : 0;
    $numero_guias = isset($_POST['numero_guias']) ? (int)$_POST['numero_guias'] : 0;
    

    if (!$id_paquete || !$nombre_completo || !$email || !$idioma || !$fecha || !$horario || !$numero_personas) {
        throw new Exception('Todos los campos obligatorios deben ser completados');
    }
    
    if (!validarEmail($email)) {
        throw new Exception('Email inválido');
    }
    
    if (!esFechaFutura($fecha)) {
        throw new Exception('La fecha debe ser futura');
    }
    

    list($hora_inicio, $hora_fin) = explode('|', $horario);
    
    // Obtener información del paquete
    $db = Database::getInstance();
    $paquete = $db->fetchOne(
        "SELECT * FROM paquetes WHERE id_paquete = ? AND activo = 1",
        [$id_paquete]
    );
    
    if (!$paquete) {
        throw new Exception('Paquete no encontrado');
    }
    
    // Verificar disponibilidad
    $reservado = $db->fetchOne(
        "SELECT COALESCE(SUM(numero_personas), 0) as total_reservado
         FROM reservaciones 
         WHERE id_paquete = ? 
         AND fecha_tour = ? 
         AND hora_inicio = ?
         AND estado IN ('pendiente', 'confirmada', 'pagada')",
        [$id_paquete, $fecha, $hora_inicio]
    );
    
    $cupos_disponibles = $paquete['capacidad_maxima'] - (int)$reservado['total_reservado'];
    
    if ($cupos_disponibles < $numero_personas) {
        throw new Exception('No hay suficientes lugares disponibles. Quedan ' . $cupos_disponibles . ' lugares.');
    }
    
   
    $precio_entradas = $numero_personas * $paquete['precio_entrada_persona'];
    $precio_guias = $numero_guias * $paquete['precio_guia'];
    $subtotal = $precio_entradas + $precio_guias;
    $total = $subtotal; 
    
    // Iniciar transacción
    $db->beginTransaction();
    
    try {
  
        // Cada reservación genera un cliente independiente para evitar sobrescribir nombres o datos previos
        $id_cliente = $db->insert(
            "INSERT INTO clientes (nombre_completo, email, idioma_preferido) VALUES (?, ?, ?)",
            [$nombre_completo, $email, $idioma]
        );
        

        $codigo_reservacion = generarCodigoReservacion();
        
        
        $id_reservacion = $db->insert(
            "INSERT INTO reservaciones (
                codigo_reservacion,
                id_cliente,
                id_paquete,
                fecha_tour,
                hora_inicio,
                numero_personas,
                numero_guias_requeridos,
                idioma_tour,
                subtotal,
                total,
                estado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')",
            [
                $codigo_reservacion,
                $id_cliente,
                $id_paquete,
                $fecha,
                $hora_inicio,
                $numero_personas,
                $numero_guias,
                $idioma,
                $subtotal,
                $total
            ]
        );
        
 
        $db->insert(
            "INSERT INTO participantes (
                id_reservacion,
                nombre_completo,
                alergias,
                idioma
            ) VALUES (?, ?, ?, ?)",
            [
                $id_reservacion,
                $nombre_completo,
                $alergias,
                $idioma
            ]
        );
        
    
        $db->commit();
        
  
        logActivity("Nueva reservación creada: {$codigo_reservacion}");
        
   
        redirect(SITE_URL . '/pages/confirmacion.php?codigo=' . $codigo_reservacion);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    
   
    if (isset($id_paquete) && $id_paquete) {
        redirect(SITE_URL . '/pages/reservar.php?id=' . $id_paquete . '&error=1');
    } else {
        redirect(SITE_URL . '/pages/paquetes.php');
    }
}
