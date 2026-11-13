# Workflows n8n: especificación histórica

Los archivos JSON de esta carpeta documentan los flujos construidos en los cortes anteriores. El aplicativo ya no llama a n8n ni necesita un servidor n8n para funcionar. Su lógica vigente está en el backend PHP; los eventos de carrito, compra y stock se disparan desde la aplicación, mientras `cron/worker.php` cubre tareas periódicas y reintentos.

Los archivos se conservan para comparar el comportamiento anterior con su implementación nativa:

| Workflow de referencia | Comportamiento implementado en PixelVault |
|---|---|
| `workflow-chatbot-ia.json` | `api/controllers/chat.php`: guardarraíl, contexto de catálogo, memoria en `chat_log`, llamada directa a Gemini y respuesta local si falta la clave o falla el servicio. |
| `workflow-recordatorio-carrito.json` | `api/services/scheduled_automations.php`: crea `carrito_1` tras 60 segundos y `carrito_2` después de otros 60 segundos; la campanita sigue leyendo la tabla `notificacion`. |
| `workflow-postcompra.json` | La compra dispara el procesamiento de pedidos, puntos, nivel, cupones, factura y avisos al admin que correspondan. El worker y el sondeo de la cuenta también cubren la solicitud de reseña entre 1 y 2 días después. |
| `workflow-stock-bajo.json` | La compra, el cambio de producto, el dashboard y el worker detectan stock bajo; al reponerlo, se habilita una futura alerta. |
| `workflow-factura-pedido.json` | Es una versión anterior basada en webhook. La factura quedó integrada en poscompra para evitar dos envíos por el mismo pedido. |

## Ejecución

Desde PowerShell, en la carpeta raíz del proyecto:

```powershell
C:\xampp\php\php.exe database\migrate_automation.php
C:\xampp\php\php.exe cron\worker.php
```

El worker conserva un bloqueo MySQL para evitar ejecuciones concurrentes. Los recordatorios corren cada 5 segundos; poscompra, reseñas y stock se revisan cada 30 segundos. Las acciones de carrito, compra y cambio de stock también se disparan desde las peticiones del aplicativo para que no dependan exclusivamente del worker. Los correos se guardan en `automatizacion_correo`; PHP los envía por SMTP/TLS cuando hay credenciales en `.env`.

Consulta el [README principal](../README.md) para instalación, `.env`, correo y migración segura. No importes estos JSON ni configures credenciales de n8n para ejecutar PixelVault.
