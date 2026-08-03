<?php
require_once '../includes/config.php';
requireLogin();
if (!isAdmin()) { header('Location: ' . SITE_URL . '/index.php'); exit; }
$pageTitle = 'Importar Productos';
$pdo = getDB();

$msg = ''; $msgTipo = ''; $resultados = [];

// Obtener categorías para el select y la validación
$categorias = $pdo->query("SELECT id, nombre, padre_id FROM categorias ORDER BY COALESCE(padre_id,0), nombre")->fetchAll();
$catPorNombre = [];
foreach ($categorias as $c) { $catPorNombre[strtolower(trim($c['nombre']))] = $c['id']; }

// ── DESCARGAR PLANTILLA ──────────────────────────────────────────
if (($_GET['action'] ?? '') === 'plantilla') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="plantilla_productos.csv"');
    header('Cache-Control: no-cache');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    fputcsv($out, ['nombre','categoria_id','marca','modelo','descripcion','precio','precio_oferta','stock','especificaciones','destacado']);
    fputcsv($out, ['Cámara IP Domo 2MP','1','Hikvision','DS-2CD2123G2','Cámara domo con IR 30m','189.00','159.00','50','Resolución: 2MP\nIR: 30m\nConexión: RJ45','1']);
    fputcsv($out, ['Router WiFi 6 AX3000','5','TP-Link','Archer AX55','Router doble banda WiFi 6','259.00','','20','Velocidad: AX3000\nBandas: 2.4GHz + 5GHz','0']);
    fclose($out);
    exit;
}

// ── PROCESAR IMPORTACIÓN ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    $file = $_FILES['archivo'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Error al subir el archivo.'; $msgTipo = 'error';
    } elseif (!in_array($ext, ['csv', 'txt'])) {
        $msg = 'Solo se aceptan archivos CSV (.csv). Para Excel, guarda como CSV desde Excel primero.'; $msgTipo = 'error';
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        // Detectar y remover BOM UTF-8
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $cabecera  = fgetcsv($handle); // Saltar cabecera
        $insertados = 0; $errores = 0; $fila = 1;

        // Detectar delimitador
        rewind($handle);
        $primeraLinea = fgets($handle); rewind($handle); fgetcsv($handle);
        $delim = (substr_count($primeraLinea, ';') > substr_count($primeraLinea, ',')) ? ';' : ',';

        $stmt = $pdo->prepare("INSERT INTO productos 
            (categoria_id, nombre, marca, modelo, descripcion, precio, precio_oferta, stock, especificaciones, destacado, activo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");

        while (($cols = fgetcsv($handle, 2000, $delim)) !== false) {
            $fila++;
            if (count($cols) < 6 || empty(trim($cols[0]))) continue;

            $nombre    = trim($cols[0] ?? '');
            $catRaw    = trim($cols[1] ?? '');
            $marca     = trim($cols[2] ?? '');
            $modelo    = trim($cols[3] ?? '');
            $desc      = trim($cols[4] ?? '');
            $precio    = (float)str_replace(',', '.', $cols[5] ?? 0);
            $oferta    = isset($cols[6]) && $cols[6] !== '' ? (float)str_replace(',', '.', $cols[6]) : null;
            $stock     = (int)($cols[7] ?? 0);
            $espec     = trim($cols[8] ?? '');
            $destacado = (int)($cols[9] ?? 0);

            // Resolver categoría: acepta ID numérico o nombre de texto
            $catId = 0;
            if (is_numeric($catRaw)) {
                $catId = (int)$catRaw;
            } else {
                $catId = $catPorNombre[strtolower($catRaw)] ?? 0;
            }

            if (!$nombre || $precio <= 0 || !$catId) {
                $resultados[] = ['fila'=>$fila,'ok'=>false,'msg'=>"Fila $fila omitida: nombre='$nombre', precio=$precio, cat='$catRaw' (inválido)"];
                $errores++; continue;
            }

            try {
                $stmt->execute([$catId, $nombre, $marca, $modelo, $desc, $precio, $oferta, $stock, $espec, $destacado]);
                $resultados[] = ['fila'=>$fila,'ok'=>true,'msg'=>"Fila $fila: '$nombre' importado (ID ".$pdo->lastInsertId().")"];
                $insertados++;
            } catch (Exception $e) {
                $resultados[] = ['fila'=>$fila,'ok'=>false,'msg'=>"Fila $fila error BD: ".$e->getMessage()];
                $errores++;
            }
        }
        fclose($handle);

        $msg = "Importación completada: <strong>$insertados productos</strong> insertados" . ($errores ? ", <strong>$errores errores</strong>" : '') . ".";
        $msgTipo = $errores && !$insertados ? 'error' : ($errores ? 'warning' : 'success');
    }
}

include '../includes/header.php';
?>

<div style="padding:24px;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div>
      <h1 style="font-size:22px;font-weight:800;margin:0 0 4px;">
        <i class="fas fa-file-excel" style="color:#1d6f42;"></i> Importar Productos
      </h1>
      <p style="color:#888;font-size:13px;margin:0;">Carga masiva desde archivo CSV (compatible con Excel)</p>
    </div>
    <a href="<?= SITE_URL ?>/admin/productos.php" style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:var(--light);border:1.5px solid var(--border);border-radius:var(--radius);font-size:13px;font-weight:600;color:var(--text);text-decoration:none;">
      <i class="fas fa-arrow-left"></i> Volver a Productos
    </a>
  </div>

  <?php if ($msg): ?>
    <div style="padding:14px 18px;border-radius:10px;margin-bottom:20px;font-size:14px;
      background:<?= $msgTipo==='success'?'#f0fdf4':($msgTipo==='error'?'#fef2f2':'#fffbeb') ?>;
      border:1.5px solid <?= $msgTipo==='success'?'#86efac':($msgTipo==='error'?'#fca5a5':'#CEFF04') ?>;
      color:<?= $msgTipo==='success'?'#166534':($msgTipo==='error'?'#991b1b':'#92400e') ?>;">
      <i class="fas fa-<?= $msgTipo==='success'?'check-circle':($msgTipo==='error'?'times-circle':'exclamation-triangle') ?>"></i>
      <?= $msg ?>
    </div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

    <!-- PANEL IZQUIERDO: Instrucciones + Formulario -->
    <div>
      <!-- Instrucciones -->
      <div style="background:#fff;border:1.5px solid var(--border);border-radius:12px;padding:22px;margin-bottom:20px;">
        <h3 style="font-size:15px;font-weight:800;margin:0 0 14px;display:flex;align-items:center;gap:8px;">
          <i class="fas fa-info-circle" style="color:#3b82f6;"></i> Instrucciones
        </h3>
        <ol style="margin:0;padding-left:18px;font-size:13px;color:#555;line-height:2;">
          <li>Descarga la <strong>plantilla CSV</strong> con el botón de abajo</li>
          <li>Ábrela en <strong>Excel</strong> o Google Sheets</li>
          <li>Rellena los productos (uno por fila)</li>
          <li>Guarda como <strong>CSV UTF-8</strong> (Archivo → Guardar como → CSV UTF-8)</li>
          <li>Súbela con el formulario</li>
        </ol>

        <div style="margin-top:16px;background:#f8fafc;border-radius:8px;padding:12px;font-size:12px;color:#666;">
          <strong>Columnas del CSV:</strong><br>
          <code style="background:#e2e8f0;padding:2px 6px;border-radius:4px;font-size:11px;">nombre</code>
          <code style="background:#e2e8f0;padding:2px 6px;border-radius:4px;font-size:11px;">categoria_id</code>
          <code style="background:#e2e8f0;padding:2px 6px;border-radius:4px;font-size:11px;">marca</code>
          <code style="background:#e2e8f0;padding:2px 6px;border-radius:4px;font-size:11px;">modelo</code>
          <code style="background:#e2e8f0;padding:2px 6px;border-radius:4px;font-size:11px;">descripcion</code>
          <code style="background:#e2e8f0;padding:2px 6px;border-radius:4px;font-size:11px;">precio</code>
          <code style="background:#e2e8f0;padding:2px 6px;border-radius:4px;font-size:11px;">precio_oferta</code>
          <code style="background:#e2e8f0;padding:2px 6px;border-radius:4px;font-size:11px;">stock</code>
          <code style="background:#e2e8f0;padding:2px 6px;border-radius:4px;font-size:11px;">especificaciones</code>
          <code style="background:#e2e8f0;padding:2px 6px;border-radius:4px;font-size:11px;">destacado (0/1)</code>
        </div>

        <a href="?action=plantilla" style="display:inline-flex;align-items:center;gap:8px;margin-top:14px;padding:10px 18px;background:#1d6f42;color:#fff;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;transition:opacity .2s;">
          <i class="fas fa-download"></i> Descargar plantilla CSV
        </a>
      </div>

      <!-- Formulario de subida -->
      <div style="background:#fff;border:1.5px solid var(--border);border-radius:12px;padding:22px;">
        <h3 style="font-size:15px;font-weight:800;margin:0 0 16px;display:flex;align-items:center;gap:8px;">
          <i class="fas fa-upload" style="color:var(--primary);"></i> Subir archivo CSV
        </h3>
        <form method="POST" enctype="multipart/form-data">
          <div id="drop-zone" style="border:2.5px dashed var(--border);border-radius:10px;padding:32px;text-align:center;cursor:pointer;transition:all .2s;margin-bottom:16px;"
               onclick="document.getElementById('csv-file').click()"
               ondragover="event.preventDefault();this.style.borderColor='var(--primary)';this.style.background='rgba(0,100,0,.04)'"
               ondragleave="this.style.borderColor='var(--border)';this.style.background=''"
               ondrop="handleDrop(event)">
            <i class="fas fa-cloud-upload-alt" style="font-size:40px;color:#ccc;display:block;margin-bottom:10px;"></i>
            <div style="font-size:14px;font-weight:700;color:#555;">Arrastra tu CSV aquí</div>
            <div style="font-size:12px;color:#aaa;margin-top:4px;">o haz clic para seleccionar</div>
            <div id="file-name" style="margin-top:10px;font-size:13px;color:var(--primary);font-weight:600;display:none;"></div>
          </div>
          <input type="file" id="csv-file" name="archivo" accept=".csv,.txt" style="display:none" onchange="showFileName(this)">

          <div style="background:#fffbeb;border:1px solid #CEFF04;border-radius:8px;padding:10px 14px;font-size:12px;color:#92400e;margin-bottom:16px;">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Importante:</strong> Los productos nuevos se <strong>agregan</strong>, no reemplazan los existentes. Máximo recomendado: 500 productos por archivo.
          </div>

          <button type="submit" id="btn-importar" style="width:100%;padding:12px;background:var(--primary);color:white;border:none;border-radius:8px;font-size:15px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:opacity .2s;">
            <i class="fas fa-file-import"></i> Importar productos
          </button>
        </form>
      </div>
    </div>

    <!-- PANEL DERECHO: IDs de categorías + Resultados -->
    <div>
      <!-- Tabla de categorías disponibles -->
      <div style="background:#fff;border:1.5px solid var(--border);border-radius:12px;padding:22px;margin-bottom:20px;">
        <h3 style="font-size:15px;font-weight:800;margin:0 0 14px;display:flex;align-items:center;gap:8px;">
          <i class="fas fa-tags" style="color:#8b5cf6;"></i> IDs de Categorías disponibles
        </h3>
        <div style="max-height:280px;overflow-y:auto;">
          <table style="width:100%;font-size:13px;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8fafc;">
                <th style="padding:8px 10px;text-align:left;border-bottom:1.5px solid var(--border);width:50px;">ID</th>
                <th style="padding:8px 10px;text-align:left;border-bottom:1.5px solid var(--border);">Nombre</th>
                <th style="padding:8px 10px;text-align:left;border-bottom:1.5px solid var(--border);">Nivel</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($categorias as $cat): ?>
              <tr style="border-bottom:1px solid #f0f0f0;<?= is_null($cat['padre_id'])?'font-weight:700;':'' ?>">
                <td style="padding:7px 10px;color:var(--primary);font-weight:800;"><?= $cat['id'] ?></td>
                <td style="padding:7px 10px;<?= !is_null($cat['padre_id'])?'padding-left:'.(!is_null($cat['padre_id'])?'22px':'10px').';color:#666;':'' ?>">
                  <?= !is_null($cat['padre_id']) ? '└ ' : '' ?><?= htmlspecialchars($cat['nombre']) ?>
                </td>
                <td style="padding:7px 10px;font-size:11px;color:#aaa;"><?= is_null($cat['padre_id']) ? 'Principal' : 'Sub' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Resultados de la última importación -->
      <?php if (!empty($resultados)): ?>
      <div style="background:#fff;border:1.5px solid var(--border);border-radius:12px;padding:22px;">
        <h3 style="font-size:15px;font-weight:800;margin:0 0 14px;display:flex;align-items:center;gap:8px;">
          <i class="fas fa-list-check" style="color:#059669;"></i> Detalle de la importación
        </h3>
        <div style="max-height:300px;overflow-y:auto;font-size:12px;">
          <?php foreach ($resultados as $r): ?>
          <div style="padding:6px 10px;border-radius:6px;margin-bottom:4px;
            background:<?= $r['ok']?'#f0fdf4':'#fef2f2' ?>;
            color:<?= $r['ok']?'#166534':'#991b1b' ?>;
            border-left:3px solid <?= $r['ok']?'#22c55e':'#ef4444' ?>;">
            <i class="fas fa-<?= $r['ok']?'check':'times' ?>"></i> <?= htmlspecialchars($r['msg']) ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function showFileName(input) {
  const fn = document.getElementById('file-name');
  if (input.files[0]) {
    fn.textContent = '✓ ' + input.files[0].name;
    fn.style.display = 'block';
    document.getElementById('drop-zone').style.borderColor = 'var(--primary)';
  }
}
function handleDrop(e) {
  e.preventDefault();
  document.getElementById('drop-zone').style.borderColor = 'var(--border)';
  document.getElementById('drop-zone').style.background = '';
  const files = e.dataTransfer.files;
  if (files.length) {
    document.getElementById('csv-file').files = files;
    showFileName(document.getElementById('csv-file'));
  }
}
document.querySelector('form').addEventListener('submit', function() {
  const btn = document.getElementById('btn-importar');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importando...';
  btn.disabled = true;
});
</script>

<?php include '../includes/footer.php'; ?>
