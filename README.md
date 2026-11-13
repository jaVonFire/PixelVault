# PixelVault

Tienda de merchandising gaming desarrollada como aplicación empresarial de tres capas:

```text
Frontend HTML/JS → API REST PHP → MySQL/MariaDB
```

El aplicativo conserva su autenticación, catálogo, carrito, pedidos, panel administrativo, chatbot y notificaciones. Las reglas de los workflows anteriores ahora se ejecutan en PHP; n8n no es requisito de ejecución.

## Requisitos

- XAMPP con Apache, PHP y MySQL/MariaDB.
- PHP CLI disponible en `C:\xampp\php\php.exe`.
- Para respuestas abiertas del chatbot configura una clave de Gemini AI Studio en `.env`. Sin clave, el bot solo responde preguntas frecuentes y catálogo con reglas locales; para lo demás indica que no puede verificar la respuesta.
- Los correos se guardan en MySQL hasta configurar SMTP en `.env`; no se descartan mientras falten credenciales.

## Instalación nueva

1. Inicia Apache y MySQL en XAMPP.
2. Importa `database/pixelvault.sql` desde phpMyAdmin. **Este SQL crea de nuevo la base `pixelvault`; úsalo solo para una instalación nueva.**
3. Copia `.env.example` como `.env` y completa las variables que necesites. El `.env` real está excluido de Git.
4. Abre `http://localhost/pixelV`.
5. Inicia el worker en una terminal desde la carpeta del proyecto:

   ```powershell
   C:\xampp\php\php.exe cron\worker.php
   ```

   Déjalo ejecutándose para cubrir revisiones programadas, tareas de respaldo, reintentos de correo y limpieza. Carrito, compra y cambio de stock también disparan sus reglas desde la aplicación. Termina con `Ctrl+C`. Para ejecutar un ciclo manual, usa `C:\xampp\php\php.exe cron\worker.php --once`.

## Migrar una base existente

Haz una copia de seguridad de la base y ejecuta el instalador idempotente desde la carpeta del proyecto:

```powershell
C:\xampp\php\php.exe database\migrate_automation.php
```

Lee la conexión desde las variables del sistema o desde `.env`. La migración agrega las columnas y tablas que falten, conserva los datos existentes y marca los pedidos históricos para que no se vuelvan a facturar ni generen puntos retroactivos. Después inicia el worker con el comando anterior.

## Configuración opcional

### Chatbot IA

Define `GEMINI_API_KEY` en `.env` con una clave de Google AI Studio y, si hace falta, cambia `GEMINI_MODEL`. El backend PHP se comunica directamente con Gemini, valida el tema, agrega datos actuales del catálogo y guarda el historial en `chat_log`. Sin clave o si falla la consulta, ofrece reglas conocidas o coincidencias reales del catálogo; para las demás preguntas explica que no puede verificarlas en lugar de inventar una respuesta.

### Visión de productos desde el chatbot

El botón de cámara del chatbot permite a un usuario con sesión enviar una foto del producto. El navegador la reescala y convierte a JPEG para quitar metadatos de la imagen, con un tamaño final inferior a 1.8 MB; antes de subirla se exige consentimiento explícito porque el análisis usa la clave y el modelo Gemini ya configurados. No deben enviarse fotos de personas, documentos ni datos personales. La solicitud se guarda en `vision_solicitud`, asociada al usuario, fecha y hash de la imagen; el archivo queda en `storage/vision`, protegido por Apache, y el worker elimina la foto a los 30 días conservando la auditoría.

Gemini solo puede proponer hasta tres IDs del catálogo activo y la respuesta se limita a un esquema JSON. El servidor descarta IDs ajenos al catálogo, no muestra candidatos con confianza inferior al 45 % y carga nombre, precio y stock desde MySQL. El cliente revisa las coincidencias y confirma cuál agregar; la operación vuelve a validar el producto y el stock dentro de una transacción. Una confianza inferior al 75 % se marca como baja. Los errores temporales del proveedor se reintentan con espera y se explican por separado de una foto que no coincide con el catálogo. El análisis no agrega nada automáticamente ni usa n8n.

En una base existente, ejecuta `database/migrate_automation.php` después de actualizar el proyecto para crear `vision_solicitud`. Para la entrega académica, documenta al menos 20 fotos reales de productos con distintos ángulos e iluminación, cuántas identificó correctamente y qué productos confundió. El uso de Gemini depende de los límites gratuitos disponibles para la clave; no habilites facturación para este proyecto.

### Correos

La aplicación y el worker conservan los correos en `automatizacion_correo` hasta habilitar el envío:

```dotenv
PV_MAIL_ENABLED=1
PV_MAIL_FROM=tienda@tudominio.com
PV_MAIL_FROM_NAME=PIXELVAULT
PV_ADMIN_EMAIL=
PV_SMTP_HOST=smtp.gmail.com
PV_SMTP_PORT=465
PV_SMTP_SECURITY=ssl
PV_SMTP_USERNAME=admin@tudominio.com
PV_SMTP_PASSWORD=contraseña-de-aplicación
PV_APP_URL=http://localhost/pixelV
```

El destinatario de administrador se toma de la primera cuenta con rol `admin`; `PV_ADMIN_EMAIL` permite fijarlo. El workflow anterior tenía como destinatario `javierandresn12@gmail.com`. El envío nativo usa PHP por SMTP con TLS, sin n8n ni librerías de pago. Para Gmail usa `smtp.gmail.com`, puerto `465`, seguridad `ssl`, tu cuenta y una contraseña de aplicación. Completa `PV_SMTP_USERNAME` y `PV_SMTP_PASSWORD` en `.env`; no guardes la contraseña en Git. La cola conserva mensajes mientras falte configuración. Si el servidor rechaza un mensaje, la cuenta admin muestra el error; los fallos temporales se reintentan y desde **Automatizaciones** puedes reencolar un correo fallido.

### Base de datos

Las variables opcionales son `PV_DB_HOST`, `PV_DB_NAME`, `PV_DB_USER` y `PV_DB_PASSWORD`. Los valores predeterminados corresponden a XAMPP local (`127.0.0.1`, `pixelvault`, `root`, contraseña vacía).

## Flujos que ahora ejecuta PHP

- **Chatbot**: validación del tema, contexto actual de catálogo, historial MySQL y Gemini. Las respuestas locales cubren solo reglas conocidas y catálogo; no inventan respuestas para preguntas desconocidas.
- **Recordatorios de carrito**: primer aviso tras 60 segundos sin actividad y segundo aviso después de otros 60 segundos.
- **Poscompra**: puntos y nivel, cupón cuando aplica, factura por correo y aviso al administrador cuando se cumplen las reglas.
- **Reseña**: correo entre 1 y 2 días después de procesar el pedido, una sola vez.
- **Stock bajo**: correo al administrador cuando quedan 4 unidades o menos (configurable con `PV_LOW_STOCK_THRESHOLD`); una reposición sobre el umbral vuelve a habilitar la alerta.

Las ejecuciones se registran en `automatizacion_ejecucion`, los cambios de poscompra en `automatizacion_log` y los correos pendientes/reintentos en `automatizacion_correo`. El panel de administración muestra la bitácora, la cola, el destinatario efectivo y el inventario bajo.

Los JSON de `n8n/` se conservan como especificación de los flujos anteriores. Consulta [n8n/README.md](n8n/README.md) para ver la correspondencia con PHP.
