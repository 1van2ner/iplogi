<?php require_once 'includes/config.php'; $pageTitle='Política de Privacidad'; include 'includes/header.php'; ?>
<div class="container" style="max-width:860px;padding:40px 20px;">
  <div class="breadcrumb"><a href="<?=SITE_URL?>/index.php">Inicio</a><span>›</span><strong>Política de Privacidad</strong></div>
  <h1 class="page-title" style="margin-bottom:8px;"><i class="fas fa-shield-alt" style="color:#D7E022;"></i> Política de Privacidad</h1>
  <p style="color:var(--gris3);font-size:13px;margin-bottom:32px;">Última actualización: <?=date('d/m/Y')?> · IP Tecnología Perú E.I.R.L. — RUC: 20601744164</p>

  <?php
  $secciones = [
    ['1. Responsable del Tratamiento','IP Tecnología Perú E.I.R.L., con RUC 20601744164, con domicilio en Jr. Paruro Nº 1322 - Sótano Tda S112, Lima, es responsable del tratamiento de los datos personales recopilados a través de este sitio web, de conformidad con la <strong>Ley N° 29733 – Ley de Protección de Datos Personales del Perú</strong> y su Reglamento D.S. 003-2013-JUS.'],
    ['2. Datos que Recopilamos','Recopilamos los siguientes datos cuando te registras o realizas una compra: nombre completo, DNI o RUC, correo electrónico, número de celular, dirección de entrega, historial de pedidos y, en pagos con tarjeta, los datos son gestionados directamente por la pasarela de pago (no almacenamos datos de tarjetas).'],
    ['3. Finalidad del Tratamiento','Utilizamos tus datos para: (a) gestionar y entregar tus pedidos, (b) emitir comprobantes de pago, (c) enviarte comunicaciones sobre tu pedido, (d) brindarte soporte posventa, (e) mejorar nuestra experiencia de usuario. No usamos tus datos para publicidad de terceros sin tu consentimiento.'],
    ['4. Base Legal','El tratamiento se basa en: (a) ejecución de contrato de compraventa, (b) consentimiento explícito al registrarse, (c) cumplimiento de obligaciones legales tributarias (SUNAT).'],
    ['5. Cookies','Utilizamos cookies técnicas necesarias para el funcionamiento del sitio (sesión de usuario, carrito de compras) y cookies analíticas opcionales para medir el tráfico. Puedes gestionar las cookies desde el banner de consentimiento o en la configuración de tu navegador. Rechazar cookies técnicas puede afectar el funcionamiento del sitio.'],
    ['6. Compartición de Datos','No vendemos ni cedemos tus datos a terceros con fines comerciales. Podemos compartir datos estrictamente necesarios con: empresas de courier para gestionar entregas, procesadores de pago (Culqi, Visa, Mastercard) bajo sus propias políticas de privacidad, y autoridades competentes cuando la ley lo exija.'],
    ['7. Conservación de Datos','Conservamos tus datos mientras mantengas una cuenta activa y durante el plazo que exija la normativa tributaria peruana (mínimo 5 años). Puedes solicitar la eliminación de tu cuenta; sin embargo, conservaremos los datos necesarios para cumplir obligaciones legales.'],
    ['8. Tus Derechos','Tienes derecho a: acceder, rectificar, cancelar y oponerte al tratamiento de tus datos personales (derechos ARCO). Para ejercerlos escríbenos a <strong>ventas@iptecnologiaperu.pe</strong> indicando tu nombre, DNI y el derecho que deseas ejercer. Responderemos en un plazo máximo de 20 días hábiles.'],
    ['9. Seguridad','Implementamos medidas técnicas y organizativas para proteger tus datos: cifrado SSL/HTTPS, acceso restringido a la base de datos, contraseñas hasheadas y copias de seguridad periódicas.'],
    ['10. Cambios en esta Política','Nos reservamos el derecho de actualizar esta Política. Los cambios sustanciales serán notificados por correo electrónico a los usuarios registrados o mediante aviso destacado en el sitio web.'],
  ];
  foreach($secciones as [$titulo,$contenido]):
  ?>
  <div style="margin-bottom:28px;background:var(--dark3);border:1px solid var(--borde);border-radius:12px;padding:22px 24px;">
    <h2 style="font-size:16px;font-weight:800;color:#D7E022;margin:0 0 10px;"><?=$titulo?></h2>
    <p style="font-size:14px;color:var(--gris2);line-height:1.8;margin:0;"><?=$contenido?></p>
  </div>
  <?php endforeach; ?>

  <div style="text-align:center;margin-top:20px;"><a href="<?=SITE_URL?>/index.php" class="btn-main"><i class="fas fa-home"></i> Volver al inicio</a></div>
</div>
<?php include 'includes/footer.php'; ?>
