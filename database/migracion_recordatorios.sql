-- ============================================================
--  PIXELVAULT - Migracion: recordatorios de carrito + notificaciones
--  Para bases de datos YA creadas (no borra datos).
--  Ejecutar en phpMyAdmin o: mysql -u root pixelvault < migracion_recordatorios.sql
--
--  Que hace:
--   1) Agrega carrito.agregado_el  -> marca de tiempo de la ultima
--      actividad en el carrito (sirve para medir el "1 minuto").
--   2) Crea la tabla notificacion  -> avisos del n8n para el usuario
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
