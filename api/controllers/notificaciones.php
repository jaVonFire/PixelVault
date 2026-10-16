<?php
/* ============================================================
   Controlador: Notificaciones (recordatorios de carrito)

   Las notificaciones NO se crean aqui: las genera el workflow de
   n8n "PixelVault - Recordatorio Carrito" (Schedule Trigger cada
   minuto) que escribe en la tabla `notificacion`. Este controlador
   solo las LEE para el usuario y marca como leidas.

   GET  /api/index.php?action=notificaciones        -> lista + no leidas
   POST /api/index.php?action=notificaciones        -> marcar leida (o todas)
        body: { id: 12 }   -> marca una
              { all: true } -> marca todas
   ============================================================ */

function notificaciones_index() {
    $u = require_login();
    $uid = (int)$u['id'];
    $pdo = db();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notificacion WHERE usuario_id=? AND leida=0');
    $stmt->execute(array($uid));
    $no_leidas = (int)$stmt->fetchColumn();

    /* No leidas primero, luego por fecha descendente */
    $stmt = $pdo->prepare('SELECT id, tipo, titulo, mensaje, leida, creado_el
                           FROM notificacion
                           WHERE usuario_id=?
                           ORDER BY leida ASC, creado_el DESC
                           LIMIT 20');
    $stmt->execute(array($uid));
    $items = $stmt->fetchAll();

    json_ok(array('items' => $items, 'no_leidas' => $no_leidas));
}

function notificacion_leer() {
    $u = require_login();
    $uid = (int)$u['id'];
    $d = read_json_body();
    $pdo = db();

    /* Marcar todas como leidas */
    if (!empty($d['all'])) {
        $stmt = $pdo->prepare('UPDATE notificacion SET leida=1 WHERE usuario_id=? AND leida=0');
        $stmt->execute(array($uid));
        json_ok(array('message' => 'Notificaciones marcadas como leidas',
                      'marcadas' => $stmt->rowCount()));
    }

    $id = isset($d['id']) ? (int)$d['id'] : 0;
    if ($id <= 0) {
        json_error('Notificacion invalida');
    }

    $stmt = $pdo->prepare('UPDATE notificacion SET leida=1 WHERE id=? AND usuario_id=?');
    $stmt->execute(array($id, $uid));
    if ($stmt->rowCount() === 0) {
        $chk = $pdo->prepare('SELECT id FROM notificacion WHERE id=? AND usuario_id=?');
        $chk->execute(array($id, $uid));
        if (!$chk->fetch()) {
            json_error('Notificacion no encontrada', 404);
        }
    }
    json_ok(array('message' => 'Notificacion leida'));
}
