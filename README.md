<<<<<<< HEAD
# Portafolio Artístico, Comisiones y Tienda en Línea

Este proyecto consiste en el desarrollo de una página web integral tipo portafolio para un cliente artista. El objetivo principal es ofrecer una plataforma centralizada para promocionar su obra, mostrar su trayectoria, gestionar solicitudes de comisiones personalizadas, recibir donaciones y comercializar obras originales junto con merchandising (stickers, pines y otros artículos variados).

---

## 🚀 Características Principales

* **🎨 Galería de Arte:** Exhibición de las obras artísticas del cliente clasificadas por categorías y en alta resolución.
* **📜 Trayectoria:** Sección dedicada a la biografía, exposiciones, reconocimientos y recorrido profesional del artista.
* **💼 Gestión de Comisiones:** Sistema y formulario para gestionar solicitudes de obras personalizadas de forma estructurada.
* **💖 Sistema de Donaciones:** Módulo para que los seguidores puedan apoyar financieramente el trabajo del artista.
* **🛒 Tienda en Línea y Sistema de Compras:**
  * Catálogo de productos (obras originales, stickers, pines, prints y artículos personalizados).
  * Carrito de compras funcional e integración con pasarela de pago.
* **📬 Sección de Contacto:** Formulario e información directa para dudas, cotizaciones y colaboraciones.
* **📱 Diseño Responsive:** Interfaz 100% adaptable a dispositivos móviles, tablets y computadores utilizando **Bootstrap**.

---

## 🛠️ Stack Tecnológico

El proyecto está diseñado bajo la siguiente estructura tecnológica (sujeta a definición según el entorno final):

* **Frontend:** 
  * HTML5, CSS3, JavaScript
  * **Framework CSS:** Bootstrap (para diseño adaptativo y responsive)
* **Backend:** PHP
* **Base de Datos:** MySQL
* **Pasarela de Pagos (Por definir):** 
  * Stripe / PayPal / Mercado Pago
  * Webpay Plus
  * *Alternativa CMS:* WordPress (en evaluación)
=======
# SHT_art — tienda, blog y pagos con Mercado Pago

## Requisitos
- PHP 8.0 o superior (recomendado PHP 8.1+).
- MySQL 5.7+ / MariaDB 10.4+.
- Extensiones PHP: PDO MySQL, cURL y mbstring.
- Servidor local XAMPP, Laragon, WAMP o hosting PHP.

## Instalación
1. Copia la carpeta `Sht_art_actualizado` dentro de `htdocs` (XAMPP) o la raíz pública del hosting.
2. Crea una base de datos MySQL llamada `Sht_art`.
3. Revisa `config/database.php` y configura host, usuario y contraseña.
4. Abre `http://localhost/Sht_art_actualizado/install.php`.
5. El instalador crea/actualiza las tablas de usuarios, productos, dibujos, pedidos, pagos y blog.
6. Acceso inicial de administrador: `admin` / `admin123`. Inicia sesión y cambia la contraseña.
7. Configura Mercado Pago (ver sección siguiente).
8. Elimina o renombra `install.php` cuando la instalación termine.

## Configurar Mercado Pago
La tienda utiliza **Checkout Pro con Orders API**, el flujo recomendado actualmente por Mercado Pago. El cliente completa el pago en el entorno seguro de Mercado Pago y vuelve a `checkout-result.php`.

Puedes configurar variables de entorno en el servidor:

```ini
MERCADOPAGO_ACCESS_TOKEN=TEST-xxxxxxxxxxxxxxxx
MERCADOPAGO_WEBHOOK_SECRET=xxxxxxxxxxxxxxxx
APP_URL=https://tudominio.cl/Sht_art_actualizado
```

- `MERCADOPAGO_ACCESS_TOKEN`: Access Token de la aplicación creada en [Tus integraciones de Mercado Pago](https://www.mercadopago.cl/developers/panel).
- `MERCADOPAGO_WEBHOOK_SECRET`: clave secreta de firma que aparece en la configuración de Webhooks de esa aplicación.
- `APP_URL`: URL pública completa de la carpeta del proyecto, sin `/` al final. En producción debe usar HTTPS.

En XAMPP también puedes copiar `config/mercadopago.local.php.example` como `config/mercadopago.local.php` y completar los tres valores. Ese archivo local está excluido de Git.
- Para pruebas, comienza con credenciales de prueba (`TEST-...`) y usuarios de prueba de Mercado Pago. No publiques ni compartas el Access Token.
- En el panel de Mercado Pago configura la URL `https://tudominio.cl/Sht_art_actualizado/mercadopago-webhook.php` y activa el evento **Order (Mercado Pago)** (tópico `order`).
- En localhost el webhook no será accesible directamente desde Internet. Usa un túnel HTTPS de desarrollo o prueba la integración en un servidor de staging.
- Habilita la extensión cURL de PHP.

El sistema crea una order por pedido, envía una clave `X-Idempotency-Key`, guarda el identificador de Mercado Pago y redirige al `checkout_url`. El retorno del navegador **no confirma el pago**: `mercadopago-webhook.php` valida la firma, consulta el pago directamente a la API, comprueba monto/moneda y actualiza el pedido. Los estados rechazados/cancelados devuelven el stock una sola vez.

## Blog
- `blog.php`: portada editorial con publicación destacada y feed cronológico de artículos, fecha, categoría, extracto, imagen y enlace «Continuar leyendo».
- `blog-post.php?id=...`: lectura completa, reacciones y comentarios.
- `register.php`: registro de cuentas de usuario.
- `blog-admin.php`: gestión de publicaciones y moderación de comentarios (solo admin).
- El administrador puede asignar categoría, portada, guardar borradores o publicar.
- Solo usuarios con sesión iniciada pueden comentar y dar «me gusta».
- Las publicaciones y comentarios se guardan en MySQL.

El diseño editorial se inspira en la estructura de lectura del blog de Marketplace.tf (publicaciones apiladas, fecha, titular, resumen y «Continuar leyendo»), manteniendo identidad visual y estilos propios de SHT_art.

## Checkout
- Formulario por secciones: contacto, entrega, facturación, pago y resumen.
- Entrega a domicilio o retiro en punto/sucursal.
- Región, comuna, dirección, complemento y RUT.
- Código promocional `SHT10`: 10% de descuento.
- Medios disponibles: Mercado Pago Checkout Pro y transferencia bancaria manual.
- El costo de despacho se coordina por correo y no se suma automáticamente al total.

## Archivos añadidos o modificados
- `config/mercadopago.php`, `includes/mercadopago.php`
- `mercadopago-webhook.php`, `checkout-result.php`
- `blog.php`, `blog-post.php`, `blog-admin.php`, `checkout.php`, `install.php`
- `css/blog.css`
- `includes/auth.php`, `includes/csrf.php`, `includes/media.php`

## Seguridad básica
- Contraseñas almacenadas con `password_hash`.
- Consultas SQL preparadas para entradas del usuario.
- Tokens CSRF en formularios de blog, administración y checkout.
- Validación de permisos del panel de administración en el servidor.
- Access Token y secreto de Webhook se configuran fuera del código público.
- El webhook no confía en el estado recibido: consulta el pago a Mercado Pago y verifica firma, monto y moneda.

## Limitaciones actuales
- El despacho se coordina manualmente; no hay cálculo automático de tarifas.
- No hay cancelación automática de pedidos abandonados ni reserva temporal de stock con vencimiento; revisa pedidos pendientes desde el panel/BD.
- La integración requiere credenciales válidas y una URL pública HTTPS para recibir Webhooks en producción.
>>>>>>> feature/cambios
