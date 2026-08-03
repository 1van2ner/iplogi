<?php require_once 'includes/config.php'; $pageTitle='Términos y Condiciones'; include 'includes/header.php'; ?>
<div class="container" style="max-width:860px;padding:40px 20px;">
  <div class="breadcrumb"><a href="<?=SITE_URL?>/index.php">Inicio</a><span>›</span><strong>Términos y Condiciones</strong></div>
  <h1 class="page-title" style="margin-bottom:8px;"><i class="fas fa-file-contract" style="color:#D7E022;"></i> Términos y Condiciones</h1>
  <p style="color:var(--gris3);font-size:13px;margin-bottom:32px;">Última actualización: <?=date('d/m/Y')?>  · IP Tecnología Perú E.I.R.L. — RUC: 20601744164</p>

  <?php
  $secciones = [
    ['1. Aceptación de los Términos','Al acceder y utilizar el sitio web <strong>iptecnologiaperu.pe</strong> y realizar compras en IP Tecnología Perú E.I.R.L., el usuario acepta íntegramente los presentes Términos y Condiciones. Si no está de acuerdo con alguno de estos términos, le recomendamos no utilizar nuestros servicios.'],
    ['2. Información de la Empresa','<strong>IP Tecnología Perú E.I.R.L.</strong><br>RUC: 20601744164<br>Domicilio fiscal: Jr. Paruro Nº 1322 - Sótano Tda S112, Lima, Perú<br>Correo: ventas@iptecnologiaperu.pe<br>Teléfono: +51 950 923 109'],
    ['3. Productos y Precios','Todos los productos ofrecidos en nuestra tienda son nuevos, originales y con garantía oficial del fabricante. Los precios están expresados en Soles (S/) e incluyen IGV. IP Tecnología Perú se reserva el derecho de modificar precios sin previo aviso. El precio válido para el comprador es el vigente al momento de confirmarse el pedido.'],
    ['4. Proceso de Compra','Para realizar una compra el usuario deberá: (a) registrarse con datos verídicos, (b) seleccionar los productos deseados, (c) elegir modalidad de entrega y método de pago, (d) confirmar el pedido. Una vez realizado el pago, el pedido no puede modificarse salvo casos de excepción sujetos a evaluación.'],
    ['5. Métodos de Pago','Aceptamos los siguientes métodos de pago: Yape, Plin, Transferencia bancaria (BCP) y Tarjeta de débito/crédito. <strong>No aceptamos pagos en efectivo.</strong> El pedido se procesará una vez confirmado el pago. Para Yape/Plin/Transferencia el cliente debe enviar el comprobante por WhatsApp al +51 950 923 109.'],
    ['6. Tiempos de Entrega','<strong>Delivery Lima:</strong> 24 a 48 horas hábiles según distrito. <strong>Recojo en tienda:</strong> disponible el mismo día tras confirmar el pedido. <strong>Provincia:</strong> 3 a 5 días hábiles vía courier (Olva, Shalom u similar). Los plazos son referenciales y pueden variar por causas externas.'],
    ['7. Garantía y Devoluciones','Todos nuestros productos cuentan con garantía oficial del fabricante mínima de 1 año. Para hacer válida la garantía el producto debe presentar defecto de fabricación y no daño por mal uso. Las devoluciones se aceptan dentro de los 7 días calendarios de recibido el producto, siempre que esté sin uso, en su embalaje original y con boleta/factura. Gastos de envío por devolución son asumidos por el cliente salvo error nuestro.'],
    ['8. Propiedad Intelectual','Todo el contenido del sitio web (imágenes, textos, logotipos, diseño) es propiedad de IP Tecnología Perú E.I.R.L. o de sus proveedores, y está protegido por las leyes peruanas de propiedad intelectual. Queda prohibida su reproducción sin autorización expresa.'],
    ['9. Limitación de Responsabilidad','IP Tecnología Perú no será responsable por daños indirectos derivados del uso de los productos. La responsabilidad máxima se limita al monto pagado por el producto. No nos responsabilizamos por retrasos causados por terceros (courriers, fuerza mayor, desastres naturales).'],
    ['10. Ley Aplicable y Jurisdicción','Los presentes Términos se rigen por las leyes de la República del Perú. Cualquier controversia se someterá a los tribunales de Lima, renunciando las partes a cualquier otro fuero.'],
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
