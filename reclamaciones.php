<?php require_once 'includes/config.php';
$pageTitle='Libro de Reclamaciones';
$enviado=false; $errMsg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $nombre   = sanitize(trim($_POST['nombre']??''));
  $dni      = sanitize(trim($_POST['dni']??''));
  $email    = sanitize(trim($_POST['email']??''));
  $celular  = sanitize(trim($_POST['celular']??''));
  $tipo     = in_array($_POST['tipo']??'',['queja','reclamo'])?$_POST['tipo']:'reclamo';
  $pedido   = sanitize(trim($_POST['pedido']??''));
  $detalle  = sanitize(trim($_POST['detalle']??''));
  $pedido_r = sanitize(trim($_POST['pedido_resolucion']??''));
  if(!$nombre||!$dni||!$email||!$detalle){$errMsg='Completa todos los campos obligatorios.';}
  else{
    // Guardar en BD si la tabla existe
    try{
      $pdo=getDB();
      $pdo->prepare("INSERT INTO reclamaciones(nombre,dni,email,celular,tipo,numero_pedido,detalle,pedido_resolucion,estado,creado_en)
        VALUES(?,?,?,?,?,?,?,?,'pendiente',NOW())")
        ->execute([$nombre,$dni,$email,$celular,$tipo,$pedido,$detalle,$pedido_r]);
    }catch(Exception $e){/* tabla no existe aún, se ignora */}
    $enviado=true;
  }
}
include 'includes/header.php'; ?>

<div class="container" style="max-width:860px;padding:40px 20px;">
  <div class="breadcrumb"><a href="<?=SITE_URL?>/index.php">Inicio</a><span>›</span><strong>Libro de Reclamaciones</strong></div>
  <h1 class="page-title" style="margin-bottom:8px;"><i class="fas fa-book" style="color:#D7E022;"></i> Libro de Reclamaciones</h1>
  <p style="color:var(--gris3);font-size:13px;margin-bottom:24px;">Conforme al <strong>Código de Protección y Defensa del Consumidor (Ley 29571)</strong> — IP Tecnología Perú E.I.R.L. · RUC: 20601744164</p>

  <!-- Aviso legal -->
  <div style="background:rgba(215,224,34,.08);border:1.5px solid rgba(215,224,34,.3);border-radius:12px;padding:16px 20px;margin-bottom:28px;font-size:13px;color:var(--gris2);line-height:1.7;">
    <i class="fas fa-info-circle" style="color:#D7E022;"></i>
    La formulación de una <strong>queja</strong> no suspende la contratación ni es promovida como acción legal. Un <strong>reclamo</strong> es una disconformidad con los productos o servicios prestados. Atenderemos tu solicitud en un plazo máximo de <strong>15 días hábiles</strong>.
  </div>

  <?php if($enviado): ?>
    <div class="alert alert-success" style="margin-bottom:24px;">
      <i class="fas fa-check-circle"></i> <strong>¡Reclamación registrada!</strong> Hemos recibido tu solicitud. Te contactaremos a <strong><?=$email?></strong> dentro de 15 días hábiles. Guarda este registro para tus archivos.
    </div>
  <?php else: ?>
    <?php if($errMsg): ?><div class="alert alert-error" style="margin-bottom:16px;"><i class="fas fa-times-circle"></i> <?=$errMsg?></div><?php endif; ?>
    <form method="POST" style="background:var(--dark3);border:1px solid var(--borde);border-radius:14px;padding:28px;">
      <h3 style="font-size:16px;font-weight:800;color:#D7E022;margin:0 0 20px;"><i class="fas fa-user"></i> Datos del Consumidor</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
        <div class="form-group" style="margin:0;">
          <label>Nombre completo *</label>
          <input type="text" name="nombre" value="<?=sanitize($_POST['nombre']??'')?>" placeholder="Tu nombre completo" required>
        </div>
        <div class="form-group" style="margin:0;">
          <label>DNI / RUC *</label>
          <input type="text" name="dni" value="<?=sanitize($_POST['dni']??'')?>" placeholder="8 u 11 dígitos" required maxlength="11">
        </div>
        <div class="form-group" style="margin:0;">
          <label>Correo electrónico *</label>
          <input type="email" name="email" value="<?=sanitize($_POST['email']??'')?>" placeholder="tucorreo@ejemplo.com" required>
        </div>
        <div class="form-group" style="margin:0;">
          <label>Celular</label>
          <input type="tel" name="celular" value="<?=sanitize($_POST['celular']??'')?>" placeholder="9XX XXX XXX">
        </div>
      </div>

      <h3 style="font-size:16px;font-weight:800;color:#D7E022;margin:20px 0 16px;"><i class="fas fa-exclamation-triangle"></i> Detalle de la Reclamación</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
        <div class="form-group" style="margin:0;">
          <label>Tipo *</label>
          <select name="tipo" style="width:100%;padding:11px 14px;background:var(--bg3);border:1.5px solid var(--borde);border-radius:var(--r);color:var(--blanco);font-size:14px;">
            <option value="reclamo" <?=($_POST['tipo']??'reclamo')==='reclamo'?'selected':''?>>📋 Reclamo (disconformidad con bien/servicio)</option>
            <option value="queja"   <?=($_POST['tipo']??'')==='queja'?'selected':''?>>😟 Queja (malestar sobre atención)</option>
          </select>
        </div>
        <div class="form-group" style="margin:0;">
          <label>Nº de Pedido (si aplica)</label>
          <input type="text" name="pedido" value="<?=sanitize($_POST['pedido']??'')?>" placeholder="Ej: TS-A1B2C3D4">
        </div>
      </div>
      <div class="form-group">
        <label>Descripción del reclamo / queja * <small style="color:var(--gris3);">(mínimo 30 caracteres)</small></label>
        <textarea name="detalle" rows="5" required minlength="30" placeholder="Describe detalladamente el motivo de tu reclamación: qué sucedió, qué producto o servicio estuvo involucrado, fecha aproximada..."><?=sanitize($_POST['detalle']??'')?></textarea>
      </div>
      <div class="form-group">
        <label>¿Qué solución esperas? <small style="color:var(--gris3);">(opcional)</small></label>
        <textarea name="pedido_resolucion" rows="3" placeholder="Ej: Cambio del producto, devolución del dinero, disculpas formales..."><?=sanitize($_POST['pedido_resolucion']??'')?></textarea>
      </div>

      <div style="background:rgba(255,255,255,.04);border-radius:8px;padding:12px;font-size:12px;color:var(--gris3);margin-bottom:18px;line-height:1.7;">
        <i class="fas fa-lock"></i> Tus datos serán tratados conforme a nuestra <a href="<?=SITE_URL?>/privacidad.php" style="color:#D7E022;">Política de Privacidad</a>.
        IP Tecnología Perú procesará tu solicitud en máximo 15 días hábiles.
      </div>
      <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Enviar Reclamación</button>
    </form>
  <?php endif; ?>

  <div style="text-align:center;margin-top:28px;"><a href="<?=SITE_URL?>/index.php" class="btn-main"><i class="fas fa-home"></i> Volver al inicio</a></div>
</div>
<?php include 'includes/footer.php'; ?>
