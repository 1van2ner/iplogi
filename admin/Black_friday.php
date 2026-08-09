<?php
require_once '../includes/config.php';
$pageTitle = 'Black Friday';
include '../includes/header.php';
?>

<!-- SECCIÓN BLACK FRIDAY -->
<section class="black-friday-section">
  <div class="bf-container">
    
    <!-- ENCABEZADO / BANNER -->
    <div class="bf-header">
      <div class="bf-badge">⚡ OFERTAS LIMITADAS</div>
      <h2 class="bf-title">BLACK <span>FRIDAY</span></h2>
      <p class="bf-subtitle">Llévate lo mejor en tecnología al precio más bajo del año. ¡Stock limitado!</p>
    </div>

    <!-- GRID DE PRODUCTOS / OFERTAS -->
    <div class="bf-grid">
      
      <!-- Producto 1 -->
      <div class="bf-card">
        <div class="bf-discount-tag">-40%</div>
        <div class="bf-img-placeholder">
          <i class="fas fa-camera"></i>
        </div>
        <div class="bf-content">
          <span class="bf-category">Cámara de Seguridad IP</span>
          <h3 class="bf-product-title">Kit Pro HD Visión Nocturna</h3>
          <div class="bf-prices">
            <span class="bf-old-price">S/ 399.00</span>
            <span class="bf-new-price">S/ 239.00</span>
          </div>
          <button class="bf-btn">Añadir al Carrito</button>
        </div>
      </div>

      <!-- Producto 2 -->
      <div class="bf-card">
        <div class="bf-discount-tag">-50%</div>
        <div class="bf-img-placeholder">
          <i class="fas fa-video"></i>
        </div>
        <div class="bf-content">
          <span class="bf-category">Videovigilancia</span>
          <h3 class="bf-product-title">Domotorizado 4K IA</h3>
          <div class="bf-prices">
            <span class="bf-old-price">S/ 599.00</span>
            <span class="bf-new-price">S/ 299.50</span>
          </div>
          <button class="bf-btn">Añadir al Carrito</button>
        </div>
      </div>

      <!-- Producto 3 -->
      <div class="bf-card">
        <div class="bf-discount-tag">-30%</div>
        <div class="bf-img-placeholder">
          <i class="fas fa-network-wired"></i>
        </div>
        <div class="bf-content">
          <span class="bf-category">Redes & Conectividad</span>
          <h3 class="bf-product-title">Switch PoE Gigabit 8 Puertos</h3>
          <div class="bf-prices">
            <span class="bf-old-price">S/ 250.00</span>
            <span class="bf-new-price">S/ 175.00</span>
          </div>
          <button class="bf-btn">Añadir al Carrito</button>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ESTINTO / CSS -->
<style>
.black-friday-section {
  background-color: #0b0b0b;
  color: #ffffff;
  padding: 60px 20px;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  border-top: 2px solid #D1FF05;
  border-bottom: 2px solid #D1FF05;
}

.bf-container {
  max-width: 1200px;
  margin: 0 auto;
}

.bf-header {
  text-align: center;
  margin-bottom: 45px;
}

.bf-badge {
  display: inline-block;
  background-color: rgba(209, 255, 5, 0.1);
  color: #D1FF05;
  border: 1px solid #D1FF05;
  padding: 6px 16px;
  font-size: 13px;
  font-weight: 700;
  border-radius: 20px;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 15px;
}

.bf-title {
  font-size: 42px;
  font-weight: 900;
  text-transform: uppercase;
  margin: 0;
  letter-spacing: 1.5px;
}

.bf-title span {
  color: #D1FF05;
  text-shadow: 0 0 20px rgba(209, 255, 5, 0.4);
}

.bf-subtitle {
  color: #a0a0a0;
  font-size: 16px;
  margin-top: 10px;
}

.bf-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 25px;
}

.bf-card {
  background-color: #141414;
  border: 1.5px solid #222222;
  border-radius: 12px;
  overflow: hidden;
  position: relative;
  transition: all 0.3s ease;
}

.bf-card:hover {
  border-color: #D1FF05;
  transform: translateY(-5px);
  box-shadow: 0 10px 25px rgba(209, 255, 5, 0.15);
}

.bf-discount-tag {
  position: absolute;
  top: 12px;
  left: 12px;
  background-color: #D1FF05;
  color: #000000;
  font-weight: 800;
  font-size: 12px;
  padding: 4px 8px;
  border-radius: 4px;
  z-index: 2;
}

.bf-img-placeholder {
  height: 180px;
  background-color: #1c1c1c;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #333333;
  font-size: 48px;
}

.bf-content {
  padding: 20px;
}

.bf-category {
  font-size: 12px;
  color: #888888;
  text-transform: uppercase;
  font-weight: 600;
}

.bf-product-title {
  font-size: 18px;
  font-weight: 700;
  color: #ffffff;
  margin: 8px 0 15px 0;
}

.bf-prices {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}

.bf-old-price {
  font-size: 14px;
  color: #777777;
  text-decoration: line-through;
}

.bf-new-price {
  font-size: 20px;
  font-weight: 800;
  color: #D1FF05;
}

.bf-btn {
  width: 100%;
  background-color: #D1FF05;
  color: #000000;
  border: none;
  padding: 12px;
  font-weight: 700;
  font-size: 14px;
  border-radius: 6px;
  cursor: pointer;
  text-transform: uppercase;
  transition: background-color 0.2s, box-shadow 0.2s;
}

.bf-btn:hover {
  background-color: #bce004;
  box-shadow: 0 0 12px rgba(209, 255, 5, 0.4);
}
</style>

<?php include '../includes/footer.php'; ?>