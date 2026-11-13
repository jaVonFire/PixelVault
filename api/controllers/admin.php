<?php
/* ============================================================
   Controlador: Panel administrativo y clientes
   ============================================================ */

/* Metricas del panel: usuarios, productos, inventario bajo,
   pedidos, ultimos pedidos y estado de los pedidos. */
function dashboard_index() {
    require_admin();
    $pdo = db();
    $stockThreshold = 4;

    /* El panel también revisa el inventario al abrirse, aunque el worker no corra. */
    try {
        require_once __DIR__ . '/../services/scheduled_automations.php';
        $stockThreshold = (int)automation_cfg()['stock_alert_threshold'];
        automation_run_low_stock();
        automation_run_email_queue();
    } catch (Throwable $automationError) {
        error_log('PixelVault admin stock trigger failed: ' . $automationError->getMessage());
    }

    $totalUsuarios   = (int)$pdo->query('SELECT COUNT(*) FROM usuario')->fetchColumn();
    $totalProductos  = (int)$pdo->query('SELECT COUNT(*) FROM producto')->fetchColumn();
    $totalPedidos    = (int)$pdo->query('SELECT COUNT(*) FROM pedido')->fetchColumn();

    /* Productos con inventario bajo (cantidad <= stock minimo) */
    $stockStmt = $pdo->prepare('SELECT p.id, p.nombre, p.cantidad, p.stock_minimo
                                FROM producto p
                                WHERE p.cantidad <= ? AND p.estado="activo"
                                ORDER BY p.cantidad');
    $stockStmt->execute(array($stockThreshold));
    $bajo = $stockStmt->fetchAll();

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

/* Eliminar un usuario (solo admin). El administrador NO edita los
   datos ni la contrasena de los usuarios: unicamente puede eliminarlos.
   Al eliminar un usuario se borran TAMBIEN sus pedidos y su carrito.
   No se permite que el admin elimine su propia cuenta. */
function clientes_delete() {
    $admin = require_admin();

    $d  = read_json_body();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($d['id']) ? (int)$d['id'] : 0);
    if ($id <= 0) {
        json_error('ID de usuario invalido');
    }
    if ($id === (int)$admin['id']) {
        json_error('No puedes eliminar tu propia cuenta de administrador');
    }

    $pdo = db();

    /* Verificar que el usuario exista */
    $stmt = $pdo->prepare('SELECT id, username FROM usuario WHERE id = ?');
    $stmt->execute(array($id));
    $user = $stmt->fetch();
    if (!$user) {
        json_error('Usuario no encontrado', 404);
    }

    $pdo->beginTransaction();
    try {
        /* 1. Pedidos del usuario (detalle_pedido se borra en cascada) */
        $pdo->prepare('DELETE FROM pedido WHERE usuario_id = ?')->execute(array($id));
        /* 2. Carrito del usuario (por consistencia; tambien cae en cascada) */
        $pdo->prepare('DELETE FROM carrito WHERE usuario_id = ?')->execute(array($id));
        /* 3. Usuario */
        $pdo->prepare('DELETE FROM usuario WHERE id = ?')->execute(array($id));
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        json_error('No se pudo eliminar el usuario: ' . $e->getMessage(), 500);
    }

    json_ok(array('message' => 'Usuario eliminado junto con sus pedidos'));
}

/* Bitacora del chatbot IA que escribe el backend nativo en chat_log.
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
    $fuentes = array('gemini' => 0, 'n8n' => 0, 'guardarrail' => 0, 'respaldo' => 0, 'local' => 0);
    foreach ($pdo->query('SELECT fuente, COUNT(*) AS n FROM chat_log GROUP BY fuente') as $r) {
        if (!isset($fuentes[$r['fuente']])) $fuentes[$r['fuente']] = 0;
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

/* Estado reciente de los flujos nativos y su cola de correo. */
function automatizaciones_index() {
    require_admin();
    $pdo = db();
    require_once __DIR__ . '/../services/automation_support.php';
    $cfg = automation_cfg();
    $runs = $pdo->query('SELECT id, flujo, estado, entrada, salida, error, iniciado_el, finalizado_el
                         FROM automatizacion_ejecucion ORDER BY id DESC LIMIT 30')->fetchAll();
    foreach ($runs as &$run) {
        $run['entrada'] = json_decode((string)$run['entrada'], true);
        $run['salida'] = json_decode((string)$run['salida'], true);
    }
    unset($run);
    $mailStats = array('pendiente' => 0, 'enviando' => 0, 'enviado' => 0, 'fallido' => 0);
    foreach ($pdo->query('SELECT estado, COUNT(*) AS n FROM automatizacion_correo GROUP BY estado') as $row) {
        $mailStats[$row['estado']] = (int)$row['n'];
    }
    $mail = $pdo->query("SELECT id, flujo, referencia_id, destinatario, asunto, estado, intentos, disponible_el,
                                enviado_el, ultimo_error, creado_el
                         FROM automatizacion_correo
                         WHERE estado IN ('pendiente','enviando','fallido')
                         ORDER BY CASE estado WHEN 'fallido' THEN 0 WHEN 'pendiente' THEN 1 ELSE 2 END, id DESC
                         LIMIT 30")->fetchAll();
    json_ok(array(
        'mail_enabled' => (bool)$cfg['mail_enabled'],
        'mail_configured' => (bool)automation_mail_is_configured($cfg),
        'admin_email_configured' => (bool)filter_var($cfg['admin_email'], FILTER_VALIDATE_EMAIL),
        'admin_email' => $cfg['admin_email'],
        'runs' => $runs,
        'correo_stats' => $mailStats,
        'correos' => $mail
    ));
}

/* Reincorpora un correo fallido a la cola sin borrar su historial. */
function automatizacion_correo_reintentar() {
    require_admin();
    $data = read_json_body();
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    if ($id <= 0) json_error('Correo invalido');
    $stmt = db()->prepare("UPDATE automatizacion_correo
                           SET estado='pendiente', intentos=0, disponible_el=NOW(), ultimo_error=NULL
                           WHERE id=? AND estado='fallido'");
    $stmt->execute(array($id));
    if ($stmt->rowCount() !== 1) json_error('No se encontro un correo fallido con ese ID', 404);
    json_ok(array('message' => 'Correo agregado de nuevo a la cola'));
}
