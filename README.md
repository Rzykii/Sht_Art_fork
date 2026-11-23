# SHT_art — versión con blog y checkout renovado

## Requisitos
- PHP 8.0 o superior (recomendado 8.1+).
- MySQL 5.7+ / MariaDB 10.4+.
- Extensiones PHP: PDO MySQL, fileinfo y mbstring.
- Servidor local XAMPP, Laragon, WAMP o hosting PHP.

## Instalación
1. Copia la carpeta `Sht_art` dentro de `htdocs` (XAMPP) o la raíz pública del hosting.
2. Crea una base de datos MySQL llamada `Sht_art`.
3. Revisa `config/database.php` y configura host, usuario y contraseña.
4. Abre `http://localhost/Sht_art/install.php`.
5. El instalador crea/actualiza las tablas de usuarios, productos, dibujos, pedidos y blog. También crea un catálogo de muestra si está vacío.
6. Acceso inicial de administrador: `admin` / `admin123`. Inicia sesión y cambia la contraseña desde el panel.
7. Elimina o renombra `install.php` cuando la instalación termine.

## Blog
- `blog.php`: listado público de publicaciones.
- `blog-post.php?id=...`: lectura, reacciones y comentarios.
- `register.php`: registro de cuentas de usuario.
- `blog-admin.php`: gestión de publicaciones y moderación de comentarios (solo admin).
- El administrador puede guardar borradores o publicar.
- Solo usuarios con sesión iniciada pueden comentar y dar «me gusta».
- Las publicaciones y comentarios se guardan en MySQL.

## Checkout
El checkout replica la organización por pasos del HTML de referencia: contacto, entrega, facturación, pago y resumen.
- Entrega: domicilio o punto/sucursal de retiro.
- Incluye región, comuna, dirección, complemento y RUT.
- Código de prueba `SHT10`: 10% de descuento.
- La versión incluida registra pedidos con transferencia bancaria manual. No se realizan cobros automáticos.
- Webpay, MercadoPago, Khipu y Flow requieren credenciales, contratos y desarrollo de integración independientes.
- El costo de despacho se coordina por correo y no se suma automáticamente al total.

## Estructura añadida
- `blog.php`, `blog-post.php`, `blog-admin.php`, `register.php`
- `includes/auth.php`, `includes/csrf.php`, `includes/media.php`
- `css/blog.css`, `css/checkout.css`
- `terminos.php`, `privacidad.php`

## Seguridad básica
- Contraseñas almacenadas con `password_hash`.
- Consultas SQL preparadas para entradas del usuario.
- Tokens CSRF en formularios de blog, administración y checkout.
- Validación de permisos del panel de administración en el servidor.
