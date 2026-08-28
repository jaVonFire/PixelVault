<?php
/* ============================================================
   Controlador: Carrito (persistente por usuario en BD)
   ============================================================ */

function carrito_active() {
    $u = require_login();
    return (int)$u['id'];
}

/* Listar carrito con datos del producto */
function carrito_index() {
    $uid = carrito_active();
    $stmt = db()->prepare(
        'SELECT c.producto_id AS id, c.cantidad,
                p.nombre, p.precio, p.imagen, p.cantidad AS stock, p.estado
         FROM carrito c
         JOIN producto p ON p.id = c.producto_id
         WHERE c.usuario_id = ?');
    $stmt->execute(array($uid));
    $items = $stmt->fetchAll();

    $total = 0;
    foreach ($items as $it) {
        $total += $it['precio'] * $it['cantidad'];
    }
    json_ok(array('items' => $items, 'total' => round($total, 2)));
}

/* Agregar producto al carrito (valida existencia y stock) */
function carrito_add() {
    $uid = carrito_active();
    $d = read_json_body();
    $producto_id = isset($d['producto_id']) ? (int)$d['producto_id'] : 0;
    $cantidad = isset($d['cantidad']) ? (int)$d['cantidad'] : 1;

    if ($producto_id <= 0 || $cantidad < 1) json_error('Datos de producto invalidos');

    $pdo = db();

    $stmt = $pdo->prepare('SELECT id, cantidad, estado FROM producto WHERE id=?');
    $stmt->execute(array($producto_id));
    $prod = $stmt->fetch();
    if (!$prod) json_error('Producto no encontrado', 404);
    if ($prod['estado'] !== 'activo') json_error('Producto no disponible');

    /* Cuanto hay ya en el carrito de este producto */
    $stmt = $pdo->prepare('SELECT cantidad FROM carrito WHERE usuario_id=? AND producto_id=?');
    $stmt->execute(array($uid, $producto_id));
    $existente = $stmt->fetch();
    $enCarrito = $existente ? (int)$existente['cantidad'] : 0;

    $nueva = $enCarrito + $cantidad;

    /* Revisa inventario disponible */
    if ($nueva > (int)$prod['cantidad']) {
        json_error('Stock insuficiente: solo hay ' . $prod['cantidad'] . ' unidades');
    }

    if ($existente) {
        $stmt = $pdo->prepare('UPDATE carrito SET cantidad=? WHERE usuario_id=? AND producto_id=?');
        $stmt->execute(array($nueva, $uid, $producto_id));
    } else {
        $stmt = $pdo->prepare('INSERT INTO carrito (usuario_id, producto_id, cantidad) VALUES (?,?,?)');
        $stmt->execute(array($uid, $producto_id, $cantidad));
    }

    json_ok(array('message' => 'Producto agregado al carrito'));
}

/* Modificar cantidad de un item del carrito */
function carrito_update() {
    $uid = carrito_active();
    $d = read_json_body();
    $producto_id = isset($d['producto_id']) ? (int)$d['producto_id'] : 0;
    $cantidad = isset($d['cantidad']) ? (int)$d['cantidad'] : 0;

    if ($producto_id <= 0 || $cantidad < 1) json_error('Cantidad invalida');

    /* Stock maximo del producto */
    $stmt = db()->prepare('SELECT cantidad FROM producto WHERE id=?');
    $stmt->execute(array($producto_id));
    $prod = $stmt->fetch();
    if (!$prod) json_error('Producto no encontrado', 404);
    if ($cantidad > (int)$prod['cantidad']) {
        json_error('Stock maximo disponible: ' . $prod['cantidad']);
    }

    $stmt = db()->prepare('UPDATE carrito SET cantidad=? WHERE usuario_id=? AND producto_id=?');
    $stmt->execute(array($cantidad, $uid, $producto_id));
    json_ok(array('message' => 'Carrito actualizado'));
}

/* Quitar un item del carrito */
function carrito_remove() {
    $uid = carrito_active();
    $d = read_json_body();
    $producto_id = isset($d['producto_id']) ? (int)$d['producto_id'] : 0;

    if ($producto_id <= 0) json_error('Producto invalido');

    $stmt = db()->prepare('DELETE FROM carrito WHERE usuario_id=? AND producto_id=?');
    $stmt->execute(array($uid, $producto_id));
    json_ok(array('message' => 'Producto eliminado del carrito'));
}
