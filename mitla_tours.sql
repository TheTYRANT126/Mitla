-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 24-11-2025 a las 15:49:10
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `mitla_tours`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_estadisticas_dashboard` (IN `p_fecha` DATE)   BEGIN
    SELECT 
        COUNT(*) as total_reservas,
        SUM(numero_personas) as total_personas,
        SUM(total) as ingresos_dia
    FROM reservaciones
    WHERE fecha_tour = p_fecha
    AND estado IN ('confirmada', 'pagada');
    
    SELECT 
        p.nombre_paquete,
        r.hora_inicio,
        p.capacidad_maxima,
        COALESCE(SUM(r.numero_personas), 0) as ocupados,
        p.capacidad_maxima - COALESCE(SUM(r.numero_personas), 0) as disponibles
    FROM paquetes p
    LEFT JOIN reservaciones r ON p.id_paquete = r.id_paquete 
        AND r.fecha_tour = p_fecha 
        AND r.estado IN ('confirmada', 'pagada')
    WHERE p.activo = 1
    GROUP BY p.id_paquete, r.hora_inicio;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_guias_disponibles` (IN `p_fecha` DATE, IN `p_hora_inicio` TIME, IN `p_idioma` VARCHAR(50))   BEGIN
    SELECT 
        g.*,
        u.email,
        GROUP_CONCAT(gi.idioma) as idiomas
    FROM guias g
    INNER JOIN usuarios u ON g.id_usuario = u.id_usuario
    LEFT JOIN guia_idiomas gi ON g.id_guia = gi.id_guia
    WHERE g.activo = 1
    AND u.activo = 1
    AND g.id_guia NOT IN (
        SELECT ag.id_guia
        FROM asignacion_guias ag
        INNER JOIN reservaciones r ON ag.id_reservacion = r.id_reservacion
        WHERE r.fecha_tour = p_fecha
        AND r.hora_inicio = p_hora_inicio
        AND r.estado IN ('confirmada', 'pagada')
    )
    AND (
        p_idioma IS NULL 
        OR g.id_guia IN (
            SELECT id_guia 
            FROM guia_idiomas 
            WHERE idioma = p_idioma
        )
    )
    GROUP BY g.id_guia
    ORDER BY g.nombre_completo;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignacion_guias`
--

CREATE TABLE `asignacion_guias` (
  `id_asignacion` int(11) NOT NULL,
  `id_reservacion` int(11) NOT NULL,
  `id_guia` int(11) NOT NULL,
  `fecha_asignacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_log`
--

CREATE TABLE `audit_log` (
  `id_log` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `accion` varchar(255) NOT NULL,
  `tabla_afectada` varchar(100) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `datos_anteriores` text DEFAULT NULL,
  `datos_nuevos` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `fecha_accion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id_cliente` int(11) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `pais` varchar(100) DEFAULT NULL,
  `idioma_preferido` enum('español','ingles','frances') DEFAULT 'español',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id_cliente`, `nombre_completo`, `email`, `telefono`, `pais`, `idioma_preferido`, `fecha_registro`) VALUES
(1, 'Emmanuel', 'correodeprueba126@hotmail.com', NULL, NULL, 'español', '2025-10-21 15:55:17'),
(2, 'Emmanuel', 'spidermanvenom1206@hotmail.com', NULL, NULL, 'español', '2025-10-21 19:34:31');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion`
--

CREATE TABLE `configuracion` (
  `id_config` int(11) NOT NULL,
  `clave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `configuracion`
--

INSERT INTO `configuracion` (`id_config`, `clave`, `valor`, `descripcion`, `fecha_actualizacion`) VALUES
(1, 'email_sistema', 'noreply@mitla.com', 'Email del sistema para envíos', '2025-10-14 00:47:01'),
(2, 'dias_anticipacion_recordatorio', '2', 'Días de anticipación para enviar recordatorio', '2025-10-14 00:47:01'),
(3, 'api_key_clima', '', 'API Key de OpenWeatherMap', '2025-10-14 00:47:01'),
(4, 'sitio_web', 'www.mitlacuevas.com', 'URL del sitio web', '2025-10-14 00:47:01'),
(5, 'telefono_contacto', '951-123-4567', 'Teléfono de contacto', '2025-10-14 00:47:01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_sistema`
--

CREATE TABLE `configuracion_sistema` (
  `id_config` int(11) NOT NULL,
  `clave` varchar(100) NOT NULL,
  `valor` text NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo_dato` enum('string','int','boolean','json') DEFAULT 'string',
  `fecha_modificacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `modificado_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `configuracion_sistema`
--

INSERT INTO `configuracion_sistema` (`id_config`, `clave`, `valor`, `descripcion`, `tipo_dato`, `fecha_modificacion`, `modificado_por`) VALUES
(1, 'emails_activos', '1', 'Activar/desactivar envío de emails', 'boolean', '2025-11-23 17:58:10', NULL),
(2, 'dias_recordatorio', '1', 'Días de anticipación para recordatorio', 'int', '2025-11-23 17:58:10', NULL),
(3, 'permitir_cancelaciones', '1', 'Permitir cancelaciones de clientes', 'boolean', '2025-11-23 17:58:10', NULL),
(4, 'horas_minimas_cancelacion', '24', 'Horas mínimas antes del tour para cancelar', 'int', '2025-11-23 17:58:10', NULL),
(5, 'modo_mantenimiento', '0', 'Activar modo mantenimiento', 'boolean', '2025-11-23 17:58:10', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `disponibilidad_calendario`
--

CREATE TABLE `disponibilidad_calendario` (
  `id_disponibilidad` int(11) NOT NULL,
  `id_paquete` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `motivo` varchar(255) DEFAULT NULL,
  `creado_por` int(11) NOT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `guias`
--

CREATE TABLE `guias` (
  `id_guia` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `nombre_completo` varchar(255) NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `curp` varchar(18) NOT NULL,
  `domicilio` text NOT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Disparadores `guias`
--
DELIMITER $$
CREATE TRIGGER `audit_guias_update` AFTER UPDATE ON `guias` FOR EACH ROW BEGIN
    IF @current_user_id IS NOT NULL THEN
        INSERT INTO audit_log (
            id_usuario, 
            accion, 
            tabla_afectada, 
            registro_id, 
            datos_anteriores, 
            datos_nuevos,
            ip
        ) VALUES (
            @current_user_id,
            'UPDATE',
            'guias',
            NEW.id_guia,
            JSON_OBJECT(
                'nombre_completo', OLD.nombre_completo,
                'activo', OLD.activo,
                'telefono', OLD.telefono
            ),
            JSON_OBJECT(
                'nombre_completo', NEW.nombre_completo,
                'activo', NEW.activo,
                'telefono', NEW.telefono
            ),
            @current_ip
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `guia_comentarios`
--

CREATE TABLE `guia_comentarios` (
  `id_comentario` int(11) NOT NULL,
  `id_guia` int(11) NOT NULL,
  `id_admin` int(11) NOT NULL,
  `comentario` text NOT NULL,
  `fecha_comentario` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `guia_idiomas`
--

CREATE TABLE `guia_idiomas` (
  `id_guia_idioma` int(11) NOT NULL,
  `id_guia` int(11) NOT NULL,
  `idioma` enum('español','ingles','frances') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_estados`
--

CREATE TABLE `historial_estados` (
  `id_historial` int(11) NOT NULL,
  `id_reservacion` int(11) NOT NULL,
  `estado_anterior` varchar(50) DEFAULT NULL,
  `estado_nuevo` varchar(50) NOT NULL,
  `motivo` text DEFAULT NULL,
  `fecha_cambio` datetime DEFAULT current_timestamp(),
  `cambiado_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horarios`
--

CREATE TABLE `horarios` (
  `id_horario` int(11) NOT NULL,
  `id_paquete` int(11) NOT NULL,
  `dia_semana` enum('lunes','martes','miercoles','jueves','viernes','sabado','domingo') NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `horarios`
--

INSERT INTO `horarios` (`id_horario`, `id_paquete`, `dia_semana`, `hora_inicio`, `hora_fin`, `activo`) VALUES
(1, 1, 'miercoles', '09:00:00', '12:00:00', 1),
(2, 1, 'miercoles', '13:00:00', '16:00:00', 1),
(3, 1, 'jueves', '09:00:00', '12:00:00', 1),
(4, 1, 'jueves', '13:00:00', '16:00:00', 1),
(5, 1, 'viernes', '09:00:00', '12:00:00', 1),
(6, 1, 'viernes', '13:00:00', '16:00:00', 1),
(7, 1, 'sabado', '09:00:00', '12:00:00', 1),
(8, 1, 'sabado', '13:00:00', '16:00:00', 1),
(9, 1, 'domingo', '09:00:00', '12:00:00', 1),
(10, 1, 'domingo', '13:00:00', '16:00:00', 1),
(11, 2, 'martes', '09:00:00', '12:00:00', 1),
(12, 2, 'martes', '13:00:00', '16:00:00', 1),
(13, 2, 'miercoles', '09:00:00', '12:00:00', 1),
(14, 2, 'miercoles', '13:00:00', '16:00:00', 1),
(15, 2, 'jueves', '09:00:00', '12:00:00', 1),
(16, 2, 'jueves', '13:00:00', '16:00:00', 1),
(17, 2, 'viernes', '09:00:00', '12:00:00', 1),
(18, 2, 'viernes', '13:00:00', '16:00:00', 1),
(19, 2, 'sabado', '09:00:00', '12:00:00', 1),
(20, 2, 'sabado', '13:00:00', '16:00:00', 1),
(21, 2, 'domingo', '09:00:00', '12:00:00', 1),
(22, 2, 'domingo', '13:00:00', '16:00:00', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `idiomas_guias`
--

CREATE TABLE `idiomas_guias` (
  `id` int(11) NOT NULL,
  `id_guia` int(11) NOT NULL,
  `idioma` enum('español','ingles','frances','zapoteco') NOT NULL,
  `nivel` enum('basico','intermedio','avanzado','nativo') DEFAULT 'intermedio'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `idiomas_guias`
--

INSERT INTO `idiomas_guias` (`id`, `id_guia`, `idioma`, `nivel`) VALUES
(1, 1, 'español', 'nativo'),
(2, 1, 'ingles', 'avanzado'),
(3, 1, 'zapoteco', 'intermedio'),
(4, 2, 'español', 'nativo'),
(5, 2, 'frances', 'avanzado'),
(6, 2, 'ingles', 'intermedio'),
(7, 3, 'español', 'nativo'),
(8, 3, 'ingles', 'basico');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `intentos_login`
--

CREATE TABLE `intentos_login` (
  `id_intento` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `fecha_intento` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `intentos_login`
--

INSERT INTO `intentos_login` (`id_intento`, `email`, `ip`, `fecha_intento`) VALUES
(1, 'admin@mitlatours.com', '::1', '2025-11-23 20:05:25'),
(2, 'admin@mitlatours.com', '::1', '2025-11-23 20:05:40');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `log_emails`
--

CREATE TABLE `log_emails` (
  `id_log` int(11) NOT NULL,
  `id_reservacion` int(11) DEFAULT NULL,
  `tipo_email` enum('ticket','recordatorio','confirmacion','cancelacion') NOT NULL,
  `destinatario` varchar(100) NOT NULL,
  `asunto` varchar(200) DEFAULT NULL,
  `estado` enum('enviado','fallido','pendiente') DEFAULT 'pendiente',
  `mensaje_error` text DEFAULT NULL,
  `fecha_envio` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones_email`
--

CREATE TABLE `notificaciones_email` (
  `id_notificacion` int(11) NOT NULL,
  `id_reservacion` int(11) DEFAULT NULL,
  `destinatario` varchar(255) NOT NULL,
  `asunto` varchar(255) NOT NULL,
  `cuerpo` text NOT NULL,
  `tipo` enum('confirmacion','recordatorio','asignacion','cancelacion','actualizacion') NOT NULL,
  `enviado` tinyint(1) DEFAULT 0,
  `fecha_programada` datetime NOT NULL,
  `fecha_enviado` datetime DEFAULT NULL,
  `intentos` int(11) DEFAULT 0,
  `error_mensaje` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `observaciones_guia`
--

CREATE TABLE `observaciones_guia` (
  `id_observacion` int(11) NOT NULL,
  `id_reservacion` int(11) NOT NULL,
  `id_guia` int(11) NOT NULL,
  `observacion` text NOT NULL,
  `fecha_observacion` datetime DEFAULT current_timestamp(),
  `leido_admin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paquetes`
--

CREATE TABLE `paquetes` (
  `id_paquete` int(11) NOT NULL,
  `nombre_paquete` varchar(150) NOT NULL,
  `descripcion_es` text DEFAULT NULL,
  `descripcion_en` text DEFAULT NULL,
  `descripcion_fr` text DEFAULT NULL,
  `duracion_horas` decimal(3,1) NOT NULL,
  `capacidad_maxima` int(11) NOT NULL DEFAULT 30,
  `personas_por_guia` int(11) NOT NULL DEFAULT 10,
  `precio_guia` decimal(10,2) NOT NULL,
  `precio_entrada_persona` decimal(10,2) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `imagen` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `paquetes`
--

INSERT INTO `paquetes` (`id_paquete`, `nombre_paquete`, `descripcion_es`, `descripcion_en`, `descripcion_fr`, `duracion_horas`, `capacidad_maxima`, `personas_por_guia`, `precio_guia`, `precio_entrada_persona`, `activo`, `fecha_creacion`, `fecha_actualizacion`, `imagen`) VALUES
(1, 'Cuevas de Unión Zapata', 'Recorrido a tres cuevas prehistóricas (Guilá Naquitz, La Paloma y Los Machines), concluyendo en el Centro Interpretativo de Unión Zapata.', 'Tour of three prehistoric caves (Guilá Naquitz, La Paloma and Los Machines), ending at the Unión Zapata Interpretive Center.', 'Visite de trois grottes préhistoriques (Guilá Naquitz, La Paloma et Los Machines), se terminant au Centre d\'Interprétation d\'Unión Zapata.', 3.0, 30, 10, 150.00, 200.00, 1, '2025-10-14 00:47:00', '2025-10-15 17:41:58', 'union-zapata.jpg'),
(2, 'Cuevas Prehistóricas de Mitla', 'Recorrido a las cuevas prehistóricas de Cueva Oscura, La Pintada y otras, con explicación arqueológica, cultural y natural.', 'Tour of the prehistoric caves of Cueva Oscura, La Pintada and others, with archaeological, cultural and natural explanation.', 'Visite des grottes préhistoriques de Cueva Oscura, La Pintada et autres, avec explication archéologique, culturelle et naturelle.', 3.0, 30, 10, 200.00, 150.00, 1, '2025-10-14 00:47:00', '2025-10-15 17:41:58', 'cuevas-mitla.jpg');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `participantes`
--

CREATE TABLE `participantes` (
  `id_participante` int(11) NOT NULL,
  `id_reservacion` int(11) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `edad` int(11) DEFAULT NULL,
  `alergias` text DEFAULT NULL,
  `condiciones_medicas` text DEFAULT NULL,
  `idioma` enum('español','ingles','frances','otro') DEFAULT 'español'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `participantes`
--

INSERT INTO `participantes` (`id_participante`, `id_reservacion`, `nombre_completo`, `edad`, `alergias`, `condiciones_medicas`, `idioma`) VALUES
(1, 1, 'Emmanuel', NULL, '', NULL, 'español'),
(2, 2, 'Emmanuel', NULL, '', NULL, 'español');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reembolsos`
--

CREATE TABLE `reembolsos` (
  `id_reembolso` int(11) NOT NULL,
  `id_reservacion` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `metodo` enum('efectivo','transferencia','tarjeta','otro') NOT NULL,
  `fecha_reembolso` datetime DEFAULT current_timestamp(),
  `procesado_por` int(11) NOT NULL,
  `notas` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reservaciones`
--

CREATE TABLE `reservaciones` (
  `id_reservacion` int(11) NOT NULL,
  `codigo_reservacion` varchar(20) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `id_paquete` int(11) NOT NULL,
  `fecha_tour` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `numero_personas` int(11) NOT NULL,
  `numero_guias_requeridos` int(11) NOT NULL,
  `idioma_tour` enum('español','ingles','frances') NOT NULL,
  `temperatura_prevista` decimal(4,1) DEFAULT NULL,
  `clima_descripcion` varchar(100) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `estado` enum('pendiente','confirmada','pagada','completada','cancelada') DEFAULT 'pendiente',
  `notas_admin` text DEFAULT NULL,
  `confirmacion_enviada` tinyint(1) DEFAULT 0,
  `metodo_pago` enum('visa','mastercard','efectivo') DEFAULT NULL,
  `referencia_pago` varchar(100) DEFAULT NULL,
  `fecha_pago` datetime DEFAULT NULL,
  `recordatorio_enviado` tinyint(1) DEFAULT 0,
  `ticket_enviado` tinyint(1) DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `reservaciones`
--

INSERT INTO `reservaciones` (`id_reservacion`, `codigo_reservacion`, `id_cliente`, `id_paquete`, `fecha_tour`, `hora_inicio`, `numero_personas`, `numero_guias_requeridos`, `idioma_tour`, `temperatura_prevista`, `clima_descripcion`, `subtotal`, `total`, `estado`, `notas_admin`, `confirmacion_enviada`, `metodo_pago`, `referencia_pago`, `fecha_pago`, `recordatorio_enviado`, `ticket_enviado`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'MT20251021-5B6F79', 1, 1, '2025-10-23', '09:00:00', 2, 2, 'español', NULL, NULL, 700.00, 700.00, 'pendiente', NULL, 0, NULL, NULL, NULL, 0, 0, '2025-10-21 15:55:17', '2025-10-21 15:55:17'),
(2, 'MT20251021-7A513C', 2, 2, '2025-10-23', '09:00:00', 4, 2, 'español', NULL, NULL, 1000.00, 1000.00, 'pendiente', NULL, 0, NULL, NULL, NULL, 0, 0, '2025-10-21 19:34:31', '2025-10-21 19:34:31');

--
-- Disparadores `reservaciones`
--
DELIMITER $$
CREATE TRIGGER `audit_reservaciones_update` AFTER UPDATE ON `reservaciones` FOR EACH ROW BEGIN
    IF @current_user_id IS NOT NULL THEN
        INSERT INTO audit_log (
            id_usuario, 
            accion, 
            tabla_afectada, 
            registro_id, 
            datos_anteriores, 
            datos_nuevos,
            ip
        ) VALUES (
            @current_user_id,
            'UPDATE',
            'reservaciones',
            NEW.id_reservacion,
            JSON_OBJECT(
                'estado', OLD.estado,
                'total', OLD.total,
                'numero_personas', OLD.numero_personas
            ),
            JSON_OBJECT(
                'estado', NEW.estado,
                'total', NEW.total,
                'numero_personas', NEW.numero_personas
            ),
            @current_ip
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('admin','guia') NOT NULL,
  `nombre_completo` varchar(255) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `ultimo_login` datetime DEFAULT NULL,
  `fecha_cambio_password` datetime DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `email`, `password_hash`, `rol`, `nombre_completo`, `activo`, `ultimo_login`, `fecha_cambio_password`, `fecha_creacion`) VALUES
(1, 'admin@mitlatours.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administrador del Sistema', 1, NULL, NULL, '2025-11-23 17:58:10');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios_sistema`
--

CREATE TABLE `usuarios_sistema` (
  `id_usuario` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `email` varchar(100) NOT NULL,
  `rol` enum('admin','operador','guia') DEFAULT 'operador',
  `activo` tinyint(1) DEFAULT 1,
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios_sistema`
--

INSERT INTO `usuarios_sistema` (`id_usuario`, `username`, `password_hash`, `nombre_completo`, `email`, `rol`, `activo`, `ultimo_acceso`, `fecha_creacion`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'admin@mitla.com', 'admin', 1, NULL, '2025-10-14 00:47:01');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_guias_completa`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_guias_completa` (
`id_guia` int(11)
,`id_usuario` int(11)
,`nombre_completo` varchar(255)
,`fecha_nacimiento` date
,`curp` varchar(18)
,`domicilio` text
,`telefono` varchar(15)
,`foto_perfil` varchar(255)
,`activo` tinyint(1)
,`fecha_registro` datetime
,`email` varchar(255)
,`usuario_activo` tinyint(1)
,`ultimo_login` datetime
,`idiomas` mediumtext
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_ocupacion_diaria`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_ocupacion_diaria` (
`fecha_tour` date
,`id_paquete` int(11)
,`nombre_paquete` varchar(150)
,`hora_inicio` time
,`capacidad_maxima` int(11)
,`lugares_ocupados` decimal(32,0)
,`lugares_disponibles` decimal(33,0)
,`porcentaje_ocupacion` decimal(38,2)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_reservaciones_completa`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_reservaciones_completa` (
`id_reservacion` int(11)
,`codigo_reservacion` varchar(20)
,`id_cliente` int(11)
,`id_paquete` int(11)
,`fecha_tour` date
,`hora_inicio` time
,`numero_personas` int(11)
,`numero_guias_requeridos` int(11)
,`idioma_tour` enum('español','ingles','frances')
,`temperatura_prevista` decimal(4,1)
,`clima_descripcion` varchar(100)
,`subtotal` decimal(10,2)
,`total` decimal(10,2)
,`estado` enum('pendiente','confirmada','pagada','completada','cancelada')
,`notas_admin` text
,`confirmacion_enviada` tinyint(1)
,`metodo_pago` enum('visa','mastercard','efectivo')
,`referencia_pago` varchar(100)
,`fecha_pago` datetime
,`recordatorio_enviado` tinyint(1)
,`ticket_enviado` tinyint(1)
,`fecha_creacion` timestamp
,`fecha_actualizacion` timestamp
,`cliente_nombre` varchar(150)
,`cliente_email` varchar(100)
,`cliente_telefono` varchar(20)
,`nombre_paquete` varchar(150)
,`duracion_horas` decimal(3,1)
,`guias_asignados` mediumtext
,`ids_guias` mediumtext
,`num_guias_asignados` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_guias_completa`
--
DROP TABLE IF EXISTS `vista_guias_completa`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_guias_completa`  AS SELECT `g`.`id_guia` AS `id_guia`, `g`.`id_usuario` AS `id_usuario`, `g`.`nombre_completo` AS `nombre_completo`, `g`.`fecha_nacimiento` AS `fecha_nacimiento`, `g`.`curp` AS `curp`, `g`.`domicilio` AS `domicilio`, `g`.`telefono` AS `telefono`, `g`.`foto_perfil` AS `foto_perfil`, `g`.`activo` AS `activo`, `g`.`fecha_registro` AS `fecha_registro`, `u`.`email` AS `email`, `u`.`activo` AS `usuario_activo`, `u`.`ultimo_login` AS `ultimo_login`, group_concat(distinct `gi`.`idioma` order by `gi`.`idioma` ASC separator ', ') AS `idiomas` FROM ((`guias` `g` join `usuarios` `u` on(`g`.`id_usuario` = `u`.`id_usuario`)) left join `guia_idiomas` `gi` on(`g`.`id_guia` = `gi`.`id_guia`)) GROUP BY `g`.`id_guia` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_ocupacion_diaria`
--
DROP TABLE IF EXISTS `vista_ocupacion_diaria`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_ocupacion_diaria`  AS SELECT `r`.`fecha_tour` AS `fecha_tour`, `r`.`id_paquete` AS `id_paquete`, `p`.`nombre_paquete` AS `nombre_paquete`, `r`.`hora_inicio` AS `hora_inicio`, `p`.`capacidad_maxima` AS `capacidad_maxima`, sum(`r`.`numero_personas`) AS `lugares_ocupados`, `p`.`capacidad_maxima`- sum(`r`.`numero_personas`) AS `lugares_disponibles`, round(sum(`r`.`numero_personas`) / `p`.`capacidad_maxima` * 100,2) AS `porcentaje_ocupacion` FROM (`reservaciones` `r` join `paquetes` `p` on(`r`.`id_paquete` = `p`.`id_paquete`)) WHERE `r`.`estado` in ('confirmada','pagada','pendiente') GROUP BY `r`.`fecha_tour`, `r`.`id_paquete`, `r`.`hora_inicio` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_reservaciones_completa`
--
DROP TABLE IF EXISTS `vista_reservaciones_completa`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_reservaciones_completa`  AS SELECT `r`.`id_reservacion` AS `id_reservacion`, `r`.`codigo_reservacion` AS `codigo_reservacion`, `r`.`id_cliente` AS `id_cliente`, `r`.`id_paquete` AS `id_paquete`, `r`.`fecha_tour` AS `fecha_tour`, `r`.`hora_inicio` AS `hora_inicio`, `r`.`numero_personas` AS `numero_personas`, `r`.`numero_guias_requeridos` AS `numero_guias_requeridos`, `r`.`idioma_tour` AS `idioma_tour`, `r`.`temperatura_prevista` AS `temperatura_prevista`, `r`.`clima_descripcion` AS `clima_descripcion`, `r`.`subtotal` AS `subtotal`, `r`.`total` AS `total`, `r`.`estado` AS `estado`, `r`.`notas_admin` AS `notas_admin`, `r`.`confirmacion_enviada` AS `confirmacion_enviada`, `r`.`metodo_pago` AS `metodo_pago`, `r`.`referencia_pago` AS `referencia_pago`, `r`.`fecha_pago` AS `fecha_pago`, `r`.`recordatorio_enviado` AS `recordatorio_enviado`, `r`.`ticket_enviado` AS `ticket_enviado`, `r`.`fecha_creacion` AS `fecha_creacion`, `r`.`fecha_actualizacion` AS `fecha_actualizacion`, `c`.`nombre_completo` AS `cliente_nombre`, `c`.`email` AS `cliente_email`, `c`.`telefono` AS `cliente_telefono`, `p`.`nombre_paquete` AS `nombre_paquete`, `p`.`duracion_horas` AS `duracion_horas`, group_concat(distinct `g`.`nombre_completo` separator ', ') AS `guias_asignados`, group_concat(distinct `g`.`id_guia` separator ',') AS `ids_guias`, count(distinct `ag`.`id_guia`) AS `num_guias_asignados` FROM ((((`reservaciones` `r` join `clientes` `c` on(`r`.`id_cliente` = `c`.`id_cliente`)) join `paquetes` `p` on(`r`.`id_paquete` = `p`.`id_paquete`)) left join `asignacion_guias` `ag` on(`r`.`id_reservacion` = `ag`.`id_reservacion`)) left join `guias` `g` on(`ag`.`id_guia` = `g`.`id_guia`)) GROUP BY `r`.`id_reservacion` ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `asignacion_guias`
--
ALTER TABLE `asignacion_guias`
  ADD PRIMARY KEY (`id_asignacion`),
  ADD UNIQUE KEY `unique_reservacion_guia` (`id_reservacion`,`id_guia`),
  ADD KEY `idx_reservacion` (`id_reservacion`),
  ADD KEY `idx_guia` (`id_guia`),
  ADD KEY `idx_fecha` (`fecha_asignacion`);

--
-- Indices de la tabla `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `idx_usuario` (`id_usuario`),
  ADD KEY `idx_fecha` (`fecha_accion`),
  ADD KEY `idx_tabla` (`tabla_afectada`),
  ADD KEY `idx_registro` (`tabla_afectada`,`registro_id`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id_cliente`),
  ADD KEY `idx_email` (`email`);

--
-- Indices de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`id_config`),
  ADD UNIQUE KEY `clave` (`clave`);

--
-- Indices de la tabla `configuracion_sistema`
--
ALTER TABLE `configuracion_sistema`
  ADD PRIMARY KEY (`id_config`),
  ADD UNIQUE KEY `clave` (`clave`),
  ADD KEY `modificado_por` (`modificado_por`),
  ADD KEY `idx_clave` (`clave`);

--
-- Indices de la tabla `disponibilidad_calendario`
--
ALTER TABLE `disponibilidad_calendario`
  ADD PRIMARY KEY (`id_disponibilidad`),
  ADD KEY `creado_por` (`creado_por`),
  ADD KEY `idx_paquete_fecha` (`id_paquete`,`fecha`),
  ADD KEY `idx_fecha` (`fecha`),
  ADD KEY `idx_activo` (`activo`);

--
-- Indices de la tabla `guias`
--
ALTER TABLE `guias`
  ADD PRIMARY KEY (`id_guia`),
  ADD UNIQUE KEY `curp` (`curp`),
  ADD KEY `idx_activo` (`activo`),
  ADD KEY `idx_usuario` (`id_usuario`);

--
-- Indices de la tabla `guia_comentarios`
--
ALTER TABLE `guia_comentarios`
  ADD PRIMARY KEY (`id_comentario`),
  ADD KEY `id_admin` (`id_admin`),
  ADD KEY `idx_guia` (`id_guia`),
  ADD KEY `idx_fecha` (`fecha_comentario`);

--
-- Indices de la tabla `guia_idiomas`
--
ALTER TABLE `guia_idiomas`
  ADD PRIMARY KEY (`id_guia_idioma`),
  ADD UNIQUE KEY `unique_guia_idioma` (`id_guia`,`idioma`),
  ADD KEY `idx_idioma` (`idioma`);

--
-- Indices de la tabla `historial_estados`
--
ALTER TABLE `historial_estados`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `cambiado_por` (`cambiado_por`),
  ADD KEY `idx_reservacion` (`id_reservacion`),
  ADD KEY `idx_fecha` (`fecha_cambio`);

--
-- Indices de la tabla `horarios`
--
ALTER TABLE `horarios`
  ADD PRIMARY KEY (`id_horario`),
  ADD KEY `id_paquete` (`id_paquete`),
  ADD KEY `idx_paquete_activo` (`id_paquete`,`activo`);

--
-- Indices de la tabla `idiomas_guias`
--
ALTER TABLE `idiomas_guias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_guia_idioma` (`id_guia`,`idioma`);

--
-- Indices de la tabla `intentos_login`
--
ALTER TABLE `intentos_login`
  ADD PRIMARY KEY (`id_intento`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_fecha` (`fecha_intento`);

--
-- Indices de la tabla `log_emails`
--
ALTER TABLE `log_emails`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `id_reservacion` (`id_reservacion`);

--
-- Indices de la tabla `notificaciones_email`
--
ALTER TABLE `notificaciones_email`
  ADD PRIMARY KEY (`id_notificacion`),
  ADD KEY `idx_enviado` (`enviado`),
  ADD KEY `idx_fecha_programada` (`fecha_programada`),
  ADD KEY `idx_reservacion` (`id_reservacion`);

--
-- Indices de la tabla `observaciones_guia`
--
ALTER TABLE `observaciones_guia`
  ADD PRIMARY KEY (`id_observacion`),
  ADD KEY `idx_reservacion` (`id_reservacion`),
  ADD KEY `idx_guia` (`id_guia`),
  ADD KEY `idx_leido` (`leido_admin`);

--
-- Indices de la tabla `paquetes`
--
ALTER TABLE `paquetes`
  ADD PRIMARY KEY (`id_paquete`),
  ADD KEY `idx_activo` (`activo`);

--
-- Indices de la tabla `participantes`
--
ALTER TABLE `participantes`
  ADD PRIMARY KEY (`id_participante`),
  ADD KEY `id_reservacion` (`id_reservacion`);

--
-- Indices de la tabla `reembolsos`
--
ALTER TABLE `reembolsos`
  ADD PRIMARY KEY (`id_reembolso`),
  ADD KEY `procesado_por` (`procesado_por`),
  ADD KEY `idx_reservacion` (`id_reservacion`),
  ADD KEY `idx_fecha` (`fecha_reembolso`);

--
-- Indices de la tabla `reservaciones`
--
ALTER TABLE `reservaciones`
  ADD PRIMARY KEY (`id_reservacion`),
  ADD UNIQUE KEY `codigo_reservacion` (`codigo_reservacion`),
  ADD KEY `id_cliente` (`id_cliente`),
  ADD KEY `id_paquete` (`id_paquete`),
  ADD KEY `idx_fecha_tour` (`fecha_tour`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_fecha_estado` (`fecha_tour`,`estado`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_rol` (`rol`),
  ADD KEY `idx_activo` (`activo`);

--
-- Indices de la tabla `usuarios_sistema`
--
ALTER TABLE `usuarios_sistema`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `asignacion_guias`
--
ALTER TABLE `asignacion_guias`
  MODIFY `id_asignacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id_cliente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  MODIFY `id_config` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `configuracion_sistema`
--
ALTER TABLE `configuracion_sistema`
  MODIFY `id_config` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `disponibilidad_calendario`
--
ALTER TABLE `disponibilidad_calendario`
  MODIFY `id_disponibilidad` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `guias`
--
ALTER TABLE `guias`
  MODIFY `id_guia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `guia_comentarios`
--
ALTER TABLE `guia_comentarios`
  MODIFY `id_comentario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `guia_idiomas`
--
ALTER TABLE `guia_idiomas`
  MODIFY `id_guia_idioma` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_estados`
--
ALTER TABLE `historial_estados`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `horarios`
--
ALTER TABLE `horarios`
  MODIFY `id_horario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de la tabla `idiomas_guias`
--
ALTER TABLE `idiomas_guias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `intentos_login`
--
ALTER TABLE `intentos_login`
  MODIFY `id_intento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `log_emails`
--
ALTER TABLE `log_emails`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notificaciones_email`
--
ALTER TABLE `notificaciones_email`
  MODIFY `id_notificacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `observaciones_guia`
--
ALTER TABLE `observaciones_guia`
  MODIFY `id_observacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `paquetes`
--
ALTER TABLE `paquetes`
  MODIFY `id_paquete` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `participantes`
--
ALTER TABLE `participantes`
  MODIFY `id_participante` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `reembolsos`
--
ALTER TABLE `reembolsos`
  MODIFY `id_reembolso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reservaciones`
--
ALTER TABLE `reservaciones`
  MODIFY `id_reservacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `usuarios_sistema`
--
ALTER TABLE `usuarios_sistema`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `asignacion_guias`
--
ALTER TABLE `asignacion_guias`
  ADD CONSTRAINT `asignacion_guias_ibfk_1` FOREIGN KEY (`id_reservacion`) REFERENCES `reservaciones` (`id_reservacion`) ON DELETE CASCADE,
  ADD CONSTRAINT `asignacion_guias_ibfk_2` FOREIGN KEY (`id_guia`) REFERENCES `guias` (`id_guia`) ON DELETE CASCADE;

--
-- Filtros para la tabla `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `configuracion_sistema`
--
ALTER TABLE `configuracion_sistema`
  ADD CONSTRAINT `configuracion_sistema_ibfk_1` FOREIGN KEY (`modificado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `disponibilidad_calendario`
--
ALTER TABLE `disponibilidad_calendario`
  ADD CONSTRAINT `disponibilidad_calendario_ibfk_1` FOREIGN KEY (`id_paquete`) REFERENCES `paquetes` (`id_paquete`) ON DELETE CASCADE,
  ADD CONSTRAINT `disponibilidad_calendario_ibfk_2` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `guias`
--
ALTER TABLE `guias`
  ADD CONSTRAINT `guias_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `guia_comentarios`
--
ALTER TABLE `guia_comentarios`
  ADD CONSTRAINT `guia_comentarios_ibfk_1` FOREIGN KEY (`id_guia`) REFERENCES `guias` (`id_guia`) ON DELETE CASCADE,
  ADD CONSTRAINT `guia_comentarios_ibfk_2` FOREIGN KEY (`id_admin`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `guia_idiomas`
--
ALTER TABLE `guia_idiomas`
  ADD CONSTRAINT `guia_idiomas_ibfk_1` FOREIGN KEY (`id_guia`) REFERENCES `guias` (`id_guia`) ON DELETE CASCADE;

--
-- Filtros para la tabla `historial_estados`
--
ALTER TABLE `historial_estados`
  ADD CONSTRAINT `historial_estados_ibfk_1` FOREIGN KEY (`id_reservacion`) REFERENCES `reservaciones` (`id_reservacion`) ON DELETE CASCADE,
  ADD CONSTRAINT `historial_estados_ibfk_2` FOREIGN KEY (`cambiado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `horarios`
--
ALTER TABLE `horarios`
  ADD CONSTRAINT `horarios_ibfk_1` FOREIGN KEY (`id_paquete`) REFERENCES `paquetes` (`id_paquete`) ON DELETE CASCADE;

--
-- Filtros para la tabla `idiomas_guias`
--
ALTER TABLE `idiomas_guias`
  ADD CONSTRAINT `idiomas_guias_ibfk_1` FOREIGN KEY (`id_guia`) REFERENCES `guias` (`id_guia`) ON DELETE CASCADE;

--
-- Filtros para la tabla `log_emails`
--
ALTER TABLE `log_emails`
  ADD CONSTRAINT `log_emails_ibfk_1` FOREIGN KEY (`id_reservacion`) REFERENCES `reservaciones` (`id_reservacion`) ON DELETE SET NULL;

--
-- Filtros para la tabla `notificaciones_email`
--
ALTER TABLE `notificaciones_email`
  ADD CONSTRAINT `notificaciones_email_ibfk_1` FOREIGN KEY (`id_reservacion`) REFERENCES `reservaciones` (`id_reservacion`) ON DELETE CASCADE;

--
-- Filtros para la tabla `observaciones_guia`
--
ALTER TABLE `observaciones_guia`
  ADD CONSTRAINT `observaciones_guia_ibfk_1` FOREIGN KEY (`id_reservacion`) REFERENCES `reservaciones` (`id_reservacion`) ON DELETE CASCADE,
  ADD CONSTRAINT `observaciones_guia_ibfk_2` FOREIGN KEY (`id_guia`) REFERENCES `guias` (`id_guia`) ON DELETE CASCADE;

--
-- Filtros para la tabla `participantes`
--
ALTER TABLE `participantes`
  ADD CONSTRAINT `participantes_ibfk_1` FOREIGN KEY (`id_reservacion`) REFERENCES `reservaciones` (`id_reservacion`) ON DELETE CASCADE;

--
-- Filtros para la tabla `reembolsos`
--
ALTER TABLE `reembolsos`
  ADD CONSTRAINT `reembolsos_ibfk_1` FOREIGN KEY (`id_reservacion`) REFERENCES `reservaciones` (`id_reservacion`) ON DELETE CASCADE,
  ADD CONSTRAINT `reembolsos_ibfk_2` FOREIGN KEY (`procesado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `reservaciones`
--
ALTER TABLE `reservaciones`
  ADD CONSTRAINT `reservaciones_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  ADD CONSTRAINT `reservaciones_ibfk_2` FOREIGN KEY (`id_paquete`) REFERENCES `paquetes` (`id_paquete`);

DELIMITER $$
--
-- Eventos
--
CREATE DEFINER=`root`@`localhost` EVENT `limpiar_intentos_login` ON SCHEDULE EVERY 1 DAY STARTS '2025-11-23 17:58:10' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM intentos_login 
    WHERE fecha_intento < DATE_SUB(NOW(), INTERVAL 30 DAY)$$

CREATE DEFINER=`root`@`localhost` EVENT `marcar_reservaciones_completadas` ON SCHEDULE EVERY 1 HOUR STARTS '2025-11-23 17:58:10' ON COMPLETION NOT PRESERVE ENABLE DO UPDATE reservaciones 
    SET estado = 'completada'
    WHERE estado IN ('confirmada', 'pagada')
    AND CONCAT(fecha_tour, ' ', hora_inicio) < DATE_SUB(NOW(), INTERVAL 3 HOUR)$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
