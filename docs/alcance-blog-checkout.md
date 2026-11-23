# Alcance de la actualización SHT_art

## Blog editorial
- Portada inspirada en el feed de Marketplace.tf: artículo destacado y publicaciones cronológicas con fecha, categoría, título, resumen, imagen y enlace para continuar leyendo.
- Vista individual de cada artículo.
- Publicación, edición, borrador y eliminación de artículos por parte del administrador.
- Registro e inicio de sesión de usuarios.
- Reacciones «me gusta» únicas por usuario y publicación.
- Comentarios asociados a usuario y publicación.
- Moderación: mostrar, ocultar o eliminar comentarios desde el panel.

## Checkout y Mercado Pago
- Formulario separado por secciones: contacto, entrega, facturación, pago y resumen.
- Envío a domicilio o retiro en punto/sucursal.
- Región, comuna, dirección, complemento y RUT.
- Código promocional de demostración SHT10.
- Mercado Pago Checkout Pro con Orders API: creación de una order por pedido, clave de idempotencia y redirección al entorno de pago.
- Webhook firmado (evento Order): consulta de la order a la API, validación de monto y moneda, actualización de estado y reintegro de stock en orders fallidas/canceladas antes del pago.
- Transferencia bancaria manual como alternativa.
- El despacho se coordina por correo y no se suma automáticamente al total.

## Configuración externa requerida
- Access Token de Mercado Pago.
- Secreto de firma de Webhooks.
- URL pública HTTPS configurada en `APP_URL`.
- Extensión PHP cURL habilitada.
