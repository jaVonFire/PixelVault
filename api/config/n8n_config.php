<?php
/* ============================================================
   Configuracion de n8n (orquestador del chatbot IA)

   El frontend NUNCA habla directo con n8n. El flujo es:
     chat.js (navegador) -> api/index.php?action=chat (este backend)
                         -> webhook de n8n -> modelo de IA -> respuesta

   Variables de entorno opcionales (tienen valor por defecto):
     PV_N8N_ON=0                desactiva n8n y deja solo el bot local
     PV_N8N_WEBHOOK=http://...  URL del webhook de n8n
     PV_N8N_TOKEN=secreto       header X-N8N-Token (opcional)
   ============================================================ */
return array(
    'activo'    => getenv('PV_N8N_ON') !== '0',
    'webhook'   => getenv('PV_N8N_WEBHOOK') ?: 'http://localhost:5678/webhook/pixelvault-bot',
    'token'     => getenv('PV_N8N_TOKEN') ?: '',
    'timeout'   => 90,   /* segundos de espera maxima al webhook */
    'max_len'   => 500   /* longitud maxima del mensaje del usuario */
);