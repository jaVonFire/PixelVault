# Arquitectura de PixelVault

## Esquema general

```
┌─────────────────────────────┐
│         FRONTEND            │
│  (HTML + JavaScript + CSS)  │
│                            │
│  index.html                 │
│  views/*.html               │
│  assets/js/*.js             │
│    └─ api.js (cliente API)  │
└─────────────┬───────────────┘
              │  Peticiones HTTP (GET/POST/PUT/DELETE)
              │  enviadas en JSON, con sesion PHP (cookies)
              ▼
┌─────────────────────────────┐
│      BACKEND / API          │
│  (PHP 8 + REST)             │
│                            │
│  api/index.php   →  router  │
│  api/controllers/*.php      │
│  api/helpers/*.php          │
│  api/config/db.php (PDO)    │
└─────────────┬───────────────┘
              │  Consultas SQL con PDO (sentencias preparadas)
              ▼
┌─────────────────────────────┐
│      BASE DE DATOS          │
│  (MySQL / MariaDB)          │
│                            │
│  BD: pixelvault             │
│  database/pixelvault.sql    │
└─────────────────────────────┘
```

## ¿Cómo se comunican las capas?

### 1. Frontend → Backend/API
- Toda la lógica de presentación vive en el frontend: `index.html` (inicio), `views/products.html` (catálogo), `views/account.html` (login/registro), `views/profile.html` (perfil y pedidos del usuario), `views/admin.html` (panel admin), `views/about.html`.
- El archivo `assets/js/api.js` es el **cliente de la API**: expone `apiGet(action, params)` y `apiSend(method, action, body, params)`.
- Todas las peticiones van a un **punto de entrada único**: `api/index.php?action=<nombre>`, usando los métodos HTTP estándar (GET para consultar, POST para crear, PUT para actualizar, DELETE para eliminar).
- La comunicación usa cookies de sesión (`credentials: same-origin`), de modo que el servidor sabe qué usuario está autenticado.

### 2. Backend/API (lógica de negocio)
- `api/index.php` actúa como **router**: lee el parámetro `action` y lo despacha al controlador correspondiente (auth, productos, categorias, carrito, pedidos, admin).
- Cada controlador contiene la **lógica de negocio**:
  - Validación de datos y credenciales.
  - Reglas de stock e inventario.
  - Cálculo de totales.
  - Transacciones al confirmar un pedido.
- `api/helpers/auth.php` implementa la **seguridad** con funciones `require_login()` (debe estar autenticado) y `require_admin()` (debe tener rol `admin`). Esto restringe las operaciones administrativas.
- `api/helpers/response.php` estandariza las respuestas JSON (`json_ok` / `json_error`).

### 3. Backend/API → Base de datos
- La conexión se realiza con **PDO** (`api/config/db.php`), usando **sentencias preparadas** para evitar inyección SQL.
- Cada controlador ejecuta consultas sobre las tablas `usuario`, `categoria`, `producto`, `pedido`, `detalle_pedido` y `carrito`.
- El esquema completo está documentado en `database/pixelvault.sql`.

## Justificación de la arquitectura
Se eligió una arquitectura **cliente-servidor de 3 capas (Frontend / Backend / Base de datos)** porque:
- Separa la presentación, la lógica de negocio y la persistencia, facilitando el mantenimiento y la evolución.
- El backend centraliza las reglas de negocio y la seguridad (roles, validaciones), evitando depender del navegador.
- Permite que cualquier interfaz futura consuma la misma API.

No se usó un framework más pesado porque el alcance de la actividad se cubre de forma clara y didáctica con PHP puro + PDO, lo que facilita entender cada capa.
