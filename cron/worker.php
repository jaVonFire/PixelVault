<?php
/* Worker CLI: reemplaza los Schedule Trigger de los workflows de n8n. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../api/config/db.php';
require_once __DIR__ . '/../api/services/scheduled_automations.php';
require_once __DIR__ . '/../api/services/product_vision.php';

$once = in_array('--once', isset($argv) ? $argv : array(), true);
$pdo = db();
$lock = $pdo->query("SELECT GET_LOCK('pixelvault_native_automations', 0)")->fetchColumn();
if ((int)$lock !== 1) {
    fwrite(STDERR, "Ya hay otro worker de PixelVault ejecutandose.\n");
    exit(1);
}

$cfg = automation_cfg();
$tick = (int)$cfg['tick_seconds'];
$nextScheduled = array('postpurchase' => 0, 'reviews' => 0, 'stock' => 0, 'prune' => 0);
fwrite(STDOUT, "Worker de automatizaciones PixelVault iniciado (cada {$tick}s).\n");
if (!$cfg['mail_enabled']) {
    fwrite(STDOUT, "Correo deshabilitado: los mensajes se guardaran en la cola hasta configurar PV_MAIL_ENABLED=1.\n");
} elseif (!automation_mail_is_configured($cfg)) {
    fwrite(STDOUT, "Correo habilitado pero SMTP sin configurar: define PV_SMTP_PASSWORD en .env.\n");
}

try {
    do {
        $now = time();
        automation_run_reminders();

        if ($now >= $nextScheduled['postpurchase']) {
            automation_run_postpurchase();
            $nextScheduled['postpurchase'] = $now + 30;
        }
        if ($now >= $nextScheduled['reviews']) {
            automation_run_reviews();
            $nextScheduled['reviews'] = $now + 30;
        }
        if ($now >= $nextScheduled['stock']) {
            automation_run_low_stock();
            $nextScheduled['stock'] = $now + 30;
        }
        if ($cfg['mail_enabled']) automation_run_email_queue();

        if ($now >= $nextScheduled['prune']) {
            automation_prune_logs();
            vision_purge_expired_images($pdo);
            $nextScheduled['prune'] = $now + 86400;
        }
        if ($once) break;
        sleep($tick);
    } while (true);
} catch (Throwable $e) {
    fwrite(STDERR, 'Worker PixelVault detenido por error: ' . $e->getMessage() . "\n");
    error_log('PixelVault automation worker stopped: ' . $e->getMessage());
    exit(1);
} finally {
    $pdo->query("SELECT RELEASE_LOCK('pixelvault_native_automations')");
}

fwrite(STDOUT, "Ciclo completado.\n");
