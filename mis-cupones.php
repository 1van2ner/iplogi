<?php
require_once 'includes/config.php';
if (!isLoggedIn()) { header('Location: ' . SITE_URL . '/login.php'); exit; }

$pdo = getDB();
$pageTitle = 'Mis Cupones y Regalos';
include 'includes/header.php';

// Datos de usuario para la barra lateral
$s = $pdo->prepare("SELECT * FROM usuarios WHERE id=?"); $s->execute([$_SESSION['usuario_id']]);
$user = $s->fetch();
$s = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE usuario_id=?"); $s->execute([$_SESSION['usuario_id']]);
$totalPedidos = (int)$s->fetchColumn();

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

<div class="container">
  <div class="profile-layout">

    <!-- SIDEBAR (perfil) -->
    <div class="profile-sidebar">
      <div class="profile-avatar"><i class="fas fa-user" style="font-size:32px;"></i></div>
      <div class="profile-name"><?= sanitize($user['nombre'].' '.($user['apellido']??'')) ?></div>
      <div style="text-align:center;margin-bottom:6px;"><span style="background:var(--bg3);color:var(--gris3);font-size:10px;font-weight:800;padding:3px 10px;border-radius:12px;text-transform:uppercase;">Cliente</span></div>
      <div class="profile-email"><?= sanitize($user['email']) ?></div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:16px 0;padding:14px;background:var(--bg3);border-radius:var(--r);">
        <div style="text-align:center;"><div style="font-size:20px;font-weight:900;color:var(--amarillo);"><?php echo $totalPedidos ?></div><div style="font-size:10px;color:var(--gris3);">Pedidos</div></div>
        <div style="text-align:center;"><div style="font-size:20px;font-weight:900;color:var(--amarillo);"><?= number_format((int)($user['puntos'] ?? 0)) ?></div><div style="font-size:10px;color:var(--gris3);">Puntos acumulados</div></div>
      </div>

      <nav class="profile-nav">
        <a href="perfil.php?tab=datos"><i class="fas fa-user-edit"></i> Mis datos</a>
        <a href="perfil.php?tab=seguridad"><i class="fas fa-lock"></i> Contraseña</a>
        <a href="mis-pedidos.php"><i class="fas fa-history"></i> Mis Pedidos</a>
        <a href="mis-cupones.php" class="active"><i class="fas fa-gift"></i> Mis Cupones</a>
        <?php if(isAdmin()): ?>
        <div style="height:1px;background:var(--borde);margin:10px 0;"></div>
        <a href="admin/index.php" style="color:var(--amarillo);font-weight:800;"><i class="fas fa-tachometer-alt"></i> Panel Admin</a>
        <?php endif; ?>
        <div style="height:1px;background:var(--borde);margin:10px 0;"></div>
        <a href="logout.php" style="color:var(--rojo);"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
      </nav>
    </div>

    <!-- CONTENIDO -->
    <div class="profile-content">

      <div class="breadcrumb">
        <a href="<?= SITE_URL ?>/index.php">Inicio</a>
        <span>›</span>
        <a href="<?= SITE_URL ?>/perfil.php">Mi Perfil</a>
        <span>›</span>
        <strong>Mis Cupones</strong>
      </div>

      <h1 class="page-title" style="margin-bottom:8px;"><i class="fas fa-gift" style="color:var(--amarillo);"></i> Mis Cupones y Regalos</h1>
      <p style="color:var(--gris3);margin-bottom:20px;">Aquí puedes ver todos tus cupones de descuento. Cópialos y úsalos en tu próxima compra.</p>

      <?php
        // Combinar usados y expirados para la columna lateral de vencidos
        $cuponesVencidos = array_merge($cuponesUsados ?? [], $cuponesExpirados ?? []);
      ?>

      <div style="display:grid;grid-template-columns:2fr 360px;gap:24px;align-items:start;margin-bottom:20px;">

        <!-- Columna principal: Cupones Activos -->
        <div>
          <h2 style="font-size:18px;font-weight:800;color:var(--blanco);margin-bottom:12px;display:flex;align-items:center;gap:8px;"><i class="fas fa-check-circle" style="color:#D1FF05;"></i> Cupones Activos (<?= count($cuponesActivos) ?>)</h2>

          <?php if (!empty($cuponesActivos)): ?>
            <div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));">
              <?php foreach ($cuponesActivos as $cupon): ?>
                <div class="cupon-card" style="border:2px solid #D1FF05;background:var(--bg2);border-radius:8px;padding:18px;position:relative;overflow:hidden;">
                  <div style="position:absolute;top:0;left:0;width:100%;height:3px;background:linear-gradient(90deg,#D1FF05,var(--amarillo));border-top-left-radius:6px;border-top-right-radius:6px;"></div>
                  <div style="padding-top:8px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                      <div>
                        <div style="font-size:13px;color:var(--gris3);font-weight:600;text-transform:uppercase;">Código:</div>
                        <div style="font-size:16px;font-weight:900;color:var(--blanco);font-family:monospace;letter-spacing:1px;">
                          <?= sanitize($cupon['codigo_personal']) ?>
                        </div>
                      </div>
                      <button onclick="copiarCodigo('<?= sanitize($cupon['codigo_personal']) ?>', this)" style="background:#D1FF05;color:var(--negro);border:none;padding:8px 12px;border-radius:6px;cursor:pointer;font-weight:700;font-size:12px;">
                        <i class="fas fa-copy"></i> Copiar
                      </button>
                    </div>

                    <p style="color:var(--blanco);font-weight:600;margin:0 0 12px;"><?= sanitize($cupon['descripcion'] ?? 'Cupón de descuento') ?></p>

                    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;background:var(--bg3);border-radius:6px;margin-bottom:12px;">
                      <div>
                        <div style="font-size:11px;color:var(--gris3);">Descuento</div>
                        <div style="font-size:18px;font-weight:900;color:#000;">
                          <?php if (($cupon['tipo_descuento'] ?? 'porcentaje') === 'monto') { echo 'S/ ' . number_format((float)$cupon['descuento'], 2); } else { echo (int)($cupon['descuento'] ?? 0) . '%'; } ?>
                        </div>
                      </div>
                      <div style="text-align:right;">
                        <div style="font-size:11px;color:var(--gris3);">Vence en</div>
                        <div style="font-size:13px;font-weight:700;color:#000;"><?= date('d/m/Y', strtotime($cupon['fecha_expiracion'])) ?></div>
                      </div>
                    </div>

                    <?php if (!empty($cupon['compra_minima'])): ?>
                      <div style="font-size:12px;color:var(--gris2);padding:8px;background:rgba(255,193,7,.06);border-radius:6px;margin-bottom:12px;"><i class="fas fa-info-circle" style="color:var(--amarillo);"></i> Compra mínima: S/ <?= number_format((float)$cupon['compra_minima'], 2) ?></div>
                    <?php endif; ?>

                    <a href="<?= SITE_URL ?>/checkout.php" style="display:block;text-align:center;background:#D1FF05;color:var(--negro);padding:10px;border-radius:6px;font-weight:700;text-decoration:none;"><i class="fas fa-shopping-cart"></i> Usar Este Cupón</a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div style="border:1px solid var(--borde);background:var(--bg3);border-radius:8px;padding:28px 20px;display:flex;align-items:center;gap:18px;">
              <div style="font-size:32px;color:var(--gris3);width:56px;text-align:center;"><i class="fas fa-inbox"></i></div>
              <div style="flex:1;color:var(--gris2);">
                <div style="font-weight:700;font-size:16px;margin-bottom:6px;color:var(--gris2);">No tienes cupones activos</div>
                <div style="font-size:14px;">Explora nuestras promociones y aplica un cupón en tu próxima compra.</div>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <!-- Columna lateral: Cupones Vencidos -->
        <aside>
          <h2 style="font-size:18px;font-weight:800;color:var(--blanco);margin-bottom:12px;display:flex;align-items:center;gap:8px;"><i class="fas fa-history" style="color:var(--rojo);"></i> Cupones Vencidos (<?= count($cuponesVencidos) ?>)</h2>

          <?php if (!empty($cuponesVencidos)): ?>
            <div style="display:grid;gap:16px;grid-template-columns:1fr;">
              <?php foreach ($cuponesVencidos as $cupon): ?>
                <div style="border:2px solid rgba(180,180,180,0.25);background:var(--bg3);border-radius:8px;padding:14px;position:relative;opacity:0.95;">
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <div>
                      <div style="font-size:12px;color:var(--gris3);font-weight:600;text-transform:uppercase;">Código:</div>
                      <div style="font-size:14px;font-weight:800;color:var(--gris2);font-family:monospace;letter-spacing:1px;"><?= sanitize($cupon['codigo_personal']) ?></div>
                    </div>
                    <div style="text-align:right;min-width:80px;">
                      <div style="font-size:12px;color:var(--gris3);font-weight:700;"><?= !empty($cupon['usado']) ? 'Usado' : 'Expirado' ?></div>
                      <div style="font-size:12px;color:var(--rojo);font-weight:600;"><?= date('d/m/Y', strtotime($cupon['fecha_expiracion'])) ?></div>
                    </div>
                  </div>
                  <p style="color:var(--gris3);margin:0 0 12px;"><?= sanitize($cupon['descripcion'] ?? '') ?></p>
                  <div style="display:flex;gap:8px;align-items:center;justify-content:flex-end;"><button disabled style="background:transparent;border:1px solid rgba(0,0,0,0.06);padding:8px 12px;border-radius:6px;color:var(--gris3);">No disponible</button></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div style="padding:14px;background:var(--bg2);border-radius:6px;text-align:center;color:var(--gris2);">No hay cupones vencidos.</div>
          <?php endif; ?>
        </aside>

      </div>

    </div><!-- /profile-content -->

  </div><!-- /profile-layout -->
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
