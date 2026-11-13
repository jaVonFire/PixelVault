-- ============================================================
--  PIXELVAULT - Migracion: alerta de stock bajo
--  Para bases de datos YA creadas (no borra datos).
--  LEGACY: para una base existente, usa `php database/migrate_automation.php`.
--  Conservado como referencia histórica; no lo ejecutes directamente.
--
--  Que hace (soporta el flujo nativo de alerta de stock bajo):
--    producto.alerta_stock_enviada -> 0 = aun no se aviso al admin de que
--      este producto esta bajo; 1 = ya se aviso (para no repetir el correo
--      en cada ejecucion).
--
--  La automatizacion:
--    - Busca productos con cantidad <= stock_minimo y alerta_stock_enviada = 0.
--    - Si hay, avisa al admin por correo y los marca con 1.
--    - Cuando el producto se repone (cantidad > stock_minimo) los vuelve a
--      poner en 0, para que un nuevo bajon de stock vuelva a avisar.
-- ============================================================
USE pixelvault;

ALTER TABLE producto
  ADD COLUMN IF NOT EXISTS alerta_stock_enviada TINYINT(1) NOT NULL DEFAULT 0;
