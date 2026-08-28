-- ============================================================
--  PIXELVAULT - Base de datos de la aplicacion empresarial
--  Desarrollo de Aplicaciones Empresariales
--  Motor: MySQL / MariaDB (XAMPP + phpMyAdmin)
--  Codificacion: utf8mb4
-- ============================================================

DROP DATABASE IF EXISTS pixelvault;
CREATE DATABASE pixelvault CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pixelvault;

-- ============================================================
--  1. USUARIO
--  Roles: 'cliente' | 'admin'
--  password_hash: hash bcrypt generado con password_hash() en PHP
-- ============================================================
CREATE TABLE usuario (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  displayname VARCHAR(100) DEFAULT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('cliente','admin') NOT NULL DEFAULT 'cliente',
  pais VARCHAR(50) DEFAULT NULL,
  edad VARCHAR(20) DEFAULT NULL,
  plataforma VARCHAR(30) DEFAULT NULL,
  telefono VARCHAR(30) DEFAULT NULL,
  direccion VARCHAR(255) DEFAULT NULL,
  moneda CHAR(3) NOT NULL DEFAULT 'USD',
  creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
--  2. CATEGORIA
-- ============================================================
CREATE TABLE categoria (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL UNIQUE,
  activa TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ============================================================
--  3. PRODUCTO
--  estado: 'activo' | 'inactivo'
-- ============================================================
CREATE TABLE producto (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  descripcion TEXT,
  categoria_id INT NOT NULL,
  precio DECIMAL(10,2) NOT NULL,
  imagen VARCHAR(255) DEFAULT NULL,
  cantidad INT NOT NULL DEFAULT 0,
  stock_minimo INT NOT NULL DEFAULT 5,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_producto_categoria FOREIGN KEY (categoria_id)
    REFERENCES categoria(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
--  4. PEDIDO
--  estado: 'pendiente' | 'procesando' | 'enviado' | 'entregado' | 'cancelado'
-- ============================================================
CREATE TABLE pedido (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  codigo VARCHAR(20) NOT NULL UNIQUE,
  total DECIMAL(10,2) NOT NULL,
  estado ENUM('pendiente','procesando','enviado','entregado','cancelado')
    NOT NULL DEFAULT 'pendiente',
  creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pedido_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuario(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
--  5. DETALLE_PEDIDO
-- ============================================================
CREATE TABLE detalle_pedido (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  producto_id INT NOT NULL,
  nombre_producto VARCHAR(150) NOT NULL,
  precio DECIMAL(10,2) NOT NULL,
  cantidad INT NOT NULL,
  CONSTRAINT fk_detalle_pedido FOREIGN KEY (pedido_id)
    REFERENCES pedido(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_detalle_producto FOREIGN KEY (producto_id)
    REFERENCES producto(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
--  6. CARRITO  (carrito persistente por usuario)
-- ============================================================
CREATE TABLE carrito (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  producto_id INT NOT NULL,
  cantidad INT NOT NULL DEFAULT 1,
  UNIQUE KEY uq_carrito (usuario_id, producto_id),
  CONSTRAINT fk_carrito_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuario(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_carrito_producto FOREIGN KEY (producto_id)
    REFERENCES producto(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  DATOS INICIALES
-- ============================================================

-- Usuarios de ejemplo.
-- IMPORTANTE: los hashes corresponden a las contrasenas en texto plano:
--   admin    -> 'pixelvault2026'   (rol admin)
--   demo     -> 'demonio123'       (rol cliente)
--   kratosX  -> 'kratos2024'       (rol cliente)
-- (Generados con password_hash(), algoritmo bcrypt)
INSERT INTO usuario (username, displayname, email, password_hash, rol, pais, edad, plataforma, telefono, direccion, moneda) VALUES
('admin', 'Administrador', 'admin@pixelvault.gg',
 '$2y$10$jOoM7JkZj/DFLe7gPGB29usdC4H.dSOjht.ohej9taNVkwAcPsDAm', 'admin', 'Mexico', '25-34', 'PC', '+52 55 0000 0001', 'Av. Gamer 123, CDMX', 'MXN'),
('demo', 'Demo Cliente', 'demo@pixelvault.gg',
 '$2y$10$JLsguGCCEDL670B79pOlleK3VuVroV2vBsv8zS9hBpOL1sKTUejge', 'cliente', 'Colombia', '18-24', 'PlayStation', '+57 300 000 0001', 'Calle 1 #2-3, Bogota', 'COP'),
('kratosX', 'Kratos Fan', 'kratos@player.gg',
 '$2y$10$SzokyVmxXz7ts4Jeq2QR8ezOeamU.lSyVcZD2jwExnatZlzsGq4q6', 'cliente', 'Argentina', '25-34', 'PC', '+54 11 0000 0001', 'Av. Siempre Viva 742', 'ARS');

INSERT INTO categoria (nombre) VALUES
('juegos'),
('accesorios'),
('merchandising'),
('coleccionables');

INSERT INTO producto (nombre, descripcion, categoria_id, precio, imagen, cantidad, stock_minimo, estado) VALUES
('Cyberpunk 2077 Ed. Coleccionista', 'Edicion coleccionista con estatua, artbook y contenido descargable.', 1, 149.99, 'https://picsum.photos/seed/cyber77/400/300.jpg', 5, 3, 'activo'),
('Control Xbox Elite Series 2', 'Control profesional con paletas intercambiables y gatillos ajustables.', 2, 179.99, 'https://picsum.photos/seed/xboxctrl/400/300.jpg', 8, 3, 'activo'),
('Figura Funko Pop - Kratos', 'Figura coleccionable vinilo de Kratos de God of War.', 3, 24.99, 'https://picsum.photos/seed/kratosfunko/400/300.jpg', 15, 5, 'activo'),
('The Legend of Zelda: TotK', 'The Legend of Zelda: Tears of the Kingdom para Nintendo Switch.', 1, 59.99, 'https://picsum.photos/seed/zeldatotk/400/300.jpg', 3, 3, 'activo'),
('Headset HyperX Cloud III', 'Auriculares gaming con microfono y sonido envolvente 7.1.', 2, 99.99, 'https://picsum.photos/seed/hyperx3/400/300.jpg', 10, 4, 'activo'),
('Poster Elden Ring', 'Poster gigante oficial de Elden Ring, 90x60cm.', 3, 19.99, 'https://picsum.photos/seed/eldenpost/400/300.jpg', 20, 5, 'activo'),
('Teclado Razer BlackWidow', 'Teclado mecanico con switches verdes y RGB Chroma.', 2, 149.99, 'https://picsum.photos/seed/razerkb/400/300.jpg', 6, 4, 'activo'),
('Caja Misteriosa Retro', 'Caja sorpresa con juguetes y objetos retro de coleccion.', 4, 49.99, 'https://picsum.photos/seed/retrobox/400/300.jpg', 2, 3, 'activo'),
('Figura Master Chief Halo', 'Figura coleccionable de 25cm de Master Chief.', 4, 89.99, 'https://picsum.photos/seed/masterchief/400/300.jpg', 4, 3, 'activo'),
('GTA V Premium Edition', 'Grand Theft Auto V con edicion premium y contenido online.', 1, 39.99, 'https://picsum.photos/seed/gta5prem/400/300.jpg', 12, 4, 'activo'),
('Mouse Logitech G502 Hero', 'Mouse con sensor HERO 25K, 11 botones programables.', 2, 79.99, 'https://picsum.photos/seed/g502hero/400/300.jpg', 14, 4, 'activo'),
('Camiseta Assassin''s Creed', 'Camiseta oficial del logo de Assassin''s Creed.', 3, 29.99, 'https://picsum.photos/seed/acshirt/400/300.jpg', 18, 5, 'activo'),
('Replica Espada Minecraft', 'Replica de 40cm de espada de diamante de Minecraft.', 4, 59.99, 'https://picsum.photos/seed/mcswrd/400/300.jpg', 0, 3, 'inactivo'),
('God of War Ragnarok PS5', 'God of War Ragnarok para PlayStation 5.', 1, 69.99, 'https://picsum.photos/seed/gowps5/400/300.jpg', 7, 3, 'activo'),
('Silla Gamer RGB Pro', 'Silla ergonomica con soporte lumbar y luces RGB.', 2, 349.99, 'https://picsum.photos/seed/gamerchair/400/300.jpg', 3, 2, 'activo'),
('Taza Pac-Man Fantasma', 'Taza de ceramica con diseno de fantasma de Pac-Man.', 3, 14.99, 'https://picsum.photos/seed/pacmug/400/300.jpg', 25, 5, 'activo');

-- Un pedido de ejemplo para que el panel admin tenga datos que mostrar.
INSERT INTO pedido (usuario_id, codigo, total, estado, creado_el) VALUES
(2, 'PV-DEMO0001', 184.97, 'enviado', NOW() - INTERVAL 2 DAY);
INSERT INTO detalle_pedido (pedido_id, producto_id, nombre_producto, precio, cantidad) VALUES
(1, 3, 'Figura Funko Pop - Kratos', 24.99, 1),
(1, 5, 'Headset HyperX Cloud III', 99.99, 1),
(1, 6, 'Poster Elden Ring', 19.99, 3);
