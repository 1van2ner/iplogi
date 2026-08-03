<?php
require_once 'includes/config.php';
$sent = false; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = sanitize($_POST['nombre']   ?? '');
    $email    = sanitize($_POST['email']    ?? '');
    $telefono = sanitize($_POST['telefono'] ?? '');
    $asunto   = sanitize($_POST['asunto']   ?? '');
    $mensaje  = sanitize($_POST['mensaje']  ?? '');
    if ($nombre && $email && $asunto && $mensaje) {
        $sent = true;
    } else {
        $error = 'Por favor completa todos los campos obligatorios.';
    }
}
$pageTitle = 'Contacto';
include 'includes/header.php';
?>
<div class="container">
  <div class="breadcrumb"><a href="<?= SITE_URL ?>/index.php">Inicio</a><span>›</span><strong>Contacto</strong></div>

  <div class="sec-head" style="margin-top:30px;">
    <span class="sec-badge"><i class="fas fa-envelope"></i> Escríbenos</span>
    <h2>Contáctanos</h2>
    <p>Estamos aquí para ayudarte. Respuesta en menos de 24 horas.</p>
  </div>

  <div class="contact-layout">

    <!-- FORMULARIO -->
    <div class="contact-form-wrap">
      <h2>Envíanos un mensaje</h2>
      <p>Cuéntanos qué necesitas y te responderemos a la brevedad.</p>
      <?php if($sent): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> ¡Mensaje enviado! Te contactaremos pronto.</div>
      <?php endif; ?>
      <?php if($error): ?>
        <div class="alert alert-error"><i class="fas fa-times-circle"></i> <?= $error ?></div>
      <?php endif; ?>
      <form method="POST">
        <div class="form-row">
          <div class="form-group">
            <label>Nombre *</label>
            <input type="text" name="nombre" placeholder="Tu nombre completo" required value="<?= sanitize($_POST['nombre']??'') ?>">
          </div>
          <div class="form-group">
            <label>Teléfono</label>
            <input type="tel" name="telefono" placeholder="+51 950 923 109" value="<?= sanitize($_POST['telefono']??'') ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input type="email" name="email" placeholder="tucorreo@ejemplo.com" required value="<?= sanitize($_POST['email']??'') ?>">
        </div>
        <div class="form-group">
          <label>Asunto *</label>
          <select name="asunto" required>
            <option value="">Selecciona un asunto</option>
            <option value="cotizacion"  <?= ($_POST['asunto']??'')==='cotizacion' ?'selected':'' ?>>Solicitar cotización</option>
            <option value="soporte"     <?= ($_POST['asunto']??'')==='soporte'    ?'selected':'' ?>>Soporte técnico</option>
            <option value="garantia"    <?= ($_POST['asunto']??'')==='garantia'   ?'selected':'' ?>>Garantía / Devolución</option>
            <option value="instalacion" <?= ($_POST['asunto']??'')==='instalacion'?'selected':'' ?>>Servicio de instalación</option>
            <option value="otro"        <?= ($_POST['asunto']??'')==='otro'       ?'selected':'' ?>>Otro</option>
          </select>
        </div>
        <div class="form-group">
          <label>Mensaje *</label>
          <textarea name="mensaje" rows="5" placeholder="Describe tu consulta con el mayor detalle posible..." required><?= sanitize($_POST['mensaje']??'') ?></textarea>
        </div>
        <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Enviar mensaje</button>
      </form>
    </div>

    <!-- INFO CON DATOS REALES -->
    <div class="contact-info-stack">

      <div class="contact-info-card">
        <div class="card-icon"><i class="fas fa-headset"></i></div>
        <h4>Atención al Cliente</h4>
        <div class="ci-row"><i class="fab fa-whatsapp"></i><a href="https://wa.me/51950923109" style="color:var(--primary);">+51 950 923 109 (WhatsApp)</a></div>
        <div class="ci-row"><i class="fas fa-envelope"></i><a href="mailto:ventas@iptecnologiaperu.com" style="color:var(--primary);">ventas@iptecnologiaperu.com</a></div>
        <div class="ci-row"><i class="fas fa-clock"></i><span>Lun–Sáb: 9:00am – 8:00pm</span></div>
      </div>

      <div class="contact-info-card">
        <div class="card-icon"><i class="fas fa-store"></i></div>
        <h4>Sede Lima</h4>
        <div class="ci-row"><i class="fas fa-map-marker-alt"></i><span>Jr. Paruro Nº 1322 - Sótano Tda S112, Cercado de Lima</span></div>
        <div class="ci-row"><i class="fas fa-phone"></i><a href="https://wa.me/51950923109" style="color:var(--primary);">+51 950 923 109</a></div>
      </div>

      <div class="contact-info-card">
        <div class="card-icon"><i class="fas fa-store"></i></div>
        <h4>Sede La Molina</h4>
        <div class="ci-row"><i class="fas fa-map-marker-alt"></i><span>Av. Melgarejo Nº 595, La Molina</span></div>
        <div class="ci-row"><i class="fas fa-phone"></i><a href="https://wa.me/51950923109" style="color:var(--primary);">+51 950 923 109</a></div>
      </div>

      <div class="contact-info-card">
        <div class="card-icon"><i class="fas fa-store"></i></div>
        <h4>Sede Ate — Plaza Vitarte</h4>
        <div class="ci-row"><i class="fas fa-map-marker-alt"></i><span>C.C. Plaza Vitarte, Block F Tda. 304, Ate</span></div>
        <div class="ci-row"><i class="fas fa-phone"></i><a href="https://wa.me/51950923109" style="color:var(--primary);">+51 950 923 109</a></div>
      </div>

      <a href="https://wa.me/51950923109?text=Hola,%20necesito%20asesoría%20técnica" target="_blank"
         style="display:flex;align-items:center;justify-content:center;gap:12px;background:linear-gradient(135deg,#25d366,#128c7e);color:#fff;padding:16px;border-radius:var(--rl);font-size:15px;font-weight:800;transition:all .2s;text-decoration:none;"
         onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
        <i class="fab fa-whatsapp" style="font-size:28px;"></i>
        ¡Chatea con un asesor ahora!
      </a>

    </div>
  </div>

  <!-- MAPA SEDE PRINCIPAL -->
  <div style="margin-top:48px;margin-bottom:60px;">
    <div class="sec-head" style="margin-bottom:20px;">
      <span class="sec-badge"><i class="fas fa-map-marker-alt"></i> Encuéntranos</span>
      <h2>Sede Principal — Lima</h2>
      <p>Jr. Paruro Nº 1322 - Sótano Tda S112, Cercado de Lima</p>
    </div>
    <div style="border-radius:14px;overflow:hidden;border:1.5px solid var(--borde);box-shadow:0 4px 20px rgba(0,0,0,.08);">
      <iframe src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d243.8634457454953!2d-77.02584925421654!3d-12.056237337699853!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x9105c50da3092917%3A0x69243f0d60417c04!2sIP%20TECNOLOGIA%20PERU!5e0!3m2!1ses-419!2sus!4v1772745424249!5m2!1ses-419!2sus"
        width="100%" height="380" style="border:0;display:block;" allowfullscreen="" loading="lazy"></iframe>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>
