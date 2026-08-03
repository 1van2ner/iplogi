// ============================================================
// IP Tecnología Perú — main.js
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

  // ── Cerrar flash automáticamente después de 5s ────────────
  const flash = document.querySelector('.flash');
  if (flash) {
    setTimeout(() => {
      flash.style.transition = 'opacity .5s';
      flash.style.opacity = '0';
      setTimeout(() => flash.remove(), 500);
    }, 5000);
  }

  // ── Toggle contraseña (ojo) ───────────────────────────────
  document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', function () {
      const input = this.previousElementSibling;
      if (!input) return;
      const isText = input.type === 'text';
      input.type = isText ? 'password' : 'text';
      this.innerHTML = isText
        ? '<i class="fas fa-eye"></i>'
        : '<i class="fas fa-eye-slash"></i>';
    });
  });

  // ── Animación de tarjetas al hacer scroll ─────────────────
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.prod-card, .cat-card, .icard, .sede-card').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'opacity .4s ease, transform .4s ease';
    observer.observe(el);
  });

  // ── Validación de formularios básica ─────────────────────
  document.querySelectorAll('form[data-validate]').forEach(form => {
    form.addEventListener('submit', function (e) {
      let valid = true;
      this.querySelectorAll('[required]').forEach(field => {
        const err = field.parentElement.querySelector('.form-error');
        if (!field.value.trim()) {
          field.classList.add('input-error');
          if (err) err.style.display = 'block';
          valid = false;
        } else {
          field.classList.remove('input-error');
          if (err) err.style.display = 'none';
        }
      });
      if (!valid) e.preventDefault();
    });
  });

  // ── Contador de carrito: badge en header ─────────────────
  // (La función updateBadge se define en footer.php inline)

  // ── Smooth scroll para anclas internas ───────────────────
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', function (e) {
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  // ── Botón "volver arriba" dinámico ────────────────────────
  const backTop = document.createElement('button');
  backTop.innerHTML = '<i class="fas fa-chevron-up"></i>';
  backTop.style.cssText = `
    position:fixed;bottom:100px;right:86px;width:42px;height:42px;
    background:var(--amarillo,#CEFF04);color:#000;border:none;
    border-radius:50%;cursor:pointer;font-size:16px;
    box-shadow:0 4px 14px rgba(0,0,0,.2);
    display:none;align-items:center;justify-content:center;
    z-index:888;transition:opacity .3s;
  `;
  document.body.appendChild(backTop);

  window.addEventListener('scroll', () => {
    backTop.style.display = window.scrollY > 400 ? 'flex' : 'none';
  });
  backTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

  // ── Resaltar nav activo según URL ─────────────────────────
  const current = window.location.pathname.split('/').pop();
  document.querySelectorAll('.nav-links a').forEach(a => {
    if (a.getAttribute('href') && a.getAttribute('href').includes(current) && current !== '') {
      a.classList.add('active');
    }
  });

});