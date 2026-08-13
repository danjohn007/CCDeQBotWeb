-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 13-08-2026 a las 11:40:47
-- Versión del servidor: 5.7.23-23
-- Versión de PHP: 8.1.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `crmcamar_allunay`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria`
--

CREATE TABLE `auditoria` (
  `id_auditoria` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` bigint(20) UNSIGNED DEFAULT NULL,
  `accion` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidad` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_entidad` bigint(20) UNSIGNED DEFAULT NULL,
  `descripcion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `datos_anteriores` json DEFAULT NULL,
  `datos_nuevos` json DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contactos`
--

CREATE TABLE `contactos` (
  `id_contacto` bigint(20) UNSIGNED NOT NULL,
  `whatsapp` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Numero con codigo de pais, solo digitos',
  `nombre_completo` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empresa` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_contacto` enum('lead','cliente_pendiente','cliente') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'lead',
  `etapa_comercial` enum('nuevo','informacion_solicitada','interesado','cotizacion_solicitada','cotizacion_enviada','seguimiento','compra_confirmada','no_interesado','cerrado','descartado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nuevo',
  `origen` enum('whatsapp','dashboard','importacion') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'whatsapp',
  `cliente_declarado` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'El usuario indico que ya es cliente, pendiente de validar',
  `id_asesor` bigint(20) UNSIGNED DEFAULT NULL,
  `proximo_seguimiento` datetime DEFAULT NULL,
  `primera_interaccion_en` datetime DEFAULT NULL,
  `ultima_interaccion_en` datetime DEFAULT NULL,
  `convertido_cliente_en` datetime DEFAULT NULL,
  `motivo_clasificacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas_generales` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contacto_etiquetas`
--

CREATE TABLE `contacto_etiquetas` (
  `id_contacto` bigint(20) UNSIGNED NOT NULL,
  `id_etiqueta` smallint(5) UNSIGNED NOT NULL,
  `id_asignado_por` bigint(20) UNSIGNED DEFAULT NULL,
  `asignada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conversaciones`
--

CREATE TABLE `conversaciones` (
  `id_conversacion` bigint(20) UNSIGNED NOT NULL,
  `id_contacto` bigint(20) UNSIGNED NOT NULL,
  `rama` enum('sin_definir','informacion','administracion','cotizacion') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sin_definir',
  `estado` enum('activa','finalizada','abandonada','error') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activa',
  `paso_inicial` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INICIO',
  `paso_final` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resultado` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `datos_resumen` json DEFAULT NULL,
  `iniciada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultima_actividad_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finalizada_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cotizaciones`
--

CREATE TABLE `cotizaciones` (
  `id_cotizacion` bigint(20) UNSIGNED NOT NULL,
  `folio` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_contacto` bigint(20) UNSIGNED NOT NULL,
  `id_conversacion` bigint(20) UNSIGNED DEFAULT NULL,
  `tipo_cliente_declarado` enum('primera_compra','cliente_existente','no_especificado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no_especificado',
  `correo_envio` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_entrega` enum('prioritaria','ordinaria','urgente') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `estado` enum('solicitada','en_revision','enviada','aceptada','rechazada','vencida','cancelada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'solicitada',
  `id_asesor` bigint(20) UNSIGNED DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `solicitada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `enviada_en` datetime DEFAULT NULL,
  `valida_hasta` datetime DEFAULT NULL,
  `aceptada_en` datetime DEFAULT NULL,
  `actualizada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cotizacion_archivos`
--

CREATE TABLE `cotizacion_archivos` (
  `id_archivo` int(10) UNSIGNED NOT NULL,
  `id_cotizacion` bigint(20) UNSIGNED NOT NULL,
  `archivo_nombre_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `archivo_nombre_guardado` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `archivo_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamano_bytes` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `subido_por` int(11) DEFAULT NULL,
  `subido_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cotizacion_detalles`
--

CREATE TABLE `cotizacion_detalles` (
  `id_detalle` bigint(20) UNSIGNED NOT NULL,
  `id_cotizacion` bigint(20) UNSIGNED NOT NULL,
  `id_producto` smallint(5) UNSIGNED DEFAULT NULL,
  `producto_nombre` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `producto_descripcion` text COLLATE utf8mb4_unicode_ci,
  `cantidad` int(10) UNSIGNED NOT NULL,
  `precio_unitario` decimal(12,2) NOT NULL,
  `importe` decimal(12,2) NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `etiquetas`
--

CREATE TABLE `etiquetas` (
  `id_etiqueta` smallint(5) UNSIGNED NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color_fondo` char(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#507B38',
  `color_texto` char(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#FFFFFF',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_estados`
--

CREATE TABLE `historial_estados` (
  `id_historial` bigint(20) UNSIGNED NOT NULL,
  `id_contacto` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` bigint(20) UNSIGNED DEFAULT NULL,
  `tipo_anterior` enum('lead','cliente_pendiente','cliente') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_nuevo` enum('lead','cliente_pendiente','cliente') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `etapa_anterior` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `etapa_nueva` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origen_cambio` enum('bot','dashboard','sistema') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dashboard',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `interacciones`
--

CREATE TABLE `interacciones` (
  `id_interaccion` bigint(20) UNSIGNED NOT NULL,
  `id_contacto` bigint(20) UNSIGNED NOT NULL,
  `id_conversacion` bigint(20) UNSIGNED DEFAULT NULL,
  `direccion` enum('entrante','saliente','sistema') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_mensaje` enum('texto','boton','interactivo','plantilla','sistema','otro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'texto',
  `meta_message_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paso_flujo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contenido` text COLLATE utf8mb4_unicode_ci,
  `payload_json` json DEFAULT NULL,
  `estado_envio` enum('recibido','enviado','entregado','leido','error') COLLATE utf8mb4_unicode_ci NOT NULL,
  `detalle_error` text COLLATE utf8mb4_unicode_ci,
  `registrado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notas_contacto`
--

CREATE TABLE `notas_contacto` (
  `id_nota` bigint(20) UNSIGNED NOT NULL,
  `id_contacto` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` bigint(20) UNSIGNED DEFAULT NULL,
  `nota` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `es_privada` tinyint(1) NOT NULL DEFAULT '0',
  `creada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos_servicios`
--

CREATE TABLE `productos_servicios` (
  `id_producto` smallint(5) UNSIGNED NOT NULL,
  `codigo_bot` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('producto','servicio') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'servicio',
  `categoria` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `precio` decimal(12,2) NOT NULL DEFAULT '0.00',
  `unidad` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requiere_afiliacion` tinyint(1) NOT NULL DEFAULT '0',
  `pago_en_linea` tinyint(1) NOT NULL DEFAULT '0',
  `link_pago` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  `archivado` tinyint(1) NOT NULL DEFAULT '0',
  `notas_flujo` text COLLATE utf8mb4_unicode_ci,
  `url_informacion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden_visualizacion` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sesiones_bot`
--

CREATE TABLE `sesiones_bot` (
  `whatsapp` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_contacto` bigint(20) UNSIGNED DEFAULT NULL,
  `id_conversacion` bigint(20) UNSIGNED DEFAULT NULL,
  `paso_actual` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INICIO',
  `datos` json DEFAULT NULL,
  `ultima_actividad_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expira_en` datetime DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes`
--

CREATE TABLE `solicitudes` (
  `id_solicitud` bigint(20) UNSIGNED NOT NULL,
  `folio` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_contacto` bigint(20) UNSIGNED NOT NULL,
  `id_conversacion` bigint(20) UNSIGNED DEFAULT NULL,
  `tipo_solicitud` enum('informacion','interes_compra','factura','contacto_administrativo','contacto_asesor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_producto` smallint(5) UNSIGNED DEFAULT NULL,
  `producto_nombre` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Copia historica del nombre mostrado por el bot',
  `cantidad` int(10) UNSIGNED DEFAULT NULL,
  `monto_estimado` decimal(12,2) DEFAULT NULL,
  `prioridad` enum('normal','alta','urgente') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `estado` enum('nueva','asignada','en_seguimiento','atendida','cerrada','cancelada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nueva',
  `id_asesor` bigint(20) UNSIGNED DEFAULT NULL,
  `detalle` text COLLATE utf8mb4_unicode_ci,
  `creada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cerrada_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios_sistema`
--

CREATE TABLE `usuarios_sistema` (
  `id_usuario` bigint(20) UNSIGNED NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidos` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` enum('superadministrador','administrador','asesor','consulta') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'asesor',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `intentos_fallidos` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `bloqueado_hasta` datetime DEFAULT NULL,
  `ultimo_acceso` datetime DEFAULT NULL,
  `foto_perfil` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id_venta` bigint(20) UNSIGNED NOT NULL,
  `folio` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_contacto` bigint(20) UNSIGNED NOT NULL,
  `id_cotizacion` bigint(20) UNSIGNED DEFAULT NULL,
  `monto_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `estado` enum('pendiente','confirmada','cancelada','reembolsada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `metodo_pago` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia_pago` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_compra` datetime DEFAULT NULL,
  `id_confirmado_por` bigint(20) UNSIGNED DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `creada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizada_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id_auditoria`),
  ADD KEY `idx_auditoria_usuario_fecha` (`id_usuario`,`creado_en`),
  ADD KEY `idx_auditoria_entidad_fecha` (`entidad`,`id_entidad`,`creado_en`);

--
-- Indices de la tabla `contactos`
--
ALTER TABLE `contactos`
  ADD PRIMARY KEY (`id_contacto`),
  ADD UNIQUE KEY `uq_contactos_whatsapp` (`whatsapp`),
  ADD KEY `idx_contactos_tipo_etapa` (`tipo_contacto`,`etapa_comercial`),
  ADD KEY `idx_contactos_ultima_interaccion` (`ultima_interaccion_en`),
  ADD KEY `idx_contactos_seguimiento` (`proximo_seguimiento`),
  ADD KEY `idx_contactos_asesor` (`id_asesor`);

--
-- Indices de la tabla `contacto_etiquetas`
--
ALTER TABLE `contacto_etiquetas`
  ADD PRIMARY KEY (`id_contacto`,`id_etiqueta`),
  ADD KEY `idx_contacto_etiquetas_etiqueta` (`id_etiqueta`),
  ADD KEY `idx_contacto_etiquetas_usuario` (`id_asignado_por`);

--
-- Indices de la tabla `conversaciones`
--
ALTER TABLE `conversaciones`
  ADD PRIMARY KEY (`id_conversacion`),
  ADD KEY `idx_conversaciones_contacto_fecha` (`id_contacto`,`iniciada_en`),
  ADD KEY `idx_conversaciones_estado_actividad` (`estado`,`ultima_actividad_en`);

--
-- Indices de la tabla `cotizaciones`
--
ALTER TABLE `cotizaciones`
  ADD PRIMARY KEY (`id_cotizacion`),
  ADD UNIQUE KEY `uq_cotizaciones_folio` (`folio`),
  ADD KEY `idx_cotizaciones_contacto_fecha` (`id_contacto`,`solicitada_en`),
  ADD KEY `idx_cotizaciones_estado_fecha` (`estado`,`solicitada_en`),
  ADD KEY `idx_cotizaciones_asesor_estado` (`id_asesor`,`estado`),
  ADD KEY `fk_cotizaciones_conversacion` (`id_conversacion`);

--
-- Indices de la tabla `cotizacion_archivos`
--
ALTER TABLE `cotizacion_archivos`
  ADD PRIMARY KEY (`id_archivo`),
  ADD KEY `idx_cotizacion_activo` (`id_cotizacion`,`activo`),
  ADD KEY `idx_subido_en` (`subido_en`);

--
-- Indices de la tabla `cotizacion_detalles`
--
ALTER TABLE `cotizacion_detalles`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `idx_cotizacion_detalles_cotizacion` (`id_cotizacion`),
  ADD KEY `idx_cotizacion_detalles_producto` (`id_producto`);

--
-- Indices de la tabla `etiquetas`
--
ALTER TABLE `etiquetas`
  ADD PRIMARY KEY (`id_etiqueta`),
  ADD UNIQUE KEY `uq_etiquetas_nombre` (`nombre`),
  ADD UNIQUE KEY `uq_etiquetas_slug` (`slug`);

--
-- Indices de la tabla `historial_estados`
--
ALTER TABLE `historial_estados`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `idx_historial_contacto_fecha` (`id_contacto`,`creado_en`),
  ADD KEY `idx_historial_usuario` (`id_usuario`);

--
-- Indices de la tabla `interacciones`
--
ALTER TABLE `interacciones`
  ADD PRIMARY KEY (`id_interaccion`),
  ADD UNIQUE KEY `uq_interacciones_meta_message` (`meta_message_id`),
  ADD KEY `idx_interacciones_contacto_fecha` (`id_contacto`,`registrado_en`),
  ADD KEY `idx_interacciones_conversacion_fecha` (`id_conversacion`,`registrado_en`),
  ADD KEY `idx_interacciones_direccion_fecha` (`direccion`,`registrado_en`);

--
-- Indices de la tabla `notas_contacto`
--
ALTER TABLE `notas_contacto`
  ADD PRIMARY KEY (`id_nota`),
  ADD KEY `idx_notas_contacto_fecha` (`id_contacto`,`creada_en`),
  ADD KEY `idx_notas_usuario` (`id_usuario`);

--
-- Indices de la tabla `productos_servicios`
--
ALTER TABLE `productos_servicios`
  ADD PRIMARY KEY (`id_producto`),
  ADD UNIQUE KEY `uq_productos_codigo_bot` (`codigo_bot`),
  ADD KEY `idx_productos_activo_orden` (`activo`,`orden_visualizacion`),
  ADD KEY `idx_visible` (`visible`),
  ADD KEY `idx_archivado` (`archivado`),
  ADD KEY `idx_tipo` (`tipo`);

--
-- Indices de la tabla `sesiones_bot`
--
ALTER TABLE `sesiones_bot`
  ADD PRIMARY KEY (`whatsapp`),
  ADD KEY `idx_sesiones_contacto` (`id_contacto`),
  ADD KEY `idx_sesiones_conversacion` (`id_conversacion`),
  ADD KEY `idx_sesiones_expira` (`expira_en`);

--
-- Indices de la tabla `solicitudes`
--
ALTER TABLE `solicitudes`
  ADD PRIMARY KEY (`id_solicitud`),
  ADD UNIQUE KEY `uq_solicitudes_folio` (`folio`),
  ADD KEY `idx_solicitudes_contacto_fecha` (`id_contacto`,`creada_en`),
  ADD KEY `idx_solicitudes_tipo_estado` (`tipo_solicitud`,`estado`),
  ADD KEY `idx_solicitudes_asesor_estado` (`id_asesor`,`estado`),
  ADD KEY `fk_solicitudes_conversacion` (`id_conversacion`),
  ADD KEY `fk_solicitudes_producto` (`id_producto`);

--
-- Indices de la tabla `usuarios_sistema`
--
ALTER TABLE `usuarios_sistema`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `uq_usuarios_correo` (`correo`),
  ADD KEY `idx_usuarios_rol_activo` (`rol`,`activo`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id_venta`),
  ADD UNIQUE KEY `uq_ventas_folio` (`folio`),
  ADD KEY `idx_ventas_contacto_fecha` (`id_contacto`,`fecha_compra`),
  ADD KEY `idx_ventas_estado_fecha` (`estado`,`fecha_compra`),
  ADD KEY `fk_ventas_cotizacion` (`id_cotizacion`),
  ADD KEY `fk_ventas_confirmado_por` (`id_confirmado_por`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `id_auditoria` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `contactos`
--
ALTER TABLE `contactos`
  MODIFY `id_contacto` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `conversaciones`
--
ALTER TABLE `conversaciones`
  MODIFY `id_conversacion` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cotizaciones`
--
ALTER TABLE `cotizaciones`
  MODIFY `id_cotizacion` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cotizacion_archivos`
--
ALTER TABLE `cotizacion_archivos`
  MODIFY `id_archivo` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cotizacion_detalles`
--
ALTER TABLE `cotizacion_detalles`
  MODIFY `id_detalle` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `etiquetas`
--
ALTER TABLE `etiquetas`
  MODIFY `id_etiqueta` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_estados`
--
ALTER TABLE `historial_estados`
  MODIFY `id_historial` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `interacciones`
--
ALTER TABLE `interacciones`
  MODIFY `id_interaccion` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notas_contacto`
--
ALTER TABLE `notas_contacto`
  MODIFY `id_nota` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `productos_servicios`
--
ALTER TABLE `productos_servicios`
  MODIFY `id_producto` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitudes`
--
ALTER TABLE `solicitudes`
  MODIFY `id_solicitud` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios_sistema`
--
ALTER TABLE `usuarios_sistema`
  MODIFY `id_usuario` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id_venta` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios_sistema` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `contactos`
--
ALTER TABLE `contactos`
  ADD CONSTRAINT `fk_contactos_asesor` FOREIGN KEY (`id_asesor`) REFERENCES `usuarios_sistema` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `contacto_etiquetas`
--
ALTER TABLE `contacto_etiquetas`
  ADD CONSTRAINT `fk_contacto_etiquetas_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_contacto_etiquetas_etiqueta` FOREIGN KEY (`id_etiqueta`) REFERENCES `etiquetas` (`id_etiqueta`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_contacto_etiquetas_usuario` FOREIGN KEY (`id_asignado_por`) REFERENCES `usuarios_sistema` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `conversaciones`
--
ALTER TABLE `conversaciones`
  ADD CONSTRAINT `fk_conversaciones_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `cotizaciones`
--
ALTER TABLE `cotizaciones`
  ADD CONSTRAINT `fk_cotizaciones_asesor` FOREIGN KEY (`id_asesor`) REFERENCES `usuarios_sistema` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cotizaciones_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cotizaciones_conversacion` FOREIGN KEY (`id_conversacion`) REFERENCES `conversaciones` (`id_conversacion`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `cotizacion_detalles`
--
ALTER TABLE `cotizacion_detalles`
  ADD CONSTRAINT `fk_cotizacion_detalles_cotizacion` FOREIGN KEY (`id_cotizacion`) REFERENCES `cotizaciones` (`id_cotizacion`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cotizacion_detalles_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos_servicios` (`id_producto`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `historial_estados`
--
ALTER TABLE `historial_estados`
  ADD CONSTRAINT `fk_historial_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historial_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios_sistema` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `interacciones`
--
ALTER TABLE `interacciones`
  ADD CONSTRAINT `fk_interacciones_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_interacciones_conversacion` FOREIGN KEY (`id_conversacion`) REFERENCES `conversaciones` (`id_conversacion`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `notas_contacto`
--
ALTER TABLE `notas_contacto`
  ADD CONSTRAINT `fk_notas_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notas_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios_sistema` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `sesiones_bot`
--
ALTER TABLE `sesiones_bot`
  ADD CONSTRAINT `fk_sesiones_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sesiones_conversacion` FOREIGN KEY (`id_conversacion`) REFERENCES `conversaciones` (`id_conversacion`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `solicitudes`
--
ALTER TABLE `solicitudes`
  ADD CONSTRAINT `fk_solicitudes_asesor` FOREIGN KEY (`id_asesor`) REFERENCES `usuarios_sistema` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_solicitudes_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_solicitudes_conversacion` FOREIGN KEY (`id_conversacion`) REFERENCES `conversaciones` (`id_conversacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_solicitudes_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos_servicios` (`id_producto`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `fk_ventas_confirmado_por` FOREIGN KEY (`id_confirmado_por`) REFERENCES `usuarios_sistema` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ventas_contacto` FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ventas_cotizacion` FOREIGN KEY (`id_cotizacion`) REFERENCES `cotizaciones` (`id_cotizacion`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
