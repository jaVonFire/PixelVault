<?php
/* Migracion no destructiva e idempotente para bases PixelVault existentes. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../api/config/db.php';
$pdo = db();

function migration_column_exists($pdo, $table, $column) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute(array($table, $column));
    return (int)$stmt->fetchColumn() > 0;
}

function migration_add_column($pdo, $table, $column, $definition) {
    if (!migration_column_exists($pdo, $table, $column)) {
        $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
        return true;
    }
    return false;
}

$newInvoiceMarker = migration_add_column($pdo, 'pedido', 'facturado_el', 'DATETIME DEFAULT NULL');
$newReviewMarker = migration_add_column($pdo, 'pedido', 'resena_enviada', 'TINYINT(1) NOT NULL DEFAULT 0');
migration_add_column($pdo, 'usuario', 'puntos', 'INT NOT NULL DEFAULT 0');
migration_add_column($pdo, 'usuario', 'nivel', "VARCHAR(20) NOT NULL DEFAULT 'nuevo'");
migration_add_column($pdo, 'producto', 'alerta_stock_enviada', 'TINYINT(1) NOT NULL DEFAULT 0');
migration_add_column($pdo, 'carrito', 'agregado_el', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

/* Marca el historial previo como ya procesado para evitar facturas o puntos retroactivos. */
if ($newInvoiceMarker) {
    $pdo->exec('UPDATE pedido SET facturado_el=creado_el, resena_enviada=1');
} elseif ($newReviewMarker) {
    $pdo->exec('UPDATE pedido SET resena_enviada=1 WHERE facturado_el IS NOT NULL');
}

$pdo->exec("CREATE TABLE IF NOT EXISTS chat_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sesion VARCHAR(64) NOT NULL,
    usuario VARCHAR(50) DEFAULT NULL,
    lang CHAR(2) NOT NULL DEFAULT 'es',
    mensaje VARCHAR(500) NOT NULL,
    reply TEXT,
    fuente VARCHAR(20) NOT NULL DEFAULT 'gemini',
    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_chat_sesion (sesion),
    KEY idx_chat_fecha (creado_el)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS vision_solicitud (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    archivo_ruta VARCHAR(80) DEFAULT NULL,
    sha256 CHAR(64) NOT NULL,
    estado ENUM('procesando','coincidencia','sin_coincidencia','error','agregado') NOT NULL DEFAULT 'procesando',
    descripcion VARCHAR(500) DEFAULT NULL,
    confianza DECIMAL(6,5) DEFAULT NULL,
    resultado_json LONGTEXT DEFAULT NULL,
    modelo VARCHAR(100) DEFAULT NULL,
    error_codigo VARCHAR(50) DEFAULT NULL,
    producto_confirmado_id INT DEFAULT NULL,
    consentido_el DATETIME NOT NULL,
    creado_el DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizado_el DATETIME DEFAULT NULL,
    confirmado_el DATETIME DEFAULT NULL,
    KEY idx_vision_usuario_fecha (usuario_id, creado_el),
    KEY idx_vision_estado_fecha (estado, creado_el),
    CONSTRAINT fk_vision_usuario FOREIGN KEY (usuario_id)
      REFERENCES usuario(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_vision_producto FOREIGN KEY (producto_confirmado_id)
      REFERENCES producto(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS notificacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    titulo VARCHAR(120) NOT NULL,
    mensaje VARCHAR(255) NOT NULL,
    leida TINYINT(1) NOT NULL DEFAULT 0,
    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notif_usuario (usuario_id, leida),
    KEY idx_notif_fecha (creado_el),
    CONSTRAINT fk_notif_usuario FOREIGN KEY (usuario_id)
      REFERENCES usuario(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS cupon (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(40) NOT NULL UNIQUE,
    usuario_id INT NOT NULL,
    pedido_id INT DEFAULT NULL,
    descuento_pct INT NOT NULL,
    motivo VARCHAR(60) DEFAULT NULL,
    estado ENUM('activo','usado','expirado') NOT NULL DEFAULT 'activo',
    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expira_el DATETIME DEFAULT NULL,
    KEY idx_cupon_usuario (usuario_id, estado),
    CONSTRAINT fk_cupon_usuario FOREIGN KEY (usuario_id)
      REFERENCES usuario(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS automatizacion_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT DEFAULT NULL,
    usuario_id INT DEFAULT NULL,
    tipo VARCHAR(40) NOT NULL,
    detalle VARCHAR(255) DEFAULT NULL,
    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_autolog_pedido (pedido_id),
    KEY idx_autolog_fecha (creado_el)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS automatizacion_configuracion (
    clave VARCHAR(80) PRIMARY KEY,
    valor VARCHAR(255) NOT NULL,
    actualizado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS automatizacion_ejecucion (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    flujo VARCHAR(40) NOT NULL,
    estado ENUM('ejecutando','completado','error') NOT NULL DEFAULT 'ejecutando',
    entrada LONGTEXT DEFAULT NULL,
    salida LONGTEXT DEFAULT NULL,
    error TEXT DEFAULT NULL,
    iniciado_el DATETIME NOT NULL,
    finalizado_el DATETIME DEFAULT NULL,
    KEY idx_auto_run_flow_date (flujo, iniciado_el),
    KEY idx_auto_run_status (estado, iniciado_el)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS automatizacion_correo (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    flujo VARCHAR(40) NOT NULL,
    clave_unica VARCHAR(160) NOT NULL UNIQUE,
    referencia_id INT DEFAULT NULL,
    destinatario VARCHAR(254) NOT NULL,
    asunto VARCHAR(255) NOT NULL,
    html MEDIUMTEXT NOT NULL,
    estado ENUM('pendiente','enviando','enviado','fallido') NOT NULL DEFAULT 'pendiente',
    intentos INT NOT NULL DEFAULT 0,
    disponible_el DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    enviado_el DATETIME DEFAULT NULL,
    ultimo_error TEXT DEFAULT NULL,
    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_auto_mail_state (estado, disponible_el),
    KEY idx_auto_mail_ref (flujo, referencia_id),
    KEY idx_auto_mail_date (creado_el)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

fwrite(STDOUT, "Migracion lista. Los pedidos que ya existian quedaron marcados como procesados.\n");
fwrite(STDOUT, "Puedes iniciar el worker con: php cron/worker.php\n");
