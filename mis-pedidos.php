<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/iptecnologia/includes/config.php';

try {
    $pdo = getDB();
} catch (Exception $e) {
    die("Error de conexión: " . $e->getMessage());
}

$pedidos = [];
try {
    $stmt = $pdo->query(
        "SELECT p.id, p.total, p.estado, p.metodo_pago, p.tipo_entrega, p.creado_en, u.nombre, u.apellido
         FROM pedidos p
         JOIN usuarios u ON p.usuario_id = u.id
         ORDER BY p.creado_en DESC LIMIT 15"
    );
    $pedidos = $stmt->fetchAll();
} catch (Exception $e) {
    // Si falla, se queda vacío
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/iptecnologia/includes/header.php';

// Asegurar que el usuario esté logueado y obtener datos de perfil para la barra lateral
if (!isLoggedIn()) { header('Location: ' . SITE_URL . '/login.php'); exit; }
$pdo = getDB();
$s = $pdo->prepare("SELECT * FROM usuarios WHERE id=?"); $s->execute([$_SESSION['usuario_id']]);
$user = $s->fetch();
$s = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE usuario_id=?"); $s->execute([$_SESSION['usuario_id']]);
$totalPedidos = (int)$s->fetchColumn();

?>

<div class="container">
  <div class="profile-layout">

    <!-- SIDEBAR (misma que en perfil.php) -->
    <div class="profile-sidebar">
      <div class="profile-avatar"><i class="fas fa-user" style="font-size:32px;"></i></div>
      <div class="profile-name"><?= sanitize($user['nombre'].' '.($user['apellido']??'')) ?></div>
      <div style="text-align:center;margin-bottom:6px;">
        <span style="background:var(--bg3);color:var(--gris3);font-size:10px;font-weight:800;padding:3px 10px;border-radius:12px;text-transform:uppercase;">
          Cliente
        </span>
      </div>
      <div class="profile-email"><?= sanitize($user['email']) ?></div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:16px 0;padding:14px;background:var(--bg3);border-radius:var(--r);">
        <div style="text-align:center;"><div style="font-size:20px;font-weight:900;color:var(--amarillo);"><?php echo $totalPedidos ?></div><div style="font-size:10px;color:var(--gris3);">Pedidos</div></div>
        <div style="text-align:center;"><div style="font-size:20px;font-weight:900;color:var(--amarillo);"><?= number_format((int)($user['puntos'] ?? 0)) ?></div><div style="font-size:10px;color:var(--gris3);">Puntos acumulados</div></div>
      </div>

      <nav class="profile-nav">
        <a href="perfil.php?tab=datos"><i class="fas fa-user-edit"></i> Mis datos</a>
        <a href="perfil.php?tab=seguridad"><i class="fas fa-lock"></i> Contraseña</a>
        <a href="mis-pedidos.php" class="active"><i class="fas fa-history"></i> Mis Pedidos</a>
        <a href="mis-cupones.php"><i class="fas fa-gift"></i> Mis Cupones</a>
        <?php if(isAdmin()): ?>
        <div style="height:1px;background:var(--borde);margin:10px 0;"></div>
        <a href="admin/index.php" style="color:var(--amarillo);font-weight:800;"><i class="fas fa-tachometer-alt"></i> Panel Admin</a>
        <?php endif; ?>
        <div style="height:1px;background:var(--borde);margin:10px 0;"></div>
        <a href="logout.php" style="color:var(--rojo);"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
      </nav>
    </div>

    <!-- CONTENIDO PRINCIPAL -->
    <div class="profile-content">

      <!-- TÍTULO LLAMATIVO -->
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; background: linear-gradient(135deg, #bae90e, #bae90e); padding: 18px 22px; border-radius: 12px; color: #000000;">
          <div>
              <h1 style="margin: 0; font-size: 20px; font-weight: 800; color: #2b2a2a;"><i class="fas fa-shipping-fast"></i> Panel de Control y Seguimiento</h1>
              <p style="margin: 4px 0 0 0; font-size: 13px; font-weight: 600; color: #000000;">Visualización de pedidos y rastreo de estado en tiempo real</p>
          </div>
          <div style="background: rgba(255,255,255,0.4); padding: 6px 12px; border-radius: 16px; font-weight: 700; font-size: 13px; color: #000000;">
              Total Registros: <?= count($pedidos) ?>
          </div>
      </div>

      <!-- TABLA LIMPIA (SOLO LECTURA) -->
      <div style="background: white; border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.04); overflow: hidden; border: 1px solid #e2e8f0;">
          <div style="overflow-x: auto;">
              <table style="width: 100%; border-collapse: collapse; text-align: left; white-space: nowrap;">
                  <thead>
                      <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #000000; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 800;">
                          <th style="padding: 14px 18px; color: #000000;">Cliente</th>
                          <th style="padding: 14px 18px; color: #000000;">Estado Actual</th>
                          <th style="padding: 14px 18px; color: #000000;">Total</th>
                          <th style="padding: 14px 18px; text-align: center; color: #000000;">Acción / Seguimiento</th>
                      </tr>
                  </thead>
                  <tbody>
                      <?php if (empty($pedidos)): ?>
                          <tr>
                              <td colspan="4" style="padding: 30px; text-align: center; color: #64748b; font-weight: 600;">
                                  No hay pedidos registrados en este momento.
                              </td>
                          </tr>
                      <?php else: ?>
                          <?php foreach ($pedidos as $index => $p): 
                              $bgRow = ($index % 2 == 0) ? '#ffffff' : '#fcfcfc';
                              
                              $badgeBg = '#f1f5f9';
                              $badgeColor = '#000000';
                              if ($p['estado'] == 'pendiente') { $badgeBg = '#fef3c7'; $badgeColor = '#000000'; }
                              elseif ($p['estado'] == 'procesando') { $badgeBg = '#e0f2fe'; $badgeColor = '#000000'; }
                              elseif ($p['estado'] == 'enviado') { $badgeBg = '#ede9fe'; $badgeColor = '#000000'; }
                              elseif ($p['estado'] == 'entregado') { $badgeBg = '#d1fae5'; $badgeColor = '#000000'; }
                          ?>
                          <tr style="border-bottom: 1px solid #f1f5f9; background: <?= $bgRow ?>;">
                              <td style="padding: 14px 18px; color: #000000; font-weight: 700;">
                                  <?= htmlspecialchars(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? '')) ?>
                              </td>
                              <td style="padding: 14px 18px;">
                                  <span style="display: inline-block; padding: 6px 14px; border-radius: 20px; font-weight: 800; font-size: 12px; background: <?= $badgeBg ?>; color: <?= $badgeColor ?>; text-transform: capitalize;">
                                      <?= htmlspecialchars($p['estado']) ?>
                                  </span>
                              </td>
                              <td style="padding: 14px 18px; font-weight: 800; color: #000000; font-size: 15px;">
                                  $<?= number_format($p['total'], 2) ?>
                              </td>
                              <td style="padding: 14px 18px; text-align: center;">
                                  <button type="button" onclick="verSeguimiento('<?= htmlspecialchars((string)$p['id']) ?>', '<?= htmlspecialchars($p['estado']) ?>')" style="background: linear-gradient(135deg, #c8ff00, #c8ff00); color: #000000; border: none; padding: 9px 18px; border-radius: 8px; cursor: pointer; font-weight: 800; font-size: 13px; display: inline-flex; align-items: center; gap: 7px; box-shadow: 0 4px 10px rgba(0,0,0,0.12);">
                                      <i class="fas fa-route" style="color: #000000;"></i> Seguir
                                  </button>
                              </td>
                          </tr>
                          <?php endforeach; ?>
                      <?php endif; ?>
                  </tbody>
              </table>
          </div>
      </div>

    </div><!-- /profile-content -->

  </div><!-- /profile-layout -->
</div><!-- /container -->

<!-- MODAL DE SEGUIMIENTO CON LETRAS NEGRAS -->
<div id="modalSeguimiento" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:white; padding:35px 40px; border-radius:20px; width:92%; max-width:800px; position:relative; box-shadow: 0 25px 50px rgba(0,0,0,0.25);">
        
        <!-- CABECERA DEL MODAL -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; border-bottom:1px solid #f1f5f9; padding-bottom:18px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="background:#f0f9ff; color:#000000; width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:18px; border:1px solid #080808;">
                    <i class="fas fa-shipping-fast" style="color: #000000;"></i>
                </div>
                <h3 id="modalTitulo" style="margin:0; font-size:22px; color:#000000; font-weight:900;">Seguimiento del Pedido</h3>
            </div>
            <button type="button" onclick="document.getElementById('modalSeguimiento').style.display='none'" style="background:#f8fafc; border:1px solid #e2e8f0; color:#000000; width:36px; height:36px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:16px; font-weight:800; transition:all 0.2s;">
                <i class="fas fa-times" style="color: #000000;"></i>
            </button>
        </div>
        
        <!-- CONTENEDOR UNIFICADO DE LA LÍNEA Y LOS 4 PASOS -->
        <div style="padding: 0 28px; margin-bottom: 40px; margin-top: 10px;">
            <div style="position:relative; width:100%;">
                
                <!-- 1. Barra de Progreso de fondo gris -->
                <div style="position:absolute; top:28px; left:28px; right:28px; height:8px; background:#e2e8f0; border-radius:4px; z-index:1;">
                    <!-- Barra verde de avance dinámico -->
                    <div id="barraProgreso" style="position:absolute; top:0; left:0; width:0%; height:8px; background:linear-gradient(90deg, #10b981, #059669); border-radius:4px; transition: width 0.5s ease;"></div>
                </div>

                <!-- 2. Los 4 Nodos distribuidos en flex -->
                <div style="display:flex; justify-content:space-between; align-items:center; position:relative; z-index:3;">
                    
                    <!-- PASO 1: Pendiente -->
                    <div class="step-col" data-index="0" style="text-align:center; flex:0 0 auto;">
                        <div class="step-icon-wrap" style="width:56px; height:56px; border-radius:50%; background:white; border:2px solid #cbd5e1; margin:0 auto; display:flex; align-items:center; justify-content:center; color:#000000; font-size:20px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition:all 0.3s;">
                            <i class="fas fa-check" style="color: #000000;"></i>
                        </div>
                        <div style="font-weight:800; color:#000000; font-size:15px; margin-top:12px;">Pendiente</div>
                        <div class="step-badge" style="display:inline-block; margin-top:6px; padding:4px 12px; border-radius:12px; font-size:11px; font-weight:800; background:#f1f5f9; color:#000000;">Pendiente</div>
                    </div>

                    <!-- PASO 2: Procesando -->
                    <div class="step-col" data-index="1" style="text-align:center; flex:0 0 auto;">
                        <div class="step-icon-wrap" style="width:56px; height:56px; border-radius:50%; background:white; border:2px solid #cbd5e1; margin:0 auto; display:flex; align-items:center; justify-content:center; color:#000000; font-size:20px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition:all 0.3s;">
                            <i class="fas fa-box" style="color: #000000;"></i>
                        </div>
                        <div style="font-weight:800; color:#000000; font-size:15px; margin-top:12px;">Procesando</div>
                        <div class="step-badge" style="display:inline-block; margin-top:6px; padding:4px 12px; border-radius:12px; font-size:11px; font-weight:800; background:#f1f5f9; color:#000000;">Pendiente</div>
                    </div>

                    <!-- PASO 3: Enviado -->
                    <div class="step-col" data-index="2" style="text-align:center; flex:0 0 auto;">
                        <div class="step-icon-wrap" style="width:56px; height:56px; border-radius:50%; background:white; border:2px solid #cbd5e1; margin:0 auto; display:flex; align-items:center; justify-content:center; color:#000000; font-size:20px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition:all 0.3s;">
                            <i class="fas fa-check" style="color: #000000;"></i>
                        </div>
                        <div style="font-weight:800; color:#000000; font-size:15px; margin-top:12px;">Enviado</div>
                        <div class="step-badge" style="display:inline-block; margin-top:6px; padding:4px 12px; border-radius:12px; font-size:11px; font-weight:800; background:#f1f5f9; color:#000000;">Pendiente</div>
                    </div>

                    <!-- PASO 4: Entregado -->
                    <div class="step-col" data-index="3" style="text-align:center; flex:0 0 auto;">
                        <div class="step-icon-wrap" style="width:56px; height:56px; border-radius:50%; background:white; border:2px solid #cbd5e1; margin:0 auto; display:flex; align-items:center; justify-content:center; color:#000000; font-size:20px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition:all 0.3s;">
                            <i class="fas fa-check" style="color: #000000;"></i>
                        </div>
                        <div style="font-weight:800; color:#000000; font-size:15px; margin-top:12px;">Entregado</div>
                        <div class="step-badge" style="display:inline-block; margin-top:6px; padding:4px 12px; border-radius:12px; font-size:11px; font-weight:800; background:#f1f5f9; color:#000000;">Pendiente</div>
                    </div>

                </div>

            </div>
        </div>

        <!-- CAJA DE ESTADO ACTUAL EN RUTA -->
        <div style="background:#f0f9ff; border:1px solid #edfdba; border-radius:14px; padding:18px 25px; display:flex; align-items:center; justify-content:center; gap:12px; margin-bottom:30px;">
            <div style="background:#c8ff00; color:#000000; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:14px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
                <i class="fas fa-paper-plane" style="color: #000000;"></i>
            </div>
            <div style="font-size:15px; color:#000000; font-weight:800;">
                Estado Actual en Ruta: <span id="textoEstadoActual" style="color:#000000; font-weight:900;">Procesando</span>
            </div>
        </div>

        <!-- PIE DE MODAL (BOTÓN CERRAR) -->
        <div style="text-align: right;">
            <button type="button" onclick="document.getElementById('modalSeguimiento').style.display='none'" style="padding:12px 28px; background:#e2e8f0; border:none; border-radius:10px; cursor:pointer; font-weight:800; color:#000000; font-size:14px; display:inline-flex; align-items:center; gap:8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <i class="fas fa-times-circle" style="color: #000000;"></i> Cerrar Ventana
            </button>
        </div>

    </div>
</div>

<script>
function verSeguimiento(codigo, estadoActual) {
    const modal = document.getElementById('modalSeguimiento');
    document.getElementById('modalTitulo').innerText = 'Seguimiento del Pedido: #' + codigo;
    modal.style.display = 'flex';

    let stepIndex = 0;
    if (estadoActual === 'pendiente') stepIndex = 0;
    else if (estadoActual === 'procesando') stepIndex = 1;
    else if (estadoActual === 'enviado') stepIndex = 2;
    else if (estadoActual === 'entregado') stepIndex = 3;

    const porcentajes = [0, 33.33, 66.66, 100];
    document.getElementById('barraProgreso').style.width = porcentajes[stepIndex] + '%';

    const cols = document.querySelectorAll('.step-col');
    const nombresPasos = ['Pendiente', 'Procesando', 'Enviado', 'Entregado'];

    cols.forEach((col, idx) => {
        const iconWrap = col.querySelector('.step-icon-wrap');
        const badge = col.querySelector('.step-badge');
        
        let iconHtml = '<i class="fas fa-check" style="color: #000000;"></i>';
        if (idx === 1) iconHtml = '<i class="fas fa-box" style="color: #000000;"></i>';

        if (idx < stepIndex) {
            iconWrap.style.background = '#ffffff';
            iconWrap.style.borderColor = '#10b981';
            iconWrap.style.color = '#000000';
            iconWrap.style.boxShadow = '0 4px 12px rgba(16,185,129,0.2)';
            iconWrap.innerHTML = '<i class="fas fa-check" style="color: #000000;"></i>';
            
            badge.style.background = '#d1fae5';
            badge.style.color = '#000000';
            badge.innerText = 'Completado';
        } else if (idx === stepIndex) {
            iconWrap.style.background = '#fffffd';
            iconWrap.style.borderColor = '#ffffff';
            iconWrap.style.color = '#000000';
            iconWrap.style.boxShadow = '0 6px 20px rgba(0,0,0,0.15)';
            iconWrap.innerHTML = iconHtml;
            
            badge.style.background = '#f2ffb0';
            badge.style.color = '#000000';
            badge.innerText = 'En curso';
        } else {
            iconWrap.style.background = '#ffffff';
            iconWrap.style.borderColor = '#cbd5e1';
            iconWrap.style.color = '#000000';
            iconWrap.style.boxShadow = 'none';
            iconWrap.innerHTML = iconHtml;
            
            badge.style.background = '#f1f5f9';
            badge.style.color = '#2b2b2b';
            badge.innerText = 'Pendiente';
        }
    });

    document.getElementById('textoEstadoActual').innerText = nombresPasos[stepIndex];
}
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/iptecnologia/includes/footer.php'; ?>