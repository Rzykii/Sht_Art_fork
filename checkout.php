<?php
require 'config/database.php';require 'includes/auth.php';require 'includes/csrf.php';require_once 'includes/media.php';require_once 'includes/mercadopago.php';
$cart=$_SESSION['cart']??[];$items=[];$subtotal=0;
if($cart){$ids=array_map('intval',array_keys($cart));$in=implode(',',array_fill(0,count($ids),'?'));$q=$pdo->prepare("SELECT id,nombre,precio,stock,activo,imagen FROM productos WHERE id IN ($in)");$q->execute($ids);foreach($q->fetchAll() as $p){$qty=(int)$cart[$p['id']];if($p['activo']&&$qty>0){$items[]=$p+['cantidad'=>$qty];$subtotal+=(int)$p['precio']*$qty;}}}
$error='';$success=false;$orderId=0;$mpRedirect=null;$discountCode=strtoupper(trim($_GET['discount']??''));if($discountCode==='')$discountCode=strtoupper(trim($_POST['codigo_descuento']??''));$discount=($discountCode==='SHT10')?(int)round($subtotal*.10):0;
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $name=trim($_POST['nombre']??'');$surname=trim($_POST['apellidos']??'');$email=filter_var(trim($_POST['email']??''),FILTER_VALIDATE_EMAIL);$phone=trim($_POST['telefono']??'');$rut=trim($_POST['rut']??'');
 $delivery=$_POST['metodo_entrega']??'domicilio';$region=trim($_POST['region']??'');$city=trim($_POST['comuna']??'');$address=trim($_POST['direccion']??'');$extra=trim($_POST['complemento']??'');$pickup=trim($_POST['punto_retiro']??'');$billingSame=!empty($_POST['facturacion_igual']);$billing=trim($_POST['direccion_facturacion']??'');$notes=trim($_POST['notas']??'');$discountCode=strtoupper(trim($_POST['codigo_descuento']??$discountCode));$payment=($_POST['metodo_pago']??'mercadopago')==='transferencia'?'Transferencia bancaria':'Mercado Pago';
 if(!$items)$error='Tu carrito está vacío o contiene productos no disponibles.';
 elseif($name===''||$surname===''||!$email||$phone===''||$region===''||$city===''||$rut==='')$error='Completa todos los datos obligatorios de contacto y facturación.';
 elseif(!preg_match('/^[0-9]{7,8}-[0-9kK]$/',$rut))$error='Ingresa el RUT con formato 12345678-9.';
 elseif($delivery==='domicilio'&&$address==='')$error='Indica la dirección de envío.';
 elseif($delivery==='retiro'&&$pickup==='')$error='Indica el punto o sucursal donde deseas retirar.';
 elseif(empty($_POST['terminos'])||empty($_POST['privacidad']))$error='Debes aceptar los términos y la política de privacidad.';
 else{
  if($discountCode==='SHT10')$discount=(int)round($subtotal*.10);
  elseif($discountCode!=='')$error='El código de descuento no es válido.';
  if(!$error){
   try{$pdo->beginTransaction();$fresh=[];$freshSubtotal=0;
    foreach($cart as $pid=>$qty){$s=$pdo->prepare("SELECT id,nombre,precio,stock,activo FROM productos WHERE id=? FOR UPDATE");$s->execute([(int)$pid]);$p=$s->fetch();$qty=(int)$qty;if(!$p||!$p['activo']||$qty<1||$qty>$p['stock'])throw new RuntimeException('El stock de uno de los productos cambió. Revisa tu carrito.');$p['cantidad']=$qty;$fresh[]=$p;$freshSubtotal+=(int)$p['precio']*$qty;}
    $discount=$discountCode==='SHT10'?(int)round($freshSubtotal*.10):0;$total=$freshSubtotal-$discount;
    $fullAddress=$delivery==='domicilio'?$address.($extra?', '.$extra:''):$pickup;
    $billingAddress=$billingSame?$fullAddress:$billing;
    $s=$pdo->prepare("INSERT INTO pedidos(nombre_cliente,apellidos,email,telefono,rut,region,comuna,direccion,complemento,metodo_entrega,punto_retiro,direccion_facturacion,notas,total,descuento,codigo_descuento,metodo_pago,acepta_terminos,acepta_privacidad) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,1)");
    $s->execute([$name,$surname,$email,$phone,$rut,$region,$city,$fullAddress,$extra,$delivery,$delivery==='retiro'?$pickup:null,$billingAddress,$notes,$total,$discount,$discountCode?:null,$payment]);
    $orderId=(int)$pdo->lastInsertId();$detail=$pdo->prepare("INSERT INTO detalle_pedido(pedido_id,producto_id,nombre_producto,cantidad,precio_unitario) VALUES(?,?,?,?,?)");$stock=$pdo->prepare("UPDATE productos SET stock=stock-? WHERE id=?");
    foreach($fresh as $p){$detail->execute([$orderId,$p['id'],$p['nombre'],$p['cantidad'],$p['precio']]);$stock->execute([$p['cantidad'],$p['id']]);}
    $pdo->prepare("INSERT INTO pagos(pedido_id,proveedor,estado) VALUES(?,?, 'pendiente')")->execute([$orderId,$payment]);
    if($payment==='Mercado Pago'){
      $mpOrder=mp_create_order((string)$email,$orderId,$total);
      $pdo->prepare("UPDATE pagos SET mp_order_id=?,external_reference=? WHERE pedido_id=? AND proveedor='Mercado Pago'")
          ->execute([(string)$mpOrder['id'],(string)$orderId,$orderId]);
      $mpRedirect=$mpOrder['checkout_url'];
    }
    $pdo->commit();$_SESSION['cart']=[];
    if($mpRedirect){header('Location: '.$mpRedirect);exit;}
    $success=true;
   }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e instanceof RuntimeException?$e->getMessage():'No se pudo registrar el pedido. Ejecuta install.php para actualizar la base de datos.';}
  }
 }
}
$shipping=0;$total=max(0,$subtotal-$discount);
?>
<!doctype html><html lang="es" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Finalizar compra — SHT</title><link rel="stylesheet" href="css/style.css"><link rel="stylesheet" href="css/tienda.css"><link rel="stylesheet" href="css/admin.css"><link rel="stylesheet" href="css/checkout.css"></head><body>
<header class="site-header"><a class="brand" href="index.php"><span class="brand-mark">S</span><span>SHT<span class="brand-dot">.</span></span></a><nav class="main-nav"><a href="tienda.php">← Volver a la tienda</a></nav><div class="header-actions"><a class="login-btn" href="tienda.php">Seguir comprando</a></div></header>
<main class="checkout-layout"><section class="checkout-main">
<?php if($success):?><div class="checkout-success"><p class="eyebrow">PEDIDO REGISTRADO</p><h1>¡Gracias por tu <em>compra!</em></h1><p>Tu pedido <strong>#<?=$orderId?></strong> quedó registrado. Te contactaremos al correo indicado para coordinar los siguientes pasos.</p><a class="button button-primary" href="tienda.php">Volver a la tienda</a></div>
<?php else:?><p class="eyebrow">FINALIZAR PEDIDO</p><h1>Checkout <em>SHT.</em></h1><p class="checkout-intro">Completa tus datos para preparar tu pedido.</p><?php if($error):?><p class="admin-alert error"><?=h($error)?></p><?php endif;?><?php if(!$items):?><p>Tu carrito está vacío.</p><a class="button button-primary" href="tienda.php">Ir a la tienda</a><?php else:?>
<form method="post" id="checkout-form"><?=csrf_field()?> <input type="hidden" name="codigo_descuento" value="<?=h($discountCode)?>">
<section class="checkout-section"><h2><span>1</span> Contacto</h2><div class="checkout-fields"><label class="full">E-mail *<input type="email" name="email" required autocomplete="email" value="<?=h($_POST['email']??'')?>"></label><label>Nombre *<input name="nombre" required value="<?=h($_POST['nombre']??'')?>"></label><label>Apellidos *<input name="apellidos" required value="<?=h($_POST['apellidos']??'')?>"></label><label class="full">Número de teléfono *<input type="tel" name="telefono" required placeholder="+56 9 1234 5678" value="<?=h($_POST['telefono']??'')?>"></label></div></section>
<section class="checkout-section"><h2><span>2</span> Método de entrega</h2><div class="delivery-options"><label class="delivery-option"><input type="radio" name="metodo_entrega" value="domicilio" <?=($_POST['metodo_entrega']??'domicilio')==='domicilio'?'checked':''?>><span><strong>Envío a domicilio</strong><small>Recibe tu pedido en la dirección que indiques.</small></span></label><label class="delivery-option"><input type="radio" name="metodo_entrega" value="retiro" <?=($_POST['metodo_entrega']??'')==='retiro'?'checked':''?>><span><strong>Retiro en punto de entrega</strong><small>Indica una sucursal o punto de retiro.</small></span></label></div>
<div class="checkout-fields"><label>Estado / Región *<input name="region" required value="<?=h($_POST['region']??'')?>"></label><label>Ciudad / Comuna *<input name="comuna" required value="<?=h($_POST['comuna']??'')?>"></label><label class="full delivery-home">Dirección *<input name="direccion" value="<?=h($_POST['direccion']??'')?>"></label><label class="full delivery-home">Apartamento, casa, puerta, etc. (opcional)<input name="complemento" value="<?=h($_POST['complemento']??'')?>"></label><label class="full delivery-pickup">Nombre o dirección de sucursal / punto de retiro *<input name="punto_retiro" value="<?=h($_POST['punto_retiro']??'')?>"></label><label class="full">Notas del pedido (opcional)<textarea name="notas" rows="3"><?=h($_POST['notas']??'')?></textarea></label></div></section>
<section class="checkout-section"><h2><span>3</span> Facturación</h2><div class="checkout-fields"><label class="full">RUT *<input name="rut" required placeholder="12345678-9" value="<?=h($_POST['rut']??'')?>"></label><label class="check-line full"><input type="checkbox" name="facturacion_igual" value="1" <?=isset($_POST['facturacion_igual'])?'checked':''?>> Dirección de facturación igual a la de envío</label><label class="full">Dirección de facturación (si es diferente)<input name="direccion_facturacion" value="<?=h($_POST['direccion_facturacion']??'')?>"></label></div></section>
<section class="checkout-section"><h2><span>4</span> Pago</h2>
<label class="payment-choice"><input type="radio" name="metodo_pago" value="mercadopago" <?=($_POST['metodo_pago']??'mercadopago')==='mercadopago'?'checked':''?>><span><strong>Mercado Pago</strong><small>Paga de forma segura en el sitio de Mercado Pago con los medios disponibles para tu cuenta.</small></span></label>
<label class="payment-choice"><input type="radio" name="metodo_pago" value="transferencia" <?=($_POST['metodo_pago']??'')==='transferencia'?'checked':''?>><span><strong>Transferencia bancaria</strong><small>El pedido quedará pendiente y coordinaremos los datos de pago por correo.</small></span></label>
<p class="checkout-muted">Al elegir Mercado Pago serás redirigido a su plataforma. El pedido solo se marcará como pagado cuando el servidor verifique la notificación de Mercado Pago.</p></section>
<section class="checkout-section checkout-legal"><label><input type="checkbox" name="terminos" required <?=isset($_POST['terminos'])?'checked':''?>> He leído y acepto los <a href="terminos.php" target="_blank">Términos y Condiciones</a>. *</label><label><input type="checkbox" name="privacidad" required <?=isset($_POST['privacidad'])?'checked':''?>> He leído y acepto la <a href="privacidad.php" target="_blank">Política de Privacidad</a>. *</label></section>
<button class="button button-primary checkout-submit" type="submit">Continuar al pago · <?=clp($total)?></button></form><?php endif;?><?php endif;?></section>
<aside class="checkout-summary"><h2>Resumen del pedido</h2><a class="text-link" href="tienda.php">Editar carrito</a><div class="summary-items"><?php foreach($items as $p):?><div class="summary-item"><img src="<?=h(product_public_url((string)$p['imagen']))?>" alt=""><div><strong><?=h($p['nombre'])?></strong><small>Cantidad: <?=$p['cantidad']?></small></div><b><?=clp($p['precio']*$p['cantidad'])?></b></div><?php endforeach;?></div><form method="get" class="discount-form" action="checkout.php"><label>Código de descuento<input name="discount" placeholder="Código promocional" value="<?=h($discountCode)?>"></label><button type="submit">Aplicar</button></form><p class="summary-line"><span>Subtotal</span><strong><?=clp($subtotal)?></strong></p><p class="summary-line"><span>Envío</span><strong>Se coordina por correo</strong></p><?php if($discount):?><p class="summary-line discount-line"><span>Descuento (<?=$discountCode?>)</span><strong>-<?=clp($discount)?></strong></p><?php endif;?><div class="summary-total"><span>Total</span><strong><?=clp($total)?></strong></div><small class="checkout-muted">Código de prueba disponible: SHT10 (10% de descuento).</small></aside>
</main><script>
document.querySelectorAll('input[name="metodo_entrega"]').forEach(el=>el.addEventListener('change',()=>{const pickup=document.querySelector('input[name="metodo_entrega"]:checked').value==='retiro';document.querySelectorAll('.delivery-home').forEach(x=>x.hidden=pickup);document.querySelectorAll('.delivery-pickup').forEach(x=>x.hidden=!pickup);}));
const delivery=document.querySelector('input[name="metodo_entrega"]:checked');if(delivery)delivery.dispatchEvent(new Event('change'));
</script><script src="js/app.js"></script></body></html>
