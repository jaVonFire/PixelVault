<?php
require_once __DIR__ . '/../helpers/env.php';

return array(
    'tick_seconds' => max(2, (int)pv_env('PV_AUTOMATION_TICK_SECONDS', '5')),
    'mail_enabled' => pv_env('PV_MAIL_ENABLED', '1') === '1',
    'mail_from' => pv_env('PV_MAIL_FROM', 'javierandresn12@gmail.com'),
    'mail_from_name' => pv_env('PV_MAIL_FROM_NAME', 'PIXELVAULT'),
    /* Vacío = usar el correo de la cuenta admin de PixelVault; PV_ADMIN_EMAIL lo puede fijar. */
    'admin_email' => trim(pv_env('PV_ADMIN_EMAIL', '')),
    'stock_alert_threshold' => max(0, (int)pv_env('PV_LOW_STOCK_THRESHOLD', '4')),
    'smtp_host' => trim(pv_env('PV_SMTP_HOST', 'smtp.gmail.com')),
    'smtp_port' => max(1, (int)pv_env('PV_SMTP_PORT', '465')),
    'smtp_security' => strtolower(trim(pv_env('PV_SMTP_SECURITY', 'ssl'))),
    'smtp_username' => trim(pv_env('PV_SMTP_USERNAME', 'javierandresn12@gmail.com')),
    'smtp_password' => pv_env('PV_SMTP_PASSWORD', ''),
    'app_url' => rtrim(pv_env('PV_APP_URL', 'http://localhost/pixelV'), '/'),
    'mail_max_attempts' => max(1, (int)pv_env('PV_MAIL_MAX_ATTEMPTS', '5')),
    'retention_days' => max(30, (int)pv_env('PV_AUTOMATION_RETENTION_DAYS', '90'))
);
