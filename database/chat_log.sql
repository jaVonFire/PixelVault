-- ============================================================
--  PIXELVAULT - Migracion: tabla chat_log
--  Para bases de datos YA creadas (no borra datos).
--  Ejecutar en phpMyAdmin o: mysql -u root pixelvault < chat_log.sql
-- ============================================================
USE pixelvault;

CREATE TABLE IF NOT EXISTS chat_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sesion VARCHAR(64) NOT NULL,
  usuario VARCHAR(50) DEFAULT NULL,
  lang CHAR(2) NOT NULL DEFAULT 'es',
  mensaje VARCHAR(500) NOT NULL,
  reply TEXT,
  fuente VARCHAR(20) NOT NULL DEFAULT 'n8n',
  creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_chat_sesion (sesion),
  KEY idx_chat_fecha (creado_el)
) ENGINE=InnoDB;