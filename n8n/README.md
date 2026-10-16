# n8n — Orquestador del Chatbot IA

Workflow de n8n que genera las respuestas del chatbot de PixelVault con un modelo de IA,
filtrando, armando el prompt, validando la respuesta y guardando la bitácora en MySQL.

- `workflow-chatbot-ia.json` — el workflow (importable en n8n)
- La configuración del lado PHP vive en `api/config/n8n_config.php`

## 1. Qué es n8n en este proyecto

[n8n](https://n8n.io) es un orquestador de workflows. Está **entre el backend PHP y el
IA** y se encarga de filtrar, armar el prompt, llamar al modelo, validar la respuesta y
guardar la conversación.

Antes, el chatbot era un motor de palabras clave dentro de `assets/js/chat.js`
(`getBotResponse`), sin IA y sin conexión con el backend. Ahora la pregunta sale del
navegador, pasa por n8n y vuelve redactada en lenguaje natural.

## 2. Flujo de datos

```
Navegador (chat.js)
   │  POST api/index.php?action=chat   { mensaje, lang }
   ▼
api/controllers/chat.php  ← valida, acota, arma contexto (sesion, usuario, catálogo real)
   │  POST http://localhost:5678/webhook/pixelvault-bot
   ▼
n8n:  Webhook → Guardarrail → IF → [Memoria] → Prompt → OpenAI → Respuesta → Guardar log → Respond
   │  { reply, fuente }
   ▼
api/controllers/chat.php  ← devuelve { reply, fuente }
   ▼
Navegador: pinta la respuesta con la etiqueta del origen
```

Tres niveles de degradación, de arriba hacia abajo:

| Situación | Quién responde | Etiqueta en el chat |
|---|---|---|
| Todo bien | La IA | `IA / n8n` (verde) |
| Pregunta fuera de temario o inyección | El guardarraíl, sin gastar tokens | `guardarrail` (dorado) |
| La IA se cae | n8n responde con un mensaje de ayuda | `respaldo` (rojo) |
| n8n apagado | El bot local de `chat.js` | `local` (gris) |

## 3. Los tres nodos de robustez (A + B + C)

### A) Guardarraíl — nodos `Guardarrail` + `Pregunta valida?`

Filtra **antes** de llamar a la IA. Tres listas: `INYECCION`, `FUERA_DE_TEMARIO` y `TEMA`.
Si la pregunta no encaja en ninguna, el nodo `IF` la manda directo a la respuesta fija,
sin gastar un solo token. Beneficio doble: ahorro de costo y la IA no puede salirse del
tema ni inventar precios.

### B) Red de seguridad — nodo `OpenAI Chat` + nodo `Respuesta`

- `retryOnFail: true`, `maxTries: 3`, `waitBetweenTries: 2000` → reintenta solo.
- `onError: "continueRegularOutput"` → un fallo no corta la ejecución.
- El nodo `Respuesta` valida que haya contenido: si está vacío devuelve un mensaje de
  respaldo útil, en el idioma del usuario, en vez de un `null`.

### C) Bitácora y memoria — nodos `Memoria (chat_log)` y `Guardar log`

**La tabla `chat_log` es la memoria real de la conversación.** Escribió y lee n8n:

- `Guardar log` (operación `insert`) guarda cada intercambio con su `fuente`.
- `Memoria (chat_log)` (`executeQuery`) recupera los últimos 8 intercambios de esa sesión
  y los mete al prompt.

Detalles importantes:

- El `sesion` viaja como **parámetro `?`**, nunca concatenado en el texto SQL.
- El `insert` usa el modo `insert` del nodo (no SQL a mano) porque **el texto viene de una
  IA**: el nodo lo escapa correctamente.
- `GROUP_CONCAT` + `COALESCE` garantizan que la consulta devuelva **siempre una fila**.
  Si devolviera 0, n8n detendría el flujo y el chat se quedaría mudo.
- `alwaysOutputData: true` en ambos nodos, por la misma razón.

## 4. Archivos que cambiaron

| Archivo | Qué hace |
|---|---|
| `api/config/n8n_config.php` | URL del webhook, token, timeout, tamaño máximo. Env: `PV_N8N_WEBHOOK`, `PV_N8N_TOKEN`, `PV_N8N_ON`. |
| `api/controllers/chat.php` | Endpoint `action=chat`. Genera el `sesion` anónimo, arma el contexto y hace el POST a n8n. |
| `api/controllers/admin.php` | `chatlog_index()`: métricas de la bitácora para el panel admin. |
| `api/index.php` | Registra `case 'chat'` y `case 'chat_log'`. |
| `assets/js/chat.js` | `sendChat()` llama a la API, indicador de escritura real, `sanitizeRich()` y `chatBadge()`. |
| `assets/js/admin.js` | `renderAdminChat()`: gráfica de 7 días, reparto por origen y últimos 30 intercambios. |
| `assets/js/i18n.js` | Claves `chat_badge_*`, `chat_fuente_*`, `admin_chat*` en ES y EN. |
| `assets/css/styles.css` | Etiqueta `.src` con color por origen. |
| `views/admin.html` | Nueva pestaña **VAULT-BOT** en el panel admin. |
| `database/pixelvault.sql` | Tabla `chat_log` (sección 7). |
| `database/chat_log.sql` | Migración para bases ya creadas. |
| `n8n/workflow-chatbot-ia.json` | Workflow importable. |

## 5. Puesta en marcha

1. Correr la migración de `chat_log` (o importar `database/pixelvault.sql` completo).
2. Abrir n8n y crear la credencial **MySQL**:
   - Host `127.0.0.1`, Database `pixelvault`, user `root`, password vacía, puerto `3306`.
   - Nombrarla `PixelVault MySQL` para que coincida con el workflow.
3. **Settings → Variables**: crear `OPENAI_API_KEY`.
4. **Workflows → Import from File** → `n8n/workflow-chatbot-ia.json`.
5. Activar el workflow.
6. Probar:

```powershell
Invoke-RestMethod -Uri "http://localhost:5678/webhook/pixelvault-bot" `
  -Method Post -ContentType "application/json" `
  -Body '{"sesion":"test","mensaje":"Que juegos tienen?","lang":"es","catalogo":[]}'
```

## 6. Cómo comprobar que funciona

En el chat, la etiqueta bajo la respuesta dice quién respondió:

| Etiqueta | Significa |
|---|---|
| `IA / n8n` | Funciona todo el flujo |
| `guardarrail` | La pregunta era fuera de temario o inyección; la IA no se llamó |
| `respaldo` | La IA falló, n8n respondió con el mensaje de ayuda |
| `local` | n8n está apagado; respondió el bot de palabras clave |

Para ver la bitácora: panel admin → pestaña **VAULT-BOT**. Ahí salen el total de
interacciones, la gráfica de los últimos 7 días, el reparto por origen y los últimos 30
intercambios. Si la pestaña sale vacía, la IA nunca contestó: revisá el nodo `Guardar log`.

## 7. Verificación realizada

- `php -l` sin errores en `chat.php`, `admin.php`, `index.php` y `n8n_config.php`.
- `node --check` sin errores en `chat.js`, `admin.js` e `i18n.js`.
- JSON del workflow parseado; **50 comprobaciones automáticas** sobre su estructura y
  ejecutando los nodos `Code` de verdad: detección del guardarraíl (9 casos), validación y
  respaldo, recorte de texto, y que el SQL use parámetros y las columnas reales.
- SQL ejecutado contra MySQL: tabla creada, `GROUP_CONCAT` devuelve el historial
  intercalado y **una sola fila** para sesiones inexistentes.
- Flujo completo PHP → n8n → PHP con un mock que ejecuta los nodos reales: primera
  pregunta sin memoria (`memoria:false`), segunda y tercera con memoria
  (`memoria:true`), usuario `demo` propagado y `sesion` estable.
- Degradación comprobada: con la IA caída → `fuente: respaldo`; con n8n apagado → PHP
  responde 503 y `chat.js` cae al bot local.

## 8. Limitaciones conocidas

- Requiere n8n levantado y con el workflow **activado**.
- Si n8n está apagado no hay memoria ni bitácora: el bot local no las usa.
- Las reglas de negocio viven en el nodo `Prompt`: cambiarlas = editar el workflow.
- El guardarraíl es por palabras clave. Es suficiente para una tienda, pero no reemplaza
  un clasificador.
- El historial se limita a 8 intercambios para controlar el costo de tokens.

---

# n8n — Recordatorio de carrito y factura por correo

Dos workflows adicionales que usan n8n como orquestador:

- `workflow-recordatorio-carrito.json` — avisa al cliente cuando deja el carrito sin comprar.
- `workflow-factura-pedido.json` — envía por correo una factura al confirmar la compra.

## A. Recordatorio de carrito (campanita)

### Flujo

```
n8n Schedule (cada 5 s)
   │  MySQL: INSERT ... SELECT  (avisos con deduplicación)
   ▼
Tabla `notificacion`  (la escribe n8n)
   │  GET api/index.php?action=notificaciones   (lee el frontend, cada 10 s)
   ▼
Navegador: campanita (badge de no leídas) + sonido (Web Audio) + panel
```

### Reglas

- **Aviso 1 (`carrito_1`)**: carrito inactivo **≥ 60 s**, solo clientes, sin un aviso 1
  posterior a la última actividad.
- **Aviso 2 (`carrito_2`)**: carrito inactivo **≥ 120 s**, con un aviso 1 enviado **≥ 60 s**
  antes, y sin un aviso 2 posterior.
- Nueva actividad en el carrito (`carrito.agregado_el`) **reinicia** los avisos.
- Al comprar, `api/controllers/pedidos.php` borra los avisos de carrito del usuario.
- Máximo 2 avisos por abandono, y para.

El Schedule corre cada **5 s** y los umbrales se miden en segundos, así el aviso 1 sale a los
**~60–65 s** y el aviso 2 **60 s después (~120–125 s)** —medido en vivo: 61 s y 121 s—, sin las
demoras de hasta 2–3 min que aparecían al medir en minutos con un tick de 1 min. Como el workflow
corre tan seguido, las ejecuciones exitosas se autoborran (`EXECUTIONS_DATA_PRUNE`,
`EXECUTIONS_DATA_MAX_AGE=24` en `INICIAR-n8n.cmd`/`.env`).

La deduplicación usa `NOT EXISTS ... AND n.creado_el >= c.agregado_el`, por lo que cada
nueva actividad vuelve a habilitar los avisos sin spamear.

### Fundamentos de la base de datos

- `database/migracion_recordatorios.sql` — para bases ya creadas: agrega
  `carrito.agregado_el` y crea la tabla `notificacion`.
- `database/pixelvault.sql` — ya incluye ambos cambios (secciones 6 y 8).

## B. Factura por correo

### Flujo

```
api/controllers/pedidos.php  (tras COMMIT, fail-safe)
   │  POST http://localhost:5678/webhook/pixelvault-factura
   ▼
n8n:  Webhook → Code (HTML factura) → Send Email (SMTP) → Respond
```

- El frontend nunca habla con n8n: el backend dispara el webhook. Si n8n está apagado,
  **la compra igual se completa** (`n8n_webhook_factura()` no lanza excepción).
- La URL se configura en `api/config/n8n_config.php` → `webhook_factura`
  (env `PV_N8N_WEBHOOK_FACTURA`).

### Credencial SMTP (Gmail)

1. Activar 2FA en la cuenta de Google y generar un **App Password** de 16 caracteres.
2. En n8n: **Credentials → New → SMTP**, nombrarla **`PixelVault SMTP`**:
   - Host `smtp.gmail.com`, Port `465`, **SSL/secure: sí**
   - User: tu correo Gmail · Password: el App Password
3. Ajustar el remitente en el nodo **Enviar correo** (`fromEmail`).

### Puesta en marcha de ambos

1. Correr `database/migracion_recordatorios.sql` (o importar `pixelvault.sql` completo).
2. Importar `workflow-recordatorio-carrito.json` y `workflow-factura-pedido.json`.
3. Activar el de recordatorio y asociar la credencial SMTP al de factura.
4. **Reiniciar n8n** para registrar el `Schedule Trigger` (los cambios de activación no
   toman efecto con n8n corriendo).

### Archivos nuevos

| Archivo | Qué hace |
|---|---|
| `database/migracion_recordatorios.sql` | Migración (`agregado_el` + tabla `notificacion`). |
| `api/controllers/notificaciones.php` | `notificaciones_index` y `notificacion_leer`. |
| `api/controllers/pedidos.php` | Borra avisos al comprar y dispara el webhook de factura. |
| `assets/js/notifications.js` | Campanita: poll 20 s, badge, panel, sonido, marcar leídas. |
| `assets/css/styles.css` | `.notif-*` y animaciones (`notifRing`, reusa `badgePulse`). |
| `components/notification-widget.html` | Copia de referencia del widget (las vistas ya lo incluyen). |
| `n8n/workflow-recordatorio-carrito.json` | Workflow importable. |
| `n8n/workflow-factura-pedido.json` | Workflow importable. |

### Cómo comprobar

- **Recordatorio**: dejar productos en el carrito logueado como cliente; en ~1 min aparece
  la campanita con sonido; ~1 min después, el segundo aviso.
- **Factura**: completar una compra; llega el correo con el HTML de la factura.
