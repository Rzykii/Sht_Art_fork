# SHT_art — tienda, blog y pagos con Mercado Pago

## Requisitos
- PHP 8.0 o superior (recomendado PHP 8.1+).
- MySQL 5.7+ / MariaDB 10.4+.
- Extensiones PHP: PDO MySQL, cURL y mbstring.
- HTTPS habilitado en el dominio del hosting.

## Instalación en InfinityFree
1. Crea tu cuenta/sitio y habilita HTTPS.
2. En el panel de InfinityFree abre **MySQL Databases** y crea una base de datos.
3. Copia el hostname, nombre de base de datos, usuario y contraseña que entrega el panel. No asumas que el host es `localhost`.
4. Sube todos los archivos del proyecto a `htdocs` (o a la subcarpeta pública que elijas).
5. Copia `config/database.local.php.example` como `config/database.local.php` y completa los cuatro datos MySQL del panel.
6. Copia `config/mercadopago.local.php.example` como `config/mercadopago.local.php` y configura el Access Token y URL pública.
7. Abre `https://TU-SITIO/install.php` una sola vez para crear/actualizar las tablas.
8. Inicia sesión con el administrador inicial `admin` / `admin123`, cambia la contraseña y elimina `install.php`.

Los archivos `database.local.php` y `mercadopago.local.php` están excluidos de Git. No publiques credenciales ni las compartas en capturas.

## Mercado Pago en InfinityFree

La tienda usa Checkout Pro mediante Orders API. El comprador paga en Mercado Pago y vuelve a `checkout-result.php`. La aplicación consulta la API autenticada de Mercado Pago para verificar el estado, monto, moneda y referencia del pedido antes de marcarlo como pagado.

### 1. Credenciales

1. Crea una aplicación en [Mercado Pago Developers](https://www.mercadopago.cl/developers/panel).
2. Obtén el Access Token de prueba (`TEST-...`) para comenzar. Para producción, usa el token de producción (`APP_USR-...`) cuando hayas probado todo.
3. En el servidor, copia `config/mercadopago.local.php.example` a `config/mercadopago.local.php`.
4. Completa el archivo:

```php
<?php
$MP_LOCAL_ACCESS_TOKEN = 'TEST-PEGA_AQUI_TU_ACCESS_TOKEN';
$MP_LOCAL_WEBHOOK_SECRET = '';
$MP_LOCAL_APP_URL = 'https://TU-SUBDOMINIO.infinityfreeapp.com';
```

- `MP_LOCAL_ACCESS_TOKEN`: Access Token de Mercado Pago. Nunca lo publiques.
- `MP_LOCAL_APP_URL`: URL HTTPS exacta donde está instalada la tienda. Si usas una subcarpeta, inclúyela. No agregues barra final.
- `MP_LOCAL_WEBHOOK_SECRET`: no es necesario para el modo de verificación por retorno descrito aquí; déjalo vacío en InfinityFree gratuito.

### 2. Restricciones y verificación

- Las solicitudes salientes PHP cURL a APIs externas usan HTTPS por el puerto 443, permitido por InfinityFree. La disponibilidad de cURL/extensión y la conexión real deben comprobarse desde la cuenta.
- InfinityFree gratuito bloquea solicitudes automáticas entrantes a tu web, incluidos webhooks de plataformas externas. Por eso **no configures el webhook como mecanismo necesario para confirmar pagos**.
- Al regresar el comprador, `checkout-result.php` consulta `GET /v1/orders/{id}` directamente a Mercado Pago y valida referencia externa, monto y moneda CLP.
- La página vuelve a consultar cada 15 segundos mientras el pedido siga pendiente.

### 3. Pruebas y publicación

1. Configura un Access Token de prueba y utiliza las cuentas/medios de prueba que proporciona Mercado Pago.
2. Completa una compra y verifica que el pedido solo aparezca pagado después de que la API confirme el estado `processed`.
3. Prueba un pago cancelado/expirado y revisa el estado y el stock.
4. Solo después de validar el flujo cambia al Access Token de producción.
5. Nunca marques un pedido como pagado basándote solo en el parámetro `status` del navegador.

**Limitación importante:** si el comprador paga y cierra la pestaña antes de volver a la tienda, el pedido puede permanecer pendiente porque InfinityFree no recibe el webhook y no ofrece cron en el plan gratuito para sincronizarlo en segundo plano. Para confirmación automática aunque el comprador no regrese, necesitas un hosting que admita webhooks entrantes y tareas programadas, o un servicio externo de sincronización seguro.

## Checkout
- Formulario de contacto, entrega, facturación, pago y resumen.
- Entrega a domicilio o retiro en punto/sucursal.
- Región, comuna, dirección, complemento y RUT.
- Código promocional `SHT10`: 10% de descuento.
- Mercado Pago y transferencia bancaria manual.
- El costo de despacho se coordina por correo y no se suma automáticamente al total.

## Blog
- `blog.php`: portada editorial con publicaciones.
- `blog-post.php?id=...`: lectura completa, reacciones y comentarios.
- `blog-admin.php`: gestión de publicaciones y moderación (solo admin).
- Solo usuarios con sesión iniciada pueden comentar y dar «me gusta».

## Seguridad
- Contraseñas almacenadas con `password_hash`.
- Consultas SQL preparadas y tokens CSRF.
- Credenciales MySQL y Access Token fuera del repositorio.
- El estado del pago se obtiene consultando la API autenticada; no se confía en el parámetro de retorno del navegador.
