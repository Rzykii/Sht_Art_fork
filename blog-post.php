<?php
require 'config/database.php'; require 'includes/auth.php'; require 'includes/csrf.php';
$id=(int)($_GET['id']??0);
$q=$pdo->prepare("SELECT * FROM blog_posts WHERE id=? AND status='published'"); $q->execute([$id]); $post=$q->fetch();
if(!$post){http_response_code(404);exit('Publicación no encontrada.');}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  require_login(); verify_csrf(); $action=$_POST['action']??'';
  if($action==='like'){
    $s=$pdo->prepare("INSERT IGNORE INTO blog_likes(post_id,user_id) VALUES(?,?)"); $s->execute([$id,(int)$_SESSION['user_id']]);
  } elseif($action==='comment'){
    $body=trim($_POST['comment']??'');
    if($body==='') $error='Escribe un comentario antes de enviarlo.';
    elseif(mb_strlen($body)>1500) $error='El comentario supera los 1500 caracteres.';
    else {$s=$pdo->prepare("INSERT INTO blog_comments(post_id,user_id,body) VALUES(?,?,?)");$s->execute([$id,(int)$_SESSION['user_id'],$body]);}
  }
  if(!$error){header('Location: blog-post.php?id='.$id.'#interaccion');exit;}
}
$s=$pdo->prepare("SELECT c.*,u.username FROM blog_comments c JOIN usuarios u ON u.id=c.user_id WHERE c.post_id=? AND c.status='visible' ORDER BY c.created_at DESC");$s->execute([$id]);$comments=$s->fetchAll();
$s=$pdo->prepare("SELECT COUNT(*) FROM blog_likes WHERE post_id=?");$s->execute([$id]);$likes=(int)$s->fetchColumn();
$liked=false;if(!empty($_SESSION['user_id'])){$s=$pdo->prepare("SELECT 1 FROM blog_likes WHERE post_id=? AND user_id=?");$s->execute([$id,(int)$_SESSION['user_id']]);$liked=(bool)$s->fetchColumn();}
?>
<!doctype html><html lang="es" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($post['title'])?> — SHT</title><link rel="stylesheet" href="css/style.css"><link rel="stylesheet" href="css/tienda.css"><link rel="stylesheet" href="css/blog.css"></head><body><header class="site-header"><a class="brand" href="index.php"><span class="brand-mark">S</span><span>SHT.</span></a><nav class="main-nav"><a href="blog.php">← Volver al blog</a><a href="tienda.php">Tienda</a></nav><div class="header-actions"><?php if(empty($_SESSION['user_id'])):?><a class="login-btn" href="login.php">Iniciar sesión</a><?php else:?><span class="admin-user"><?=h($_SESSION['username'])?></span><?php endif;?></div></header>
<main class="blog-wrap blog-detail"><p class="blog-kicker"><?=h($post['category']??'Novedades')?> <span>·</span> <?=date('d.m.Y',strtotime($post['published_at']?:$post['created_at']))?></p><h1><?=h($post['title'])?></h1><p class="blog-excerpt"><?=h($post['excerpt'])?></p><?php if($post['cover_image']):?><img class="blog-cover" src="<?=h($post['cover_image'])?>" alt=""><?php endif;?><article class="blog-content"><?php foreach(preg_split("/\R{2,}/", trim((string)$post['content'])) as $paragraph): ?><p><?=nl2br(h($paragraph))?></p><?php endforeach; ?></article>
<section id="interaccion" class="blog-interaction"><h2>Comunidad</h2><?php if($error):?><p class="admin-alert error"><?=h($error)?></p><?php endif;?>
<?php if(!empty($_SESSION['user_id'])):?><form method="post" class="like-form"><?=csrf_field()?><input type="hidden" name="action" value="like"><button class="button button-primary" type="submit"><?=$liked?'Te gusta':'Me gusta'?> · <?=$likes?></button></form><form method="post" class="comment-form"><?=csrf_field()?><input type="hidden" name="action" value="comment"><label for="comment">Deja un comentario</label><textarea id="comment" name="comment" maxlength="1500" required rows="4" placeholder="Comparte tu opinión..."></textarea><button class="button button-primary" type="submit">Publicar comentario</button></form><?php else:?><p>Para dar «me gusta» o comentar, <a class="text-link" href="login.php">inicia sesión</a> o <a class="text-link" href="register.php">crea una cuenta</a>.</p><p class="like-count"><?=$likes?> me gusta</p><?php endif;?>
<div class="comments-list"><h3>Comentarios (<?=count($comments)?>)</h3><?php foreach($comments as $c):?><article class="comment"><strong><?=h($c['username'])?></strong><small><?=date('d/m/Y H:i',strtotime($c['created_at']))?></small><p><?=nl2br(h($c['body']))?></p></article><?php endforeach;?><?php if(!$comments):?><p class="muted">Aún no hay comentarios.</p><?php endif;?></div></section></main><script src="js/app.js"></script></body></html>
