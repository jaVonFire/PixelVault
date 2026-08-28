<?php
/* ============================================================
   Controlador: Productos (CRUD)
   GET    /api/index.php?action=productos            -> listar
   POST   /api/index.php?action=productos            -> crear (admin)
   PUT    /api/index.php?action=productos&id=N       -> editar (admin)
   DELETE /api/index.php?action=productos&id=N       -> eliminar (admin)
   Los productos SIEMPRE provienen de la base de datos.
   ============================================================ */

function productos_index($method) {
    if ($method === 'GET') {
        productos_list();
        return;
    }

    /* Todas las escrituras requieren rol admin */
    require_admin();

    if ($method === 'POST') {
        productos_create();
    } elseif ($method === 'PUT') {
        productos_update();
    } elseif ($method === 'DELETE') {
        productos_delete();
    }
}

/* Listar: admite filtro por categoria y por estado */
function productos_list() {
    $cat = isset($_GET['categoria']) ? (int)$_GET['categoria'] : null;
    $todos = isset($_GET['todos']) ? (int)$_GET['todos'] : 0; // 1 = incluir inactivos (solo admin)

    $sql = 'SELECT p.id, p.nombre, p.descripcion, p.categoria_id, c.nombre AS categoria,
                   p.precio, p.imagen, p.cantidad, p.stock_minimo, p.estado, p.creado_el
            FROM producto p
            JOIN categoria c ON c.id = p.categoria_id';
    $where = array();

    if ($cat) {
        $where[] = 'p.categoria_id = ' . (int)$cat;
    }
    if (!$todos) {
        $where[] = "p.estado = 'activo'";
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY p.id';

    $stmt = db()->query($sql);
    json_ok(array('productos' => $stmt->fetchAll()));
}

function productos_create() {
    $d = read_json_body();

    $nombre        = isset($d['nombre']) ? trim($d['nombre']) : '';
    $descripcion   = isset($d['descripcion']) ? trim($d['descripcion']) : '';
    $categoria_id  = isset($d['categoria_id']) ? (int)$d['categoria_id'] : 0;
    $precio        = isset($d['precio']) ? (float)$d['precio'] : 0;
    $imagen        = isset($d['imagen']) ? trim($d['imagen']) : '';
    $cantidad      = isset($d['cantidad']) ? (int)$d['cantidad'] : 0;
    $stock_minimo  = isset($d['stock_minimo']) ? (int)$d['stock_minimo'] : 5;
    $estado        = isset($d['estado']) ? $d['estado'] : 'activo';

    if ($nombre === '' || $categoria_id <= 0 || $precio < 0) {
        json_error('Nombre, categoria y precio son obligatorios');
    }
    if (!in_array($estado, array('activo', 'inactivo'))) {
        $estado = 'activo';
    }

    $stmt = db()->prepare('INSERT INTO producto (nombre, descripcion, categoria_id, precio, imagen, cantidad, stock_minimo, estado)
                           VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute(array($nombre, $descripcion, $categoria_id, $precio, $imagen, $cantidad, $stock_minimo, $estado));

    json_ok(array('id' => (int)db()->lastInsertId(), 'message' => 'Producto creado'), 201);
}

function productos_update() {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) json_error('ID de producto invalido');

    $d = read_json_body();

    $nombre        = isset($d['nombre']) ? trim($d['nombre']) : '';
    $descripcion   = isset($d['descripcion']) ? trim($d['descripcion']) : '';
    $categoria_id  = isset($d['categoria_id']) ? (int)$d['categoria_id'] : 0;
    $precio        = isset($d['precio']) ? (float)$d['precio'] : 0;
    $imagen        = isset($d['imagen']) ? trim($d['imagen']) : '';
    $cantidad      = isset($d['cantidad']) ? (int)$d['cantidad'] : 0;
    $stock_minimo  = isset($d['stock_minimo']) ? (int)$d['stock_minimo'] : 5;
    $estado        = isset($d['estado']) ? $d['estado'] : 'activo';

    if ($nombre === '' || $categoria_id <= 0 || $precio < 0) {
        json_error('Nombre, categoria y precio son obligatorios');
    }

    $stmt = db()->prepare('UPDATE producto
                           SET nombre=?, descripcion=?, categoria_id=?, precio=?, imagen=?,
                               cantidad=?, stock_minimo=?, estado=?
                           WHERE id=?');
    $stmt->execute(array($nombre, $descripcion, $categoria_id, $precio, $imagen, $cantidad, $stock_minimo, $estado, $id));

    if ($stmt->rowCount() === 0) {
        /* verificar que exista el producto */
        $chk = db()->prepare('SELECT id FROM producto WHERE id=?');
        $chk->execute(array($id));
        if (!$chk->fetch()) json_error('Producto no encontrado', 404);
    }
    json_ok(array('message' => 'Producto actualizado'));
}

function productos_delete() {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) json_error('ID de producto invalido');

    $pdo = db();

    /* Verificar que el producto existe */
    $chk = $pdo->prepare('SELECT id FROM producto WHERE id=?');
    $chk->execute(array($id));
    if (!$chk->fetch()) json_error('Producto no encontrado', 404);

    /* Eliminacion FISICA siempre, en transaccion:
       - primero los detalles de pedido que referencian el producto
         (para liberar la FK detalle_pedido.producto_id -> producto.id)
       - luego el producto mismo */
    $pdo->beginTransaction();
    try {
        $delDet = $pdo->prepare('DELETE FROM detalle_pedido WHERE producto_id=?');
        $delDet->execute(array($id));

        $delProd = $pdo->prepare('DELETE FROM producto WHERE id=?');
        $delProd->execute(array($id));

        $pdo->commit();
        json_ok(array('message' => 'Producto eliminado definitivamente de la base de datos'));
    } catch (Exception $e) {
        $pdo->rollBack();
        json_error('No se pudo eliminar el producto', 500);
    }
}

/* Productos con inventario bajo (alerta) */
function productos_alertas() {
    require_admin();
    $stmt = db()->query('SELECT id, nombre, cantidad, stock_minimo FROM producto
                         WHERE estado="activo" AND cantidad <= stock_minimo ORDER BY cantidad');
    json_ok(array('alertas' => $stmt->fetchAll()));
}
