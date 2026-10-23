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
  juego_favorito VARCHAR(100) DEFAULT NULL,
  telefono VARCHAR(30) DEFAULT NULL,
  direccion VARCHAR(255) DEFAULT NULL,
  moneda CHAR(3) NOT NULL DEFAULT 'USD',
  metodo_pago VARCHAR(40) DEFAULT NULL,
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
  imagen VARCHAR(500) DEFAULT NULL,
  cantidad INT NOT NULL DEFAULT 0,
  stock_minimo INT NOT NULL DEFAULT 5,
  alerta_stock_enviada TINYINT(1) NOT NULL DEFAULT 0,
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
  agregado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_carrito (usuario_id, producto_id),
  CONSTRAINT fk_carrito_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuario(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_carrito_producto FOREIGN KEY (producto_id)
    REFERENCES producto(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  7. CHAT_LOG  (bitacora del chatbot IA orquestado por n8n)
--  La escribe el n8n (nodo MySQL) en cada intercambio y
--  ademas le sirve de memoria conversacional a la IA.
--  sesion: identificador anonimo que envia api/controllers/chat.php
--  fuente: 'n8n' (respondio la IA) | 'guardarrail' | 'respaldo'
-- ============================================================
CREATE TABLE chat_log (
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

-- ============================================================
--  8. NOTIFICACION  (avisos generados por n8n para el usuario)
--  Las escribe el n8n (workflow "Recordatorio Carrito") cuando un
--  cliente deja productos sin comprar. El frontend las lee con
--  api/controllers/notificaciones.php y las muestra en la campanita.
--  tipo: 'carrito_1' (primer aviso) | 'carrito_2' (recordatorio final)
--  leida: 0 = no leida (cuenta en la campanita) | 1 = leida
-- ============================================================
CREATE TABLE notificacion (
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
('Cyberpunk 2077 Ed. Coleccionista', 'Edicion coleccionista con estatua, artbook y contenido descargable.', 1, 149.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/1091500/header.jpg&w=400&h=300&fit=cover&output=jpg', 5, 3, 'activo'),
('Control Xbox Elite Series 2', 'Control profesional con paletas intercambiables y gatillos ajustables.', 2, 179.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/c/c5/Xbox_One_Elite_Wireless_Controller_Series_2_(Model_1797).jpg&w=400&h=300&fit=cover&output=jpg', 8, 3, 'activo'),
('Figura Funko Pop - Kratos', 'Figura coleccionable vinilo de Kratos de God of War.', 3, 24.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/2/2f/Kratos_PS4.png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 15, 5, 'activo'),
('The Legend of Zelda: TotK', 'The Legend of Zelda: Tears of the Kingdom para Nintendo Switch.', 1, 59.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/f/fb/The_Legend_of_Zelda_Tears_of_the_Kingdom_cover.jpg%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 3, 3, 'activo'),
('Headset HyperX Cloud III', 'Auriculares gaming con microfono y sonido envolvente 7.1.', 2, 99.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/5602/15489257016_ec3863caee_b.jpg&w=400&h=300&fit=cover&output=jpg', 10, 4, 'activo'),
('Poster Elden Ring', 'Poster gigante oficial de Elden Ring, 90x60cm.', 3, 19.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/b/b9/Elden_Ring_Box_art.jpg%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 20, 5, 'activo'),
('Teclado Razer BlackWidow', 'Teclado mecanico con switches verdes y RGB Chroma.', 2, 149.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/f/fa/Green_mechanical_switch_Razer_keyboard_-_Levi_Loot.jpg&w=400&h=300&fit=cover&output=jpg', 6, 4, 'activo'),
('Caja Misteriosa Retro', 'Caja sorpresa con juguetes y objetos retro de coleccion.', 4, 49.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/3761/9499710490_4230faed2d.jpg&w=400&h=300&fit=cover&output=jpg', 2, 3, 'activo'),
('Figura Master Chief Halo', 'Figura coleccionable de 25cm de Master Chief.', 4, 89.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/4/42/Master_chief_halo_infinite.png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 4, 3, 'activo'),
('GTA V Premium Edition', 'Grand Theft Auto V con edicion premium y contenido online.', 1, 39.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/271590/header.jpg&w=400&h=300&fit=cover&output=jpg', 12, 4, 'activo'),
('Mouse Logitech G502 Hero', 'Mouse con sensor HERO 25K, 11 botones programables.', 2, 79.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/2925/14155686102_25a0a229ed.jpg&w=400&h=300&fit=cover&output=jpg', 14, 4, 'activo'),
('Camiseta Assassin''s Creed', 'Camiseta oficial del logo de Assassin''s Creed.', 3, 29.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/e/ee/E3_2010_Assassin''s_Creed_Brotherhood_at_the_Ubisoft_booth.jpg&w=400&h=300&fit=cover&output=jpg', 18, 5, 'activo'),
('Replica Espada Minecraft', 'Replica de 40cm de espada de diamante de Minecraft.', 4, 59.99, 'https://wsrv.nl/?url=https://media.sketchfab.com/models/dfaae49a4e3f4d1183a13ea1a5e1aaa5/thumbnails/deaffb97f4b54978b995acbdaeb36a0d/1dcbb0785ab9467aa3396fd8e293ced5.jpeg&w=400&h=300&fit=cover&output=jpg', 0, 3, 'inactivo'),
('God of War Ragnarok PS5', 'God of War Ragnarok para PlayStation 5.', 1, 69.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/2322010/header.jpg&w=400&h=300&fit=cover&output=jpg', 7, 3, 'activo'),
('Silla Gamer RGB Pro', 'Silla ergonomica con soporte lumbar y luces RGB.', 2, 349.99, 'https://wsrv.nl/?url=https://media.sketchfab.com/models/bcb13eb7ef9e4c3c9499aacff6c79757/thumbnails/6a475509aa4d41eeb4d45c152efc8ec2/dc3c07761a0c4cbaaf967a8904bed19a.jpeg&w=400&h=300&fit=cover&output=jpg', 3, 2, 'activo'),
('Taza Pac-Man Fantasma', 'Taza de ceramica con diseno de fantasma de Pac-Man.', 3, 14.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/c/ce/Girl_plays_Pac_Man.JPG&w=400&h=300&fit=cover&output=jpg', 25, 5, 'activo');

-- ============================================================
--  CATALOGO AMPLIADO - 127 productos adicionales
--  (juegos, accesorios, merchandising y coleccionables)
--  Imagenes: caratula real (Steam) o foto relacionada (Wikipedia/Openverse),
--  servidas y redimensionadas con el proxy wsrv.nl (400x300, cacheado).
-- ============================================================
INSERT INTO producto (nombre, descripcion, categoria_id, precio, imagen, cantidad, stock_minimo, estado) VALUES
('Elden Ring', 'Elden Ring. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 59.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/1245620/header.jpg&w=400&h=300&fit=cover&output=jpg', 200, 5, 'activo'),
('God of War Ragnarok', 'God of War Ragnarok. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 69.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/2322010/header.jpg&w=400&h=300&fit=cover&output=jpg', 80, 5, 'activo'),
('Spider-Man 2', 'Spider-Man 2. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 49.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/2651280/header.jpg&w=400&h=300&fit=cover&output=jpg', 180, 5, 'activo'),
('The Last of Us Part II', 'The Last of Us Part II. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 39.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/2531310/header.jpg&w=400&h=300&fit=cover&output=jpg', 150, 5, 'activo'),
('Horizon Forbidden West', 'Horizon Forbidden West. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 29.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/2420110/header.jpg&w=400&h=300&fit=cover&output=jpg', 90, 5, 'activo'),
('Ghost of Tsushima', 'Ghost of Tsushima. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 19.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/2215430/header.jpg&w=400&h=300&fit=cover&output=jpg', 60, 5, 'activo'),
('Bloodborne', 'Bloodborne. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 59.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/6/68/Bloodborne_Cover_Wallpaper.jpg%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 100, 5, 'activo'),
('Sekiro Shadows Die Twice', 'Sekiro Shadows Die Twice. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 69.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/814380/header.jpg&w=400&h=300&fit=cover&output=jpg', 120, 5, 'activo'),
('Dark Souls III', 'Dark Souls III. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 49.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/374320/header.jpg&w=400&h=300&fit=cover&output=jpg', 250, 5, 'activo'),
('Red Dead Redemption 2', 'Red Dead Redemption 2. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 39.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/1174180/header.jpg&w=400&h=300&fit=cover&output=jpg', 300, 5, 'activo'),
('The Witcher 3 Wild Hunt', 'The Witcher 3 Wild Hunt. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 29.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/292030/header.jpg&w=400&h=300&fit=cover&output=jpg', 200, 5, 'activo'),
('Cyberpunk 2077', 'Cyberpunk 2077. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 19.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/1091500/header.jpg&w=400&h=300&fit=cover&output=jpg', 80, 5, 'activo'),
('Baldurs Gate 3', 'Baldurs Gate 3. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 59.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/1086940/header.jpg&w=400&h=300&fit=cover&output=jpg', 180, 5, 'activo'),
('Starfield', 'Starfield. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 69.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/1716740/header.jpg&w=400&h=300&fit=cover&output=jpg', 150, 5, 'activo'),
('Hogwarts Legacy', 'Hogwarts Legacy. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 49.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/990080/header.jpg&w=400&h=300&fit=cover&output=jpg', 90, 5, 'activo'),
('Resident Evil 4 Remake', 'Resident Evil 4 Remake. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 39.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/2050650/header.jpg&w=400&h=300&fit=cover&output=jpg', 60, 5, 'activo'),
('Dead Space Remake', 'Dead Space Remake. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 29.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/1693980/header.jpg&w=400&h=300&fit=cover&output=jpg', 100, 5, 'activo'),
('Elden Ring Shadow of the Erdtree', 'Elden Ring Shadow of the Erdtree. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 19.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/2778580/header.jpg&w=400&h=300&fit=cover&output=jpg', 120, 5, 'activo'),
('Final Fantasy XVI', 'Final Fantasy XVI. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 59.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/2515020/header.jpg&w=400&h=300&fit=cover&output=jpg', 250, 5, 'activo'),
('Final Fantasy VII Remake', 'Final Fantasy VII Remake. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 69.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/1462040/header.jpg&w=400&h=300&fit=cover&output=jpg', 300, 5, 'activo'),
('Persona 5 Royal', 'Persona 5 Royal. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 49.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/1687950/header.jpg&w=400&h=300&fit=cover&output=jpg', 200, 5, 'activo'),
('Nier Automata', 'Nier Automata. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 39.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/524220/header.jpg&w=400&h=300&fit=cover&output=jpg', 80, 5, 'activo'),
('Minecraft', 'Minecraft. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 29.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/8688/16862316202_522f0414b8_b.jpg&w=400&h=300&fit=cover&output=jpg', 180, 5, 'activo'),
('Fortnite', 'Fortnite. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 19.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/826/41430778724_e5396f0cc1_b.jpg&w=400&h=300&fit=cover&output=jpg', 150, 5, 'activo'),
('Call of Duty Modern Warfare III', 'Call of Duty Modern Warfare III. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 59.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/2519060/header.jpg&w=400&h=300&fit=cover&output=jpg', 90, 5, 'activo'),
('Call of Duty Black Ops 6', 'Call of Duty Black Ops 6. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 69.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/2933620/header.jpg&w=400&h=300&fit=cover&output=jpg', 60, 5, 'activo'),
('Battlefield 2042', 'Battlefield 2042. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 49.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/1517290/header.jpg&w=400&h=300&fit=cover&output=jpg', 100, 5, 'activo'),
('Halo Infinite', 'Halo Infinite. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 39.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/1240440/header.jpg&w=400&h=300&fit=cover&output=jpg', 120, 5, 'activo'),
('Forza Horizon 5', 'Forza Horizon 5. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 29.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/1551360/header.jpg&w=400&h=300&fit=cover&output=jpg', 250, 5, 'activo'),
('Gran Turismo 7', 'Gran Turismo 7. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 19.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/1/14/Gran_Turismo_7_cover_art.jpg%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 300, 5, 'activo'),
('Mario Kart 8 Deluxe', 'Mario Kart 8 Deluxe. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 59.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/b/b5/MarioKart8Boxart.jpg%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 200, 5, 'activo'),
('Super Mario Odyssey', 'Super Mario Odyssey. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 69.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/8/8d/Super_Mario_Odyssey.jpg%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 80, 5, 'activo'),
('Zelda Breath of the Wild', 'Zelda Breath of the Wild. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 49.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/c/c6/The_Legend_of_Zelda_Breath_of_the_Wild.jpg%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 180, 5, 'activo'),
('Animal Crossing New Horizons', 'Animal Crossing New Horizons. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 39.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/1/1f/Animal_Crossing_New_Horizons.jpg%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 150, 5, 'activo'),
('Pokemon Scarlet', 'Pokemon Scarlet. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 29.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/2603/4001610651_a0145b3420.jpg&w=400&h=300&fit=cover&output=jpg', 90, 5, 'activo'),
('Pokemon Violet', 'Pokemon Violet. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 19.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/2603/4001610651_a0145b3420.jpg&w=400&h=300&fit=cover&output=jpg', 60, 5, 'activo'),
('Super Smash Bros Ultimate', 'Super Smash Bros Ultimate. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 59.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/5/50/Super_Smash_Bros._Ultimate.jpg%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 100, 5, 'activo'),
('Metroid Dread', 'Metroid Dread. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 69.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/f/f7/Metroid_Dread_Banner.png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 120, 5, 'activo'),
('Splatoon 3', 'Splatoon 3. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 49.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/4/4f/Splatoon.3.jpg%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 250, 5, 'activo'),
('Stardew Valley', 'Stardew Valley. Juego original para consola y PC, edicion fisica con caja y manual.', 1, 39.99, 'https://wsrv.nl/?url=https://cdn.cloudflare.steamstatic.com/steam/apps/413150/header.jpg&w=400&h=300&fit=cover&output=jpg', 300, 5, 'activo'),
('Control DualSense PS5', 'Control DualSense PS5. Accesorio gamer de alta calidad para completar tu setup.', 2, 79.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/3/3c/Playstation_DualSense_Controller.png&w=400&h=300&fit=cover&output=jpg', 120, 5, 'activo'),
('Control DualSense Edge', 'Control DualSense Edge. Accesorio gamer de alta calidad para completar tu setup.', 2, 149.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/3/3c/Playstation_DualSense_Controller.png&w=400&h=300&fit=cover&output=jpg', 250, 5, 'activo'),
('Control Xbox Series X', 'Control Xbox Series X. Accesorio gamer de alta calidad para completar tu setup.', 2, 99.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/8/80/Xbox_Wireless_Controller,_Model_1914_''Shock_Blue''.jpg&w=400&h=300&fit=cover&output=jpg', 300, 5, 'activo'),
('Control Nintendo Switch Pro', 'Control Nintendo Switch Pro. Accesorio gamer de alta calidad para completar tu setup.', 2, 199.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/9/9a/Nintendo_Switch_Pro_Controller_Zelda.jpg&w=400&h=300&fit=cover&output=jpg', 200, 5, 'activo'),
('Mando Xbox Elite Series 2 Core', 'Mando Xbox Elite Series 2 Core. Accesorio gamer de alta calidad para completar tu setup.', 2, 349.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/c/c5/Xbox_One_Elite_Wireless_Controller_Series_2_(Model_1797).jpg&w=400&h=300&fit=cover&output=jpg', 80, 5, 'activo'),
('Teclado Logitech G Pro X', 'Teclado Logitech G Pro X. Accesorio gamer de alta calidad para completar tu setup.', 2, 29.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/65535/49279967096_5eab73840f_b.jpg&w=400&h=300&fit=cover&output=jpg', 180, 5, 'activo'),
('Teclado Corsair K70 RGB', 'Teclado Corsair K70 RGB. Accesorio gamer de alta calidad para completar tu setup.', 2, 59.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/65535/49402373102_0b4de6d138_b.jpg&w=400&h=300&fit=cover&output=jpg', 150, 5, 'activo'),
('Teclado HyperX Alloy Origins', 'Teclado HyperX Alloy Origins. Accesorio gamer de alta calidad para completar tu setup.', 2, 24.99, 'https://wsrv.nl/?url=https://thumb.wikimedia.org/wikipedia/commons/thumb/f/f3/HP_Omen_logo.svg/120px-HP_Omen_logo.svg.png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail&w=400&h=300&fit=cover&output=jpg', 90, 5, 'activo'),
('Teclado SteelSeries Apex Pro', 'Teclado SteelSeries Apex Pro. Accesorio gamer de alta calidad para completar tu setup.', 2, 79.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/a/a1/Function_keys_on_Steelseries_Apex_5_Hebrew_keyboard.jpg&w=400&h=300&fit=cover&output=jpg', 60, 5, 'activo'),
('Mouse Razer DeathAdder V3', 'Mouse Razer DeathAdder V3. Accesorio gamer de alta calidad para completar tu setup.', 2, 149.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/1/19/Razer_DeathAdder_2013_Edition-front_oblique-ar_16to10-fs_PNr%C2%B00400.jpg&w=400&h=300&fit=cover&output=jpg', 100, 5, 'activo'),
('Mouse Logitech G Pro X Superlight', 'Mouse Logitech G Pro X Superlight. Accesorio gamer de alta calidad para completar tu setup.', 2, 99.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/2558/4184449780_b6ce674398_b.jpg&w=400&h=300&fit=cover&output=jpg', 120, 5, 'activo'),
('Mouse SteelSeries Rival 3', 'Mouse SteelSeries Rival 3. Accesorio gamer de alta calidad para completar tu setup.', 2, 199.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/1/1c/Rival_Mouse_by_SteelSeries,_FACEIT_(2018,_Cropped).jpg&w=400&h=300&fit=cover&output=jpg', 250, 5, 'activo'),
('Mousepad Razer Goliathus Extended', 'Mousepad Razer Goliathus Extended. Accesorio gamer de alta calidad para completar tu setup.', 2, 349.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/7/7a/Razer_Naga_Hex_Green_with_Razer_mousepad.jpg&w=400&h=300&fit=cover&output=jpg', 300, 5, 'activo'),
('Mousepad SteelSeries QcK XXL', 'Mousepad SteelSeries QcK XXL. Accesorio gamer de alta calidad para completar tu setup.', 2, 29.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/3016/2792794756_b3e1273514_b.jpg&w=400&h=300&fit=cover&output=jpg', 200, 5, 'activo'),
('Headset Razer BlackShark V2', 'Headset Razer BlackShark V2. Accesorio gamer de alta calidad para completar tu setup.', 2, 59.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/5689/22749702167_963daae836_b.jpg&w=400&h=300&fit=cover&output=jpg', 80, 5, 'activo'),
('Headset Logitech G Pro X', 'Headset Logitech G Pro X. Accesorio gamer de alta calidad para completar tu setup.', 2, 24.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/7447/10963478003_18c20b5e35_b.jpg&w=400&h=300&fit=cover&output=jpg', 180, 5, 'activo'),
('Headset SteelSeries Arctis Nova Pro', 'Headset SteelSeries Arctis Nova Pro. Accesorio gamer de alta calidad para completar tu setup.', 2, 79.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/65535/48209264466_eef50df162_b.jpg&w=400&h=300&fit=cover&output=jpg', 150, 5, 'activo'),
('Headset Astro A50', 'Headset Astro A50. Accesorio gamer de alta calidad para completar tu setup.', 2, 149.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/a/a5/ASTRO_Wiki_Image.png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 90, 5, 'activo'),
('Microfono HyperX QuadCast', 'Microfono HyperX QuadCast. Accesorio gamer de alta calidad para completar tu setup.', 2, 99.99, 'https://wsrv.nl/?url=https://thumb.wikimedia.org/wikipedia/commons/thumb/f/f3/HP_Omen_logo.svg/120px-HP_Omen_logo.svg.png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail&w=400&h=300&fit=cover&output=jpg', 60, 5, 'activo'),
('Microfono Blue Yeti', 'Microfono Blue Yeti. Accesorio gamer de alta calidad para completar tu setup.', 2, 199.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/185/457979032_0af84babeb_b.jpg&w=400&h=300&fit=cover&output=jpg', 100, 5, 'activo'),
('Webcam Logitech C920', 'Webcam Logitech C920. Accesorio gamer de alta calidad para completar tu setup.', 2, 349.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/5/55/Webcam_01.jpg&w=400&h=300&fit=cover&output=jpg', 120, 5, 'activo'),
('Webcam Razer Kiyo Pro', 'Webcam Razer Kiyo Pro. Accesorio gamer de alta calidad para completar tu setup.', 2, 29.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/2251/2400508507_08e689705b.jpg&w=400&h=300&fit=cover&output=jpg', 250, 5, 'activo'),
('Silla DXRacer Master', 'Silla DXRacer Master. Accesorio gamer de alta calidad para completar tu setup.', 2, 59.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/3/3e/DXRacer_gaming_chairs_at_E3_2017.jpg&w=400&h=300&fit=cover&output=jpg', 300, 5, 'activo'),
('Silla Secretlab Titan Evo', 'Silla Secretlab Titan Evo. Accesorio gamer de alta calidad para completar tu setup.', 2, 24.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/5301/5895210427_e912680d46_b.jpg&w=400&h=300&fit=cover&output=jpg', 200, 5, 'activo'),
('Escritorio Gamer RGB', 'Escritorio Gamer RGB. Accesorio gamer de alta calidad para completar tu setup.', 2, 79.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/3610/3430258924_29aeaaa3a8_b.jpg&w=400&h=300&fit=cover&output=jpg', 80, 5, 'activo'),
('Monitor Samsung Odyssey G7', 'Monitor Samsung Odyssey G7. Accesorio gamer de alta calidad para completar tu setup.', 2, 149.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/3293/3126952638_de8f1c04d0_b.jpg&w=400&h=300&fit=cover&output=jpg', 180, 5, 'activo'),
('Monitor LG UltraGear 27', 'Monitor LG UltraGear 27. Accesorio gamer de alta calidad para completar tu setup.', 2, 99.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/3293/3126952638_de8f1c04d0_b.jpg&w=400&h=300&fit=cover&output=jpg', 150, 5, 'activo'),
('Volante Logitech G920', 'Volante Logitech G920. Accesorio gamer de alta calidad para completar tu setup.', 2, 199.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/2127/1497389602_af474a9942_b.jpg&w=400&h=300&fit=cover&output=jpg', 90, 5, 'activo'),
('Volante Thrustmaster T300', 'Volante Thrustmaster T300. Accesorio gamer de alta calidad para completar tu setup.', 2, 349.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/b/b3/Thrustmaster_TX_Racing_Wheel_Leather_edition_-_front.jpg&w=400&h=300&fit=cover&output=jpg', 60, 5, 'activo'),
('Pedales Thrustmaster T-LCM', 'Pedales Thrustmaster T-LCM. Accesorio gamer de alta calidad para completar tu setup.', 2, 29.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/a/ab/Thrustmaster_F-T2.jpg%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 100, 5, 'activo'),
('Gamepad 8BitDo Pro 2', 'Gamepad 8BitDo Pro 2. Accesorio gamer de alta calidad para completar tu setup.', 2, 59.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/f/f5/8BitDo_M30.png&w=400&h=300&fit=cover&output=jpg', 120, 5, 'activo'),
('Gamepad Backbone One', 'Gamepad Backbone One. Accesorio gamer de alta calidad para completar tu setup.', 2, 24.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/3167/2295802559_49c7338099_b.jpg&w=400&h=300&fit=cover&output=jpg', 250, 5, 'activo'),
('Auriculares Sony Pulse 3D', 'Auriculares Sony Pulse 3D. Accesorio gamer de alta calidad para completar tu setup.', 2, 79.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/7230/6941242756_9e1f5ebc4d_b.jpg&w=400&h=300&fit=cover&output=jpg', 300, 5, 'activo'),
('Bateria Xbox Play and Charge', 'Bateria Xbox Play and Charge. Accesorio gamer de alta calidad para completar tu setup.', 2, 149.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/8356/8339705719_07c4e8b14d_b.jpg&w=400&h=300&fit=cover&output=jpg', 200, 5, 'activo'),
('Base de carga DualSense', 'Base de carga DualSense. Accesorio gamer de alta calidad para completar tu setup.', 2, 99.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/4/4e/PlayStation_Move_-_Base.jpg&w=400&h=300&fit=cover&output=jpg', 80, 5, 'activo'),
('Camiseta The Witcher', 'Camiseta The Witcher. Merchandising oficial inspirado en el universo gamer.', 3, 24.99, 'https://wsrv.nl/?url=https://thumb.wikimedia.org/wikipedia/commons/thumb/4/4e/Kolekcjonerskie_wydanie_sagi_o_wied%C5%BAminie.jpg/3840px-Kolekcjonerskie_wydanie_sagi_o_wied%C5%BAminie.jpg%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail&w=400&h=300&fit=cover&output=jpg', 90, 5, 'activo'),
('Camiseta Cyberpunk 2077', 'Camiseta Cyberpunk 2077. Merchandising oficial inspirado en el universo gamer.', 3, 29.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/9/9f/Cyberpunk_2077_box_art.jpg%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 60, 5, 'activo'),
('Camiseta Elden Ring', 'Camiseta Elden Ring. Merchandising oficial inspirado en el universo gamer.', 3, 19.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/b/b9/Elden_Ring_Box_art.jpg%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 100, 5, 'activo'),
('Sudadera Gamer Pixel', 'Sudadera Gamer Pixel. Merchandising oficial inspirado en el universo gamer.', 3, 14.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/3860/15023141998_647297aa8a_b.jpg&w=400&h=300&fit=cover&output=jpg', 120, 5, 'activo'),
('Hoodie Nintendo', 'Hoodie Nintendo. Merchandising oficial inspirado en el universo gamer.', 3, 34.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/b/b0/Nintendo_Switch_Lite_representation.png&w=400&h=300&fit=cover&output=jpg', 250, 5, 'activo'),
('Gorra PlayStation', 'Gorra PlayStation. Merchandising oficial inspirado en el universo gamer.', 3, 12.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/8469/8442851109_88c99549ec_b.jpg&w=400&h=300&fit=cover&output=jpg', 300, 5, 'activo'),
('Gorra Xbox', 'Gorra Xbox. Merchandising oficial inspirado en el universo gamer.', 3, 24.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/7/75/XBOX_logo_(2026).png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 200, 5, 'activo'),
('Taza Zelda', 'Taza Zelda. Merchandising oficial inspirado en el universo gamer.', 3, 29.99, 'https://wsrv.nl/?url=https://thumb.wikimedia.org/wikipedia/commons/thumb/5/5b/Zelda_2017.svg/960px-Zelda_2017.svg.png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail&w=400&h=300&fit=cover&output=jpg', 80, 5, 'activo'),
('Taza Pokemon', 'Taza Pokemon. Merchandising oficial inspirado en el universo gamer.', 3, 19.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/7409/11183496596_a4cb460b5e.jpg&w=400&h=300&fit=cover&output=jpg', 180, 5, 'activo'),
('Taza Minecraft Creeper', 'Taza Minecraft Creeper. Merchandising oficial inspirado en el universo gamer.', 3, 14.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/5/5b/Minecraft_Creeper.png&w=400&h=300&fit=cover&output=jpg', 150, 5, 'activo'),
('Botella Termica Gaming', 'Botella Termica Gaming. Merchandising oficial inspirado en el universo gamer.', 3, 34.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/4063/4715448785_14a4b7e379_b.jpg&w=400&h=300&fit=cover&output=jpg', 90, 5, 'activo'),
('Mochila Gamer', 'Mochila Gamer. Merchandising oficial inspirado en el universo gamer.', 3, 12.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/8426/7658317110_9dd7ed324e_b.jpg&w=400&h=300&fit=cover&output=jpg', 60, 5, 'activo'),
('Mochila Portatil Nintendo', 'Mochila Portatil Nintendo. Merchandising oficial inspirado en el universo gamer.', 3, 24.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/6/63/Innenleben_der_Nintendo_Switch_20230405_HOF09139_RAW-Export.png&w=400&h=300&fit=cover&output=jpg', 100, 5, 'activo'),
('Llavero Kratos', 'Llavero Kratos. Merchandising oficial inspirado en el universo gamer.', 3, 29.99, 'https://wsrv.nl/?url=https://media.sketchfab.com/models/dc80c2582d3446aab1cff9f4055e04ec/thumbnails/4f15c47285b34c93bbf6d562a5beb924/af012f86d5df4b8abe916ffb7e1dd3a9.jpeg&w=400&h=300&fit=cover&output=jpg', 120, 5, 'activo'),
('Llavero Master Chief', 'Llavero Master Chief. Merchandising oficial inspirado en el universo gamer.', 3, 19.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/4/42/Master_chief_halo_infinite.png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 250, 5, 'activo'),
('Llavero Pikachu', 'Llavero Pikachu. Merchandising oficial inspirado en el universo gamer.', 3, 14.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/b/b0/Pikachu_Parade_(14905092432).jpg&w=400&h=300&fit=cover&output=jpg', 300, 5, 'activo'),
('Calcetines Gamer Pack', 'Calcetines Gamer Pack. Merchandising oficial inspirado en el universo gamer.', 3, 34.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/3119/2860580883_36c5cafdc8_b.jpg&w=400&h=300&fit=cover&output=jpg', 200, 5, 'activo'),
('Cuaderno Gamer', 'Cuaderno Gamer. Merchandising oficial inspirado en el universo gamer.', 3, 12.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/5341/10188453483_b80a4bf1b0_b.jpg&w=400&h=300&fit=cover&output=jpg', 80, 5, 'activo'),
('Poster Skyrim', 'Poster Skyrim. Merchandising oficial inspirado en el universo gamer.', 3, 24.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/b/b1/Mapa_Skyrim.png&w=400&h=300&fit=cover&output=jpg', 180, 5, 'activo'),
('Poster Dark Souls', 'Poster Dark Souls. Merchandising oficial inspirado en el universo gamer.', 3, 29.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/8/8d/Dark_Souls_Cover_Art.jpg%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 150, 5, 'activo'),
('Poster Halo', 'Poster Halo. Merchandising oficial inspirado en el universo gamer.', 3, 19.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/65535/52332896293_b5953a6b56_b.jpg&w=400&h=300&fit=cover&output=jpg', 90, 5, 'activo'),
('Vinilo Banda Sonora Undertale', 'Vinilo Banda Sonora Undertale. Merchandising oficial inspirado en el universo gamer.', 3, 14.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/d/d1/Time-coded_vinyl_record.jpg&w=400&h=300&fit=cover&output=jpg', 60, 5, 'activo'),
('Vinilo Banda Sonora Celeste', 'Vinilo Banda Sonora Celeste. Merchandising oficial inspirado en el universo gamer.', 3, 34.99, 'https://wsrv.nl/?url=https://coimages.sciencemuseumgroup.org.uk/205/57/large_cd0611_001_100216_2005_86_25_bbc_manchester_vinyl_record_manchester_carols.jpg&w=400&h=300&fit=cover&output=jpg', 100, 5, 'activo'),
('Mousepad XL Neon', 'Mousepad XL Neon. Merchandising oficial inspirado en el universo gamer.', 3, 12.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/4082/4776723533_e434ffc277_m.jpg&w=400&h=300&fit=cover&output=jpg', 120, 5, 'activo'),
('Almohada Gamer', 'Almohada Gamer. Merchandising oficial inspirado en el universo gamer.', 3, 24.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/d/dd/Throw_Pillows_(Scatter_Cushions).jpg&w=400&h=300&fit=cover&output=jpg', 250, 5, 'activo'),
('Manta Gamer', 'Manta Gamer. Merchandising oficial inspirado en el universo gamer.', 3, 29.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/65535/49820901073_6ee2c40067_b.jpg&w=400&h=300&fit=cover&output=jpg', 300, 5, 'activo'),
('Figura Darth Vader', 'Figura Darth Vader. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 24.99, 'https://wsrv.nl/?url=https://images.rawpixel.com/editor_1024/cHJpdmF0ZS9zdGF0aWMvaW1hZ2Uvd2Vic2l0ZS8yMDIyLTA0L2xyL3B4Njg5MjcyLWltYWdlLWt6MmViNTk2LmpwZw.jpg&w=400&h=300&fit=cover&output=jpg', 80, 5, 'activo'),
('Figura Yoda', 'Figura Yoda. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 49.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/9/9b/Yoda_Empire_Strikes_Back.png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 180, 5, 'activo'),
('Figura Mandalorian', 'Figura Mandalorian. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 89.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/a/a1/TheMandalorian.jpg%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 150, 5, 'activo'),
('Figura Spider-Man', 'Figura Spider-Man. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 129.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/2505/3751659340_533d1a7089_b.jpg&w=400&h=300&fit=cover&output=jpg', 90, 5, 'activo'),
('Figura Iron Man', 'Figura Iron Man. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 34.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/4/47/Iron_Man_(circa_2018).png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 60, 5, 'activo'),
('Figura Batman', 'Figura Batman. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 64.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/7102/7394479306_39ce24938c_b.jpg&w=400&h=300&fit=cover&output=jpg', 100, 5, 'activo'),
('Figura Wonder Woman', 'Figura Wonder Woman. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 24.99, 'https://wsrv.nl/?url=https://images.rawpixel.com/editor_1024/cHJpdmF0ZS9zdGF0aWMvaW1hZ2Uvd2Vic2l0ZS8yMDIyLTA0L2xyL3B4MTA2MTQ0MS1pbWFnZS1rejJlOW43Ni5qcGc.jpg&w=400&h=300&fit=cover&output=jpg', 120, 5, 'activo'),
('Figura Goku Super Saiyan', 'Figura Goku Super Saiyan. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 49.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/4/4c/GokumangaToriyama.png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 250, 5, 'activo'),
('Figura Vegeta', 'Figura Vegeta. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 89.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/8/88/Vegeta_Dragon_Ball.jpg%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 300, 5, 'activo'),
('Figura Naruto', 'Figura Naruto. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 129.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/9/9a/NarutoUzumaki.png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 200, 5, 'activo'),
('Figura Sasuke', 'Figura Sasuke. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 34.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/38/76298482_5f750e9acb.jpg&w=400&h=300&fit=cover&output=jpg', 80, 5, 'activo'),
('Figura Luffy', 'Figura Luffy. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 64.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/3238/2871314532_e4d3c7b041_b.jpg&w=400&h=300&fit=cover&output=jpg', 180, 5, 'activo'),
('Figura Goku Ultra Instinct', 'Figura Goku Ultra Instinct. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 24.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/4/4c/GokumangaToriyama.png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 150, 5, 'activo'),
('Figura Geralt de Rivia', 'Figura Geralt de Rivia. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 49.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/8/87/Geralt.jpg%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 90, 5, 'activo'),
('Figura Cloud Strife', 'Figura Cloud Strife. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 89.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/2/2e/Cosplayer_of_Cloud_Strife,_Final_Fantasy_VII_at_CH1_20160220.jpg&w=400&h=300&fit=cover&output=jpg', 60, 5, 'activo'),
('Figura Sephiroth', 'Figura Sephiroth. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 129.99, 'https://wsrv.nl/?url=https://media.sketchfab.com/models/66b877fa049940b0bbe68c91dc2e4d26/thumbnails/0f519c4cf9114e2c800074b9faf867cd/2772a5e474384bf09d19acbd8f6a86c1.jpeg&w=400&h=300&fit=cover&output=jpg', 100, 5, 'activo'),
('Figura Sonic', 'Figura Sonic. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 34.99, 'https://wsrv.nl/?url=https://thumb.wikimedia.org/wikipedia/commons/thumb/1/1f/Sonic_The_Hedgehog.svg/1280px-Sonic_The_Hedgehog.svg.png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail&w=400&h=300&fit=cover&output=jpg', 120, 5, 'activo'),
('Figura Mario', 'Figura Mario. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 64.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/5/5c/Mario_by_Shigehisa_Nakaue.png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 250, 5, 'activo'),
('Figura Link', 'Figura Link. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 24.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/9/9d/Link_(Hyrule_Historia).png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 300, 5, 'activo'),
('Figura Samus Aran', 'Figura Samus Aran. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 49.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/d/d6/Samus_Aran.png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 200, 5, 'activo'),
('Funko Pop Doom Slayer', 'Funko Pop Doom Slayer. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 89.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/f/fc/Doom_Slayer.png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 80, 5, 'activo'),
('Funko Pop Master Chief', 'Funko Pop Master Chief. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 129.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/4/42/Master_chief_halo_infinite.png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 180, 5, 'activo'),
('Amiibo Link', 'Amiibo Link. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 34.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/9/9d/Link_(Hyrule_Historia).png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 150, 5, 'activo'),
('Amiibo Mario', 'Amiibo Mario. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 64.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/en/5/5c/Mario_by_Shigehisa_Nakaue.png%3Futm_source=en.wikipedia.org%26utm_campaign=api%26utm_content=thumbnail_unscaled&w=400&h=300&fit=cover&output=jpg', 90, 5, 'activo'),
('Replica Pokedex', 'Replica Pokedex. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 24.99, 'https://wsrv.nl/?url=https://upload.wikimedia.org/wikipedia/commons/8/82/Pokedex-German.jpg&w=400&h=300&fit=cover&output=jpg', 60, 5, 'activo'),
('Replica Espada Master Sword', 'Replica Espada Master Sword. Figura coleccionable detallada, ideal para exhibir en tu vitrina.', 4, 49.99, 'https://wsrv.nl/?url=https://live.staticflickr.com/7035/6463446793_e9f697a60b_b.jpg&w=400&h=300&fit=cover&output=jpg', 100, 5, 'activo');

-- Un pedido de ejemplo para que el panel admin tenga datos que mostrar.
INSERT INTO pedido (usuario_id, codigo, total, estado, creado_el) VALUES
(2, 'PV-DEMO0001', 184.97, 'enviado', NOW() - INTERVAL 2 DAY);
INSERT INTO detalle_pedido (pedido_id, producto_id, nombre_producto, precio, cantidad) VALUES
(1, 3, 'Figura Funko Pop - Kratos', 24.99, 1),
(1, 5, 'Headset HyperX Cloud III', 99.99, 1),
(1, 6, 'Poster Elden Ring', 19.99, 3);
