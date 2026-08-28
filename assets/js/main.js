/* ============================================================
   INICIALIZACION - conectada con la API (backend + MySQL)
   ============================================================ */

function initSelectors() {
  var cs = document.getElementById('currencySelect');
  if (cs) {
    var opts = '';
    Object.keys(CURRENCIES).forEach(function (c) {
      opts += '<option value="' + c + '" ' + (currentCurrency === c ? 'selected' : '') + '>' + c + '</option>';
    });
    cs.innerHTML = opts;
  }
  var countries = ['Mexico','Colombia','Argentina','Chile','Peru','Bolivia','Uruguay','Paraguay','Guatemala','Costa Rica','Republica Dominicana','Venezuela','Brasil','Espana','Otro'];
  var csel = document.getElementById('reg-country');
  if (csel) csel.innerHTML = '<option value="">' + (currentLang() === 'es' ? 'Seleccionar...' : 'Select...') + '</option>' + countries.map(function (c) { return '<option>' + c + '</option>'; }).join('');
}

/* Carga los pedidos del usuario (para el perfil) y los deja en profileOrders */
function loadMyOrders() {
  if (!currentUser) { profileOrders = []; return Promise.resolve([]); }
  return apiGet('pedidos').then(function (data) {
    profileOrders = data.pedidos || [];
    return profileOrders;
  }).catch(function () { profileOrders = []; return []; });
}

/* Carga inicial de la app: sesion, catalogo, carrito, idioma */
(function init() {
  document.documentElement.setAttribute('lang', currentLang());

  /* Inicializa selectores y traducciones estaticas */
  initSelectors();
  applyTranslations();

  /* Carga el usuario logueado, el carrito y el catalogo */
  loadMe().then(function (u) {
    updateAuthUI();
    if (typeof updateRateButtonState === 'function') updateRateButtonState();
    if (u) return refreshCart().then(function () { return u; });
    return u;
  }).then(function (u) {
    return loadProducts().then(function () { return u; });
  }).then(function (u) {
    var page = document.body.getAttribute('data-page');
    var tab = new URLSearchParams(window.location.search).get('tab');

    if (document.getElementById('featured-grid')) renderFeatured();
    if (document.getElementById('stat-products')) updateHomeStats();
    if (page === 'products') renderProducts(currentFilter);
    if (page === 'profile') {
      loadMyOrders().then(function () {
        renderProfile();
        if (tab) showProfileTab(tab);
      });
    }
    if (page === 'auth' && tab === 'register') showAuthTab('register');
    if (page === 'admin') initAdmin();
    if (u) updateCartBadge();
  });

  /* Selector de idioma */
  var langBtn = document.getElementById('langSelect');
  if (langBtn) langBtn.value = currentLang();
  setTimeout(function () { applyTranslations(); }, 0);
})();
