<?php
require_once 'includes/config.php';
if (!isLoggedIn()) { header('Location: ' . SITE_URL . '/login.php'); exit; }

$pdo = getDB();
$pageTitle = 'Mis Cupones y Regalos';
include 'includes/header.php';

// Obtener cupones activos (no usados y dentro de vigencia)
$stmtActivos = $pdo->prepare("
    SELECT c.*, uc.codigo_personal, uc.fecha_expiracion, uc.usado
    FROM usuario_cupones uc
    JOIN cupones c ON uc.cupon_id = c.id
    WHERE uc.usuario_id = ? AND uc.usado = 0 AND uc.fecha_expiracion >= CURDATE()
    ORDER BY uc.fecha_expiracion ASC
");
$stmtActivos->execute([$_SESSION['usuario_id']]);
$cuponesActivos = $stmtActivos->fetchAll();

// Obtener cupones ya usados
$stmtUsados = $pdo->prepare("
    SELECT c.*, uc.codigo_personal, uc.fecha_expiracion, uc.usado
    FROM usuario_cupones uc
    JOIN cupones c ON uc.cupon_id = c.id
    WHERE uc.usuario_id = ? AND uc.usado = 1
    ORDER BY uc.fecha_expiracion DESC
");
$stmtUsados->execute([$_SESSION['usuario_id']]);
$cuponesUsados = $stmtUsados->fetchAll();

// Obtener cupones expirados
$stmtExpirados = $pdo->prepare("
    SELECT c.*, uc.codigo_personal, uc.fecha_expiracion, uc.usado
    FROM usuario_cupones uc
    JOIN cupones c ON uc.cupon_id = c.id
    WHERE uc.usuario_id = ? AND uc.usado = 0 AND uc.fecha_expiracion < CURDATE()
    ORDER BY uc.fecha_expiracion DESC
");
$stmtExpirados->execute([$_SESSION['usuario_id']]);
$cuponesExpirados = $stmtExpirados->fetchAll();
?>

<div class="container" style="padding:30px 20px 60px;">
  <div class="breadcrumb">
    <a href="<?= SITE_URL ?>/index.php">Inicio</a>
    <span>›</span>
    <a href="<?= SITE_URL ?>/perfil.php">Mi Perfil</a>
    <span>›</span>
    <strong>Mis Cupones</strong>
  </div>

  <h1 class="page-title" style="margin-bottom:8px;">
    <i class="fas fa-gift" style="color:var(--amarillo);"></i> Mis Cupones y Regalos
  </h1>
  <p style="color:var(--gris3);margin-bottom:30px;">Aquí puedes ver todos tus cupones de descuento. Cópialos y úsalos en tu próxima compra.</p>

  <!-- ════════════════════════════════════════════════════════════ -->
  <!-- CUPONES ACTIVOS -->
  <!-- ════════════════════════════════════════════════════════════ -->
  <div style="margin-bottom:40px;">
    <h2 style="font-size:18px;font-weight:800;color:var(--blanco);margin-bottom:16px;display:flex;align-items:center;gap:8px;">
      <i class="fas fa-check-circle" style="color:#D1FF05;"></i> 
      Cupones Activos (<?= count($cuponesActivos) ?>)
    </h2>

    <?php if (!empty($cuponesActivos)): ?>
      <div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));">
        <?php foreach ($cuponesActivos as $cupon): ?>
          <div style="border:2px solid #D1FF05;background:var(--bg2);border-radius:var(--r);padding:18px;position:relative;overflow:hidden;">
            <!-- Línea decorativa -->
            <div style="position:absolute;top:0;left:0;width:100%;height:3px;background:linear-gradient(90deg, #D1FF05, var(--amarillo));"></div>

            <div style="padding-top:6px;">
              <!-- Código -->
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <div>
                  <div style="font-size:13px;color:var(--gris3);font-weight:600;text-transform:uppercase;">Código:</div>
                  <div style="font-size:16px;font-weight:900;color:var(--blanco);font-family:monospace;letter-spacing:1px;">
                    <?= sanitize($cupon['codigo_personal']) ?>
                  </div>
                </div>
                <button onclick="copiarCodigo('<?= sanitize($cupon['codigo_personal']) ?>', this)" 
                  style="background:#D1FF05;color:var(--negro);border:none;padding:8px 12px;border-radius:6px;cursor:pointer;font-weight:700;font-size:12px;transition:all .2s;">
                  <i class="fas fa-copy"></i> Copiar
                </button>
              </div>

              <!-- Descripción -->
              <div style="margin-bottom:14px;">
                <p style="color:var(--blanco);font-weight:600;margin:0 0 4px;">
                  <?= sanitize($cupon['descripcion'] ?? 'Cupón de descuento') ?>
                </p>
              </div>

              <!-- Descuento y validez -->
              <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:var(--bg3);border-radius:6px;margin-bottom:12px;">
                <div>
                  <div style="font-size:11px;color:var(--gris3);margin-bottom:2px;">Descuento</div>
                  <div style="font-size:18px;font-weight:900;color:#000;">
                    <?php 
                      if (($cupon['tipo_descuento'] ?? 'porcentaje') === 'monto') {
                        echo 'S/ ' . number_format((float)$cupon['descuento'], 2);
                      } else {
                        echo (int)($cupon['descuento'] ?? 0) . '%';
                      }
                    ?>
                  </div>
                </div>
                <div style="text-align:right;">
                  <div style="font-size:11px;color:var(--gris3);margin-bottom:2px;">Vence en</div>
                  <div style="font-size:13px;font-weight:700;color:#000;">
                    <?= date('d/m/Y', strtotime($cupon['fecha_expiracion'])) ?>
                  </div>
                </div>
              </div>

              <!-- Información adicional -->
              <?php if (!empty($cupon['compra_minima'])): ?>
                <div style="font-size:12px;color:var(--gris2);padding:8px;background:rgba(255,193,7,.1);border-radius:6px;margin-bottom:10px;">
                  <i class="fas fa-info-circle" style="color:var(--amarillo);"></i>
                  Compra mínima: S/ <?= number_format((float)$cupon['compra_minima'], 2) ?>
                </div>
              <?php endif; ?>

              <!-- Botón usar -->
              <a href="<?= SITE_URL ?>/checkout.php" style="display:block;text-align:center;background:#D1FF05;color:var(--negro);padding:10px;border-radius:6px;font-weight:700;text-decoration:none;transition:all .2s;">
                <i class="fas fa-shopping-cart"></i> Usar Este Cupón
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div style="padding:30px;text-align:center;background:var(--bg2);border-radius:var(--r);border-left:4px solid var(--gris2);">
        <i class="fas fa-inbox" style="font-size:32px;color:var(--gris3);margin-bottom:10px;display:block;"></i>
        <p style="color:var(--gris2);margin:0;">No tienes cupones activos en este momento.</p>
      </div>
    <?php endif; ?>
  </div>

  <!-- ════════════════════════════════════════════════════════════ -->
  <!-- CUPONES USADOS -->
  <!-- ════════════════════════════════════════════════════════════ -->
  <?php if (!empty($cuponesUsados)): ?>
  <div style="margin-bottom:40px;">
    <h2 style="font-size:18px;font-weight:800;color:var(--blanco);margin-bottom:16px;display:flex;align-items:center;gap:8px;">
      <i class="fas fa-check" style="color:var(--gris3);"></i> 
      Cupones Usados (<?= count($cuponesUsados) ?>)
    </h2>

    <div style="display:grid;gap:12px;">
      <?php foreach ($cuponesUsados as $cupon): ?>
        <div style="border:1px solid var(--borde);background:var(--bg3);border-radius:var(--r);padding:14px;display:flex;justify-content:space-between;align-items:center;opacity:.7;">
          <div>
            <div style="font-weight:700;color:var(--blanco);">
              <?= sanitize($cupon['codigo_personal']) ?>
            </div>
            <div style="font-size:12px;color:var(--gris3);">
              <?= sanitize($cupon['descripcion'] ?? '') ?>
            </div>
          </div>
          <div style="text-align:right;font-size:12px;color:var(--gris3);">
            <span style="display:block;font-weight:700;">Usado</span>
            <?= date('d/m/Y', strtotime($cupon['fecha_expiracion'])) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ════════════════════════════════════════════════════════════ -->
  <!-- CUPONES EXPIRADOS -->
  <!-- ════════════════════════════════════════════════════════════ -->
  <?php if (!empty($cuponesExpirados)): ?>
  <div>
    <h2 style="font-size:18px;font-weight:800;color:var(--blanco);margin-bottom:16px;display:flex;align-items:center;gap:8px;">
      <i class="fas fa-times-circle" style="color:var(--rojo);"></i> 
      Cupones Expirados (<?= count($cuponesExpirados) ?>)
    </h2>

    <div style="display:grid;gap:12px;">
      <?php foreach ($cuponesExpirados as $cupon): ?>
        <div style="border:1px solid var(--borde);background:var(--bg3);border-radius:var(--r);padding:14px;display:flex;justify-content:space-between;align-items:center;opacity:.5;">
          <div>
            <div style="font-weight:700;color:var(--gris2);">
              <?= sanitize($cupon['codigo_personal']) ?>
            </div>
            <div style="font-size:12px;color:var(--gris3);">
              <?= sanitize($cupon['descripcion'] ?? '') ?>
            </div>
          </div>
          <div style="text-align:right;font-size:12px;color:var(--rojo);font-weight:700;">
            Expirado: <?= date('d/m/Y', strtotime($cupon['fecha_expiracion'])) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<style>
.cupon-card {
  transition: all .3s;
}
.cupon-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0,0,0,.2);
}
</style>

<script>
function copiarCodigo(codigo, btn) {
  navigator.clipboard.writeText(codigo).then(() => {
    const iconoOriginal = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Copiado';
    btn.style.background = 'var(--amarillo)';
    btn.style.color = 'var(--negro)';
    setTimeout(() => {
      btn.innerHTML = iconoOriginal;
      btn.style.background = '#D1FF05';
      btn.style.color = 'var(--negro)';
    }, 2000);
  });
}
</script>

<?php include 'includes/footer.php'; ?>
