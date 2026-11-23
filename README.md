# SHT_art — tienda, blog y pagos con Mercado Pago

## Requisitos
- PHP 8.0 o superior (recomendado PHP 8.1+).
- MySQL 5.7+ / MariaDB 10.4+.
- Extensiones PHP: PDO MySQL, cURL y mbstring.
- HTTPS habilitado en el dominio del hosting.

## Instalación general
1. Sube el contenido del proyecto a la carpeta pública de tu hosting (por ejemplo, `htdocs` o `public_html`).
2. Crea una base de datos MySQL y un usuario desde el panel del hosting.
3. Configura `config/database.php` con los datos entregados por el hosting.
4. Abre `https://TU-DOMINIO/install.php` una sola vez para crear/actualizar las tablas.
5. Inicia sesión con el administrador inicial `admin` / `admin123`, cambia la contraseña y elimina `install.php`.
6. Configura Mercado Pago siguiendo la sección siguiente.

## Mercado Pago en InfinityFree

La tienda usa Checkout Pro mediante Orders API. El comprador paga en Mercado Pago y vuelve a `checkout-result.php`. La aplicación consulta la API autenticada de Mercado Pago para verificar el estado, monto, moneda y referencia del pedido antes de marcarlo como pagado.

### 1. Credenciales

1. Crea una aplicación en [Mercado Pago Developers](https://www.mercadopago.cl/developers/panel).
2. Obtén el Access Token de prueba para comenzar (`TEST-...`). Para producción, usa el token de producción (`APP_USR-...`) cuando hayas probado todo.
3. En el servidor, copia `config/mercadopago.local.php.example` a `config/mercadopago.local.php`.
4. Completa el archivo privado:

```php
<?php
$MP_LOCAL_ACCESS_TOKEN = 'TEST-PEGA_AQUI_TU_ACCESS_TOKEN';
$MP_LOCAL_WEBHOOK_SECRET = '';
$MP_LOCAL_APP_URL = 'https://TU-SUBDOMINIO.infinityfreeapp.com';
```

- `MP_LOCAL_ACCESS_TOKEN`: Access Token de Mercado Pago. No lo publiques ni lo incluyas en capturas.
- `MP_LOCAL_APP_URL`: URL HTTPS exacta donde está instalada la tienda. Si subiste los archivos a una subcarpeta, incluye esa ruta, por ejemplo `https://tu-dominio.com/tienda`. No agregues una barra final.
- `MP_LOCAL_WEBHOOK_SECRET`: no es necesario para el modo de verificación por retorno descrito aquí. Déjalo vacío en InfinityFree gratuito.

El archivo `mercadopago.local.php` está excluido de Git. Súbelo por FTP/File Manager y no lo compartas.

### 2. Ajustes de InfinityFree

- Habilita HTTPS/SSL para el dominio antes de activar Mercado Pago.
- Comprueba que PHP tenga habilitada la extensión cURL.
- Las conexiones salientes HTTPS a `api.mercadopago.com` se realizan desde PHP por el puerto 443.
- InfinityFree gratuito bloquea solicitudes automáticas entrantes a tu web, incluidos webhooks de plataformas externas. Por eso **no configures el webhook como mecanismo necesario para confirmar pagos**. La página de retorno consulta directamente la API de Mercado Pago.
- La consulta se realiza cuando el comprador vuelve desde Mercado Pago y se repite al recargar la página mientras el pedido siga pendiente.

### 3. Pruebas y publicación

1. Configura primero un Access Token de prueba y usa las cuentas/tarjetas de prueba indicadas por Mercado Pago.
2. Realiza una compra de prueba completa y confirma que la página de retorno muestre `Pago confirmado` únicamente después de consultar la API.
3. Prueba también un pago rechazado/cancelado y verifica el estado del pedido y la restitución de stock.
4. Cuando todo funcione, reemplaza el token de prueba por el Access Token de producción.
5. Nunca marques manualmente un pedido como pagado basándote solo en los parámetros de retorno del navegador.

**Limitación del hosting gratuito:** si el comprador paga y cierra la pestaña antes de volver a la tienda, el pedido puede permanecer pendiente porque InfinityFree no recibe el webhook y no hay un proceso programado que lo sincronice. Para confirmación automática incluso cuando el comprador no regresa, usa un hosting que permita webhooks entrantes o un servicio externo de sincronización.

## Checkout y pedidos
- Formulario por secciones: contacto, entrega, facturación, pago y resumen.
- Entrega a domicilio o retiro en punto/sucursal.
- Región, comuna, dirección, complemento y RUT.
- Código promocional `SHT10`: 10% de descuento.
- Medios disponibles: Mercado Pago y transferencia bancaria manual.
- El costo de despacho se coordina por correo y no se suma automáticamente al total.

## Blog
- `blog.php`: portada editorial con publicaciones.
- `blog-post.php?id=...`: lectura completa, reacciones y comentarios.
- `blog-admin.php`: gestión de publicaciones y moderación (solo admin).
- Solo usuarios con sesión iniciada pueden comentar y dar «me gusta».

## Seguridad
- Contraseñas almacenadas con `password_hash`.
- Consultas SQL preparadas para entradas del usuario.
- Tokens CSRF en formularios.
- Access Token fuera del repositorio público.
- El estado del pago se obtiene consultando la API de Mercado Pago; no se confía en el parámetro `status` del navegador.
- Se verifica la referencia externa, el monto y la moneda antes de actualizar el pedido.
