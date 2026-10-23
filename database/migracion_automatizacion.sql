-- ============================================================
--  PIXELVAULT - Migracion: automatizacion post-compra
--  Para bases de datos YA creadas (no borra datos).
--  Ejecutar en phpMyAdmin o: mysql -u root pixelvault < migracion_automatizacion.sql
--
--  Que hace (soporta la automatizacion de n8n "Post-Compra"):
--   1) pedido.facturado_el   -> marca de tiempo en que n8n proceso el
--      pedido (envio la factura + fidelidad). Sirve de candado para no
--      procesar dos veces y de "estado" del flujo.
--   2) pedido.resena_enviada -> 1 cuando n8n ya envio el correo de
--      seguimiento pidiendo resena.
--   3) usuario.puntos        -> puntos de fidelidad acumulados.
--   4) usuario.nivel         -> nivel de fidelidad (nuevo/plata/oro/platino).
--   5) cupon                 -> cupones generados automaticamente.
--   6) automatizacion_log    -> bitacora de lo que hizo la automatizacion.
-- ============================================================
USE pixelvault;

ALTER TABLE pedido
  ADD COLUMN facturado_el DATETIME DEFAULT NULL,
  ADD COLUMN resena_enviada TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE usuario
  ADD COLUMN puntos INT NOT NULL DEFAULT 0,
  ADD COLUMN nivel VARCHAR(20) NOT NULL DEFAULT 'nuevo';

CREATE TABLE IF NOT EXISTS cupon (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS automatizacion_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT DEFAULT NULL,
  usuario_id INT DEFAULT NULL,
  tipo VARCHAR(40) NOT NULL,
  detalle VARCHAR(255) DEFAULT NULL,
  creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_autolog_pedido (pedido_id),
  KEY idx_autolog_fecha (creado_el)
) ENGINE=InnoDB;

-- Los pedidos que ya existian ANTES de instalar la automatizacion no se
-- reprocesan: quedan marcados como facturados y sin resena pendiente.
-- (Los pedidos NUEVOS tendran facturado_el = NULL y resena_enviada = 0.)
UPDATE pedido SET facturado_el = creado_el WHERE facturado_el IS NULL;
UPDATE pedido SET resena_enviada = 1;
