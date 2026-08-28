# PixelVault - Manual de instalacion y ejecucion

Aplicacion empresarial: tienda de merchandising gaming (ESP/ING).
Stack: HTML + JS (frontend) + PHP 8 / REST (backend) + MySQL/MariaDB (base de datos).

## Requisitos
- XAMPP instalado (Apache, PHP 8+, MySQL/MariaDB).
- Navegador moderno.

## Instalacion (paso a paso)

### 1. Copiar el proyecto
Colocar la carpeta completa del proyecto en el servidor web de XAMPP:

```
C:\xampp\htdocs\pixelvault\
```

(Estructura: `index.html`, `views/`, `assets/`, `api/`, `database/`, `docs/`)

### 2. Iniciar los servicios de XAMPP
Abrir el Panel de Control de XAMPP y pulsar **Start** en:
- **Apache** (sirve la aplicacion en el puerto 80).
- **MySQL** (MariaDB, la base de datos).

### 3. Importar la base de datos
Abrir `http://localhost/phpmyadmin`:
1. Ir a la pestana **Importar**.
2. Seleccionar el archivo `database/pixelvault.sql`.
3. Pulsar **Continuar**.

Esto crea la base de datos `pixelvault` con sus tablas, relaciones y datos iniciales.

### 4. Abrir la aplicacion
En el navegador ir a:

```
http://localhost/pixelvault
```

## Credenciales de prueba

| Rol       | Usuario  | Contrasena      |
|-----------|----------|-----------------|
| Admin     | `admin`  | `pixelvault2026`|
| Cliente   | `demo`   | `demonio123`    |
| Cliente   | `kratosX`| `kratos2024`    |

- El **boton Admin** en el menu superior habilita el panel administrativo al iniciar sesion como `admin`.
- Cualquier usuario puede registrarse como cliente desde "Mi Cuenta > Crear Cuenta".

## Arquitectura
Ver `docs/ARQUITECTURA.md` (Frontend -> Backend/API -> Base de datos).
Ver `docs/DIAGRAMA_ER.md` para el modelo de datos.

## Nota de configuracion
La conexion a la base de datos se define en `api/config/db_config.php`
(por defecto: host `127.0.0.1`, base `pixelvault`, usuario `root`, sin contrasena,
que es la configuracion estandar de XAMPP).
