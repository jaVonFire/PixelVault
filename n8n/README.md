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