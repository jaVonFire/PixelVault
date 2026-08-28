<?php
/* ============================================================
   Controlador: Pedidos
   ============================================================ */

/* Confirmar compra: toma el carrito, crea PEDIDO + DETALLE_PEDIDO,
   descuenta inventario y vacia el carrito. Todo en una transaccion. */
function pedidos_create() {
    $u = require_login();
    $uid = (int)$u['id'];
    $pdo = db();

    $pdo->beginTransaction();
    try {
        /* 1. Leer carrito con datos del producto */
        $stmt = $pdo->prepare(
            'SELECT c.producto_id AS id, c.cantidad, p.nombre, p.precio, p.cantidad AS stock
             FROM carrito c JOIN producto p ON p.id = c.producto_id
             WHERE c.usuario_id = ?');
        $stmt->execute(array($uid));
        $items = $stmt->fetchAll();

        if (empty($items)) {
            $pdo->rollBack();
            json_error('Tu carrito esta vacio');
        }

        /* 2. Validar stock de cada item */
        foreach ($items as $it) {
            if ((int)$it['cantidad'] > (int)$it['stock']) {
                $pdo->rollBack();
                json_error('Stock insuficiente de "' . $it['nombre'] . '"');
            }
        }

        /* 3. Total y codigo del pedido */
        $total = 0;
        foreach ($items as $it) {
            $total += $it['precio'] * $it['cantidad'];
        }
        $codigo = 'PV-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        /* 4. Crear pedido */
        $stmt = $pdo->prepare('INSERT INTO pedido (usuario_id, codigo, total, estado) VALUES (?,?,?, "pendiente")');
        $stmt->execute(array($uid, $codigo, round($total, 2)));
        $pedido_id = (int)$pdo->lastInsertId();

        /* 5. Detalles del pedido + descontar inventario */
        $det = $pdo->prepare('INSERT INTO detalle_pedido (pedido_id, producto_id, nombre_producto, precio, cantidad) VALUES (?,?,?,?,?)');
        $upd = $pdo->prepare('UPDATE producto SET cantidad = cantidad - ? WHERE id = ?');
        foreach ($items as $it) {
            $det->execute(array($pedido_id, $it['id'], $it['nombre'], $it['precio'], $it['cantidad']));
            $upd->execute(array($it['cantidad'], $it['id']));
        }

        /* 6. Vaciar carrito */
        $pdo->prepare('DELETE FROM carrito WHERE usuario_id=?')->execute(array($uid));

        $pdo->commit();

        json_ok(array(
            'pedido_id' => $pedido_id,
            'codigo'    => $codigo,
            'total'     => round($total, 2),
            'message'   => 'Compra confirmada correctamente'
        ), 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        json_error('No se pudo procesar el pedido: ' . $e->getMessage(), 500);
    }
}

/* Historial de pedidos.
   Cliente: solo los suyos. Admin: todos (con filtro opcional por estado). */
function pedidos_index() {
    $u = require_login();

    $sql = 'SELECT p.id, p.codigo, p.total, p.estado, p.creado_el, u.username
            FROM pedido p JOIN usuario u ON u.id = p.usuario_id';
    $params = array();

    if ($u['rol'] !== 'admin') {
        $sql .= ' WHERE p.usuario_id = ?';
        $params[] = (int)$u['id'];
    } else {
        $estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
        if ($estado !== '' && in_array($estado, array('pendiente','procesando','enviado','entregado','cancelado'))) {
            $sql .= ' WHERE p.estado = ?';
            $params[] = $estado;
        }
    }
    $sql .= ' ORDER BY p.creado_el DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll();

    /* Cargar detalles de cada pedido */
    $det = db()->prepare('SELECT nombre_producto, precio, cantidad FROM detalle_pedido WHERE pedido_id=?');
    foreach ($pedidos as &$p) {
        $det->execute(array($p['id']));
        $p['items'] = $det->fetchAll();
    }

    json_ok(array('pedidos' => $pedidos));
}

/* Cambiar estado de un pedido (solo admin) */
function pedidos_update_estado() {
    require_admin();

    $d = read_json_body();
    $id = isset($d['pedido_id']) ? (int)$d['pedido_id'] : 0;
    $estado = isset($d['estado']) ? $d['estado'] : '';

    if ($id <= 0 || !in_array($estado, array('pendiente','procesando','enviado','entregado','cancelado'))) {
        json_error('Datos de estado invalidos');
    }

    $stmt = db()->prepare('UPDATE pedido SET estado=? WHERE id=?');
    $stmt->execute(array($estado, $id));
    if ($stmt->rowCount() === 0) {
        $chk = db()->prepare('SELECT id FROM pedido WHERE id=?');
        $chk->execute(array($id));
        if (!$chk->fetch()) json_error('Pedido no encontrado', 404);
    }
    json_ok(array('message' => 'Estado del pedido actualizado'));
}
