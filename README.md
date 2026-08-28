# PixelVault - Tienda Gaming

Tienda de merchandising y productos de videojuegos desarrollada como aplicacion empresarial de 3 capas:
**Frontend (HTML/JS) -> Backend/API (PHP REST) -> Base de datos (MySQL/MariaDB)**.

## Funcionalidades
- Autenticacion con roles (Cliente / Administrador) y contrasenas hasheadas (bcrypt).
- Gestion de productos (CRUD) desde la base de datos (no hardcodeados).
- Control de inventario y alertas de stock bajo.
- Carrito de compras persistente y pedidos (con historial por usuario).
- Panel administrativo con metricas del negocio.
- Internacionalizacion ES / ING (selectivamente en todas las vistas).

## Estructura
```
index.html                 Inicio (landing) y destacados
views/                     Productos, Cuenta, Perfil, Admin, Acerca
assets/                    CSS + JavaScript (cliente de la API, i18n, etc.)
api/                       Backend PHP (router, controladores, helpers)
database/pixelvault.sql    Esquema y datos iniciales de la base de datos
docs/                      Arquitectura, diagrama ER e instalacion
```

## Instalacion rapida
1. Copiar la carpeta a `C:\xampp\htdocs\pixelvault`.
2. Iniciar Apache y MySQL en XAMPP.
3. Importar `database/pixelvault.sql` en phpMyAdmin.
4. Abrir `http://localhost/pixelvault`.

Contacta con la documentacion en `docs/` para mas detalle.
"# PixelVault" 
"# PixelVault" 
