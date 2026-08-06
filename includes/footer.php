<footer class="footer">
  <div class="footer-top">
    <div class="container">
      <div class="footer-grid">

        <!-- BRAND -->
        <div class="footer-brand">
          <div class="flogo-wrap">
            <img src="/assets/img/logop.jpg" alt="IP Tecnología Perú" class="logo-img">
            <div>
              <span class="flogo-name">Tecnología Perú</span>
              <span class="flogo-sub">Seguridad &amp; Conectividad</span>
            </div>
          </div>
          <p>Más de 10 años brindando soluciones tecnológicas de calidad. Cámaras IP, redes, impresoras y UPS de las mejores marcas a nivel nacional.</p>
          <div class="social">
            <a href="https://www.facebook.com/share/1BQtUxkbqv/" title="Facebook" target="_blank"><i class="fab fa-facebook-f"></i></a>
            <a href="https://www.instagram.com/iptecnologiaperu" title="Instagram" target="_blank"><i class="fab fa-instagram"></i></a>
            <a href="https://wa.me/51950923109" title="WhatsApp" target="_blank"><i class="fab fa-whatsapp"></i></a>
            <a href="https://youtube.com/@iptecnologiaperu9328" title="YouTube" target="_blank"><i class="fab fa-youtube"></i></a>
            <a href="https://www.tiktok.com/@iptecnologiaperu" title="TikTok" target="_blank"><i class="fab fa-tiktok"></i></a>
          </div>
          <div class="fbadges" style="margin-top:16px;">
            <span class="fbadge">✓ 5 años de Garantía Oficial</span>
            <span class="fbadge">✓ RUC: 20601744164</span>
            <span class="fbadge">✓ +10 años</span>
          </div>
        </div>

        <!-- CATEGORIAS -->
        <div class="footer-col">
          <h4>Categorías</h4>
          <ul>
            <li><a href="<?= SITE_URL ?>/productos.php?categoria=1"><i class="fas fa-chevron-right"></i> Cámaras IP</a></li>
            <li><a href="<?= SITE_URL ?>/productos.php?categoria=2"><i class="fas fa-chevron-right"></i> Routers y Redes</a></li>
            <li><a href="<?= SITE_URL ?>/productos.php?categoria=3"><i class="fas fa-chevron-right"></i> Impresoras</a></li>
            <li><a href="<?= SITE_URL ?>/productos.php?categoria=4"><i class="fas fa-chevron-right"></i> UPS / Estabilizadores</a></li>
            <li><a href="<?= SITE_URL ?>/productos.php"><i class="fas fa-chevron-right"></i> Ver todo el catálogo</a></li>
          </ul>
        </div>

        <!-- MI CUENTA -->
        <div class="footer-col">
          <h4>Mi Cuenta</h4>
          <ul>
            <li><a href="<?= SITE_URL ?>/login.php"><i class="fas fa-chevron-right"></i> Iniciar Sesión</a></li>
            <li><a href="<?= SITE_URL ?>/registro.php"><i class="fas fa-chevron-right"></i> Crear Cuenta</a></li>
            <li><a href="<?= SITE_URL ?>/mis-pedidos.php"><i class="fas fa-chevron-right"></i> Mis Pedidos</a></li>
            <li><a href="<?= SITE_URL ?>/carrito.php"><i class="fas fa-chevron-right"></i> Mi Carrito</a></li>
            <li><a href="<?= SITE_URL ?>/perfil.php"><i class="fas fa-chevron-right"></i> Mi Perfil</a></li>
          </ul>
        </div>

        <!-- CONTACTO -->
        <div class="footer-col">
          <h4>Contacto</h4>
          <div class="contact-item"><i class="fas fa-map-marker-alt"></i><span>Sede Lima: Jr. Paruro Nº 1322 - Sótano Tda - S112</span></div>
          <div class="contact-item"><i class="fas fa-map-marker-alt"></i><span>Sede La Molina: Av. Melgarejo Nº 595</span></div>
          <div class="contact-item"><i class="fas fa-map-marker-alt"></i><span>Sede Ate: C.C. Plaza Vitarte, Block F Tda. 304</span></div> 
          <div class="contact-item"><i class="fab fa-whatsapp"></i><a href="https://wa.me/51950923109" style="color:var(--primary);">+51 950 923 109</a></div>
          <div class="contact-item"><i class="fas fa-envelope"></i><a href="mailto:ventas@iptecnologiaperu.com" style="color:var(--primary);">ventas@iptecnologiaperu.com</a></div>
          <div class="contact-item"><i class="fas fa-clock"></i><span>Lun–Sáb: 9:00am – 6:00pm</span></div>
        </div>

      </div>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="container">
      <span>© <?= date('Y') ?> IP Tecnología Perú. Todos los derechos reservados.</span>
      <span>
        <a href="<?= SITE_URL ?>/terminos.php">Términos y Condiciones</a> &nbsp;·&nbsp;
        <a href="<?= SITE_URL ?>/privacidad.php">Política de Privacidad</a> &nbsp;·&nbsp;
        <a href="<?= SITE_URL ?>/reclamaciones.php">Libro de Reclamaciones</a>
      </span>
    </div>
  </div>
</footer>

<div id="cart-toast" class="toast">
  <i class="fas fa-check-circle"></i>
  <span id="toast-msg">¡Producto agregado!</span>
</div>

<script src="<?= SITE_URL ?>/assets/js/main.js"></script>

<!-- ── WIDGET WHATSAPP FLOTANTE ──────────────────────────────── -->
<div id="wsp-widget">
  <div id="wsp-label">¿Necesitas ayuda?</div>
  <button id="wsp-toggle" onclick="toggleWspMenu()" title="Escríbenos por WhatsApp">
    <i class="fab fa-whatsapp" id="wsp-icon-open"></i>
    <i class="fas fa-times"    id="wsp-icon-close" style="display:none;"></i>
    <span class="wsp-badge">1</span>
  </button>
  <div id="wsp-menu" style="display:none;">
    <div class="wsp-header">
      <div class="wsp-avatar"><i class="fab fa-whatsapp"></i></div>
      <div>
        <div class="wsp-name">IP Tecnología Perú</div>
        <div class="wsp-status"><span class="wsp-dot"></span> En línea</div>
      </div>
      <button class="wsp-close-btn" onclick="toggleWspMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="wsp-body">
      <div class="wsp-bubble">
        <p>👋 ¡Hola! ¿En qué podemos ayudarte hoy?</p>
        <span class="wsp-time">Ahora</span>
      </div>
    </div>
    <div class="wsp-opciones">
      <a href="https://wa.me/51950923109?text=Hola,%20necesito%20una%20cotización" target="_blank" class="wsp-opcion">
        <i class="fas fa-file-invoice-dollar"></i> Solicitar cotización
      </a>
      <a href="https://wa.me/51950923109?text=Hola,%20necesito%20soporte%20técnico" target="_blank" class="wsp-opcion">
        <i class="fas fa-tools"></i> Soporte técnico
      </a>
      <a href="https://wa.me/51950923109?text=Hola,%20tengo%20una%20consulta%20de%20producto" target="_blank" class="wsp-opcion">
        <i class="fas fa-question-circle"></i> Consulta de producto
      </a>
      <a href="https://wa.me/51950923109?text=Hola,%20consulta%20sobre%20garantía" target="_blank" class="wsp-opcion">
        <i class="fas fa-shield-alt"></i> Garantía / Devolución
      </a>
    </div>
  </div>
</div>
<style>
/* ── Widget WhatsApp flotante ── */
#wsp-widget{position:fixed;bottom:24px;right:24px;z-index:1999;font-family:'Inter',sans-serif;display:flex;flex-direction:column;align-items:flex-end;gap:8px;}

/* Etiqueta flotante "¿Necesitas ayuda?" */
#wsp-label{
  background:#075e54;color:#fff;font-size:13px;font-weight:700;
  padding:7px 14px;border-radius:20px;white-space:nowrap;
  box-shadow:0 4px 16px rgba(0,0,0,.2);
  animation:wspLabelPop .4s ease .8s both;
  cursor:pointer;
}
#wsp-label:hover{background:#128c7e;}
@keyframes wspLabelPop{from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:translateY(0);}}

/* Botón principal — más grande */
#wsp-toggle{
  width:68px;height:68px;border-radius:50%;
  background:linear-gradient(135deg,#25d366,#128c7e);
  border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  font-size:36px;color:#fff;
  box-shadow:0 6px 28px rgba(37,211,102,.6);
  transition:transform .2s,box-shadow .2s;
  position:relative;
}
#wsp-toggle:hover{transform:scale(1.1);box-shadow:0 8px 36px rgba(37,211,102,.75);}

/* Anillo pulsante detrás del botón */
#wsp-toggle::before{
  content:'';position:absolute;inset:-6px;border-radius:50%;
  border:3px solid rgba(37,211,102,.4);
  animation:wspRing 2s ease infinite;
}
#wsp-toggle::after{
  content:'';position:absolute;inset:-14px;border-radius:50%;
  border:2px solid rgba(37,211,102,.2);
  animation:wspRing 2s ease .4s infinite;
}
@keyframes wspRing{0%{transform:scale(.9);opacity:.8;}100%{transform:scale(1.2);opacity:0;}}

/* Badge rojo */
.wsp-badge{
  position:absolute;top:-2px;right:-2px;
  background:#e53935;color:#fff;
  width:22px;height:22px;border-radius:50%;
  font-size:12px;font-weight:800;
  display:flex;align-items:center;justify-content:center;
  border:2px solid #fff;
  animation:wspBounce 1.5s ease infinite;
}
@keyframes wspBounce{0%,100%{transform:scale(1);}40%{transform:scale(1.25);}60%{transform:scale(.95);}}

/* Menú desplegable */
#wsp-menu{position:absolute;bottom:86px;right:0;width:310px;background:#fff;
  border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,.18);overflow:hidden;
  animation:wspSlide .2s ease;}
@keyframes wspSlide{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}
.wsp-header{background:#075e54;padding:16px;display:flex;align-items:center;gap:12px;}
.wsp-avatar{width:46px;height:46px;border-radius:50%;background:#25d366;
  display:flex;align-items:center;justify-content:center;font-size:24px;color:#fff;flex-shrink:0;}
.wsp-name{color:#fff;font-weight:800;font-size:15px;}
.wsp-status{display:flex;align-items:center;gap:5px;font-size:12px;color:rgba(255,255,255,.85);}
.wsp-dot{width:8px;height:8px;border-radius:50%;background:#4eff91;flex-shrink:0;
  box-shadow:0 0 6px #4eff91;animation:wspDotBlink 2s infinite;}
@keyframes wspDotBlink{0%,100%{opacity:1;}50%{opacity:.4;}}
.wsp-close-btn{margin-left:auto;background:none;border:none;color:rgba(255,255,255,.7);cursor:pointer;font-size:18px;padding:4px;}
.wsp-close-btn:hover{color:#fff;}
.wsp-body{background:#e5ddd5;padding:16px;}
.wsp-bubble{background:#fff;border-radius:8px 8px 8px 0;padding:12px 14px;
  display:inline-block;box-shadow:0 1px 4px rgba(0,0,0,.1);max-width:95%;}
.wsp-bubble p{margin:0;font-size:14px;color:#333;line-height:1.6;}
.wsp-time{font-size:10px;color:#aaa;display:block;text-align:right;margin-top:4px;}
.wsp-opciones{padding:14px;background:#fff;}
.wsp-opcion{display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:10px;
  background:#f0fdf4;color:#075e54;font-size:13px;font-weight:700;text-decoration:none;
  margin-bottom:8px;transition:all .15s;border:1px solid rgba(37,211,102,.2);}
.wsp-opcion:last-child{margin-bottom:0;}
.wsp-opcion:hover{background:#dcfce7;transform:translateX(-2px);}
.wsp-opcion i{width:18px;text-align:center;color:#25d366;font-size:15px;}
@media(max-width:420px){
  #wsp-menu{width:calc(100vw - 32px);right:-4px;}
  #wsp-toggle{width:60px;height:60px;font-size:30px;}
}
/* ══════════════════════════════════════════════════════
   FIX GLOBAL — NOMBRE USUARIO + WHATSAPP WIDGET
   ══════════════════════════════════════════════════════ */

/* 1. Nombre del admin/usuario visible en todos los tamaños */
.user-btn span {
  display: inline !important;
  max-width: 90px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  vertical-align: middle;
}

/* 2. WhatsApp widget — contener sin cortar el badge ni los anillos */
#wsp-widget {
  position: fixed !important;
  bottom: 24px !important;
  right: 24px !important;
  z-index: 1999 !important;
  display: flex !important;
  flex-direction: column !important;
  align-items: flex-end !important;
  gap: 8px !important;
  /* Crítico: dar espacio para que los anillos pulsantes no se corten */
  padding: 14px !important;
  margin: -14px !important;
  overflow: visible !important;
}

/* El botón principal con espacio para los anillos ::before y ::after */
#wsp-toggle {
  position: relative !important;
  overflow: visible !important;
  flex-shrink: 0 !important;
}

/* El badge rojo no se corta */
.wsp-badge {
  z-index: 10 !important;
  pointer-events: none !important;
}

/* El menú nunca se sale de pantalla */
#wsp-menu {
  position: absolute !important;
  bottom: 86px !important;
  right: 0 !important;
  width: 310px !important;
  max-width: calc(100vw - 48px) !important;
  box-sizing: border-box !important;
}

/* En móvil: menú anclado para no salirse por la derecha */
@media (max-width: 420px) {
  #wsp-widget {
    bottom: 14px !important;
    right: 14px !important;
    padding: 10px !important;
    margin: -10px !important;
  }
  #wsp-toggle {
    width: 60px !important;
    height: 60px !important;
    font-size: 30px !important;
  }
  #wsp-menu {
    width: calc(100vw - 28px) !important;
    /* Alinear al borde izquierdo de la pantalla */
    right: auto !important;
    left: calc(-100vw + 88px) !important;
  }
  #wsp-label {
    font-size: 12px !important;
    padding: 6px 11px !important;
  }
}
</style>
<script>
function toggleWspMenu(){
  const menu=document.getElementById('wsp-menu');
  const i1=document.getElementById('wsp-icon-open');
  const i2=document.getElementById('wsp-icon-close');
  const badge=document.querySelector('.wsp-badge');
  const label=document.getElementById('wsp-label');
  const open=menu.style.display==='none';
  menu.style.display=open?'block':'none';
  i1.style.display=open?'none':'flex';
  i2.style.display=open?'flex':'none';
  if(label) label.style.display=open?'none':'flex';
  if(open&&badge) badge.style.display='none';
}
// Ocultar label automáticamente a los 6 segundos
setTimeout(()=>{ const l=document.getElementById('wsp-label'); if(l) l.style.opacity='0'; },6000);
setTimeout(()=>{ const l=document.getElementById('wsp-label'); if(l) l.style.display='none'; },6400);
</script>

<!-- ── BANNER DE COOKIES ─────────────────────────────────────── -->
<div id="cookie-banner" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:9999;background:#111;border-top:2px solid #D7E022;padding:16px 24px;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;box-shadow:0 -4px 24px rgba(0,0,0,.5);">
  <p style="margin:0;font-size:13px;color:#ccc;line-height:1.6;flex:1;min-width:220px;">
    🍪 Usamos <strong style="color:#fff;">cookies</strong> para mejorar tu experiencia de compra, recordar tu carrito y analizar el tráfico del sitio.
    Al hacer clic en "Aceptar", consientes el uso de cookies conforme a nuestra
    <a href="<?= SITE_URL ?>/privacidad.php" style="color:#D7E022;text-decoration:none;font-weight:700;">Política de Privacidad</a>.
  </p>
  <div style="display:flex;gap:10px;flex-shrink:0;">
  <button onclick="acceptCookies()" style="background:#CEFF04;color:#000;border:none;padding:9px 20px;border-radius:8px;font-size:13px;font-weight:800;cursor:pointer;">Aceptar todas</button>    <button onclick="rejectCookies()" style="background:transparent;color:#ccc;border:1.5px solid #444;padding:9px 20px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">Solo esenciales</button>
  </div>
</div>
<script>
(function(){
  if (!localStorage.getItem('cookie_consent')) {
    document.getElementById('cookie-banner').style.display = 'flex';
  }
})();
function acceptCookies() {
  localStorage.setItem('cookie_consent', 'accepted');
  document.getElementById('cookie-banner').style.display = 'none';
}
function rejectCookies() {
  localStorage.setItem('cookie_consent', 'essential');
  document.getElementById('cookie-banner').style.display = 'none';
}
</script>
<script>
// ── Toast ────────────────────────────────────────────────────
function showToast(msg, ok = true) {
  const t = document.getElementById('cart-toast');
  if (!t) return;
  document.getElementById('toast-msg').textContent = msg;
  t.style.background = ok ? '#CEFF04' : '#e53935';
  t.style.color      = ok ? '#000'    : '#fff';
  t.style.display = 'flex';
  clearTimeout(t._timer);
  t._timer = setTimeout(() => { t.style.display = 'none'; }, 3000);
}

// ── Actualizar badge del carrito ─────────────────────────────
function updateBadge(n) {
  let b   = document.querySelector('.cart-badge');
  const btn = document.querySelector('.cart-btn');
  if (!btn) return;
  if (n > 0) {
    if (!b) {
      b = document.createElement('span');
      b.className = 'cart-badge';
      btn.appendChild(b);
    }
    b.textContent = n;
  } else {
    if (b) b.remove();
  }
}

// ── Agregar al carrito (delegado en document para cubrir
//    productos cargados dinámicamente) ────────────────────────
document.addEventListener('click', function(e) {
  if (e.target.closest('.btn-redeem')) return;
  const btn = e.target.closest('.btn-cart, .btn-add-cart, [data-action="agregar-carrito"]');
  if (!btn) return;

  const productoId = btn.dataset.id || btn.dataset.productoId;
  if (!productoId) return;

  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';

  fetch('<?= SITE_URL ?>/ajax/carrito.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body:    'action=agregar&producto_id=' + encodeURIComponent(productoId) + '&cantidad=1'
  })
  .then(r => {
    // Leer como texto primero para depurar posibles warnings de PHP
    return r.text();
  })
  .then(text => {
    // Extraer solo el JSON (ignora cualquier output de PHP antes del {)
    const start = text.indexOf('{');
    const clean = start !== -1 ? text.slice(start) : text;
    let d;
    try { d = JSON.parse(clean); } catch(err) {
      console.error('Respuesta no-JSON del servidor:', text);
      showToast('Error del servidor', false);
      return;
    }
    if (d.success) {
      showToast('¡Agregado al carrito!', true);
      updateBadge(d.cart_count);
    } else {
      showToast(d.message || 'No se pudo agregar', false);
    }
  })
  .catch(err => {
    console.error('Error fetch carrito:', err);
    showToast('Error de conexión', false);
  })
  .finally(() => {
    btn.disabled  = false;
    btn.innerHTML = orig;
  });
});
</script><!-- MODAL CONFIRMACIÓN GLOBAL -->
<div id="modal-confirm" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#1a1a1a;border:1.5px solid #333;border-radius:16px;padding:32px 28px;max-width:380px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.5);">
    <div style="width:56px;height:56px;background:rgba(206,255,4,.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:26px;">⚠️</div>
    <p id="modal-confirm-msg" style="font-size:16px;font-weight:700;color:#fff;margin-bottom:24px;line-height:1.5;"></p>
    <div style="display:flex;gap:10px;justify-content:center;">
      <button id="modal-confirm-cancel" style="padding:11px 24px;background:#2a2a2a;color:#aaa;border:1.5px solid #444;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;">Cancelar</button>
      <button id="modal-confirm-ok" style="padding:11px 24px;background:#CEFF04;color:#000;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;">Aceptar</button>
    </div>
  </div>
</div>

<script>
function confirmar(mensaje, callback) {
    const modal   = document.getElementById('modal-confirm');
    const msg     = document.getElementById('modal-confirm-msg');
    const btnOk   = document.getElementById('modal-confirm-ok');
    const btnCancel = document.getElementById('modal-confirm-cancel');

    msg.textContent = mensaje;
    modal.style.display = 'flex';

    // Limpiar listeners anteriores
    const okClone     = btnOk.cloneNode(true);
    const cancelClone = btnCancel.cloneNode(true);
    btnOk.parentNode.replaceChild(okClone, btnOk);
    btnCancel.parentNode.replaceChild(cancelClone, btnCancel);

    document.getElementById('modal-confirm-ok').addEventListener('click', () => {
        modal.style.display = 'none';
        callback(true);
    });
    document.getElementById('modal-confirm-cancel').addEventListener('click', () => {
        modal.style.display = 'none';
        callback(false);
    });
}
</script>
</body>
</html>