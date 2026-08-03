<?php
require_once '../includes/config.php';
requireLogin();
if (!isAdmin()) { header('Location: ' . SITE_URL . '/index.php'); exit; }
$pageTitle = 'Testimonios / Reseñas';
$pdo = getDB();
$msg = '';

// Crear tabla si no existe
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `testimonios` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nombre` varchar(120) NOT NULL,
        `empresa` varchar(120) DEFAULT NULL,
        `cargo` varchar(100) DEFAULT NULL,
        `mensaje` text NOT NULL,
        `estrellas` tinyint(1) DEFAULT 5,
        `aprobado` tinyint(1) DEFAULT 0,
        `creado_en` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Exception $e) {}

$action = $_GET['action'] ?? '';
$tid = (int)($_GET['id'] ?? 0);

if ($action === 'aprobar' && $tid) {
    $pdo->prepare("UPDATE testimonios SET aprobado=1 WHERE id=?")->execute([$tid]);
    header('Location: testimonios.php?msg=aprobado'); exit;
}
if ($action === 'rechazar' && $tid) {
    $pdo->prepare("UPDATE testimonios SET aprobado=0 WHERE id=?")->execute([$tid]);
    header('Location: testimonios.php?msg=rechazado'); exit;
}
if ($action === 'delete' && $tid) {
    $pdo->prepare("DELETE FROM testimonios WHERE id=?")->execute([$tid]);
    header('Location: testimonios.php?msg=eliminado'); exit;
}

if (isset($_GET['msg'])) {
    $msgs = ['aprobado'=>'Testimonio aprobado.','rechazado'=>'Testimonio ocultado.','eliminado'=>'Testimonio eliminado.'];
    $msg = $msgs[$_GET['msg']] ?? '';
}

$filtro = $_GET['filtro'] ?? 'todos';
$where = match($filtro) { 'aprobados'=>'WHERE aprobado=1', 'pendientes'=>'WHERE aprobado=0', default=>'' };
$testimonios = $pdo->query("SELECT * FROM testimonios $where ORDER BY creado_en DESC")->fetchAll();

$total      = $pdo->query("SELECT COUNT(*) FROM testimonios")->fetchColumn();
$aprobados  = $pdo->query("SELECT COUNT(*) FROM testimonios WHERE aprobado=1")->fetchColumn();
$pendientes = $pdo->query("SELECT COUNT(*) FROM testimonios WHERE aprobado=0")->fetchColumn();

include '../includes/header.php';
?>
<div class="container" style="padding:24px 20px 60px;">
  <div style="margin-bottom:20px;">
    <h1 style="font-size:22px;font-weight:800;"><i class="fas fa-star" style="color:var(--primary);"></i> Testimonios y Reseñas</h1>
    <div style="font-size:13px;color:var(--gray);margin-top:2px;"><a href="index.php">Dashboard</a> › Testimonios</div>
  </div>

  <!-- Stats -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px;">
    <?php foreach([['Total','fas fa-comment-dots',$total,'#3b82f6'],['Aprobados','fas fa-check-circle',$aprobados,'#22c55e'],['Pendientes','fas fa-clock',$pendientes,'#f59e0b']] as [$lbl,$ico,$n,$c]): ?>
    <div style="background:var(--dark2);border:1px solid var(--border);border-radius:12px;padding:16px;display:flex;align-items:center;gap:12px;">
      <div style="width:40px;height:40px;background:<?=$c?>20;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:<?=$c?>;"><i class="<?=$ico?>"></i></div>
      <div><div style="font-size:22px;font-weight:900;color:var(--white);"><?=$n?></div><div style="font-size:12px;color:var(--gray);"><?=$lbl?></div></div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-success" style="margin-bottom:16px;"><i class="fas fa-check-circle"></i> <?= sanitize($msg) ?></div>
  <?php endif; ?>

  <!-- Filtros -->
  <div style="display:flex;gap:8px;margin-bottom:16px;">
    <?php foreach(['todos'=>'Todos','aprobados'=>'Aprobados','pendientes'=>'Pendientes'] as $k=>$v): ?>
      <a href="testimonios.php?filtro=<?=$k?>"
         style="padding:6px 16px;border-radius:20px;font-size:13px;font-weight:700;text-decoration:none;background:<?=$filtro===$k?'var(--primary)':'var(--dark3)'?>;color:<?=$filtro===$k?'#000':'var(--gray)'?>;border:1px solid var(--border);">
        <?=$v?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Lista testimonios -->
  <?php if (empty($testimonios)): ?>
    <div style="text-align:center;padding:60px;background:var(--dark2);border-radius:14px;border:1.5px dashed var(--border);">
      <p style="color:var(--gray);">No hay testimonios en esta categoría.</p>
    </div>
  <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:12px;">
      <?php foreach($testimonios as $t): ?>
      <div style="background:var(--dark2);border:1.5px solid <?=$t['aprobado']?'rgba(34,197,94,.3)':'var(--border)'?>;border-radius:12px;padding:18px 20px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
          <div>
            <div style="font-size:15px;font-weight:700;color:var(--white);"><?= sanitize($t['nombre']) ?></div>
            <?php if($t['cargo']||$t['empresa']): ?>
              <div style="font-size:12px;color:var(--gray);">
                <?= sanitize($t['cargo']??'') ?><?= ($t['cargo']&&$t['empresa'])?' · ':'' ?><?= sanitize($t['empresa']??'') ?>
              </div>
            <?php endif; ?>
            <div style="display:flex;gap:2px;margin-top:4px;">
              <?php for($s=1;$s<=5;$s++): ?>
                <i class="fas fa-star" style="font-size:12px;color:<?=$s<=$t['estrellas']?'#D7E022':'#444'?>;"></i>
              <?php endfor; ?>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
            <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:<?=$t['aprobado']?'rgba(34,197,94,.15)':'rgba(245,158,11,.15)'?>;color:<?=$t['aprobado']?'#22c55e':'#f59e0b'?>;">
              <?= $t['aprobado'] ? 'Aprobado' : 'Pendiente' ?>
            </span>
            <span style="font-size:11px;color:var(--gray);"><?= date('d/m/Y', strtotime($t['creado_en'])) ?></span>
          </div>
        </div>
        <p style="font-size:14px;color:var(--gray2,#ccc);margin:10px 0;line-height:1.6;font-style:italic;">"<?= sanitize($t['mensaje']) ?>"</p>
        <div style="display:flex;gap:8px;">
          <?php if(!$t['aprobado']): ?>
            <a href="testimonios.php?action=aprobar&id=<?=$t['id']?>&filtro=<?=$filtro?>"
               style="padding:6px 14px;background:rgba(34,197,94,.15);color:#22c55e;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none;">
              <i class="fas fa-check"></i> Aprobar
            </a>
          <?php else: ?>
            <a href="testimonios.php?action=rechazar&id=<?=$t['id']?>&filtro=<?=$filtro?>"
               style="padding:6px 14px;background:rgba(245,158,11,.15);color:#f59e0b;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none;">
              <i class="fas fa-eye-slash"></i> Ocultar
            </a>
          <?php endif; ?>
          <a href="javascript:void(0)"
             onclick="eliminarTestimonio(<?=$t['id']?>, 'testimonios.php?action=delete&id=<?=$t['id']?>&filtro=<?=$filtro?>')"
             style="padding:6px 14px;background:rgba(239,68,68,.1);color:#ef4444;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none;">
            <i class="fas fa-trash"></i> Eliminar
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
// ── Eliminar testimonio con modal confirmar() del footer ──────
function eliminarTestimonio(id, url) {
    confirmar('¿Eliminar este testimonio?', function(ok) {
        if (ok) window.location.href = url;
    });
}
</script>

<?php include '../includes/footer.php'; ?>