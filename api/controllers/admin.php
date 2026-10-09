<?php
/* ============================================================
   Controlador: Panel administrativo y clientes
   ============================================================ */

/* Metricas del panel: usuarios, productos, inventario bajo,
   pedidos, ultimos pedidos y estado de los pedidos. */
function dashboard_index() {
    require_admin();
    $pdo = db();

    $totalUsuarios   = (int)$pdo->query('SELECT COUNT(*) FROM usuario')->fetchColumn();
    $totalProductos  = (int)$pdo->query('SELECT COUNT(*) FROM producto')->fetchColumn();
    $totalPedidos    = (int)$pdo->query('SELECT COUNT(*) FROM pedido')->fetchColumn();

    /* Productos con inventario bajo (cantidad <= stock minimo) */
    $bajo = $pdo->query('SELECT p.id, p.nombre, p.cantidad, p.stock_minimo
                         FROM producto p
                         WHERE p.cantidad <= p.stock_minimo AND p.estado="activo"
                         ORDER BY p.cantidad')->fetchAll();

    /* Ultimos 5 pedidos registrados */
    $ultimos = $pdo->query('SELECT p.id, p.codigo, p.total, p.estado, p.creado_el, u.username
                            FROM pedido p JOIN usuario u ON u.id = p.usuario_id
                            ORDER BY p.creado_el DESC LIMIT 5')->fetchAll();

    /* Conteo de pedidos por estado */
    $porEstado = array('pendiente'=>0,'procesando'=>0,'enviado'=>0,'entregado'=>0,'cancelado'=>0);
    $rows = $pdo->query('SELECT estado, COUNT(*) AS n FROM pedido GROUP BY estado')->fetchAll();
    foreach ($rows as $r) {
        if (isset($porEstado[$r['estado']])) $porEstado[$r['estado']] = (int)$r['n'];
    }

    /* Ultimos registros de clientes */
    $ultimosClientes = $pdo->query('SELECT id, username, email, creado_el FROM usuario ORDER BY creado_el DESC LIMIT 5')->fetchAll();

    json_ok(array(
        'total_usuarios'   => $totalUsuarios,
        'total_productos'  => $totalProductos,
        'total_pedidos'    => $totalPedidos,
        'inventario_bajo'  => $bajo,
        'ultimos_pedidos'  => $ultimos,
        'pedidos_por_estado' => $porEstado,
        'ultimos_clientes' => $ultimosClientes
    ));
}

function clientes_index() {
    require_admin();
    $stmt = db()->query('SELECT id, username, displayname, email, rol, pais, moneda, creado_el
                         FROM usuario ORDER BY creado_el DESC');
    json_ok(array('clientes' => $stmt->fetchAll()));
}

/* Bitacora del chatbot IA que escribe n8n en la tabla chat_log.
   Sirve para auditar que respondio la IA, que bloqueo el guardarrail
   y que preguntas hacen los usuarios. */
function chatlog_index() {
    require_admin();
    $pdo = db();

    $total     = (int)$pdo->query('SELECT COUNT(*) FROM chat_log')->fetchColumn();
    $hoy       = (int)$pdo->query('SELECT COUNT(*) FROM chat_log WHERE DATE(creado_el) = CURDATE()')->fetchColumn();
    $conCuenta = (int)$pdo->query('SELECT COUNT(DISTINCT usuario) FROM chat_log WHERE usuario IS NOT NULL')->fetchColumn();
    $conversaciones = (int)$pdo->query('SELECT COUNT(DISTINCT sesion) FROM chat_log')->fetchColumn();

    /* Reparto por origen de la respuesta */
    $fuentes = array('n8n' => 0, 'guardarrail' => 0, 'respaldo' => 0);
    foreach ($pdo->query('SELECT fuente, COUNT(*) AS n FROM chat_log GROUP BY fuente') as $r) {
        $fuentes[$r['fuente']] = (int)$r['n'];
    }

    /* Interacciones por dia (ultimos 7) */
    $porDia = $pdo->query('SELECT DATE(creado_el) AS dia, COUNT(*) AS n
                           FROM chat_log
                           WHERE creado_el >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                           GROUP BY dia ORDER BY dia')->fetchAll();

    /* Ultimos 30 intercambios */
    $ultimos = $pdo->query('SELECT sesion, usuario, lang, mensaje, reply, fuente, creado_el
                            FROM chat_log ORDER BY id DESC LIMIT 30')->fetchAll();

    json_ok(array(
        'total'          => $total,
        'hoy'            => $hoy,
        'con_cuenta'     => $conCuenta,
        'conversaciones' => $conversaciones,
        'fuentes'        => $fuentes,
        'por_dia'        => $porDia,
        'ultimos'        => $ultimos
    ));
}
