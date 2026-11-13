-- ============================================================
--  PIXELVAULT - Migracion: recordatorios de carrito + notificaciones
--  Para bases de datos YA creadas (no borra datos).
--  LEGACY: para una base existente, usa `php database/migrate_automation.php`.
--  Ese instalador es idempotente y prepara tambien la cola del worker PHP.
--  Conservado como referencia histórica; no lo ejecutes directamente.
--
--  Que hace:
--   1) Agrega carrito.agregado_el  -> marca de tiempo de la ultima
--      actividad en el carrito (sirve para medir el "1 minuto").
--   2) Crea la tabla notificacion  -> avisos del worker para el usuario
--      (campanita con contador de no leidas).
-- ============================================================
USE pixelvault;

ALTER TABLE carrito
  ADD COLUMN agregado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS notificacion (
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
) ENGINE=InnoDB;
