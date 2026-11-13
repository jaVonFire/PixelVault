<?php
/* Reglas de negocio trasladadas desde los workflows de n8n a PHP/MySQL. */
require_once __DIR__ . '/automation_support.php';

function automation_execute($flow, $callback) {
    $runId = automation_run_start($flow);
    try {
        $summary = call_user_func($callback);
        $hasWork = !empty($summary['acciones']) || !empty($summary['correos_encolados'])
            || !empty($summary['correos_enviados']) || !empty($summary['reintentos'])
            || !empty($summary['repuestos']);
        automation_run_finish($runId, $hasWork ? 'completado' : 'sin_cambios', $summary);
        return $summary;
    } catch (Throwable $e) {
        automation_run_finish($runId, 'error', array(), automation_limit_text($e->getMessage(), 1000));
        error_log('PixelVault automation [' . $flow . '] failed: ' . $e->getMessage());
        return array('error' => $e->getMessage());
    }
}

function automation_run_reminders($onlyUserId = null) {
    return automation_execute('recordatorio_carrito', function () use ($onlyUserId) {
        $pdo = db();
        if ((int)$onlyUserId > 0) {
            $userStmt = $pdo->prepare("SELECT c.usuario_id, MAX(c.agregado_el) AS ultima_actividad,
                                              COUNT(DISTINCT c.producto_id) AS productos
                                       FROM carrito c
                                       JOIN usuario u ON u.id=c.usuario_id AND u.rol='cliente'
                                       WHERE c.usuario_id=?
                                       GROUP BY c.usuario_id
                                       HAVING MAX(c.agregado_el) <= DATE_SUB(NOW(), INTERVAL 60 SECOND)");
            $userStmt->execute(array((int)$onlyUserId));
            $users = $userStmt->fetchAll();
        } else {
            $users = $pdo->query("SELECT c.usuario_id, MAX(c.agregado_el) AS ultima_actividad,
                                         COUNT(DISTINCT c.producto_id) AS productos
                                  FROM carrito c
                                  JOIN usuario u ON u.id=c.usuario_id AND u.rol='cliente'
                                  GROUP BY c.usuario_id
                                  HAVING MAX(c.agregado_el) <= DATE_SUB(NOW(), INTERVAL 60 SECOND)")->fetchAll();
        }
        $first = 0;
        $second = 0;
        foreach ($users as $user) {
            $counts = automation_process_reminder_user($pdo, $user);
            $first += $counts['primeros'];
            $second += $counts['segundos'];
        }
        return array('acciones' => $first + $second, 'primeros_avisos' => $first, 'segundos_avisos' => $second);
    });
}

function automation_process_reminder_user($pdo, $user) {
    $uid = (int)$user['usuario_id'];
    $lockName = 'pv_cart_reminder_' . $uid;
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $lock->execute(array($lockName));
    if ((int)$lock->fetchColumn() !== 1) return array('primeros' => 0, 'segundos' => 0);

    $first = 0;
    $second = 0;
    try {
        $activity = $user['ultima_actividad'];
        $currentActivity = $pdo->prepare('SELECT MAX(agregado_el) FROM carrito WHERE usuario_id=?');
        $currentActivity->execute(array($uid));
        if ($currentActivity->fetchColumn() !== $activity) return array('primeros' => 0, 'segundos' => 0);

        $hasFirst = $pdo->prepare("SELECT id FROM notificacion WHERE usuario_id=? AND tipo='carrito_1' AND creado_el>=? LIMIT 1");
        $hasFirst->execute(array($uid, $activity));
        $firstNotice = $hasFirst->fetchColumn();
        if (!$firstNotice) {
            $insert = $pdo->prepare("INSERT INTO notificacion (usuario_id, tipo, titulo, mensaje)
                                     VALUES (?, 'carrito_1', 'Tu carrito te espera', ?)");
            $insert->execute(array($uid, 'Tienes ' . (int)$user['productos'] . ' producto(s) esperando en tu carrito. ¡Termina tu compra ahora!'));
            $first++;
            $hasFirst->execute(array($uid, $activity));
            $firstNotice = $hasFirst->fetchColumn();
        }

        if (!$firstNotice) return array('primeros' => $first, 'segundos' => $second);
        $ready = $pdo->prepare("SELECT id FROM notificacion
                                WHERE id=? AND creado_el<=DATE_SUB(NOW(), INTERVAL 60 SECOND)");
        $ready->execute(array((int)$firstNotice));
        if (!$ready->fetchColumn()) return array('primeros' => $first, 'segundos' => $second);

        $isStale = $pdo->prepare('SELECT MAX(agregado_el) FROM carrito WHERE usuario_id=?');
        $isStale->execute(array($uid));
        if ($isStale->fetchColumn() !== $activity) return array('primeros' => $first, 'segundos' => $second);
        $hasSecond = $pdo->prepare("SELECT id FROM notificacion WHERE usuario_id=? AND tipo='carrito_2' AND creado_el>=? LIMIT 1");
        $hasSecond->execute(array($uid, $activity));
        if ($hasSecond->fetchColumn()) return array('primeros' => $first, 'segundos' => $second);
        $insert = $pdo->prepare("INSERT INTO notificacion (usuario_id, tipo, titulo, mensaje)
                                 VALUES (?, 'carrito_2', '¡Última oportunidad!', 'Tu carrito sigue esperando. Los productos que quieres podrían agotarse. ¡Complétalo ahora!')");
        $insert->execute(array($uid));
        $second++;
        return array('primeros' => $first, 'segundos' => $second);
    } finally {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute(array($lockName));
    }
}

function automation_run_postpurchase() {
    return automation_execute('poscompra', function () {
        $pdo = db();
        $ids = $pdo->query("SELECT id FROM pedido WHERE facturado_el IS NULL ORDER BY id ASC LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);
        $processed = 0;
        $skipped = 0;
        $queued = 0;
        foreach ($ids as $orderId) {
            $pdo->beginTransaction();
            try {
                $orderStmt = $pdo->prepare('SELECT p.id, p.usuario_id, p.codigo, p.total, p.estado, p.creado_el, p.facturado_el,
                                                   u.username, u.displayname, u.email, u.moneda, u.puntos
                                            FROM pedido p JOIN usuario u ON u.id=p.usuario_id
                                            WHERE p.id=? FOR UPDATE');
                $orderStmt->execute(array((int)$orderId));
                $order = $orderStmt->fetch();
                if (!$order || $order['facturado_el'] !== null) {
                    $pdo->commit();
                    continue;
                }
                if ($order['estado'] === 'cancelado') {
                    if ($order) $pdo->prepare('UPDATE pedido SET facturado_el=NOW(), resena_enviada=1 WHERE id=?')->execute(array((int)$orderId));
                    $pdo->commit();
                    $skipped++;
                    continue;
                }

                $itemsStmt = $pdo->prepare('SELECT d.nombre_producto, d.precio, d.cantidad, d.producto_id,
                                                    pr.nombre AS nombre_actual, pr.cantidad AS stock, pr.stock_minimo
                                             FROM detalle_pedido d
                                             LEFT JOIN producto pr ON pr.id=d.producto_id
                                             WHERE d.pedido_id=?');
                $itemsStmt->execute(array((int)$orderId));
                $items = $itemsStmt->fetchAll();
                if (!$items) {
                    $pdo->prepare('UPDATE pedido SET facturado_el=NOW(), resena_enviada=1 WHERE id=?')->execute(array((int)$orderId));
                    $pdo->prepare("INSERT INTO automatizacion_log (pedido_id, usuario_id, tipo, detalle) VALUES (?, ?, 'pos_compra', 'Pedido sin detalle; se marco para no reprocesar')")
                        ->execute(array((int)$orderId, (int)$order['usuario_id']));
                    $pdo->commit();
                    $skipped++;
                    continue;
                }

                $earned = max(0, (int)floor((float)$order['total']));
                $points = (int)$order['puntos'] + $earned;
                $level = $points >= 600 ? 'platino' : ($points >= 300 ? 'oro' : ($points >= 100 ? 'plata' : 'nuevo'));
                $pdo->prepare('UPDATE usuario SET puntos=?, nivel=? WHERE id=?')
                    ->execute(array($points, $level, (int)$order['usuario_id']));

                $previous = $pdo->prepare('SELECT COUNT(*) FROM pedido WHERE usuario_id=? AND id<?');
                $previous->execute(array((int)$order['usuario_id'], (int)$orderId));
                $percent = 0;
                $reason = '';
                if ((float)$order['total'] >= 150) {
                    $percent = 10;
                    $reason = 'Compra de alto valor';
                } elseif ((int)$previous->fetchColumn() === 0) {
                    $percent = 5;
                    $reason = 'Cliente nuevo';
                }
                $couponCode = '';
                if ($percent > 0) {
                    for ($try = 0; $try < 4; $try++) {
                        $couponCode = 'PV' . $percent . '-' . (int)$orderId . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
                        try {
                            $pdo->prepare("INSERT INTO cupon (codigo, usuario_id, pedido_id, descuento_pct, motivo, estado, expira_el)
                                           VALUES (?, ?, ?, ?, ?, 'activo', DATE_ADD(NOW(), INTERVAL 30 DAY))")
                                ->execute(array($couponCode, (int)$order['usuario_id'], (int)$orderId, $percent, $reason));
                            break;
                        } catch (PDOException $e) {
                            if ($try === 3) throw $e;
                        }
                    }
                }

                $currency = $order['moneda'] ?: 'USD';
                $name = $order['displayname'] ?: $order['username'];
                $invoice = automation_invoice_html($order, $name, $items, $earned, $points, $level, $couponCode, $percent, $currency);
                $invoiceQueued = automation_queue_email('poscompra_factura', 'factura:' . (int)$orderId, $order['email'],
                    'Tu factura PIXELVAULT - Pedido ' . $order['codigo'], $invoice, (int)$orderId);
                if ($invoiceQueued) $queued++;

                $lowItems = array();
                foreach ($items as $item) {
                    if ($item['stock'] !== null && (int)$item['stock'] <= (int)$item['stock_minimo']) {
                        $lowItems[] = $item['nombre_actual'] . ' (quedan ' . (int)$item['stock'] . ' uds)';
                    }
                }
                $adminNeeded = (float)$order['total'] >= 250 || count($lowItems) > 0;
                if ($adminNeeded && filter_var(automation_cfg()['admin_email'], FILTER_VALIDATE_EMAIL)) {
                    $adminHtml = '<h3>Alerta PIXELVAULT - Pedido ' . automation_html($order['codigo']) . '</h3>'
                        . '<p>Cliente: ' . automation_html($order['email']) . '<br>Total: ' . automation_html($currency) . ' ' . number_format((float)$order['total'], 2) . '<br>Nivel: ' . automation_html($level) . '</p>';
                    if ($lowItems) $adminHtml .= '<p><b>Stock bajo tras la compra:</b> ' . automation_html(implode(', ', $lowItems)) . '</p>';
                    if ((float)$order['total'] >= 250) $adminHtml .= '<p><b>Pedido de alto valor.</b></p>';
                    if (automation_queue_email('poscompra_alerta_admin', 'alerta-pedido:' . (int)$orderId, automation_cfg()['admin_email'],
                        'PIXELVAULT - Alerta pedido ' . $order['codigo'], $adminHtml, (int)$orderId)) $queued++;
                }

                $summary = 'Factura en cola: ' . ($invoiceQueued ? 'si' : 'no') . '; +' . $earned . ' puntos; nivel ' . $level;
                if ($couponCode !== '') $summary .= '; cupon ' . $couponCode;
                $pdo->prepare('UPDATE pedido SET facturado_el=NOW() WHERE id=?')->execute(array((int)$orderId));
                $pdo->prepare("INSERT INTO automatizacion_log (pedido_id, usuario_id, tipo, detalle) VALUES (?, ?, 'pos_compra', ?)")
                    ->execute(array((int)$orderId, (int)$order['usuario_id'], automation_limit_text($summary, 255)));
                $pdo->commit();
                $processed++;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        }
        return array('acciones' => $processed + $skipped, 'pedidos_procesados' => $processed, 'pedidos_omitidos' => $skipped, 'correos_encolados' => $queued);
    });
}

function automation_invoice_html($order, $name, $items, $earned, $points, $level, $coupon, $percent, $currency) {
    $appUrl = automation_html(automation_cfg()['app_url']);
    $rows = '';
    foreach ($items as $item) {
        $line = (float)$item['precio'] * (int)$item['cantidad'];
        $rows .= '<tr><td style="padding:9px 12px;border-bottom:1px solid #e8e8f0;">' . automation_html($item['nombre_producto']) . '</td>'
            . '<td style="padding:9px 12px;text-align:center;border-bottom:1px solid #e8e8f0;">' . (int)$item['cantidad'] . '</td>'
            . '<td style="padding:9px 12px;text-align:right;border-bottom:1px solid #e8e8f0;">' . automation_html($currency) . ' ' . number_format((float)$item['precio'], 2) . '</td>'
            . '<td style="padding:9px 12px;text-align:right;border-bottom:1px solid #e8e8f0;">' . automation_html($currency) . ' ' . number_format($line, 2) . '</td></tr>';
    }
    $couponBlock = '';
    if ($coupon !== '') {
        $couponBlock = '<div style="margin-top:14px;padding:16px;border:2px dashed #fbbf24;text-align:center;background:#fffbeb;">'
            . '<b>Cupon ' . automation_html($percent) . '% OFF</b><br>' . automation_html($coupon)
            . '<br><small>Valido por 30 dias en tu proxima compra.</small></div>';
    }
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,sans-serif;color:#1f2937;">'
        . '<div style="max-width:640px;margin:auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">'
        . '<header style="padding:24px;background:#111827;color:#fbbf24;font-size:22px;font-weight:bold;">PIXELVAULT <span style="display:block;color:#d1d5db;font-size:12px;">Factura de compra</span></header>'
        . '<main style="padding:24px;"><p>Hola <b>' . automation_html($name) . '</b>, gracias por tu compra.</p>'
        . '<p>Pedido: <b>' . automation_html($order['codigo']) . '</b> · Fecha: ' . automation_html($order['creado_el']) . '</p>'
        . '<table style="width:100%;border-collapse:collapse;font-size:14px;"><thead><tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Subtotal</th></tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<p style="text-align:right;font-size:20px;"><b>Total: ' . automation_html($currency) . ' ' . number_format((float)$order['total'], 2) . '</b></p>'
        . '<div style="padding:14px;background:#111827;color:#f3f4f6;">Ganaste +' . (int)$earned . ' puntos. Tienes ' . (int)$points . ' puntos y tu nivel es ' . automation_html(ucfirst($level)) . '.</div>'
        . $couponBlock . '<p style="margin-top:20px;"><a href="' . $appUrl . '" style="color:#2563eb;">Visita PixelVault</a></p></main>'
        . '<footer style="padding:14px;text-align:center;background:#f9fafb;color:#6b7280;font-size:12px;">PIXELVAULT - Correo automatico, no responder.</footer>'
        . '</div></body></html>';
}

function automation_run_reviews($onlyUserId = null) {
    return automation_execute('poscompra_resenas', function () use ($onlyUserId) {
        $pdo = db();
        $sql = "SELECT p.id, p.codigo, p.facturado_el, u.email, u.displayname, u.username
                FROM pedido p JOIN usuario u ON u.id=p.usuario_id
                WHERE p.facturado_el IS NOT NULL AND p.resena_enviada=0
                  AND p.facturado_el <= DATE_SUB(NOW(), INTERVAL 1 DAY)
                  AND p.facturado_el >= DATE_SUB(NOW(), INTERVAL 2 DAY)
                  AND p.estado<>'cancelado'";
        if ((int)$onlyUserId > 0) $sql .= ' AND p.usuario_id=' . (int)$onlyUserId;
        $sql .= ' ORDER BY p.id ASC LIMIT 50';
        $orders = $pdo->query($sql)->fetchAll();
        $queued = 0;
        foreach ($orders as $order) {
            $name = $order['displayname'] ?: $order['username'];
            $appUrl = automation_html(automation_cfg()['app_url']);
            $html = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;color:#1f2937;"><div style="max-width:560px;margin:auto;padding:24px;border:1px solid #e5e7eb;">'
                . '<h2 style="color:#b45309;">PIXELVAULT</h2><p>Hola ' . automation_html($name) . ',</p>'
                . '<p>Esperamos que disfrutes tu pedido <b>' . automation_html($order['codigo']) . '</b>. ¿Nos cuentas cómo fue tu experiencia?</p>'
                . '<p>Responde a este correo con tu opinión. Nos ayuda a mejorar la tienda.</p><p>¡Gracias por elegir PixelVault!</p>'
                . '<p><a href="' . $appUrl . '">Visitar PixelVault</a></p></div></body></html>';
            if (automation_queue_email('poscompra_resena', 'resena:' . (int)$order['id'], $order['email'],
                '¿Cómo estuvo tu compra en PIXELVAULT?', $html, (int)$order['id'])) $queued++;
        }
        return array('acciones' => $queued, 'correos_encolados' => $queued);
    });
}

function automation_run_low_stock() {
    return automation_execute('alerta_stock_bajo', function () {
        $pdo = db();
        $threshold = (int)automation_cfg()['stock_alert_threshold'];
        $pdo->beginTransaction();
        try {
            /* En el primer ciclo nativo reavisa el inventario ya bajo con el umbral de la app. */
            $nativeStockMarker = $pdo->exec("INSERT IGNORE INTO automatizacion_configuracion (clave, valor)
                                             VALUES ('stock_alertas_nativas_inicializadas', '1')");
            if ($nativeStockMarker > 0) {
                $resetInitial = $pdo->prepare("UPDATE producto SET alerta_stock_enviada=0
                                               WHERE estado='activo' AND cantidad<=?");
                $resetInitial->execute(array($threshold));
            }
            $resetStmt = $pdo->prepare('UPDATE producto SET alerta_stock_enviada=0 WHERE cantidad>? AND alerta_stock_enviada=1');
            $resetStmt->execute(array($threshold));
            $reset = $resetStmt->rowCount();
            $itemsStmt = $pdo->prepare("SELECT id, nombre, cantidad FROM producto
                                        WHERE estado='activo' AND cantidad<=? AND alerta_stock_enviada=0
                                        ORDER BY cantidad ASC, nombre ASC FOR UPDATE");
            $itemsStmt->execute(array($threshold));
            $items = $itemsStmt->fetchAll();
            $queued = false;
            if ($items && filter_var(automation_cfg()['admin_email'], FILTER_VALIDATE_EMAIL)) {
                $rows = '';
                $ids = array();
                foreach ($items as $item) {
                    $ids[] = (int)$item['id'];
                    $color = (int)$item['cantidad'] === 0 ? '#dc2626' : '#1f2937';
                    $rows .= '<tr><td style="padding:8px;border-bottom:1px solid #e5e7eb;">' . automation_html($item['nombre']) . '</td>'
                        . '<td style="padding:8px;text-align:center;color:' . $color . ';font-weight:bold;">' . (int)$item['cantidad'] . '</td>'
                        . '<td style="padding:8px;text-align:center;">' . $threshold . '</td></tr>';
                }
                $html = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;color:#1f2937;"><div style="max-width:640px;margin:auto;padding:24px;border:1px solid #e5e7eb;">'
                    . '<h2>Alerta de inventario bajo - PIXELVAULT</h2><p>' . count($items) . ' producto(s) requieren revisión.</p>'
                    . '<table style="width:100%;border-collapse:collapse;"><thead><tr><th>Producto</th><th>Stock</th><th>Umbral</th></tr></thead><tbody>' . $rows . '</tbody></table></div></body></html>';
                $dedupe = 'stock:' . gmdate('YmdHis') . ':' . substr(hash('sha256', implode(',', $ids) . random_bytes(8)), 0, 16);
                $queued = automation_queue_email('alerta_stock_bajo', $dedupe, automation_cfg()['admin_email'],
                    'PIXELVAULT - ' . count($items) . ' producto(s) con stock bajo', $html);
                if ($queued) {
                    $mark = $pdo->prepare('UPDATE producto SET alerta_stock_enviada=1 WHERE id=?');
                    foreach ($ids as $id) $mark->execute(array($id));
                }
            }
            $pdo->commit();
            return array('acciones' => (int)$reset + ($queued ? count($items) : 0), 'repuestos' => (int)$reset,
                'productos_bajo_stock' => count($items), 'umbral_stock' => $threshold, 'correos_encolados' => $queued ? 1 : 0,
                'sin_correo_admin' => $items && !$queued);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    });
}

function automation_run_email_queue() {
    return automation_execute('cola_correos', function () {
        $result = automation_send_queued_emails(10);
        $result['acciones'] = (int)$result['enviados'] + (int)$result['reintentos'];
        $result['correos_enviados'] = (int)$result['enviados'];
        return $result;
    });
}
