<?php
require_once __DIR__ . '/../helpers/env.php';

return array(
    'api_key' => pv_env('GEMINI_API_KEY', ''),
    'model' => pv_env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
    'timeout' => max(5, (int)pv_env('GEMINI_TIMEOUT', '25')),
    'retries' => max(1, min(3, (int)pv_env('GEMINI_RETRIES', '3'))),
    'retry_delay_ms' => max(250, min(3000, (int)pv_env('GEMINI_RETRY_DELAY_MS', '2000'))),
    'max_len' => 500,
    'max_output_tokens' => 700
);
