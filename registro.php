<?php
require_once 'includes/config.php';
include_once 'includes/funciones_cupones.php';
if (isLoggedIn()) { header('Location: ' . SITE_URL . '/index.php'); exit; }

// Cargar email_verification
require_once 'includes/email_verification.php';

// ── ENCUESTA: calcula el rol automáticamente según las respuestas ──────
// Nada de texto libre, todo por selección (botones/radios).
function calcularRolEncuesta($motivo, $frecuencia, $camaras, $servicio) {
    $puntos = ['cliente_final' => 0, 'tecnico' => 0, 'proyectista' => 0, 'distribuidor' => 0];

    // Cada pregunta reparte puntos a uno o dos roles (cuando la respuesta es ambigua).
    $mapas = [
        'motivo' => [
            'instalar_propio'   => ['cliente_final' => 2],
            'disenio_proyecto'  => ['proyectista'   => 2],
            'instalar_terceros' => ['tecnico'        => 2],
            'reventa'           => ['distribuidor'   => 2],
        ],
        'frecuencia' => [
            'una_vez'       => ['cliente_final' => 2],
            'algunas_veces' => ['tecnico' => 1, 'proyectista' => 1], // técnico/proyectista
            'varias_veces'  => ['distribuidor' => 2],
        ],
        'camaras' => [
            'si_frecuente' => ['proyectista'   => 2],
            'a_veces'      => ['tecnico'       => 2],
            'no_una_vez'   => ['cliente_final' => 2],
        ],
        'servicio' => [
            'asesoria_proyectos' => ['proyectista' => 2],
            'soporte_postventa'  => ['tecnico' => 1, 'cliente_final' => 1], // técnico/final
            'compras_volumen'    => ['distribuidor'   => 2],
            'necesito_asesor'    => ['cliente_final'  => 2],
        ],
    ];

    $respuestas = ['motivo' => $motivo, 'frecuencia' => $frecuencia, 'camaras' => $camaras, 'servicio' => $servicio];

    foreach ($mapas as $pregunta => $mapa) {
        $val = $respuestas[$pregunta];
        if (isset($mapa[$val])) {
            foreach ($mapa[$val] as $rol => $pts) $puntos[$rol] += $pts;
        }
    }

    arsort($puntos);
    return array_key_first($puntos);
}

$errors = [];
$d = [
    'tipo_documento'  => 'DNI',
    'dni_ruc'         => '',
    'nombre'          => '',
    'apellido'        => '',
    'email'           => '',
    'celular'         => '',
    'direccion_entrega'=> '',
    'fecha_nacimiento' => '',
];
$enc = [
    'motivo'     => '',
    'frecuencia' => '',
    'camaras'    => '',
    'servicio'   => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d['tipo_documento']     = in_array($_POST['tipo_documento'] ?? '', ['DNI','RUC']) ? $_POST['tipo_documento'] : 'DNI';
    $d['dni_ruc']            = sanitize($_POST['dni_ruc']            ?? '');
    $d['nombre']             = sanitize($_POST['nombre']             ?? '');
    $d['apellido']           = sanitize($_POST['apellido']           ?? '');
    $d['email']              = sanitize($_POST['email']              ?? '');
    $d['celular']            = sanitize($_POST['celular']            ?? '');
    $d['direccion_entrega']  = sanitize($_POST['direccion_entrega']  ?? '');
    $d['fecha_nacimiento']   = sanitize($_POST['fecha_nacimiento']   ?? '');
    $pass  = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';

    $enc['motivo']     = sanitize($_POST['enc_motivo']     ?? '');
    $enc['frecuencia'] = sanitize($_POST['enc_frecuencia'] ?? '');
    $enc['camaras']    = sanitize($_POST['enc_camaras']    ?? '');
    $enc['servicio']   = sanitize($_POST['enc_servicio']   ?? '');

    // Validaciones
    if (!in_array($enc['motivo'],     ['instalar_propio','disenio_proyecto','instalar_terceros','reventa']))          $errors['enc_motivo']     = 'Selecciona una opción.';
    if (!in_array($enc['frecuencia'], ['una_vez','algunas_veces','varias_veces']))                                    $errors['enc_frecuencia'] = 'Selecciona una opción.';
    if (!in_array($enc['camaras'],    ['si_frecuente','a_veces','no_una_vez']))                                       $errors['enc_camaras']    = 'Selecciona una opción.';
    if (!in_array($enc['servicio'],   ['asesoria_proyectos','soporte_postventa','compras_volumen','necesito_asesor'])) $errors['enc_servicio']   = 'Selecciona una opción.';
    if ($d['tipo_documento'] === 'DNI') {
        if (!preg_match('/^\d{8}$/', $d['dni_ruc'])) $errors['dni_ruc'] = 'El DNI debe tener exactamente 8 dígitos.';
    } else {
        if (!preg_match('/^\d{11}$/', $d['dni_ruc'])) $errors['dni_ruc'] = 'El RUC debe tener exactamente 11 dígitos.';
    }
    if (strlen($d['nombre'])   < 2) $errors['nombre']   = 'Ingresa tu nombre (mínimo 2 letras).';
    if (strlen($d['apellido']) < 2) $errors['apellido'] = 'Ingresa tu apellido (mínimo 2 letras).';
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Correo electrónico no válido.';
    if (!preg_match('/^[\d\s\+\-]{7,15}$/', $d['celular'])) $errors['celular'] = 'Número de celular no válido (7-15 dígitos).';
    if (!empty($d['fecha_nacimiento'])) {
        $fnac = DateTime::createFromFormat('Y-m-d', $d['fecha_nacimiento']);
        if (!$fnac || $fnac > new DateTime()) $errors['fecha_nacimiento'] = 'Fecha de nacimiento no válida.';
    }
    if (strlen($pass) < 6) $errors['password']  = 'La contraseña debe tener mínimo 6 caracteres.';
    if ($pass !== $pass2)  $errors['password2']  = 'Las contraseñas no coinciden.';

    if (empty($errors)) {
        $pdo = getDB();

        $s = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $s->execute([$d['email']]);
        if ($s->fetch()) $errors['email'] = 'Este correo ya está registrado. ¿Ya tienes cuenta?';

        $s = $pdo->prepare("SELECT id FROM usuarios WHERE dni_ruc = ?");
        $s->execute([$d['dni_ruc']]);
        if ($s->fetch()) $errors['dni_ruc'] = 'Este ' . $d['tipo_documento'] . ' ya está registrado.';
    }

    if (empty($errors)) {
        $pdo   = getDB();
        $hash  = password_hash($pass, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));
        $expira = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $rolCalculado = calcularRolEncuesta($enc['motivo'], $enc['frecuencia'], $enc['camaras'], $enc['servicio']);

        // Detectar qué columnas existen en la tabla usuarios
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_COLUMN);
            // Si tiene columnas de verificación, usarlas
            $tieneVerificacion = in_array('is_verified', $cols) && in_array('verify_token', $cols);
            // Si ya se corrió migracion_roles.sql, tendrá estas columnas
            $tieneEncuesta = in_array('encuesta_uso', $cols) && in_array('verificado', $cols);
            // Columna nueva para la 4ta pregunta (ver migracion_encuesta_servicio.sql)
            $tieneEncuestaServicio = in_array('encuesta_servicio', $cols);
        } catch(Exception $e) {
            $tieneVerificacion = false;
            $tieneEncuesta = false;
            $tieneEncuestaServicio = false;
        }

        try {
            $campos  = ['tipo_documento','dni_ruc','nombre','apellido','email','telefono','celular',
                        'direccion_entrega','fecha_nacimiento','password','rol','activo'];
            $valores = [
                $d['tipo_documento'], $d['dni_ruc'],
                $d['nombre'], $d['apellido'],
                $d['email'], $d['celular'], $d['celular'],
                ($d['direccion_entrega'] ?: null),
                ($d['fecha_nacimiento']  ?: null),
                $hash, $rolCalculado, 1
            ];

            if ($tieneVerificacion) {
                $campos[]  = 'is_verified';    $valores[] = 0;
                $campos[]  = 'verify_token';   $valores[] = $token;
                $campos[]  = 'verify_expires'; $valores[] = $expira;
            }
            if ($tieneEncuesta) {
                $campos[]  = 'verificado';        $valores[] = 0;
                $campos[]  = 'encuesta_uso';       $valores[] = $enc['motivo'];
                $campos[]  = 'encuesta_ruc';       $valores[] = $enc['frecuencia'];
                $campos[]  = 'encuesta_volumen';   $valores[] = $enc['camaras'];
            }
            if (!empty($tieneEncuestaServicio)) {
                $campos[]  = 'encuesta_servicio'; $valores[] = $enc['servicio'];
            }

            $placeholders = implode(',', array_fill(0, count($valores), '?'));
            $sqlIns = "INSERT INTO usuarios (" . implode(', ', $campos) . ") VALUES ($placeholders)";
            $pdo->prepare($sqlIns)->execute($valores);

            $nuevo_usuario_id = (int)$pdo->lastInsertId();
            $cupon_bienvenida_id = 1;
            asignarCuponAutomatico($pdo, $nuevo_usuario_id, $cupon_bienvenida_id, 15);

            // Si el rol calculado es "proyectista", avisar por correo (dato clave para verificarlo a mano)
            if ($rolCalculado === 'proyectista' && function_exists('sendNotificacionRolEspecial')) {
                try { sendNotificacionRolEspecial($d, $rolCalculado); } catch (Exception $e) { /* no bloquear el registro */ }
            }

            // Intentar enviar email de verificación
            $mailSent = false;
            if ($tieneVerificacion && function_exists('sendVerificationEmail')) {
                try {
                    $mailSent = sendVerificationEmail($d['email'], $d['nombre'], $token);
                } catch(Exception $e) {
                    $mailSent = false;
                }
            }

            if ($tieneVerificacion && $mailSent) {
                $_SESSION['flash_message'] = '¡Cuenta creada! Revisa tu correo para verificarla antes de iniciar sesión.';
                $_SESSION['flash_type']    = 'success';
                header('Location: ' . SITE_URL . '/login.php');
                exit;
            } elseif ($tieneVerificacion && !$mailSent) {
                // El correo no se envió, la cuenta está creada pero sin verificación
                $_SESSION['flash_message'] = 'Cuenta creada pero hubo error enviando correo. Por favor intenta más tarde.';
                $_SESSION['flash_type']    = 'error';
                header('Location: ' . SITE_URL . '/login.php');
                exit;
            } else {
                // Si no hay verificación por email, loguear directo
                $nuevoId = $pdo->lastInsertId();
                $usuarioNuevo = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                $usuarioNuevo->execute([$nuevoId]);
                $u = $usuarioNuevo->fetch();

                $_SESSION['usuario_id'] = $u['id'];
                $_SESSION['nombre']     = $u['nombre'];
                $_SESSION['apellido']   = $u['apellido'] ?? '';
                $_SESSION['email']      = $u['email'];
                $_SESSION['rol']        = $u['rol'];

                $_SESSION['flash_message'] = '¡Bienvenido, ' . sanitize($u['nombre']) . '! Tu cuenta fue creada correctamente.';
                $_SESSION['flash_type']    = 'success';

                header('Location: ' . SITE_URL . '/index.php');
                exit;
            }

        } catch(Exception $e) {
            $errors['general'] = 'Error al crear la cuenta. Intenta nuevamente. (' . $e->getMessage() . ')';
        }

        if (empty($errors)) {
            header('Location: ' . SITE_URL . '/login.php');
            exit;
        }
    }
}

$mostrarPasoDatos = ($_SERVER['REQUEST_METHOD'] === 'POST')
    && !isset($errors['enc_motivo']) && !isset($errors['enc_frecuencia'])
    && !isset($errors['enc_camaras']) && !isset($errors['enc_servicio']);

$pageTitle = 'Crear Cuenta';
include 'includes/header.php';
?>

<div class="auth-page">
  <div class="auth-card" style="max-width:560px;">
    <div class="auth-logo">
      <img src="/assets/img/logo.ico" alt="IP Tecnología" class="logo-img">
      <h2>Crear Cuenta</h2>
      <p>Regístrate para hacer seguimiento de tus pedidos</p>
    </div>

    <?php if (isset($errors['general'])): ?>
      <div class="alert alert-error" style="margin-bottom:16px;">
        <i class="fas fa-times-circle"></i> <?= sanitize($errors['general']) ?>
      </div>
    <?php elseif ($errors): ?>
      <div class="alert alert-error" style="margin-bottom:16px;">
        <i class="fas fa-exclamation-triangle"></i> Corrige los errores indicados abajo.
      </div>
    <?php endif; ?>

    <form method="POST" id="form-registro">

      <!-- ══════════ PASO 1: ENCUESTA (todo por selección) ══════════ -->
      <div id="paso-encuesta" style="<?= $mostrarPasoDatos ? 'display:none;' : '' ?>">
        <p style="font-size:13px;color:var(--gris3);margin-bottom:18px;">Antes de crear tu cuenta, cuéntanos un poco sobre ti para mostrarte los precios y beneficios correctos.</p>

        <div class="form-group">
          <label>1. ¿Cuál es el motivo principal de tu compra? *</label>
          <div class="enc-opciones" data-name="enc_motivo">
            <label class="enc-op"><input type="radio" name="enc_motivo" value="instalar_propio" <?= $enc['motivo']==='instalar_propio'?'checked':'' ?>><span>Instalar en mi casa o negocio</span></label>
            <label class="enc-op"><input type="radio" name="enc_motivo" value="disenio_proyecto" <?= $enc['motivo']==='disenio_proyecto'?'checked':'' ?>><span>Diseñar un proyecto para un cliente</span></label>
            <label class="enc-op"><input type="radio" name="enc_motivo" value="instalar_terceros" <?= $enc['motivo']==='instalar_terceros'?'checked':'' ?>><span>Instalar equipos para terceros</span></label>
            <label class="enc-op"><input type="radio" name="enc_motivo" value="reventa" <?= $enc['motivo']==='reventa'?'checked':'' ?>><span>Comprar para revender</span></label>
          </div>
          <?php if(isset($errors['enc_motivo'])): ?><span class="form-error"><?= $errors['enc_motivo'] ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label>2. ¿Con qué frecuencia compras equipos de seguridad? *</label>
          <div class="enc-opciones" data-name="enc_frecuencia">
            <label class="enc-op"><input type="radio" name="enc_frecuencia" value="una_vez" <?= $enc['frecuencia']==='una_vez'?'checked':'' ?>><span>Una vez</span></label>
            <label class="enc-op"><input type="radio" name="enc_frecuencia" value="algunas_veces" <?= $enc['frecuencia']==='algunas_veces'?'checked':'' ?>><span>Algunas veces al año</span></label>
            <label class="enc-op"><input type="radio" name="enc_frecuencia" value="varias_veces" <?= $enc['frecuencia']==='varias_veces'?'checked':'' ?>><span>Varias veces al año</span></label>
          </div>
          <?php if(isset($errors['enc_frecuencia'])): ?><span class="form-error"><?= $errors['enc_frecuencia'] ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label>3. ¿Realizas instalaciones de cámaras? *</label>
          <div class="enc-opciones" data-name="enc_camaras">
            <label class="enc-op"><input type="radio" name="enc_camaras" value="si_frecuente" <?= $enc['camaras']==='si_frecuente'?'checked':'' ?>><span>Sí, frecuentemente</span></label>
            <label class="enc-op"><input type="radio" name="enc_camaras" value="a_veces" <?= $enc['camaras']==='a_veces'?'checked':'' ?>><span>A veces</span></label>
            <label class="enc-op"><input type="radio" name="enc_camaras" value="no_una_vez" <?= $enc['camaras']==='no_una_vez'?'checked':'' ?>><span>No, una sola vez</span></label>
          </div>
          <?php if(isset($errors['enc_camaras'])): ?><span class="form-error"><?= $errors['enc_camaras'] ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label>4. ¿Qué servicio necesitas principalmente? *</label>
          <div class="enc-opciones" data-name="enc_servicio">
            <label class="enc-op"><input type="radio" name="enc_servicio" value="asesoria_proyectos" <?= $enc['servicio']==='asesoria_proyectos'?'checked':'' ?>><span>Asesoría para proyectos</span></label>
            <label class="enc-op"><input type="radio" name="enc_servicio" value="soporte_postventa" <?= $enc['servicio']==='soporte_postventa'?'checked':'' ?>><span>Soporte o post venta</span></label>
            <label class="enc-op"><input type="radio" name="enc_servicio" value="compras_volumen" <?= $enc['servicio']==='compras_volumen'?'checked':'' ?>><span>Compras por volumen</span></label>
            <label class="enc-op"><input type="radio" name="enc_servicio" value="necesito_asesor" <?= $enc['servicio']==='necesito_asesor'?'checked':'' ?>><span>Necesito un asesor para comprar</span></label>
          </div>
          <?php if(isset($errors['enc_servicio'])): ?><span class="form-error"><?= $errors['enc_servicio'] ?></span><?php endif; ?>
        </div>

        <button type="button" id="btn-continuar-registro" class="btn-submit">
          Continuar <i class="fas fa-arrow-right"></i>
        </button>
      </div>

      <!-- ══════════ PASO 2: DATOS DE REGISTRO ══════════ -->
      <div id="paso-datos" style="<?= $mostrarPasoDatos ? '' : 'display:none;' ?>">

      <!-- DOCUMENTO -->
      <div class="form-group">
        <label>Tipo y Número de Documento *</label>
        <div style="display:flex;gap:8px;">
          <select name="tipo_documento" id="tipo_documento" onchange="actualizarDoc()"
            style="width:105px;padding:11px 10px;background:var(--bg3);border:1.5px solid var(--borde);border-radius:var(--r);font-size:14px;color:var(--blanco);">
            <option value="DNI" <?= $d['tipo_documento']==='DNI'?'selected':'' ?>>DNI</option>
            <option value="RUC" <?= $d['tipo_documento']==='RUC'?'selected':'' ?>>RUC</option>
          </select>
          <div style="position:relative;flex:1;">
            <input type="text" name="dni_ruc" id="dni_ruc" value="<?= $d['dni_ruc'] ?>"
              placeholder="8 dígitos" maxlength="8" style="width:100%;"
              class="<?= isset($errors['dni_ruc'])?'input-error':'' ?>">
            <i id="doc-spinner" class="fas fa-spinner fa-spin" style="display:none;position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--gris3);"></i>
            <i id="doc-check" class="fas fa-check-circle" style="display:none;position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--verde);"></i>
          </div>
        </div>
        <div id="doc-msg" style="font-size:12.5px;margin-top:5px;"></div>
        <?php if(isset($errors['dni_ruc'])): ?><span class="form-error"><?= $errors['dni_ruc'] ?></span><?php endif; ?>
      </div>

      <!-- NOMBRE Y APELLIDO -->
      <div class="form-row">
        <div class="form-group">
          <label>Nombre *</label>
          <input type="text" name="nombre" id="campo-nombre" value="<?= $d['nombre'] ?>" placeholder="Ej: Juan"
            class="<?= isset($errors['nombre'])?'input-error':'' ?>">
          <?php if(isset($errors['nombre'])): ?><span class="form-error"><?= $errors['nombre'] ?></span><?php endif; ?>
        </div>
        <div class="form-group">
          <label>Apellido *</label>
          <input type="text" name="apellido" id="campo-apellido" value="<?= $d['apellido'] ?>" placeholder="Ej: Pérez García"
            class="<?= isset($errors['apellido'])?'input-error':'' ?>">
          <?php if(isset($errors['apellido'])): ?><span class="form-error"><?= $errors['apellido'] ?></span><?php endif; ?>
        </div>
      </div>

      <!-- CORREO -->
      <div class="form-group">
        <label>Correo Electrónico *</label>
        <input type="email" name="email" value="<?= $d['email'] ?>" placeholder="tucorreo@ejemplo.com"
          class="<?= isset($errors['email'])?'input-error':'' ?>">
        <?php if(isset($errors['email'])): ?><span class="form-error"><?= $errors['email'] ?></span><?php endif; ?>
      </div>

      <!-- CELULAR Y FECHA -->
      <div class="form-row">
        <div class="form-group">
          <label>Celular *</label>
          <input type="tel" name="celular" value="<?= $d['celular'] ?>" placeholder="987 654 321"
            class="<?= isset($errors['celular'])?'input-error':'' ?>">
          <?php if(isset($errors['celular'])): ?><span class="form-error"><?= $errors['celular'] ?></span><?php endif; ?>
        </div>
        <div class="form-group">
          <label>Fecha de Nacimiento <span style="color:var(--gris3);font-weight:400;">(opcional)</span></label>
          <input type="date" name="fecha_nacimiento" value="<?= $d['fecha_nacimiento'] ?>" max="<?= date('Y-m-d') ?>"
            class="<?= isset($errors['fecha_nacimiento'])?'input-error':'' ?>">
          <?php if(isset($errors['fecha_nacimiento'])): ?><span class="form-error"><?= $errors['fecha_nacimiento'] ?></span><?php endif; ?>
        </div>
      </div>

      <!-- DIRECCIÓN -->
      <div class="form-group">
        <label>Dirección de Entrega <span style="color:var(--gris3);font-weight:400;">(opcional)</span></label>
        <input type="text" name="direccion_entrega" value="<?= $d['direccion_entrega'] ?>"
          placeholder="Ej: Av. Javier Prado 1234, San Isidro, Lima">
      </div>

      <!-- CONTRASEÑAS -->
      <div class="form-row">
        <div class="form-group">
          <label>Contraseña *</label>
          <div style="position:relative;">
            <input type="password" name="password" id="pass1" placeholder="Mínimo 6 caracteres"
              style="padding-right:44px;width:100%;"
              class="<?= isset($errors['password'])?'input-error':'' ?>">
            <button type="button" onclick="togglePass('pass1',this)"
              style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#888;font-size:16px;padding:4px;z-index:10;">
              <i class="fas fa-eye"></i>
            </button>
          </div>
          <?php if(isset($errors['password'])): ?><span class="form-error"><?= $errors['password'] ?></span><?php endif; ?>
        </div>
        <div class="form-group">
          <label>Repetir Contraseña *</label>
          <div style="position:relative;">
            <input type="password" name="password2" id="pass2" placeholder="Repite tu contraseña"
              style="padding-right:44px;width:100%;"
              class="<?= isset($errors['password2'])?'input-error':'' ?>">
            <button type="button" onclick="togglePass('pass2',this)"
              style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#888;font-size:16px;padding:4px;z-index:10;">
              <i class="fas fa-eye"></i>
            </button>
          </div>
          <?php if(isset($errors['password2'])): ?><span class="form-error"><?= $errors['password2'] ?></span><?php endif; ?>
        </div>
      </div>

      <div style="font-size:12px;color:var(--gris3);margin-bottom:18px;">
        Al registrarte aceptas nuestros <a href="<?= SITE_URL ?>/terminos.php" style="color:var(--amarillo);">Términos y Condiciones</a>.
      </div>

      <div style="display:flex;gap:10px;">
        <button type="button" onclick="volverEncuesta()" style="padding:14px 18px;background:var(--bg3);color:var(--gris2);border:1px solid var(--borde);border-radius:var(--r);font-weight:700;cursor:pointer;">
          <i class="fas fa-arrow-left"></i>
        </button>
        <button type="submit" class="btn-submit" style="flex:1;">
          <i class="fas fa-user-plus"></i> Crear Cuenta Gratis
        </button>
      </div>

      </div><!-- /paso-datos -->
    </form>

    <div class="auth-footer">¿Ya tienes cuenta? <a href="<?= SITE_URL ?>/login.php">Iniciar sesión</a></div>
  </div>
</div>

<style>
.enc-opciones { display: flex; flex-direction: column; gap: 8px; }
.enc-op {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 14px; border: 1.5px solid var(--borde); border-radius: var(--r);
  cursor: pointer; font-size: 13.5px; color: var(--gris2); background: var(--bg3);
  transition: all .15s;
}
.enc-op input { accent-color: var(--amarillo); width: 16px; height: 16px; flex-shrink: 0; }
.enc-op:hover { border-color: var(--amarillo); }
.enc-op:has(input:checked) { border-color: var(--amarillo); background: rgba(237,232,42,.08); color: var(--blanco); font-weight: 600; }
</style>

<script>
function volverEncuesta() {
  document.getElementById('paso-datos').style.display = 'none';
  document.getElementById('paso-encuesta').style.display = '';
  window.scrollTo({top:0, behavior:'smooth'});
}
document.getElementById('btn-continuar-registro').addEventListener('click', function() {
  const grupos = ['enc_motivo', 'enc_frecuencia', 'enc_camaras', 'enc_servicio'];
  let ok = true;
  grupos.forEach(function(nombre) {
    const marcado = document.querySelector('input[name="' + nombre + '"]:checked');
    const cont = document.querySelector('.enc-opciones[data-name="' + nombre + '"]');
    if (!marcado) { ok = false; cont.style.borderRadius = '10px'; cont.style.outline = '2px solid #e53935'; }
    else { cont.style.outline = 'none'; }
  });
  if (!ok) { alert('Por favor responde las 4 preguntas para continuar.'); return; }
  document.getElementById('paso-encuesta').style.display = 'none';
  document.getElementById('paso-datos').style.display = '';
  window.scrollTo({top:0, behavior:'smooth'});
});
function togglePass(id, btn) {
  const i = document.getElementById(id);
  const p = i.type === 'password';
  i.type = p ? 'text' : 'password';
  btn.innerHTML = p ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
}
function actualizarDoc() {
  const t = document.getElementById('tipo_documento').value;
  const c = document.getElementById('dni_ruc');
  c.maxLength = t === 'DNI' ? 8 : 11;
  c.placeholder = t === 'DNI' ? '8 dígitos' : '11 dígitos';
  c.value = ''; c.focus();
  desbloquearNombreApellido();
  document.getElementById('doc-msg').textContent = '';
  document.getElementById('doc-msg').style.color = '';
}

const campoNombre   = document.getElementById('campo-nombre');
const campoApellido = document.getElementById('campo-apellido');
const docSpinner    = document.getElementById('doc-spinner');
const docCheck      = document.getElementById('doc-check');
const docMsg        = document.getElementById('doc-msg');

function bloquearNombreApellido() {
  campoNombre.readOnly = true;
  campoApellido.readOnly = true;
  campoNombre.style.background = 'var(--bg3)';
  campoApellido.style.background = 'var(--bg3)';
}
function desbloquearNombreApellido() {
  campoNombre.readOnly = false;
  campoApellido.readOnly = false;
  campoNombre.style.background = '';
  campoApellido.style.background = '';
}

let docTimeout = null;
document.getElementById('dni_ruc').addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '');
  desbloquearNombreApellido();
  docCheck.style.display = 'none';
  docMsg.textContent = '';

  const tipo = document.getElementById('tipo_documento').value;
  const largoEsperado = tipo === 'DNI' ? 8 : 11;

  clearTimeout(docTimeout);
  if (this.value.length !== largoEsperado) { docSpinner.style.display = 'none'; return; }

  const numero = this.value;
  docTimeout = setTimeout(() => consultarDocumento(tipo, numero), 300);
});

function consultarDocumento(tipo, numero) {
  docSpinner.style.display = '';
  docMsg.textContent = '';
  docMsg.style.color = '';

  const fd = new FormData();
  fd.append('tipo', tipo);
  fd.append('numero', numero);

  fetch('includes/consulta-documento.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      docSpinner.style.display = 'none';
      if (data.success) {
        campoNombre.value = data.nombre || '';
        campoApellido.value = data.apellido || '';
        bloquearNombreApellido();
        docCheck.style.display = '';
        docMsg.style.color = 'var(--verde)';
        docMsg.textContent = tipo === 'RUC' ? 'Razón social verificada.' : 'Datos verificados.';
      } else {
        desbloquearNombreApellido();
        docMsg.style.color = 'var(--rojo)';
        docMsg.textContent = data.message || 'No se pudo verificar. Completa tus datos manualmente.';
      }
    })
    .catch(() => {
      docSpinner.style.display = 'none';
      desbloquearNombreApellido();
      docMsg.style.color = 'var(--rojo)';
      docMsg.textContent = 'No se pudo conectar al servicio de verificación. Completa tus datos manualmente.';
    });
}
</script>

<?php include 'includes/footer.php'; ?>