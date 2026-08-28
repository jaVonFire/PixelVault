/* ============================================================
   PRODUCTOS - cargados desde la BASE DE DATOS via la API.
   Ya no estan escritos en el codigo.
   ============================================================ */
var currentFilter = 'all';
var allProducts = [];

/* Carga el catalogo desde el backend */
function loadProducts(todos) {
  var params = {};
  if (todos) params.todos = 1;
  return apiGet('productos', params).then(function (data) {
    allProducts = data.productos || [];
    return allProducts;
  }).catch(function (err) {
    showToast(err.message, 'error');
    allProducts = [];
    return allProducts;
  });
}

function stockLabel(p) {
  var s = p.cantidad;
  if (s <= 0) return '<div class="product-stock out"><i class="fas fa-ban"></i> ' + t('stock_out') + '</div>';
  if (s <= p.stock_minimo) return '<div class="product-stock low"><i class="fas fa-fire"></i> ' + t('stock_low', { n: s }) + '</div>';
  return '<div class="product-stock in"><i class="fas fa-box"></i> ' + t('stock_in', { n: s }) + '</div>';
}

function renderProductCard(p) {
  var s = p.cantidad;
  var disponible = p.estado === 'activo';
  return '<div class="product-card" data-cat="' + p.categoria_id + '">' +
    '<img class="product-img" src="' + p.imagen + '" alt="' + escapeHtml(p.nombre) + '" loading="lazy">' +
    '<div class="product-body">' +
      '<div class="product-cat">' + escapeHtml(p.categoria) + '</div>' +
      '<div class="product-name">' + escapeHtml(p.nombre) + '</div>' +
      '<div class="product-price">' + formatPrice(p.precio) + ' <small>USD ' + Number(p.precio).toFixed(2) + '</small></div>' +
      stockLabel(p) +
      '<button class="product-btn" ' + ((s <= 0 || !disponible) ? 'disabled' : '') +
        ' onclick="addToCart(' + p.id + ')"><i class="fas fa-cart-plus"></i> ' +
        (s <= 0 ? t('no_stock') : t('add_cart')) + '</button>' +
    '</div></div>';
}

function renderFeatured() {
  var grid = document.getElementById('featured-grid');
  if (!grid) return;
  grid.innerHTML = allProducts.filter(function (p) { return p.estado === 'activo'; }).slice(0, 4).map(renderProductCard).join('');
}

function renderProducts(filter) {
  currentFilter = filter || 'all';
  var grid = document.getElementById('products-grid');
  if (!grid) return;
  var list = currentFilter === 'all'
    ? allProducts
    : allProducts.filter(function (p) { return String(p.categoria_id) === String(currentFilter); });
  grid.innerHTML = list.map(renderProductCard).join('');
}

function filterProducts(cat, btn) {
  document.querySelectorAll('#filter-bar .nav-btn').forEach(function (b) { b.classList.remove('active'); });
  if (btn) btn.classList.add('active');
  renderProducts(cat);
}

/* Estadisticas del home (desde BD).
   El total de clientes y el rating se muestran solo al
   administrador (requieren permisos); para el resto quedan
   ocultos de forma limpia. */
function updateHomeStats() {
  loadProducts().then(function (prods) {
    var el1 = document.getElementById('stat-products');
    if (el1) el1.textContent = prods.filter(function (p) { return p.estado === 'activo'; }).length;
  });
}
